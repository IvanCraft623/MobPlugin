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

namespace IvanCraft623\MobPlugin\entity\projectile;

use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\projectile\Trident;
use pocketmine\player\Player;

/**
 * A trident thrown by a mob: nobody can pick it up and it vanishes after a while.
 */
class DrownedTrident extends Trident implements NeverSavedWithChunkEntity{

	private const LIFETIME_TICKS = 1200;

	private int $stuckTicks = 0;

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if ($this->blockHit !== null){
			$this->stuckTicks += $tickDiff;
			if ($this->stuckTicks > self::LIFETIME_TICKS){
				$this->flagForDespawn();
			}
		}

		return parent::entityBaseTick($tickDiff);
	}

	public function onCollideWithPlayer(Player $player) : void{
	}

	public function canSaveWithChunk() : bool{
		return false;
	}
}
