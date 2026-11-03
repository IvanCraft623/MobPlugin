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

namespace IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\IvanCraft623\Pathfinder\evaluator;

use IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\IvanCraft623\Pathfinder\Node;
use IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\IvanCraft623\Pathfinder\pathtype\FlightPathTypeResolver;
use IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\IvanCraft623\Pathfinder\pathtype\PathTypeResolver;

use pocketmine\block\Water;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use function floor;
use function max;

class FlightNodeEvaluator extends NodeEvaluator {

	public function __construct(PathTypeResolver $pathTypeResolver = new FlightPathTypeResolver()){
		parent::__construct($pathTypeResolver);
	}

	public function getStart() : Node{
		$y = (int) floor($this->context->start->y);

		if ($this->context->profile->canFloat && $this->isStartUnderwater()) {
			$block = $this->context->blockGetter->getBlockAt((int) floor($this->context->start->x), $y, (int) floor($this->context->start->z));
			while ($block instanceof Water && $y + 1 < $this->context->blockGetter->maxY) {
				$block = $this->context->blockGetter->getBlockAt((int) floor($this->context->start->x), ++$y, (int) floor($this->context->start->z));
			}
		} else {
			$y = (int) floor($this->context->start->y + 0.5);
		}

		$pos = new Vector3((int) floor($this->context->start->x), $y, (int) floor($this->context->start->z));

		if (!$this->canStartAt($pos)) {
			foreach ($this->getStartNodeCandidates() as $candidate) {
				if ($this->canStartAt($candidate)) {
					return $this->getStartNode($candidate);
				}
			}
		}

		return $this->getStartNode($pos);
	}

	private function isStartUnderwater() : bool{
		$start = $this->context->start;
		$y = $start->y + $this->context->profile->eyeHeight;
		$blockY = (int) floor($y);
		$block = $this->context->blockGetter->getBlockAt((int) floor($start->x), $blockY, (int) floor($start->z));
		return $block instanceof Water && $y < ($blockY + 1) - ($block->getFluidHeightPercent() - 0.1111111);
	}

	protected function canStartAt(Vector3 $pos) : bool{
		$pathType = $this->pathTypeResolver->getBlockPathType($this->context->blockGetter, (int) $pos->x, (int) $pos->y, (int) $pos->z, $this->context->start);
		return $this->getPathfindingMalus($pathType) >= 0;
	}

	/**
	 * @return Vector3[]
	 */
	private function getStartNodeCandidates() : array{
		$bb = $this->context->getStartBoundingBox();
		$blockY = (int) floor($this->context->start->y);
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

		$nodeX = $node->x();
		$nodeY = $node->y();
		$nodeZ = $node->z();

		// 6 cardinal directions: ±X, ±Y, ±Z
		/** @var array<int, ?Node> $cardinals Facing::* => Node|null */
		$cardinals = [];
		foreach (Facing::ALL as $side) {
			[$dx, $dy, $dz] = Facing::OFFSET[$side];
			$cardinal = $this->findAcceptedNode($nodeX + $dx, $nodeY + $dy, $nodeZ + $dz);
			if ($cardinal !== null && !$cardinal->closed) {
				$nodes[] = $cardinal;
			}
			$cardinals[$side] = $cardinal;
		}

		/** @var array<int, array<int, ?Node>> $yEdges yFace => (hFace => Node|null) */
		$yEdges = [];
		foreach ([Facing::UP, Facing::DOWN] as $yFace) {
			[$oyx, $oyy, $oyz] = Facing::OFFSET[$yFace];
			$yEdges[$yFace] = [];
			foreach (Facing::HORIZONTAL as $hFace) {
				$cY = $cardinals[$yFace];
				$cH = $cardinals[$hFace];
				if ($cY !== null && $cY->costMalus >= 0 && $cH !== null && $cH->costMalus >= 0) {
					[$ohx, $ohy, $ohz] = Facing::OFFSET[$hFace];
					$edge = $this->findAcceptedNode($nodeX + $oyx + $ohx, $nodeY + $oyy + $ohy, $nodeZ + $oyz + $ohz);
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
			[$ozx, $ozy, $ozz] = Facing::OFFSET[$zFace];
			$hEdges[$zFace] = [];
			foreach ([Facing::WEST, Facing::EAST] as $xFace) {
				$cZ = $cardinals[$zFace];
				$cX = $cardinals[$xFace];
				if ($cZ !== null && $cZ->costMalus >= 0 && $cX !== null && $cX->costMalus >= 0) {
					[$oxx, $oxy, $oxz] = Facing::OFFSET[$xFace];
					$edge = $this->findAcceptedNode($nodeX + $ozx + $oxx, $nodeY + $ozy + $oxy, $nodeZ + $ozz + $oxz);
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
			[$oyx, $oyy, $oyz] = Facing::OFFSET[$yFace];
			foreach ([Facing::NORTH, Facing::SOUTH] as $zFace) {
				[$ozx, $ozy, $ozz] = Facing::OFFSET[$zFace];
				foreach ([Facing::WEST, Facing::EAST] as $xFace) {
					[$oxx, $oxy, $oxz] = Facing::OFFSET[$xFace];
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
						$corner = $this->findAcceptedNode(
							$nodeX + $oyx + $ozx + $oxx,
							$nodeY + $oyy + $ozy + $oxy,
							$nodeZ + $oyz + $ozz + $oxz
						);
						if ($corner !== null && !$corner->closed) {
							$nodes[] = $corner;
						}
					}
				}
			}
		}

		return $nodes;
	}

	private function findAcceptedNode(int $x, int $y, int $z) : ?Node{
		if (!$this->context->blockGetter->isInWorld($x, $y, $z)) {
			return null;
		}

		$node = null;
		$pathType = $this->getCachedPathType($x, $y, $z);
		$malus = $this->getPathfindingMalus($pathType);

		if ($malus >= 0) {
			$node = $this->getNodeAt($x, $y, $z);
			$node->type = $pathType;
			$node->costMalus = max($node->costMalus, $malus);
			if ($pathType === BlockPathType::WALKABLE) {
				// Penalty for low-altitude flying
				$node->costMalus++;
			}
		}

		return $node;
	}
}