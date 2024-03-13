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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\Mob;

use pocketmine\player\Player;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\block\Liquid;

class RandomTeleportGoal extends Goal {

	public function getDefaultRandomTeleportRange() : Vector3{
		return new Vector3(32, 16, 32);
	}

	public function getDefaultTargetTeleportRange() : Vector3{
		return new Vector3(16, 16, 16);
	}

	public const DEFAULT_MIN_TELEPORT_INTERVAL = 0;
	public const DEFAULT_MAX_TELEPORT_INTERVAL = 400;

	public const DEFAULT_TARGET_TELEPORT_CHANCE = 0.05; //Chance that the entity will teleport if the entity is chasing a target
	public const DEFAULT_LIGHT_TELEPORT_CHANCE = 0.01; //Chance that the entity will teleport if the entity is in daylight
	public const DEFAULT_DARK_TELEPORT_CHANCE = 0.01; //Chance that the entity will teleport if the entity is in darkness

	protected Vector3 $randomTeleportRange;
	protected Vector3 $targetTeleportRange;

	protected int $teleportTick;

	protected Vector3 $teleportPos;

	public function __construct(
		protected Mob $entity,
		?Vector3 $randomTeleportRange = null,
		?Vector3 $targetTeleportRange = null,
		protected int $minTeleportInterval = self::DEFAULT_MIN_TELEPORT_INTERVAL,
		protected int $maxTeleportInterval = self::DEFAULT_MAX_TELEPORT_INTERVAL,
		protected float $targetTeleportChance = self::DEFAULT_TARGET_TELEPORT_CHANCE,
		protected float $lightTeleportChance = self::DEFAULT_LIGHT_TELEPORT_CHANCE,
		protected float $darkTeleportChance = self::DEFAULT_DARK_TELEPORT_CHANCE
	) {
		$this->randomTeleportRange = $randomTeleportRange ?? self::getDefaultRandomTeleportRange();
		$this->targetTeleportRange = $targetTeleportRange ?? self::getDefaultTargetTeleportRange();

		$this->resetTeleportTick();
	}

	public function getTeleportTick() : int{
		return $this->teleportTick;
	}

	public function setTeleportTick(int $ticks) : void{
		$this->teleportTick = $ticks;
	}

	public function resetTeleportTick() : void{
		$this->teleportTick = $this->entity->getServer()->getTick() + mt_rand($this->minTeleportInterval, $this->maxTeleportInterval);
	}

	public function canUse() : bool{
		//TODO: not sure how chance stuff works in vanilla :P

		if (!$this->entity->isAlive()) {
			return false;
		}

		if ($this->teleportTick - $this->entity->getServer()->getTick() <= 0){
			if (($target = $this->entity->getTargetEntity()) !== null) {
				$pos = $this->getRandomTeleportPosition($target->getPosition(), $this->targetTeleportRange);
			} else {
				$pos = $this->getRandomTeleportPosition($this->entity->getPosition(), $this->randomTeleportRange);
			}

			if ($pos !== null) {
				$this->teleportPos = $pos;
				return true;
			}
		}

		return false;
	}

	public function getRandomTeleportPosition(Position $origin, Vector3 $teleportRange) : ?Vector3{
		$world = $origin->getWorld();

		$rangeX = $teleportRange->x / 2;
		$rangeY = $teleportRange->y / 2;
		$rangeZ = $teleportRange->z / 2;

		$minX = (int) ($origin->getFloorX() - $rangeX);
		$minY = (int) (min($origin->getFloorY(), $world->getMaxY()) - $rangeY);
		$minZ = (int) ($origin->getFloorZ() - $rangeZ);

		$maxX = (int) ($origin->getFloorX() + $rangeX);
		$maxY = (int) ($origin->getFloorY() + $rangeY);
		$maxZ = (int) ($origin->getFloorZ() + $rangeZ);

		$worldMinY = $world->getMinY();

		for($attempts = 0; $attempts < 4; ++$attempts){
			$x = mt_rand($minX, $maxX);
			$y = mt_rand($minY, $maxY);
			$z = mt_rand($minZ, $maxZ);

			while($y >= $worldMinY && !$world->getBlockAt($x, $y, $z)->isSolid()){
				$y--;
			}
			if($y < $worldMinY){
				continue;
			}

			//TODO: Use $entity->canStandAt() instead of this, but is somehow broken
			for ($extraY = 1; $extraY <= 3; $extraY++) { 
				if (($block = $world->getBlockAt($x, $y + $extraY, $z))->isSolid() || $block instanceof Liquid) {
					continue 2;
				}
			}

			return new Vector3($x + 0.5, $y + 1, $z + 0.5);
		}

		return null;
	}

	public function canContinueToUse() : bool{
		return false;
	}

	public function start() : void{
		$oldPosition = $this->entity->getLocation();

		if ($this->entity->teleport($this->teleportPos)) {
			$this->entity->onRandomTeleport($oldPosition, $this->teleportPos);
		}
	}

	public function stop() : void{
		$this->resetTeleportTick();
		unset($this->teleportPos);
	}
}
