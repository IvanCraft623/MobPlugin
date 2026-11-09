<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_3cb7361eeacc73c5\kostamax27\loottable;

use LogicException;

/**
 * The loot tables being rolled, so a table that reaches itself fails instead of looping.
 */
final class LootTableStack{

	/** @var array<string, true> */
	private array $names = [];

	/**
	 * @throws LogicException if the table is already being rolled
	 */
	public function enter(string $name) : void{
		isset($this->names[$name]) && throw new LogicException("Loot table '{$name}' references itself");
		$this->names[$name] = true;
	}

	public function leave(string $name) : void{
		unset($this->names[$name]);
	}
}