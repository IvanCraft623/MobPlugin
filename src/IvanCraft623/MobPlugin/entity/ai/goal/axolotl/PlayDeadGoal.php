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

namespace IvanCraft623\MobPlugin\entity\ai\goal\axolotl;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\animal\Axolotl;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;

class PlayDeadGoal extends Goal{

	private const DURATION_TICKS = 200;
	private const START_CHANCE = 0.33;
	private const MAX_DAMAGE = 2;
	private const FORCE_BELOW_HEALTH = 8;
	//TODO: thorns damage isn't told apart in PocketMine, wither damage arrives as magic
	private const CAUSES = [
		EntityDamageEvent::CAUSE_CONTACT => true,
		EntityDamageEvent::CAUSE_ENTITY_ATTACK => true,
		EntityDamageEvent::CAUSE_ENTITY_EXPLOSION => true,
		EntityDamageEvent::CAUSE_MAGIC => true,
		EntityDamageEvent::CAUSE_PROJECTILE => true,
	];

	/** Every hit is looked at once. */
	private ?EntityDamageEvent $lastChecked = null;

	private int $endTick = 0;

	public function __construct(private Axolotl $mob){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK, Goal::FLAG_JUMP);
	}

	public function canUse() : bool{
		$damage = $this->mob->getLastDamageCause();
		if($damage === null || $damage === $this->lastChecked){
			return false;
		}
		$this->lastChecked = $damage;

		if(!isset(self::CAUSES[$damage->getCause()]) || !$this->mob->isInWater()){
			return false;
		}

		$random = $this->mob->getRandom();

		return $this->mob->getHealth() < self::FORCE_BELOW_HEALTH ||
			($random->nextFloat() < self::START_CHANCE && $damage->getFinalDamage() > $random->nextBoundedInt(self::MAX_DAMAGE + 1));
	}

	public function canContinueToUse() : bool{
		return $this->mob->getServer()->getTick() < $this->endTick;
	}

	public function isInterruptable() : bool{
		return false;
	}

	public function start() : void{
		$this->endTick = $this->mob->getServer()->getTick() + self::DURATION_TICKS;
		$this->mob->getNavigation()->stop();
		$this->mob->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), self::DURATION_TICKS));
		$this->mob->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::PLAYING_DEAD, true);
	}

	public function stop() : void{
		$this->mob->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::PLAYING_DEAD, false);
		$this->mob->setTargetEntity(null);
	}
}
