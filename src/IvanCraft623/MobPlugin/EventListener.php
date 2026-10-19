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

namespace IvanCraft623\MobPlugin;

use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\animal\Axolotl;
use IvanCraft623\MobPlugin\entity\boss\Boss;
use IvanCraft623\MobPlugin\entity\boss\Wither;
use IvanCraft623\MobPlugin\entity\Living;
use IvanCraft623\MobPlugin\entity\monster\Endermite;
use IvanCraft623\MobPlugin\entity\monster\Zombie;
use IvanCraft623\MobPlugin\event\MobSpawnCause;
use IvanCraft623\MobPlugin\event\MobSpawnEvent;
use IvanCraft623\MobPlugin\pattern\BlockPatternFactory;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\entity\projectile\Projectile;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\PotionType;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\player\UsedChunkStatus;
use pocketmine\utils\Utils as PMUtils;
use pocketmine\world\World;
use function min;

class EventListener implements Listener {

	// --- Overriding spawn egg ---

	/**
	 * Overrides pocketmine's built-in mobs spawn logic
	 */
	public function onSpawnEggInteract(PlayerInteractEvent $event) : void{
		$item = $event->getItem();
		if ($item->getTypeId() === ItemTypeIds::ZOMBIE_SPAWN_EGG) {
			$event->cancel();

			$blockPosition = $event->getBlock()->getPosition();
			$entity = new Zombie(
				Location::fromObject($blockPosition->add(0.5, 1, 0.5),
				$blockPosition->getWorld(), PMUtils::getRandomFloat() * 360, 0)
			);
			(new MobSpawnEvent($entity, MobSpawnCause::SPAWN_EGG))->call();

			if($item->hasCustomName()){
				$entity->setNameTag($item->getCustomName());
			}
			Utils::popItemInHand($event->getPlayer());
			$entity->spawnToAll();
		}
	}

	// --- Block patterns ---

	/**
	 * @priority HIGH
	 * @ignoreCancelled
	 */
	public function onPatternBlockPlace(BlockPlaceEvent $event) : void {
		$patternFactory = BlockPatternFactory::getInstance();
		foreach ($event->getTransaction()->getBlocks() as [, , , $block]) {
			foreach ($patternFactory->getCandidates($block) as $pattern) {
				$match = $pattern->find($block->getPosition(), true);
				if ($match === null) {
					continue;
				}

				$player = $event->getPlayer();

				$event->cancel();

				Utils::popItemInHand($player);
				$pattern->execute($match, $block, $player);
				return; //Just one valid pattern
			}
		}
	}

	// --- Effects ---

	/**
	 * @priority LOWEST
	 */
	public function onEffectAdd(EntityEffectAddEvent $event) : void{
		$entity = $event->getEntity();
		if ($entity instanceof Living && !$entity->canAddEffect($event->getEffect())) {
			$event->cancel();
		}
	}

	// --- Wither ---

	public function onWitherKill(EntityDeathEvent $event) : void{
		$entity = $event->getEntity();
		$deathCause = $entity->getLastDamageCause();
		if (!$deathCause instanceof EntityDamageByEntityEvent) {
			return;
		}

		$killer = $deathCause->getDamager();
		if (!$killer instanceof Wither &&
			!($killer instanceof Projectile && $killer->getOwningEntity() instanceof Wither)
		) {
			// When a projectile explodes, the event that fires is always EntityDamageByEntityEvent
			// instead of EntityDamageByChildEntityEvent, which is why the check is done this way.
			return;
		}

		$witherRose = VanillaBlocks::WITHER_ROSE();
		$blockPosition = $entity->getPosition();
		$world = $entity->getWorld();
		if ($witherRose->canBePlacedAt($world->getBlock($blockPosition), Vector3::zero(), Facing::UP, false)) {
			$world->setBlock($blockPosition, $witherRose);
		} else {
			$drops = $event->getDrops();
			$drops[] = $witherRose->asItem();
			$event->setDrops($drops);
		}
	}

	// --- Axolotl ---

	/**
	 * The axolotls fighting the killed entity make its killer regenerate.
	 */
	public function onAxolotlTargetKilled(EntityDeathEvent $event) : void{
		$victim = $event->getEntity();
		$deathCause = $victim->getLastDamageCause();
		if (!$deathCause instanceof EntityDamageByEntityEvent) {
			return;
		}

		$killer = $deathCause->getDamager();
		if (!$killer instanceof Player) {
			return;
		}

		$range = Axolotl::BUFF_RANGE;
		foreach ($victim->getWorld()->getNearbyEntities($killer->getBoundingBox()->expandedCopy($range, $range, $range)) as $nearby) {
			if (!$nearby instanceof Axolotl || $nearby->getTargetEntity() !== $victim) {
				continue;
			}

			$effects = $killer->getEffects();
			$remaining = $effects->get(VanillaEffects::REGENERATION())?->getDuration() ?? 0;
			if ($remaining < Axolotl::MAX_REGENERATION_BUFF_TICKS - 1) {
				$effects->add(new EffectInstance(VanillaEffects::REGENERATION(), min(Axolotl::MAX_REGENERATION_BUFF_TICKS, Axolotl::BUFF_TICKS + $remaining)));
			}
			$effects->remove(VanillaEffects::MINING_FATIGUE());
		}
	}

	/**
	 * Water bottles hydrate the axolotls close to where they break.
	 */
	public function onWaterBottleSplash(ProjectileHitEvent $event) : void{
		$potion = $event->getEntity();
		if (!$potion instanceof SplashPotion || !$potion->getPotionType()->equals(PotionType::WATER())) {
			return;
		}

		foreach ($potion->getWorld()->getNearbyEntities($potion->getBoundingBox()->expandedCopy(4, 2, 4)) as $entity) {
			if ($entity instanceof Axolotl && $entity->getPosition()->distanceSquared($potion->getPosition()) < 16) {
				$entity->setAirSupplyTicks(min($entity->getMaxAirSupplyTicks(), $entity->getAirSupplyTicks() + Axolotl::POTION_HYDRATION_TICKS));
			}
		}
	}

	// --- Endermite ---

	public function onEnderPearlHit(ProjectileHitEvent $event) : void{
		$pearl = $event->getEntity();
		if (!$pearl instanceof EnderPearl || !$pearl->getOwningEntity() instanceof Player) {
			return;
		}

		$world = $pearl->getWorld();
		if ($world->getDifficulty() === World::DIFFICULTY_PEACEFUL ||
			!Settings::getSettings($world->getFolderName())->isMobNaturalSpawningEnabled()
		) {
			return;
		}

		if (PMUtils::getRandomFloat() >= Endermite::ENDER_PEARL_SPAWN_CHANCE) {
			return;
		}

		$hitResult = $event->getRayTraceResult();

		$endermite = new Endermite(Location::fromObject(
			$hitResult->getHitVector()->addVector(
				Vector3::zero()->getSide($hitResult->getHitFace())->multiply(VanillaEntitySizes::ENDERMITE_HEIGHT)
			),
			$world,
			PMUtils::getRandomFloat() * 360
		));
		(new MobSpawnEvent($endermite, MobSpawnCause::ENDER_PEARL, $pearl))->call();
		$endermite->spawnToAll();
	}

	// --- Boss bars ---

	/**
	 * TODO: HACK! The client ignores the BossEventPackets sent during the login sequence.
	 * This is a problem because bosses near the player when they log in won't display their boss bar,
	 * so we resend the packet onJoin.
	 */
	public function onPlayerJoinShowBossBars(PlayerJoinEvent $event) : void{
		$player = $event->getPlayer();
		$world = $player->getWorld();
		foreach ($player->getUsedChunks() as $chunkHash => $chunkStatus) {
			if ($chunkStatus !== UsedChunkStatus::SENT) {
				continue;
			}

			World::getXZ($chunkHash, $chunkX, $chunkZ);
			foreach ($world->getChunkEntities($chunkX, $chunkZ) as $entity) {
				if (!$entity instanceof Boss) {
					continue;
				}

				$entity->getBossBar()->showTo([$player]);
			}
		}
	}
}