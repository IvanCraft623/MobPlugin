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

namespace IvanCraft623\MobPlugin\entity\ai\behavior;

use IvanCraft623\MobPlugin\entity\ai\memory\MemoryModuleType;
use IvanCraft623\MobPlugin\entity\Living;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\Entity;
use pocketmine\entity\Living as PMLiving;
use pocketmine\item\Item;
use pocketmine\item\Releasable;
use pocketmine\world\Position;

class BehaviorUtils {

	public static function lockGazeAndWalkToEachOther(Living $entity1, Living $entity2, float $speedModifier) : void {
		self::lookAtEachOther($entity1, $entity2);
		self::setWalkAndLookTargetMemoriesToEachOther($entity1, $entity2, $speedModifier);
	}

	private static function lookAtEachOther(Living $entity1, Living $entity2) : void {
		self::lookAtEntity($entity1, $entity2);
		self::lookAtEntity($entity2, $entity1);
	}

	public static function lookAtEntity(Living $entity, PMLiving $target) : void {
		$entity->getBrain()->setMemory(MemoryModuleType::LOOK_TARGET(), new EntityTracker($target, true));
	}

	private static function setWalkAndLookTargetMemoriesToEachOther(Living $entity1, Living $entity2, float $speedModifier) : void {
		$closeEnoughDist = 2;
		self::setWalkToEntityAndLookTargetMemories($entity1, $entity2, $speedModifier, $closeEnoughDist);
		self::setWalkToEntityAndLookTargetMemories($entity2, $entity1, $speedModifier, $closeEnoughDist);
	}

	public static function setWalkToEntityAndLookTargetMemories(Living $entity, Entity $target, float $speedModifier, int $closeEnoughDist) : void {
		$walkTarget = new WalkTarget(new EntityTracker($target, false), $speedModifier, $closeEnoughDist);
		$entity->getBrain()->setMemory(MemoryModuleType::LOOK_TARGET(), new EntityTracker($target, true));
		$entity->getBrain()->setMemory(MemoryModuleType::WALK_TARGET(), $walkTarget);
	}

	public static function setWalkToPositionAndLookTargetMemories(Living $entity, Position $target, float $speedModifier, int $closeEnoughDist) : void {
		$walkTarget = new WalkTarget(new BlockPosTracker($target), $speedModifier, $closeEnoughDist);
		$entity->getBrain()->setMemory(MemoryModuleType::LOOK_TARGET(), new BlockPosTracker($target));
		$entity->getBrain()->setMemory(MemoryModuleType::WALK_TARGET(), $walkTarget);
	}

	public static function dropItem(Living $entity, Item $item) : void {
		$entity->getWorld()->dropItem($entity->getLocation()->add(0, $entity->getEyeHeight() - 0.3, 0), $item, $entity->getDirectionVector()->multiply(0.4), 40);
	}

	public static function isWithinAttackRange(Mob $mob, PMLiving $target, int $range) : bool {
		$item = $mob->getInventory()->getItemInHand();
		if ($item instanceof Releasable && $mob->canUseReleasable($item)) {
			return $mob->getLocation()->distanceSquared($target->getLocation()) < (Utils::getDefaultProjectileRange() - $range) ** 2;
		}
		return self::isWithinMeleeAttackRange($mob, $target);
	}

	public static function isWithinMeleeAttackRange(Mob $mob, PMLiving $target, ?float $range = null) : bool {
		if ($range === null) {
			$distanceSquared = $mob->getLocation()->distanceSquared($target->getLocation());
			return (($mob->getSize()->getWidth() * 2) ** 2) + $target->getSize()->getWidth() <= $distanceSquared;
		} else {
			$value = $entity->getBrain()->getMemory(MemoryModuleType::ATTACK_TARGET());
			if ($value === null) {
				return false;
			}
			$distanceTarget = $entity->getLocation()->distanceSquared($value->getValue()->getLocation());
			$distancePTarget = $entity->getLocation()->distanceSquared($target->getLocation());
			return $distancePTarget > $distanceTarget + $range * $range;
		}
	}

	public static function canSee(Living $entity, PMLiving $target) : bool {
		$brain = $entity->getBrain();
		if ($brain->hasMemoryValue(MemoryModuleType::VISIBLE_LIVING_ENTITIES())) {
			$value = $brain->getMemory(MemoryModuleType::VISIBLE_LIVING_ENTITIES());
			if ($value !== null) {
				$entities = $value->getValue();
				return isset($entities[$target->getId()]);
			}
		}
		return false;
	}
}
