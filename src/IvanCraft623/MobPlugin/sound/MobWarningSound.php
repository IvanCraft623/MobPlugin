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

use pocketmine\entity\Ageable;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\world\sound\Sound;

class MobWarningSound implements Sound{

	public function __construct(private Entity $entity){}

	public function encode(Vector3 $pos) : array{
		$isBaby = $this->entity instanceof Ageable && $this->entity->isBaby();
		return [LevelSoundEventPacket::create(
			$isBaby ? LevelSoundEvent::MOB_WARNING_BABY : LevelSoundEvent::MOB_WARNING,
			$pos,
			-1,
			$this->entity::getNetworkTypeId(),
			$isBaby,
			false,
			$this->entity->getId()
		)];
	}
}
