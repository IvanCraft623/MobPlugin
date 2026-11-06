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

namespace IvanCraft623\MobPlugin\entity\ai\navigation;

use IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\IvanCraft623\Pathfinder\PathCursor;
use pocketmine\math\Vector3;

/**
 * A path being requested, spliced onto $base from $spliceIndex (to $rejoinIndex, if repairing) when set.
 *
 * @internal
 */
final class PathRequest{
	public function __construct(
		public readonly Vector3 $target,
		public readonly int $reach,
		public readonly ?float $maxDistanceFromStart,
		public readonly ?PathCursor $base = null,
		public readonly int $spliceIndex = -1,
		public readonly ?int $rejoinIndex = null
	){
	}

	public function splicingOnto(PathCursor $base, int $spliceIndex, ?int $rejoinIndex = null) : self{
		return new self($this->target, $this->reach, $this->maxDistanceFromStart, $base, $spliceIndex, $rejoinIndex);
	}

	public function withoutSplice() : self{
		return new self($this->target, $this->reach, $this->maxDistanceFromStart);
	}
}