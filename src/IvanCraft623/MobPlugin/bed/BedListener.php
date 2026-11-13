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

namespace IvanCraft623\MobPlugin\bed;

use IvanCraft623\MobPlugin\entity\SleepsWithOwner;
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedLeaveEvent;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * The pet that claims each bed to sleep with its owner.
 */
final class BedListener implements Listener{
	use SingletonTrait;

	/** @var array<int, array<int, Entity&SleepsWithOwner>> pet by world id and bed block hash */
	private array $claims = [];

	/**
	 * Gives the bed to the pet, unless another pet has it.
	 *
	 * @return bool whether the pet has the bed
	 */
	public function claim(Position $bed, Entity&SleepsWithOwner $pet) : bool{
		$worldId = $bed->getWorld()->getId();
		$hash = World::blockHash($bed->getFloorX(), $bed->getFloorY(), $bed->getFloorZ());
		$claimant = $this->claims[$worldId][$hash] ?? null;
		if ($claimant !== null && $claimant !== $pet && !$claimant->isClosed()) {
			return false;
		}

		$this->claims[$worldId][$hash] = $pet;

		return true;
	}

	public function release(Position $bed, Entity&SleepsWithOwner $pet) : void{
		$worldId = $bed->getWorld()->getId();
		$hash = World::blockHash($bed->getFloorX(), $bed->getFloorY(), $bed->getFloorZ());
		if (($this->claims[$worldId][$hash] ?? null) === $pet) {
			unset($this->claims[$worldId][$hash]);
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerBedLeave(PlayerBedLeaveEvent $event) : void{
		$bed = $event->getBed()->getPosition();
		$worldId = $bed->getWorld()->getId();
		$hash = World::blockHash($bed->getFloorX(), $bed->getFloorY(), $bed->getFloorZ());
		$pet = $this->claims[$worldId][$hash] ?? null;
		unset($this->claims[$worldId][$hash]);
		if ($pet !== null && !$pet->isClosed() && self::isNaturalWake($event->getPlayer(), $event->getBed())) {
			$pet->onWakeWithOwner();
		}
	}

	/**
	 * Whether the night was skipped, which sets the time of day to 0: the only wake-up vanilla doesn't force.
	 */
	private static function isNaturalWake(Player $player, Block $bed) : bool{
		return $bed instanceof Bed && $player->getWorld()->getTimeOfDay() === 0;
	}
}
