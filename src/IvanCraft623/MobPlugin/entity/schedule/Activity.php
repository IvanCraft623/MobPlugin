<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\schedule;

use pocketmine\utils\EnumTrait;

class Activity {
	use EnumTrait {
		__construct as Enum___construct;
	}

	protected static function setup() : void{
		self::registerAll(
			new Activity("core"),
			new Activity("idle"),
			new Activity("work"),
			new Activity("play"),
			new Activity("rest"),
			new Activity("meet"),
			new Activity("panic"),
			new Activity("raid"),
			new Activity("pre_raid"),
			new Activity("hide"),
			new Activity("fight"),
			new Activity("celebrate"),
			new Activity("avoid"),
			new Activity("ride")
		);
	}

	private function __construct(string $enumName) {
		$this->Enum___construct($enumName);
	}

	public function getName(): string {
		return $this->name();
	}
}