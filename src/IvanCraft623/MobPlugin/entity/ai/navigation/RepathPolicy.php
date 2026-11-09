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

use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\PathCursor;
use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\PathInvalidation;
use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\PathRepair;
use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\SearchRequest;
use pocketmine\math\Vector3;
use function floor;
use function max;
use function min;

/**
 * Decides how to get a path again: search from the mob, extend the current path or repair it.
 *
 * @internal
 */
final class RepathPolicy{
	/** Blocks a target may move for its path to be extended */
	public const SPLICE_MAX_TARGET_MOVE = 4;
	/** Extensions and repairs in a row before searching from the mob again */
	public const MAX_SPLICES_IN_A_ROW = 4;
	/** Part of the node budget an extension or repair gets */
	public const SPLICE_NODE_BUDGET_FACTOR = 0.25;
	/** Least blocks around its start node that a repair may search */
	public const REPAIR_MIN_RADIUS = 8;

	/**
	 * Extends $current to a target that moved a little, if it can.
	 */
	public function extend(PathRequest $request, ?PathCursor $current, ?Vector3 $lastTarget, int $lastReach, int $splicesInARow) : PathRequest{
		if ($current === null || $current->isDone() || !$current->getPath()->canReach() ||
			$lastTarget === null || $request->reach !== $lastReach ||
			$splicesInARow >= self::MAX_SPLICES_IN_A_ROW ||
			$lastTarget->distance($request->target->floor()) > self::SPLICE_MAX_TARGET_MOVE
		) {
			return $request;
		}
		return $request->splicingOnto($current, $current->getClosestNodeIndex($request->target));
	}

	/**
	 * How to patch $cursor around its invalidated nodes, if worth it.
	 */
	public function repair(?PathCursor $cursor, ?PathInvalidation $invalidation, int $splicesInARow) : ?PathRepair{
		if ($cursor === null || $invalidation === null || $cursor->isDone() || $splicesInARow >= self::MAX_SPLICES_IN_A_ROW) {
			return null;
		}
		return $cursor->planRepair($invalidation);
	}

	public function createSearchRequest(PathRequest $request, SearchSetup $setup) : SearchRequest{
		$maxDistanceFromStart = $request->maxDistanceFromStart ?? $setup->followRange;
		$base = $request->base;
		if ($base === null) {
			return new SearchRequest($setup->start, $request->target, $setup->maxVisitedNodes, $maxDistanceFromStart, $request->reach, $setup->onGround);
		}

		$basePath = $base->getPath();
		$start = $basePath->getNode($request->spliceIndex);
		$rejoin = $request->rejoinIndex === null ? null : $basePath->getNode($request->rejoinIndex);
		return new SearchRequest(
			$start,
			$rejoin ?? $request->target,
			(int) floor($setup->maxVisitedNodes * self::SPLICE_NODE_BUDGET_FACTOR),
			$rejoin === null ? $maxDistanceFromStart : min($maxDistanceFromStart, max(self::REPAIR_MIN_RADIUS, 2 * $start->distance($rejoin))),
			$rejoin === null ? $request->reach : 0,
			startAtNode: true
		);
	}

	/**
	 * Whether the result still fits the path being followed.
	 */
	public function isResultUsable(PathRequest $request, Path $result, ?PathCursor $current) : bool{
		$base = $request->base;
		return $base === null || (
			$base === $current && $result->canReach() && $base->canSplice($request->spliceIndex, $result, $request->rejoinIndex)
		);
	}
}