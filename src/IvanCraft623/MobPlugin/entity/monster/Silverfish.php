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

namespace IvanCraft623\MobPlugin\entity\monster;

use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\silverfish\InfestingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\silverfish\WakeUpFriendsGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\golem\SnowGolem;
use IvanCraft623\MobPlugin\entity\MobType;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\InfestedStone;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function count;

class Silverfish extends Monster {

	private const ALERT_OTHERS_RANGE = 20.0; //blocks, horizontally
	private const HOST_WALK_TARGET_VALUE = 10.0; //score, it likes to be at a block it can hide in
	private const MAGMA_WALK_TARGET_VALUE = -0.5; //score, and to stay off magma

	/** @var array<int, true> type ids of the blocks it can hide in, see initInfestationInfo() */
	private static array $infestableTypeIds = [];

	/** @var list<int> type ids of the infested forms of those blocks, see initInfestationInfo() */
	private static array $infestedTypeIds = [];

	/** @var array<int, int> state id xor mask that swaps a block with its infested form, keyed by the type id of both */
	private static array $infestationToggleMasks = [];

	public static function getNetworkTypeId() : string{ return EntityIds::SILVERFISH; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(
			VanillaEntitySizes::SILVERFISH_HEIGHT,
			VanillaEntitySizes::SILVERFISH_WIDTH
		);
	}

	public function getName() : string{
		return "Silverfish";
	}

	public function getMobType() : MobType{
		return MobType::ARTHROPOD();
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(1, new FloatGoal($this));
		//TODO: powder snow climb goal
		$this->goalSelector->addGoal(1, new WakeUpFriendsGoal($this));
		$this->goalSelector->addGoal(4, new MeleeAttackGoal($this, 1));
		$this->goalSelector->addGoal(5, new InfestingRandomStrollGoal($this, speedModifier: 1.0, interval: 0, checkInactivityTime: false));

		$this->targetSelector->addGoal(1, (new HurtByTargetGoal($this))->setAlertOthers()->setAlertRange(self::ALERT_OTHERS_RANGE));
		$this->targetSelector->addGoal(2, new NearestAttackableGoal(
			entity: $this,
			targetType: Living::class,
			mustSee: false,
			targetValidator: static fn(Living $entity) : bool => $entity instanceof Player || $entity instanceof IronGolem || $entity instanceof SnowGolem,
			range: 8.0
		));
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(8);
		$this->setAttackDamage(1);
	}

	public function getWalkTargetValue(Vector3 $position) : float{
		$world = $this->getWorld();
		if (self::isInfestable($world->getBlock($position))) {
			return self::HOST_WALK_TARGET_VALUE;
		}
		if ($world->getBlock($position->down())->getTypeId() === BlockTypeIds::MAGMA) {
			return self::MAGMA_WALK_TARGET_VALUE;
		}

		//TODO: light level, vanilla adds 0.5 minus the brightness here
		return parent::getWalkTargetValue($position);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	private static function initInfestationInfo() : void{
		if (count(self::$infestableTypeIds) !== 0) {
			return;
		}

		foreach (VanillaBlocks::getAll() as $block) {
			if ($block instanceof InfestedStone) {
				$hostTypeId = $block->getImitatedBlock()->getTypeId();
				$mask = $block->getStateId() ^ $block->getImitatedBlock()->getStateId();
				self::$infestableTypeIds[$hostTypeId] = true;
				self::$infestedTypeIds[] = $block->getTypeId();
				self::$infestationToggleMasks[$hostTypeId] = $mask;
				self::$infestationToggleMasks[$block->getTypeId()] = $mask;
			}
		}
	}

	/**
	 * Whether a silverfish can hide in the block, not whether it already has.
	 */
	public static function isInfestable(Block $block) : bool{
		self::initInfestationInfo();

		return isset(self::$infestableTypeIds[$block->getTypeId()]);
	}

	/**
	 * @return list<int>
	 */
	public static function getInfestedTypeIds() : array{
		self::initInfestationInfo();

		return self::$infestedTypeIds;
	}

	/**
	 * Infests a block a silverfish can hide in, or turns an infested block back, keeping its state.
	 * Both encode their state alike, so xoring the state id with the bits that set them apart swaps the type only.
	 *
	 * @return Block|null null if the block is neither
	 */
	public static function toggleInfestation(Block $block) : ?Block{
		self::initInfestationInfo();

		$mask = self::$infestationToggleMasks[$block->getTypeId()] ?? null;
		if ($mask === null) {
			return null;
		}

		return RuntimeBlockStateRegistry::getInstance()->fromStateId($block->getStateId() ^ $mask);
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::SILVERFISH_SPAWN_EGG();
	}
}
