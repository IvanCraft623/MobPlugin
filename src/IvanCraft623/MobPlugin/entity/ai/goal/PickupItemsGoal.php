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

use Closure;

use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\entity\object\ItemEntity;

use pocketmine\item\Item;
use function count;
use function is_int;
use function min;
use const PHP_INT_MAX;

class PickupItemsGoal extends Goal {

	/** @phpstan-var array<int, int|Closure(Item): int> */
	private array $wantedItems = [];

	protected ?ItemEntity $targetItem = null;
	protected int $wantedCount = 0;

	public function __construct(
		protected PathfinderMob $entity,
		protected float $speedMultiplier = 1.0,
		protected float $goalRadius = 2,
		protected float $maxDist = 8.0,
		protected ?float $searchHeight = null,
		protected ?int $cooldownAfterBeingAttacked = null,
		protected bool $trackTarget = false,
		protected bool $replaceItems = true
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function wantItem(int $priority, int $typeId, int $wantAmount = 1) : static {
		$this->wantedItems[$priority] = $wantAmount === 1
			? $typeId
			: static fn(Item $item) : int => $item->getTypeId() === $typeId ? $wantAmount : 0;
		return $this;
	}

	/**
	 * @phpstan-param Closure(Item): int $predicate
	 */
	public function wantIf(int $priority, Closure $predicate) : static {
		$this->wantedItems[$priority] = $predicate;
		return $this;
	}

	/**
	 * @phpstan-param array<int, int|Closure(Item): int> $template
	 */
	public function setWantedItems(array $template) : static {
		$this->wantedItems = $template;
		return $this;
	}

	public function canUse() : bool {
		if (count($this->wantedItems) === 0) {
			return false;
		}

		if ($this->cooldownAfterBeingAttacked !== null) {
			if (
				($lastDamageTick = $this->entity->getLastDamageByEntityTick()) !== -1 &&
				$this->entity->getWorld()->getServer()->getTick() - $lastDamageTick < $this->cooldownAfterBeingAttacked
			) {
				return false;
			}
		}

		$searchBox = $this->entity->getBoundingBox()->expandedCopy(
			$this->maxDist,
			$this->searchHeight ?? $this->maxDist,
			$this->maxDist
		);

		$nearbyEntities = $this->entity->getWorld()->getNearbyEntities($searchBox, $this->entity);

		$bestItem = null;
		$bestPriority = PHP_INT_MAX;
		$bestDistance = PHP_INT_MAX;
		$bestCount = 0;
		$priority = -1;
		$count = 0;

		$mobLocation = $this->entity->getLocation();
		foreach ($nearbyEntities as $entity) {
			if (!$entity instanceof ItemEntity) {
				continue;
			}

			if ($entity->getPickupDelay() !== 0) {
				continue;
			}

			$item = $entity->getItem();
			$this->evaluateItem($item, $priority, $count);

			if ($count <= 0) {
				continue;
			}

			$slot = 0;
			$equipInventory = $this->entity->getEquipInventoryAndSlot($item, $slot);
			$equippedItem = $equipInventory->getItem($slot);
			if (!$equippedItem->isNull()) {
				if (!$this->replaceItems) {
					continue;
				}

				$equipedItemPriority = PHP_INT_MAX;
				$unused = 0;
				$this->evaluateItem($equippedItem, $equipedItemPriority, $unused);
				if ($equipedItemPriority <= $priority) {
					continue;
				}
			}

			$distance = $mobLocation->distanceSquared($entity->getLocation());

			if ($priority < $bestPriority || ($priority === $bestPriority && $distance < $bestDistance)) {
				$bestItem = $entity;
				$bestPriority = $priority;
				$bestDistance = $distance;
				$bestCount = $count;
			}
		}

		if ($bestItem === null) {
			return false;
		}

		$this->targetItem = $bestItem;
		$this->wantedCount = $bestCount;
		return true;
	}

	public function canContinueToUse() : bool {
		if ($this->targetItem === null || $this->targetItem->isClosed()) {
			return false;
		}

		if (!$this->trackTarget && !$this->entity->getSensing()->canSee($this->targetItem)) {
			return false;
		}

		return $this->entity->getLocation()->distanceSquared($this->targetItem->getLocation()) <= $this->maxDist * $this->maxDist;
	}

	public function start() : void {
		if ($this->targetItem === null) {
			return;
		}

		$this->entity->getNavigation()->moveToEntity($this->targetItem, $this->speedMultiplier);
	}

	public function stop() : void {
		$this->targetItem = null;
		$this->wantedCount = 0;
		$this->entity->getNavigation()->stop();
	}

	public function tick() : void {
		if ($this->targetItem === null || $this->targetItem->isClosed()) {
			return;
		}

		$this->entity->getLookControl()->setLookAt($this->targetItem, 30, 30);

		if ($this->trackTarget) {
			$this->entity->getNavigation()->moveToEntity($this->targetItem, $this->speedMultiplier);
		}

		if ($this->entity->getLocation()->distanceSquared($this->targetItem->getLocation()) <= $this->goalRadius * $this->goalRadius) {
			$this->entity->pickupItem($this->targetItem, min($this->wantedCount, $this->targetItem->getItem()->getCount()));
			$this->targetItem = null;
		}
	}

	private function evaluateItem(Item $item, int &$priority, int &$count) : void {
		foreach ($this->wantedItems as $priority => $entry) {
			$count = is_int($entry)
				? ($item->getTypeId() === $entry ? 1 : 0)
				: $entry($item);
			if ($count > 0) {
				return;
			}
		}

		$priority = -1;
		$count = 0;
	}

	public function close() : void{
		$this->wantedItems = [];
		parent::close();
	}
}
