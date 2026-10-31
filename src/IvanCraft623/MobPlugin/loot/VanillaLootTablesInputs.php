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

namespace IvanCraft623\MobPlugin\loot;

use pocketmine\utils\RegistrySource;
use function preg_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Source of the generated VanillaLootTables: a member for each vanilla loot table. Entity
 * tables are named without "entities/", the others keep their folder (entities/cow is
 * COW(), gameplay/entities/sniffer_seeds is GAMEPLAY_ENTITIES_SNIFFER_SEEDS()).
 *
 * Regenerate VanillaLootTables with: composer compile-loot-tables
 *
 * @internal
 * @phpstan-extends RegistrySource<LootTable>
 */
final class VanillaLootTablesInputs extends RegistrySource{

	private const UNPREFIXED_FOLDER = "entities/";

	public function getTargetClassName() : string{
		return "VanillaLootTables";
	}

	public function getTargetClassDocComment() : array{
		return ["The vanilla loot tables"];
	}

	protected function setup() : void{
		foreach(LootTableRegistry::getInstance()->getNames() as $name){
			$member = str_starts_with($name, self::UNPREFIXED_FOLDER) ? substr($name, strlen(self::UNPREFIXED_FOLDER)) : $name;
			self::registerValue(preg_replace("/[^A-Za-z0-9]+/", "_", $member) ?? $member, new LootTable($name));
		}
	}
}