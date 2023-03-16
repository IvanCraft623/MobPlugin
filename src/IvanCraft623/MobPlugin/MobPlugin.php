<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Random;
use pocketmine\utils\SingletonTrait;

class MobPlugin extends PluginBase {
	use SingletonTrait;

	private ?Random $random = null;

	public function onLoad() : void {
		self::setInstance($this);
	}

	public function onEnable() : void {
		# Nothing >:D
	}

	public function getRandom(): Random {
		if ($this->random === null) {
			$this->random = new Random(mt_rand());
		}
		return $this->random;
	}
}
