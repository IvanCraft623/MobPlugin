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

namespace IvanCraft623\MobPlugin\pathfinder;

use pocketmine\utils\EnumTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static BlockPathTypes BLOCKED()
 * @method static BlockPathTypes BREACH()
 * @method static BlockPathTypes COCOA()
 * @method static BlockPathTypes DAMAGE_FIRE()
 * @method static BlockPathTypes DAMAGE_OTHER()
 * @method static BlockPathTypes DANGER_FIRE()
 * @method static BlockPathTypes DANGER_OTHER()
 * @method static BlockPathTypes DANGER_POWDER_SNOW()
 * @method static BlockPathTypes DOOR_IRON_CLOSED()
 * @method static BlockPathTypes DOOR_OPEN()
 * @method static BlockPathTypes DOOR_WOOD_CLOSED()
 * @method static BlockPathTypes FENCE()
 * @method static BlockPathTypes LAVA()
 * @method static BlockPathTypes LEAVES()
 * @method static BlockPathTypes OPEN()
 * @method static BlockPathTypes POWDER_SNOW()
 * @method static BlockPathTypes RAIL()
 * @method static BlockPathTypes STICKY_HONEY()
 * @method static BlockPathTypes TRAPDOOR()
 * @method static BlockPathTypes UNPASSABLE_RAIL()
 * @method static BlockPathTypes WALKABLE()
 * @method static BlockPathTypes WALKABLE_DOOR()
 * @method static BlockPathTypes WATER()
 * @method static BlockPathTypes WATER_BORDER()
 */
final class BlockPathTypes {
	use EnumTrait {
		__construct as Enum___construct;
	}

	protected static function setup() : void{
		self::registerAll(
			new self("blocked", -1),
			new self("open", 0),
			new self("walkable", 0),
			new self("walkable_door", 0),
			new self("trapdoor", 0),
			new self("powder_snow", -1),
			new self("danger_powder_snow", 0),
			new self("fence", -1),
			new self("lava", -1),
			new self("water", 8),
			new self("water_border", 8),
			new self("rail", 0),
			new self("unpassable_rail", 0),
			new self("danger_fire", 8),
			new self("damage_fire", 16),
			new self("danger_other", 8),
			new self("damage_other", -1),
			new self("door_open", 0),
			new self("door_wood_closed", -1),
			new self("door_iron_closed", -1),
			new self("breach", 4),
			new self("leaves", -1),
			new self("sticky_honey", 8),
			new self("cocoa", 0)
		);
	}

	private float $malus;

	private function __construct(string $enumName, float $malus) {
		$this->malus = $malus;
		$this->Enum___construct($enumName);
	}

	public function getName() : string {
		return $this->name();
	}

	public function getMalus() : float{
		return $this->malus;
	}
}
