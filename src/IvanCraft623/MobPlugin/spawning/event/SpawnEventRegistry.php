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

namespace IvanCraft623\MobPlugin\spawning\event;

use IvanCraft623\MobPlugin\entity\AgeableMob;
use pocketmine\entity\Entity;
use pocketmine\utils\SingletonTrait;
use function array_keys;
use function array_values;
use function class_implements;
use function class_parents;

/**
 * What a mob does for the events of the spawn rules that spawned it.
 */
final class SpawnEventRegistry{
	use SingletonTrait;

	/**
	 * @var \Closure[][] event => class => handler
	 * @phpstan-var array<string, array<class-string, \Closure(Entity, SpawnEventContext) : void>>
	 */
	private array $handlers = [];

	public function __construct(){
		$this->register(VanillaSpawnEvents::ENTITY_BORN, AgeableMob::class, static fn(AgeableMob $mob) => $mob->setBaby());
	}

	/**
	 * The handler runs for mobs of the class, or of its subclasses unless one of those has its own.
	 *
	 * @template T of Entity
	 * @phpstan-param class-string<T>                 $entityClass
	 * @phpstan-param \Closure(T, SpawnEventContext) : void $handler
	 *
	 * @throws \InvalidArgumentException if the class already handles the event
	 */
	public function register(string $event, string $entityClass, \Closure $handler) : void{
		if(isset($this->handlers[$event][$entityClass])){
			throw new \InvalidArgumentException("\"$entityClass\" already handles the spawn event \"$event\"");
		}
		/** @phpstan-var \Closure(Entity, SpawnEventContext) : void $handler */
		$this->handlers[$event][$entityClass] = $handler;
	}

	/**
	 * Registers the handler of every event of a variant map: each sets its own variant.
	 *
	 * @template T of Entity
	 * @template V of object
	 * @phpstan-param class-string<T>  $entityClass
	 * @phpstan-param array<string, V> $variants    event => variant, as the maps return them
	 * @phpstan-param \Closure(T, V) : void $apply
	 *
	 * @throws \InvalidArgumentException if the class already handles one of the events
	 */
	public function registerVariants(string $entityClass, array $variants, \Closure $apply) : void{
		foreach($variants as $event => $variant){
			$this->register($event, $entityClass, static fn(Entity $entity) => $apply($entity, $variant));
		}
	}

	public function isHandled(string $event) : bool{
		return isset($this->handlers[$event]);
	}

	/**
	 * @phpstan-return list<string>
	 */
	public function getHandledEvents() : array{
		return array_keys($this->handlers);
	}

	/**
	 * Runs the handler of the mob's own class, else of the nearest class above it. An event
	 * nothing handles does nothing.
	 */
	public function fire(string $event, Entity $entity, SpawnEventContext $ctx) : void{
		$handlers = $this->handlers[$event] ?? null;
		if($handlers === null){
			return;
		}

		$classes = [$entity::class, ...array_values(class_parents($entity)), ...array_values(class_implements($entity))];
		foreach($classes as $class){
			if(isset($handlers[$class])){
				$handlers[$class]($entity, $ctx);
				return;
			}
		}
	}
}
