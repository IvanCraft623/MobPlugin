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

use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use IvanCraft623\MobPlugin\spawning\condition\DensityLimitCondition;
use IvanCraft623\MobPlugin\spawning\condition\RangeCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnsOnBlock;
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
use IvanCraft623\MobPlugin\spawning\parse\schema\SpawnSchema;
use IvanCraft623\MobPlugin\spawning\parse\schema\VanillaSpawnConditions;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use pocketmine\utils\Filesystem;
use pocketmine\world\World;
use function count;
use function implode;
use function max;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * Strict: any value it cannot compile aborts the whole load.
 *
 * @phpstan-type ComponentParser \Closure(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void
 * @phpstan-type ParsedRules array<string, array{string, list<SpawnRuleGroup>}>
 */
final class SpawnRulesParser{
	private const PREFIX = "minecraft:";

	private const SPAWN_RULES_KEY = "minecraft:spawn_rules";

	/**
	 * Vanilla components with no implementation: groups using them never spawn.
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
	 * Recognized components with no runtime effect.
	 *
	 * @var list<string>
	 */
	private const PASS_THROUGH_VANILLA = [
		VanillaSpawnConditions::DISALLOW_SPAWNS_IN_BUBBLE, // PocketMine has no bubble-column blocks
		VanillaSpawnConditions::IS_PERSISTENT,
		VanillaSpawnConditions::IS_EXPERIMENTAL,
	];

	/**
	 * population_control values vanilla spawns through events (patrols/raids), not
	 * natural spawning; their rule sets are skipped.
	 *
	 * @var array<string, true>
	 */
	private const NON_NATURAL_POPULATION_CONTROL = ["pillager" => true, "pillager_patrol" => true];

	/** @phpstan-var array<string, ComponentParser> */
	private array $components = [];

	private readonly BlockNameResolver $blocks;

	public function __construct(
		private readonly BiomeTagMap $biomeTags
	){
		$this->blocks = new BlockNameResolver();
	}

	public static function createVanilla(?BiomeTagMap $biomeTags = null) : self{
		$parser = new self($biomeTags ?? BiomeTagMap::fromBedrockData());
		$parser->registerVanillaComponents();

		return $parser;
	}

	public function getBiomeTags() : BiomeTagMap{
		return $this->biomeTags;
	}

	/**
	 * @phpstan-param ComponentParser $parser
	 */
	public function registerComponent(string $component, \Closure $parser, bool $override = false) : void{
		$component = self::normalize($component);
		if(!$override && isset($this->components[$component])){
			throw new \InvalidArgumentException("Spawn rule component \"$component\" is already registered");
		}
		$this->components[$component] = $parser;
	}

	/**
	 * @phpstan-return ComponentParser|null
	 */
	public function getComponent(string $component) : ?\Closure{
		return $this->components[self::normalize($component)] ?? null;
	}

	/**
	 * @phpstan-return ParsedRules
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function parseFile(string $path) : array{
		return $this->parse(Filesystem::fileGetContents($path));
	}

	/**
	 * @phpstan-return ParsedRules
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function parse(string $json) : array{
		$document = SpawnData::fromJson($json);

		$entries = [];
		foreach($document->keys() as $identifier){
			if($identifier === ""){
				throw new SpawnRulesParseException("Spawn rules: invalid empty entry key");
			}
			try{
				$entry = $this->parseEntry($document->object($identifier), $identifier);
			}catch(\InvalidArgumentException $e){
				// Also covers condition constructors rejecting a value (e.g. an unknown difficulty).
				throw new SpawnRulesParseException("Spawn rules for \"$identifier\": " . $e->getMessage(), 0, $e);
			}
			if($entry !== null){
				$entries[$identifier] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * @phpstan-return array{string, list<SpawnRuleGroup>}|null
	 */
	private function parseEntry(SpawnData $body, string $identifier) : ?array{
		$spawnRules = $body->object(self::SPAWN_RULES_KEY);
		$description = $spawnRules->object(SpawnSchema::KEY_DESCRIPTION);
		$categoryId = $description->string(SpawnSchema::KEY_POPULATION_CONTROL);
		if($categoryId === ""){
			throw new SpawnRulesParseException("'{$description->at(SpawnSchema::KEY_POPULATION_CONTROL)}' must not be empty");
		}
		if(isset(self::NON_NATURAL_POPULATION_CONTROL[$categoryId])){
			return null;
		}

		// Mobs without natural spawns (e.g. blaze) have no conditions.
		$groups = [];
		if($spawnRules->has(SpawnSchema::KEY_CONDITIONS)){
			foreach($spawnRules->objectOrList(SpawnSchema::KEY_CONDITIONS) as $condition){
				$group = $this->parseGroup($condition, $identifier);
				if($group !== null){
					$groups[] = $group;
				}
			}
		}

		return [$categoryId, $groups];
	}

	private function parseGroup(SpawnData $condition, string $identifier) : ?SpawnRuleGroup{
		$normalized = [];
		foreach($condition->keys() as $key){
			$normalized[self::normalize($key)] = $condition->raw($key);
		}
		if(count($normalized) === 0){
			throw new SpawnRulesParseException("'{$condition->path}' has no spawn rule components");
		}
		$condition = new SpawnData($normalized, $condition->path);

		$builder = new SpawnRuleGroupBuilder($identifier);
		foreach($condition->keys() as $component){
			$parser = $this->components[$component] ?? throw new SpawnRulesParseException("'{$condition->at($component)}' is not a recognized spawn rule component");
			$parser(new ComponentParseContext($condition, $component, $this->blocks), $builder);
		}

		return $builder->build();
	}

	private function registerVanillaComponents() : void{
		// Each habitat marker allows its own band; the builder unions them.
		$this->registerComponent(VanillaSpawnConditions::SPAWNS_ON_SURFACE, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$builder->allowHabitatBand(SpawnBand::SURFACE);
		});
		$this->registerComponent(VanillaSpawnConditions::SPAWNS_UNDERGROUND, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$builder->allowHabitatBand(SpawnBand::CAVE);
		});
		$this->registerComponent(VanillaSpawnConditions::SPAWNS_UNDERWATER, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$builder->setLiquid(SpawnLiquid::WATER);
		});
		$this->registerComponent(VanillaSpawnConditions::SPAWNS_LAVA, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$builder->setLiquid(SpawnLiquid::LAVA);
		});
		$this->registerComponent(VanillaSpawnConditions::BRIGHTNESS_FILTER, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$m = $ctx->map(BrightnessFilterData::class);
			$builder->addCondition(RangeCondition::brightness($m->min ?? 0, $m->max ?? 15, $m->adjust_for_weather ?? false));
		});
		$this->registerComponent(VanillaSpawnConditions::DIFFICULTY_FILTER, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$m = $ctx->map(DifficultyFilterData::class);
			$builder->addCondition(RangeCondition::difficulty(self::parseDifficulty($m->min ?? SpawnSchema::DIFFICULTY_MIN), self::parseDifficulty($m->max ?? SpawnSchema::DIFFICULTY_MAX)));
		});
		$this->registerComponent(VanillaSpawnConditions::HEIGHT_FILTER, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$m = $ctx->map(HeightFilterData::class);
			$builder->addCondition(RangeCondition::height($m->min, $m->max));
		});
		$this->registerComponent(VanillaSpawnConditions::DISTANCE_FILTER, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$m = $ctx->map(DistanceFilterData::class);
			$builder->addCondition(RangeCondition::distance(
				$m->min === null ? null : (float) $m->min,
				$m->max === null ? null : (float) $m->max
			));
		});
		$this->registerComponent(VanillaSpawnConditions::WORLD_AGE_FILTER, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$m = $ctx->map(WorldAgeFilterData::class);
			$builder->addCondition(RangeCondition::worldAge($m->min, $m->max));
		});
		$this->registerComponent(VanillaSpawnConditions::SPAWNS_ON_BLOCK_FILTER, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$builder->addCondition(new SpawnsOnBlock($ctx->resolveBlockSet(), false));
		});
		$this->registerComponent(VanillaSpawnConditions::SPAWNS_ON_BLOCK_PREVENTED_FILTER, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$builder->addCondition(new SpawnsOnBlock($ctx->resolveBlockSet(), true));
		});
		$this->registerComponent(VanillaSpawnConditions::DENSITY_LIMIT, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$m = $ctx->map(DensityLimitData::class);
			$builder->addCondition(new DensityLimitCondition($builder->getIdentifier(), $m->surface, $m->underground));
		});
		$this->registerComponent(VanillaSpawnConditions::WEIGHT, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			// The vanilla "rarity" field is not consumed.
			$builder->setWeight($ctx->map(WeightData::class)->default);
		});
		$this->registerComponent(VanillaSpawnConditions::HERD, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			//TODO: a list holds one herd per spawn event (e.g. horse coat colours); only the first is used until spawn events are supported
			$data = $ctx->mapList(HerdData::class)[0];
			$min = max(1, $data->min_size ?? 1);
			$builder->setHerd($min, max($min, $data->max_size ?? $min));
		});
		$this->registerComponent(VanillaSpawnConditions::PERMUTE_TYPE, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$permutations = [];
			foreach($ctx->mapList(PermuteTypeData::class) as $data){
				// A missing entity_type keeps the base type; the "<event>" suffix is dropped.
				$identifier = $data->entity_type ?? $builder->getIdentifier();
				$suffixStart = strpos($identifier, "<");
				if($suffixStart !== false){
					$identifier = substr($identifier, 0, $suffixStart);
				}
				$permutations[$identifier] = ($permutations[$identifier] ?? 0) + $data->weight;
			}
			$builder->setPermutations($permutations);
		});
		$this->registerComponent(VanillaSpawnConditions::SPAWN_EVENT, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
			$ctx->map(MobEventFilterData::class); // validated only: nothing consumes spawn events
		});
		$biomeFilter = new BiomeFilterParser($this->biomeTags);
		$this->registerComponent(VanillaSpawnConditions::BIOME_FILTER, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) use ($biomeFilter) : void{
			$builder->addCondition($biomeFilter->parse($ctx));
		});

		foreach(self::UNSUPPORTED_VANILLA as $component){
			$this->registerComponent($component, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
				$builder->markNeverSpawns();
			});
		}
		foreach(self::PASS_THROUGH_VANILLA as $component){
			$this->registerComponent($component, static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{});
		}
	}

	private static function parseDifficulty(string $name) : int{
		$difficulty = World::getDifficultyFromString($name);
		if($difficulty === -1){
			throw new SpawnRulesParseException("unknown difficulty \"$name\"; names declared by the schema: " . implode(", ", SpawnSchema::DIFFICULTY_CASES));
		}

		return $difficulty;
	}

	private static function normalize(string $component) : string{
		return str_starts_with($component, self::PREFIX) ? substr($component, strlen(self::PREFIX)) : $component;
	}
}
