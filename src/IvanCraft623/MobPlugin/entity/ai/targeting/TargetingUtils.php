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

namespace IvanCraft623\MobPlugin\entity\ai\targeting;

use IvanCraft623\MobPlugin\entity\monster\Zombie;

use pocketmine\block\MobHead;
use pocketmine\block\utils\MobHeadType;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use function count;

class TargetingUtils {

	public static function getVisibilityPercent(Living $entity, ?Entity $target = null) : float {
		$visibilityPercent = 1.0;
		if ($entity->isSneaking()) {
			$visibilityPercent *= 0.8;
		}
		if ($entity->isInvisible()) {
			$percent = self::getArmorCoverPercentage($entity);
			if ($percent < 0.1) {
				$percent = 0.1;
			}
			$visibilityPercent *= 0.7 * $percent;
		}
		if ($target !== null) {
			$head = $entity->getArmorInventory()->getHelmet();
			$headBlock = $head->getBlock();
			if ($headBlock instanceof MobHead) {
				$headType = $headBlock->getMobHeadType();
				if (
					//($target instanceof Skeleton && $headType->equals(MobHeadType::SKELETON())) ||
					($target instanceof Zombie && $headType->equals(MobHeadType::ZOMBIE())) //||
					//($target instanceof Creeper && $headType->equals(MobHeadType::CREEPER()))
				) {
					$visibilityPercent *= 0.5;
				}
			}
		}
		return $visibilityPercent;
	}

	public static function getArmorCoverPercentage(Living $entity) : float {
		$inventory = $entity->getArmorInventory();
		$size = $inventory->getSize();
		return ($size > 0) ? (count($inventory->getContents()) / $size) : 0.0;
	}
}
