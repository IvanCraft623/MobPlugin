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

namespace IvanCraft623\MobPlugin\spawning\condition;

use IvanCraft623\MobPlugin\spawning\SpawnBand;

final class DensityLimitCondition implements SpawnCondition{
	public function __construct(
		private readonly string $identifier,
		private readonly ?int $surfaceLimit,
		private readonly ?int $caveLimit
	){}

	/**
	 * The maximum nearby same-identifier count in the band, or null for no limit.
	 */
	public function getLimit(SpawnBand $band) : ?int{
		$limit = $band === SpawnBand::SURFACE ? $this->surfaceLimit : $this->caveLimit;

		return $limit !== null && $limit >= 0 ? $limit : null;
	}

	public function isCacheable() : bool{
		return true;
	}

	public function test(SpawnConditionContext $ctx) : bool{
		$band = $ctx->getBand();
		$limit = $this->getLimit($band);

		return $limit === null || $ctx->getPopulation()->getIdentifierCount($this->identifier, $band) < $limit;
	}
}
