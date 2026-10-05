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

use IvanCraft623\MobPlugin\event\EntityNaturalDespawnEvent;
use IvanCraft623\MobPlugin\event\NaturalDespawnCause;
use IvanCraft623\MobPlugin\Settings;
use IvanCraft623\MobPlugin\utils\SimulationRange;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\player\ChunkSelector;
use pocketmine\utils\Random;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use function count;
use function min;
use const PHP_FLOAT_MAX;

/**
 * One run of a world's despawn checks. NaturalDespawnTask creates one per world; the
 * chunk lookups live only as long as the pass.
 */
final class WorldDespawnPass{
	/** How often the task runs, in ticks. Vanilla checks every tick; the chance is scaled to match. */
	public const CHECK_INTERVAL = 20;

	/**
	 * With despawning off the inactivity time is still kept, for the goals that act on it;
	 * the difficulty and maximum lifetime removals still apply.
	 */
	private readonly bool $enabled;

	private readonly int $difficulty;

	private readonly bool $lowTickRadius;

	/** @phpstan-var array<int, true>|null by chunk hash; null until needed */
	private ?array $tickingChunks = null;

	/** @phpstan-var array<int, bool> by chunk hash */
	private array $simulationEdges = [];

	/**
	 * @phpstan-param list<Position> $playerPositions where the players that keep mobs around are
	 */
	public function __construct(
		private readonly World $world,
		private readonly array $playerPositions,
		private readonly DespawnRuleRegistry $registry,
		private readonly Random $random
	){
		$this->enabled = Settings::getSettings($world->getFolderName())->isMobNaturalDespawningEnabled();
		$this->difficulty = $world->getDifficulty();
		$this->lowTickRadius = SimulationRange::isLowTickRadius($world);
	}

	public function run() : void{
		$hasPlayers = count($this->playerPositions) !== 0;
		foreach($this->world->getEntities() as $entity){
			if($entity->isFlaggedForDespawn() || !$entity->isAlive()){
				continue; // a dying mob finishes dying
			}
			$resolved = $this->registry->resolve($entity);
			if($resolved === null){
				continue;
			}
			[$rule, $profile] = $resolved;
			if($rule->shouldDespawnInDifficulty($entity, $this->difficulty)){
				$this->despawn($entity, NaturalDespawnCause::DIFFICULTY);
				continue;
			}
			$named = $entity->getNameTag() !== "";
			if($rule->maxLifetime !== null && !$named && $profile->getLifetime() >= $rule->maxLifetime){
				$this->despawn($entity, NaturalDespawnCause::MAX_LIFETIME); // persistence doesn't stop it, only a name
				continue;
			}
			if($profile->isPersistent() || ($named && $rule->persistentWithNameTag) || $rule->keepsPersistent($entity) || !$rule->despawnsAwayFromPlayers){
				// Vanilla leaves the inactivity time of these running. Resetting it changes nothing
				// for despawning, and keeps the goals that act on it working.
				$profile->markActive();
				continue;
			}
			if(!$hasPlayers){
				continue; // the rules below measure from the players
			}

			$pos = $entity->getPosition();
			$distanceSquared = PHP_FLOAT_MAX;
			foreach($this->playerPositions as $playerPos){
				$distanceSquared = min($distanceSquared, $playerPos->distanceSquared($pos));
			}

			$playerClose = $distanceSquared < $rule->minDistance ** 2;
			if($playerClose){
				$profile->markActive();
			}elseif($rule->fasterInactivityMinLight !== null && $this->world->getFullLight($pos) >= $rule->fasterInactivityMinLight){
				$profile->addInactivityTime($rule->fasterInactivityBonus * self::CHECK_INTERVAL);
			}
			if(!$this->enabled){
				continue;
			}
			$cause = !$playerClose && $this->softRulesPrescribeDespawn($rule, $profile->getInactivityTime()) ?
				NaturalDespawnCause::INACTIVITY :
				$this->hardRulesPrescribeDespawn($rule, $pos, $distanceSquared);
			if($cause !== null){
				$this->despawn($entity, $cause);
			}
		}
	}

	private function despawn(Entity $entity, NaturalDespawnCause $cause) : void{
		$event = new EntityNaturalDespawnEvent($entity, $cause);
		$event->call();
		if(!$event->isCancelled()){
			$entity->flagForDespawn();
		}
	}

	/**
	 * The rules for an entity with no player inside the minimum distance: inactive for long
	 * enough, then by chance.
	 *
	 * @phpstan-param DespawnRule<*> $rule
	 */
	private function softRulesPrescribeDespawn(DespawnRule $rule, int $inactivityTime) : bool{
		if($inactivityTime < $rule->inactivityTime){
			return false;
		}

		return $rule->despawnChance === 0 || $this->random->nextFloat() < self::CHECK_INTERVAL / $rule->despawnChance;
	}

	/**
	 * The rules that despawn an entity at once: no player inside the maximum distance, or
	 * at the edge of the ticking chunks. Returns which one does, or null if none.
	 *
	 * @phpstan-param DespawnRule<*> $rule
	 */
	private function hardRulesPrescribeDespawn(DespawnRule $rule, Vector3 $pos, float $distanceSquared) : ?NaturalDespawnCause{
		$maxDistance = $rule->maxDistance;
		if($this->lowTickRadius){
			// Too few chunks tick for an edge: a shorter distance stands in for both rules.
			$maxDistance = min(SimulationRange::LOW_TICK_RADIUS_MAX_PLAYER_DISTANCE, $maxDistance);
		}
		if($distanceSquared >= $maxDistance ** 2){
			return NaturalDespawnCause::DISTANCE;
		}
		if(!$this->lowTickRadius && $this->isAtSimulationEdge($pos->getFloorX() >> Chunk::COORD_BIT_SIZE, $pos->getFloorZ() >> Chunk::COORD_BIT_SIZE)){
			return NaturalDespawnCause::SIMULATION_EDGE;
		}

		return null;
	}

	/**
	 * Whether one of the 3×3 chunks around the chunk is outside the ticking chunks. As in
	 * vanilla, it only counts once all of them are loaded.
	 */
	private function isAtSimulationEdge(int $chunkX, int $chunkZ) : bool{
		return $this->simulationEdges[World::chunkHash($chunkX, $chunkZ)] ??= $this->computeSimulationEdge($chunkX, $chunkZ);
	}

	private function computeSimulationEdge(int $chunkX, int $chunkZ) : bool{
		$this->tickingChunks ??= $this->selectTickingChunks();
		$atEdge = false;
		for($x = $chunkX - 1; $x <= $chunkX + 1; $x++){
			for($z = $chunkZ - 1; $z <= $chunkZ + 1; $z++){
				if(!$this->world->isChunkLoaded($x, $z)){
					return false;
				}
				if(!isset($this->tickingChunks[World::chunkHash($x, $z)])){
					$atEdge = true;
				}
			}
		}

		return $atEdge;
	}

	/**
	 * The chunks the world's players keep ticking, selected the way PocketMine-MP does. Not
	 * World::getTickingChunks(): a chunk drops out of it while it is locked or being lit.
	 *
	 * @phpstan-return array<int, true> by chunk hash
	 */
	private function selectTickingChunks() : array{
		$selector = new ChunkSelector();
		$server = $this->world->getServer();
		$tickRadius = $this->world->getChunkTickRadius();
		$chunks = [];
		foreach($this->world->getPlayers() as $player){
			$pos = $player->getPosition();
			foreach($selector->selectChunks(
				min($tickRadius, $server->getAllowedViewDistance($player->getViewDistance())),
				$pos->getFloorX() >> Chunk::COORD_BIT_SIZE,
				$pos->getFloorZ() >> Chunk::COORD_BIT_SIZE
			) as $hash){
				$chunks[$hash] = true;
			}
		}

		return $chunks;
	}
}
