<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\kostamax27\loottable;

use InvalidArgumentException;

final class NullLootTableResolver implements LootTableResolver{

	private static self $instance;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function resolve(string $name) : LootTable{
		throw new InvalidArgumentException("Cannot resolve loot table '{$name}': no loot table resolver has been configured, see LootTableFactory::setTableResolver()");
	}
}