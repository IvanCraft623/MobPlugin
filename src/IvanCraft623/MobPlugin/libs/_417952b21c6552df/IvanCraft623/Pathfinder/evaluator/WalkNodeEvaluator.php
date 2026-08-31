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

namespace IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\evaluator;

use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\Node;
use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\PathComputationType;
use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\Target;
use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\utils\EnumSet;
use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\utils\Utils;
use IvanCraft623\MobPlugin\libs\_417952b21c6552df\IvanCraft623\Pathfinder\world\BlockGetter;

use pocketmine\block\BaseRail;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Campfire;
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
use pocketmine\utils\Limits;
use pocketmine\world\World;
use function ceil;
use function floor;
use function max;
use function min;

class WalkNodeEvaluator extends EntityNodeEvaluator {

	public const DEFAULT_MOB_JUMP_HEIGHT = 1.125;

	/** @var array<int, BlockPathType> World::blockHash() => BlockPathType */
	protected array $pathTypesByPosCache = [];

	public function done() : void{
		$this->pathTypesByPosCache = [];

		parent::done();
	}

	public function getStart() : Node{
		$position = clone $this->startPosition;

		$y = (int) floor($position->y);
		$block = $this->blockGetter->getBlock($position);

		if (!($block instanceof Liquid && $this->canStandOnFluid($block))) {
			if ($this->canFloat() && $this->isEntityUnderwater() && $block instanceof Water) {
				while (true) {
					if (!$block instanceof Water) {
						--$y;
						break;
					}
					$block = $this->blockGetter->getBlockAt((int) floor($position->x), ++$y, (int) floor($position->z));
				}
			} elseif ($this->isEntityOnGround()) {
				$y = (int) floor($this->startPosition->y + 0.5);
			} else {
				$pos = new Vector3((int) floor($this->startPosition->x), (int) floor($this->startPosition->y) + 1, (int) floor($this->startPosition->z));

				while ((($b = $this->blockGetter->getBlock($pos))->getTypeId() === BlockTypeIds::AIR || Utils::isPathfindable($b, PathComputationType::LAND())) && $pos->y > World::Y_MIN) {
					$pos = $pos->down();
				}

				$y = $pos->y + 1;
			}
		} else {
			while ($block instanceof Liquid && $this->canStandOnFluid($block)) {
				$position->y = ++$y;
				$block = $this->blockGetter->getBlock($position);
			}

			--$y;
		}

		$position->y = $y;
		return $this->getStartNode($position);
	}

	protected function getStartNode(Vector3 $position) : Node{
		$node = $this->getNodeAt(
			(int) max(Limits::INT32_MIN, min(Limits::INT32_MAX, (int) floor($position->x))),
			(int) max($this->blockGetter->minY, min($this->blockGetter->maxY - 1, (int) floor($position->y))),
			(int) max(Limits::INT32_MIN, min(Limits::INT32_MAX, (int) floor($position->z)))
		);

		$node->type = $this->getCachedBlockPathType($this->blockGetter, $node->x(), $node->y(), $node->z());
		$node->costMalus = $this->pathTypeCostMap->getPathfindingMalus($node->type);

		return $node;
	}

	public function getGoal(float $x, float $y, float $z) : Target{
		return $this->getTargetFromNode($this->getNodeAt(
			(int) max(Limits::INT32_MIN, min(Limits::INT32_MAX, (int) floor($x))),
			(int) max($this->blockGetter->minY, min($this->blockGetter->maxY - 1, (int) floor($y))),
			(int) max(Limits::INT32_MIN, min(Limits::INT32_MAX, (int) floor($z)))
		));
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

		$pathType = $this->getCachedBlockPathType($this->blockGetter, $nodeX, $nodeY, $nodeZ);
		$aboveY = $nodeY + 1;
		$pathTypeAbove = $this->blockGetter->isInWorld($nodeX, $aboveY, $nodeZ)
			? $this->getCachedBlockPathType($this->blockGetter, $nodeX, $aboveY, $nodeZ)
			: BlockPathType::BLOCKED;

		if ($this->pathTypeCostMap->getPathfindingMalus($pathTypeAbove) >= 0 && !$pathType->equals(BlockPathType::STICKY_HONEY)) {
			$maxUpStep = (int) floor(max(1, $this->getMaxUpStep()));
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

		if (!$neighbor1->type->equals(BlockPathType::WALKABLE_DOOR) &&
			!$neighbor2->type->equals(BlockPathType::WALKABLE_DOOR) &&
			!$diagonal->type->equals(BlockPathType::WALKABLE_DOOR)
		) {
			$isFence = $neighbor1->type->equals(BlockPathType::FENCE) &&
				$neighbor2->type->equals(BlockPathType::FENCE) &&
				$this->entitySizeInfo->getWidth() < 0.5;
			return $diagonal->costMalus >= 0 &&
				($neighbor1->y < $node->y || $neighbor1->costMalus >= 0 || $isFence) &&
				($neighbor2->y < $node->y || $neighbor2->costMalus >= 0 || $isFence);
		}
		return false;
	}

	public static function doesBlockHavePartialCollision(BlockPathType $pathType) : bool{
		return $pathType->equals(BlockPathType::FENCE) ||
			$pathType->equals(BlockPathType::DOOR_WOOD_CLOSED) ||
			$pathType->equals(BlockPathType::DOOR_IRON_CLOSED);
	}

	private function canReachWithoutCollision(Node $node) : bool{
		$bb = clone $this->getEntityBoundingBox();
		$mobPos = $this->startPosition;

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
		if (($this->canFloat() || $this->isAmphibious()) && $this->blockGetter->getBlock($pos) instanceof Water) {
			return $pos->getY() + 0.5;
		}
		return static::getFloorLevelAt($this->blockGetter, $pos);
	}

	public static function getFloorLevelAt(BlockGetter $blockGetter, Vector3 $pos) : float{
		$down = $pos->down();
		$traceResult = $blockGetter->getBlock($down)->calculateIntercept($pos, $down);

		return $traceResult === null ? $down->getY() : $traceResult->getHitVector()->getY();
	}

	protected function isAmphibious() : bool{
		return false;
	}

	public function findAcceptedNode(int $x, int $y, int $z, int $remainingJumpHeight, float $floorLevel, int $facing, BlockPathType $originPathType) : ?Node{
		if (!$this->blockGetter->isInWorld($x, $y, $z)) {
			return null;
		}

		$resultNode = null;
		$pos = new Vector3($x, $y, $z);

		if ($this->getFloorLevel($pos) - $floorLevel > $this->getMobJumpHeight()) {
			return null;
		}

		$currentPathType = $this->getCachedBlockPathType($this->blockGetter, $x, $y, $z);
		$malus = $this->pathTypeCostMap->getPathfindingMalus($currentPathType);

		if ($malus >= 0) {
			$resultNode = $this->getNodeAndUpdateCostToMax($x, $y, $z, $currentPathType, $malus);
		}

		if (static::doesBlockHavePartialCollision($originPathType) &&
			$resultNode !== null && $resultNode->costMalus >= 0 &&
			!$this->canReachWithoutCollision($resultNode)
		) {
			$resultNode = null;
		}

		if (!$currentPathType->equals(BlockPathType::WALKABLE) && (!$this->isAmphibious() || !$currentPathType->equals(BlockPathType::WATER))) {
			if (($resultNode === null || $resultNode->costMalus < 0) &&
				$remainingJumpHeight > 0 &&
				(!$currentPathType->equals(BlockPathType::FENCE) || $this->canWalkOverFences()) &&
				!$currentPathType->equals(BlockPathType::UNPASSABLE_RAIL) &&
				!$currentPathType->equals(BlockPathType::TRAPDOOR) &&
				!$currentPathType->equals(BlockPathType::POWDER_SNOW)
			) {
				$resultNode = $this->findAcceptedNode($x, $y + 1, $z, $remainingJumpHeight - 1, $floorLevel, $facing, $originPathType);
				$width = $this->entitySizeInfo->getWidth();
				if ($resultNode !== null &&
					($resultNode->type->equals(BlockPathType::OPEN) || $resultNode->type->equals(BlockPathType::WALKABLE))
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
						maxY: $this->entitySizeInfo->getHeight() + max($y1, $y2) - 0.002,
						maxZ: max($sidePos->z, $originSidePos->z) + $halfWidth
					);
					if ($this->hasCollisions($bb)) {
						$resultNode = null;
					}
				}
			}

			if (!$this->isAmphibious() && $currentPathType->equals(BlockPathType::WATER) && !$this->canFloat()) {
				if ($y - 1 < $this->blockGetter->minY ||
					!$this->getCachedBlockPathType($this->blockGetter, $x, $y - 1, $z)->equals(BlockPathType::WATER)
				) {
					return $resultNode;
				}

				while ($y > World::Y_MIN) {
					$currentPathType = $this->getCachedBlockPathType($this->blockGetter, $x, --$y, $z);
					if (!$currentPathType->equals(BlockPathType::WATER)) {
						return $resultNode;
					}

					$resultNode = $this->getNodeAndUpdateCostToMax($x, $y, $z, $currentPathType, $this->pathTypeCostMap->getPathfindingMalus($currentPathType));
				}
			}

			if ($currentPathType->equals(BlockPathType::OPEN)) {
				$fallDistance = 0;
				$startY = $y;

				while ($currentPathType->equals(BlockPathType::OPEN)) {
					if (--$y < World::Y_MIN) {
						return $this->getBlockedNode($x, $startY, $z);
					}

					if ($fallDistance++ >= $this->getMaxFallDistance()) {
						return $this->getBlockedNode($x, $y, $z);
					}

					$currentPathType = $this->getCachedBlockPathType($this->blockGetter, $x, $y, $z);
					$malus = $this->pathTypeCostMap->getPathfindingMalus($currentPathType);
					if (!$currentPathType->equals(BlockPathType::OPEN) && $malus >= 0) {
						$resultNode = $this->getNodeAndUpdateCostToMax($x, $y, $z, $currentPathType, $malus);
						break;
					}

					if ($malus < 0) {
						return $this->getBlockedNode($x, $y, $z);
					}
				}
			}

			if (static::doesBlockHavePartialCollision($currentPathType) && $resultNode === null) {
				$resultNode = $this->getNodeAt($x, $y, $z);
				$resultNode->closed = true;
				$resultNode->type = $currentPathType;
				$resultNode->costMalus = $currentPathType->getMalus();
			}
		}

		return $resultNode;
	}

	private function getMobJumpHeight() : float{
		return max(static::DEFAULT_MOB_JUMP_HEIGHT, $this->getMaxUpStep());
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
		if (!$this->blockGetter->isInWorld((int) floor($bb->minX), (int) floor($bb->minY), (int) floor($bb->minZ)) ||
			!$this->blockGetter->isInWorld((int) floor($bb->maxX), (int) floor($bb->maxY), (int) floor($bb->maxZ))
		) {
			return true;
		}

		foreach ($this->blockGetter->getCollisionBlocks($bb) as $block) {
			if ($block->isSolid()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param EnumSet<BlockPathType> $pathTypes
	 */
	public function getBlockPathTypes(BlockGetter $blockGetter, int $startX, int $startY, int $startZ, EnumSet $pathTypes, BlockPathType $pathType, Vector3 $mobPos) : BlockPathType{
		for ($currentX = 0; $currentX < $this->entityWidth; ++$currentX) {
			for ($currentY = 0; $currentY < $this->entityHeight; ++$currentY) {
				for($currentZ = 0; $currentZ < $this->entityDepth; ++$currentZ) {
					$currentPathType = $this->evaluateBlockPathType($blockGetter, $mobPos,
						$this->getBlockPathType($blockGetter, $startX + $currentX, $startY + $currentY, $startZ + $currentZ)
					);

					if ($currentX === 0 && $currentY === 0 && $currentZ === 0) {
						$pathType = $currentPathType;
					}

					$pathTypes->add($currentPathType);
				}
			}
		}

		return $pathType;
	}

	protected function evaluateBlockPathType(BlockGetter $blockGetter, Vector3 $mobPos, BlockPathType $pathType) : BlockPathType{
		$canPassDoors = $this->canPassDoors();
		if ($pathType->equals(BlockPathType::DOOR_WOOD_CLOSED) && $this->canOpenDoors() && $canPassDoors) {
			$pathType = BlockPathType::WALKABLE_DOOR;
		} elseif ($pathType->equals(BlockPathType::DOOR_OPEN) && !$canPassDoors) {
			$pathType = BlockPathType::BLOCKED;
		} elseif ($pathType->equals(BlockPathType::RAIL) &&
			!($blockGetter->getBlock($mobPos) instanceof BaseRail) &&
			!($blockGetter->getBlock($mobPos->down()) instanceof BaseRail)
		) {
			$pathType = BlockPathType::UNPASSABLE_RAIL;
		}

		return $pathType;
	}

	public function getCachedBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		$blockHash = World::blockHash($x, $y, $z);
		if (!isset($this->pathTypesByPosCache[$blockHash])) {
			$this->pathTypesByPosCache[$blockHash] = $this->getBlockPathTypeAt($blockGetter, $x, $y, $z);
		}

		return $this->pathTypesByPosCache[$blockHash];
	}

	public function getBlockPathTypeAt(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		$this->pathTypes ??= new EnumSet(BlockPathType::class);
		/** @var EnumSet<BlockPathType> $pathTypes */
		$pathTypes = $this->pathTypes;
		$pathTypes->clear();
		$currentPathType = $this->getBlockPathTypes($blockGetter, $x, $y, $z, $pathTypes, BlockPathType::BLOCKED, $this->startPosition->floor());

		foreach ([BlockPathType::FENCE, BlockPathType::UNPASSABLE_RAIL] as $unpassableType) {
			if ($pathTypes->contains($unpassableType)) {
				return $unpassableType;
			}
		}

		$bestPathType = BlockPathType::BLOCKED;
		$bestMalus = $this->pathTypeCostMap->getPathfindingMalus($bestPathType);
		foreach ($pathTypes as $pathType) {
			$malus = $this->pathTypeCostMap->getPathfindingMalus($pathType);
			if ($malus < 0) {
				return $pathType;
			}

			if ($malus >= $bestMalus) {
				$bestPathType = $pathType;
				$bestMalus = $malus;
			}
		}

		return ($currentPathType->equals(BlockPathType::OPEN) &&
			$bestMalus === 0.0 &&
			$this->entityWidth <= 1) ? BlockPathType::OPEN : $bestPathType;
	}

	public function getBlockPathType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		return static::getBlockPathTypeStatic($blockGetter, $x, $y, $z);
	}

	public static function getBlockPathTypeStatic(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		$pathType = static::getBlockPathTypeRaw($blockGetter, $x, $y, $z);

		if ($pathType->equals(BlockPathType::OPEN) && $y >= World::Y_MIN + 1) {
			$pathTypeDown = static::getBlockPathTypeRaw($blockGetter, $x, $y - 1, $z);

			if ($pathTypeDown->equals(BlockPathType::OPEN) ||
				$pathTypeDown->equals(BlockPathType::WATER) ||
				$pathTypeDown->equals(BlockPathType::LAVA) ||
				$pathTypeDown->equals(BlockPathType::WALKABLE)
			) {
				// Block below is passable/fluid — we are floating above it
				$pathType = BlockPathType::OPEN;
			} else {
				// Block below is solid-ish — check for hazard overrides first, then fall back to WALKABLE
				if ($pathTypeDown->equals(BlockPathType::DAMAGE_FIRE)) {
					$pathType = BlockPathType::DAMAGE_FIRE;
				} elseif ($pathTypeDown->equals(BlockPathType::DAMAGE_OTHER)) {
					$pathType = BlockPathType::DAMAGE_OTHER;
				} elseif ($pathTypeDown->equals(BlockPathType::STICKY_HONEY)) {
					$pathType = BlockPathType::STICKY_HONEY;
				} elseif ($pathTypeDown->equals(BlockPathType::POWDER_SNOW)) {
					$pathType = BlockPathType::DANGER_POWDER_SNOW;
				} else {
					$pathType = BlockPathType::WALKABLE;
				}
			}
		}

		if ($pathType->equals(BlockPathType::WALKABLE)) {
			$pathType = static::checkNeighbourBlocks($blockGetter, $x, $y, $z, $pathType);
		}

		return $pathType;
	}

	public static function checkNeighbourBlocks(BlockGetter $blockGetter, int $x, int $y, int $z, BlockPathType $pathType) : BlockPathType{
		for ($currentX = -1; $currentX <= 1; $currentX++) {
			for ($currentY = -1; $currentY <= 1; $currentY++) {
				for ($currentZ = -1; $currentZ <= 1; $currentZ++) {
					if ($currentX === 0 && $currentY === 0 && $currentZ === 0) {
						continue;
					}

					$block = $blockGetter->getBlockAt($x + $currentX, $y + $currentY, $z + $currentZ);
					$id = $block->getTypeId();

					if ($id === BlockTypeIds::CACTUS || $id === BlockTypeIds::SWEET_BERRY_BUSH) {
						return BlockPathType::DANGER_OTHER;
					}

					if (static::isBurningBlock($block)) {
						return BlockPathType::DANGER_FIRE;
					}

					if ($block instanceof Water) {
						return BlockPathType::WATER_BORDER;
					}
				}
			}
		}
		return $pathType;
	}

	public static function getBlockPathTypeRaw(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		$block = $blockGetter->getBlockAt($x, $y, $z);
		$blockId = $block->getTypeId();

		switch (true) {
			case ($blockId === BlockTypeIds::AIR):
				return BlockPathType::OPEN;

			case ($block instanceof Trapdoor):
			case ($blockId === BlockTypeIds::LILY_PAD):
			case ($blockId === BlockTypeIds::BIG_DRIPLEAF_HEAD):
				return BlockPathType::TRAPDOOR;

			//TODO: powder snow

			case ($blockId === BlockTypeIds::CACTUS):
			case ($blockId === BlockTypeIds::SWEET_BERRY_BUSH):
				return BlockPathType::DAMAGE_OTHER;

			//TODO: honey

			case ($blockId === BlockTypeIds::COCOA_POD):
				return BlockPathType::COCOA;

			case ($block instanceof Water):
				return BlockPathType::WATER;

			case ($block instanceof Lava):
				return BlockPathType::LAVA;

			case (static::isBurningBlock($block)):
				return BlockPathType::DAMAGE_FIRE;

			case ($block instanceof Door):
				if (!$block->isOpen()) {
					return $block instanceof WoodenDoor ? BlockPathType::DOOR_WOOD_CLOSED : BlockPathType::DOOR_IRON_CLOSED;
				}
				return BlockPathType::DOOR_OPEN;

			case ($block instanceof BaseRail):
				return BlockPathType::RAIL;

			case ($block instanceof Leaves):
				return BlockPathType::LEAVES;

			case ($block instanceof Fence):
			case ($block instanceof Wall):
				return BlockPathType::FENCE;

			case ($block instanceof FenceGate):
				return $block->isOpen() ? BlockPathType::OPEN : BlockPathType::FENCE;

			default:
				break;

		}
		return Utils::isPathfindable($block, PathComputationType::LAND()) ? BlockPathType::OPEN : BlockPathType::BLOCKED;
	}

	public static function isBurningBlock(Block $block) : bool{
		$blockId = $block->getTypeId();

		return $blockId === BlockTypeIds::FIRE ||
			($block instanceof Campfire && $block->isLit()) ||
			$block instanceof Lava ||
			$blockId === BlockTypeIds::MAGMA ||
			$blockId === BlockTypeIds::LAVA_CAULDRON;
	}
}