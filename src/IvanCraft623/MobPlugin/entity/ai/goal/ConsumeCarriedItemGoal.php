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

use IvanCraft623\MobPlugin\entity\ItemCarrier;
use IvanCraft623\MobPlugin\entity\PathfinderMob;
use pocketmine\entity\animation\ConsumingItemAnimation;
use pocketmine\item\ConsumableItem;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

/**
 * Consumes the item in its hand while it goes about its business, with the effects it would have on anyone else.
 */
class ConsumeCarriedItemGoal extends Goal{

	/** What it does with the leftover of what it consumed, a bowl for example: lets it fall at its feet. */
	public const RESIDUE_DROP = 0;
	/** What it does with the leftover: gets rid of it. */
	public const RESIDUE_DESTROY = 1;
	/** What it does with the leftover: takes it in its hand, or drops it if the hand is not free. */
	public const RESIDUE_HOLD = 2;

	private ?int $holdingSince = null; //server tick at which it started holding the item

	private int $consumingTicks = 0;

	/**
	 * @param class-string<ConsumableItem> $consumableType       only items of this type are consumed
	 * @param int                          $delayBeforeConsuming ticks the item is held before consuming it
	 * @param int                          $consumeDuration      ticks it takes to consume it
	 * @param int                          $residueAction        one of the RESIDUE_ constants
	 */
	public function __construct(
		protected PathfinderMob&ItemCarrier $entity,
		private string $consumableType = ConsumableItem::class,
		private int $delayBeforeConsuming = 0,
		private int $consumeDuration = 32,
		private int $residueAction = self::RESIDUE_DROP
	){
	}

	public function canUse() : bool{
		if (!$this->isHoldingConsumable()) {
			$this->holdingSince = null;
			return false;
		}

		$tick = $this->entity->getServer()->getTick();
		$this->holdingSince ??= $tick;

		return $tick - $this->holdingSince >= $this->delayBeforeConsuming;
	}

	public function canContinueToUse() : bool{
		return $this->isHoldingConsumable();
	}

	public function start() : void{
		$this->consumingTicks = 0;
	}

	public function stop() : void{
		$this->consumingTicks = 0;
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$item = $this->entity->getInventory()->getItemInHand();
		if ($this->consumingTicks % 4 === 0) { //ticks between the chewing animations
			$this->entity->broadcastAnimation(new ConsumingItemAnimation($this->entity, $item));
		}

		if (++$this->consumingTicks >= $this->consumeDuration) {
			$this->consume($item);
		}
	}

	private function isHoldingConsumable() : bool{
		return $this->entity->getInventory()->getItemInHand() instanceof $this->consumableType;
	}

	private function consume(Item $item) : void{
		if (!$item instanceof ConsumableItem) {
			return;
		}

		$residue = $item->getResidue();
		$inventory = $this->entity->getInventory();

		$this->entity->consumeObject($item);
		$item->pop();
		$inventory->setItemInHand($item->isNull() ? VanillaItems::AIR() : $item);

		if ($residue->isNull() || $this->residueAction === self::RESIDUE_DESTROY) {
			return;
		}
		if ($this->residueAction === self::RESIDUE_HOLD && $inventory->getItemInHand()->isNull()) {
			$inventory->setItemInHand($residue);
		} else {
			$this->entity->getWorld()->dropItem($this->entity->getPosition(), $residue);
		}
	}
}