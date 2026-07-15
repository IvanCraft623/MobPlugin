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

namespace IvanCraft623\MobPlugin\entity\ai\goal\wither;

use pocketmine\entity\Entity;
use pocketmine\Server;

final class WitherBurstShot{

	readonly ?int $targetEntityId;

	public function __construct(
		?Entity $target,
		readonly bool $dangerous,
		readonly int $delay
	) {
		$this->targetEntityId = $target?->getId() ?? null;
	}

	public function getTargetEntity() : ?Entity {
		return $this->targetEntityId !== null ?
			Server::getInstance()->getWorldManager()->findEntity($this->targetEntityId) :
			null
		;
	}
}
