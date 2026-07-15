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

namespace IvanCraft623\MobPlugin\entity\boss;

use IvanCraft623\MobPlugin\entity\ai\control\FlightMoveControl;
use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\ai\goal\LookAtEntityGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\RandomLookAroundGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\NearestAttackableGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\target\TargetHighestDamagerGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WaterAvoidingRandomFlyingGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\wither\WitherAttackGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\wither\WitherExplodeOnHalfLifeGoal;
use IvanCraft623\MobPlugin\entity\ai\goal\WrappedGoal;
use IvanCraft623\MobPlugin\entity\ai\navigation\FlyingPathNavigation;
use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\entity\DamageTracker;
use IvanCraft623\MobPlugin\entity\DamageTrackerTrait;
use IvanCraft623\MobPlugin\entity\Flyable;
use IvanCraft623\MobPlugin\entity\FlyableTrait;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\MobType;
use IvanCraft623\MobPlugin\entity\monster\Monster;
use IvanCraft623\MobPlugin\entity\Powerable;
use IvanCraft623\MobPlugin\entity\projectile\DangerousWitherSkull;
use IvanCraft623\MobPlugin\entity\projectile\WitherSkull;
use IvanCraft623\MobPlugin\entity\RangedAttackMob;
use IvanCraft623\MobPlugin\pattern\BlockPattern;
use IvanCraft623\MobPlugin\pattern\BlockPatternBuilder;
use IvanCraft623\MobPlugin\sound\DeathSound;
use IvanCraft623\MobPlugin\sound\EntityBreakBlockSound;
use IvanCraft623\MobPlugin\sound\EntityShootSound;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\MobHead;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\InstantEffect;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Explosive;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\utils\Binary;
use pocketmine\utils\Utils as PMUtils;
use pocketmine\world\Explosion;
use pocketmine\world\sound\BowShootSound;
use pocketmine\world\World;

use xenialdan\apibossbar\BossBar;

use function ceil;
use function cos;
use function count;
use function floor;
use function fmod;
use function max;
use function mt_rand;
use function round;
use function sin;
use function spl_object_id;
use function sqrt;
use const INF;
use const M_PI;
use const M_PI_2;

class Wither extends Monster implements Boss, Flyable, Explosive, Powerable, RangedAttackMob, DamageTracker{
	use FlyableTrait {
		addAttributes as addAttributesFlyableTrait;
	}
	use DamageTrackerTrait {
		attack as attackDamageTracker;
	}

	private static BlockPattern $spawnPattern;

	public static function getSpawnPattern() : BlockPattern{
		if (!isset(self::$spawnPattern)) {
			self::$spawnPattern = BlockPatternBuilder::start()
				->aisle([
					"OOO",
					"###",
					"*#*"
				])
				->where('O', fn(Block $block) =>
					$block instanceof MobHead &&
					$block->getMobHeadType() === MobHeadType::WITHER_SKELETON
				)
				->where('#', fn(Block $block) =>
					($typeId = $block->getTypeId()) === BlockTypeIds::SOUL_SAND ||
					$typeId === BlockTypeIds::SOUL_SOIL
				)
				->where('*', fn(Block $block) => $block->getTypeId() === BlockTypeIds::AIR)
				->build();
		}
		return self::$spawnPattern;
	}

	private const TAG_INVULNERABLE = "Invul"; //TAG_Int
	private const TAG_MAX_HEALTH = "maxHealth"; //TAG_Int
	private const TAG_NOT_POWERED = "AirAttack"; //TAG_Byte

	public const DEFAULT_EXPLOSION_RADIUS = 7; //in blocks

	public const HEAL_REGEN_CHECK_INTERVAL = 20; //in ticks
	public const HEAL_REGEN_DISTANCE = 50; //in blocks

	public const HOVER_HEIGHT_ABOVE_TARGET = 5; //in blocks
	public const POWERED_HOVER_HEIGHT_ABOVE_TARGET = 0.5; //in blocks

	public const SPAWN_DELAY_TICKS = 200;
	public const SPAWN_SOUND_PLAY_COUNT = 20;

	public const DEATH_TICKS = 200;

	public static function getNetworkTypeId() : string{ return EntityIds::WITHER; }

	protected BossBar $bossBar;

	protected int $maxDeadTicks = self::DEATH_TICKS;

	protected bool $powered = false;

	protected int $explosionRadius = self::DEFAULT_EXPLOSION_RADIUS;

	protected int $invulnerableTicks = self::SPAWN_DELAY_TICKS;

	protected int $breakBlocksAroundTicks = 0;

	protected ?int $leftHeadTargetId = null;
	protected ?int $rightHeadTargetId = null;

	protected TargetingConditions $targetingConditions;

	protected WitherAttackGoal $attackGoal;

	private WrappedGoal $explodeOnHalfLifeWrapped;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(3, 1, 3.6); // what? eye above bounding box...
	}

	public function getName() : string{
		return "Wither";
	}

	public function getMobType() : MobType{
		return MobType::UNDEAD();
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new class($this) extends Goal { //DoNothingGoal
			public function __construct(protected Wither $wither) {
				$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK, Goal::FLAG_JUMP);
			}

			public function canUse() : bool{
				return $this->wither->getInvulnerableTicks() > 0;
			}
		});
		$this->explodeOnHalfLifeWrapped = $this->goalSelector->addGoal(1, new WitherExplodeOnHalfLifeGoal($this, 1.25));
		$this->goalSelector->addGoal(2, $this->attackGoal = new WitherAttackGoal($this, 1, 1.25));
		$this->goalSelector->addGoal(5, new WaterAvoidingRandomFlyingGoal($this, 1));
		$this->goalSelector->addGoal(6, new LookAtEntityGoal($this, Player::class, 8));
		$this->goalSelector->addGoal(7, new RandomLookAroundGoal($this));

		$nearestAttackableGoal = new NearestAttackableGoal(
			entity: $this, targetType: Living::class, targetValidator: static function(Living $e) : bool{
				return !($e instanceof Mob && $e->getMobType()->equals(MobType::UNDEAD()));
			}
		);
		$this->targetingConditions = $nearestAttackableGoal->getTargetingConditions();

		$this->targetSelector->addGoal(1, new TargetHighestDamagerGoal($this, $this->targetingConditions));
		$this->targetSelector->addGoal(2, new HurtByTargetGoal($this));
		$this->targetSelector->addGoal(3, $nearestAttackableGoal);
	}

	public function createNavigation() : PathNavigation{
		$navigation = new FlyingPathNavigation($this);
		$navigation->setCanPassDoors(false);
		$navigation->setCanOpenDoors(true);
		$navigation->setCanFloat(true);
		return $navigation;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->moveControl = new FlightMoveControl($this, 10, false);

		$this->bossBar = BossBar::createForEntity($this)
			->setTitle($this->getDisplayName())
			->setColor(BossBarColor::REBECCA_PURPLE)
		;

		$this->invulnerableTicks = $nbt->getInt(self::TAG_INVULNERABLE, self::SPAWN_DELAY_TICKS);
		$this->powered = $nbt->getByte(self::TAG_NOT_POWERED, 1) !== 1;

		// Players might try to cheat and reduce the Wither's health by decreasing difficulty
		// while being on a unloaded chunk, that's why maxHealth is saved.
		$this->setMaxHealth($nbt->getInt(self::TAG_MAX_HEALTH, $this->getMaxHealth()));
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$nbt->setInt(self::TAG_INVULNERABLE, Binary::signInt($this->invulnerableTicks));
		$nbt->setInt(self::TAG_MAX_HEALTH, Binary::unsignInt($this->getMaxHealth()));
		$nbt->setByte(self::TAG_NOT_POWERED, !$this->powered ? 1 : 0);

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::SPAWNING_FRAMES, self::SPAWN_DELAY_TICKS);

		$properties->setLong(EntityMetadataProperties::WITHER_TARGET_1, $this->leftHeadTargetId ?? -1);
		$properties->setLong(EntityMetadataProperties::WITHER_TARGET_2, $this->targetId ?? -1);
		$properties->setLong(EntityMetadataProperties::WITHER_TARGET_3, $this->rightHeadTarget ?? -1);

		if ($this->isAlive()) {
			$properties->setShort(EntityMetadataProperties::WITHER_AERIAL_ATTACK, !$this->powered ? 1 : 0);
			$properties->setInt(EntityMetadataProperties::WITHER_INVULNERABLE_TICKS, $this->invulnerableTicks);
		} elseif($this->deadTicks < $this->maxDeadTicks){ // Death animation...
			$blinkPhases = 8;
			$inner = $blinkPhases * $blinkPhases - $this->deadTicks;
			$showPowered = $inner <= 0
				? ($this->deadTicks % 2 !== 0)
				: (($blinkPhases - (int) floor(sqrt($inner - 1))) % 2 !== 0);

			$properties->setShort(EntityMetadataProperties::WITHER_AERIAL_ATTACK, !$showPowered ? 1 : 0);
			$properties->setInt(EntityMetadataProperties::WITHER_INVULNERABLE_TICKS, $this->maxDeadTicks - $this->deadTicks);
		}

		// TODO: Find out what makes wither display dash position!!!
	}

	protected function addAttributes() : void{
		parent::addAttributes();

		$this->addAttributesFlyableTrait();
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setFollowRange(70); // java = 40, bedrock = 70
		$this->setMaxHealth(match ($this->getWorld()->getDifficulty()) {
			World::DIFFICULTY_HARD => 600,
			World::DIFFICULTY_NORMAL => 450,
			default => 300, // java always is 300
		});
		$this->flyingSpeed->setValue(0.6);

	}

	public function getDefaultMovementSpeed() : float{
		return 0.25; // java uses 0.6 on ground
	}

	public function isFireProof() : bool{
		return true;
	}

	public function canBreathe() : bool{
		return true;
	}

	protected function tryChangeMovement() : void{
		$motionX = $this->motion->x;
		$motionY = $this->motion->y * 0.6;
		$motionZ = $this->motion->z;

		$targetEntity = $this->getTargetEntity();
		if ($targetEntity !== null) {
			$targetPosition = $targetEntity->getLocation();

			// When powered (below half health), it holds the exact same
			// height as its target. When not powered, it holds 5 blocks above.
			if ($this->navigation->isDone() &&
				!$this->moveControl->hasWanted() &&
				!$this->explodeOnHalfLifeWrapped->isRunning() &&
				$this->location->y < $targetPosition->y + ($this->powered ?
					self::POWERED_HOVER_HEIGHT_ABOVE_TARGET :
					self::HOVER_HEIGHT_ABOVE_TARGET
			)) {
				$motionY = max(0.0, $motionY);
				$motionY += 0.3 - $motionY * 0.6;
			}
		}

		$this->motion = new Vector3($motionX, $motionY, $motionZ);

		// Rotate to moving direction, this makes wither "smoothly" turn to moving direction (java-only behavior)
		/*if (($motionX ** 2 + $motionZ ** 2) > 0.05) {
			$this->setRotation(rad2deg(atan2($motionZ, $motionX)) - 90.0, $this->location->pitch);
		}*/

		parent::tryChangeMovement();
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

	public function getDrops() : array{
		$items = parent::getDrops();

		$items[] = VanillaItems::NETHER_STAR();

		return $items;
	}

	public function getXpDropAmount() : int{
		return 50;
	}

	public function isPowered() : bool{
		return $this->powered;
	}

	/**
	 * @return $this
	 */
	public function setPowered(bool $powered = true) : self {
		$this->powered = $powered;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function getExplosionRadius() : int{
		return $this->explosionRadius;
	}

	/**
	 * @return $this
	 */
	public function setExplosionRadius(int $radius) : self {
		$this->explosionRadius = $radius;
		return $this;
	}

	public function getInvulnerableTicks() : int {
		return $this->invulnerableTicks;
	}

	/**
	 * @return $this
	 */
	public function setInvulnerableTicks(int $ticks) : self {
		$this->invulnerableTicks = $ticks;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function getBreakBlocksAroundTicks() : int {
		return $this->breakBlocksAroundTicks;
	}

	/**
	 * @return $this
	 */
	public function setBreakBlocksAroundTicks(int $ticks) : self {
		$this->breakBlocksAroundTicks = $ticks;
		return $this;
	}

	public function getBossBar() : BossBar {
		return $this->bossBar;
	}

	public function getAttackGoal() : WitherAttackGoal{
		return $this->attackGoal;
	}

	public function setNameTag(string $name) : void {
		parent::setNameTag($name);

		if (isset($this->bossBar)) {
			$this->bossBar->setTitle($this->getDisplayName());
		}
	}

	public function setMaxHealth(int $amount) : void {
		parent::setMaxHealth($amount);

		if (isset($this->bossBar)) {
			$this->bossBar->setPercentage($this->getHealth() / $this->getMaxHealth());
		}
	}

	public function setHealth(float $amount) : void {
		parent::setHealth($amount);

		if (isset($this->bossBar)) {
			$this->bossBar->setPercentage($this->getHealth() / $this->getMaxHealth());
		}
	}

	/**
	 * Returns the entity ID of the entity's left head target, or null if it doesn't have a target.
	 */
	public function getLeftHeadTargetEntityId() : ?int{
		return $this->leftHeadTargetId;
	}

	/**
	 * Returns the entity's left head target entity, or null if not found.
	 */
	public function getLeftHeadTargetEntity() : ?Entity{
		return $this->leftHeadTargetId !== null ? $this->server->getWorldManager()->findEntity($this->leftHeadTargetId) : null;
	}

	/**
	 * Sets the entity's second target entity. Passing null will remove the current target.
	 *
	 * @throws \InvalidArgumentException if the target entity is not valid
	 */
	public function setLeftHeadTargetEntity(?Entity $target) : void{
		if($target === null){
			$this->leftHeadTargetId = null;
		}elseif($target->closed){
			throw new \InvalidArgumentException("Supplied target entity is garbage and cannot be used");
		}else{
			$this->leftHeadTargetId = $target->getId();
		}
		$this->networkPropertiesDirty = true;
	}

	/**
	 * Returns the entity ID of the entity's third target, or null if it doesn't have a target.
	 */
	public function getRightHeadTargetEntityId() : ?int{
		return $this->rightHeadTargetId;
	}

	/**
	 * Returns the entity's third target entity, or null if not found.
	 */
	public function getRightHeadTargetEntity() : ?Entity{
		return $this->rightHeadTargetId !== null ? $this->server->getWorldManager()->findEntity($this->rightHeadTargetId) : null;
	}

	/**
	 * Sets the entity's third target entity. Passing null will remove the current target.
	 *
	 * @throws \InvalidArgumentException if the target entity is not valid
	 */
	public function setRightHeadTargetEntity(?Entity $target) : void{
		if($target === null){
			$this->rightHeadTargetId = null;
		}elseif($target->closed){
			throw new \InvalidArgumentException("Supplied target entity is garbage and cannot be used");
		}else{
			$this->rightHeadTargetId = $target->getId();
		}
		$this->networkPropertiesDirty = true;
	}

	public function isTargetEntity(Entity $entity) : bool{
		return ($eid = $entity->getId()) === $this->targetId ||
			$eid === $this->leftHeadTargetId ||
			$eid === $this->rightHeadTargetId
		;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if ($this->invulnerableTicks > 0) {
			$this->invulnerableTicks -= $tickDiff;
			$this->networkPropertiesDirty = true;
			if ($this->invulnerableTicks <= 0) {
				$this->explode();
			}

			// Scaled triangular distribution: fires SPAWN_SOUND_PLAY_COUNT times
			// with approximately decreasing intervals across SPAWN_DELAY_TICKS
			$t = (int) round(
				($this->invulnerableTicks - 1) *
				(self::SPAWN_SOUND_PLAY_COUNT * (self::SPAWN_SOUND_PLAY_COUNT - 1) / 2) /
				(self::SPAWN_DELAY_TICKS - 1)
			);
			$k = (int) round((sqrt(1 + 8 * $t) - 1) / 2);
			if ($k * ($k + 1) / 2 === $t) {
				$this->playSpawnSound();
			}

			$this->bossBar->setPercentage(
				(self::SPAWN_DELAY_TICKS - $this->invulnerableTicks) / self::SPAWN_DELAY_TICKS
			);
			$hasUpdate = true;
		} elseif ($this->breakBlocksAroundTicks > 0) {
			$this->breakBlocksAroundTicks -= $tickDiff;
			if ($this->breakBlocksAroundTicks <= 0) {
				// TODO: DO_MOB_GRIEFING gamerule!
				$hasUpdate = true;
				$this->breakBlocksAround();
			}
		}

		if ($this->ticksLived % self::HEAL_REGEN_CHECK_INTERVAL === 0 && $this->getHealth() < $this->getMaxHealth()) {
			$shouldHeal = true;
			$world = $this->getWorld();
			foreach($world->getCollidingEntities($this->boundingBox->expandedCopy(
				self::HEAL_REGEN_DISTANCE, self::HEAL_REGEN_DISTANCE, self::HEAL_REGEN_DISTANCE
			), $this) as $entity) {
				if (!$entity instanceof Player) {
					continue;
				}

				if ($entity->getPosition()->distanceSquared($this->location) > self::HEAL_REGEN_DISTANCE * self::HEAL_REGEN_DISTANCE) {
					continue;
				}

				$shouldHeal = false;
				break;
			}

			if ($shouldHeal) {
				$this->heal(new EntityRegainHealthEvent($this, 1, EntityRegainHealthEvent::CAUSE_REGEN));
			}
		}

		return $hasUpdate;
	}

	public function tickAi() : void{
		parent::tickAi();

		//Randomly shot a wither skull in a 12s average.
		if ($this->invulnerableTicks <= 0 && mt_rand(1, 240) === 1) {
			$yaw = 2 * M_PI * PMUtils::getRandomFloat();
			$pitch = M_PI_2 * PMUtils::getRandomFloat();
			$xz = cos($pitch);

			$this->performRangedAttackInDirection($this->getEyePos(), new Vector3(
				-$xz * sin($yaw),
				-sin($pitch),
				$xz * cos($yaw)
			), 1);
		}

		//Secondary targets selector
		if ($this->ticksLived % 10 === 0) {
			//Second target
			if (!($leftHeadTarget = $this->getLeftHeadTargetEntity()) instanceof Living ||
				!$this->targetingConditions->test($this, $leftHeadTarget)
			) {
				$this->setLeftHeadTargetEntity(null);
			}

			if ($this->leftHeadTargetId === null) {
				$this->setLeftHeadTargetEntity($this->findSecondaryTarget());
			}

			//Third target
			if (!($rightHeadTarget = $this->getRightHeadTargetEntity()) instanceof Living ||
				!$this->targetingConditions->test($this, $rightHeadTarget)
			) {
				$this->setRightHeadTargetEntity(null);
			}

			if ($this->rightHeadTargetId === null) {
				$this->setRightHeadTargetEntity($this->findSecondaryTarget());
			}
		}
	}

	private function findSecondaryTarget() : ?Living {
		$target = null;
		$targetDistanceSquared = INF;

		$range = $this->getFollowRange();
		foreach ($this->getWorld()->getCollidingEntities(
			$this->boundingBox->expandedCopy($range, $range, $range), $this
		) as $entity) {
			if ($this->isTargetEntity($entity)) {
				continue;
			}

			if (!$entity instanceof Living || !$this->targetingConditions->test($this, $entity)) {
				continue;
			}

			$distanceSquared = $entity->getLocation()->distanceSquared($this->location);
			if ($distanceSquared > $targetDistanceSquared) {
				continue;
			}

			$target = $entity;
			$targetDistanceSquared = $distanceSquared;
		}

		return $target;
	}

	/**
	 * Unlike other entities the Wither doesn't immedatly handle drops on death.
	 * It does after death animation, so we need to override this.
	 */
	protected function onDeath() : void {
		$this->startDeathAnimation();
	}

	protected function onDeathUpdate(int $tickDiff) : bool{
		if (parent::onDeathUpdate($tickDiff)) {
			$this->explode();

			$ev = new EntityDeathEvent($this, $this->getDrops(), $this->getXpDropAmount());
			$ev->call();

			$world = $this->getWorld();
			foreach($ev->getDrops() as $item){
				$world->dropItem($this->location, $item)?->setDespawnDelay(ItemEntity::NEVER_DESPAWN);
			}

			$world->dropExperience($this->location, $ev->getXpDropAmount());
			return true;
		}

		// Death sounds
		if ($this->deadTicks < $this->maxDeadTicks) {
			$this->networkPropertiesDirty = true;

			$oneThirdMaxDeadTicks = (int) floor($this->maxDeadTicks * 3 / 10);
			if ($this->deadTicks === $oneThirdMaxDeadTicks) {
				$this->broadcastSound(new DeathSound($this));
			} elseif ($this->deadTicks === $oneThirdMaxDeadTicks * 2) {
				$this->broadcastSound(new DeathSound($this, DeathSound::MID_VOLUME));
			}
		}

		// entityBaseTick() normally handles dirty metadata however it's not called on this state
		$changedProperties = $this->getDirtyNetworkData();
		if(count($changedProperties) > 0){
			$this->sendData(null, $changedProperties);
			$this->getNetworkProperties()->clearDirtyProperties();
		}

		return false;
	}

	protected function startDeathAnimation() : void{
		parent::startDeathAnimation();
		$this->broadcastSound(new DeathSound($this, DeathSound::MIN_VOLUME));
	}

	public function canAddEffect(EffectInstance $effect) : bool{
		return $effect->getType() instanceof InstantEffect;
	}

	public function attack(EntityDamageEvent $source) : void {
		$cause = $source->getCause();

		if ($cause === EntityDamageEvent::CAUSE_FALL) {
			$source->cancel();
		}

		// Invulnerability on spawn
		if ($this->invulnerableTicks > 0 && $cause !== EntityDamageEvent::CAUSE_SUICIDE) {
			$source->cancel();
		}

		// Explosions invulnerability
		if ($cause === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION ||
			$cause === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION
		) {
			$source->cancel();
		}

		if ($source instanceof EntityDamageByEntityEvent) {
			// Projectile invulnerability when powered unless it's a deflected skull
			if (!$source->isCancelled() && $this->powered && $cause === EntityDamageEvent::CAUSE_PROJECTILE) {
				if ($source instanceof EntityDamageByChildEntityEvent) {
					$projectile = $source->getChild();
				} else {
					$projectile = $source->getDamager();
				}

				if ($projectile instanceof Projectile && !(
					$projectile instanceof WitherSkull && $projectile->wasDeflected()
				)) {
					$source->cancel();

					// Not the best way but pmmp despawns arrows immediatly onEntityHit :(
					if ($projectile instanceof Arrow) {
						$spawnLoc = $projectile->getLocation();
						$spawnLoc->yaw = fmod($spawnLoc->yaw + 170 + (20 * PMUtils::getRandomFloat()), 360);

						$arrow = new Arrow($spawnLoc, $this, $projectile->isCritical());
						$arrow->setMotion($projectile->getMotion()->multiply(-0.25)); //java uses -0.5 * 0.2 = -0.01
						$arrow->spawnToAll();
					}
				}
			}
		}

		parent::attack($source);

		// Damage tracker, useful for targeting
		$this->attackDamageTracker($source);

		// Blocks destruction on damage
		if (!$source->isCancelled() && $this->breakBlocksAroundTicks <= 0) {
			$this->breakBlocksAroundTicks = 20;
		}
	}

	public function breakBlocksAround() : void {
		// bedrock wither destroys a 4×4×6 area
		$playBreakSound = false;

		$width = (int) floor($this->size->getWidth()) + 1;
		$height = (int) floor($this->size->getHeight()) + 1;

		$world = $this->getWorld();
		$pos = $this->location->floor();
		$airBlock = VanillaBlocks::AIR();
		$airItem = VanillaItems::AIR();
		for ($dx = -$width + 1; $dx <= $width; $dx++) {
			for ($dz = -$width + 1; $dz <= $width; $dz++) {
				for ($dy = -1; $dy <= $height; $dy++) {
					$p = $pos->add($dx, $dy, $dz);
					$b = $world->getBlock($p);
					if ($b->getTypeId() === BlockTypeIds::AIR || !$b->getBreakInfo()->isBreakable()) {
						continue;
					}

					$world->setBlock($p, $airBlock);
					foreach ($b->getDropsForCompatibleTool($airItem) as $drop) {
						$world->dropItem($p, $drop);
					}
					$playBreakSound = true;
				}
			}
		}

		if ($playBreakSound) {
			$this->broadcastSound(new EntityBreakBlockSound($this));
		}
	}

	public function performRangedAttack(Entity $target, float $force) : void{
		$eyePos = $this->getEyePos();
		$this->performRangedAttackInDirection($eyePos, $target->getPosition()->subtractVector($eyePos), $force);
	}

	private function performRangedAttackInDirection(Vector3 $position, Vector3 $direction, float $force) : void{
		$location = Location::fromObject($position, $this->getWorld(), $this->location->yaw, $this->location->pitch);

		if ($force >= 1) {
			$projectile = new DangerousWitherSkull($location, $this);
			$projectile->setDeflectable();
		} else {
			$projectile = new WitherSkull($location, $this);
		}

		$projectile->setMotion($direction->normalize());
		$projectile->spawnToAll();

		$this->doAttackAnimation();
	}

	public function doAttackAnimation() : void{
		$this->broadcastSound(new EntityShootSound($this));
		$this->broadcastSound(new BowShootSound());
	}

	public function getIntervalTicksByHealth(int $min, int $max) : int {
		if ($min > $max) {
			throw new \InvalidArgumentException("Min interval cannot be greater than max.");
		}

		$halfMaxHealth = $this->getMaxHealth() / 2;
		return (int) ceil(
			$min + ($max - $min) * ((((int) ceil($this->getHealth()) - 1) % $halfMaxHealth) / $halfMaxHealth)
		);
	}

	public function spawnTo(Player $player) : void{
		parent::spawnTo($player);
		$this->bossBar->addPlayer($player);
	}

	public function despawnFrom(Player $player, bool $send = true) : void{
		if ($send && isset($this->hasSpawned[spl_object_id($player)])) {
			$this->bossBar->removePlayer($player);
		}

		parent::despawnFrom($player, $send);
	}

	public function despawnFromAll() : void {
		$this->bossBar->removeAllPlayers();
		parent::despawnFromAll();
	}

	protected function destroyCycles() : void{
		unset(
			$this->bossBar,
			$this->targetingConditions,
			$this->explodeOnHalfLifeWrapped
		);

		parent::destroyCycles();
	}
}
