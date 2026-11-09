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

use IvanCraft623\MobPlugin\libs\_481ce64b04486f2f\IvanCraft623\Pathfinder\PathCursor;

/**
 * Keeps goals that chase an entity from requesting a path every tick.
 *
 * @internal
 */
final class EntityRepathThrottle{
	/** Ticks between requests for an entity that stays in its block */
	public const INTERVAL = 20;

	/** Block of the entity last requested */
	private ?int $block = null;

	/** Path that request resolved to */
	private ?PathCursor $cursor = null;

	private int $tick = 0;

	public function shouldRequest(int $block, bool $pending, ?PathCursor $current, int $tick) : bool{
		if ($block !== $this->block) {
			return true;
		}
		if ($pending || ($current !== null && $current === $this->cursor && !$current->isDone())) {
			return false;
		}
		//The path ended: retry only now and then
		return $tick - $this->tick >= self::INTERVAL;
	}

	public function requested(int $block, bool $pending, int $tick) : void{
		//It would get the running search's path, not its own
		$this->block = $pending ? null : $block;
		$this->cursor = null;
		$this->tick = $tick;
	}

	public function resolved(int $block, ?PathCursor $cursor) : void{
		if ($this->block === $block) {
			$this->cursor = $cursor;
		}
	}

	public function reset() : void{
		$this->block = null;
		$this->cursor = null;
	}
}