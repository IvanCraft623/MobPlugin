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

use pocketmine\entity\Entity;
use pocketmine\entity\Living as PMLiving;
use pocketmine\event\entity\EntityDamageByEntityEvent;

interface NeutralMob {

	public function getRemainingAngerTime() : int;

	public function setRemainingAngerTime(int $ticks) : void;

	public function startAngerTimer() : void;

	public function stopBeingAngry() : void;

	public function getTargetEntity() : ?Entity;

	public function setTargetEntity(?Entity $target) : void;

	public function isAngryAt(Entity $entity) : bool;

	public function isAngry() : bool;

	public function getLastDamageByEntity() : ?EntityDamageByEntityEvent;

	public function canAttack(PMLiving $target) : bool;
}
