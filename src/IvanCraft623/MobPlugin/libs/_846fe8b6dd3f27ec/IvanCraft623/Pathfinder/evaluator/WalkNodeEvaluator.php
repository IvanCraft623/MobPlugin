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

namespace IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\IvanCraft623\Pathfinder\evaluator;

use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\IvanCraft623\Pathfinder\Node;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\IvanCraft623\Pathfinder\PathComputationType;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\IvanCraft623\Pathfinder\pathtype\BlockClassifier;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\IvanCraft623\Pathfinder\pathtype\PathTypeResolver;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\IvanCraft623\Pathfinder\pathtype\WalkPathTypeResolver;
use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\IvanCraft623\Pathfinder\SearchContext;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\block\Water;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function ceil;
use function floor;
use function is_int;
use function max;
use function min;

/**
 * @phpstan-import-type BlockPosHash from World
 */
class WalkNodeEvaluator extends NodeEvaluator {

	public const DEFAULT_MOB_JUMP_HEIGHT = 1.125;

	/** @var array<BlockPosHash, float> of whole block positions */
	private array $floorLevels = [];

	public function __construct(PathTypeResolver $pathTypeResolver = new WalkPathTypeResolver()){
		parent::__construct($pathTypeResolver);
	}

	public function prepare(SearchContext $context) : void{
		parent::prepare($context);
		$this->floorLevels = [];
	}

	public function done() : void{
		parent::done();
		$this->floorLevels = [];
	}

	public function getStart() : Node{
		$position = clone $this->context->start;

		$y = (int) floor($position->y);
		$block = $this->context->blockGetter->getBlock($position);

		if (!($block instanceof Liquid && $this->context->profile->canStandOnFluid($block))) {
			if ($this->context->profile->canFloat && $block instanceof Water) {
				while (true) {
					if (!$block instanceof Water) {
						--$y;
						break;
					}
					$block = $this->context->blockGetter->getBlockAt((int) floor($position->x), ++$y, (int) floor($position->z));
				}
			} elseif ($this->context->onGround) {
				$y = (int) floor($this->context->start->y + 0.5);
			} else {
				$pos = new Vector3((int) floor($this->context->start->x), (int) floor($this->context->start->y) + 1, (int) floor($this->context->start->z));

				while ((($b = $this->context->blockGetter->getBlock($pos))->getTypeId() === BlockTypeIds::AIR || BlockClassifier::isPathfindable($b, PathComputationType::LAND)) && $pos->y > $this->context->blockGetter->minY) {
					$pos = $pos->down();
				}

				$y = $pos->y + 1;
			}
		} else {
			while ($block instanceof Liquid && $this->context->profile->canStandOnFluid($block)) {
				$position->y = ++$y;
				$block = $this->context->blockGetter->getBlock($position);
			}

			--$y;
		}

		$position->y = $y;
		return $this->getStartNode($position);
	}

	/**
	 * @return Node[]
	 */
	public function getNeighbors(Node $node) : array{
		$nodes = [];
		$maxUpStep = 0;

		$nodeX = $node->x();
		$nodeY = $node->y();
		$nodeZ = $node->z();

		$pathType = $this->getCachedPathType($nodeX, $nodeY, $nodeZ);
		$aboveY = $nodeY + 1;
		$pathTypeAbove = $this->context->blockGetter->isInWorld($nodeX, $aboveY, $nodeZ)
			? $this->getCachedPathType($nodeX, $aboveY, $nodeZ)
			: BlockPathType::BLOCKED;

		if ($this->getPathfindingMalus($pathTypeAbove) >= 0 && $pathType !== BlockPathType::STICKY_HONEY) {
			$maxUpStep = (int) floor(max(1, $this->context->profile->maxUpStep));
		}

		$floorLevel = $this->getFloorLevel($node);

		/**
		 * @var array<int, ?Node> $horizontalNeighbors face => node
		 */
		$horizontalNeighbors = [];
		foreach (Facing::HORIZONTAL as $side) {
			[$dx, $dy, $dz] = Facing::OFFSET[$side];
			$neighborNode = $this->findAcceptedNode($nodeX + $dx, $nodeY + $dy, $nodeZ + $dz, $maxUpStep, $floorLevel, $side, $pathType);

			$horizontalNeighbors[$side] = $neighborNode;
			if ($neighborNode !== null && $this->isNeighborValid($neighborNode, $node)) {
				$nodes[] = $neighborNode;
			}
		}

		// Iterate diagonals
		foreach ([Facing::NORTH, Facing::SOUTH] as $zFace) {
			[$dxz, $dyz, $dzz] = Facing::OFFSET[$zFace];
			foreach ([Facing::WEST, Facing::EAST] as $xFace) {
				[$dxx, $dyx, $dzx] = Facing::OFFSET[$xFace];
				$diagonalNode = $this->findAcceptedNode($nodeX + $dxz + $dxx, $nodeY + $dyz + $dyx, $nodeZ + $dzz + $dzx, $maxUpStep, $floorLevel, $zFace, $pathType);

				if ($diagonalNode !== null && $this->isDiagonalValid($node, $horizontalNeighbors[$xFace], $horizontalNeighbors[$zFace], $diagonalNode)) {
					$nodes[] = $diagonalNode;
				}
			}
		}

		return $nodes;
	}

	protected function isNeighborValid(Node $neighbor, Node $node) : bool{
		return !$neighbor->closed && ($neighbor->costMalus >= 0 || $node->costMalus < 0);
	}

	protected function isDiagonalValid(Node $node, ?Node $neighbor1, ?Node $neighbor2, Node $diagonal) : bool{
		if ($neighbor1 === null || $neighbor2 === null) {
			return false;
		}
		if ($diagonal->closed) {
			return false;
		}
		if ($neighbor1->y > $node->y || $neighbor2->y > $node->y) {
			return false;
		}

		if ($neighbor1->type !== BlockPathType::WALKABLE_DOOR &&
			$neighbor2->type !== BlockPathType::WALKABLE_DOOR &&
			$diagonal->type !== BlockPathType::WALKABLE_DOOR
		) {
			$isFence = $neighbor1->type === BlockPathType::FENCE &&
				$neighbor2->type === BlockPathType::FENCE &&
				$this->context->profile->width < 0.5;
			return $diagonal->costMalus >= 0 &&
				($neighbor1->y < $node->y || $neighbor1->costMalus >= 0 || $isFence) &&
				($neighbor2->y < $node->y || $neighbor2->costMalus >= 0 || $isFence);
		}
		return false;
	}

	private static function doesBlockHavePartialCollision(BlockPathType $pathType) : bool{
		return $pathType === BlockPathType::FENCE ||
			$pathType === BlockPathType::DOOR_WOOD_CLOSED ||
			$pathType === BlockPathType::DOOR_IRON_CLOSED;
	}

	private function canReachWithoutCollision(Node $node) : bool{
		$bb = clone $this->context->getStartBoundingBox();
		$mobPos = $this->context->start;

		$relativePos = new Vector3(
			$node->x - $mobPos->x + $bb->getXLength() / 2,
			$node->y - $mobPos->y + $bb->getYLength() / 2,
			$node->z - $mobPos->z + $bb->getZLength() / 2
		);

		$stepCount = (int) ceil($relativePos->length() / $bb->getAverageEdgeLength());
		$relativePos = $relativePos->multiply(1 / $stepCount);

		for ($i = 1; $i <= $stepCount; $i++) {
			$bb->offset($relativePos->x, $relativePos->y, $relativePos->z);
			if ($this->hasCollisions($bb)) {
				return false;
			}
		}

		return true;
	}

	protected function getFloorLevel(Vector3 $pos) : float{
		//Within a block, the floor of shapes like stairs is at a height depending on where
		if (!is_int($pos->x) || !is_int($pos->y) || !is_int($pos->z)) {
			return $this->computeFloorLevel($pos);
		}
		return $this->floorLevels[World::blockHash($pos->x, $pos->y, $pos->z)] ??= $this->computeFloorLevel($pos);
	}

	private function computeFloorLevel(Vector3 $pos) : float{
		//TODO: waterlogging check
		if (($this->context->profile->canFloat || $this->isAmphibious()) && $this->context->blockGetter->getBlock($pos) instanceof Water) {
			return $pos->getY() + 0.5;
		}
		return BlockClassifier::getFloorLevel($this->context->blockGetter, $pos);
	}

	protected function isAmphibious() : bool{
		return false;
	}

	protected function findAcceptedNode(int $x, int $y, int $z, int $remainingJumpHeight, float $floorLevel, int $facing, BlockPathType $originPathType) : ?Node{
		if (!$this->context->blockGetter->isInWorld($x, $y, $z)) {
			return null;
		}

		$resultNode = null;
		$pos = new Vector3($x, $y, $z);

		if ($this->getFloorLevel($pos) - $floorLevel > $this->getMobJumpHeight()) {
			return null;
		}

		$currentPathType = $this->getCachedPathType($x, $y, $z);
		$malus = $this->getPathfindingMalus($currentPathType);

		if ($malus >= 0) {
			$resultNode = $this->getNodeAndUpdateCostToMax($x, $y, $z, $currentPathType, $malus);
		}

		if (self::doesBlockHavePartialCollision($originPathType) &&
			$resultNode !== null && $resultNode->costMalus >= 0 &&
			!$this->canReachWithoutCollision($resultNode)
		) {
			$resultNode = null;
		}

		if ($currentPathType !== BlockPathType::WALKABLE && (!$this->isAmphibious() || $currentPathType !== BlockPathType::WATER)) {
			if (($resultNode === null || $resultNode->costMalus < 0) &&
				$remainingJumpHeight > 0 &&
				($currentPathType !== BlockPathType::FENCE || $this->context->profile->canWalkOverFences) &&
				$currentPathType !== BlockPathType::UNPASSABLE_RAIL &&
				$currentPathType !== BlockPathType::TRAPDOOR &&
				$currentPathType !== BlockPathType::POWDER_SNOW
			) {
				$resultNode = $this->findAcceptedNode($x, $y + 1, $z, $remainingJumpHeight - 1, $floorLevel, $facing, $originPathType);
				$width = $this->context->profile->width;
				if ($resultNode !== null &&
					($resultNode->type === BlockPathType::OPEN || $resultNode->type === BlockPathType::WALKABLE)
				) {
					$halfWidth = $width / 2;
					[$dx, $dy, $dz] = Facing::OFFSET[$facing];
					$sidePos = new Vector3($x + $dx + 0.5, $y + $dy, $z + $dz + 0.5);
					$y1 = $this->getFloorLevel(new Vector3($sidePos->x, $y + 1, $sidePos->z));
					$y2 = $this->getFloorLevel(new Vector3($resultNode->x, $resultNode->y, $resultNode->z));
					$originSidePos = new Vector3($x + 0.5, $y, $z + 0.5);
					$bb = new AxisAlignedBB(
						minX: min($sidePos->x, $originSidePos->x) - $halfWidth,
						minY: min($y1, $y2) + 0.001,
						minZ: min($sidePos->z, $originSidePos->z) - $halfWidth,
						maxX: max($sidePos->x, $originSidePos->x) + $halfWidth,
						maxY: $this->context->profile->height + max($y1, $y2) - 0.002,
						maxZ: max($sidePos->z, $originSidePos->z) + $halfWidth
					);
					if ($this->hasCollisions($bb)) {
						$resultNode = null;
					}
				}
			}

			if (!$this->isAmphibious() && $currentPathType === BlockPathType::WATER && !$this->context->profile->canFloat) {
				if ($y - 1 < $this->context->blockGetter->minY ||
					$this->getCachedPathType($x, $y - 1, $z) !== BlockPathType::WATER
				) {
					return $resultNode;
				}

				while ($y > $this->context->blockGetter->minY) {
					$currentPathType = $this->getCachedPathType($x, --$y, $z);
					if ($currentPathType !== BlockPathType::WATER) {
						return $resultNode;
					}

					$resultNode = $this->getNodeAndUpdateCostToMax($x, $y, $z, $currentPathType, $this->getPathfindingMalus($currentPathType));
				}
			}

			if ($currentPathType === BlockPathType::OPEN) {
				$fallDistance = 0;
				$startY = $y;

				while ($currentPathType === BlockPathType::OPEN) {
					if (--$y < $this->context->blockGetter->minY) {
						return $this->getBlockedNode($x, $startY, $z);
					}

					if ($fallDistance++ >= $this->context->profile->maxFallDistance) {
						return $this->getBlockedNode($x, $y, $z);
					}

					$currentPathType = $this->getCachedPathType($x, $y, $z);
					$malus = $this->getPathfindingMalus($currentPathType);
					if ($currentPathType !== BlockPathType::OPEN && $malus >= 0) {
						$resultNode = $this->getNodeAndUpdateCostToMax($x, $y, $z, $currentPathType, $malus);
						break;
					}

					if ($malus < 0) {
						return $this->getBlockedNode($x, $y, $z);
					}
				}
			}

			if (self::doesBlockHavePartialCollision($currentPathType) && $resultNode === null) {
				$resultNode = $this->getNodeAt($x, $y, $z);
				$resultNode->closed = true;
				$resultNode->type = $currentPathType;
				$resultNode->costMalus = $currentPathType->getMalus();
			}
		}

		return $resultNode;
	}

	private function getMobJumpHeight() : float{
		return max(static::DEFAULT_MOB_JUMP_HEIGHT, $this->context->profile->maxUpStep);
	}

	private function getNodeAndUpdateCostToMax(int $x, int $y, int $z, BlockPathType $pathType, float $malus) : Node{
		$node = $this->getNodeAt($x, $y, $z);
		$node->type = $pathType;
		$node->costMalus = max($node->costMalus, $malus);

		return $node;
	}

	private function getBlockedNode(int $x, int $y, int $z) : Node{
		$node = $this->getNodeAt($x, $y, $z);
		$node->type = BlockPathType::BLOCKED;
		$node->costMalus = -1;

		return $node;
	}

	private function hasCollisions(AxisAlignedBB $bb) : bool{
		if (!$this->context->blockGetter->isInWorld((int) floor($bb->minX), (int) floor($bb->minY), (int) floor($bb->minZ)) ||
			!$this->context->blockGetter->isInWorld((int) floor($bb->maxX), (int) floor($bb->maxY), (int) floor($bb->maxZ))
		) {
			return true;
		}

		foreach ($this->context->blockGetter->getCollisionBlocks($bb) as $block) {
			if ($block->isSolid()) {
				return true;
			}
		}

		return false;
	}
}