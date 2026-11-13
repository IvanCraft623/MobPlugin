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

namespace IvanCraft623\MobPlugin\entity\ai\goal\tame;

use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\entity\Tameable;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function abs;

/**
 * What {@see FollowOwnerGoal} and {@see TeleportToOwnerGoal} share to bring a tamed mob to its owner.
 */
final class OwnerTeleport{

	private const ATTEMPTS = 10; //tries to find a spot
	private const MIN_OFFSET = 2; //blocks from the owner, on at least one horizontal axis
	private const MAX_OFFSET = 3; //blocks from the owner

	private function __construct(){
		//NOOP
	}

	/**
	 * The owner of the mob, if it can teleport to them: they are in its world and not spectating.
	 */
	public static function getOwner(PathfinderMob&Tameable $mob) : ?Entity{
		$owner = $mob->getOwningEntity();
		if ($owner === null || ($owner instanceof Player && $owner->isSpectator()) || $owner->getWorld() !== $mob->getWorld()) {
			return null;
		}

		return $owner;
	}

	/**
	 * A free spot a few blocks from the owner, if one is found.
	 */
	public static function findDestination(PathfinderMob $mob, Entity $owner) : ?Vector3{
		$random = $mob->getRandom();
		$ownerPos = $owner->getPosition();
		for ($i = 0; $i < self::ATTEMPTS; ++$i) {
			$dx = $random->nextRange(-self::MAX_OFFSET, self::MAX_OFFSET);
			$dz = $random->nextRange(-self::MAX_OFFSET, self::MAX_OFFSET);
			if (abs($dx) < self::MIN_OFFSET && abs($dz) < self::MIN_OFFSET) {
				continue;
			}

			$position = new Vector3(
				$ownerPos->getFloorX() + $dx + 0.5,
				$ownerPos->getFloorY() + $random->nextRange(-1, 1),
				$ownerPos->getFloorZ() + $dz + 0.5
			);
			if ($mob->canStandAt($position)) {
				return $position;
			}
		}

		return null;
	}

	public static function teleport(PathfinderMob $mob, Vector3 $destination) : void{
		$mob->teleport($destination);
		$mob->getNavigation()->stop();
	}
}