<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\inventory;

use IvanCraft623\MobPlugin\entity\Living;

use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;

class MobInventory extends SimpleInventory {
	public const SLOT_MAIN_HAND = 0;
	public const SLOT_OFFHAND = 1;

	private Living $holder;

	public function __construct(Living $holder) {
		parent::__construct(2);
		$this->holder = $holder;
	}

	public function getHolder(): Living {
		return $this->holder;
	}

	public function getHeldItemIndex(): int {
		return self::SLOT_MAIN_HAND;
	}

	public function getMainHand(): Item {
		return $this->getItem(self::SLOT_MAIN_HAND);
	}

	public function getOffHand(): Item {
		return $this->getItem(self::SLOT_OFFHAND);
	}
}