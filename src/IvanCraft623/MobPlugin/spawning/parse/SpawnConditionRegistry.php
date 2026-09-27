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

namespace IvanCraft623\MobPlugin\spawning\parse;

use IvanCraft623\MobPlugin\spawning\condition\NeverSpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\PassThroughSpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\BrightnessFilter;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\DensityLimitCondition;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\DifficultyFilter;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\DistanceFilter;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\HeightFilter;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\SpawnsInLiquid;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\SpawnsOnBlock;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\WorldAgeFilter;
use IvanCraft623\MobPlugin\spawning\parse\resolver\BiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\VanillaBiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnComponent;
use IvanCraft623\MobPlugin\spawning\payload\Herd;
use IvanCraft623\MobPlugin\spawning\payload\PermuteType;
use IvanCraft623\MobPlugin\spawning\payload\SpawnEvent;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use pocketmine\block\BlockTypeIds;
use pocketmine\utils\SingletonTrait;
use function max;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Registry mapping spawn-rule component names to parser closures. A process-wide
 * singleton (pocketmine\utils\SingletonTrait) preloaded with every vanilla component,
 * open for plugins: register/unregister/replace components freely.
 *
 * Vanilla components are referenced by their SpawnComponent enum cases — the cases are
 * generated from the official Mojang spawn schemas, so a component Mojang renames or
 * removes fails PHPStan here instead of silently disappearing from the registry. New
 * components fail the registry-coverage PHPUnit test until classified below.
 *
 * Parser contract: throw SpawnParseException (with the JSON path) to reject the whole
 * condition — the strict loader has no warning channel, so any unusable value aborts
 * the load.
 *
 * @phpstan-type ComponentParser \Closure(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void
 */
final class SpawnConditionRegistry{
	use SingletonTrait;

	/**
	 * Vanilla components with no implementation, registered to fail closed. Replaceable.
	 *
	 * @var list<SpawnComponent>
	 */
	public const UNSUPPORTED_VANILLA = [
		SpawnComponent::MOB_EVENT_FILTER,
		SpawnComponent::DELAY_FILTER,
		SpawnComponent::PLAYER_IN_VILLAGE_FILTER,
		SpawnComponent::SPAWNS_ABOVE_BLOCK_FILTER,
	];

	/**
	 * Recognized components with no runtime effect, registered as pass-through. Replaceable.
	 *
	 * @var list<SpawnComponent>
	 */
	public const PASS_THROUGH_VANILLA = [
		SpawnComponent::DISALLOW_SPAWNS_IN_BUBBLE, // PocketMine has no bubble-column blocks
		SpawnComponent::IS_PERSISTENT,
		SpawnComponent::IS_EXPERIMENTAL,
	];

	/** @phpstan-var array<string, ComponentParser> */
	private array $parsers = [];

	private readonly BiomeTagResolver $biomeTags;

	private function __construct(){
		$this->biomeTags = new VanillaBiomeTagResolver();
		$this->registerDefaultVanilla();
	}

	public function getBiomeTags() : BiomeTagResolver{
		return $this->biomeTags;
	}

	/** Registers the parser for every vanilla spawn-rule component, exactly once. */
	private function registerDefaultVanilla() : void{
		// Marker parsers each accept their own band; the builder unions them.
		$this->register(SpawnComponent::SPAWNS_ON_SURFACE, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$builder->allowHabitatBand(SpawnBand::SURFACE);
		});
		$this->register(SpawnComponent::SPAWNS_UNDERGROUND, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$builder->allowHabitatBand(SpawnBand::CAVE);
		});
		$this->register(SpawnComponent::SPAWNS_UNDERWATER, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$builder->addCondition(new SpawnsInLiquid(BlockTypeIds::WATER));
		});
		$this->register(SpawnComponent::SPAWNS_LAVA, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$builder->addCondition(new SpawnsInLiquid(BlockTypeIds::LAVA));
		});
		$this->register(SpawnComponent::BRIGHTNESS_FILTER, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$data = $condition->object($component);

			$builder->addCondition(new BrightnessFilter(
				$data->intOr("min", 0),
				$data->intOr("max", 15),
				$data->boolOr("adjust_for_weather", false)
			));
		});
		$this->register(SpawnComponent::DIFFICULTY_FILTER, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$data = $condition->object($component);

			$builder->addCondition(DifficultyFilter::fromNames($data->stringNullable("min"), $data->stringNullable("max")));
		});
		$this->register(SpawnComponent::HEIGHT_FILTER, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$data = $condition->object($component);

			$builder->addCondition(new HeightFilter($data->intNullable("min"), $data->intNullable("max")));
		});
		$this->register(SpawnComponent::DISTANCE_FILTER, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$data = $condition->object($component);

			$builder->addCondition(new DistanceFilter($data->floatNullable("min"), $data->floatNullable("max")));
		});
		$this->register(SpawnComponent::WORLD_AGE_FILTER, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$data = $condition->object($component);

			$builder->addCondition(new WorldAgeFilter($data->intNullable("min"), $data->intNullable("max")));
		});
		$this->register(SpawnComponent::SPAWNS_ON_BLOCK_FILTER, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$builder->addCondition(new SpawnsOnBlock($factory->resolveBlockSet($condition, $component), false));
		});
		$this->register(SpawnComponent::SPAWNS_ON_BLOCK_PREVENTED_FILTER, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$builder->addCondition(new SpawnsOnBlock($factory->resolveBlockSet($condition, $component), true));
		});
		$this->register(SpawnComponent::DENSITY_LIMIT, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$data = $condition->object($component);

			$builder->addCondition(new DensityLimitCondition(
				$builder->getIdentifier(),
				$data->intNullable("surface"),
				$data->intNullable("underground")
			));
		});
		$this->register(SpawnComponent::WEIGHT, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$data = $condition->object($component);

			// The vanilla "rarity" field is not consumed (documented approximation).
			$builder->setWeight($data->intOr("default", 1));
		});
		$this->register(SpawnComponent::HERD, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$data = $condition->objectOrList($component)[0];

			$min = max(1, $data->intOr("min_size", 1));
			$max = max($min, $data->intOr("max_size", $min));
			$builder->setHerd(new Herd($min, $max));
		});
		$this->register(SpawnComponent::PERMUTE_TYPE, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$entries = $condition->objectOrList($component);

			$permuteTypes = [];
			foreach($entries as $data){
				$permuteTypes[] = new PermuteType($data->intOr("weight", 1), $data->stringNullable("entity_type"));
			}
			$builder->setPermuteTypes($permuteTypes);
		});
		$this->register(SpawnComponent::SPAWN_EVENT, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$data = $condition->object($component);

			$builder->setEvent(new SpawnEvent($data->stringNullable("event")));
		});
		$this->register(SpawnComponent::BIOME_FILTER, static function(SpawnData $condition, string $component, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{
			$builder->addCondition((new BiomeFilterParser(SpawnConditionRegistry::getInstance()->getBiomeTags()))->fromCondition($condition, $component));
		});
		foreach(self::UNSUPPORTED_VANILLA as $component){
			$this->register($component, static function(SpawnData $condition, string $componentKey, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{

				$builder->addCondition(NeverSpawnCondition::instance());
			});
		}
		foreach(self::PASS_THROUGH_VANILLA as $component){
			$this->register($component, static function(SpawnData $condition, string $componentKey, SpawnGroupBuilder $builder, SpawnRulesFactory $factory) : void{

				$builder->addCondition(PassThroughSpawnCondition::instance());
			});
		}
	}

	/**
	 * @phpstan-param SpawnComponent|string $component
	 * @phpstan-param ComponentParser $parser
	 * @phpstan-throws \InvalidArgumentException when the component is already registered
	 */
	public function register(SpawnComponent|string $component, \Closure $parser) : void{
		$component = self::normalize($component);
		if(isset($this->parsers[$component])){
			throw new \InvalidArgumentException("Spawn condition component \"$component\" is already registered");
		}
		$this->parsers[$component] = $parser;
	}

	/**
	 * @phpstan-param SpawnComponent|string $component
	 *
	 * @phpstan-return ComponentParser
	 * @phpstan-throws \InvalidArgumentException when the component is not registered
	 */
	public function unregister(SpawnComponent|string $component) : \Closure{
		$component = self::normalize($component);
		$parser = $this->parsers[$component] ?? throw new \InvalidArgumentException("Spawn condition component \"$component\" is not registered");
		unset($this->parsers[$component]);

		return $parser;
	}

	/**
	 * @phpstan-param SpawnComponent|string $component
	 *
	 * @phpstan-return ComponentParser|null
	 */
	public function get(SpawnComponent|string $component) : ?\Closure{
		return $this->parsers[self::normalize($component)] ?? null;
	}

	/**
	 * Strips the "minecraft:" prefix; enum cases are already unprefixed.
	 *
	 * @phpstan-param SpawnComponent|string $component
	 */
	public static function normalize(SpawnComponent|string $component) : string{
		if($component instanceof SpawnComponent){
			return $component->value;
		}

		return str_starts_with($component, "minecraft:") ? substr($component, strlen("minecraft:")) : $component;
	}
}
