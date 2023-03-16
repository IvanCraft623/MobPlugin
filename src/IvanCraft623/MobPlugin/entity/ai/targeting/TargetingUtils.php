<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\targeting;


use IvanCraft623\MobPlugin\entity\Zombie;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\block\utils\SkullType;
use pocketmine\item\Skull;

class TargetingUtils {
	use SingletonTrait;

	public function getVisibilityPercent(Living $entity, ?Entity $target = null): float {
		$visibilityPercent = 1.0;
		if ($entity->isSneaking()) {
			$visibilityPercent *= 0.8;
		}
		if ($entity->isInvisible()) {
			$percent = $this->getArmorCoverPercentage($entity);
			if ($percent < 0.1) {
				$percent = 0.1
			}
			$visibilityPercent *= 0.7 * $percent;
		}
		if ($target !== null) {
			$head = $entity->getArmorInventory()->getHelmet();
			if ($head instanceof Skull) {
				$skullType = $head->getSkullType();
				if (
					//($target instanceof Skeleton && $skullType->equals(SkullType::SKELETON())) ||
					($target instanceof Zombie && $skullType->equals(SkullType::ZOMBIE())) //||
					//($target instanceof Creeper && $skullType->equals(SkullType::CREEPER()))
				) {
					$visibilityPercent *= 0.5;
				}
			}
		}
		return $visibilityPercent;
	}

	public function getArmorCoverPercentage(Living $entity): float {
		$inventory = $entity->getArmorInventory();
		$size = $inventory->getSize();
		return ($size > 0) ? (count($inventory->getContents()) / $size) : 0.0;
	}
}