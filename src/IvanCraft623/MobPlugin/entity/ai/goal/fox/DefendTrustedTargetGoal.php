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

namespace IvanCraft623\MobPlugin\entity\ai\goal\fox;

use IvanCraft623\MobPlugin\entity\ai\goal\target\FightTargetGoal;
use IvanCraft623\MobPlugin\entity\animal\Fox;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\memory\Memories;
use IvanCraft623\MobPlugin\memory\Occurrence;

/**
 * Targets whoever fights a trusted player nearby, and makes the fox defend them.
 */
final class DefendTrustedTargetGoal extends FightTargetGoal{

	private const OCCURRENCES = [Occurrence::HURT_BY, Occurrence::HURT];

	/**
	 * @param float $radius blocks around the fox where it looks for trusted players
	 */
	public function __construct(private Fox $fox, private float $radius){
		parent::__construct($fox);
	}

	public function canUse() : bool{
		$memories = Memories::getInstance();
		foreach ($this->fox->getSensing()->getNearbyPlayers($this->radius) as $player) {
			if (!$this->fox->isTrusting($player)) {
				continue;
			}

			foreach (self::OCCURRENCES as $occurrence) {
				if ($this->answer($memories->latest($player, $occurrence, Mob::HURT_BY_MEMORY_TICKS))) {
					return true;
				}
			}
		}

		return false;
	}

	public function start() : void{
		$this->fox->setDefending(true);

		parent::start();
	}

	public function stop() : void{
		$this->fox->setDefending(false);

		parent::stop();
	}
}