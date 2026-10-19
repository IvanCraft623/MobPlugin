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

namespace IvanCraft623\MobPlugin\libs\_551bdf40ffc65884\IvanCraft623\Pathfinder\evaluator;

use IvanCraft623\MobPlugin\libs\_551bdf40ffc65884\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_551bdf40ffc65884\IvanCraft623\Pathfinder\Node;
use IvanCraft623\MobPlugin\libs\_551bdf40ffc65884\IvanCraft623\Pathfinder\Target;
use IvanCraft623\MobPlugin\libs\_551bdf40ffc65884\IvanCraft623\Pathfinder\world\BlockGetter;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use function floor;
use function max;
use function min;

/**
 * Swims in 3D through water and walks on land.
 */
class AmphibiousNodeEvaluator extends WalkNodeEvaluator {

	public function prepare(BlockGetter $blockGetter, Vector3 $startPosition) : void{
		parent::prepare($blockGetter, $startPosition);

		$this->pathTypeCostMap->setPathfindingMalus(BlockPathType::WATER, 0.0);
		$this->pathTypeCostMap->setPathfindingMalus(BlockPathType::WALKABLE, 6.0);
		$this->pathTypeCostMap->setPathfindingMalus(BlockPathType::WATER_BORDER, 4.0);
	}

	public function getStart() : Node{
		$bb = $this->getEntityBoundingBox();
		return $this->getStartNode(new Vector3(floor($bb->minX), floor($bb->minY + 0.5), floor($bb->minZ)));
	}

	public function getGoal(float $x, float $y, float $z) : Target{
		return $this->getTargetFromNode($this->getNodeAt(
			(int) floor($x),
			max($this->blockGetter->minY, min($this->blockGetter->maxY - 1, (int) floor($y + 0.5))),
			(int) floor($z)
		));
	}

	/**
	 * @return Node[]
	 */
	public function getNeighbors(Node $node) : array{
		$nodes = parent::getNeighbors($node);

		$pathType = $this->getCachedBlockPathType($this->blockGetter, $node->x(), $node->y(), $node->z());
		$pathTypeAbove = $this->blockGetter->isInWorld($node->x(), $node->y() + 1, $node->z())
			? $this->getCachedBlockPathType($this->blockGetter, $node->x(), $node->y() + 1, $node->z())
			: BlockPathType::BLOCKED;

		$maxUpStep = $this->pathTypeCostMap->getPathfindingMalus($pathTypeAbove) >= 0 && $pathType !== BlockPathType::STICKY_HONEY
			? (int) floor(max(1, $this->getMaxUpStep()))
			: 0;

		$floorLevel = $this->getFloorLevel($node);
		$up = $this->findAcceptedNode($node->x(), $node->y() + 1, $node->z(), max(0, $maxUpStep - 1), $floorLevel, Facing::UP, $pathType);
		$down = $this->findAcceptedNode($node->x(), $node->y() - 1, $node->z(), $maxUpStep, $floorLevel, Facing::DOWN, $pathType);

		if ($up !== null && $this->isVerticalNeighborValid($up, $node)) {
			$nodes[] = $up;
		}
		if ($down !== null && $this->isVerticalNeighborValid($down, $node) && $pathType !== BlockPathType::TRAPDOOR) {
			$nodes[] = $down;
		}

		return $nodes;
	}

	private function isVerticalNeighborValid(Node $neighbor, Node $node) : bool{
		return $this->isNeighborValid($neighbor, $node) && $neighbor->type === BlockPathType::WATER;
	}

	protected function isAmphibious() : bool{
		return true;
	}

	public function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		if (static::getBlockPathTypeRaw($blockGetter, $x, $y, $z) !== BlockPathType::WATER) {
			return static::getBlockPathTypeStatic($blockGetter, $x, $y, $z);
		}

		foreach (Facing::OFFSET as [$dx, $dy, $dz]) {
			if (static::getBlockPathTypeRaw($blockGetter, $x + $dx, $y + $dy, $z + $dz) === BlockPathType::BLOCKED) {
				return BlockPathType::WATER_BORDER;
			}
		}

		return BlockPathType::WATER;
	}
}