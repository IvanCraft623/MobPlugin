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

use IvanCraft623\MobPlugin\loot\LootTable;
use IvanCraft623\MobPlugin\MobPlugin;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;

/**
 * @phpstan-require-extends Living
 */
trait LootRollingTrait{

	/**
	 * Rolls a table for this mob's death, credited to whoever killed it.
	 *
	 * @return Item[]
	 */
	protected function generateDeathLoot(LootTable $table) : array{
		$cause = $this->getLastDamageCause();

		return $this->generateLoot($table, $cause instanceof EntityDamageByEntityEvent ? $cause->getDamager() : null);
	}

	/**
	 * Rolls a table for a drop that $source caused with $tool (its held item if null).
	 *
	 * @return Item[]
	 */
	protected function generateLoot(LootTable $table, ?Entity $source = null, ?Item $tool = null) : array{
		return $table->generateFor($this, MobPlugin::getInstance()->getRandom(), $source, $tool);
	}

	/**
	 * Drops what a table rolls for a drop that $source caused with $tool, above this mob's head.
	 */
	protected function dropLoot(LootTable $table, ?Entity $source, ?Item $tool) : void{
		$position = $this->location->add(0, $this->getSize()->getHeight(), 0);
		foreach ($this->generateLoot($table, $source, $tool) as $item) {
			$this->getWorld()->dropItem($position, $item);
		}
	}
}