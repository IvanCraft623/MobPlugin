<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable;

use InvalidArgumentException;

interface LootTableResolver{

	/**
	 * Returns the table a "loot_table" entry refers to.
	 *
	 * @param string $name path as written in the entry, e.g. "loot_tables/entities/armor_set_iron.json"
	 * @throws InvalidArgumentException if no table exists by that name
	 */
	public function resolve(string $name) : LootTable;
}