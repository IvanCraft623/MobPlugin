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

namespace IvanCraft623\MobPlugin\entity\projectile;

use pocketmine\block\Block;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Explosive;
use pocketmine\entity\Living;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Explosion;
use pocketmine\world\sound\ArrowHitSound;
use pocketmine\world\World;
use function sqrt;

/**
 * Why NeverSavedWithChunkEntity? A wither skull's actual lifespan is only
 * a few seconds, but without this, an unresolved skull (one that never
 * hits anything and is never explicitly despawned) easily could be persisted
 * to chunk data and reloaded indefinitely on every chunk load, causing
 * these projectiles to accumulate forever across chunk saves.
 *
 * This has been observed in practice - see:
 * https://www.reddit.com/r/Minecraft/comments/3tlqvi/i_found_some_floating_wither_skull_projectiles_3/
 */
class WitherSkull extends Projectile implements Explosive, NeverSavedWithChunkEntity{

	public const DEFAULT_DAMAGE = 8;
	public const DEFAULT_EXPLOSION_RADIUS = 1;
	public const DEFAULT_MOTION_ACCELERATION = 0.1;

	public static function getNetworkTypeId() : string{ return EntityIds::WITHER_SKULL; }

	protected float $damage = self::DEFAULT_DAMAGE;
	protected float $explosionRadius = self::DEFAULT_EXPLOSION_RADIUS;
	protected float $acceleration = self::DEFAULT_MOTION_ACCELERATION;

	protected bool $deflectable = false;
	protected bool $wasDeflected = false;

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.15, 0.15); }

	protected function getInitialDragMultiplier() : float{ return 0.05; }

	protected function getInitialGravity() : float{ return 0; }

	public function getExplosionRadius() : float{
		return $this->explosionRadius;
	}

	/**
	 * @return $this
	 */
	public function setExplosionRadius(float $radius) : self{
		$this->explosionRadius = $radius;
		return $this;
	}

	public function getAcceleration() : float{
		return $this->acceleration;
	}

	/**
	 * @return $this
	 */
	public function setAcceleration(float $acceleration) : self{
		$this->acceleration = $acceleration;
		return $this;
	}

	public function isDeflectable() : bool{
		return $this->deflectable;
	}

	/**
	 * @return $this
	 */
	public function setDeflectable(bool $deflectable = true) : self{
		$this->deflectable = $deflectable;
		return $this;
	}

	public function wasDeflected() : bool{
		return $this->wasDeflected;
	}

	public function explode() : void{
		$ev = new EntityPreExplodeEvent($this, $this->explosionRadius);
		$ev->call();
		if(!$ev->isCancelled()){
			$explosion = new Explosion($this->location, $ev->getRadius(), $this);
			if($ev->isBlockBreaking()){
				$explosion->explodeA();
			}
			$explosion->explodeB();
		}
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		parent::onHit($event);
		$this->broadcastSound(new ArrowHitSound()); // this isn't an arrow...
		$this->explode();
	}

	/**
	 * Override because we need to apply wither effect only
	 * when EntityDamageByEntityEvent isn't cancelled.
	 */
	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		$damage = $this->getResultDamage();

		if($damage >= 0){
			$owner = $this->getOwningEntity();
			if($owner === null){
				$ev = new EntityDamageByEntityEvent($this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
			} else {
				$ev = new EntityDamageByChildEntityEvent($owner, $this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
			}

			$entityHit->attack($ev);

			if (!$ev->isCancelled() &&
				$entityHit instanceof Living &&
				($difficulty = $entityHit->getWorld()->getDifficulty()) >= World::DIFFICULTY_NORMAL
			) {
				$entityHit->getEffects()->add(new EffectInstance(
					VanillaEffects::WITHER(),
					$difficulty === World::DIFFICULTY_HARD ? 800 : 200,
					1
				));
			}

			if($this->isOnFire()){
				$ev = new EntityCombustByEntityEvent($this, $entityHit, 5);
				$ev->call();
				if(!$ev->isCancelled()){
					$entityHit->setOnFire($ev->getDuration());
				}
			}
		}

		if($this->despawnsOnEntityHit()){
			$this->flagForDespawn();
		}
	}

	protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void{
		parent::onHitBlock($blockHit, $hitResult);
		$this->flagForDespawn();
	}

	protected function tryChangeMovement() : void{
		//Equivalent of this without many Vector3 allocations, tryChangeMovement is a hot path!!!
		//$this->motion = $this->motion->addVector($this->motion->normalize()->multiply($this->acceleration));

		$motionX = $this->motion->x;
		$motionY = $this->motion->y;
		$motionZ = $this->motion->z;

		$motionLengthSquared = ($motionX * $motionX) + ($motionY * $motionY) + ($motionZ * $motionZ);
		if ($motionLengthSquared > 0) {
			$motionLength = sqrt($motionLengthSquared);

			$motionX += ($motionX / $motionLength) * $this->acceleration;
			$motionY += ($motionY / $motionLength) * $this->acceleration;
			$motionZ += ($motionZ / $motionLength) * $this->acceleration;

			$this->motion = new Vector3($motionX, $motionY, $motionZ);
		}

		parent::tryChangeMovement();
	}

	public function attack(EntityDamageEvent $source) : void {
		parent::attack($source);
		if ($this->deflectable &&
			!$source->isCancelled() &&
			$source instanceof EntityDamageByEntityEvent &&
			($damager = $source->getDamager()) !== null
		) {
			$this->setMotion($damager->getDirectionVector());
			$this->wasDeflected = true;
		}
	}

	public function canSaveWithChunk() : bool{
		return false;
	}
}
