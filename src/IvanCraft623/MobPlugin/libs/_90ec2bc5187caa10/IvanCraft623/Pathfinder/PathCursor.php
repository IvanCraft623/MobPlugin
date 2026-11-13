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

namespace IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\IvanCraft623\Pathfinder;

use pocketmine\math\Vector3;

/**
 * A {@link Path} and how far along it the mob is: the node next to reach. What depends on the progress, like which
 * changed blocks still matter, is answered here.
 */
final class PathCursor{

	public function __construct(
		private readonly Path $path,
		private int $nextNodeIndex = 0
	){
	}

	public function getPath() : Path{
		return $this->path;
	}

	/**
	 * This progress on another path, e.g. the one adjusted to the mob.
	 */
	public function withPath(Path $path) : self{
		return new self($path, $this->nextNodeIndex);
	}

	public function advance() : void{
		++$this->nextNodeIndex;
	}

	public function notStarted() : bool{
		return $this->nextNodeIndex <= 0;
	}

	public function isDone() : bool{
		return $this->nextNodeIndex >= $this->path->getNodeCount();
	}

	public function getNextNodeIndex() : int{
		return $this->nextNodeIndex;
	}

	public function getNextNodeCenter() : Vector3{
		return $this->path->getNodeCenter($this->nextNodeIndex);
	}

	public function getNextNode() : Node{
		return $this->path->getNode($this->nextNodeIndex);
	}

	public function getNextNodePos() : Vector3{
		return $this->path->getNodePos($this->nextNodeIndex);
	}

	public function getPreviousNode() : ?Node{
		return $this->nextNodeIndex > 0 ? $this->path->getNode($this->nextNodeIndex - 1) : null;
	}

	/**
	 * Index of the node still to walk closest to $position.
	 *
	 * @throws \LogicException if the path is done
	 */
	public function getClosestNodeIndex(Vector3 $position) : int{
		return $this->path->getClosestNodeIndex($position, $this->nextNodeIndex);
	}

	/**
	 * Whether the block is one the search looked at for a node still to be walked, see {@link Path::isInCorridor()}.
	 */
	public function isInCorridor(int $x, int $y, int $z) : bool{
		return $this->path->isInCorridor($x, $y, $z, $this->nextNodeIndex);
	}

	/**
	 * The nodes still to be walked that the block at x, y, z matters to, see {@link Path::findInvalidation()}.
	 */
	public function findInvalidation(int $x, int $y, int $z) : ?PathInvalidation{
		return $this->path->findInvalidation($x, $y, $z, $this->nextNodeIndex);
	}

	/**
	 * How to patch the path around the invalidation, see {@link Path::planRepair()}.
	 */
	public function planRepair(PathInvalidation $invalidation) : ?PathRepair{
		return $this->path->planRepair($invalidation, $this->nextNodeIndex);
	}

	/**
	 * Whether {@link splice()} accepts these arguments, for a path that may have changed since the continuation
	 * was searched.
	 */
	public function canSplice(int $index, Path $continuation, ?int $rejoinIndex = null) : bool{
		return $index >= $this->nextNodeIndex - 1 && $this->path->canSplice($index, $continuation, $rejoinIndex);
	}

	/**
	 * The path spliced as {@link Path::splice()} does, with the progress kept: $index can't be behind the node last
	 * walked.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function splice(int $index, Path $continuation, ?int $rejoinIndex = null) : self{
		if ($index < $this->nextNodeIndex - 1) {
			throw new \InvalidArgumentException("Can't splice at node $index, node $this->nextNodeIndex is next");
		}

		return new self($this->path->splice($index, $continuation, $rejoinIndex), $this->nextNodeIndex);
	}
}