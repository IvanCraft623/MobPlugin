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

use IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\Pathfinder\execution\PathSearch;
use IvanCraft623\Pathfinder\execution\SearchExecutor;
use IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\SearchRequest;
use IvanCraft623\Pathfinder\world\Terrain;
use pocketmine\promise\PromiseResolver;
use function count;

/**
 * Starts searches that finish when the test says so.
 */
final class FakeSearchExecutor implements SearchExecutor{
	/** @var list<SearchRequest> */
	public array $requests = [];

	/** @var list<PromiseResolver<Path>> */
	private array $resolvers = [];

	public int $cancelled = 0;

	public function search(NodeEvaluator $evaluator, MovementProfile $profile, Terrain $terrain, SearchRequest $request) : PathSearch{
		/** @phpstan-var PromiseResolver<Path> $resolver */
		$resolver = new PromiseResolver();
		$this->requests[] = $request;
		$this->resolvers[] = $resolver;
		return new PathSearch($resolver->getPromise(), function() : void{
			++$this->cancelled;
		});
	}

	/**
	 * Finishes the $index-th search, the last by default.
	 */
	public function finish(Path $path, ?int $index = null) : void{
		$resolver = $this->resolvers[$index ?? count($this->resolvers) - 1] ?? throw new \LogicException("No such search");
		$resolver->resolve($path);
	}
}
