<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\memory;

use pocketmine\utils\CloningRegistryTrait;

class MemoryModuleType {
	use CloningRegistryTrait;

	private ?ExpirableValue $value = null;

	protected static function register(string $name, MemoryModuleType $memoryModuleType): void {
		self::_registryRegister($name, $memoryModuleType);
	}

	/**
	 * @return MemoryModuleType[]
	 */
	public static function getAll(): array {
		/** @var MemoryModuleType[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup(): void {
		self::register("dummy", new MemoryModuleType()); //void
		self::register("home", new MemoryModuleType()); //Position
		self::register("job_site", new MemoryModuleType()); //Position
		self::register("potential_job_site", new MemoryModuleType()); //Position
		self::register("meeting_pont", new MemoryModuleType()); //Position
		self::register("secondary_job_site", new MemoryModuleType()); //Position[]
		self::register("living_entities", new MemoryModuleType()); //Living[]
		self::register("visible_living_entities", new MemoryModuleType()); //Living[]
		self::register("visible_villager_babies", new MemoryModuleType()); //Living[]
		self::register("nearest_players", new MemoryModuleType()); //Player[]
		self::register("nearest_visible_player", new MemoryModuleType()); //Player
		self::register("nearest_visible_targetable_player", new MemoryModuleType()); //Player
		self::register("walk_target", new MemoryModuleType()); //WalkTarget
		self::register("look_target", new MemoryModuleType()); //PositionTracker
		self::register("attack_target", new MemoryModuleType()); //Living
		self::register("attack_cooling_down", new MemoryModuleType()); //bool
		self::register("interaction_target", new MemoryModuleType()); //Living
		self::register("breed_target", new MemoryModuleType()); //AgeableMob
		self::register("ride_target", new MemoryModuleType()); //Entity
		self::register("path", new MemoryModuleType()); //Path
		self::register("interactable_doors", new MemoryModuleType()); //Position[]
		self::register("doors_to_close", new MemoryModuleType()); //Position[]
		self::register("nearest_bed", new MemoryModuleType()); //Position
		self::register("hurt_by", new MemoryModuleType()); //EntityDamgeEvent
		self::register("hurt_by_entity", new MemoryModuleType()); //Living
		self::register("avoid_target", new MemoryModuleType()); //Living
		self::register("nearest_hostile", new MemoryModuleType()); //Living
		self::register("hiding_place", new MemoryModuleType()); //Position
		self::register("heard_bell_time", new MemoryModuleType()); //int
		self::register("cant_reach_walk_target_since", new MemoryModuleType()); //int
		self::register("golem_detected_recently", new MemoryModuleType()); //bool
		self::register("last_slept", new MemoryModuleType()); //int
		self::register("last_woken", new MemoryModuleType()); //int
		self::register("last_worked_at_poi", new MemoryModuleType()); //int
		self::register("nearest_visible_adult", new MemoryModuleType()); //AgeableMob
		self::register("nearest_visible_wanted_item", new MemoryModuleType()); //ItemEntity
		self::register("nearest_visible_nemesis", new MemoryModuleType()); //Mob
		self::register("angry_at", new MemoryModuleType()); //Entity Id
		self::register("universal_anger", new MemoryModuleType()); //bool
		self::register("admiring_item", new MemoryModuleType()); //bool
		self::register("time_trying_to_reach_admire_item", new MemoryModuleType()); //int
		self::register("disable_walk_to_admire_item", new MemoryModuleType()); //bool
		self::register("admiring_disabled", new MemoryModuleType()); //bool
		self::register("hunted_recently", new MemoryModuleType()); //bool
		self::register("celebrate_location", new MemoryModuleType()); //Position
		self::register("dancing", new MemoryModuleType()); //bool
		self::register("nearest_visible_huntable_hoglin", new MemoryModuleType()); //Hoglin
		self::register("nearest_visible_baby_hoglin", new MemoryModuleType()); //Hoglin
		self::register("nearest_targetable_player_not_wearing_gold", new MemoryModuleType()); //Player
		self::register("nearby_adult_piglins", new MemoryModuleType()); //Piglin[]
		self::register("nearest_visible_adult_piglins", new MemoryModuleType()); //Piglin[]
		self::register("nearest_visible_adult_hoglins", new MemoryModuleType()); //Hoglin[]
		self::register("nearest_visible_adult_piglin", new MemoryModuleType()); //Piglin
		self::register("nearest_visible_zombified", new MemoryModuleType()); //Living
		self::register("visible_adult_piglin_count", new MemoryModuleType()); //int
		self::register("visible_adult_hoglin_count", new MemoryModuleType()); //int
		self::register("nearest_player_holding_wanted_item", new MemoryModuleType()); //Player
		self::register("ate_recently", new MemoryModuleType()); //bool
		self::register("nearest_repellent", new MemoryModuleType()); //Position
		self::register("pacified", new MemoryModuleType()); //bool
	}

	public function setValue(?ExpirableValue $value): void {
		$this->value = $value;
	}

	public function getValue(): ?ExpirableValue {
		return $this->value;
	}
}