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

namespace IvanCraft623\MobPlugin\despawning;

use IvanCraft623\MobPlugin\entity\ambient\Bat;
use IvanCraft623\MobPlugin\entity\animal\Axolotl;
use IvanCraft623\MobPlugin\entity\animal\Chicken;
use IvanCraft623\MobPlugin\entity\animal\Cow;
use IvanCraft623\MobPlugin\entity\animal\MooshroomCow;
use IvanCraft623\MobPlugin\entity\animal\Pig;
use IvanCraft623\MobPlugin\entity\animal\PolarBear;
use IvanCraft623\MobPlugin\entity\animal\Rabbit;
use IvanCraft623\MobPlugin\entity\animal\Sheep;
use IvanCraft623\MobPlugin\entity\animal\Wolf;
use IvanCraft623\MobPlugin\entity\aquatic\Cod;
use IvanCraft623\MobPlugin\entity\aquatic\GlowSquid;
use IvanCraft623\MobPlugin\entity\aquatic\Pufferfish;
use IvanCraft623\MobPlugin\entity\aquatic\Salmon;
use IvanCraft623\MobPlugin\entity\aquatic\TropicalFish;
use IvanCraft623\MobPlugin\entity\boss\Wither;
use IvanCraft623\MobPlugin\entity\monster\CaveSpider;
use IvanCraft623\MobPlugin\entity\monster\Creeper;
use IvanCraft623\MobPlugin\entity\monster\Drowned;
use IvanCraft623\MobPlugin\entity\monster\Enderman;
use IvanCraft623\MobPlugin\entity\monster\Endermite;
use IvanCraft623\MobPlugin\entity\monster\Husk;
use IvanCraft623\MobPlugin\entity\monster\illager\Vindicator;
use IvanCraft623\MobPlugin\entity\monster\MagmaCube;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Bogged;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Skeleton;
use IvanCraft623\MobPlugin\entity\monster\skeleton\Stray;
use IvanCraft623\MobPlugin\entity\monster\skeleton\WitherSkeleton;
use IvanCraft623\MobPlugin\entity\monster\Slime;
use IvanCraft623\MobPlugin\entity\monster\Spider;
use IvanCraft623\MobPlugin\entity\monster\Witch;
use IvanCraft623\MobPlugin\entity\monster\Zombie;
use IvanCraft623\MobPlugin\entity\monster\ZombifiedPiglin;
use pocketmine\entity\Entity;
use pocketmine\entity\Squid;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

/**
 * The despawn rule of each entity type, by network id, and the despawn profile of each
 * entity that has a rule. An entity without a rule never despawns.
 */
final class DespawnRuleRegistry{
	use SingletonTrait;

	/** @phpstan-var array<string, DespawnRule<*>> */
	private array $rules = [];

	/**
	 * WeakMap ensures that the profile is destroyed when the entity is destroyed
	 *
	 * @phpstan-var \WeakMap<Entity, EntityDespawnProfile>
	 */
	private \WeakMap $profiles;

	private function __construct(){
		/** @phpstan-var \WeakMap<Entity, EntityDespawnProfile> $profiles */
		$profiles = new \WeakMap();
		$this->profiles = $profiles;

		foreach([
			Axolotl::class,
			Bat::class,
			Chicken::class,
			Cow::class,
			MooshroomCow::class,
			Pig::class,
			PolarBear::class,
			Rabbit::class,
			Sheep::class,
			GlowSquid::class,
			Squid::class // implemented by PocketMine-MP
		] as $entityClass){
			$this->register(new DespawnRule($entityClass));
		}
		$this->register(new DespawnRule(Wolf::class, persistentWhile: static fn(Wolf $wolf) : bool => $wolf->isTamed()));
		foreach([
			Bogged::class,
			CaveSpider::class,
			Creeper::class,
			Skeleton::class,
			Spider::class,
			Stray::class,
			WitherSkeleton::class,
			Husk::class,
			Drowned::class,
			Vindicator::class,
			Witch::class,
			Zombie::class,
			ZombifiedPiglin::class
		] as $entityClass){
			$this->register(DespawnRule::monster($entityClass));
		}
		$this->register(DespawnRule::monster(
			Enderman::class,
			persistentWhile: static fn(Enderman $enderman) : bool => $enderman->getCarriedBlock() !== null
		));
		$this->register(DespawnRule::monster(Endermite::class, maxLifetime: 2400));
		$this->register(DespawnRule::monster(MagmaCube::class));
		foreach([
			Cod::class,
			Pufferfish::class,
			Salmon::class,
			TropicalFish::class
		] as $entityClass){
			$this->register(new DespawnRule($entityClass, maxDistance: 40));
		}
		$this->register(DespawnRule::monster(
			Slime::class,
			difficultyCondition: static fn(Slime $slime) : bool => $slime->getAttackDamage() > 0 // the smallest are harmless
		));
		$this->register(new DespawnRule(
			Wither::class,
			despawnsAwayFromPlayers: false,
			minDifficulty: World::DIFFICULTY_EASY
		));
	}

	/**
	 * @phpstan-param DespawnRule<*> $rule
	 *
	 * @throws \InvalidArgumentException if the entity type already has a rule
	 */
	public function register(DespawnRule $rule, bool $override = false) : void{
		$identifier = $rule->entityClass::getNetworkTypeId();
		if(!$override && isset($this->rules[$identifier])){
			throw new \InvalidArgumentException("A despawn rule for \"$identifier\" is already registered");
		}
		$this->rules[$identifier] = $rule;
	}

	/**
	 * @phpstan-param class-string<Entity> $entityClass
	 */
	public function unregister(string $entityClass) : void{
		unset($this->rules[$entityClass::getNetworkTypeId()]);
	}

	/**
	 * @phpstan-param class-string<Entity> $entityClass
	 *
	 * @phpstan-return DespawnRule<*>|null
	 */
	public function get(string $entityClass) : ?DespawnRule{
		return $this->rules[$entityClass::getNetworkTypeId()] ?? null;
	}

	/**
	 * Returns the despawn profile of an entity, or null if its type has no rule.
	 */
	public function getProfile(Entity $entity) : ?EntityDespawnProfile{
		if(!isset($this->rules[$entity::getNetworkTypeId()])){
			return null;
		}

		return $this->createProfile($entity);
	}

	/**
	 * Returns the rule and the despawn profile of an entity, or null if its type has no rule.
	 *
	 * @phpstan-return array{DespawnRule<*>, EntityDespawnProfile}|null
	 */
	public function resolve(Entity $entity) : ?array{
		$rule = $this->rules[$entity::getNetworkTypeId()] ?? null;
		if($rule === null){
			return null;
		}

		return [$rule, $this->createProfile($entity)];
	}

	private function createProfile(Entity $entity) : EntityDespawnProfile{
		return $this->profiles[$entity] ??= new EntityDespawnProfile();
	}
}