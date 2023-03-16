<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\targeting;

use pocketmine\entity\Entity;
use pocketmine\entity\Living as PMLiving;
use pocketmine\player\Player;

use IvanCraft623\MobPlugin\entity\Living;
use IvanCraft623\MobPlugin\entity\Mob;

class TargetingConditions {

	public float $range = -1;

	public bool $allowInvulnerable;

	public bool $allowUnseeable;

	public bool $allowNonAttackable;

	public bool $testInvisible = true;

	public function __construct(float $range, bool $allowInvulnerable, bool $allowUnseeable, bool $allowNonAttackable, bool $testInvisible) {
		$result->range = $range;
		$result->allowInvulnerable = $allowInvulnerable;
		$result->allowUnseeable = $allowUnseeable;
		$result->allowNonAttackable = $allowNonAttackable;
		$result->testInvisible = $testInvisible;
	}

	public function test(?PMLiving $entity, PMLiving $target): bool {
		if ($entity === $target) {
			return false;
		}
		if ($target instanceof Player && $target->isSpectator()) {
			return false;
		}
		if (!$target->isAlive()) {
			return false;
		}
		if (!$this->allowInvulnerable && ($target instanceof Player && $target->isCreative())) {
			return false;
		}
		if ($entity !== null) {
			if (!$this->allowNonAttackable) {
				if ($entity instanceof Living && !$entity->canAttack($target)) {
					return false;
				}
			}
			if ($this->range > 0) {
				$percent = $this->testInvisible ? TargetingUtils::getInstance()->getVisibilityPercent($target, $entity) : 1.0;
				$visibility = max($this->range * $percent, 2.0);
				$distanceSquare = $entity->getLocation()->distanceSquared($target->getLocation());
				if ($distanceSquare > $visibility * $visibility) {
					return false;
				}
			}
			if (!$this->allowUnseeable && $entity instanceof Mob && !$entity->getSensing()->canSee($target)) {
				return false;
			}
		}
		return true;
	}
}