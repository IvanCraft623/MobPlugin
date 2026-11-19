<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_47f51ab364465e1c\kostamax27\loottable\entry;

use IvanCraft623\MobPlugin\libs\_47f51ab364465e1c\kostamax27\loottable\LootContext;

final class EmptyLootEntry implements LootEntry{

	private static self $instance;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function generate(LootContext $context) : array{
		return [];
	}
}