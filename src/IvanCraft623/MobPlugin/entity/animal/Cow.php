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

namespace IvanCraft623\MobPlugin\entity\animal;

use IvanCraft623\MobPlugin\entity\AgeableMob;
use IvanCraft623\MobPlugin\entity\ai\goal\BreedGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FloatGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\FollowParentGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\PanicGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\TemptGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\sound\CowMilkSound;
use IvanCraft623\MobPlugin\utils\ItemSet;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Bucket;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function mt_rand;

class Cow extends Animal {

	public static function getNetworkTypeId() : string{ return EntityIds::COW; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.3, 0.9, 1.3);
	}

	public function getName() : string{
		return "Cow";
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new PanicGoal($this, 1.25));
		$this->goalSelector->addGoal(2, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(3, new TemptGoal($this, 1.25, (new ItemSet())->add(VanillaItems::WHEAT()), false));
		$this->goalSelector->addGoal(4, new FollowParentGoal($this, 1.1));
		$this->goalSelector->addGoal(5, new WaterAvoidingRandomStrollGoal($this, 0.8));
		$this->goalSelector->addGoal(6, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(7, new RandomLookAroundGoal($this));
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(10);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if (!$this->isBaby() && $item instanceof Bucket) {
			Utils::transformItemInHand($player, VanillaItems::MILK_BUCKET());
			$this->broadcastSound(new CowMilkSound());
			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	public function getBreedOffspring(AgeableMob $partner) : Cow{
		return new Cow($this->getLocation());
	}

	public function getDrops() : array{
		$drops = [];
		if (!$this->isBaby()) {
			$drops = [
				VanillaItems::LEATHER()->setCount(mt_rand(0, 2)),
				($this->shouldDropCookedItems() ? VanillaItems::STEAK() : VanillaItems::RAW_BEEF())->setCount(mt_rand(1, 3))
			];
		}

		return $drops;
	}
}
