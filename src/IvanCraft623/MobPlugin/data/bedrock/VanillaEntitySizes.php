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

namespace IvanCraft623\MobPlugin\data\bedrock;

use pocketmine\entity\EntitySizeInfo;

/**
 * Auto-generated from the entities of the pinned Mojang bedrock-samples — do not edit by
 * hand. Each entity's base collision box, before scale; entities that only declare boxes
 * for their variants are left out.
 *
 * Regenerate with: php tools/entity-data/generate-entity-data.php
 */
final class VanillaEntitySizes{

	public const ALLAY_WIDTH = 0.35;
	public const ALLAY_HEIGHT = 0.6;
	public const ARMADILLO_WIDTH = 0.7;
	public const ARMADILLO_HEIGHT = 0.65;
	public const ARMOR_STAND_WIDTH = 0.5;
	public const ARMOR_STAND_HEIGHT = 1.975;
	public const ARROW_WIDTH = 0.25;
	public const ARROW_HEIGHT = 0.25;
	public const AXOLOTL_WIDTH = 0.75;
	public const AXOLOTL_HEIGHT = 0.42;
	public const BAT_WIDTH = 0.5;
	public const BAT_HEIGHT = 0.9;
	public const BEE_WIDTH = 0.55;
	public const BEE_HEIGHT = 0.5;
	public const BLAZE_WIDTH = 0.5;
	public const BLAZE_HEIGHT = 1.8;
	public const BOAT_WIDTH = 1.4;
	public const BOAT_HEIGHT = 0.455;
	public const BOGGED_WIDTH = 0.6;
	public const BOGGED_HEIGHT = 1.9;
	public const BREEZE_WIDTH = 0.6;
	public const BREEZE_HEIGHT = 1.77;
	public const BREEZE_WIND_CHARGE_PROJECTILE_WIDTH = 0.3125;
	public const BREEZE_WIND_CHARGE_PROJECTILE_HEIGHT = 0.3125;
	public const CAMEL_WIDTH = 1.7;
	public const CAMEL_HEIGHT = 2.375;
	public const CAMEL_HUSK_WIDTH = 1.7;
	public const CAMEL_HUSK_HEIGHT = 2.375;
	public const CAT_WIDTH = 0.6;
	public const CAT_HEIGHT = 0.7;
	public const CAVE_SPIDER_WIDTH = 0.7;
	public const CAVE_SPIDER_HEIGHT = 0.5;
	public const CHEST_BOAT_WIDTH = 1.4;
	public const CHEST_BOAT_HEIGHT = 0.455;
	public const CHEST_MINECART_WIDTH = 0.98;
	public const CHEST_MINECART_HEIGHT = 0.7;
	public const CHICKEN_WIDTH = 0.6;
	public const CHICKEN_HEIGHT = 0.8;
	public const COD_WIDTH = 0.6;
	public const COD_HEIGHT = 0.3;
	public const COMMAND_BLOCK_MINECART_WIDTH = 0.98;
	public const COMMAND_BLOCK_MINECART_HEIGHT = 0.7;
	public const COPPER_GOLEM_WIDTH = 0.6;
	public const COPPER_GOLEM_HEIGHT = 0.98;
	public const COW_WIDTH = 0.9;
	public const COW_HEIGHT = 1.3;
	public const CREAKING_WIDTH = 0.9;
	public const CREAKING_HEIGHT = 2.7;
	public const CREEPER_WIDTH = 0.6;
	public const CREEPER_HEIGHT = 1.8;
	public const CUSHION_WIDTH = 0.999;
	public const CUSHION_HEIGHT = 0.249;
	public const DOLPHIN_WIDTH = 0.9;
	public const DOLPHIN_HEIGHT = 0.6;
	public const DONKEY_WIDTH = 1.4;
	public const DONKEY_HEIGHT = 1.6;
	public const DRAGON_FIREBALL_WIDTH = 0.31;
	public const DRAGON_FIREBALL_HEIGHT = 0.31;
	public const DROWNED_WIDTH = 0.6;
	public const DROWNED_HEIGHT = 1.9;
	public const EGG_WIDTH = 0.25;
	public const EGG_HEIGHT = 0.25;
	public const ELDER_GUARDIAN_WIDTH = 1.99;
	public const ELDER_GUARDIAN_HEIGHT = 1.99;
	public const ENDERMAN_WIDTH = 0.6;
	public const ENDERMAN_HEIGHT = 2.9;
	public const ENDERMITE_WIDTH = 0.4;
	public const ENDERMITE_HEIGHT = 0.3;
	public const ENDER_CRYSTAL_WIDTH = 2.0;
	public const ENDER_CRYSTAL_HEIGHT = 2.0;
	public const ENDER_DRAGON_WIDTH = 13.0;
	public const ENDER_DRAGON_HEIGHT = 4.0;
	public const ENDER_PEARL_WIDTH = 0.25;
	public const ENDER_PEARL_HEIGHT = 0.25;
	public const EVOCATION_ILLAGER_WIDTH = 0.6;
	public const EVOCATION_ILLAGER_HEIGHT = 1.9;
	public const EYE_OF_ENDER_SIGNAL_WIDTH = 0.25;
	public const EYE_OF_ENDER_SIGNAL_HEIGHT = 0.25;
	public const FIREBALL_WIDTH = 1.0;
	public const FIREBALL_HEIGHT = 1.0;
	public const FIREWORKS_ROCKET_WIDTH = 0.25;
	public const FIREWORKS_ROCKET_HEIGHT = 0.25;
	public const FISHING_HOOK_WIDTH = 0.15;
	public const FISHING_HOOK_HEIGHT = 0.15;
	public const FOX_WIDTH = 0.6;
	public const FOX_HEIGHT = 0.7;
	public const FROG_WIDTH = 0.5;
	public const FROG_HEIGHT = 0.55;
	public const GHAST_WIDTH = 4.02;
	public const GHAST_HEIGHT = 4.0;
	public const GLOW_SQUID_WIDTH = 0.8;
	public const GLOW_SQUID_HEIGHT = 0.8;
	public const GOAT_WIDTH = 0.9;
	public const GOAT_HEIGHT = 1.3;
	public const GUARDIAN_WIDTH = 0.85;
	public const GUARDIAN_HEIGHT = 0.85;
	public const HAPPY_GHAST_WIDTH = 4.0;
	public const HAPPY_GHAST_HEIGHT = 4.0;
	public const HOGLIN_WIDTH = 0.6;
	public const HOGLIN_HEIGHT = 1.9;
	public const HOPPER_MINECART_WIDTH = 0.98;
	public const HOPPER_MINECART_HEIGHT = 0.7;
	public const HORSE_WIDTH = 1.4;
	public const HORSE_HEIGHT = 1.6;
	public const HUSK_WIDTH = 0.6;
	public const HUSK_HEIGHT = 1.9;
	public const IRON_GOLEM_WIDTH = 1.4;
	public const IRON_GOLEM_HEIGHT = 2.9;
	public const LINGERING_POTION_WIDTH = 0.25;
	public const LINGERING_POTION_HEIGHT = 0.25;
	public const LLAMA_WIDTH = 0.9;
	public const LLAMA_HEIGHT = 1.87;
	public const LLAMA_SPIT_WIDTH = 0.31;
	public const LLAMA_SPIT_HEIGHT = 0.31;
	public const MAGMA_CUBE_WIDTH = 2.08;
	public const MAGMA_CUBE_HEIGHT = 2.08;
	public const MINECART_WIDTH = 0.98;
	public const MINECART_HEIGHT = 0.7;
	public const MOOSHROOM_WIDTH = 0.9;
	public const MOOSHROOM_HEIGHT = 1.3;
	public const MULE_WIDTH = 1.4;
	public const MULE_HEIGHT = 1.6;
	public const NPC_WIDTH = 0.6;
	public const NPC_HEIGHT = 2.1;
	public const OCELOT_WIDTH = 0.6;
	public const OCELOT_HEIGHT = 0.7;
	public const PANDA_WIDTH = 1.3;
	public const PANDA_HEIGHT = 1.25;
	public const PARCHED_WIDTH = 0.6;
	public const PARCHED_HEIGHT = 1.9;
	public const PARROT_WIDTH = 0.5;
	public const PARROT_HEIGHT = 1.0;
	public const PHANTOM_WIDTH = 0.9;
	public const PHANTOM_HEIGHT = 0.5;
	public const PIG_WIDTH = 0.9;
	public const PIG_HEIGHT = 0.9;
	public const PIGLIN_WIDTH = 0.6;
	public const PIGLIN_HEIGHT = 1.9;
	public const PIGLIN_BRUTE_WIDTH = 0.6;
	public const PIGLIN_BRUTE_HEIGHT = 1.9;
	public const PILLAGER_WIDTH = 0.6;
	public const PILLAGER_HEIGHT = 1.9;
	public const PLAYER_WIDTH = 0.6;
	public const PLAYER_HEIGHT = 1.8;
	public const POLAR_BEAR_WIDTH = 1.4;
	public const POLAR_BEAR_HEIGHT = 1.4;
	public const PUFFERFISH_WIDTH = 0.8;
	public const PUFFERFISH_HEIGHT = 0.8;
	public const RAVAGER_WIDTH = 1.95;
	public const RAVAGER_HEIGHT = 2.2;
	public const SALMON_WIDTH = 0.5;
	public const SALMON_HEIGHT = 0.5;
	public const SHEEP_WIDTH = 0.9;
	public const SHEEP_HEIGHT = 1.3;
	public const SHULKER_BULLET_WIDTH = 0.625;
	public const SHULKER_BULLET_HEIGHT = 0.625;
	public const SILVERFISH_WIDTH = 0.4;
	public const SILVERFISH_HEIGHT = 0.3;
	public const SKELETON_WIDTH = 0.6;
	public const SKELETON_HEIGHT = 1.9;
	public const SLIME_WIDTH = 2.08;
	public const SLIME_HEIGHT = 2.08;
	public const SMALL_FIREBALL_WIDTH = 0.31;
	public const SMALL_FIREBALL_HEIGHT = 0.31;
	public const SNIFFER_WIDTH = 1.9;
	public const SNIFFER_HEIGHT = 1.75;
	public const SNOWBALL_WIDTH = 0.25;
	public const SNOWBALL_HEIGHT = 0.25;
	public const SNOW_GOLEM_WIDTH = 0.4;
	public const SNOW_GOLEM_HEIGHT = 1.8;
	public const SPIDER_WIDTH = 1.4;
	public const SPIDER_HEIGHT = 0.9;
	public const SPLASH_POTION_WIDTH = 0.25;
	public const SPLASH_POTION_HEIGHT = 0.25;
	public const SQUID_WIDTH = 0.8;
	public const SQUID_HEIGHT = 0.8;
	public const STRAY_WIDTH = 0.6;
	public const STRAY_HEIGHT = 1.9;
	public const STRIDER_WIDTH = 0.9;
	public const STRIDER_HEIGHT = 1.7;
	public const SULFUR_CUBE_WIDTH = 0.98;
	public const SULFUR_CUBE_HEIGHT = 0.98;
	public const TADPOLE_WIDTH = 0.8;
	public const TADPOLE_HEIGHT = 0.6;
	public const THROWN_TRIDENT_WIDTH = 0.25;
	public const THROWN_TRIDENT_HEIGHT = 0.35;
	public const TNT_WIDTH = 0.98;
	public const TNT_HEIGHT = 0.98;
	public const TNT_MINECART_WIDTH = 0.98;
	public const TNT_MINECART_HEIGHT = 0.7;
	public const TRADER_LLAMA_WIDTH = 0.9;
	public const TRADER_LLAMA_HEIGHT = 1.87;
	public const TRIPOD_CAMERA_WIDTH = 0.75;
	public const TRIPOD_CAMERA_HEIGHT = 1.8;
	public const TROPICALFISH_WIDTH = 0.4;
	public const TROPICALFISH_HEIGHT = 0.4;
	public const VEX_WIDTH = 0.4;
	public const VEX_HEIGHT = 0.8;
	public const VILLAGER_WIDTH = 0.6;
	public const VILLAGER_HEIGHT = 1.9;
	public const VILLAGER_V2_WIDTH = 0.6;
	public const VILLAGER_V2_HEIGHT = 1.9;
	public const VINDICATOR_WIDTH = 0.6;
	public const VINDICATOR_HEIGHT = 1.9;
	public const WANDERING_TRADER_WIDTH = 0.6;
	public const WANDERING_TRADER_HEIGHT = 1.9;
	public const WARDEN_WIDTH = 0.9;
	public const WARDEN_HEIGHT = 2.9;
	public const WIND_CHARGE_PROJECTILE_WIDTH = 0.3125;
	public const WIND_CHARGE_PROJECTILE_HEIGHT = 0.3125;
	public const WITCH_WIDTH = 0.6;
	public const WITCH_HEIGHT = 1.9;
	public const WITHER_WIDTH = 1.0;
	public const WITHER_HEIGHT = 3.0;
	public const WITHER_SKELETON_WIDTH = 0.72;
	public const WITHER_SKELETON_HEIGHT = 2.01;
	public const WITHER_SKULL_WIDTH = 0.15;
	public const WITHER_SKULL_HEIGHT = 0.15;
	public const WITHER_SKULL_DANGEROUS_WIDTH = 0.15;
	public const WITHER_SKULL_DANGEROUS_HEIGHT = 0.15;
	public const WOLF_WIDTH = 0.6;
	public const WOLF_HEIGHT = 0.8;
	public const XP_BOTTLE_WIDTH = 0.25;
	public const XP_BOTTLE_HEIGHT = 0.25;
	public const XP_ORB_WIDTH = 0.25;
	public const XP_ORB_HEIGHT = 0.25;
	public const ZOMBIE_WIDTH = 0.6;
	public const ZOMBIE_HEIGHT = 1.9;
	public const ZOMBIE_NAUTILUS_WIDTH = 0.875;
	public const ZOMBIE_NAUTILUS_HEIGHT = 0.95;
	public const ZOMBIE_PIGMAN_WIDTH = 0.6;
	public const ZOMBIE_PIGMAN_HEIGHT = 1.9;
	public const ZOMBIE_VILLAGER_WIDTH = 0.6;
	public const ZOMBIE_VILLAGER_HEIGHT = 1.9;
	public const ZOMBIE_VILLAGER_V2_WIDTH = 0.6;
	public const ZOMBIE_VILLAGER_V2_HEIGHT = 1.9;

	/** @phpstan-var array<string, array{float, float}> entity id => [width, height] */
	private const BY_ID = [
		EntityIds::ALLAY => [self::ALLAY_WIDTH, self::ALLAY_HEIGHT],
		EntityIds::ARMADILLO => [self::ARMADILLO_WIDTH, self::ARMADILLO_HEIGHT],
		EntityIds::ARMOR_STAND => [self::ARMOR_STAND_WIDTH, self::ARMOR_STAND_HEIGHT],
		EntityIds::ARROW => [self::ARROW_WIDTH, self::ARROW_HEIGHT],
		EntityIds::AXOLOTL => [self::AXOLOTL_WIDTH, self::AXOLOTL_HEIGHT],
		EntityIds::BAT => [self::BAT_WIDTH, self::BAT_HEIGHT],
		EntityIds::BEE => [self::BEE_WIDTH, self::BEE_HEIGHT],
		EntityIds::BLAZE => [self::BLAZE_WIDTH, self::BLAZE_HEIGHT],
		EntityIds::BOAT => [self::BOAT_WIDTH, self::BOAT_HEIGHT],
		EntityIds::BOGGED => [self::BOGGED_WIDTH, self::BOGGED_HEIGHT],
		EntityIds::BREEZE => [self::BREEZE_WIDTH, self::BREEZE_HEIGHT],
		EntityIds::BREEZE_WIND_CHARGE_PROJECTILE => [self::BREEZE_WIND_CHARGE_PROJECTILE_WIDTH, self::BREEZE_WIND_CHARGE_PROJECTILE_HEIGHT],
		EntityIds::CAMEL => [self::CAMEL_WIDTH, self::CAMEL_HEIGHT],
		EntityIds::CAMEL_HUSK => [self::CAMEL_HUSK_WIDTH, self::CAMEL_HUSK_HEIGHT],
		EntityIds::CAT => [self::CAT_WIDTH, self::CAT_HEIGHT],
		EntityIds::CAVE_SPIDER => [self::CAVE_SPIDER_WIDTH, self::CAVE_SPIDER_HEIGHT],
		EntityIds::CHEST_BOAT => [self::CHEST_BOAT_WIDTH, self::CHEST_BOAT_HEIGHT],
		EntityIds::CHEST_MINECART => [self::CHEST_MINECART_WIDTH, self::CHEST_MINECART_HEIGHT],
		EntityIds::CHICKEN => [self::CHICKEN_WIDTH, self::CHICKEN_HEIGHT],
		EntityIds::COD => [self::COD_WIDTH, self::COD_HEIGHT],
		EntityIds::COMMAND_BLOCK_MINECART => [self::COMMAND_BLOCK_MINECART_WIDTH, self::COMMAND_BLOCK_MINECART_HEIGHT],
		EntityIds::COPPER_GOLEM => [self::COPPER_GOLEM_WIDTH, self::COPPER_GOLEM_HEIGHT],
		EntityIds::COW => [self::COW_WIDTH, self::COW_HEIGHT],
		EntityIds::CREAKING => [self::CREAKING_WIDTH, self::CREAKING_HEIGHT],
		EntityIds::CREEPER => [self::CREEPER_WIDTH, self::CREEPER_HEIGHT],
		EntityIds::CUSHION => [self::CUSHION_WIDTH, self::CUSHION_HEIGHT],
		EntityIds::DOLPHIN => [self::DOLPHIN_WIDTH, self::DOLPHIN_HEIGHT],
		EntityIds::DONKEY => [self::DONKEY_WIDTH, self::DONKEY_HEIGHT],
		EntityIds::DRAGON_FIREBALL => [self::DRAGON_FIREBALL_WIDTH, self::DRAGON_FIREBALL_HEIGHT],
		EntityIds::DROWNED => [self::DROWNED_WIDTH, self::DROWNED_HEIGHT],
		EntityIds::EGG => [self::EGG_WIDTH, self::EGG_HEIGHT],
		EntityIds::ELDER_GUARDIAN => [self::ELDER_GUARDIAN_WIDTH, self::ELDER_GUARDIAN_HEIGHT],
		EntityIds::ENDERMAN => [self::ENDERMAN_WIDTH, self::ENDERMAN_HEIGHT],
		EntityIds::ENDERMITE => [self::ENDERMITE_WIDTH, self::ENDERMITE_HEIGHT],
		EntityIds::ENDER_CRYSTAL => [self::ENDER_CRYSTAL_WIDTH, self::ENDER_CRYSTAL_HEIGHT],
		EntityIds::ENDER_DRAGON => [self::ENDER_DRAGON_WIDTH, self::ENDER_DRAGON_HEIGHT],
		EntityIds::ENDER_PEARL => [self::ENDER_PEARL_WIDTH, self::ENDER_PEARL_HEIGHT],
		EntityIds::EVOCATION_ILLAGER => [self::EVOCATION_ILLAGER_WIDTH, self::EVOCATION_ILLAGER_HEIGHT],
		EntityIds::EYE_OF_ENDER_SIGNAL => [self::EYE_OF_ENDER_SIGNAL_WIDTH, self::EYE_OF_ENDER_SIGNAL_HEIGHT],
		EntityIds::FIREBALL => [self::FIREBALL_WIDTH, self::FIREBALL_HEIGHT],
		EntityIds::FIREWORKS_ROCKET => [self::FIREWORKS_ROCKET_WIDTH, self::FIREWORKS_ROCKET_HEIGHT],
		EntityIds::FISHING_HOOK => [self::FISHING_HOOK_WIDTH, self::FISHING_HOOK_HEIGHT],
		EntityIds::FOX => [self::FOX_WIDTH, self::FOX_HEIGHT],
		EntityIds::FROG => [self::FROG_WIDTH, self::FROG_HEIGHT],
		EntityIds::GHAST => [self::GHAST_WIDTH, self::GHAST_HEIGHT],
		EntityIds::GLOW_SQUID => [self::GLOW_SQUID_WIDTH, self::GLOW_SQUID_HEIGHT],
		EntityIds::GOAT => [self::GOAT_WIDTH, self::GOAT_HEIGHT],
		EntityIds::GUARDIAN => [self::GUARDIAN_WIDTH, self::GUARDIAN_HEIGHT],
		EntityIds::HAPPY_GHAST => [self::HAPPY_GHAST_WIDTH, self::HAPPY_GHAST_HEIGHT],
		EntityIds::HOGLIN => [self::HOGLIN_WIDTH, self::HOGLIN_HEIGHT],
		EntityIds::HOPPER_MINECART => [self::HOPPER_MINECART_WIDTH, self::HOPPER_MINECART_HEIGHT],
		EntityIds::HORSE => [self::HORSE_WIDTH, self::HORSE_HEIGHT],
		EntityIds::HUSK => [self::HUSK_WIDTH, self::HUSK_HEIGHT],
		EntityIds::IRON_GOLEM => [self::IRON_GOLEM_WIDTH, self::IRON_GOLEM_HEIGHT],
		EntityIds::LINGERING_POTION => [self::LINGERING_POTION_WIDTH, self::LINGERING_POTION_HEIGHT],
		EntityIds::LLAMA => [self::LLAMA_WIDTH, self::LLAMA_HEIGHT],
		EntityIds::LLAMA_SPIT => [self::LLAMA_SPIT_WIDTH, self::LLAMA_SPIT_HEIGHT],
		EntityIds::MAGMA_CUBE => [self::MAGMA_CUBE_WIDTH, self::MAGMA_CUBE_HEIGHT],
		EntityIds::MINECART => [self::MINECART_WIDTH, self::MINECART_HEIGHT],
		EntityIds::MOOSHROOM => [self::MOOSHROOM_WIDTH, self::MOOSHROOM_HEIGHT],
		EntityIds::MULE => [self::MULE_WIDTH, self::MULE_HEIGHT],
		EntityIds::NPC => [self::NPC_WIDTH, self::NPC_HEIGHT],
		EntityIds::OCELOT => [self::OCELOT_WIDTH, self::OCELOT_HEIGHT],
		EntityIds::PANDA => [self::PANDA_WIDTH, self::PANDA_HEIGHT],
		EntityIds::PARCHED => [self::PARCHED_WIDTH, self::PARCHED_HEIGHT],
		EntityIds::PARROT => [self::PARROT_WIDTH, self::PARROT_HEIGHT],
		EntityIds::PHANTOM => [self::PHANTOM_WIDTH, self::PHANTOM_HEIGHT],
		EntityIds::PIG => [self::PIG_WIDTH, self::PIG_HEIGHT],
		EntityIds::PIGLIN => [self::PIGLIN_WIDTH, self::PIGLIN_HEIGHT],
		EntityIds::PIGLIN_BRUTE => [self::PIGLIN_BRUTE_WIDTH, self::PIGLIN_BRUTE_HEIGHT],
		EntityIds::PILLAGER => [self::PILLAGER_WIDTH, self::PILLAGER_HEIGHT],
		EntityIds::PLAYER => [self::PLAYER_WIDTH, self::PLAYER_HEIGHT],
		EntityIds::POLAR_BEAR => [self::POLAR_BEAR_WIDTH, self::POLAR_BEAR_HEIGHT],
		EntityIds::PUFFERFISH => [self::PUFFERFISH_WIDTH, self::PUFFERFISH_HEIGHT],
		EntityIds::RAVAGER => [self::RAVAGER_WIDTH, self::RAVAGER_HEIGHT],
		EntityIds::SALMON => [self::SALMON_WIDTH, self::SALMON_HEIGHT],
		EntityIds::SHEEP => [self::SHEEP_WIDTH, self::SHEEP_HEIGHT],
		EntityIds::SHULKER_BULLET => [self::SHULKER_BULLET_WIDTH, self::SHULKER_BULLET_HEIGHT],
		EntityIds::SILVERFISH => [self::SILVERFISH_WIDTH, self::SILVERFISH_HEIGHT],
		EntityIds::SKELETON => [self::SKELETON_WIDTH, self::SKELETON_HEIGHT],
		EntityIds::SLIME => [self::SLIME_WIDTH, self::SLIME_HEIGHT],
		EntityIds::SMALL_FIREBALL => [self::SMALL_FIREBALL_WIDTH, self::SMALL_FIREBALL_HEIGHT],
		EntityIds::SNIFFER => [self::SNIFFER_WIDTH, self::SNIFFER_HEIGHT],
		EntityIds::SNOWBALL => [self::SNOWBALL_WIDTH, self::SNOWBALL_HEIGHT],
		EntityIds::SNOW_GOLEM => [self::SNOW_GOLEM_WIDTH, self::SNOW_GOLEM_HEIGHT],
		EntityIds::SPIDER => [self::SPIDER_WIDTH, self::SPIDER_HEIGHT],
		EntityIds::SPLASH_POTION => [self::SPLASH_POTION_WIDTH, self::SPLASH_POTION_HEIGHT],
		EntityIds::SQUID => [self::SQUID_WIDTH, self::SQUID_HEIGHT],
		EntityIds::STRAY => [self::STRAY_WIDTH, self::STRAY_HEIGHT],
		EntityIds::STRIDER => [self::STRIDER_WIDTH, self::STRIDER_HEIGHT],
		EntityIds::SULFUR_CUBE => [self::SULFUR_CUBE_WIDTH, self::SULFUR_CUBE_HEIGHT],
		EntityIds::TADPOLE => [self::TADPOLE_WIDTH, self::TADPOLE_HEIGHT],
		EntityIds::THROWN_TRIDENT => [self::THROWN_TRIDENT_WIDTH, self::THROWN_TRIDENT_HEIGHT],
		EntityIds::TNT => [self::TNT_WIDTH, self::TNT_HEIGHT],
		EntityIds::TNT_MINECART => [self::TNT_MINECART_WIDTH, self::TNT_MINECART_HEIGHT],
		EntityIds::TRADER_LLAMA => [self::TRADER_LLAMA_WIDTH, self::TRADER_LLAMA_HEIGHT],
		EntityIds::TRIPOD_CAMERA => [self::TRIPOD_CAMERA_WIDTH, self::TRIPOD_CAMERA_HEIGHT],
		EntityIds::TROPICALFISH => [self::TROPICALFISH_WIDTH, self::TROPICALFISH_HEIGHT],
		EntityIds::VEX => [self::VEX_WIDTH, self::VEX_HEIGHT],
		EntityIds::VILLAGER => [self::VILLAGER_WIDTH, self::VILLAGER_HEIGHT],
		EntityIds::VILLAGER_V2 => [self::VILLAGER_V2_WIDTH, self::VILLAGER_V2_HEIGHT],
		EntityIds::VINDICATOR => [self::VINDICATOR_WIDTH, self::VINDICATOR_HEIGHT],
		EntityIds::WANDERING_TRADER => [self::WANDERING_TRADER_WIDTH, self::WANDERING_TRADER_HEIGHT],
		EntityIds::WARDEN => [self::WARDEN_WIDTH, self::WARDEN_HEIGHT],
		EntityIds::WIND_CHARGE_PROJECTILE => [self::WIND_CHARGE_PROJECTILE_WIDTH, self::WIND_CHARGE_PROJECTILE_HEIGHT],
		EntityIds::WITCH => [self::WITCH_WIDTH, self::WITCH_HEIGHT],
		EntityIds::WITHER => [self::WITHER_WIDTH, self::WITHER_HEIGHT],
		EntityIds::WITHER_SKELETON => [self::WITHER_SKELETON_WIDTH, self::WITHER_SKELETON_HEIGHT],
		EntityIds::WITHER_SKULL => [self::WITHER_SKULL_WIDTH, self::WITHER_SKULL_HEIGHT],
		EntityIds::WITHER_SKULL_DANGEROUS => [self::WITHER_SKULL_DANGEROUS_WIDTH, self::WITHER_SKULL_DANGEROUS_HEIGHT],
		EntityIds::WOLF => [self::WOLF_WIDTH, self::WOLF_HEIGHT],
		EntityIds::XP_BOTTLE => [self::XP_BOTTLE_WIDTH, self::XP_BOTTLE_HEIGHT],
		EntityIds::XP_ORB => [self::XP_ORB_WIDTH, self::XP_ORB_HEIGHT],
		EntityIds::ZOMBIE => [self::ZOMBIE_WIDTH, self::ZOMBIE_HEIGHT],
		EntityIds::ZOMBIE_NAUTILUS => [self::ZOMBIE_NAUTILUS_WIDTH, self::ZOMBIE_NAUTILUS_HEIGHT],
		EntityIds::ZOMBIE_PIGMAN => [self::ZOMBIE_PIGMAN_WIDTH, self::ZOMBIE_PIGMAN_HEIGHT],
		EntityIds::ZOMBIE_VILLAGER => [self::ZOMBIE_VILLAGER_WIDTH, self::ZOMBIE_VILLAGER_HEIGHT],
		EntityIds::ZOMBIE_VILLAGER_V2 => [self::ZOMBIE_VILLAGER_V2_WIDTH, self::ZOMBIE_VILLAGER_V2_HEIGHT],
	];

	private function __construct(){}

	/**
	 * The entity's base collision box, with PocketMine-MP's default eye height.
	 */
	public static function get(string $entityId) : ?EntitySizeInfo{
		if(!isset(self::BY_ID[$entityId])){
			return null;
		}
		[$width, $height] = self::BY_ID[$entityId];

		return new EntitySizeInfo($height, $width);
	}
}
