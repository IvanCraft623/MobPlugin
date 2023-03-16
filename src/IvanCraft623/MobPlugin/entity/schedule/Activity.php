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

namespace IvanCraft623\MobPlugin\entity\schedule;

use pocketmine\utils\EnumTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static Activity AVOID()
 * @method static Activity CELEBRATE()
 * @method static Activity CORE()
 * @method static Activity FIGHT()
 * @method static Activity HIDE()
 * @method static Activity IDLE()
 * @method static Activity MEET()
 * @method static Activity PANIC()
 * @method static Activity PLAY()
 * @method static Activity PRE_RAID()
 * @method static Activity RAID()
 * @method static Activity REST()
 * @method static Activity RIDE()
 * @method static Activity WORK()
 */
final class Activity {
	use EnumTrait;

	protected static function setup() : void{
		self::registerAll(
			new self("core"),
			new self("idle"),
			new self("work"),
			new self("play"),
			new self("rest"),
			new self("meet"),
			new self("panic"),
			new self("raid"),
			new self("pre_raid"),
			new self("hide"),
			new self("fight"),
			new self("celebrate"),
			new self("avoid"),
			new self("ride")
		);
	}

	public function getName() : string {
		return $this->name();
	}
}
