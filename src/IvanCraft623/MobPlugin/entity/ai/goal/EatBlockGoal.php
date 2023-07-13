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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\entity\animal\Animal;
use IvanCraft623\MobPlugin\entity\animation\EatBlockAnimation;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\particle\BlockBreakParticle;
use function max;

class EatBlockGoal extends Goal {

	public const EAT_ANIMATION_TICKS = 40;

	/** @var array<int, Block> */
	private static array $eatableBlocks;

	/** @var array<int, Block> */
	private static array $eatableBlockReplacers;

	private static function initEatableBlocks() : void{
		if (!isset(self::$eatableBlocks)) {
			self::addEatableBlock(VanillaBlocks::GRASS(), VanillaBlocks::DIRT());
			self::addEatableBlock(VanillaBlocks::TALL_GRASS(), VanillaBlocks::AIR());
			self::addEatableBlock(VanillaBlocks::FERN(), VanillaBlocks::AIR());
		}
	}

	public static function addEatableBlock(Block $block, Block $replacer) : void{
		$id = $block->getFullId();
		self::$eatableBlocks[$id] = $block;
		self::$eatableBlockReplacers[$id] = $replacer;
	}

	public static function isEatable(Block $block) : bool{
		self::initEatableBlocks();

		return isset(self::$eatableBlocks[$block->getFullId()]);
	}

	public static function getEatableReplacer(Block $block) : Block{
		self::initEatableBlocks();

		if (!self::isEatable($block)) {
			throw new \InvalidArgumentException("Block provided is not eatable");
		}

		return clone self::$eatableBlockReplacers[$block->getFullId()];
	}

	private int $eatAnimationTick = 0;

	public function __construct(
		protected Animal $entity
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK, Goal::FLAG_JUMP);
	}

	public function canUse() : bool{
		if ($this->entity->getRandom()->nextBoundedInt($this->entity->isBaby() ? 50 : 1000) !== 0) {
			return false;
		}

		return $this->findEatableBlock() !== null;
	}

	public function start() : void{
		$this->eatAnimationTick = $this->adjustedTickDelay(self::EAT_ANIMATION_TICKS);
		$this->entity->getNavigation()->stop();

		$this->entity->broadcastAnimation(new EatBlockAnimation($this->entity));
	}

	public function stop() : void{
		$this->eatAnimationTick = 0;
	}

	public function canContinueToUse() : bool{
		return $this->eatAnimationTick > 0;
	}

	public function getEatAnimationTick() : int{
		return $this->eatAnimationTick;
	}

	public function tick() : void{
		$this->eatAnimationTick = max($this->eatAnimationTick - 1, 0);
		if ($this->eatAnimationTick === $this->adjustedTickDelay(4)) {
			$block = $this->findEatableBlock();
			if ($block !== null) {
				$world = $this->entity->getWorld();
				$world->addParticle($this->entity->getPosition()->floor()->add(0.5, 0.5, 0.5), new BlockBreakParticle($block));

				$world->setBlock($block->getPosition(), self::getEatableReplacer($block));

				$this->entity->onEat();
			}
		}
	}

	public function findEatableBlock() : ?Block{
		$world = $this->entity->getWorld();
		$pos = $this->entity->getPosition();
		foreach ([$pos, $pos->down()] as $position) {
			$block = $world->getBlock($position);
			if (self::isEatable($block)) {
				return $block;
			}
		}

		return null;
	}
}
