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

namespace IvanCraft623\MobPlugin\entity\aquatic;

use IvanCraft623\MobPlugin\despawning\DespawnPersistenceTrait;
use IvanCraft623\MobPlugin\entity\LootRollingTrait;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;
use IvanCraft623\MobPlugin\memory\Memories;
use IvanCraft623\MobPlugin\memory\Occurrence;

use pocketmine\entity\Squid;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

/**
 * Moves like PocketMine-MP's squid, which has no AI to extend.
 */
class GlowSquid extends Squid {
	use DespawnPersistenceTrait {
		initEntity as public;
	}
	use LootRollingTrait;

	public static function getNetworkTypeId() : string{ return EntityIds::GLOW_SQUID; }

	public function getName() : string{
		return "Glow Squid";
	}

	public function getXpDropAmount() : int{
		return Memories::getInstance()->latest($this, Occurrence::HURT_BY_PLAYER, Mob::LAST_HIT_BY_PLAYER_TICKS) !== null ? mt_rand(1, 3) : 0;
	}

	public function getDrops() : array{
		return $this->generateDeathLoot(VanillaLootTables::GLOW_SQUID());
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::GLOW_SQUID_SPAWN_EGG();
	}
}