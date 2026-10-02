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

namespace IvanCraft623\MobPlugin\spawning;

use IvanCraft623\MobPlugin\entity\monster\Monster;
use IvanCraft623\MobPlugin\entity\monster\Slime;
use IvanCraft623\MobPlugin\MobPlugin;
use IvanCraft623\MobPlugin\spawning\condition\AllOf;
use IvanCraft623\MobPlugin\spawning\condition\AnyOf;
use IvanCraft623\MobPlugin\spawning\condition\BiomeTagCondition;
use IvanCraft623\MobPlugin\spawning\condition\HeightCondition;
use IvanCraft623\MobPlugin\spawning\condition\LightChanceCondition;
use IvanCraft623\MobPlugin\spawning\condition\MoonPhaseChanceCondition;
use IvanCraft623\MobPlugin\spawning\condition\RangeCondition;
use IvanCraft623\MobPlugin\spawning\condition\SlimeChunkCondition;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesParser;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\Squid;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginException;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\World;
use function array_map;
use function is_a;

/**
 * @phpstan-import-type SpawnFactory from SpawnRules
 */
final class SpawnRuleRegistry{
	use SingletonTrait;

	/** @phpstan-var array<string, SpawnRules> */
	private array $rules = [];

	private int $revision = 0;

	/**
	 * @phpstan-throws PluginException
	 */
	public function register(SpawnRules $rules, bool $override = false) : void{
		$identifier = $rules->getIdentifier();
		if(!$override && isset($this->rules[$identifier])){
			throw new PluginException("Spawn rules for \"$identifier\" are already registered");
		}
		$categoryId = $rules->getCategoryId();
		if(!MobCategoryRegistry::getInstance()->has($categoryId)){
			throw new PluginException("Spawn rules for \"$identifier\": unknown mob category \"$categoryId\"");
		}
		$this->rules[$identifier] = $rules;
		$this->revision++;
	}

	public function registerVanilla(string $spawnRulesPath) : void{
		$parser = SpawnRulesParser::createVanilla();
		$parsed = $parser->parseFile($spawnRulesPath);

		$entityClasses = MobPlugin::ALL_ENTITIES;
		$entityClasses[] = Squid::class; // implemented by PocketMine-MP
		foreach($entityClasses as $entityClass){
			$identifier = $entityClass::getNetworkTypeId();
			if(!isset($parsed[$identifier])){
				continue; // no natural spawns (constructed mobs, bosses)
			}
			[$categoryId, $groups] = $parsed[$identifier];

			// WORKAROUND: vanilla hardcodes these rules in the engine instead of the rules file.
			$hardcoded = [];
			if($entityClass === Slime::class){
				$hardcoded[] = new AnyOf([
					new AllOf([
						new HeightCondition(null, 38),
						new SlimeChunkCondition(),
					]),
					new AllOf([
						new HeightCondition(50, 68),
						new BiomeTagCondition($parser->getBiomeTags(), "spawns_slimes_on_surface"),
						new LightChanceCondition(8, inverted: true),
						new MoonPhaseChanceCondition(),
					]),
				]);
			}
			if(is_a($entityClass, Monster::class, true)){
				//TODO: the Nether has its own rule, and thunderstorms darken the sky
				$hardcoded[] = RangeCondition::blockLight(0, 0);
			}
			$groups = array_map(static fn(SpawnRuleGroup $group) : SpawnRuleGroup => $group->withConditions($hardcoded), $groups);

			$this->register(new SpawnRules($identifier, $categoryId, $groups, self::createFactory($entityClass)));
		}
	}

	/**
	 * @phpstan-param class-string<Entity> $entityClass
	 * @phpstan-return SpawnFactory
	 */
	private static function createFactory(string $entityClass) : \Closure{
		return static fn(World $world, Vector3 $pos, SpawnRuleGroup $group) : Entity => new $entityClass(Location::fromObject($pos, $world, Utils::getRandomFloat() * 360.0));
	}

	public function unregister(string $identifier) : void{
		if(isset($this->rules[$identifier])){
			unset($this->rules[$identifier]);
			$this->revision++;
		}
	}

	public function get(string $identifier) : ?SpawnRules{
		return $this->rules[$identifier] ?? null;
	}

	/**
	 * @phpstan-return array<string, SpawnRules>
	 */
	public function getAll() : array{
		return $this->rules;
	}

	/**
	 * Drops every cached rule evaluation. Call it when outside state read by cacheable
	 * conditions changes.
	 */
	public function invalidateCache() : void{
		$this->revision++;
	}

	public function getRevision() : int{
		return $this->revision;
	}
}
