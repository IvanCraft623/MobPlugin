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

namespace IvanCraft623\MobPlugin\entity;

use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

/**
 * @phpstan-require-extends Entity
 * @phpstan-require-implements Bucketable
 */
trait BucketableTrait{

	/** Save tags that don't travel with the bucket. */
	private const UNSAVED_TAGS = [
		"Motion",
		"FallDistance",
		"OnGround",
		"Air",
		"HurtTime",
		"PortalCooldown",
		"ActiveEffects",
		"TargetID"
	];

	private function getBucketNBT() : CompoundTag{
		$nbt = $this->saveNBT();
		foreach(self::UNSAVED_TAGS as $tag){
			$nbt->removeTag($tag);
		}

		return $nbt;
	}

	/**
	 * Swaps the water bucket in the player's hand for this entity's bucket.
	 */
	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if(!$this->isAlive() || $player->getInventory()->getItemInHand()->getTypeId() !== ItemTypeIds::WATER_BUCKET){
			return false;
		}

		Utils::transformItemInHand($player, $this->getBucketItem()->setEntityData($this->getBucketNBT()));
		$this->flagForDespawn();

		return true;
	}
}