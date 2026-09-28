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
use IvanCraft623\MobPlugin\spawning\parse\schema\model\BrightnessFilterData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\DensityLimitData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\DifficultyFilterData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\DistanceFilterData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\HeightFilterData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\HerdData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\MobEventFilterData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\PermuteTypeData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\WeightData;
use IvanCraft623\MobPlugin\spawning\parse\schema\model\WorldAgeFilterData;
use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaSpawnConditions;
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
 * Maps spawn-rule condition names to parser closures.
 *
 * @phpstan-type ConditionParser \Closure(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void
 */
final class SpawnConditionRegistry{
	use SingletonTrait;

	/**
	 * Vanilla components with no implementation, registered to fail closed. Replaceable.
	 *
	 * @var list<string>
	 */
	public const UNSUPPORTED_VANILLA = [
		VanillaSpawnConditions::MOB_EVENT_FILTER,
		VanillaSpawnConditions::DELAY_FILTER,
		VanillaSpawnConditions::PLAYER_IN_VILLAGE_FILTER,
		VanillaSpawnConditions::SPAWNS_ABOVE_BLOCK_FILTER,
	];

	/**
	 * Recognized components with no runtime effect, registered as pass-through. Replaceable.
	 *
	 * @var list<string>
	 */
	public const PASS_THROUGH_VANILLA = [
		VanillaSpawnConditions::DISALLOW_SPAWNS_IN_BUBBLE, // PocketMine has no bubble-column blocks
		VanillaSpawnConditions::IS_PERSISTENT,
		VanillaSpawnConditions::IS_EXPERIMENTAL,
	];

	/** @phpstan-var array<string, ConditionParser> */
	private array $parsers = [];

	private function __construct(){
		$this->registerDefaultVanilla();
	}

	/** Registers the parser for every vanilla spawn-rule component. */
	private function registerDefaultVanilla() : void{
		// Marker parsers each accept their own band; the builder unions them.
		$this->register(VanillaSpawnConditions::SPAWNS_ON_SURFACE, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$builder->allowHabitatBand(SpawnBand::SURFACE);
		});
		$this->register(VanillaSpawnConditions::SPAWNS_UNDERGROUND, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$builder->allowHabitatBand(SpawnBand::CAVE);
		});
		$this->register(VanillaSpawnConditions::SPAWNS_UNDERWATER, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$builder->addCondition(new SpawnsInLiquid(BlockTypeIds::WATER));
		});
		$this->register(VanillaSpawnConditions::SPAWNS_LAVA, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$builder->addCondition(new SpawnsInLiquid(BlockTypeIds::LAVA));
		});
		$this->register(VanillaSpawnConditions::BRIGHTNESS_FILTER, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$m = $ctx->map(BrightnessFilterData::class);

			$builder->addCondition(new BrightnessFilter($m->min ?? 0, $m->max ?? 15, $m->adjust_for_weather ?? false));
		});
		$this->register(VanillaSpawnConditions::DIFFICULTY_FILTER, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$m = $ctx->map(DifficultyFilterData::class);

			$builder->addCondition(DifficultyFilter::fromNames($m->min, $m->max));
		});
		$this->register(VanillaSpawnConditions::HEIGHT_FILTER, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$m = $ctx->map(HeightFilterData::class);

			$builder->addCondition(new HeightFilter($m->min, $m->max));
		});
		$this->register(VanillaSpawnConditions::DISTANCE_FILTER, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$m = $ctx->map(DistanceFilterData::class);

			// Schema types distance min/max as integers; the condition model is float.
			$builder->addCondition(new DistanceFilter(
				$m->min === null ? null : (float) $m->min,
				$m->max === null ? null : (float) $m->max
			));
		});
		$this->register(VanillaSpawnConditions::WORLD_AGE_FILTER, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$m = $ctx->map(WorldAgeFilterData::class);

			$builder->addCondition(new WorldAgeFilter($m->min, $m->max));
		});
		$this->register(VanillaSpawnConditions::SPAWNS_ON_BLOCK_FILTER, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$builder->addCondition(new SpawnsOnBlock($ctx->resolveBlockSet(), false));
		});
		$this->register(VanillaSpawnConditions::SPAWNS_ON_BLOCK_PREVENTED_FILTER, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$builder->addCondition(new SpawnsOnBlock($ctx->resolveBlockSet(), true));
		});
		$this->register(VanillaSpawnConditions::DENSITY_LIMIT, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$m = $ctx->map(DensityLimitData::class);

			$builder->addCondition(new DensityLimitCondition($builder->getIdentifier(), $m->surface, $m->underground));
		});
		$this->register(VanillaSpawnConditions::WEIGHT, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$m = $ctx->map(WeightData::class);

			// The vanilla "rarity" field is not consumed (documented approximation).
			$builder->setWeight($m->default);
		});
		$this->register(VanillaSpawnConditions::HERD, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$herds = $ctx->mapList(HerdData::class);
			$data = $herds[0];

			$min = max(1, $data->min_size ?? 1);
			$max = max($min, $data->max_size ?? $min);
			$builder->setHerd(new Herd($min, $max));
		});
		$this->register(VanillaSpawnConditions::PERMUTE_TYPE, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$entries = $ctx->mapList(PermuteTypeData::class);

			$permuteTypes = [];
			foreach($entries as $data){
				$permuteTypes[] = new PermuteType($data->weight, $data->entity_type);
			}
			$builder->setPermuteTypes($permuteTypes);
		});
		$this->register(VanillaSpawnConditions::SPAWN_EVENT, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$m = $ctx->map(MobEventFilterData::class);

			$builder->setEvent(new SpawnEvent($m->event));
		});
		$this->register(VanillaSpawnConditions::BIOME_FILTER, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{
			$builder->addCondition((new BiomeFilterParser($ctx->biomeTags()))->parse($ctx));
		});

		foreach(self::UNSUPPORTED_VANILLA as $component){
			$this->register($component, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{

				$builder->addCondition(NeverSpawnCondition::instance());
			});
		}
		foreach(self::PASS_THROUGH_VANILLA as $component){
			$this->register($component, static function(SpawnConditionContext $ctx, SpawnGroupBuilder $builder) : void{

				$builder->addCondition(PassThroughSpawnCondition::instance());
			});
		}
	}

	/**
	 * @phpstan-param string $component
	 * @phpstan-param ConditionParser $parser
	 * @phpstan-throws \InvalidArgumentException when the component is already registered
	 */
	public function register(string $component, \Closure $parser) : void{
		$component = self::normalize($component);
		if(isset($this->parsers[$component])){
			throw new \InvalidArgumentException("Spawn condition component \"$component\" is already registered");
		}
		$this->parsers[$component] = $parser;
	}

	/**
	 * @phpstan-param string $component
	 *
	 * @phpstan-return ConditionParser
	 * @phpstan-throws \InvalidArgumentException when the component is not registered
	 */
	public function unregister(string $component) : \Closure{
		$component = self::normalize($component);
		$parser = $this->parsers[$component] ?? throw new \InvalidArgumentException("Spawn condition component \"$component\" is not registered");
		unset($this->parsers[$component]);

		return $parser;
	}

	/**
	 * @phpstan-param string $component
	 *
	 * @phpstan-return ConditionParser|null
	 */
	public function get(string $component) : ?\Closure{
		return $this->parsers[self::normalize($component)] ?? null;
	}

	/**
	 * Strips the "minecraft:" prefix; constants are already unprefixed.
	 *
	 * @phpstan-param string $component
	 */
	public static function normalize(string $component) : string{
		return str_starts_with($component, "minecraft:") ? substr($component, strlen("minecraft:")) : $component;
	}
}
