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

use IvanCraft623\MobPlugin\entity\monster\Slime;
use IvanCraft623\MobPlugin\MobPlugin;
use IvanCraft623\MobPlugin\spawning\condition\AllOf;
use IvanCraft623\MobPlugin\spawning\condition\AnyOf;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\BiomeTagCondition;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\HeightFilter;
use IvanCraft623\MobPlugin\spawning\condition\vanilla\IsSlimeChunkCondition;
use IvanCraft623\MobPlugin\spawning\parse\resolver\BiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesFactory;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\Squid;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginException;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\World;
use Symfony\Component\Filesystem\Path;
use function array_keys;

/**
 * Registry binding rule sets (SpawnRules) to the factories that materialize their
 * matches.
 *
 * @phpstan-type SpawnFactory \Closure(World $world, Vector3 $pos, SpawnConditionMatch $match): Entity
 */
final class SpawnRuleRegistry{
	use SingletonTrait;

	/** @var array<string, SpawnRuleBinding> */
	private array $entries = [];

	private int $revision = 0;

	private function __construct(){
		$this->registerVanillaMobs();
	}

	private function registerVanillaMobs() : void{
		$factory = SpawnRulesFactory::createDefault();
		$path = Path::join(MobPlugin::getInstance()->getResourceFolder(), "spawning", "spawn_rules.json");
		$rules = $factory->loadFile($path); // strict: any uncompilable value aborts startup

		foreach(MobPlugin::ALL_ENTITIES as $entityClass){
			$identifier = $entityClass::getNetworkTypeId();
			$entityRules = $rules[$identifier] ?? null;

			if($entityRules === null){
				continue; // implemented mob without vanilla spawn rules (constructed mobs, bosses)
			}

			if($identifier === Slime::getNetworkTypeId()){
				$entityRules = self::applySlimeChunkRule($entityRules, $factory->getBiomeTags());
			}

			$this->register($entityRules, self::createFactory($entityClass));
		}

		//Squids are implemented on raw PMMP...
		$squidRules = $rules[Squid::getNetworkTypeId()] ?? throw new \RuntimeException("Squid spawn rules not found");
		$this->register($squidRules, self::createFactory(Squid::class));
	}

	/**
	 * WORKAROUND: These spawn conditions are hardcoded in the vanilla game engine
	 * and are not present in standard data-driven spawn rules files.
	 */
	private static function applySlimeChunkRule(SpawnRules $rules, BiomeTagResolver $tags) : SpawnRules{
		$groups = [];
		foreach($rules->getGroups() as $group){
			$groups[] = $group->withConditions([new AnyOf([
				new AllOf([
					new HeightFilter(null, 40),
					new IsSlimeChunkCondition(),
				]),
				new BiomeTagCondition($tags, ["spawns_slimes_on_surface"], []),
			])]);
		}

		return new SpawnRules($rules->getIdentifier(), $rules->getCategoryId(), $groups);
	}

	/**
	 * Factories construct but never spawn — the applier calls spawnToAll() after herd
	 * positioning.
	 *
	 * @phpstan-param class-string<Entity> $entityClass
	 *
	 * @phpstan-return SpawnFactory
	 */
	private static function createFactory(string $entityClass) : \Closure{
		return static function(World $world, Vector3 $pos, SpawnConditionMatch $match) use ($entityClass) : Entity{
			return new $entityClass(Location::fromObject($pos, $world, Utils::getRandomFloat() * 360.0));
		};
	}

	/**
	 * Registers a rule set bound to a factory. The rule set's category id is resolved
	 * once here (MobCategoryRegistry); the binding's category is the one the whole
	 * pipeline uses from then on.
	 *
	 * @phpstan-param SpawnFactory $factory
	 *
	 * @phpstan-throws PluginException
	 */
	public function register(SpawnRules $rules, \Closure $factory, bool $override = false) : void{
		$identifier = $rules->getIdentifier();
		$mobCategoryId = $rules->getCategoryId();
		if(!$override && isset($this->entries[$identifier])){
			throw new PluginException("Spawn rules for \"$identifier\" are already registered.");
		}
		$category = MobCategoryRegistry::getInstance()->get($mobCategoryId);
		if($category === null){
			throw new PluginException("Spawn rules for \"$identifier\": unknown mob category \"$mobCategoryId\".");
		}
		$this->entries[$identifier] = new SpawnRuleBinding($rules, $factory, $category);
		$this->revision++;
	}

	public function unregister(string $identifier) : void{
		if(isset($this->entries[$identifier])){
			unset($this->entries[$identifier]);
			$this->revision++;
		}
	}

	public function get(string $identifier) : ?SpawnRuleBinding{
		return $this->entries[$identifier] ?? null;
	}

	/**
	 * @phpstan-return array<string, SpawnRuleBinding>
	 */
	public function getSpawnEntries() : array{
		return $this->entries;
	}

	/**
	 * @phpstan-return list<string>
	 */
	public function getIdentifiers() : array{
		return array_keys($this->entries);
	}

	/** Bumped on every mutation so cache consumers know when to rebuild. */
	public function getRevision() : int{
		return $this->revision;
	}
}
