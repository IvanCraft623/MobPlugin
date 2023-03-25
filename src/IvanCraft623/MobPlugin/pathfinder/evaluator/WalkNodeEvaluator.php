<?php

/*
 *   __  __       _     _____  _             _
 *  |  \/  |     | |   |  __ \| |           (_)
 *  | \  / | ___ | |__ | |__) | |_   _  __ _ _ _ __
 *  | |\/| |/ _ \| '_ \|  ___/| | | | |/ _` | | '_ \
 *  | |  | | (_) | |_) | |    | | |_| | (_| | | | | |
 *  |_|  |_|\___/|_.__/|_|    |_|\__,_|\__, |_|_| |_|
 *                                      __/ |
 *                                     |___/
 *
 * A PocketMine-MP plugin that implements mobs AI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\pathfinder\evaluator;

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\pathfinder\BlockPathTypes;
use IvanCraft623\MobPlugin\pathfinder\Node;
use IvanCraft623\MobPlugin\pathfinder\PathComputationType;
use IvanCraft623\MobPlugin\pathfinder\Target;
use IvanCraft623\MobPlugin\Utils;

use pocketmine\block\BaseRail;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Door;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;
use pocketmine\block\Lava;
use pocketmine\block\Leaves;
use pocketmine\block\Liquid;
use pocketmine\block\Trapdoor;
use pocketmine\block\Wall;
use pocketmine\block\Water;
use pocketmine\block\WoodenDoor;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function ceil;
use function count;
use function floor;
use function max;
use function spl_object_id;

class WalkNodeEvaluator extends NodeEvaluator {

	public const DEFAULT_MOB_JUMP_HEIGHT = 1.125;

	protected float $oldWaterCost;

	/** @var array<int, BlockPathTypes> World::blockHash() => BlockPathTypes */
	protected array $pathTypesByPosCache = [];

	/** @var array<int, bool> spl_object_id(AxisAlignedBB) => bool */
	protected array $collisionCache = [];

	public function prepare(World $world, Mob $mob) : void{
		parent::prepare($world, $mob);

		$this->oldWaterCost = $mob->getPathfindingMalus(BlockPathTypes::WATER());
	}

	public function done() : void{
		$this->mob->setPathfindingMalus(BlockPathTypes::WATER(), $this->oldWaterCost);
		$this->pathTypesByPosCache = [];
		$this->collisionCache = [];

		parent::done();
	}

	public function getStart() : Node{
		$position = $this->mob->getPosition()->floor();
		$y = (int) $position->y;
		$block = $this->world->getBlock($position);

		if (!($block instanceof Liquid && $this->mob->canStandOnFluid($block))) {
			if ($this->canFloat() && $this->mob->isUnderWater() && $block instanceof Water) {
				while (true) {
					if (!($block instanceof Water && $block->isSource())) {
						--$y;
						break;
					}
					$block = $this->world->getBlockAt($position->x, ++$y, $position->z);
				}
			} elseif ($this->mob->isOnGround()) {
				$y = (int) floor($this->mob->getPosition()->y + 0.5);
			} else {
				$pos = $this->mob->getPosition()->floor();

				while ((($b = $this->world->getBlock($pos))->getId() === BlockLegacyIds::AIR || Utils::isPathfindable($b,PathComputationType::LAND())) && $pos->y > World::Y_MIN) {
					$pos = $pos->down();
				}

				$y = $pos->y + 1;
			}
		} else {
			while ($block instanceof Liquid && $this->mob->canStandOnFluid($block)) {
				$position->y = ++$y;
				$block = $this->world->getBlock($position);
			}

			--$y;
		}

		$position->y = $y;
		return $this->getStartNode($position);
	}

	protected function getStartNode(Vector3 $position) : Node{
		$node = $this->getNode($position);
		$node->type = $this->getBlockPathType($this->mob, $node->x, $node->y, $node->z);
		$node->costMalus = $this->mob->getPathfindingMalus($node->type);

		return $node;
	}

	public function getGoal(float $x, float $y, float $z) : Target{
		return $this->getTargetFromNode($this->getNodeAt((int) floor($x), (int) floor($y), (int) floor($z)));
	}

	/**
	 * @param Node[] $nodes
	 */
	public function getNeighbors(array &$nodes, Node $node) : int{
		$count = 0;
		$maxUpStep = 0;

		$pathType = $this->getBlockPathType($this->mob, $node->x, $node->y, $node->z);
		$pathTypeAbove = $this->getBlockPathType($this->mob, $node->x, $node->y + 1, $node->z);

		if ($this->mob->getPathfindingMalus($pathTypeAbove) >= 0 && !$pathType->equals(BlockPathTypes::STICKY_HONEY())) {
			$maxUpStep = floor(max(1, $this->mob->getMaxUpStep()));
		}

		$nodePos = $node->asVector3();
		$floorLevel = $this->getFloorLevel($nodePos);

		/**
		 * @var array<int, ?Node> $horizontalNeighbors face => node
		 */
		$horizontalNeighbors = [];
		foreach (Facing::HORIZONTAL as $side) {
			$neighborPos = $nodePos->getSide($side);
			$neighborNode = $this->findAcceptedNode($neighborPos->x, $neighborPos->z, $neighborPos->y, $maxUpStep, $floorLevel, $side, $pathType);

			$horizontalNeighbors[$side] = $neighborNode;
			if ($neighborNode !== null && $this->isNeighborValid($neighborNode, $node)) {
				$nodes[$count++] = $neighborNode;
			}
		}

		// Iterate diagonals
		foreach ([Facing::NORTH, Facing::SOUTH] as $zFace) {
			$zFacePos = $nodePos->getSide($zFace);
			foreach ([Facing::WEST, Facing::EAST] as $xFace) {
				$diagonalPos = $zFacePos->getSide($xFace);
				$diagonalNode = $this->findAcceptedNode($diagonalPos->x, $diagonalPos->z, $diagonalPos->y, $maxUpStep, $floorLevel, $zFace, $pathType);

				if ($diagonalNode !== null && $this->isDiagonalValid($node, $horizontalNeighbors[$xFace], $horizontalNeighbors[$zFace], $diagonalNode)) {
					$nodes[$count++] = $diagonalNode;
				}
			}
		}

		return $count;
	}

	public function isNeighborValid(Node $neighbor, Node $node) : bool{
		return !$neighbor->closed && ($neighbor->costMalus >= 0 || $node->costMalus < 0);
	}

	public function isDiagonalValid(Node $node, ?Node $neighbor1, ?Node $neighbor2, Node $diagonal) : bool{
		if ($neighbor1 === null || $neighbor2 === null) {
			return false;
		}
		if ($diagonal->closed) {
			return false;
		}
		if ($neighbor1->y > $node->y || $neighbor2->y > $node->y) {
			return false;
		}

		if (!$neighbor1->type->equals(BlockPathTypes::WALKABLE_DOOR()) &&
			!$neighbor2->type->equals(BlockPathTypes::WALKABLE_DOOR()) &&
			!$diagonal->type->equals(BlockPathTypes::WALKABLE_DOOR())
		) {
			$isFence = $neighbor1->type->equals(BlockPathTypes::FENCE()) &&
				$neighbor2->type->equals(BlockPathTypes::FENCE()) &&
				$this->mob->getSize()->getWidth() < 0.5;
			return $diagonal->costMalus >= 0 &&
				($neighbor1->y < $node->y || $neighbor1->costMalus >= 0. || $isFence) &&
				($neighbor2->y < $node->y || $neighbor2->costMalus >= 0. || $isFence);
		}
		return false;
	}

	private static function doesBlockHavePartialCollision(BlockPathTypes $pathType) : bool{
		return $pathType->equals(BlockPathTypes::FENCE()) || $pathType->equals(BlockPathTypes::DOOR_WOOD_CLOSED()) || $pathType->equals(BlockPathTypes::DOOR_IRON_CLOSED());
	}

	private function canReachWithoutCollision(Node $node) : bool{
		$bb = clone $this->mob->getBoundingBox();
		$mobPos = $this->mob->getPosition();
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
		//TODO: waterlogging check
		if (($this->canFloat() || $this->isAmphibious()) && $this->world->getBlock($pos) instanceof Water) {
			return $pos->getY() + 0.5;
		}
		return static::getFloorLevelAt($this->world, $pos);
	}

	public static function getFloorLevelAt(World $world, Vector3 $pos) : float{
		$down = $pos->down();
		$traceResult = $world->getBlock($down)->calculateIntercept($pos, $down);

		return $down->getY() + ($traceResult === null ? 0 : $traceResult->getHitVector()->getY());
	}

	protected function isAmphibious() : bool{
		return false;
	}

	public function findAcceptedNode(int $x, int $y, int $z, int $targetY, float $floorLevel, int $facing, BlockPathTypes $preferredPathType) : ?Node{
		$resultNode = null;
		$pos = new Vector3($x, $y, $z);
		$floorLevel = $this->getFloorLevel($pos);

		if ($floorLevel - $targetY > $this->getMobJumpHeight()) {
			return null;
		}

		$currentPathType = $this->getBlockPathType($this->mob, $x, $y, $z);
		$malus = $this->mob->getPathfindingMalus($currentPathType);

		if ($malus >= 0) {
			$resultNode = $this->getNodeAndUpdateCostToMax($x, $y, $z, $currentPathType, $malus);
		}

		if (static::doesBlockHavePartialCollision($preferredPathType) &&
			$resultNode !== null && $resultNode->costMalus >= 0 &&
			!$this->canReachWithoutCollision($resultNode)
		) {
			$resultNode = null;
		}

		if (!$currentPathType->equals(BlockPathTypes::WALKABLE()) && (!$this->isAmphibious() || !$currentPathType->equals(BlockPathTypes::WATER()))) {
			if (($resultNode === null || $resultNode->costMalus < 0) &&
				$targetY > 0 &&
				(!$currentPathType->equals(BlockPathTypes::FENCE()) || $this->canWalkOverFences()) &&
				!$currentPathType->equals(BlockPathTypes::UNPASSABLE_RAIL()) &&
				!$currentPathType->equals(BlockPathTypes::TRAPDOOR()) &&
				!$currentPathType->equals(BlockPathTypes::POWDER_SNOW())
			) {
				$resultNode = $this->findAcceptedNode($x, $y + 1, $z, $targetY - 1, $floorLevel, $facing, $preferredPathType);
				$width = $this->mob->getSize()->getWidth();
				if ($resultNode !== null &&
					($resultNode->type->equals(BlockPathTypes::OPEN()) || $resultNode->type->equals(BlockPathTypes::WALKABLE())) &&
					$width < 1
				) {
					$halfWidth = $width / 2;
					$sidePos = $pos->getSide($facing)->add(0.5, 0, 0.5);
					$bb = new AxisAlignedBB(
						$sidePos->x - $halfWidth,
						$this->getFloorLevel(new Vector3($sidePos->x, $y + 1, $sidePos->z)) + 0.001,
						$sidePos->z - $halfWidth,
						$sidePos->x + $halfWidth,
						$this->mob->getSize()->getHeight() + $this->getFloorLevel(new Vector3($resultNode->x, $resultNode->y, $resultNode->z)) - 0.002,
						$sidePos->z + $halfWidth
					);
					if ($this->hasCollisions($bb)) {
						$resultNode = null;
					}
				}
			}

			if (!$this->isAmphibious() && $currentPathType->equals(BlockPathTypes::WATER()) && !$this->canFloat()) {
				if (!$this->getBlockPathType($this->mob, $x, $y - 1, $z)->equals(BlockPathTypes::WATER())) {
					return $resultNode;
				}

				while ($y > World::Y_MIN) {
					$currentPathType = $this->getBlockPathType($this->mob, $x, --$y, $z);
					if (!$currentPathType->equals(BlockPathTypes::WATER())) {
						return $resultNode;
					}

					$resultNode = $this->getNodeAndUpdateCostToMax($x, $y, $z, $currentPathType, $this->mob->getPathfindingMalus($currentPathType));
				}
			}

			if ($currentPathType->equals(BlockPathTypes::OPEN())) {
				$fallDistance = 0;
				$startY = $y;

				while ($currentPathType->equals(BlockPathTypes::OPEN())) {
					if (--$y < World::Y_MIN) {
						return $this->getBlockedNode($x, $startY, $z);
					}

					if ($fallDistance++ >= $this->mob->getMaxFallDistance()) {
						return $this->getBlockedNode($x, $y, $z);
					}

					$currentPathType = $this->getBlockPathType($this->mob, $x, $y, $z);
					$malus = $this->mob->getPathfindingMalus($currentPathType);
					if (!$currentPathType->equals(BlockPathTypes::OPEN) && $malus >= 0) {
						$resultNode = $this->getNodeAndUpdateCostToMax($x, $y, $z, $currentPathType, $malus);
						break;
					}

					if ($malus < 0) {
						return $this->getBlockedNode($x, $y, $z);
					}
				}
			}

			if (static::doesBlockHavePartialCollision($currentPathType) && $resultNode === null) {
				$resultNode = $this->getNode($x, $y, $z);
				$resultNode->closed = true;
				$resultNode->type = $currentPathType;
				$resultNode->costMalus = $currentPathType->getMalus();
			}
		}
		return $resultNode;
	}

	private function getMobJumpHeight() : floar{
		return max(static::DEFAULT_MOB_JUMP_HEIGHT, $this->mob->getMaxUpStep());
	}

	private function getNodeAndUpdateCostToMax(int $x, int $y, int $z, BlockPathTypes $pathType, float $malus) : Node{
		$node = $this->getNode($x, $y, $z);
		$node->pathType = BlockPathTypes::BLOCKED();
		$node->costMalus = max($node->costMalus, $malus);

		return $node;
	}

	private function getBlockedNode(int $x, int $y, int $z) : Node{
		$node = $this->getNode($x, $y, $z);
		$node->pathType = $pathType;
		$node->costMalus = -1;

		return $node;
	}

	private function hasCollisions(AxisAlignedBB $bb) : bool{
		$bbId = spl_object_id($bb);
		if (!isset($this->collisionCache[$bbId])) {
			$this->collisionCache[$bbId] =
				count($this->world->getCollisionBlocks($bb, true) !== 0) ||
				!$this->world->isInWorld($bb->minX, $bb->minY, $bb->minZ) ||
				!$this->world->isInWorld($bb->maxX, $bb->maxY, $bb->maxZ);
		}

		return $this->collisionCache[$bbId];
	}

	/**
	 * @param array<int BlockPathTypes> $pathTypes
	 */
	public function getBlockPathTypes(World $world, int $startX, int $startY, int $startZ, array &$pathTypes, BlockPathTypes $pathType, Vector3 $mobPosition) : BlockPathTypes{
		for ($currentX = 0; $currentX < $this->entityWidth; ++$currentX) {
			for ($currentY = 0; $currentY < $this->entityHeight; ++$currentY) {
				for($currentZ = 0; $currentZ < $this->entityDepth; ++$currentZ) {
					$currentPathType = $this->evaluateBlockPathType($world, $mobPos,
						static::getBlockPathTypeStatic($world, $startX + $currentX, $startY + $currentY, $startZ + $currentZ)
					);

					if ($currentX === 0 && $currentY === 0 && $currentZ === 0) {
						$pathType = $currentPathType;
					}

					$pathTypes[] = $currentPathType;
				}
			}
		}

		return $pathType;
	}

	protected function evaluateBlockPathType(World $world, Vector3 $mobPos, BlockPathTypes $pathType) : BlockPathTypes{
		$canPassDoors = $this->canPassDoors();
		if ($pathType->equals(BlockPathTypes::DOOR_WOOD_CLOSED()) && $this->canOpenDoors() && $canPassDoors) {
			$pathType = BlockPathTypes::WALKABLE_DOOR();
		} elseif ($pathType->equals(BlockPathTypes::DOOR_OPEN()) && $canPassDoors) {
			$pathType = BlockPathTypes::BLOCKED();
		} elseif ($pathType->equals(BlockPathTypes::RAIL()) &&
			!($world->getBlock($mobPos) instanceof BaseRail) &&
			!($world->getBlock($mobPos->down()) instanceof BaseRail)
		) {
			$pathType = BlockPathTypes::UNPASSABLE_RAIL();
		}

		return $pathTypes;
	}

	public function getBlockPathType(Mob $mob, int $x, int $y, int $z) : BlockPathTypes{
		$blockHash = World::blockHash($x, $y, $z);
		if (!isset($this->pathTypesByPosCache[$blockHash])) {
			$this->pathTypesByPosCache[$blockHash] = $this->getBlockPathTypeAt($this->world, $x, $y, $z, $mob);
		}

		return $this->pathTypesByPosCache[$blockHash];
	}

	public function getBlockPathTypeAt(World $world, int $x, int $y, int $z, Mob $mob) : BlockPathTypes{
		/**
		 * @var array<int, BlockPathTypes> $pathTypes BlockPathTypes->id() => BlockPathTypes
		 */
		$pathTypes = [];
		$currentPathType = $this->getBlockPathTypes($world, $x, $y, $z, $pathTypes, BlockPathTypes::BLOCKED(), $mob->getPosition()->floor());

		foreach ([BlockPathTypes::FENCE(), BlockPathTypes::UNPASSABLE_RAIL()] as $unpassableType) {
			if (isset($pathTypes[$unpassableType->id()])) {
				return $unpassableType;
			}
		}

		$bestPathType = BlockPathTypes::BLOCKED();
		foreach ($pathTypes as $pathType) {
			if ($mob->getPathfindingMalus($pathType) < 0) {
				return $pathType;
			}

			if ($mob->getPathfindingMalus($pathType) >= $mob->getPathfindingMalus($bestPathType)) {
				$bestPathType = $pathType;
			}
		}

		return ($currentPathType->equals(BlockPathTypes::OPEN()) &&
			$mob->getPathfindingMalus($bestPathType) === 0.0 &&
			$this->entityWidth <= 1) ? BlockPathTypes::OPEN() : $bestPathType;
	}

	public static function getBlockPathTypeStatic(World $world, int $x, int $y, int $z) : BlockPathTypes{
		$pathType = static::getBlockPathTypeRaw($world, $x, $y, $z);

		if ($pathType->equals(BlockPathTypes::WALKABLE()) && $y >= World::Y_MIN + 1) {
			$pathTypeDown = static::getBlockPathTypeRaw($world, $x, $y - 1, $z);
			$pathType = (!$pathTypeDown->equals(BlockPathTypes::WALKABLE()) &&
				!$pathTypeDown->equals(BlockPathTypes::OPEN()) &&
				!$pathTypeDown->equals(BlockPathTypes::WATER()) &&
				!$pathTypeDown->equals(BlockPathTypes::LAVA())
			) ? BlockPathTypes::WALKABLE() : BlockPathTypes::OPEN();

			foreach ([
				[BlockPathTypes::DAMAGE_FIRE(), BlockPathTypes::DAMAGE_FIRE()],
				[BlockPathTypes::DAMAGE_OTHER(), BlockPathTypes::DAMAGE_OTHER()],
				[BlockPathTypes::STICKY_HONEY(), BlockPathTypes::STICKY_HONEY()],
				[BlockPathTypes::POWDER_SNOW(), BlockPathTypes::DANGER_POWDER_SNOW()],
			] as $pathMap) {
				if ($pathTypeDown->equals($pathMap[0])) {
					$pathType = $pathMap[1];
				}
			}
		}

		if ($pathType->equals(BlockPathTypes::WALKABLE())) {
			$pathType = static::checkNeighbourBlocks($world, $x, $y, $z, $pathType);
		}

		return $pathType;
	}

	public static function checkNeighbourBlocks(World $world, int $x, int $y, int $z, BlockPathTypes $pathType) : BlockPathTypes{
		for ($currentX = -1; $currentX <= 1; $currentX++) {
			for ($currentY = -1; $currentY <= 1; $currentY++) {
				for ($currentZ = -1; $currentZ <= 1; $currentZ++) {
					if ($currentX === 0 && $currentY === 0 && $currentZ === 0) {
						continue;
					}

					$block = $world->getBlockAt($x + $currentX, $y + $currentY, $z + $currentZ);
					$id = $block->getId();

					if ($id === BlockLegacyIds::CACTUS || $id === BlockLegacyIds::SWEET_BERRY_BUSH) {
						return BlockPathTypes::DANGER_OTHER();
					}

					if (static::isBurningBlock($block)) {
						return BlockPathTypes::DANGER_FIRE();
					}

					if ($block instanceof Water) {
						return BlockPathTypes::WATER_BORDER();
					}
				}
			}
		}
		return $pathType;
	}

	public static function getBlockPathTypeRaw(World $world, int $x, int $y, int $z) : BlockPathTypes{
		$block = $world->getBlockAt($x, $y, $z);
		$blockId = $block->getId();

		switch (true) {
			case ($blockId === BlockLegacyIds::AIR):
				return BlockPathTypes::OPEN();
				break;

			case ($block instanceof Trapdoor):
			case ($blockId === BlockLegacyIds::LILY_PAD):
			//TODO: big dripleaf
				return BlockPathTypes::TRAPDOOR();
				break;

			//TODO: powder snow

			case ($blockId === BlockLegacyIds::CACTUS):
			case ($blockId === BlockLegacyIds::SWEET_BERRY_BUSH):
				return BlockPathTypes::DAMAGE_OTHER();
				break;

			//TODO: honey

			case ($blockId === BlockLegacyIds::COCOA):
				return BlockPathTypes::COCOA();
				break;

			case ($blockId === BlockLegacyIds::COCOA):
				return BlockPathTypes::COCOA();
				break;

			case ($blockId === BlockLegacyIds::COCOA):
				return BlockPathTypes::COCOA();
				break;

			case ($block instanceof Water):
				return BlockPathTypes::WATER();
				break;

			case ($block instanceof Lava):
				return BlockPathTypes::LAVA();
				break;

			case (static::isBurningBlock($block)):
				return BlockPathTypes::DAMAGE_FIRE();
				break;

			case ($block instanceof Door):
				/**
				 * @var Door $block
				 */
				if (!$block->isOpen()) {
					return $block instanceof WoodenDoor ? BlockPathTypes::DOOR_WOOD_CLOSED() : BlockPathTypes::DOOR_IRON_CLOSED();
				}
				return BlockPathTypes::DOOR_OPEN();
				break;

			case ($block instanceof BaseRail):
				return BlockPathTypes::RAIL();
				break;

			case ($block instanceof Leaves):
				return BlockPathTypes::LEAVES();
				break;

			case ($block instanceof Fence):
			case ($block instanceof Wall):
				return BlockPathTypes::FENCE();
				break;

			case ($block instanceof FenceGate):
				/**
				 * @var FeceGate $block
				 */
				return $block->isOpen() ? BlockPathTypes::OPEN() : BlockPathTypes::BLOCKED();
				break;

			default:
				break;

		}
		return Utils::isPathfindable($block, PathComputationType::LAND()) ? BlockPathTypes::OPEN() : BlockPathTypes::BLOCKED();
	}

	public static function isBurningBlock(Block $block) : bool{
		$blockId = $block->getId();

		return $blockId === BlockLegacyIds::FIRE ||
			$block instanceof Lava ||
			$blockId === BlockLegacyIds::MAGMA;
			//TODO: lava cauldron
			//TODO: lit camfire
	}
}