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

use IvanCraft623\MobPlugin\libs\_24f1b309f55b1ceb\bStats\PocketmineMp\charts\DrilldownPie;
use IvanCraft623\MobPlugin\libs\_24f1b309f55b1ceb\bStats\PocketmineMp\charts\SingleLineChart;
use IvanCraft623\MobPlugin\libs\_24f1b309f55b1ceb\bStats\PocketmineMp\Metrics;

use IvanCraft623\MobPlugin\despawning\DespawnListener;
use IvanCraft623\MobPlugin\despawning\DespawnRuleRegistry;
use IvanCraft623\MobPlugin\despawning\NaturalDespawnTask;
use IvanCraft623\MobPlugin\despawning\WorldDespawnPass;
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
use IvanCraft623\MobPlugin\entity\Mob;
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
use IvanCraft623\MobPlugin\spawning\NaturalSpawnerTask;
use IvanCraft623\MobPlugin\spawning\parse\SpawnRulesParseException;
use IvanCraft623\MobPlugin\spawning\SpawnRuleRegistry;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\entity\AttributeFactory;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper as Helper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\DisablePluginException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Random;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;
use Symfony\Component\Filesystem\Path;

use IvanCraft623\MobPlugin\libs\_24f1b309f55b1ceb\xenialdan\apibossbar\API as BossBarAPI;

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

	public function onLoad() : void {
		self::setInstance($this);
	}

	public function onEnable() : void {
		Settings::init();
		CustomTimings::init();

		$this->registerSpawnRules();
		$this->registerAttributes();
		$this->registerEntities();
		$this->registerMetrics();
		$this->registerNaturalSpawning();
		$this->registerNaturalDespawning();

		ExtraItemRegisterHelper::init();

		BossBarAPI::load($this);

		$this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
		$this->getServer()->getPluginManager()->registerEvents(new DespawnListener(DespawnRuleRegistry::getInstance()), $this);
	}

	/**
	 * Runs before anything else is registered, so a failure leaves nothing half set up.
	 */
	private function registerSpawnRules() : void{
		$path = Path::join($this->getResourceFolder(), "spawning", "spawn_rules.json");
		try{
			$warnings = SpawnRuleRegistry::getInstance()->registerVanilla($path);
		}catch(SpawnRulesParseException $e){
			$this->getLogger()->critical("Could not load the spawn rules from $path: " . $e->getMessage());
			throw new DisablePluginException();
		}
		foreach($warnings as $warning){
			$this->getLogger()->warning($warning);
		}
	}

	private function registerNaturalSpawning() : void{
		$settings = Settings::getGlobalSettings();

		$this->getScheduler()->scheduleRepeatingTask(new NaturalSpawnerTask(
			SpawnRuleRegistry::getInstance(),
			$settings->getMobNaturalSpawningMaxAttemptsPerTick(),
			$this->getServer()->getWorldManager()
		), 1);
	}

	private function registerNaturalDespawning() : void{
		$this->getScheduler()->scheduleRepeatingTask(new NaturalDespawnTask(DespawnRuleRegistry::getInstance(), $this->getServer()), WorldDespawnPass::CHECK_INTERVAL);
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

	public function trackEntity(Mob $mob) : void {
		$this->totalEntitiesCount++;
		$categoryName = SpawnRuleRegistry::getInstance()->get($mob::getNetworkTypeId())?->getCategoryId() ?? "unknown";
		$mobName = strtolower($mob->getName());
		$this->entitiesStats[$categoryName][$mobName] =
			($this->entitiesStats[$categoryName][$mobName] ?? 0) + 1
		;
	}

	public function untrackEntity(Mob $mob) : void {
		$categoryName = SpawnRuleRegistry::getInstance()->get($mob::getNetworkTypeId())?->getCategoryId() ?? "unknown";
		$mobName = strtolower($mob->getName());
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