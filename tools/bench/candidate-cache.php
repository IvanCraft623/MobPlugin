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

/**
 * Benchmarks CandidateCache against uncached SpawnRules evaluation on the vanilla rules.
 * Not run in CI: timings on shared runners are noise.
 *
 * Usage: php tools/bench/candidate-cache.php [positions]
 */

namespace IvanCraft623\MobPlugin\tools\bench;

use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesParser;
use IvanCraft623\MobPlugin\spawning\SpawnBand;
use IvanCraft623\MobPlugin\spawning\spawner\CandidateCache;
use IvanCraft623\MobPlugin\spawning\spawner\RegionPopulation;
use IvanCraft623\MobPlugin\spawning\SpawnLiquid;
use IvanCraft623\MobPlugin\spawning\SpawnRuleGroup;
use IvanCraft623\MobPlugin\spawning\SpawnRules;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\utils\Random;
use function count;
use function dirname;
use function gc_collect_cycles;
use function hrtime;
use function max;
use function memory_get_usage;
use function printf;

require dirname(__DIR__, 2) . "/vendor/autoload.php";

final class BenchContext implements SpawnConditionContext{
	public function __construct(
		private readonly int $biomeId,
		private readonly SpawnBand $band,
		private readonly int $difficulty,
		private readonly SpawnLiquid $feetLiquid,
		private readonly int $x,
		private readonly int $y,
		private readonly int $z,
		private readonly int $light,
		private readonly int $belowTypeId,
		private readonly float $nearestPlayerDistance,
		private readonly int $time,
		private readonly RegionPopulation $population
	){}

	public function getBiomeId() : int{ return $this->biomeId; }

	public function getBand() : SpawnBand{ return $this->band; }

	public function getDifficulty() : int{ return $this->difficulty; }

	public function getFeetLiquid() : SpawnLiquid{ return $this->feetLiquid; }

	public function getX() : int{ return $this->x; }

	public function getY() : int{ return $this->y; }

	public function getZ() : int{ return $this->z; }

	public function getGroundY() : int{ return $this->y - 1; }

	public function getLight() : int{ return $this->light; }

	public function getWeatherLightPenalty() : int{ return 0; }

	public function getBelowTypeId() : int{ return $this->belowTypeId; }

	public function getNearestPlayerDistance() : float{ return $this->nearestPlayerDistance; }

	public function getTime() : int{ return $this->time; }

	public function getPopulation() : RegionPopulation{ return $this->population; }
}

function uncached(SpawnRules $rules, SpawnConditionContext $ctx) : ?SpawnRuleGroup{
	$liquid = $ctx->getFeetLiquid();
	foreach($rules->getGroups() as $group){
		if(($liquid === SpawnLiquid::NONE || $group->getRequiredLiquid() === $liquid) && $group->matches($ctx)){
			return $group;
		}
	}

	return null;
}

$root = dirname(__DIR__, 2);
$positions = max(1, (int) ($argv[1] ?? 20000));
$tags = BiomeTagMap::fromFiles($root . "/vendor/pocketmine/bedrock-data/biome_id_map.json", $root . "/vendor/pocketmine/bedrock-data/biome_definitions.json");
$rules = [];
foreach(SpawnRulesParser::createVanilla($tags)->parseFile($root . "/resources/spawning/spawn_rules.json") as $identifier => [$categoryId, $groups]){
	$rules[] = new SpawnRules($identifier, $categoryId, $groups, static fn() : Entity => throw new \LogicException());
}

$biomeIds = [];
for($id = 0; $id < 400; $id++){
	if($tags->getTags($id) !== []){
		$biomeIds[] = $id;
	}
}
$below = [BlockTypeIds::GRASS, BlockTypeIds::SAND, BlockTypeIds::STONE, BlockTypeIds::PODZOL, BlockTypeIds::SNOW, BlockTypeIds::NETHERRACK, BlockTypeIds::DEEPSLATE];
$random = new Random(42);
$population = new RegionPopulation();
$contexts = [];
for($i = 0; $i < $positions; $i++){
	$liquidRoll = $random->nextBoundedInt(10);
	$contexts[] = new BenchContext(
		$biomeIds[$random->nextBoundedInt(count($biomeIds))],
		$random->nextBoundedInt(3) === 0 ? SpawnBand::CAVE : SpawnBand::SURFACE,
		$random->nextRange(1, 3),
		$liquidRoll === 0 ? SpawnLiquid::WATER : ($liquidRoll === 1 ? SpawnLiquid::LAVA : SpawnLiquid::NONE),
		$random->nextRange(-3000, 3000),
		$random->nextRange(-60, 120),
		$random->nextRange(-3000, 3000),
		$random->nextBoundedInt(16),
		$below[$random->nextBoundedInt(count($below))],
		24 + $random->nextFloat() * 104,
		$random->nextBoundedInt(1000000),
		$population
	);
}

$start = hrtime(true);
$uncachedMatches = 0;
foreach($contexts as $ctx){
	foreach($rules as $r){
		if(uncached($r, $ctx) !== null){
			$uncachedMatches++;
		}
	}
}
$uncachedNs = hrtime(true) - $start;

$cache = new CandidateCache($rules);
foreach($contexts as $ctx){
	$cache->getCandidates($ctx);
}
$start = hrtime(true);
$cachedMatches = 0;
foreach($contexts as $ctx){
	foreach($cache->getCandidates($ctx) as $candidate){
		if($candidate->match($ctx) !== null){
			$cachedMatches++;
		}
	}
}
$cachedNs = hrtime(true) - $start;

$keys = [];
foreach($biomeIds as $biomeId){
	foreach(SpawnBand::cases() as $band){
		for($difficulty = 0; $difficulty <= 3; $difficulty++){
			foreach(SpawnLiquid::cases() as $liquid){
				$keys[] = new BenchContext($biomeId, $band, $difficulty, $liquid, 0, 64, 0, 15, BlockTypeIds::GRASS, 30.0, 0, $population);
			}
		}
	}
}
gc_collect_cycles();
$memoryBefore = memory_get_usage();
$full = new CandidateCache($rules, count($keys));
$start = hrtime(true);
foreach($keys as $ctx){
	$full->getCandidates($ctx);
}
$fillNs = hrtime(true) - $start;
gc_collect_cycles();
$memoryAfter = memory_get_usage();

printf("rules: %d, positions: %d, matches: %d uncached / %d cached%s\n", count($rules), $positions, $uncachedMatches, $cachedMatches, $uncachedMatches === $cachedMatches ? "" : "  MISMATCH");
printf("uncached SpawnRules evaluation: %6.2f µs/position\n", $uncachedNs / 1000 / $positions);
printf("CandidateCache (warm):          %6.2f µs/position   (accept <= 5)\n", $cachedNs / 1000 / $positions);
printf("full vanilla key space:         %d keys, %.0f KB   (accept <= 1024)\n", $full->getSize(), ($memoryAfter - $memoryBefore) / 1024);
printf("cold resolve:                   %6.1f µs/key   (accept <= 500)\n", $fillNs / 1000 / count($keys));
