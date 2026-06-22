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

namespace IvanCraft623\MobPlugin\entity\monster\skeleton;

use Closure;

use IvanCraft623\MobPlugin\entity\monster\Creeper;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\MobHead;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

use function mt_rand;

class Skeleton extends AbstractSkeleton {

	public static function getNetworkTypeId() : string {
		return EntityIds::SKELETON;
	}

	public function getName() : string {
		return "Skeleton";
	}

	public function isSunSensitive() : bool{
		return true;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo {
		return new EntitySizeInfo(1.9, 0.6, 1.71);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		if ($nbt->count() === 0) {
			//TODO gear equipment
			$this->inventory->setItemInHand(VanillaItems::BOW());
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool {
		//TODO: conversion to stray with powder snow
		return parent::entityBaseTick($tickDiff);
	}

	public function getDrops() : array{
		$drops = parent::getDrops();

		//TODO: looting enchantment logic
		$drops[] = VanillaItems::ARROW()->setCount(mt_rand(0, 2));
		$drops[] = VanillaItems::BONE()->setCount(mt_rand(0, 2));

		if (($cause = $this->getLastDamageCause()) instanceof EntityDamageByEntityEvent &&
			($killer = $cause->getDamager()) instanceof Creeper && $killer->isPowered()
		) {
			$drops[] = VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::SKELETON)->asItem();
		}

		return $drops;
	}

	/**
	 * @phpstan-return array<int, int|Closure(Item): int>
	 */
	public static function getWantedItems() : array {
		$items = [];

		$items[] = ItemTypeIds::NETHERITE_SWORD;
		$items[] = ItemTypeIds::NETHERITE_HELMET;
		$items[] = ItemTypeIds::NETHERITE_CHESTPLATE;
		$items[] = ItemTypeIds::NETHERITE_LEGGINGS;
		$items[] = ItemTypeIds::NETHERITE_BOOTS;

		$items[] = ItemTypeIds::DIAMOND_SWORD;
		$items[] = ItemTypeIds::DIAMOND_HELMET;
		$items[] = ItemTypeIds::DIAMOND_CHESTPLATE;
		$items[] = ItemTypeIds::DIAMOND_LEGGINGS;
		$items[] = ItemTypeIds::DIAMOND_BOOTS;

		$items[] = ItemTypeIds::IRON_SWORD;
		$items[] = ItemTypeIds::IRON_HELMET;
		$items[] = ItemTypeIds::IRON_CHESTPLATE;
		$items[] = ItemTypeIds::IRON_LEGGINGS;
		$items[] = ItemTypeIds::IRON_BOOTS;

		$items[] = ItemTypeIds::COPPER_SWORD;

		$items[] = ItemTypeIds::CHAINMAIL_HELMET;
		$items[] = ItemTypeIds::CHAINMAIL_CHESTPLATE;
		$items[] = ItemTypeIds::CHAINMAIL_LEGGINGS;
		$items[] = ItemTypeIds::CHAINMAIL_BOOTS;

		$items[] = ItemTypeIds::GOLDEN_SWORD;
		$items[] = ItemTypeIds::GOLDEN_HELMET;
		$items[] = ItemTypeIds::GOLDEN_CHESTPLATE;
		$items[] = ItemTypeIds::GOLDEN_LEGGINGS;
		$items[] = ItemTypeIds::GOLDEN_BOOTS;

		$items[] = ItemTypeIds::COPPER_HELMET;
		$items[] = ItemTypeIds::COPPER_CHESTPLATE;
		$items[] = ItemTypeIds::COPPER_LEGGINGS;
		$items[] = ItemTypeIds::COPPER_BOOTS;

		$items[] = ItemTypeIds::STONE_SWORD;

		$items[] = ItemTypeIds::WOODEN_SWORD;
		$items[] = ItemTypeIds::LEATHER_CAP;
		$items[] = ItemTypeIds::LEATHER_TUNIC;
		$items[] = ItemTypeIds::LEATHER_PANTS;
		$items[] = ItemTypeIds::LEATHER_BOOTS;

		$items[] = ItemTypeIds::BOW;

		$items[] = ItemTypeIds::TURTLE_HELMET;

		// skeleton and wither skeleton skulls
		$items[] = static function(Item $i) : int {
			if ($i instanceof ItemBlock && ($b = $i->getBlock()) instanceof MobHead) {
				$type = $b->getMobHeadType();
				return ($type === MobHeadType::SKELETON || $type === MobHeadType::WITHER_SKELETON) ? 1 : 0;
			}
			return 0;
		};

		$items[] = ItemTypeIds::fromBlockTypeId(BlockTypeIds::CARVED_PUMPKIN);

		$items[] = static fn (Item $i) => $i->getCount(); // pickup all other items

		return $items;
	}
}
