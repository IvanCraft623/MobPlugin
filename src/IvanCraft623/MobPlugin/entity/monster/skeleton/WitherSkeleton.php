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

use IvanCraft623\MobPlugin\entity\boss\Wither;
use IvanCraft623\MobPlugin\entity\monster\Creeper;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\Pathfinder\BlockPathType;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\MobHead;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;

use function mt_rand;

class WitherSkeleton extends AbstractSkeleton {

	public const WITHER_EFFECT_DURATION = 200; // in ticks

	public static function getNetworkTypeId() : string {
		return EntityIds::WITHER_SKELETON;
	}

	public function getName() : string {
		return "Wither Skeleton";
	}

	public function isFireProof() : bool{
		return true;
	}

	protected function registerGoals() : void{
		parent::registerGoals();

		$this->hurtByTargetGoal->getTargetingConditions()->setValidator(static function(Living $e) : bool{
			return !$e instanceof Wither;
		});
		//TODO: NearestAttackableGoal to piglins
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		if ($nbt->count() === 0) {
			//TODO gear equipment
			$this->inventory->setItemInHand(VanillaItems::STONE_SWORD());
		}
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setScale(1.2); //why? bedrock...

		$this->setPathfindingMalus(BlockPathType::LAVA, 8);
		$this->setPathfindingMalus(BlockPathType::DANGER_FIRE, BlockPathType::OPEN_MALUS);
		$this->setPathfindingMalus(BlockPathType::DAMAGE_FIRE, BlockPathType::OPEN_MALUS);
	}

	public function getDrops() : array{
		$drops = parent::getDrops();

		//TODO: looting enchantment logic
		$drops[] = VanillaItems::COAL()->setCount(mt_rand(0, 1));
		$drops[] = VanillaItems::BONE()->setCount(mt_rand(0, 2));

		if (($cause = $this->getLastDamageCause()) instanceof EntityDamageByEntityEvent &&
			(
				(($killer = $cause->getDamager()) instanceof Creeper && $killer->isPowered()) ||
				($killer instanceof Player && mt_rand(1, 40) === 1) //TODO: looting enchantment logic
			)
		) {
			$drops[] = VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::WITHER_SKELETON)->asItem();
		}

		return $drops;
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::WITHER_SKELETON_SPAWN_EGG();
	}

	public function attackEntity(Entity $entity) : bool{
		if (parent::attackEntity($entity)) {
			if ($entity instanceof Living) {
				$entity->getEffects()->add(new EffectInstance(VanillaEffects::WITHER(), self::WITHER_EFFECT_DURATION));
			}
			return true;
		}

		return false;
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

		$items[] = ItemTypeIds::GOLDEN_SWORD; //bruh...

		$items[] = ItemTypeIds::CHAINMAIL_HELMET;
		$items[] = ItemTypeIds::CHAINMAIL_CHESTPLATE;
		$items[] = ItemTypeIds::CHAINMAIL_LEGGINGS;
		$items[] = ItemTypeIds::CHAINMAIL_BOOTS;

		$items[] = ItemTypeIds::COPPER_SWORD;

		$items[] = ItemTypeIds::GOLDEN_HELMET;
		$items[] = ItemTypeIds::GOLDEN_CHESTPLATE;
		$items[] = ItemTypeIds::GOLDEN_LEGGINGS;
		$items[] = ItemTypeIds::GOLDEN_BOOTS;

		$items[] = ItemTypeIds::STONE_SWORD;

		$items[] = ItemTypeIds::COPPER_HELMET;
		$items[] = ItemTypeIds::COPPER_CHESTPLATE;
		$items[] = ItemTypeIds::COPPER_LEGGINGS;
		$items[] = ItemTypeIds::COPPER_BOOTS;

		$items[] = ItemTypeIds::WOODEN_SWORD;

		$items[] = ItemTypeIds::LEATHER_CAP;
		$items[] = ItemTypeIds::LEATHER_TUNIC;
		$items[] = ItemTypeIds::LEATHER_PANTS;
		$items[] = ItemTypeIds::LEATHER_BOOTS;

		$items[] = ItemTypeIds::TURTLE_HELMET;

		// Sorted by attack damage, then by durability (highest to lowest),
		// all these have the same priority in vanilla.
		// 8 attack damage
		$items[] = ItemTypeIds::NETHERITE_AXE;

		// 7 attack damage
		$items[] = ItemTypeIds::NETHERITE_PICKAXE;
		$items[] = ItemTypeIds::NETHERITE_HOE;
		$items[] = ItemTypeIds::DIAMOND_AXE;

		// 6 attack damage
		$items[] = ItemTypeIds::NETHERITE_SHOVEL;
		$items[] = ItemTypeIds::DIAMOND_PICKAXE;
		$items[] = ItemTypeIds::DIAMOND_HOE;
		$items[] = ItemTypeIds::IRON_AXE;

		// 5 attack damage
		$items[] = ItemTypeIds::DIAMOND_SHOVEL;
		$items[] = ItemTypeIds::IRON_PICKAXE;
		$items[] = ItemTypeIds::IRON_HOE;
		$items[] = ItemTypeIds::COPPER_AXE;
		$items[] = ItemTypeIds::STONE_AXE;

		// 4 attack damage
		$items[] = ItemTypeIds::IRON_SHOVEL;
		$items[] = ItemTypeIds::COPPER_PICKAXE;
		$items[] = ItemTypeIds::COPPER_HOE;
		$items[] = ItemTypeIds::STONE_PICKAXE;
		$items[] = ItemTypeIds::STONE_HOE;
		$items[] = ItemTypeIds::WOODEN_AXE;
		$items[] = ItemTypeIds::GOLDEN_AXE;

		// 3 attack damage
		$items[] = ItemTypeIds::COPPER_SHOVEL;
		$items[] = ItemTypeIds::STONE_SHOVEL;
		$items[] = ItemTypeIds::WOODEN_PICKAXE;
		$items[] = ItemTypeIds::WOODEN_HOE;
		$items[] = ItemTypeIds::GOLDEN_PICKAXE;
		$items[] = ItemTypeIds::GOLDEN_HOE;

		// 2 attack damage
		$items[] = ItemTypeIds::WOODEN_SHOVEL;
		$items[] = ItemTypeIds::GOLDEN_SHOVEL;

		// skeleton and wither skeleton skulls
		$items[] = static function(Item $i) : int {
			if ($i instanceof ItemBlock && ($b = $i->getBlock()) instanceof MobHead) {
				$type = $b->getMobHeadType();
				return ($type === MobHeadType::SKELETON || $type === MobHeadType::WITHER_SKELETON) ? 1 : 0;
			}
			return 0;
		};
		$items[] = ItemTypeIds::fromBlockTypeId(BlockTypeIds::CARVED_PUMPKIN);

		//Unlike other skeleton variants, bedrock wither skeletons don't pick up items other than these...

		return $items;
	}
}
