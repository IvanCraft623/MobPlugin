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

namespace IvanCraft623\MobPlugin\entity\golem;

use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RangedAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomStrollGoal;
use IvanCraft623\MobPlugin\entity\monster\Enemy;
use IvanCraft623\MobPlugin\entity\Shearable;
use IvanCraft623\MobPlugin\pattern\BlockPattern;
use IvanCraft623\MobPlugin\pattern\BlockPatternBuilder;
use IvanCraft623\MobPlugin\sound\EntityShootSound;
use IvanCraft623\MobPlugin\sound\ShearSound;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\WaterCauldron;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living as PMLiving;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;

use function floor;
use function mt_rand;
use function sqrt;

class SnowGolem extends Golem implements Shearable{

	private static BlockPattern $spawnPattern;

	public static function getSpawnPattern() : BlockPattern{
		if (!isset(self::$spawnPattern)) {
			self::$spawnPattern = BlockPatternBuilder::start()
				->aisle([
					"O",
					"#",
					"#"
				])
				->where('O', fn(Block $block) =>
					($id = $block->getTypeId()) === BlockTypeIds::CARVED_PUMPKIN ||
					$id === BlockTypeIds::LIT_PUMPKIN ||
					$id === BlockTypeIds::PUMPKIN
				)
				->where('#', fn(Block $block) => $block->getTypeId() === BlockTypeIds::SNOW)
				->build();
		}
		return self::$spawnPattern;
	}

	private const TAG_SHEARED = "Sheared"; //TAG_Byte

	public static function getNetworkTypeId() : string{ return EntityIds::SNOW_GOLEM; }

	protected bool $sheared = false;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.4, 1.7);
	}

	public function getName() : string{
		return "Snow Golem";
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(1, new RangedAttackGoal($this, 1.25, 20, 20, 10));
		$this->goalSelector->addGoal(2, new WaterAvoidingRandomStrollGoal($this, 1, 0.00001));
		$this->goalSelector->addGoal(3, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(4, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(1, new NearestAttackableGoal(
			entity: $this,
			targetType: PMLiving::class,
			targetValidator: fn(PMLiving $e) : bool => $e instanceof Enemy
		));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->sheared = $nbt->getByte(self::TAG_SHEARED, 0) !== 0;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setByte(self::TAG_SHEARED, $this->isSheared() ? 1 : 0);

		return $nbt;
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(4);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.2;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->isSheared());
	}

	public function getDrops() : array{
		$drops = parent::getDrops();

		$drops[] = VanillaItems::SNOWBALL()->setCount(mt_rand(0, 15));

		return $drops;
	}

	public function getXpDropAmount() : int{
		return 0;
	}

	public function isSensitiveToWater() : bool{
		return true;
	}

	protected function calculateFallDamage(float $fallDistance) : float{
		return 0;
	}

	public function onInsideBlock(Block $block) : bool{
		$hasUpdate = parent::onInsideBlock($block);

		if ($block->getTypeId() === BlockTypeIds::WATER || $block instanceof WaterCauldron) {
			$this->attack(new EntityDamageByBlockEvent($block, $this, EntityDamageEvent::CAUSE_DROWNING, 1));

			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function tickAi() : void{
		parent::tickAi();

		$pos = $this->getPosition();
		$world = $this->getWorld();

		$temperature = $world->getBiome((int) $pos->x, (int) $pos->y, (int) $pos->z)->getTemperature();
		if ($temperature > 1) {
			$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FIRE_TICK, 1));
		}

		if ($temperature > 0.8) {
			return;
		}

		for ($i = 0; $i < 4; $i++) {
			$x = (int) floor($pos->x + (($i % 2 * 2 - 1) * 0.25));
			$y = (int) floor($pos->y);
			$z = (int) floor($pos->z + (((int) ($i / 2) % 2 * 2 - 1) * 0.25));

			if (($blockAt = $world->getBlockAt($x, $y, $z))->getTypeId() === BlockTypeIds::AIR && //TODO: snow logging!
				$blockAt->getSide(Facing::DOWN)->isFullCube()
			) {
				$world->setBlockAt($x, $y, $z, VanillaBlocks::SNOW_LAYER());
			}
		}
	}

	public function performRangedAttack(Entity $target, float $force) : void{
		$eyePos = $this->getEyePos();
		$projectile = new Snowball(Location::fromObject($eyePos, $this->getWorld()), $this);

		$targetPos = $target->getEyePos();
		$targetPos->y -= 1.1;

		$delta = $targetPos->subtractVector($eyePos);
		$delta->y += sqrt($delta->x ** 2 + $delta->z ** 2) * 0.225;

		$projectile->setMotion($delta->normalize()->multiply(VanillaItems::SNOWBALL()->getThrowForce()));
		$projectile->spawnToAll();

		$this->doAttackAnimation();
	}

	public function doAttackAnimation() : void{
		$this->broadcastSound(new EntityShootSound($this));
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if ($item->getTypeId() === ItemTypeIds::SHEARS && $this->isReadyForShearing()) {
			$this->shear();
			Utils::damageItemInHand($player);

			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	public function shear() : void{
		$this->broadcastSound(new ShearSound());
		$this->setSheared();

		$this->getWorld()->dropItem(
			$this->location->add(0, $this->getSize()->getHeight(), 0),
			VanillaBlocks::CARVED_PUMPKIN()->asItem()
		);
	}

	public function setSheared(bool $sheared = true) : void{
		$this->sheared = $sheared;

		$this->networkPropertiesDirty = true;
	}

	public function isSheared() : bool{
		return $this->sheared;
	}

	public function isReadyForShearing() : bool{
		return $this->isAlive() && !$this->isSheared();
	}

	//TODO: immune to damage from powder snow
}
