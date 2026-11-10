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

namespace IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\pathtype;

use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\MovementProfile;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\world\BlockGetter;
use pocketmine\math\Vector3;

class FlightPathTypeResolver extends PathTypeResolver{
	public function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		$pathType = BlockClassifier::getRawType($blockGetter, $x, $y, $z);

		if ($pathType === BlockPathType::OPEN && $y >= $blockGetter->minY + 1) {
			$pathTypeDown = BlockClassifier::getRawType($blockGetter, $x, $y - 1, $z);

			if ($pathTypeDown === BlockPathType::DAMAGE_FIRE || $pathTypeDown === BlockPathType::LAVA) {
				$pathType = BlockPathType::DAMAGE_FIRE;
			} elseif ($pathTypeDown === BlockPathType::DAMAGE_OTHER) {
				$pathType = BlockPathType::DAMAGE_OTHER;
			} elseif ($pathTypeDown === BlockPathType::COCOA) {
				$pathType = BlockPathType::COCOA;
			} elseif ($pathTypeDown === BlockPathType::FENCE) {
				$pathType = BlockPathType::FENCE;
			} else {
				$pathType = (
					$pathTypeDown !== BlockPathType::WALKABLE &&
					$pathTypeDown !== BlockPathType::OPEN &&
					$pathTypeDown !== BlockPathType::WATER
				) ? BlockPathType::WALKABLE : BlockPathType::OPEN;
			}
		}

		if ($pathType === BlockPathType::WALKABLE || $pathType === BlockPathType::OPEN) {
			$pathType = BlockClassifier::getNeighbourDanger($blockGetter, $x, $y, $z, $pathType);
		}

		return $pathType;
	}

	/**
	 * The block the mob starts in is not a fence for standing over one.
	 */
	protected function applyStart(BlockGetter $blockGetter, Vector3 $startBlock, int $x, int $y, int $z, BlockPathType $pathType) : BlockPathType{
		if ($pathType === BlockPathType::FENCE &&
			$x === $startBlock->getFloorX() && $y === $startBlock->getFloorY() && $z === $startBlock->getFloorZ() &&
			BlockClassifier::getRawType($blockGetter, $x, $y, $z) === BlockPathType::OPEN
		) {
			return BlockClassifier::getNeighbourDanger($blockGetter, $x, $y, $z, BlockPathType::OPEN);
		}
		return parent::applyStart($blockGetter, $startBlock, $x, $y, $z, $pathType);
	}

	protected function getUnpassableTypes() : array{
		return [BlockPathType::FENCE];
	}

	protected function isOpenWhenFree(MovementProfile $profile) : bool{
		return true;
	}
}