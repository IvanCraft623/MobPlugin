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

namespace IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\evaluator;

use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\BlockPathTypeCostMap;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\Node;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\pathtype\AmphibiousPathTypeResolver;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\pathtype\PathTypeResolver;
use IvanCraft623\MobPlugin\libs\_8ba7b7fe2577a0b1\IvanCraft623\Pathfinder\Target;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use function floor;
use function max;
use function min;

/**
 * Swims in 3D through water and walks on land; use with {@link BlockPathTypeCostMap::amphibious()} costs.
 */
class AmphibiousNodeEvaluator extends WalkNodeEvaluator {

	public function __construct(PathTypeResolver $pathTypeResolver = new AmphibiousPathTypeResolver()){
		parent::__construct($pathTypeResolver);
	}

	public function getStart() : Node{
		$bb = $this->context->getStartBoundingBox();
		return $this->getStartNode(new Vector3(floor($bb->minX), floor($bb->minY + 0.5), floor($bb->minZ)));
	}

	public function getGoal(float $x, float $y, float $z) : Target{
		return $this->getTargetFromNode($this->getNodeAt(
			(int) floor($x),
			max($this->context->blockGetter->minY, min($this->context->blockGetter->maxY - 1, (int) floor($y + 0.5))),
			(int) floor($z)
		));
	}

	/**
	 * @return Node[]
	 */
	public function getNeighbors(Node $node) : array{
		$nodes = parent::getNeighbors($node);

		$pathType = $this->getCachedPathType($node->x(), $node->y(), $node->z());
		$pathTypeAbove = $this->context->blockGetter->isInWorld($node->x(), $node->y() + 1, $node->z())
			? $this->getCachedPathType($node->x(), $node->y() + 1, $node->z())
			: BlockPathType::BLOCKED;

		$maxUpStep = $this->getPathfindingMalus($pathTypeAbove) >= 0 && $pathType !== BlockPathType::STICKY_HONEY
			? (int) floor(max(1, $this->context->profile->maxUpStep))
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
}