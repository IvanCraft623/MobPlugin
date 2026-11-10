<?php

/*
 *   __  __       _     _____  _             _
 *  |  \/  |     | |   |  __ \| |           (_)
 *  | \  / | ___ | |__ | |__) | |_   _  __ _ _ _ __
 *  | |\/| |/ _ \| '_ \|  ___/| | | | |/ _` | | '_ \
 *  | |  | | (_) | |_) | |    | | |_| | (_| | | | | |
 *  |_|  |_|\___/|_.__/|_|    |_|\__,_|\__, |_|_| |_|
 *                                      __/ |
 *                                     |___/
 *
 * A PocketMine-MP plugin that implements mobs AI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\navigation;

use Closure;
use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\IvanCraft623\Pathfinder\execution\PathSearch;
use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\IvanCraft623\Pathfinder\execution\SearchExecutor;
use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\IvanCraft623\Pathfinder\PathCursor;
use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\IvanCraft623\Pathfinder\PathInvalidation;
use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\IvanCraft623\Pathfinder\PathRepair;
use pocketmine\math\Vector3;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use function floor;

/**
 * Gets the paths of a navigation: runs the searches, reuses the current path as {@link RepathPolicy}
 * says, and ignores results nobody waits for.
 *
 * @internal
 */
final class PathRequester{
	/** Ticks between two recomputations of the path */
	public const RECOMPUTE_INTERVAL = 20;

	/** Block of the target of the last path computed */
	private ?Vector3 $target = null;

	private int $reach = 1;

	private int $computationId = 0;

	private int $splicesInARow = 0;

	/**
	 * Of the in-flight computation; requests made meanwhile share its promise.
	 *
	 * @phpstan-var PromiseResolver<PathCursor>|null
	 */
	private ?PromiseResolver $pendingResolver = null;

	private ?PathSearch $pendingSearch = null;

	/** Block changes that invalidated $invalidatedCursor since it was computed */
	private ?PathCursor $invalidatedCursor = null;
	private ?PathInvalidation $invalidation = null;

	private int $lastRecomputeTick = 0;

	private bool $recomputeDelayed = false;

	/**
	 * @phpstan-param Closure() : ?PathCursor $currentCursor   the path being followed
	 * @phpstan-param Closure() : SearchSetup $createSetup   read each time a search starts
	 * @phpstan-param Closure() : void        $onSearchStart
	 */
	public function __construct(
		private SearchExecutor $executor,
		private RepathPolicy $policy,
		private Closure $currentCursor,
		private Closure $createSetup,
		private Closure $onSearchStart
	){
	}

	public function isPending() : bool{
		return $this->pendingResolver !== null;
	}

	public function hasDelayedRecompute() : bool{
		return $this->recomputeDelayed;
	}

	/**
	 * The path to $target; the current path if it already goes there.
	 *
	 * @phpstan-return Promise<PathCursor>
	 */
	public function request(Vector3 $target, int $reach, ?PathCursor $current, ?float $maxDistanceFromStart = null) : Promise{
		return $this->startRequest(new PathRequest($target, $reach, $maxDistanceFromStart), $current, null);
	}

	/**
	 * Records a changed block; returns whether it matters to the nodes left to walk.
	 */
	public function invalidate(PathCursor $cursor, int $x, int $y, int $z) : bool{
		$invalidation = $cursor->findInvalidation($x, $y, $z);
		if ($invalidation === null) {
			return false;
		}
		if ($this->invalidatedCursor === $cursor && $this->invalidation !== null) {
			$invalidation = $this->invalidation->union($invalidation);
		}
		$this->invalidatedCursor = $cursor;
		$this->invalidation = $invalidation;
		return true;
	}

	/**
	 * Searches again for the same target, patching $current if only some nodes were invalidated.
	 * Null when it must wait (too soon, or a search is running); see {@link hasDelayedRecompute()}.
	 *
	 * @phpstan-param Closure() : void $dropCurrent called when $current can't be kept meanwhile
	 * @phpstan-return Promise<PathCursor>|null
	 */
	public function recompute(?PathCursor $current, int $tick, Closure $dropCurrent) : ?Promise{
		if ($tick - $this->lastRecomputeTick <= self::RECOMPUTE_INTERVAL) {
			$this->recomputeDelayed = true;
			return null;
		}
		$target = $this->target;
		if ($target === null) {
			return null;
		}

		$invalidation = $current !== null && $this->invalidatedCursor === $current ? $this->invalidation : null;
		if ($invalidation !== null && $this->pendingResolver !== null) {
			//Patching needs a free search, and the one running may not have seen the change
			$this->recomputeDelayed = true;
			return null;
		}
		$repair = $this->policy->repair($current, $invalidation, $this->splicesInARow);

		$this->lastRecomputeTick = $tick;
		$this->recomputeDelayed = false;
		$this->invalidatedCursor = null;
		$this->invalidation = null;

		//A repair keeps walking the current path
		if ($repair === null) {
			$dropCurrent();
			$current = null;
		}

		return $this->startRequest(new PathRequest($target, $this->reach, null), $current, $repair);
	}

	/**
	 * Asks {@link hasDelayedRecompute()} to keep the recompute for later, when it can't be made now.
	 */
	public function postponeRecompute() : void{
		$this->recomputeDelayed = true;
	}

	/**
	 * Forgets the computation in flight, so a late result can't revive a stopped navigation.
	 */
	public function cancel() : void{
		$this->recomputeDelayed = false;
		$this->invalidatedCursor = null;
		$this->invalidation = null;

		$this->computationId++;
		$this->pendingResolver = null;
		$this->pendingSearch?->cancel();
		$this->pendingSearch = null;
	}

	/**
	 * @phpstan-return Promise<PathCursor>
	 */
	private function startRequest(PathRequest $request, ?PathCursor $current, ?PathRepair $repair) : Promise{
		/** @phpstan-var PromiseResolver<PathCursor> $resolver */
		$resolver = new PromiseResolver();

		if ($repair === null && $current !== null && !$current->isDone() &&
			$this->target !== null && $this->sameBlock($this->target, $request->target)
		) {
			$resolver->resolve($current);
			return $resolver->getPromise();
		}

		if ($this->pendingResolver !== null) {
			//Share the running search: starting another one serializes the chunks again on the main thread
			$this->pendingResolver->getPromise()->onCompletion(
				static function(PathCursor $cursor) use ($resolver) : void{
					$resolver->resolve($cursor);
				},
				static function() use ($resolver) : void{
					$resolver->reject();
				}
			);
			return $resolver->getPromise();
		}

		($this->onSearchStart)();

		$this->pendingResolver = $resolver;
		if ($repair !== null && $current !== null) {
			$request = $request->splicingOnto($current, $repair->from, $repair->rejoin);
		} else {
			$request = $this->policy->extend($request, $current, $this->target, $this->reach, $this->splicesInARow);
		}
		$this->startSearch($request);

		return $resolver->getPromise();
	}

	private function startSearch(PathRequest $request) : void{
		$requestId = ++$this->computationId;
		$setup = ($this->createSetup)();

		$search = $this->executor->search(
			$setup->evaluator,
			$setup->profile,
			$setup->terrain,
			$this->policy->createSearchRequest($request, $setup)
		);
		$this->pendingSearch = $search;
		//The search must not keep the navigation (and its mob) alive
		$requester = \WeakReference::create($this);
		$search->getPromise()->onCompletion(static function(Path $path) use ($requester, $request, $requestId) : void{
			$requester->get()?->onPathComputed($path, $request, $requestId);
		}, static function() : void{});
	}

	private function onPathComputed(Path $path, PathRequest $request, int $requestId) : void{
		$resolver = $this->pendingResolver;
		if ($requestId !== $this->computationId || $resolver === null) {
			//Stale: stopped or superseded since
			return;
		}

		if (!$this->policy->isResultUsable($request, $path, ($this->currentCursor)())) {
			//Path changed or target unreachable from it: search from the mob
			$this->startSearch($request->withoutSplice());
			return;
		}

		if ($request->base === null) {
			$this->splicesInARow = 0;
			$followed = new PathCursor($path);
		} else {
			$followed = $request->base->splice($request->spliceIndex, $path, $request->rejoinIndex);
			++$this->splicesInARow;
		}

		$this->pendingResolver = null;
		$this->pendingSearch = null;

		$this->target = $followed->getPath()->getTarget()->floor();
		$this->reach = $request->reach;

		$resolver->resolve($followed);
	}

	private function sameBlock(Vector3 $a, Vector3 $b) : bool{
		return (int) floor($a->getX()) === (int) floor($b->getX()) &&
			(int) floor($a->getY()) === (int) floor($b->getY()) &&
			(int) floor($a->getZ()) === (int) floor($b->getZ());
	}
}