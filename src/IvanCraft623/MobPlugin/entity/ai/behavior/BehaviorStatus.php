<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\ai\memory;

use pocketmine\utils\EnumTrait;

class BehaviorStatus {
	use EnumTrait {
		__construct as Enum___construct;
	}

	protected static function setup() : void{
		self::registerAll(
			new Activity("stopped"),
			new Activity("running")
		);
	}

	private function __construct(string $enumName) {
		$this->Enum___construct($enumName);
	}
}