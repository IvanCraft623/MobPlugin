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

namespace IvanCraft623\MobPlugin\particle;

use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\LevelEventGenericPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\world\particle\Particle;

class TeleportTrailParticle implements Particle{

	private const TAG_PARTICLE_COUNT = "Count";

	private const TAG_DIR_SCALE = "DirScale";

	private const TAG_FROM_X = "Startx";
	private const TAG_FROM_Y = "Starty";
	private const TAG_FROM_Z = "Startz";

	private const TAG_TO_X = "Endx";
	private const TAG_TO_Y = "Endy";
	private const TAG_TO_Z = "Endz";

	private const TAG_HORIZONTAL_VARIATION = "Variationx";
	private const TAG_VERTICAL_VARIATION = "Variationy";

	public const DEFAULT_PARTICLE_COUNT = 128;
	public const DEFAULT_DIR_SCALE = 0.2;

	public function __construct(
		private Vector3 $to,
		private float $horizontalVariation,
		private float $verticalVariation,
		private int $count = self::DEFAULT_PARTICLE_COUNT,
		private float $dirScale = self::DEFAULT_DIR_SCALE
	){}

	public function encode(Vector3 $from) : array{
		return [LevelEventGenericPacket::create(LevelEvent::PARTICLE_TELEPORT_TRAIL, CompoundTag::create()
			->setFloat(self::TAG_FROM_X, $from->x)
			->setFloat(self::TAG_FROM_Y, $from->y)
			->setFloat(self::TAG_FROM_Z, $from->z)
			->setFloat(self::TAG_TO_X, $this->to->x)
			->setFloat(self::TAG_TO_Y, $this->to->y)
			->setFloat(self::TAG_TO_Z, $this->to->z)
			->setFloat(self::TAG_HORIZONTAL_VARIATION, $this->horizontalVariation)
			->setFloat(self::TAG_VERTICAL_VARIATION, $this->verticalVariation)
			->setFloat(self::TAG_DIR_SCALE, $this->dirScale)
			->setInt(self::TAG_PARTICLE_COUNT, $this->count)
		)];
	}
}
