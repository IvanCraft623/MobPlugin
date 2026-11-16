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

use IvanCraft623\MobPlugin\entity\animal\GoatVariant;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\world\sound\Sound;

/**
 * A goat lowering its head before a charge.
 */
class GoatPreRamSound implements Sound{

	public function __construct(private GoatVariant $variant){
	}

	public function encode(Vector3 $pos) : array{
		return [LevelSoundEventPacket::nonActorSound(
			match($this->variant){
				GoatVariant::NORMAL => LevelSoundEvent::PRE_RAM,
				GoatVariant::SCREAMER => LevelSoundEvent::PRE_RAM_SCREAMER
			},
			$pos,
			false
		)];
	}
}