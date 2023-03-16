<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\sensing;

use pocketmine\entity\Entity;

use IvanCraft623\MobPlugin\entity\Mob;

class Sensing {

	private Mob $mob;

	private array $seen = [];

	private array $unseen = [];

	public function __construct(Mob $mob) {
		$this->mob = $mob;
	}

	public function tick(): void {
		$this->seen = [];
		$this->unseen = [];
	}

	public function canSee(Entity $entity): bool {
		if (isset($this->seen[$entity->getId()])) {
			return true;
		}
		if (isset($this->unseen[$entity->getId()])) {
			return false;
		}
		$canSee = $this->mob->canSee($entity);
		if ($canSee) {
			$this->seen[$entity->getId()] = $entity;
		} else {
			$this->unseen[$entity->getId()] = $entity;
		}
		return $canSee;
	}
}