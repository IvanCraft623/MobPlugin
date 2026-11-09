<?php

/*
 *  _____      _   _      __ _           _
 * |  __ \    | | | |    / _(_)         | |
 * | |__) |_ _| |_| |__ | |_ _ _ __   __| | ___ _ __
 * |  ___/ _` | __| '_ \|  _| | '_ \ / _` |/ _ \ '__|
 * | |  | (_| | |_| | | | | | | | | | (_| |  __/ |
 * |_|   \__,_|\__|_| |_|_| |_|_| |_|\__,_|\___|_|
 *
 * A PocketMine-MP virion that implements a mob-oriented pathfinding.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder\evaluator;

use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder\BlockPathType;
use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder\Node;
use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder\pathtype\PathTypeResolver;
use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder\SearchContext;
use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder\Target;

use pocketmine\math\Vector3;
use pocketmine\utils\Limits;
use pocketmine\world\World;
use function floor;
use function max;
use function min;

/**
 * @phpstan-import-type BlockPosHash from World
 */
abstract class NodeEvaluator {

	protected SearchContext $context;

	/** @var array<BlockPosHash, Node> */
	protected array $nodes = [];

	/** @var array<BlockPosHash, BlockPathType> */
	private array $pathTypes = [];

	public function __construct(
		protected readonly PathTypeResolver $pathTypeResolver
	){
	}

	public function getPathTypeResolver() : PathTypeResolver{
		return $this->pathTypeResolver;
	}

	public function prepare(SearchContext $context) : void{
		$this->context = $context;
		$this->nodes = [];
		$this->pathTypes = [];
	}

	public function done() : void{
		unset($this->context);
		$this->nodes = [];
		$this->pathTypes = [];
	}

	final protected function getPathfindingMalus(BlockPathType $pathType) : float{
		return $this->context->profile->costMap->getPathfindingMalus($pathType);
	}

	/**
	 * Type of the blocks the mob takes with its lowest corner at x, y, z.
	 */
	final protected function getCachedPathType(int $x, int $y, int $z) : BlockPathType{
		$hash = World::blockHash($x, $y, $z);
		return $this->pathTypes[$hash] ??= $this->pathTypeResolver->getPathType(
			$this->context->blockGetter,
			$this->context->profile,
			$x, $y, $z,
			$this->context->start
		);
	}

	public function getNode(Vector3 $pos) : Node{
		return $this->getNodeAt((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z));
	}

	public function getNodeAt(int $x, int $y, int $z) : Node{
		if (!$this->context->blockGetter->isInWorld($x, $y, $z)) {
			throw new \InvalidArgumentException("Position ($x, $y, $z) is out of world bounds!");
		}

		$hash = Node::createHash($x, $y, $z);
		if (!isset($this->nodes[$hash])) {
			$this->nodes[$hash] = new Node($x, $y, $z);
		}
		return $this->nodes[$hash];
	}

	public abstract function getStart() : Node;

	public function getGoal(float $x, float $y, float $z) : Target{
		return $this->getTargetFromNode($this->getNodeAt(
			(int) max(Limits::INT32_MIN, min(Limits::INT32_MAX, (int) floor($x))),
			(int) max($this->context->blockGetter->minY, min($this->context->blockGetter->maxY - 1, (int) floor($y))),
			(int) max(Limits::INT32_MIN, min(Limits::INT32_MAX, (int) floor($z)))
		));
	}

	protected function getTargetFromNode(Node $node) : Target {
		return Target::fromObject($node);
	}

	/**
	 * Start node at the block of $position, clamped into the world.
	 */
	public function getStartNode(Vector3 $position) : Node{
		$node = $this->getNodeAt(
			(int) max(Limits::INT32_MIN, min(Limits::INT32_MAX, (int) floor($position->x))),
			(int) max($this->context->blockGetter->minY, min($this->context->blockGetter->maxY - 1, (int) floor($position->y))),
			(int) max(Limits::INT32_MIN, min(Limits::INT32_MAX, (int) floor($position->z)))
		);

		$node->type = $this->getCachedPathType($node->x(), $node->y(), $node->z());
		$node->costMalus = $this->getPathfindingMalus($node->type);

		return $node;
	}

	/**
	 * @return Node[]
	 */
	public abstract function getNeighbors(Node $node) : array;
}