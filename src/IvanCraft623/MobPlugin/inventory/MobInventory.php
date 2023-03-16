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

	public function getHolder() : Living {
		return $this->holder;
	}

	public function getHeldItemIndex() : int {
		return self::SLOT_MAIN_HAND;
	}

	public function getMainHand() : Item {
		return $this->getItem(self::SLOT_MAIN_HAND);
	}

	public function getOffHand() : Item {
		return $this->getItem(self::SLOT_OFFHAND);
	}
}
