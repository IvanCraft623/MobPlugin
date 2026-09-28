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

namespace IvanCraft623\MobPlugin;

use bStats\PocketmineMp\charts\DrilldownPie;
use bStats\PocketmineMp\charts\SingleLineChart;
use bStats\PocketmineMp\Metrics;

use IvanCraft623\MobPlugin\entity\ambient\Bat;
use IvanCraft623\MobPlugin\entity\animal\Chicken;
use IvanCraft623\MobPlugin\entity\animal\Cow;
use IvanCraft623\MobPlugin\entity\animal\MooshroomCow;
use IvanCraft623\MobPlugin\entity\animal\Pig;
use IvanCraft623\MobPlugin\entity\animal\Sheep;
use IvanCraft623\MobPlugin\entity\boss\Wither;
use IvanCraft623\MobPlugin\entity\CustomAttributes;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\golem\SnowGolem;
use IvanCraft623\MobPlugin\entity\monster\CaveSpider;
use IvanCraft623\MobPlugin\entity\monster\Creeper;
use IvanCraft623\MobPlugin\entity\monster\Enderman;
use IvanCraft623\MobPlugin\entity\monster\Endermite;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Skeleton;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Stray;
use IvanCraft623\MobPlugin\entity\monster\skeleton\WitherSkeleton;
use IvanCraft623\MobPlugin\entity\monster\Slime;
use IvanCraft623\MobPlugin\entity\monster\Spider;
use IvanCraft623\MobPlugin\entity\monster\Zombie;
use IvanCraft623\MobPlugin\item\ExtraItemRegisterHelper;
use IvanCraft623\MobPlugin\spawning\MobCategory;
use IvanCraft623\MobPlugin\spawning\NaturalSpawner;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\AttributeFactory;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper as Helper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\Random;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

use xenialdan\apibossbar\API as BossBarAPI;

use function count;
use function mt_rand;
use function strtolower;

class MobPlugin extends PluginBase {
	use SingletonTrait;

	private const BSTATS_PLUGIN_ID = 32915;

	public const ALL_ENTITIES = [
		Bat::class,
		Chicken::class,
		Cow::class,
		MooshroomCow::class,
		Pig::class,
		Sheep::class,
		IronGolem::class,
		SnowGolem::class,
		CaveSpider::class,
		Creeper::class,
		Enderman::class,
		Endermite::class,
		Slime::class,
		Spider::class,
		Zombie::class,
		Skeleton::class,
		Stray::class,
		WitherSkeleton::class,
		Wither::class
	];

	private ?Random $random = null;

	private int $totalEntitiesCount = 0;

	/** @var array<string, array<string, int>> */
	private array $entitiesStats = [];

	private ?NaturalSpawner $naturalSpawner = null;

	private SpawnRuleRegistry $spawnRuleRegistry;

	/** @var TaskHandler<ClosureTask>|null */
	private ?TaskHandler $spawningTaskHandler = null;
	public function onLoad() : void {
		self::setInstance($this);
	}

	public function onEnable() : void {
		Settings::init();
		CustomTimings::init();

		$this->registerAttributes();
		$this->registerEntities();
		$this->registerMetrics();
		$this->registerNaturalSpawning();

		ExtraItemRegisterHelper::init();

		BossBarAPI::load($this);

		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
	}

	public function onDisable() : void {
		$this->spawningTaskHandler?->cancel();
	}

	private function registerNaturalSpawning() : void{
		$settings = Settings::getGlobalSettings();

		// The registry is the plugin's public spawning API: it is always built, whether
		// or not the built-in natural spawner is enabled — third-party plugins may
		// register their own rules or drive their own spawners against it.
		$this->spawnRuleRegistry = SpawnRuleRegistry::getInstance();

		if (!$settings->isMobNaturalSpawningEnabled()) {
			return;
		}

		$this->naturalSpawner = new NaturalSpawner(
			$this->spawnRuleRegistry,
			$settings->getMobNaturalSpawningAttemptsPerTick(),
			$this->getServer()->getWorldManager()
		);
		$this->spawningTaskHandler = $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
			fn() => $this->naturalSpawner->tick()
		), 1);
	}

	/**
	 * Registry of natural spawn rules bound to entity factories; third-party plugins can
	 * register their own rules or replace vanilla ones (before or after enable).
	 */
	public function getSpawnRuleRegistry() : SpawnRuleRegistry{
		return $this->spawnRuleRegistry;
	}

	public function getRandom() : Random {
		if ($this->random === null) {
			$this->random = new Random(mt_rand());
		}
		return $this->random;
	}

	private function registerAttributes() : void{
		$factory = AttributeFactory::getInstance();

		$factory->register(CustomAttributes::ATTACK_KNOCKBACK, 0.00, 340282346638528859811704183484516925440.00, 0.4, false);
		$factory->register(CustomAttributes::AIR_MOVEMENT, 0.00, 340282346638528859811704183484516925440.00, 0.02, false);
	}

	private function registerEntities() : void{
		$factory = EntityFactory::getInstance();

		foreach (self::ALL_ENTITIES as $entityClass) {
			$this->registerEntity($factory, $entityClass);
		}
	}

	/**
	 * @phpstan-param class-string<Entity> $entityClass
	 */
	private function registerEntity(EntityFactory $factory, string $entityClass) : void{
		//Did you know that bedrock entity's save ids are the same as network ids?
		$entityId = $entityClass::getNetworkTypeId();

		$factory->register($entityClass, function(World $world, CompoundTag $nbt) use ($entityClass) : Entity{
			return new $entityClass(Helper::parseLocation($nbt, $world), $nbt);
		}, [$entityId, Utils::getEntityNameFromId($entityId)]);
	}

	public function trackEntity(MobCategory $category, string $name) : void {
		$this->totalEntitiesCount++;
		$categoryName = strtolower($category->id);
		$mobName = strtolower($name);
		$this->entitiesStats[$categoryName][$mobName] =
			($this->entitiesStats[$categoryName][$mobName] ?? 0) + 1
		;
	}

	public function untrackEntity(MobCategory $category, string $name) : void {
		$categoryName = strtolower($category->id);
		$mobName = strtolower($name);
		if (isset($this->entitiesStats[$categoryName][$mobName])) {
			$this->totalEntitiesCount--;
			if (--$this->entitiesStats[$categoryName][$mobName] <= 0) {
				unset($this->entitiesStats[$categoryName][$mobName]);
			}

			if (count($this->entitiesStats[$categoryName]) <= 0) {
				unset($this->entitiesStats[$categoryName]);
			}
		}
	}

	private function registerMetrics() : void {
		$metrics = new Metrics($this, self::BSTATS_PLUGIN_ID);

		$metrics->addCustomChart(new SingleLineChart("mobs_alive", function() : int {
			return $this->totalEntitiesCount;
		}));

		$metrics->addCustomChart(new DrilldownPie("mobs_alive_category", function() : array {
			return $this->entitiesStats;
		}));
	}
}
