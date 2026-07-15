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

use IvanCraft623\MobPlugin\entity\boss\Boss;
use IvanCraft623\MobPlugin\entity\boss\Wither;
use IvanCraft623\MobPlugin\entity\golem\IronGolem;
use IvanCraft623\MobPlugin\entity\golem\SnowGolem;
use IvanCraft623\MobPlugin\entity\monster\Zombie;
use IvanCraft623\MobPlugin\pattern\BlockPattern;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\MobHead;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\UsedChunkStatus;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Utils as PMUtils;
use pocketmine\world\Position;
use pocketmine\world\World;

class EventListener implements Listener {

	/**
	 * Overrides pocketmine's built-in mobs spawn logic
	 */
	public function onPlayerInteract(PlayerInteractEvent $event) : void{
		$item = $event->getItem();
		if ($item->getTypeId() === ItemTypeIds::ZOMBIE_SPAWN_EGG) {
			$event->cancel();

			$blockPosition = $event->getBlock()->getPosition();
			$entity = (new Zombie(
				Location::fromObject($blockPosition->add(0.5, 1, 0.5),
				$blockPosition->getWorld(), PMUtils::getRandomFloat() * 360, 0))
			)->setPersistent();

			if($item->hasCustomName()){
				$entity->setNameTag($item->getCustomName());
			}
			Utils::popItemInHand($event->getPlayer());
			$entity->spawnToAll();
		}
	}

	/**
	 * @priority HIGH
	 * @ignoreCancelled
	 */
	public function onBlockPlace(BlockPlaceEvent $event) : void {
		foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){
			if (($id = $block->getTypeId()) === BlockTypeIds::CARVED_PUMPKIN ||
				$id === BlockTypeIds::LIT_PUMPKIN ||
				$id === BlockTypeIds::PUMPKIN
			) {
				$player = $event->getPlayer();
				MobPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($block, $player) : void{
					$pos = $block->getPosition();

					($this->tryToSpawnFromPattern(
						$pos, IronGolem::getSpawnPattern(), fn(Location $l) => new IronGolem($l), new Vector3(1, 2, 0)
					) ?? $this->tryToSpawnFromPattern(
						$pos, SnowGolem::getSpawnPattern(), fn(Location $l) => new SnowGolem($l), new Vector3(0, 2, 0)
					))?->setOwningEntity($player->isClosed() ? null : $player); //Player may have been disconnected
				}), 1);
			} elseif ($block instanceof MobHead && $block->getMobHeadType() === MobHeadType::WITHER_SKELETON) {
				$player = $event->getPlayer();
				MobPlugin::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($block, $player) : void{
					$pos = $block->getPosition();

					$this->tryToSpawnFromPattern(
						$pos, Wither::getSpawnPattern(), fn(Location $l) => new Wither($l), new Vector3(1, 2, 0)
					)?->setOwningEntity($player->isClosed() ? null : $player); //Player may have been disconnected
				}), 1);
			}
		}
	}

	private function tryToSpawnFromPattern(Position $pos, BlockPattern $pattern, \Closure $entityConstructor, Vector3 $spawnOffset) : ?Entity{
		$patternMatch = $pattern->find($pos);
		if ($patternMatch === null) {
			return null;
		}

		$world = $pos->getWorld();

		//clear pattern
		$totalWidth = $patternMatch->getWidth();
		for ($currentWidth = 0; $currentWidth < $totalWidth; $currentWidth++) {
			$totalHeight = $patternMatch->getHeight();
			for ($currentHeight = 0; $currentHeight < $totalHeight; $currentHeight++) {
				$b = $patternMatch->getBlock($currentWidth, $currentHeight, 0, $world);
				if ($b->getTypeId() === BlockTypeIds::AIR) {
					continue;
				}

				Utils::destroyBlock($world, $b->getPosition());
			}
		}

		//spawn
		$e = $entityConstructor(Location::fromObject(
			$patternMatch->getBlock((int) $spawnOffset->x, (int) $spawnOffset->y, (int) $spawnOffset->z, $world)
				->getPosition()
				->add(0.5, 0, 0.5),
			$world
		));
		$e->spawnToAll();

		return $e;
	}

	public function onEntityDeath(EntityDeathEvent $event) : void{
		$entity = $event->getEntity();
		if (!$entity instanceof Living) {
			return;
		}

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

	/**
	 * TODO: HACK! The client ignores the BossEventPackets sent during the login sequence.
	 * This is a problem because bosses near the player when they log in won't display their boss bar,
	 * so we resend the packet onJoin.
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void{
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
