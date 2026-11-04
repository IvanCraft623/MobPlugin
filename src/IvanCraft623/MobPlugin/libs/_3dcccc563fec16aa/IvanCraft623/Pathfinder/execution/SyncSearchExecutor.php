<?php

/*
 *  _____      _   _      __ _           _
 * |  __ \    | | | |    / _(_)         | |
 * | |__) |_ _| |_| |__ | |_ _ _ __   __| | ___ _ __
 * |  ___/ _` | __| '_ \|  _| | '_ \ / _` |/ _ \ '__|
 * | |  | (_| | |_| | | | | | | | | | (_| |  __/ |
 * |_|   \__,_|\__|_| |_|_| |_|_| |_|\__,_|\___|_|
 *
 * A PocketMine-MP virion that implements a mob-oriented pathfinding.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\IvanCraft623\Pathfinder\execution;

use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\IvanCraft623\Pathfinder\AStar;
use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\IvanCraft623\Pathfinder\evaluator\NodeEvaluator;
use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\IvanCraft623\Pathfinder\SearchRequest;
use IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\IvanCraft623\Pathfinder\world\Terrain;
use pocketmine\promise\PromiseResolver;

/**
 * Searches on the calling thread; the promise is resolved on return.
 */
final class SyncSearchExecutor implements SearchExecutor{
	public function search(NodeEvaluator $evaluator, MovementProfile $profile, Terrain $terrain, SearchRequest $request) : PathSearch{
		/** @phpstan-var PromiseResolver<Path> $resolver */
		$resolver = new PromiseResolver();
		$resolver->resolve((new AStar($evaluator, $profile, $terrain->createBlockGetter()))->search($request));
		return new PathSearch($resolver->getPromise(), static function() : void{});
	}
}