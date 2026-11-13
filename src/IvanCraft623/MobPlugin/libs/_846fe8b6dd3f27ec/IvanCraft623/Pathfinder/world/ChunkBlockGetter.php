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

namespace IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\IvanCraft623\Pathfinder\world;

use pocketmine\block\Block;
use pocketmine\block\Door;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
use pocketmine\world\Position;
use pocketmine\world\World;

use ReflectionProperty;
use function count;
use function max;
use function min;

/**
 * @phpstan-import-type BlockPosHash from World
 * @phpstan-import-type ChunkPosHash from World
 */
class ChunkBlockGetter extends BlockGetter{

	private const BLOCK_CACHE_SIZE_CAP = 2048;

	private bool $inDynamicStateRecalculation = false;

	/**
	 * @var Block[]
	 * @phpstan-var array<BlockPosHash, Block>
	 */
	private array $blocksCache;

	private int $blockCacheSize = 0;

	/**
	 * Sections may hold any of a set, the chunks don't change while it is used.
	 *
	 * @phpstan-var \WeakMap<BlockStateSet, array<ChunkPosHash, array<int, bool>>>
	 */
	private \WeakMap $sectionsMayContain;

	public function __construct(private ChunkSource $chunks, int $minY, int $maxY){
		parent::__construct($minY, $maxY);
		$this->sectionsMayContain = new \WeakMap();
	}

	public function mayContainAny(BlockStateSet $states, int $minX, int $minY, int $minZ, int $maxX, int $maxY, int $maxZ) : bool{
		$minY = max($minY, $this->minY);
		$maxY = min($maxY, $this->maxY - 1);
		/** @phpstan-var array<ChunkPosHash, array<int, bool>> $sections */
		$sections = $this->sectionsMayContain[$states] ?? [];
		$mayContain = false;
		for ($chunkX = $minX >> Chunk::COORD_BIT_SIZE; !$mayContain && $chunkX <= $maxX >> Chunk::COORD_BIT_SIZE; ++$chunkX) {
			for ($chunkZ = $minZ >> Chunk::COORD_BIT_SIZE; !$mayContain && $chunkZ <= $maxZ >> Chunk::COORD_BIT_SIZE; ++$chunkZ) {
				$chunkHash = World::chunkHash($chunkX, $chunkZ);
				for ($sectionY = $minY >> SubChunk::COORD_BIT_SIZE; !$mayContain && $sectionY <= $maxY >> SubChunk::COORD_BIT_SIZE; ++$sectionY) {
					$mayContain = $sections[$chunkHash][$sectionY] ??= $this->sectionMayContain($states, $chunkX, $sectionY, $chunkZ);
				}
			}
		}
		$this->sectionsMayContain[$states] = $sections;
		return $mayContain;
	}

	private function sectionMayContain(BlockStateSet $states, int $chunkX, int $sectionY, int $chunkZ) : bool{
		$chunk = $this->chunks->getChunk($chunkX, $chunkZ);
		if ($chunk === null || $sectionY < Chunk::MIN_SUBCHUNK_INDEX || $sectionY > Chunk::MAX_SUBCHUNK_INDEX) {
			return $states->contains(VanillaBlocks::AIR()->getStateId()); //read as air
		}
		$section = $chunk->getSubChunk($sectionY);
		$layers = $section->getBlockLayers();
		if (count($layers) === 0) {
			return $states->contains($section->getEmptyBlockId());
		}
		foreach ($layers[0]->getPalette() as $stateId) {
			if ($states->contains($stateId)) {
				return true;
			}
		}
		return false;
	}

	public function getBlockAt(int $x, int $y, int $z, bool $cached = true, bool $addToCache = true) : Block{
		$blockHash = 0;
		if (!$this->isInWorld($x, $y, $z)) {
			$block = VanillaBlocks::AIR();
			$addToCache = false;
		} else {
			$blockHash = World::blockHash($x, $y, $z);
			if ($cached && isset($this->blocksCache[$blockHash])) {
				return $this->blocksCache[$blockHash];
			}

			if (($chunk = $this->chunks->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)) === null) {
				$block = VanillaBlocks::AIR();
			} else {
				$block = RuntimeBlockStateRegistry::getInstance()->fromStateId(
					$chunk->getBlockStateId($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK)
				);
			}
		}

		$this->positionBlock($block, new Position($x, $y, $z, null));

		if($this->inDynamicStateRecalculation) {
			//this ensures that it's impossible for dynamic state properties to recursively depend on each other.
			$addToCache = false;
		} else {
			$this->inDynamicStateRecalculation = true;
			$replacement = $this->readStateFromWorld($block);
			if($replacement !== $block){
				$this->positionBlock($replacement, clone $block->getPosition());
				$block = $replacement;
			}
			$this->inDynamicStateRecalculation = false;
		}

		if ($addToCache) {
			$this->blocksCache[$blockHash] = $block;
			if (++$this->blockCacheSize >= self::BLOCK_CACHE_SIZE_CAP) {
				$this->blocksCache = [];
				$this->blockCacheSize = 0;
			}
		}

		return $block;
	}

	private function positionBlock(Block $block, Position $position) : void{
		/** @var ?ReflectionProperty $positionProperty */
		static $positionProperty = null;
		$positionProperty ??= new ReflectionProperty(Block::class, "position");
		$positionProperty->setValue($block, $position);
	}

	/**
	 * Called when the block is created
	 *
	 * Replacement of {@link Block::readStateFromWorld()}, because that function cannot be executed
	 * since the World class is unavailable.
	 *
	 * A replacement block may be returned. This only is useful if the block type changed due to reading
	 * others blocks data.
	 */
	private function readStateFromWorld(Block $block) : Block{
		if ($block instanceof Door) {
			$blockIsTop = $block->isTop();
			$other = $this->getBlockAtSide($block->getPosition(), $blockIsTop ? Facing::DOWN : Facing::UP);
			if($other instanceof Door && $other->hasSameTypeId($block)) {
				if ($blockIsTop) {
					$block->setFacing($other->getFacing())
						->setOpen($other->isOpen());
				} else {
					$block->setHingeRight($other->isHingeRight());
				}
			}
		}

		return $block;
	}

	/**
	 * Returns the Block on the side $side, works like Vector3::getSide()
	 */
	public function getBlockAtSide(Vector3 $position, int $side, int $step = 1) : Block{
		[$dx, $dy, $dz] = Facing::OFFSET[$side] ?? [0, 0, 0];
		return $this->getBlockAt(
			(int) $position->x + ($dx * $step),
			(int) $position->y + ($dy * $step),
			(int) $position->z + ($dz * $step)
		);
	}
}