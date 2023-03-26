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

namespace IvanCraft623\MobPlugin\entity\ai\memory;

use pocketmine\utils\CloningRegistryTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static MemoryModuleType ADMIRING_DISABLED()
 * @method static MemoryModuleType ADMIRING_ITEM()
 * @method static MemoryModuleType ANGRY_AT()
 * @method static MemoryModuleType ATE_RECENTLY()
 * @method static MemoryModuleType ATTACK_COOLING_DOWN()
 * @method static MemoryModuleType ATTACK_TARGET()
 * @method static MemoryModuleType AVOID_TARGET()
 * @method static MemoryModuleType BREED_TARGET()
 * @method static MemoryModuleType CANT_REACH_WALK_TARGET_SINCE()
 * @method static MemoryModuleType CELEBRATE_LOCATION()
 * @method static MemoryModuleType DANCING()
 * @method static MemoryModuleType DISABLE_WALK_TO_ADMIRE_ITEM()
 * @method static MemoryModuleType DOORS_TO_CLOSE()
 * @method static MemoryModuleType DUMMY()
 * @method static MemoryModuleType GOLEM_DETECTED_RECENTLY()
 * @method static MemoryModuleType HEARD_BELL_TIME()
 * @method static MemoryModuleType HIDING_PLACE()
 * @method static MemoryModuleType HOME()
 * @method static MemoryModuleType HUNTED_RECENTLY()
 * @method static MemoryModuleType HURT_BY()
 * @method static MemoryModuleType HURT_BY_ENTITY()
 * @method static MemoryModuleType INTERACTABLE_DOORS()
 * @method static MemoryModuleType INTERACTION_TARGET()
 * @method static MemoryModuleType JOB_SITE()
 * @method static MemoryModuleType LAST_SLEPT()
 * @method static MemoryModuleType LAST_WOKEN()
 * @method static MemoryModuleType LAST_WORKED_AT_POI()
 * @method static MemoryModuleType LIVING_ENTITIES()
 * @method static MemoryModuleType LOOK_TARGET()
 * @method static MemoryModuleType MEETING_PONT()
 * @method static MemoryModuleType NEARBY_ADULT_PIGLINS()
 * @method static MemoryModuleType NEAREST_BED()
 * @method static MemoryModuleType NEAREST_HOSTILE()
 * @method static MemoryModuleType NEAREST_PLAYERS()
 * @method static MemoryModuleType NEAREST_PLAYER_HOLDING_WANTED_ITEM()
 * @method static MemoryModuleType NEAREST_REPELLENT()
 * @method static MemoryModuleType NEAREST_TARGETABLE_PLAYER_NOT_WEARING_GOLD()
 * @method static MemoryModuleType NEAREST_VISIBLE_ADULT()
 * @method static MemoryModuleType NEAREST_VISIBLE_ADULT_HOGLINS()
 * @method static MemoryModuleType NEAREST_VISIBLE_ADULT_PIGLIN()
 * @method static MemoryModuleType NEAREST_VISIBLE_ADULT_PIGLINS()
 * @method static MemoryModuleType NEAREST_VISIBLE_BABY_HOGLIN()
 * @method static MemoryModuleType NEAREST_VISIBLE_HUNTABLE_HOGLIN()
 * @method static MemoryModuleType NEAREST_VISIBLE_NEMESIS()
 * @method static MemoryModuleType NEAREST_VISIBLE_PLAYER()
 * @method static MemoryModuleType NEAREST_VISIBLE_TARGETABLE_PLAYER()
 * @method static MemoryModuleType NEAREST_VISIBLE_WANTED_ITEM()
 * @method static MemoryModuleType NEAREST_VISIBLE_ZOMBIFIED()
 * @method static MemoryModuleType PACIFIED()
 * @method static MemoryModuleType PATH()
 * @method static MemoryModuleType POTENTIAL_JOB_SITE()
 * @method static MemoryModuleType RIDE_TARGET()
 * @method static MemoryModuleType SECONDARY_JOB_SITE()
 * @method static MemoryModuleType TIME_TRYING_TO_REACH_ADMIRE_ITEM()
 * @method static MemoryModuleType UNIVERSAL_ANGER()
 * @method static MemoryModuleType VISIBLE_ADULT_HOGLIN_COUNT()
 * @method static MemoryModuleType VISIBLE_ADULT_PIGLIN_COUNT()
 * @method static MemoryModuleType VISIBLE_LIVING_ENTITIES()
 * @method static MemoryModuleType VISIBLE_VILLAGER_BABIES()
 * @method static MemoryModuleType WALK_TARGET()
 */
final class MemoryModuleType {
	use CloningRegistryTrait;

	protected string $name;

	private ?ExpirableValue $value = null;

	protected static function register(string $name, MemoryModuleType $memoryModuleType) : void {
		self::_registryRegister($name, $memoryModuleType);
	}

	/**
	 * @return MemoryModuleType[]
	 */
	public static function getAll() : array {
		/** @var MemoryModuleType[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void {
		self::register("dummy", new MemoryModuleType("dummy")); //void
		self::register("home", new MemoryModuleType("home")); //Position
		self::register("job_site", new MemoryModuleType("job_site")); //Position
		self::register("potential_job_site", new MemoryModuleType("potential_job_site")); //Position
		self::register("meeting_pont", new MemoryModuleType("meeting_pont")); //Position
		self::register("secondary_job_site", new MemoryModuleType("secondary_job_site")); //Position[]
		self::register("living_entities", new MemoryModuleType("living_entities")); //Living[]
		self::register("visible_living_entities", new MemoryModuleType("visible_living_entities")); //array<int, Living>
		self::register("visible_villager_babies", new MemoryModuleType("visible_villager_babies")); //Living[]
		self::register("nearest_players", new MemoryModuleType("nearest_players")); //Player[]
		self::register("nearest_visible_player", new MemoryModuleType("nearest_visible_player")); //Player
		self::register("nearest_visible_targetable_player", new MemoryModuleType("nearest_visible_targetable_player")); //Player
		self::register("walk_target", new MemoryModuleType("walk_target")); //WalkTarget
		self::register("look_target", new MemoryModuleType("look_target")); //PositionTracker
		self::register("attack_target", new MemoryModuleType("attack_target")); //Living
		self::register("attack_cooling_down", new MemoryModuleType("attack_cooling_down")); //bool
		self::register("interaction_target", new MemoryModuleType("interaction_target")); //Living
		self::register("breed_target", new MemoryModuleType("breed_target")); //AgeableMob
		self::register("ride_target", new MemoryModuleType("ride_target")); //Entity
		self::register("path", new MemoryModuleType("path")); //Path
		self::register("interactable_doors", new MemoryModuleType("interactable_doors")); //Position[]
		self::register("doors_to_close", new MemoryModuleType("doors_to_close")); //Position[]
		self::register("nearest_bed", new MemoryModuleType("nearest_bed")); //Position
		self::register("hurt_by", new MemoryModuleType("hurt_by")); //EntityDamgeEvent
		self::register("hurt_by_entity", new MemoryModuleType("hurt_by_entity")); //Living
		self::register("avoid_target", new MemoryModuleType("avoid_target")); //Living
		self::register("nearest_hostile", new MemoryModuleType("nearest_hostile")); //Living
		self::register("hiding_place", new MemoryModuleType("hiding_place")); //Position
		self::register("heard_bell_time", new MemoryModuleType("heard_bell_time")); //int
		self::register("cant_reach_walk_target_since", new MemoryModuleType("cant_reach_walk_target_since")); //int
		self::register("golem_detected_recently", new MemoryModuleType("golem_detected_recently")); //bool
		self::register("last_slept", new MemoryModuleType("last_slept")); //int
		self::register("last_woken", new MemoryModuleType("last_woken")); //int
		self::register("last_worked_at_poi", new MemoryModuleType("last_worked_at_poi")); //int
		self::register("nearest_visible_adult", new MemoryModuleType("nearest_visible_adult")); //AgeableMob
		self::register("nearest_visible_wanted_item", new MemoryModuleType("nearest_visible_wanted_item")); //ItemEntity
		self::register("nearest_visible_nemesis", new MemoryModuleType("nearest_visible_nemesis")); //Mob
		self::register("angry_at", new MemoryModuleType("angry_at")); //Entity Id
		self::register("universal_anger", new MemoryModuleType("universal_anger")); //bool
		self::register("admiring_item", new MemoryModuleType("admiring_item")); //bool
		self::register("time_trying_to_reach_admire_item", new MemoryModuleType("time_trying_to_reach_admire_item")); //int
		self::register("disable_walk_to_admire_item", new MemoryModuleType("disable_walk_to_admire_item")); //bool
		self::register("admiring_disabled", new MemoryModuleType("admiring_disabled")); //bool
		self::register("hunted_recently", new MemoryModuleType("hunted_recently")); //bool
		self::register("celebrate_location", new MemoryModuleType("celebrate_location")); //Position
		self::register("dancing", new MemoryModuleType("dancing")); //bool
		self::register("nearest_visible_huntable_hoglin", new MemoryModuleType("nearest_visible_huntable_hoglin")); //Hoglin
		self::register("nearest_visible_baby_hoglin", new MemoryModuleType("nearest_visible_baby_hoglin")); //Hoglin
		self::register("nearest_targetable_player_not_wearing_gold", new MemoryModuleType("nearest_targetable_player_not_wearing_gold")); //Player
		self::register("nearby_adult_piglins", new MemoryModuleType("nearby_adult_piglins")); //Piglin[]
		self::register("nearest_visible_adult_piglins", new MemoryModuleType("nearest_visible_adult_piglins")); //Piglin[]
		self::register("nearest_visible_adult_hoglins", new MemoryModuleType("nearest_visible_adult_hoglins")); //Hoglin[]
		self::register("nearest_visible_adult_piglin", new MemoryModuleType("nearest_visible_adult_piglin")); //Piglin
		self::register("nearest_visible_zombified", new MemoryModuleType("nearest_visible_zombified")); //Living
		self::register("visible_adult_piglin_count", new MemoryModuleType("visible_adult_piglin_count")); //int
		self::register("visible_adult_hoglin_count", new MemoryModuleType("visible_adult_hoglin_count")); //int
		self::register("nearest_player_holding_wanted_item", new MemoryModuleType("nearest_player_holding_wanted_item")); //Player
		self::register("ate_recently", new MemoryModuleType("ate_recently")); //bool
		self::register("nearest_repellent", new MemoryModuleType("nearest_repellent")); //Position
		self::register("pacified", new MemoryModuleType("pacified")); //bool
	}

	public function __construct(string $name) {
		$this->name = $name;
	}

	public function name() : string{
		return $this->name;
	}

	public function setValue(?ExpirableValue $value) : void {
		$this->value = $value;
	}

	public function getValue() : ?ExpirableValue {
		return $this->value;
	}
}
