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

namespace IvanCraft623\MobPlugin\pathfinder\evaluator;

use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\pathfinder\BlockPathTypes;
use IvanCraft623\MobPlugin\pathfinder\Node;
use IvanCraft623\MobPlugin\pathfinder\Target;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use function floor;

abstract class NodeEvaluator {

	protected World $world;

	protected Mob $mob;

	/** @var array<int, Node> hash => Node */
	protected array $nodes = [];

	protected int $entityWidth;
	protected int $entityHeight;
	protected int $entityDepth;

	protected bool $canPassDoors = false;
	protected bool $canOpenDoors = false;
	protected bool $canFloat = false;
	protected bool $canWalkOverFences = false;

	public function prepare(World $world, Mob $mob) : void{
		$this->world = $world;
		$this->mob = $mob;

		$this->nodes = [];

		$this->entityWidth = (int) floor($mob->getSize()->getWidth() + 1);
		$this->entityHeight = (int) floor($mob->getSize()->getHeight() + 1);
		$this->entityDepth = (int) floor($mob->getSize()->getWidth() + 1);
	}

	public function done() : void{
		unset($this->world);
		unset($this->mob);
	}

	public function getNode(Vector3 $pos) : Node{
		return $this->getNodeAt((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z));
	}

	public function getNodeAt(int $x, int $y, int $z) : Node{
		$hash = Node::createHash($x, $y, $z);
		if (!isset($this->nodes[$hash])) {
			$this->nodes[$hash] = new Node($x, $y, $z);
		}
		return $this->nodes[$hash];
	}

	public abstract function getStart() : Node;

	public abstract function getGoal(float $x, float $y, float $z) : Target;

	protected function getTargetFromNode(Node $node) : Target {
		return Target::fromObject($node);
	}

	/**
	 * @param Node[] $nodes
	 */
	public abstract function getNeighbors(array &$nodes, Node $node) : int;

	public abstract function getBlockPathTypeWithMob(World $world, int $x, int $y, int $z, Mob $mob) : BlockPathTypes;

	public abstract function getBlockPathType(World $world, int $x, int $y, int $z) : BlockPathTypes;

	 public function setCanPassDoors(bool $canPassDoors = true) : void {
		$this->canPassDoors = $canPassDoors;
	}

	public function setCanOpenDoors(bool $canOpenDoors = true) : void {
		$this->canOpenDoors = $canOpenDoors;
	}

	public function setCanFloat(bool $canFloat = true) : void {
		$this->canFloat = $canFloat;
	}

	public function setCanWalkOverFences(bool $canWalkOverFences = true) : void {
		$this->canWalkOverFences = $canWalkOverFences;
	}

	public function canPassDoors() : bool {
		return $this->canPassDoors;
	}

	public function canOpenDoors() : bool {
		return $this->canOpenDoors;
	}

	public function canFloat() : bool {
		return $this->canFloat;
	}

	public function canWalkOverFences() : bool{
		return $this->canWalkOverFences;
	}
}
