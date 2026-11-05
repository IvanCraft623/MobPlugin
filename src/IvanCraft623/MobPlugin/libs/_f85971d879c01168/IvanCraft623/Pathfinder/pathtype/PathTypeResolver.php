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

namespace IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\pathtype;

use IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\block\BaseRail;
use pocketmine\math\Vector3;

/**
 * Path types for one way of moving.
 */
abstract class PathTypeResolver{
	/**
	 * Type of one block.
	 */
	abstract public function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType;

	/**
	 * Type of one block for a search starting at $start, see {@link applyStart()}.
	 */
	public function getBlockPathTypeFrom(BlockGetter $blockGetter, int $x, int $y, int $z, Vector3 $start) : BlockPathType{
		return $this->applyStart($blockGetter, $start->floor(), $x, $y, $z, $this->getBlockPathType($blockGetter, $x, $y, $z));
	}

	/**
	 * Type of the blocks a mob of $profile takes with its lowest corner at x, y, z, the search starting at $start.
	 */
	public function getPathType(BlockGetter $blockGetter, MovementProfile $profile, int $x, int $y, int $z, Vector3 $start) : BlockPathType{
		$startBlock = $start->floor();

		/** @var true[] $pathTypes BlockPathType name => true */
		$pathTypes = [];
		$cornerPathType = BlockPathType::BLOCKED;
		for ($currentX = 0; $currentX < $profile->nodeWidth; ++$currentX) {
			for ($currentY = 0; $currentY < $profile->nodeHeight; ++$currentY) {
				for ($currentZ = 0; $currentZ < $profile->nodeWidth; ++$currentZ) {
					$cellX = $x + $currentX;
					$cellY = $y + $currentY;
					$cellZ = $z + $currentZ;
					$pathType = $this->applyProfile($profile, $this->applyStart(
						$blockGetter, $startBlock, $cellX, $cellY, $cellZ,
						$this->getBlockPathType($blockGetter, $cellX, $cellY, $cellZ)
					));
					if ($currentX === 0 && $currentY === 0 && $currentZ === 0) {
						$cornerPathType = $pathType;
					}
					$pathTypes[$pathType->name] = true;
				}
			}
		}

		foreach ($this->getUnpassableTypes() as $unpassableType) {
			if (isset($pathTypes[$unpassableType->name])) {
				return $unpassableType;
			}
		}

		$bestPathType = BlockPathType::BLOCKED;
		$bestMalus = $profile->costMap->getPathfindingMalus($bestPathType);
		foreach (self::allPathTypes() as $pathType) { //in declaration order, it settles ties
			if (!isset($pathTypes[$pathType->name])) {
				continue;
			}
			$malus = $profile->costMap->getPathfindingMalus($pathType);
			if ($malus < 0) {
				return $pathType;
			}

			if ($malus >= $bestMalus) {
				$bestPathType = $pathType;
				$bestMalus = $malus;
			}
		}

		return ($cornerPathType === BlockPathType::OPEN && $bestMalus === 0.0 && $this->isOpenWhenFree($profile)) ? BlockPathType::OPEN : $bestPathType;
	}

	/**
	 * Types that make the whole space that type if any of its blocks is.
	 *
	 * @return BlockPathType[]
	 */
	protected function getUnpassableTypes() : array{
		return [BlockPathType::FENCE, BlockPathType::UNPASSABLE_RAIL];
	}

	/**
	 * Whether a space without costs whose corner is open is open, instead of its costliest type.
	 */
	protected function isOpenWhenFree(MovementProfile $profile) : bool{
		return $profile->nodeWidth <= 1;
	}

	/**
	 * What the search start changes about the type of the block at x, y, z: a rail is only passable from a rail.
	 */
	protected function applyStart(BlockGetter $blockGetter, Vector3 $startBlock, int $x, int $y, int $z, BlockPathType $pathType) : BlockPathType{
		if ($pathType === BlockPathType::RAIL &&
			!($blockGetter->getBlock($startBlock) instanceof BaseRail) &&
			!($blockGetter->getBlock($startBlock->down()) instanceof BaseRail)
		) {
			return BlockPathType::UNPASSABLE_RAIL;
		}
		return $pathType;
	}

	private function applyProfile(MovementProfile $profile, BlockPathType $pathType) : BlockPathType{
		if ($pathType === BlockPathType::DOOR_WOOD_CLOSED && $profile->canOpenDoors && $profile->canPassDoors) {
			return BlockPathType::WALKABLE_DOOR;
		}
		if ($pathType === BlockPathType::DOOR_OPEN && !$profile->canPassDoors) {
			return BlockPathType::BLOCKED;
		}
		return $pathType;
	}

	/**
	 * @return BlockPathType[]
	 */
	private static function allPathTypes() : array{
		static $pathTypes = null;
		return $pathTypes ??= BlockPathType::cases();
	}
}