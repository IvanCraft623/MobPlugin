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

use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;

/**
 * Sitting state and the order to sit.
 *
 * @phpstan-require-extends PathfinderMob
 * @phpstan-require-implements Pet
 */
trait PetTrait{

	private const TAG_SITTING = "Sitting"; //TAG_Byte

	private bool $sitting = false;

	protected function initEntity(CompoundTag $nbt) : void{
		$this->sitting = $nbt->getByte(self::TAG_SITTING, 0) !== 0;
	}

	public function saveNBT() : CompoundTag{
		return CompoundTag::create()->setByte(self::TAG_SITTING, $this->sitting ? 1 : 0);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
	}

	public function isSitting() : bool{
		return $this->sitting;
	}

	public function setSitting(bool $sitting) : void{
		if ($this->sitting !== $sitting) {
			$this->sitting = $sitting;
			$this->networkPropertiesDirty = true;
		}
	}

	/**
	 * Toggles sitting.
	 */
	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$this->setSitting(!$this->sitting);
		$this->navigation->stop();
		$this->setTargetEntity(null);

		return true;
	}
}
