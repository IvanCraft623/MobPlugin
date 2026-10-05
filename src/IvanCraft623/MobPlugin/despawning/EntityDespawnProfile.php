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

namespace IvanCraft623\MobPlugin\despawning;

use pocketmine\Server;

/**
 * The despawn state of one entity: whether it is persistent, and for how long it has lived
 * and been inactive. Only entities with a despawn rule have one.
 *
 * @see DespawnRuleRegistry::getProfile()
 */
final class EntityDespawnProfile{

	private bool $persistent = false;

	private int $spawnTick;

	private int $lastActionTick;

	public function __construct(){
		$this->spawnTick = $this->lastActionTick = Server::getInstance()->getTick();
	}

	/**
	 * Returns whether the entity is kept from despawning for good. Saved with the entity.
	 */
	public function isPersistent() : bool{
		return $this->persistent;
	}

	public function setPersistent(bool $value = true) : void{
		$this->persistent = $value;
	}

	/**
	 * In ticks. Saved with the entity.
	 */
	public function getLifetime() : int{
		return Server::getInstance()->getTick() - $this->spawnTick;
	}

	public function setLifetime(int $ticks) : void{
		$this->spawnTick = Server::getInstance()->getTick() - $ticks;
	}

	/**
	 * In ticks.
	 */
	public function getInactivityTime() : int{
		return Server::getInstance()->getTick() - $this->lastActionTick;
	}

	public function addInactivityTime(int $ticks) : void{
		$this->lastActionTick -= $ticks;
	}

	/**
	 * Resets the inactivity time.
	 */
	public function markActive() : void{
		$this->lastActionTick = Server::getInstance()->getTick();
	}
}
