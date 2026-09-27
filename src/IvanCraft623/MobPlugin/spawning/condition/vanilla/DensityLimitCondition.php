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

namespace IvanCraft623\MobPlugin\spawning\condition\vanilla;

use IvanCraft623\MobPlugin\spawning\condition\SpawnCondition;
use IvanCraft623\MobPlugin\spawning\condition\SpawnConditionContext;
use IvanCraft623\MobPlugin\spawning\SpawnBand;

/**
 * density_limit: nearby same-identifier count in the position's band must stay under
 * the band's limit (absent limit = no restriction). Identifier baked in at parse time.
 */
final class DensityLimitCondition implements SpawnCondition{
	public function __construct(
		private readonly string $identifier,
		private readonly ?int $surfaceLimit,
		private readonly ?int $undergroundLimit
	){}

	public function test(SpawnConditionContext $ctx) : bool{
		$band = SpawnBand::fromPosition($ctx->y, $ctx->env->getSurfaceY());
		$limit = $band === SpawnBand::SURFACE ? $this->surfaceLimit : $this->undergroundLimit;
		if($limit === null || $limit < 0){
			return true;
		}

		return $ctx->env->countNearby($this->identifier) < $limit;
	}
}
