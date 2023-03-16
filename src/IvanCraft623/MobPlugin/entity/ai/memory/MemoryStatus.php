<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\memory;

use pocketmine\utils\EnumTrait;

class MemoryStatus {
	use EnumTrait {
		__construct as Enum___construct;
	}

	protected static function setup() : void{
		self::registerAll(
			new Activity("value_present"),
			new Activity("value_absent"),
			new Activity("registered")
		);
	}

	private function __construct(string $enumName) {
		$this->Enum___construct($enumName);
	}
}