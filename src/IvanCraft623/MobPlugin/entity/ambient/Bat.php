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

namespace IvanCraft623\MobPlugin\entity\ambient;

use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\Sleepable;
use IvanCraft623\MobPlugin\entity\SleepableTrait;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use function atan2;
use function count;
use function floor;
use const M_PI;

class Bat extends Ambient implements Sleepable {
	use SleepableTrait;

	public const WAKEUP_DISTANCE = 4;

	private const TAG_SLEEPING = "BatFlags"; //TAG_Byte

	protected ?Vector3 $target = null;

	public static function getNetworkTypeId() : string{ return EntityIds::BAT; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(
			VanillaEntitySizes::BAT_HEIGHT,
			VanillaEntitySizes::BAT_WIDTH,
			VanillaEntitySizes::BAT_HEIGHT * 0.44
		);
	}

	public function getName() : string{
		return "Bat";
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->setSleeping($nbt->getByte(self::TAG_SLEEPING, 0) !== 0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setByte(self::TAG_SLEEPING, $this->isSleeping() ? 1 : 0);

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::RESTING, $this->isSleeping()); //bedrock shows a sleeping bat as resting
	}

	protected function tryChangeMovement() : void{
		if (!$this->isSleeping()) {
			$this->motion = new Vector3($this->motion->x, $this->motion->y * 0.6, $this->motion->z);
		}

		parent::tryChangeMovement();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if ($this->isSleeping()) {
			$this->motion = Vector3::zero();
			$this->setPosition(new Vector3(
				$this->location->x,
				floor($this->location->y) + 1 - $this->getSize()->getHeight(),
				$this->location->z
			));
		}

		return $hasUpdate;
	}

	protected function tickState() : void{
		parent::tickState();

		if ($this->isSleeping()) {
			if (!$this->canSleep()) {
				$this->setSleeping(false);
			} elseif ($this->random->nextBoundedInt(200) === 0) {
				$this->setRotation($this->random->nextBoundedInt(360), $this->location->pitch);
			}
		} else {
			$world = $this->getWorld();
			if ($this->target !== null && ($world->getBlock($this->target)->getTypeId() !== BlockTypeIds::AIR || $this->target->y <= $world->getMinY())) {
				$this->target = null;
			}

			if ($this->target === null || $this->random->nextBoundedInt(30) === 0 || $this->target->add(0.5, 0.5, 0.5)->distanceSquared($this->location) < 4) {
				$this->target = $this->location->floor()->add(
					$this->random->nextBoundedInt(7) - $this->random->nextBoundedInt(7),
					$this->random->nextBoundedInt(6) - 2,
					$this->random->nextBoundedInt(7) - $this->random->nextBoundedInt(7)
				);
			}

			$targetDiff = $this->target->add(0.5, 0.1, 0.5)->subtractVector($this->location);
			$newMotion = $this->motion->add(
				(Utils::signum($targetDiff->x) * 0.5 - $this->motion->x) * 0.25,
				(Utils::signum($targetDiff->y) * 0.7 - $this->motion->y) * 0.3,
				(Utils::signum($targetDiff->z) * 0.5 - $this->motion->z) * 0.25
			);
			$this->setMotion($newMotion);

			$newYaw = (atan2($newMotion->z, $newMotion->x) * 180 / M_PI) - 90;
			$yawDiff = Utils::wrapDegrees($newYaw - $this->location->yaw);
			$this->setRotation($newYaw, $this->location->pitch);

			if ($this->random->nextBoundedInt(100) === 0 && $this->canSleep()) {
				$this->setSleeping(true);
			}
		}
	}

	protected function canSleep() : bool{
		$world = $this->getWorld();
		if (!$world->getBlockAt($this->location->getFloorX(), $this->location->getFloorY() + 1, $this->location->getFloorZ())->isFullCube()) {
			return false;
		}

		return count($this->getSensing()->getNearbyPlayers(self::WAKEUP_DISTANCE, targetFirst: true)) === 0;
	}

	protected function calculateFallDamage(float $fallDistance) : float{
		return 0;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);

		if (!$source->isCancelled()) {
			$this->setSleeping(false);
		}
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::BAT_SPAWN_EGG();
	}
}
