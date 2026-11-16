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

namespace IvanCraft623\MobPlugin\block;

use IvanCraft623\MobPlugin\entity\ambient\Bat;
use IvanCraft623\MobPlugin\entity\animal\Turtle;
use IvanCraft623\MobPlugin\event\MobSpawnCause;
use IvanCraft623\MobPlugin\event\MobSpawnEvent;
use IvanCraft623\MobPlugin\sound\TurtleEggBreakSound;
use IvanCraft623\MobPlugin\sound\TurtleEggCrackSound;
use IvanCraft623\MobPlugin\sound\TurtleEggHatchSound;
use pocketmine\block\Block;
use pocketmine\block\Transparent;
use pocketmine\block\utils\StaticSupportTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\particle\BlockBreakParticle;
use function mt_rand;

/**
 * Up to four turtle eggs. On sand they crack towards dawn and hatch into baby turtles, and mobs trample them.
 */
class TurtleEgg extends Transparent{
	use StaticSupportTrait {
		canBePlacedAt as supportedWhenPlacedAt;
	}

	public const MIN_COUNT = 1;
	public const MAX_COUNT = 4;
	public const MAX_CRACK_STAGE = 2; //the stage at which the next hatch step hatches the eggs

	private const HATCH_TIME_START = 21000; //time of day, ticks: 3 AM, in the last stretch of the night
	private const HATCH_TIME_END = 22000; //time of day, ticks, exclusive: 4 AM
	private const HATCH_CHANCE = 500; //1 in this many random ticks makes progress outside of the hatch time
	private const HATCHLING_SPACING = 0.3; //blocks between the turtles of one hatch

	private const STEP_ON_TRAMPLE_CHANCE = 100; //1 in this many ticks of a mob standing on the eggs
	private const LAND_ON_TRAMPLE_CHANCE = 3; //1 in this many mobs landing on the eggs

	protected int $count = self::MIN_COUNT;
	protected int $crackStage = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(self::MIN_COUNT, self::MAX_COUNT, $this->count);
		$w->boundedIntAuto(0, self::MAX_CRACK_STAGE, $this->crackStage);
	}

	public function getCount() : int{ return $this->count; }

	/** @return $this */
	public function setCount(int $count) : self{
		if ($count < self::MIN_COUNT || $count > self::MAX_COUNT) {
			throw new \InvalidArgumentException("Count must be in range " . self::MIN_COUNT . " ... " . self::MAX_COUNT);
		}
		$this->count = $count;

		return $this;
	}

	/**
	 * From 0, not cracked, to {@see TurtleEgg::MAX_CRACK_STAGE}.
	 */
	public function getCrackStage() : int{ return $this->crackStage; }

	/** @return $this */
	public function setCrackStage(int $crackStage) : self{
		if ($crackStage < 0 || $crackStage > self::MAX_CRACK_STAGE) {
			throw new \InvalidArgumentException("Crack stage must be in range 0 ... " . self::MAX_CRACK_STAGE);
		}
		$this->crackStage = $crackStage;

		return $this;
	}

	public function isSolid() : bool{
		return false;
	}

	protected function recalculateCollisionBoxes() : array{
		$box = AxisAlignedBB::one()->trim(Facing::UP, 9 / 16);

		if ($this->count === self::MIN_COUNT) {
			foreach (Facing::HORIZONTAL as $face) {
				$box->trim($face, (Facing::isPositive($face) ? 4 : 3) / 16); //it sits a little off-centre
			}
		} else {
			$box->contract(1 / 16, 0, 1 / 16);
		}

		return [$box];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	private function canBeSupportedAt(Block $block) : bool{
		return $block->getAdjacentSupportType(Facing::DOWN)->hasCenterSupport();
	}

	public function canBePlacedAt(Block $blockReplace, Vector3 $clickVector, int $face, bool $isClickedBlock) : bool{
		return ($blockReplace instanceof TurtleEgg && $blockReplace->count < self::MAX_COUNT) ||
			$this->supportedWhenPlacedAt($blockReplace, $clickVector, $face, $isClickedBlock);
	}

	public function place(
		BlockTransaction $tx,
		Item $item,
		Block $blockReplace,
		Block $blockClicked,
		int $face,
		Vector3 $clickVector,
		?Player $player = null
	) : bool{
		if ($blockReplace instanceof TurtleEgg && $blockReplace->count < self::MAX_COUNT) {
			$this->count = $blockReplace->count + 1;
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function ticksRandomly() : bool{
		return true;
	}

	/**
	 * The eggs only progress on sand, mostly towards dawn.
	 */
	public function onRandomTick() : void{
		$world = $this->position->getWorld();
		if (!isset(Turtle::NESTING_BLOCKS[$this->getSide(Facing::DOWN)->getTypeId()]) || !$this->isHatchTime()) {
			return;
		}

		if ($this->crackStage < self::MAX_CRACK_STAGE) {
			$world->addSound($this->position->add(0.5, 0.5, 0.5), new TurtleEggCrackSound());
			$world->setBlock($this->position, (clone $this)->setCrackStage($this->crackStage + 1));
		} else {
			$this->hatch();
		}
	}

	private function isHatchTime() : bool{
		$time = $this->position->getWorld()->getTimeOfDay();

		return ($time >= self::HATCH_TIME_START && $time < self::HATCH_TIME_END) || mt_rand(0, self::HATCH_CHANCE - 1) === 0;
	}

	/**
	 * Replaces the eggs with a baby turtle each, that come home to here.
	 */
	private function hatch() : void{
		$world = $this->position->getWorld();
		$center = $this->position->add(0.5, 0, 0.5);

		$world->addSound($center, new TurtleEggHatchSound());
		$world->setBlock($this->position, VanillaBlocks::AIR());

		for ($i = 0; $i < $this->count; ++$i) {
			$turtle = new Turtle(Location::fromObject($center->add(self::HATCHLING_SPACING * $i, 0, 0), $world));
			$turtle->setBaby();
			$turtle->setHomePosition($center);
			(new MobSpawnEvent($turtle, MobSpawnCause::HATCHING))->call();
			$turtle->spawnToAll();
		}
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	/**
	 * Mobs that step on the eggs may break one.
	 */
	public function onEntityInside(Entity $entity) : bool{
		if ($entity->isOnGround() && $this->canTrample($entity) && mt_rand(0, self::STEP_ON_TRAMPLE_CHANCE - 1) === 0) {
			$this->breakOne();

			return false;
		}

		return true;
	}

	/**
	 * Falling onto the eggs is more likely to break one.
	 */
	public function onEntityLand(Entity $entity) : ?float{
		if ($this->canTrample($entity) && mt_rand(0, self::LAND_ON_TRAMPLE_CHANCE - 1) === 0) {
			$this->breakOne();
		}

		return null;
	}

	/**
	 * Turtles and bats are light enough, and a player has to watch their step.
	 */
	private function canTrample(Entity $entity) : bool{
		return $entity instanceof Living &&
			!$entity instanceof Turtle &&
			!$entity instanceof Bat &&
			!($entity instanceof Player && $entity->isSneaking());
	}

	private function breakOne(bool $showParticles = true) : void{
		$world = $this->position->getWorld();

		if ($showParticles) {
			$world->addParticle($this->position->add(0.5, 0.5, 0.5), new BlockBreakParticle($this));
		}
		$world->addSound($this->position->add(0.5, 0.5, 0.5), new TurtleEggBreakSound());
		$world->setBlock(
			$this->position,
			$this->count > self::MIN_COUNT ? (clone $this)->setCount($this->count - 1) : VanillaBlocks::AIR()
		);
	}

	/**
	 * Breaking the eggs by hand breaks one at a time, and only silk touch picks them up.
	 */
	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		if ($item->hasEnchantment(VanillaEnchantments::SILK_TOUCH())) {
			return parent::onBreak($item, $player, $returnedItems);
		}

		$this->breakOne(false); //PocketMine already shows the particles when a player breaks a block

		return true;
	}

	public function isAffectedBySilkTouch() : bool{
		return true;
	}

	public function getSilkTouchDrops(Item $item) : array{
		return [$this->asItem()->setCount($this->count)];
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return [];
	}
}