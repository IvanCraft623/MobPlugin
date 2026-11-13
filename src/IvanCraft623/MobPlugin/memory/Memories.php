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

namespace IvanCraft623\MobPlugin\memory;

use pocketmine\entity\Entity;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use WeakMap;
use function spl_object_id;

/**
 * What entities remember about each other, kept outside the entities and fed by {@see MemoryListener}.
 * Only the latest memory of each occurrence is kept, and one is forgotten once it is too old to be asked for.
 */
final class Memories{
	use SingletonTrait;

	/** @phpstan-var WeakMap<Entity, array<int, Memory>> memories by spl_object_id of the occurrence */
	private WeakMap $memories;

	private function __construct(){
		$this->memories = new WeakMap();
	}

	public function record(Entity $subject, Occurrence $occurrence, Entity $other) : void{
		$memories = $this->memories[$subject] ?? [];
		$memories[spl_object_id($occurrence)] = new Memory($other, Server::getInstance()->getTick());
		$this->memories[$subject] = $memories;
	}

	/**
	 * @param int $maxAge ticks since it happened
	 */
	public function latest(Entity $subject, Occurrence $occurrence, int $maxAge) : ?Memory{
		$memory = $this->memories[$subject][spl_object_id($occurrence)] ?? null;
		if ($memory === null || Server::getInstance()->getTick() - $memory->getTick() > $maxAge) {
			return null;
		}

		return $memory;
	}

	public function forget(Entity $subject, Occurrence $occurrence) : void{
		if (!isset($this->memories[$subject])) {
			return;
		}

		$memories = $this->memories[$subject];
		unset($memories[spl_object_id($occurrence)]);
		$this->memories[$subject] = $memories;
	}
}