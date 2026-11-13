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

namespace IvanCraft623\MobPlugin\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\world\sound\Sound;

class WolfArmorSound implements Sound{

	public function __construct(private WolfArmorSoundType $type){}

	public function encode(Vector3 $pos) : array{
		$sound = match($this->type){
			WolfArmorSoundType::EQUIP => LevelSoundEvent::ARMOR_EQUIP_WOLF,
			WolfArmorSoundType::UNEQUIP => LevelSoundEvent::ARMOR_UNEQUIP_WOLF,
			WolfArmorSoundType::REPAIR => LevelSoundEvent::ARMOR_REPAIR_WOLF,
			//Not one of this protocol version's LevelSoundEvents, though the client has the sound
			WolfArmorSoundType::DAMAGE => "armor.damage_wolf",
			WolfArmorSoundType::CRACK => LevelSoundEvent::ARMOR_CRACK_WOLF,
			WolfArmorSoundType::BREAK => LevelSoundEvent::ARMOR_BREAK_WOLF,
		};

		return [LevelSoundEventPacket::nonActorSound($sound, $pos, false)];
	}
}