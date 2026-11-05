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
use IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\PathComputationType;
use IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\world\BlockGetter;
use IvanCraft623\MobPlugin\libs\_f85971d879c01168\IvanCraft623\Pathfinder\world\BlockStateSet;
use pocketmine\block\BaseRail;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Campfire;
use pocketmine\block\Door;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;
use pocketmine\block\Lava;
use pocketmine\block\Leaves;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\block\Trapdoor;
use pocketmine\block\Wall;
use pocketmine\block\Water;
use pocketmine\block\WoodenDoor;
use pocketmine\math\Vector3;

/**
 * Path types of blocks on their own, whatever the mob.
 */
final class BlockClassifier{
	/** Tallest shape a mob walks over instead of around */
	private const MAX_WALK_OVER_HEIGHT = 0.6;

	/**
	 * @var bool[] state id => whether a mob can enter its cell
	 * @phpstan-var array<int, bool>
	 */
	private static array $lowShapes = [];

	/**
	 * Static properties are per thread, so each worker builds its own.
	 *
	 * @var BlockPathType[]|false[] type id => raw type of all its states, false if it depends on the state
	 * @phpstan-var array<int, BlockPathType|false>
	 */
	private static array $rawTypesByTypeId = [];

	/**
	 * @var Block[][]|null type id => its states
	 * @phpstan-var array<int, list<Block>>|null
	 */
	private static ?array $statesByTypeId = null;

	private static ?BlockStateSet $dangerousStates = null;

	private function __construct(){
	}

	/**
	 * Type of the block itself.
	 */
	public static function getRawType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		$block = $blockGetter->getBlockAt($x, $y, $z);
		$typeId = $block->getTypeId();
		$rawType = self::$rawTypesByTypeId[$typeId] ?? (self::$rawTypesByTypeId[$typeId] = self::getRawTypeOfAllStates($typeId));
		return $rawType !== false ? $rawType : self::getBlockRawType($block);
	}

	/**
	 * The raw type every state of the block type has, false if it depends on the state.
	 */
	private static function getRawTypeOfAllStates(int $typeId) : BlockPathType|false{
		if (self::$statesByTypeId === null) {
			self::$statesByTypeId = [];
			foreach (RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $state) {
				self::$statesByTypeId[$state->getTypeId()][] = $state;
			}
		}

		$rawType = false;
		foreach (self::$statesByTypeId[$typeId] ?? [] as $state) {
			try {
				$stateRawType = self::getBlockRawType($state);
			} catch (\Throwable) {
				return false; //needs a world to tell
			}
			if ($rawType !== false && $stateRawType !== $rawType) {
				return false;
			}
			$rawType = $stateRawType;
		}
		return $rawType;
	}

	private static function getBlockRawType(Block $block) : BlockPathType{
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

			case (self::isBurning($block)):
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
		return self::isPathfindable($block, PathComputationType::LAND) ? BlockPathType::OPEN : BlockPathType::BLOCKED;
	}

	/**
	 * Type of the block for a mob on land, from the block and the one below it.
	 */
	public static function getType(BlockGetter $blockGetter, int $x, int $y, int $z) : BlockPathType{
		$pathType = self::getRawType($blockGetter, $x, $y, $z);

		if ($pathType === BlockPathType::OPEN && $y >= $blockGetter->minY + 1) {
			$pathTypeDown = self::getRawType($blockGetter, $x, $y - 1, $z);

			if ($pathTypeDown === BlockPathType::OPEN ||
				$pathTypeDown === BlockPathType::WATER ||
				$pathTypeDown === BlockPathType::LAVA ||
				$pathTypeDown === BlockPathType::WALKABLE
			) {
				// Block below is passable/fluid — we are floating above it
				$pathType = BlockPathType::OPEN;
			} else {
				// Block below is solid-ish — check for hazard overrides first, then fall back to WALKABLE
				if ($pathTypeDown === BlockPathType::DAMAGE_FIRE) {
					$pathType = BlockPathType::DAMAGE_FIRE;
				} elseif ($pathTypeDown === BlockPathType::DAMAGE_OTHER) {
					$pathType = BlockPathType::DAMAGE_OTHER;
				} elseif ($pathTypeDown === BlockPathType::STICKY_HONEY) {
					$pathType = BlockPathType::STICKY_HONEY;
				} elseif ($pathTypeDown === BlockPathType::POWDER_SNOW) {
					$pathType = BlockPathType::DANGER_POWDER_SNOW;
				} else {
					$pathType = BlockPathType::WALKABLE;
				}
			}
		}

		if ($pathType === BlockPathType::WALKABLE) {
			$pathType = self::getNeighbourDanger($blockGetter, $x, $y, $z, $pathType);
		}

		return $pathType;
	}

	/**
	 * The danger of the first dangerous block around, or $pathType if there is none.
	 */
	public static function getNeighbourDanger(BlockGetter $blockGetter, int $x, int $y, int $z, BlockPathType $pathType) : BlockPathType{
		self::$dangerousStates ??= BlockStateSet::of(static fn(Block $block) : bool => self::getDanger($block) !== null);
		if (!$blockGetter->mayContainAny(self::$dangerousStates, $x - 1, $y - 1, $z - 1, $x + 1, $y + 1, $z + 1)) {
			return $pathType;
		}

		for ($currentX = -1; $currentX <= 1; $currentX++) {
			for ($currentY = -1; $currentY <= 1; $currentY++) {
				for ($currentZ = -1; $currentZ <= 1; $currentZ++) {
					if ($currentX === 0 && $currentY === 0 && $currentZ === 0) {
						continue;
					}

					$danger = self::getDanger($blockGetter->getBlockAt($x + $currentX, $y + $currentY, $z + $currentZ));
					if ($danger !== null) {
						return $danger;
					}
				}
			}
		}
		return $pathType;
	}

	/**
	 * What being next to the block is, if dangerous.
	 */
	private static function getDanger(Block $block) : ?BlockPathType{
		$id = $block->getTypeId();
		if ($id === BlockTypeIds::CACTUS || $id === BlockTypeIds::SWEET_BERRY_BUSH) {
			return BlockPathType::DANGER_OTHER;
		}
		if (self::isBurning($block)) {
			return BlockPathType::DANGER_FIRE;
		}
		if ($block instanceof Water) {
			return BlockPathType::WATER_BORDER;
		}
		return null;
	}

	/**
	 * Height a mob at $pos stands at, from the shape of the block below.
	 */
	public static function getFloorLevel(BlockGetter $blockGetter, Vector3 $pos) : float{
		$down = $pos->down();
		$traceResult = $blockGetter->getBlock($down)->calculateIntercept($pos, $down);

		return $traceResult === null ? $down->getY() : $traceResult->getHitVector()->getY();
	}

	public static function isBurning(Block $block) : bool{
		$blockId = $block->getTypeId();

		return $blockId === BlockTypeIds::FIRE ||
			($block instanceof Campfire && $block->isLit()) ||
			$block instanceof Lava ||
			$blockId === BlockTypeIds::MAGMA ||
			$blockId === BlockTypeIds::LAVA_CAULDRON;
	}

	public static function isPathfindable(Block $block, PathComputationType $pathType) : bool{
		if ($block instanceof Door) {
			if ($pathType === PathComputationType::LAND || $pathType === PathComputationType::AIR) {
				return $block->isOpen();
			}
			return false;
		} elseif ($block instanceof Slab) {
			//TODO: Waterlogging check
			return false;
		}

		switch ($block->getTypeId()) {
			case BlockTypeIds::ANVIL:
			case BlockTypeIds::BREWING_STAND:
			case BlockTypeIds::DRAGON_EGG:
			case BlockTypeIds::RESPAWN_ANCHOR:
			case BlockTypeIds::END_ROD:
			case BlockTypeIds::LIGHTNING_ROD:
			//TODO: piston arm
				return false;
			case BlockTypeIds::DEAD_BUSH:
				return $pathType === PathComputationType::AIR ? true : self::isPathfindableByDefault($block, $pathType);
			default:
				return self::isPathfindableByDefault($block, $pathType);
		}
	}

	private static function isPathfindableByDefault(Block $block, PathComputationType $pathType) : bool{
		return match($pathType){
			PathComputationType::LAND, PathComputationType::AIR => self::hasWalkableShape($block),
			PathComputationType::WATER => $block instanceof Water, //TODO: watterlogging check
		};
	}

	private static function hasWalkableShape(Block $block) : bool{
		return self::$lowShapes[$block->getStateId()] ??= self::isShapeLow($block) || $block instanceof Stair;
	}

	private static function isShapeLow(Block $block) : bool{
		//The registry's block has its shape at the origin, as cached by isFullCube() before
		$shape = (RuntimeBlockStateRegistry::getInstance()->getAllKnownStates()[$block->getStateId()] ?? $block)->getCollisionBoxes();
		foreach ($shape as $box) {
			if ($box->maxY > self::MAX_WALK_OVER_HEIGHT) {
				return false;
			}
		}
		return true;
	}
}