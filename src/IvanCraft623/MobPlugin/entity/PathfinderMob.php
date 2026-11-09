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

namespace IvanCraft623\MobPlugin\entity;

use IvanCraft623\MobPlugin\CustomTimings;
use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\navigation\locomotion\GroundLocomotion;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\IvanCraft623\Pathfinder\PathCursor;
use pocketmine\color\Color;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\ChunkListener;
use pocketmine\world\format\Chunk;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\Position;
use pocketmine\world\World;

abstract class PathfinderMob extends Mob implements ChunkListener {

	/**
	 * Chunks listened for block changes: the ones the path being followed goes through.
	 *
	 * @phpstan-var array<int, true> chunkHash => true
	 */
	protected array $usedChunks = [];

	private ?PathCursor $listenedCursor = null;

	protected PathNavigation $navigation;

	public function createNavigation() : PathNavigation{
		return new PathNavigation($this, new GroundLocomotion());
	}

	protected function createMoveControl() : MoveControl{
		return $this->navigation->getLocomotion()->createMoveControl($this, $this->navigation);
	}

	public function getNavigation() : PathNavigation{
		return $this->navigation;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->navigation = $this->createNavigation();

		parent::initEntity($nbt);
	}

	private function listenToPathChunks(?PathCursor $cursor) : void{
		$chunks = $cursor?->getPath()->getCorridorChunks() ?? [];

		$world = $this->getWorld();
		foreach($chunks as $hash => $_){
			if(!isset($this->usedChunks[$hash])){
				World::getXZ($hash, $chunkX, $chunkZ);
				$world->registerChunkListener($this, $chunkX, $chunkZ);
			}
		}
		foreach($this->usedChunks as $hash => $_){
			if(!isset($chunks[$hash])){
				World::getXZ($hash, $chunkX, $chunkZ);
				$world->unregisterChunkListener($this, $chunkX, $chunkZ);
			}
		}

		$this->usedChunks = $chunks;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$cursor = $this->navigation->getCursor();

		if($cursor !== null && $this->ticksLived % 10 === 0 && $this->getSettings()->isDebugModeEnabled()){
			$world = $this->getWorld();
			foreach($cursor->getPath()->getNodes() as $node){
				$world->addParticle($node->add(0.5, 0.5, 0.5), new DustParticle(new Color(255, 0, 0))); //red particle
			}
		}

		if($cursor !== null && $cursor->isDone()){
			$cursor = null;
		}
		if($cursor !== $this->listenedCursor){
			$this->listenedCursor = $cursor;
			$this->listenToPathChunks($cursor);
		}

		return $hasUpdate;
	}

	protected function setPosition(Vector3 $pos) : bool{
		if(!$this->closed && $pos instanceof Position && $pos->getWorld() !== $this->getWorld()){
			//The chunks listened belong to the world being left
			$this->listenedCursor = null;
			$this->listenToPathChunks(null);
		}

		return parent::setPosition($pos);
	}

	public function teleport(Vector3 $pos, ?float $yaw = null, ?float $pitch = null) : bool{
		if(parent::teleport($pos, $yaw, $pitch)){
			$this->navigation->stop();

			return true;
		}

		return false;
	}

	protected function destroyCycles() : void{
		$this->navigation->stop();

		parent::destroyCycles();

		unset($this->navigation);
	}

	public function onChunkChanged(int $chunkX, int $chunkZ, Chunk $chunk) : void{}
	public function onChunkLoaded(int $chunkX, int $chunkZ, Chunk $chunk) : void{}
	public function onChunkUnloaded(int $chunkX, int $chunkZ, Chunk $chunk) : void{}
	public function onChunkPopulated(int $chunkX, int $chunkZ, Chunk $chunk) : void{}

	public function onBlockChanged(Vector3 $position) : void{
		// It would be great to be able to compare block collisions to save execution time but
		// with the current pocketmine implementation there is no an easy way to know which block was before
		CustomTimings::$pathBlockChange->startTiming();
		$this->navigation->onBlockChanged($position);
		CustomTimings::$pathBlockChange->stopTiming();
	}

	public function getWalkTargetValue(Vector3 $position) : float{
		return 0;
	}

	protected function onDispose() : void{
		if($this->location->isValid()){
			$world = $this->getWorld();
			foreach($this->usedChunks as $index => $status){
				World::getXZ($index, $X, $Z);
				$world->unregisterChunkListener($this, $X, $Z);
			}
		}

		parent::onDispose();
	}
}