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
use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\item\Dye;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;

/**
 * Collar color and dyeing.
 *
 * @phpstan-require-extends PathfinderMob
 * @phpstan-require-implements Collared
 */
trait CollaredTrait{

	private const TAG_COLLAR_COLOR = "Color"; //TAG_Byte

	private DyeColor $collarColor;

	protected function initEntity(CompoundTag $nbt) : void{
		$this->collarColor = DyeColorIdMap::getInstance()->fromId($nbt->getByte(self::TAG_COLLAR_COLOR, -1)) ?? DyeColor::RED();
	}

	public function saveNBT() : CompoundTag{
		return CompoundTag::create()->setByte(self::TAG_COLLAR_COLOR, DyeColorIdMap::getInstance()->toId($this->collarColor));
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		$properties->setByte(EntityMetadataProperties::COLOR, DyeColorIdMap::getInstance()->toId($this->collarColor));
	}

	public function getCollarColor() : DyeColor{
		return $this->collarColor;
	}

	public function setCollarColor(DyeColor $color) : void{
		$this->collarColor = $color;
		$this->networkPropertiesDirty = true;
	}

	/**
	 * Dyes the collar with the dye in hand.
	 */
	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if (!$item instanceof Dye || $item->getColor()->equals($this->collarColor)) {
			return false;
		}

		$this->setCollarColor($item->getColor());
		Utils::popItemInHand($player);

		return true;
	}
}
