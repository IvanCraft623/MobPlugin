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

namespace IvanCraft623\MobPlugin\pattern;

use IvanCraft623\MobPlugin\entity\boss\Wither;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\golem\SnowGolem;

use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\MobHead;
use pocketmine\block\utils\MobHeadType;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\SingletonTrait;

final class BlockPatternFactory {
	use SingletonTrait;

	/** @var array<string, BlockPattern> */
	private array $patterns = [];

	public function __construct() {
		$isAir = static fn(Block $block) => $block->getTypeId() === BlockTypeIds::AIR;

		$isPumpkin = static fn(Block $block) => //TODO: a pumpkin tag will suit better
			($id = $block->getTypeId()) === BlockTypeIds::CARVED_PUMPKIN ||
			$id === BlockTypeIds::LIT_PUMPKIN ||
			$id === BlockTypeIds::PUMPKIN
		;

		$this->register(BlockPatternIds::IRON_GOLEM, BlockPatternBuilder::start()
			->aisle([
				"*O*",
				"###",
				"*#*"
			])
			->where('O', $isPumpkin)
			->where('*', $isAir)
			->where('#', static fn(Block $block) => $block->getTypeId() === BlockTypeIds::IRON)
			->trigger($isPumpkin)
			->onMatch(static function(BlockPatternMatch $match, Block $placedBlock, ?Living $owner) {
				$world = $placedBlock->getPosition()->getWorld();

				$match->clear($world);

				$golem = new IronGolem(Location::fromObject(
					$match->getBlock(1, 2, 0, $world)
						->getPosition()
						->add(0.5, 0, 0.5),
					$world
				));
				$golem->setOwningEntity($owner);
				$golem->spawnToAll();
			})
			->build()
		);

		$this->register(BlockPatternIds::SNOW_GOLEM, BlockPatternBuilder::start()
			->aisle([
				"O",
				"#",
				"#"
			])
			->where('O', $isPumpkin)
			->where('#', static fn(Block $block) => $block->getTypeId() === BlockTypeIds::SNOW)
			->trigger($isPumpkin)
			->onMatch(static function(BlockPatternMatch $match, Block $placedBlock, ?Living $owner) {
				$world = $placedBlock->getPosition()->getWorld();

				$match->clear($world);

				$golem = new SnowGolem(Location::fromObject(
					$match->getBlock(0, 2, 0, $world)
						->getPosition()
						->add(0.5, 0, 0.5),
					$world
				));
				$golem->setOwningEntity($owner);
				$golem->spawnToAll();
			})
			->build()
		);

		$isWitherSkull = static fn(Block $block) =>
			$block instanceof MobHead &&
			$block->getMobHeadType() === MobHeadType::WITHER_SKELETON
		;
		$this->register(BlockPatternIds::WITHER, BlockPatternBuilder::start()
			->aisle([
				"OOO",
				"###",
				"*#*"
			])
			->where('O', $isWitherSkull)
			->where('#', static fn(Block $block) =>
				($typeId = $block->getTypeId()) === BlockTypeIds::SOUL_SAND ||
				$typeId === BlockTypeIds::SOUL_SOIL
			)
			->where('*', $isAir)
			->trigger($isWitherSkull)
			->onMatch(static function(BlockPatternMatch $match, Block $placedBlock, ?Living $owner) {
				$world = $placedBlock->getPosition()->getWorld();

				$match->clear($world);

				$wither = new Wither(Location::fromObject(
					$match->getBlock(1, 2, 0, $world)
						->getPosition()
						->add(0.5, 0, 0.5),
					$world
				));
				$wither->setOwningEntity($owner);
				$wither->spawnToAll();
			})
			->build()
		);
	}

	public function register(string $id, BlockPattern $pattern) : void {
		$this->patterns[$id] = $pattern;
	}

	public function unregister(string $id) : void {
		unset($this->patterns[$id]);
	}

	/**
	 * Returns the patterns where the placed block acts as a trigger.
	 * @return BlockPattern[]
	 */
	public function getCandidates(Block $placedBlock) : array {
		$candidates = [];
		foreach ($this->patterns as $id => $pattern) {
			if ($pattern->isTrigger($placedBlock)) {
				$candidates[$id] = $pattern;
			}
		}
		return $candidates;
	}
}
