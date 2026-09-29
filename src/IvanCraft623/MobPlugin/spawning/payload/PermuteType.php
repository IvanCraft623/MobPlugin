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

namespace IvanCraft623\MobPlugin\spawning\payload;

use function strpos;
use function substr;

/**
 * One permute_type entry: a weighted alternative entity type. The event suffix
 * ("minecraft:pillager<minecraft:...>") is stripped here at parse time, so consumers
 * get a clean identifier. A null identifier leaves the spawn type untouched (vanilla
 * data quirk).
 */
final class PermuteType{
	public readonly ?string $entityType;

	public function __construct(
		public readonly int $weight,
		?string $entityType
	){
		// Strip any event suffix ("<minecraft:...>") once here, at parse time, so
		// consumers get a clean identifier.
		if($entityType !== null){
			$suffixStart = strpos($entityType, "<");
			if($suffixStart !== false){
				$entityType = substr($entityType, 0, $suffixStart);
			}
		}
		$this->entityType = $entityType;
	}
}
