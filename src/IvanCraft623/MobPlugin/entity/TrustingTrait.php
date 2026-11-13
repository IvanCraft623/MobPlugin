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

namespace IvanCraft623\MobPlugin\entity;

use IvanCraft623\MobPlugin\entity\animation\TameAnimation;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;

/**
 * Trust earned by chance with an item that is used up.
 *
 * @phpstan-require-extends Mob
 * @phpstan-require-implements Trusting
 */
trait TrustingTrait{

	/**
	 * Component group of a mob that trusts players.
	 */
	abstract protected function getTrustingComponentGroup() : string;

	/**
	 * Component group of a mob that doesn't trust players yet.
	 */
	abstract protected function getWaryComponentGroup() : string;

	abstract protected function isTrustItem(Item $item) : bool;

	/**
	 * Probability, from 0 to 1, that the item earns trust.
	 */
	abstract protected function getTrustChance() : float;

	private bool $trusting = false;

	/**
	 * Reads the state from the component groups, once the mob has loaded them.
	 */
	protected function readTrusting() : void{
		$this->trusting = $this->componentGroups->has($this->getTrustingComponentGroup());
	}

	/**
	 * Puts the state in the component groups, before the mob saves them.
	 */
	protected function writeTrusting() : void{
		$this->componentGroups->set($this->getTrustingComponentGroup(), $this->trusting);
		$this->componentGroups->set($this->getWaryComponentGroup(), !$this->trusting);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		$properties->setGenericFlag(EntityMetadataFlags::TRUSTING, $this->trusting);
	}

	public function isTrusting() : bool{
		return $this->trusting;
	}

	public function setTrusting(bool $trusting) : void{
		if ($this->trusting !== $trusting) {
			$this->trusting = $trusting;
			$this->networkPropertiesDirty = true;
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if ($this->trusting || !$this->isTrustItem($player->getInventory()->getItemInHand())) {
			return false;
		}

		Utils::popItemInHand($player);
		$trusts = $this->random->nextFloat() < $this->getTrustChance();
		if ($trusts) {
			$this->setTrusting(true);
		}
		$this->broadcastAnimation(new TameAnimation($this, $trusts));

		return true;
	}
}