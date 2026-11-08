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

use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\NeutralMob;
use IvanCraft623\MobPlugin\entity\NeutralMobTrait;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\Pathfinder\BlockPathType;

use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

class ZombifiedPiglin extends AbstractZombie implements NeutralMob {
	use NeutralMobTrait;

	private const ANGER_TICKS = 25 * 20;

	public static function getNetworkTypeId() : string {
		return EntityIds::ZOMBIE_PIGMAN;
	}

	public function getName() : string {
		return "Zombified Piglin";
	}

	public function isFireProof() : bool{
		return true;
	}

	protected function initProperties() : void {
		parent::initProperties();

		$this->setAttackDamage(5);
		$this->navigation->setPathfindingMalus(BlockPathType::LAVA, 8);
		$this->navigation->setPathfindingMalus(BlockPathType::DANGER_FIRE, BlockPathType::OPEN_MALUS);
		$this->navigation->setPathfindingMalus(BlockPathType::DAMAGE_FIRE, BlockPathType::OPEN_MALUS);
	}

	protected function registerBehaviorGoals() : void {
		$this->goalSelector->addGoal(5, new MeleeAttackGoal($this, 1.5));
		$this->goalSelector->addGoal(8, new RandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(9, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(10, new RandomLookAroundGoal($this));
	}

	protected function registerTargetGoals() : void {
		$this->targetSelector->addGoal(1, (new HurtByTargetGoal($this))->setAlertOthers());
	}

	public function startAngerTimer() : void {
		$this->setRemainingAngerTicks(self::ANGER_TICKS);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool {
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$this->updateAnger(true);

		return $hasUpdate;
	}

	public function generateEquipment() : void{
		$this->equipFromLootTable(VanillaLootTables::ZOMBIE_PIGMAN_GEAR());
	}

	public function getDrops() : array {
		return [...$this->getEquipmentDrops(), ...$this->generateDeathLoot(VanillaLootTables::ZOMBIE_PIGMAN())];
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::ZOMBIE_PIGMAN_SPAWN_EGG();
	}
}
