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

namespace IvanCraft623\MobPlugin\libs\_d8e47a9fc36ff85c\IvanCraft623\Pathfinder\world;

use Closure;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;

final class BlockStateSet{
	/** @var true[] state id => true */
	private array $stateIds = [];

	/**
	 * @param int[] $stateIds
	 */
	public function __construct(array $stateIds){
		foreach ($stateIds as $stateId) {
			$this->stateIds[$stateId] = true;
		}
	}

	/**
	 * The known states $filter accepts.
	 *
	 * @phpstan-param Closure(Block) : bool $filter
	 */
	public static function of(Closure $filter) : self{
		$stateIds = [];
		foreach (RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $stateId => $state) {
			if ($filter($state)) {
				$stateIds[] = $stateId;
			}
		}
		return new self($stateIds);
	}

	public function contains(int $stateId) : bool{
		return isset($this->stateIds[$stateId]);
	}
}