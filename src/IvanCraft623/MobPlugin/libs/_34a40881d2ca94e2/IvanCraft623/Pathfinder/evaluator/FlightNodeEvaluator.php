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

namespace IvanCraft623\MobPlugin\libs\_34a40881d2ca94e2\IvanCraft623\Pathfinder\evaluator;

use IvanCraft623\MobPlugin\libs\_34a40881d2ca94e2\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_34a40881d2ca94e2\IvanCraft623\Pathfinder\Node;
use IvanCraft623\MobPlugin\libs\_34a40881d2ca94e2\IvanCraft623\Pathfinder\Target;
use IvanCraft623\MobPlugin\libs\_34a40881d2ca94e2\IvanCraft623\Pathfinder\utils\EnumSet;
use IvanCraft623\MobPlugin\libs\_34a40881d2ca94e2\IvanCraft623\Pathfinder\world\BlockGetter;

use pocketmine\block\Water;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function floor;
use function max;
use function min;

class FlightNodeEvaluator extends WalkNodeEvaluator {

	/** @var array<int, BlockPathType> World::blockHash() => BlockPathType */
	private array $pathTypeByPosCache = [];

	public function done() : void{
		$this->pathTypeByPosCache = [];

		parent::done();
	}

	public function getStart() : Node{
		$y = (int) floor($this->startPosition->y);

		if ($this->canFloat() && $this->isEntityUnderwater()) {
			$block = $this->blockGetter->getBlockAt((int) floor($this->startPosition->x), $y, (int) floor($this->startPosition->z));
			while ($block instanceof Water && $y + 1 < $this->blockGetter->maxY) {
				$block = $this->blockGetter->getBlockAt((int) floor($this->startPosition->x), ++$y, (int) floor($this->startPosition->z));
			}
		} else {
			$y = (int) floor($this->startPosition->y + 0.5);
		}

		$pos = new Vector3((int) floor($this->startPosition->x), $y, (int) floor($this->startPosition->z));

		if (!$this->canStartAt($pos)) {
			foreach ($this->getStartNodeCandidates() as $candidate) {
				if ($this->canStartAt($candidate)) {
					return parent::getStartNode($candidate);
				}
			}
		}

		return parent::getStartNode($pos);
	}

	protected function canStartAt(Vector3 $pos) : bool{
		$pathType = $this->getBlockPathType($this->blockGetter, (int) $pos->x, (int) $pos->y, (int) $pos->z);
		return $this->pathTypeCostMap->getPathfindingMalus($pathType) >= 0;
	}

	private function getStartNodeCandidates() : array{
		$bb = $this->getEntityBoundingBox();
		$blockY = (int) floor($this->startPosition->y);
		return [
			new Vector3((int) floor($bb->minX), $blockY, (int) floor($bb->minZ)),
			new Vector3((int) floor($bb->minX), $blockY, (int) floor($bb->maxZ)),
			new Vector3((int) floor($bb->maxX), $blockY, (int) floor($bb->minZ)),
			new Vector3((int) floor($bb->maxX), $blockY, (int) floor($bb->maxZ)),
		];
	}

	/**
	 * @return Node[]
	 */
	public function getNeighbors(Node $node) : array{
		$nodes = [];

		// 6 cardinal directions: ±X, ±Y, ±Z
		/** @var array<int, ?Node> $cardinals Facing::* => Node|null */
		$cardinals = [];
		foreach (Facing::ALL as $side) {
			$neighborPos = $node->getSide($side);
			$cardinal = $this->findAcceptedNode((int) $neighborPos->x, (int) $neighborPos->y, (int) $neighborPos->z);
			if ($cardinal !== null && !$cardinal->closed) {
				$nodes[] = $cardinal;
			}
			$cardinals[$side] = $cardinal;
		}

		/** @var array<int, array<int, ?Node>> $yEdges yFace => (hFace => Node|null) */
		$yEdges = [];
		foreach ([Facing::UP, Facing::DOWN] as $yFace) {
			$yEdges[$yFace] = [];
			foreach (Facing::HORIZONTAL as $hFace) {
				$cY = $cardinals[$yFace];
				$cH = $cardinals[$hFace];
				if ($cY !== null && $cY->costMalus >= 0 && $cH !== null && $cH->costMalus >= 0) {
					$edgePos = $node->getSide($yFace)->getSide($hFace);
					$edge = $this->findAcceptedNode((int) $edgePos->x, (int) $edgePos->y, (int) $edgePos->z);
					if ($edge !== null && !$edge->closed) {
						$nodes[] = $edge;
					}
					$yEdges[$yFace][$hFace] = $edge;
				} else {
					$yEdges[$yFace][$hFace] = null;
				}
			}
		}

		/** @var array<int, array<int, ?Node>> $hEdges zFace => (xFace => Node|null) */
		$hEdges = [];
		foreach ([Facing::NORTH, Facing::SOUTH] as $zFace) {
			$hEdges[$zFace] = [];
			foreach ([Facing::WEST, Facing::EAST] as $xFace) {
				$cZ = $cardinals[$zFace];
				$cX = $cardinals[$xFace];
				if ($cZ !== null && $cZ->costMalus >= 0 && $cX !== null && $cX->costMalus >= 0) {
					$edgePos = $node->getSide($zFace)->getSide($xFace);
					$edge = $this->findAcceptedNode((int) $edgePos->x, (int) $edgePos->y, (int) $edgePos->z);
					if ($edge !== null && !$edge->closed) {
						$nodes[] = $edge;
					}
					$hEdges[$zFace][$xFace] = $edge;
				} else {
					$hEdges[$zFace][$xFace] = null;
				}
			}
		}

		// 8 full 3D corners: each horizontal diagonal combined with UP or DOWN.
		// Guard: the horizontal edge + both horizontal cardinals + the Y cardinal
		//        + the two Y-axis edges that share those components must all have costMalus >= 0.
		foreach ([Facing::UP, Facing::DOWN] as $yFace) {
			foreach ([Facing::NORTH, Facing::SOUTH] as $zFace) {
				foreach ([Facing::WEST, Facing::EAST] as $xFace) {
					$hEdge = $hEdges[$zFace][$xFace];
					$yEdgeZ = $yEdges[$yFace][$zFace];
					$yEdgeX = $yEdges[$yFace][$xFace];
					$cZ = $cardinals[$zFace];
					$cX = $cardinals[$xFace];
					$cY = $cardinals[$yFace];
					if ($hEdge !== null && $hEdge->costMalus >= 0 &&
						$cZ !== null && $cZ->costMalus >= 0 &&
						$cX !== null && $cX->costMalus >= 0 &&
						$cY !== null && $cY->costMalus >= 0 &&
						$yEdgeZ !== null && $yEdgeZ->costMalus >= 0 &&
						$yEdgeX !== null && $yEdgeX->costMalus >= 0
					) {
						$cornerPos = $node->getSide($yFace)->getSide($zFace)->getSide($xFace);
						$corner = $this->findAcceptedNode((int) $cornerPos->x, (int) $cornerPos->y, (int) $cornerPos->z);
						if ($corner !== null && !$corner->closed) {
							$nodes[] = $corner;
						}
					}
				}
			}
		}

		return $nodes;
	}

	public function findAcceptedNode(int $x, int $y, int $z, int $remainingJumpHeight = 0, float $floorLevel = 0.0, int $facing = 0, ?BlockPathType $originPathType = null) : ?Node{
		if (!$this->blockGetter->isInWorld($x, $y, $z)) {
			return null;
		}

		$node = null;
		$pathType = $this->getCachedBlockPathType($this->blockGetter, $x, $y, $z);
		$malus = $this->pathTypeCostMap->getPathfindingMalus($pathType);

		if ($malus >= 0) {
			$node = $this->getNodeAt($x, $y, $z);
			$node->type = $pathType;
			$node->costMalus = max($node->costMalus, $malus);
			if ($pathType->equals(BlockPathType::WALKABLE)) {
				// Penalty for low-altitude flying
				$node->costMalus++;
			}
		}

		return $node;
	}

	public function getCachedBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		$blockHash = World::blockHash($x, $y, $z);
		if (!isset($this->pathTypeByPosCache[$blockHash])) {
			$this->pathTypeByPosCache[$blockHash] = $this->getBlockPathTypeAt($blockGetter, $x, $y, $z);
		}

		return $this->pathTypeByPosCache[$blockHash];
	}

	public function getBlockPathTypeAt(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		/**
		 * @var EnumSet<BlockPathType>
		 */
		$pathTypes = new EnumSet(BlockPathType::class);
		$currentPathType = $this->getBlockPathTypes($blockGetter, $x, $y, $z, $pathTypes, BlockPathType::BLOCKED, $this->startPosition->floor());

		if ($pathTypes->contains(BlockPathType::FENCE)) {
			return BlockPathType::FENCE;
		}

		$bestPathType = BlockPathType::BLOCKED;
		foreach ($pathTypes as $pathType) {
			if ($this->pathTypeCostMap->getPathfindingMalus($pathType) < 0) {
				return $pathType;
			}

			if ($this->pathTypeCostMap->getPathfindingMalus($pathType) >= $this->pathTypeCostMap->getPathfindingMalus($bestPathType)) {
				$bestPathType = $pathType;
			}
		}

		return ($currentPathType->equals(BlockPathType::OPEN) &&
			$this->pathTypeCostMap->getPathfindingMalus($bestPathType) === 0.0) ? BlockPathType::OPEN : $bestPathType;
	}

	public function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		$pathType = static::getBlockPathTypeRaw($blockGetter, $x, $y, $z);

		if ($pathType->equals(BlockPathType::OPEN) && $y >= World::Y_MIN + 1) {
			$pathTypeDown = static::getBlockPathTypeRaw($blockGetter, $x, $y - 1, $z);

			if ($pathTypeDown->equals(BlockPathType::DAMAGE_FIRE) || $pathTypeDown->equals(BlockPathType::LAVA)) {
				$pathType = BlockPathType::DAMAGE_FIRE;
			} elseif ($pathTypeDown->equals(BlockPathType::DAMAGE_OTHER)) {
				$pathType = BlockPathType::DAMAGE_OTHER;
			} elseif ($pathTypeDown->equals(BlockPathType::COCOA)) {
				$pathType = BlockPathType::COCOA;
			} elseif ($pathTypeDown->equals(BlockPathType::FENCE)) {
				// Only treat as FENCE if we are not standing at the mob's own position
				$mobPos = $this->startPosition->floor();
				if ($x !== (int) $mobPos->x || $y !== (int) $mobPos->y || $z !== (int) $mobPos->z) {
					$pathType = BlockPathType::FENCE;
				}
			} else {
				$pathType = (
					!$pathTypeDown->equals(BlockPathType::WALKABLE) &&
					!$pathTypeDown->equals(BlockPathType::OPEN) &&
					!$pathTypeDown->equals(BlockPathType::WATER)
				) ? BlockPathType::WALKABLE : BlockPathType::OPEN;
			}
		}

		if ($pathType->equals(BlockPathType::WALKABLE) || $pathType->equals(BlockPathType::OPEN)) {
			$pathType = static::checkNeighbourBlocks($blockGetter, $x, $y, $z, $pathType);
		}

		return $pathType;
	}
}