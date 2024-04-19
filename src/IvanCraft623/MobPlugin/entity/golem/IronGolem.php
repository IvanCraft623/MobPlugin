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
use IvanCraft623\MobPlugin\entity\ai\goal\MeleeAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\MoveTowardsTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\OfferFlowerGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomStrollGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\monster\Creeper;
use IvanCraft623\MobPlugin\entity\monster\Enemy;
use IvanCraft623\MobPlugin\entity\NeutralMob;
use IvanCraft623\MobPlugin\entity\NeutralMobTrait;
use IvanCraft623\MobPlugin\pattern\BlockPattern;
use IvanCraft623\MobPlugin\pattern\BlockPatternBuilder;
use IvanCraft623\MobPlugin\sound\IronGolemCrackSound;
use IvanCraft623\MobPlugin\sound\IronGolemRepairSound;
use IvanCraft623\MobPlugin\sound\ThrowSound;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living as PMLiving;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\EntityEventBroadcaster;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;

use function min;
use function mt_rand;

class IronGolem extends Golem implements NeutralMob{
	use NeutralMobTrait;

	private static BlockPattern $spawnPattern;

	public static function getSpawnPattern() : BlockPattern{
		if (!isset(self::$spawnPattern)) {
			self::$spawnPattern = BlockPatternBuilder::start()
				->aisle([
					"*O*",
					"###",
					"*#*"
				])
				->where('O', fn(Block $block) => $block->getTypeId() === BlockTypeIds::CARVED_PUMPKIN)
				->where('*', fn(Block $block) => $block->getTypeId() === BlockTypeIds::AIR)
				->where('#', fn(Block $block) => $block->getTypeId() === BlockTypeIds::IRON)
				->build();
		}
		return self::$spawnPattern;
	}

	private const COMPONENT_GROUP_PLAYER_CREATED = "minecraft:player_created";
	private const COMPONENT_GROUP_VILLAGE_CREATED = "minecraft:village_created";

	public const MIN_ANGER_TIME = 20;
	public const MAX_ANGER_TIME = 40;

	public static function getNetworkTypeId() : string{ return EntityIds::IRON_GOLEM; }

	protected float $stepHeight = 1;

	private int $remainingAngerTime = 0;

	protected bool $createdByPlayer = false;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.9, 1.4, 2.61);
	}

	public function getName() : string{
		return "IronGolem";
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(1, new MeleeAttackGoal($this, 1, false));
		$this->goalSelector->addGoal(2, new MoveTowardsTargetGoal($this, 0.9, 32));
		$this->goalSelector->addGoal(4, new RandomStrollGoal($this, 0.6, 240));
		$this->goalSelector->addGoal(5, new OfferFlowerGoal($this));
		$this->goalSelector->addGoal(7, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(8, new RandomLookAroundGoal($this));
		//TODO: village related goals

		$this->targetSelector->addGoal(2, new HurtByTargetGoal($this));
		$this->targetSelector->addGoal(3, new NearestAttackableGoal(
			entity: $this,
			targetType: PMLiving::class,
			targetValidator: fn(PMLiving $e) : bool => $this->isAngryAt($e) || ($e instanceof Enemy && !$e instanceof Creeper)
		));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->createdByPlayer = !$this->componentGroups->has(self::COMPONENT_GROUP_VILLAGE_CREATED);
	}

	public function saveNBT() : CompoundTag{
		$this->componentGroups->add($this->createdByPlayer ?
			self::COMPONENT_GROUP_PLAYER_CREATED : self::COMPONENT_GROUP_VILLAGE_CREATED
		);

		return parent::saveNBT();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		//TODO!

		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->isAngry());
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(100);
		$this->setAttackDamage(15);
		$this->setKnockbackResistance(1);

		//TODO!
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	public function canBreathe() : bool{
		return true;
	}

	protected function calculateFallDamage(float $fallDistance) : float{
		return 0;
	}

	public function getXpDropAmount() : int{
		if ($this->hasBeenDamagedByPlayer()) {
			return 5;
		}

		return 0;
	}

	public function getDrops() : array{
		$drops = parent::getDrops();

		$drops[] = VanillaBlocks::POPPY()->asItem()->setCount(mt_rand(0, 2));
		$drops[] = VanillaItems::IRON_INGOT()->setCount(mt_rand(3, 5));

		return $drops;
	}

	public function isCreatedByPlayer() : bool{
		return $this->createdByPlayer;
	}

	public function setCreatedByPlayer(bool $value) : void{
		$this->createdByPlayer = $value;
	}

	public function startAngerTimer() : void{
		$this->setRemainingAngerTime(mt_rand(self::MIN_ANGER_TIME, self::MAX_ANGER_TIME));
	}

	public function getRemainingAngerTime() : int{
		return $this->remainingAngerTime;
	}

	public function setRemainingAngerTime(int $ticks) : void{
		$this->remainingAngerTime = $ticks;
	}

	public function getCrackiness() : IronGolemCrackiness{
		return IronGolemCrackiness::fromHealthPercentage($this->getHealth() / $this->getMaxHealth());
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$this->updateAnger(true);

		return $hasUpdate;
	}

	protected function doAttackAnimation() : void{
		parent::doAttackAnimation();

		$this->broadcastSound(new ThrowSound($this));
	}

	public function attack(EntityDamageEvent $source) : void{
		$crackiness = $this->getCrackiness();

		parent::attack($source);

		if (!$source->isCancelled() && $crackiness !== $this->getCrackiness()) {
			$this->broadcastSound(new IronGolemCrackSound($this));
		}
	}

	public function setHealth(float $amount) : void{
		parent::setHealth($amount);

		//We need to sync health for crackiness display
		NetworkBroadcastUtils::broadcastEntityEvent($this->getViewers(),
			function(EntityEventBroadcaster $broadcaster, array $recipients) : void{
				$broadcaster->syncAttributes($recipients, $this, [$this->healthAttr]);
			}
		);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if ($item->getTypeId() === ItemTypeIds::IRON_INGOT &&
			($health = $this->getHealth()) < ($maxHealth = $this->getMaxHealth())
		) {
			$this->setHealth(min($health + 25, $maxHealth));
			Utils::popItemInHand($player);

			$this->broadcastSound(new IronGolemRepairSound($this));
		}

		return parent::onInteract($player, $clickPos);
	}

	//TODO: spawn rules code
}
