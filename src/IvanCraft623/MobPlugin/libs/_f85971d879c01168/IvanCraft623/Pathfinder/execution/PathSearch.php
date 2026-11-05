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

namespace IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\execution;

use Closure;
use IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\Path;
use IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\PathResult;
use pocketmine\promise\Promise;

final class PathSearch{
	/**
	 * @phpstan-param Promise<Path>   $promise
	 * @phpstan-param Closure() : void $cancel
	 */
	public function __construct(
		private Promise $promise,
		private Closure $cancel
	){
	}

	/**
	 * Always resolves, see {@link Path::getPathResult()}.
	 *
	 * @phpstan-return Promise<Path>
	 */
	public function getPromise() : Promise{
		return $this->promise;
	}

	/**
	 * Stops the search; a pending promise resolves with an empty {@link PathResult::CANCELLED} path.
	 */
	public function cancel() : void{
		($this->cancel)();
	}
}