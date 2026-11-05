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

use IvanCraft623\MobPlugin\CustomTimings;
use IvanCraft623\MobPlugin\despawning\DespawnPersistenceTrait;
use IvanCraft623\MobPlugin\entity\ai\Brain;
use IvanCraft623\MobPlugin\entity\ai\control\IdleMoveControl;
use IvanCraft623\MobPlugin\entity\ai\control\JumpControl;
use IvanCraft623\MobPlugin\entity\ai\control\LookControl;
use IvanCraft623\MobPlugin\entity\ai\control\MoveControl;
use IvanCraft623\MobPlugin\entity\ai\control\MovementInput;
use IvanCraft623\MobPlugin\entity\ai\goal\GoalSelector;
use IvanCraft623\MobPlugin\entity\ai\sensing\Sensing;
use IvanCraft623\MobPlugin\loot\LootTable;
use IvanCraft623\MobPlugin\MobPlugin;
use IvanCraft623\MobPlugin\Settings;
use IvanCraft623\MobPlugin\sound\EntitySpawnSound;
use IvanCraft623\MobPlugin\sound\MobWarningSound;
use IvanCraft623\MobPlugin\utils\Utils;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\InstantHealthEffect;
use pocketmine\entity\effect\PoisonEffect;
use pocketmine\entity\effect\RegenerationEffect;
use pocketmine\entity\effect\WitherEffect;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\MeleeWeaponEnchantment;
use pocketmine\item\Item;
use pocketmine\item\Releasable;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Random;
use pocketmine\world\Position;
use pocketmine\world\sound\ItemBreakSound;
use pocketmine\world\World;
use function assert;
use function basename;
use function cos;
use function count;
use function debug_backtrace;
use function implode;
use function max;
use function round;
use function sin;
use function sqrt;
use function str_replace;
use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const M_PI;

abstract class Mob extends Living {
	use DespawnPersistenceTrait {
		initEntity as initDespawnPersistence;
		saveNBT as saveDespawnPersistenceNBT;
	}
	//TODO!

	private const TAG_COMPONENT_GROUPS = "definitions"; //TAG_List

	protected Random $random;

	protected ComponentGroups $componentGroups;

	/** Latest hit by an entity, forgotten after 100 ticks. Not DamageTracker's per-entity totals. */
	protected ?EntityDamageByEntityEvent $lastDamageByEntity = null;

	protected int $lastDamageByEntityTick = -1; //server tick

	protected bool $hasBeenDamagedByPlayer = false;

	protected Brain $brain;

	protected LookControl $lookControl;

	protected MoveControl $moveControl;

	protected JumpControl $jumpControl;

	protected GoalSelector $goalSelector;

	protected GoalSelector $targetSelector;

	protected Sensing $sensing;

	protected MovementInput $movementInput;

	protected bool $hasAi = true;

	protected Attribute $attackDamageAttr;

	protected Attribute $attackKnockbackAttr;

	protected Attribute $airSpeedAttr;

	protected Attribute $followRangeAttr;

	private bool $invertingHealAndDamageEffect = false;

	protected ?string $settingsWorld = null;

	protected ?Settings $settings = null;

	public function getSettings() : Settings{
		//Cache per world: Settings::getSettings() does a static lookup plus path construction every call,
		//and Mob::entityBaseTick() hits it multiple times per tick.
		$world = $this->getWorld()->getFolderName();
		if ($this->settingsWorld !== $world) {
			$this->settingsWorld = $world;
			$this->settings = Settings::getSettings($world);
		}
		if ($this->settings === null) {
			throw new AssumptionFailedError("Settings should have been initialized");
		}
		return $this->settings;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->initProperties();

		$this->initDespawnPersistence($nbt);

		$this->random = MobPlugin::getInstance()->getRandom();

		if (($componentGroupsTag = $nbt->getTag(self::TAG_COMPONENT_GROUPS)) instanceof ListTag) {
			$this->componentGroups = ComponentGroups::fromListTag($componentGroupsTag);
		} else {
			$this->componentGroups = new ComponentGroups();
			if ($this->canSaveWithChunk()) {
				$this->componentGroups->add(EntityFactory::getInstance()->getSaveId($this::class));
			}
		}

		if ($nbt->count() === 0) { //Entity just created!
			$this->onCreate();
		}

		MobPlugin::getInstance()->trackEntity($this);

		$this->goalSelector = new GoalSelector();
		$this->targetSelector = new GoalSelector();
		$this->lookControl = new LookControl($this);
		$this->movementInput = new MovementInput();
		$this->moveControl = $this->createMoveControl();
		$this->jumpControl = new JumpControl($this);
		$this->sensing = new Sensing($this);

		$this->brain = $this->makeBrain();

		$this->registerGoals();
	}

	protected function initProperties() : void{
		$this->moveSpeedAttr->setDefaultValue($this->getDefaultMovementSpeed());
		$this->moveSpeedAttr->setValue($this->getDefaultMovementSpeed());
	}

	protected function registerGoals() : void{
	}

	protected function addAttributes() : void{
		parent::addAttributes();

		$this->attributeMap->add($this->attackDamageAttr = AttributeFactory::getInstance()->mustGet(Attribute::ATTACK_DAMAGE));
		$this->attributeMap->add($this->attackKnockbackAttr = AttributeFactory::getInstance()->mustGet(CustomAttributes::ATTACK_KNOCKBACK));
		$this->attributeMap->add($this->airSpeedAttr = AttributeFactory::getInstance()->mustGet(CustomAttributes::AIR_MOVEMENT));

		$this->followRangeAttr = $this->attributeMap->get(Attribute::FOLLOW_RANGE) ?? throw new AssumptionFailedError("Follow range attribute is null");
		;
	}

	public function getLookControl() : LookControl {
		return $this->lookControl;
	}

	/**
	 * How this mob moves toward the position its goals want. Idle for mobs that don't.
	 */
	protected function createMoveControl() : MoveControl{
		return new IdleMoveControl($this);
	}

	public function getMoveControl() : MoveControl {
		return $this->moveControl;
	}

	public function getJumpControl() : JumpControl {
		return $this->jumpControl;
	}

	public function getSensing() : Sensing {
		return $this->sensing;
	}

	public function getMobType() : MobType{
		return MobType::UNDEFINED();
	}

	public function getMovementInput() : MovementInput{
		return $this->movementInput;
	}

	/**
	 * Returns acceleration while in water.
	 */
	public function getWaterMovementSpeed() : float{
		return $this->getMovementSpeed();
	}

	/**
	 * Returns acceleration while airborne.
	 */
	public function getAirMovementSpeed() : float{
		return $this->airSpeedAttr->getValue();
	}

	/**
	 * Sets acceleration while airborne.
	 */
	public function setAirMovementSpeed(float $airSpeed) : void{
		$this->airSpeedAttr->setValue($airSpeed);
	}

	public function hasAi() : bool{
		return $this->hasAi;
	}

	public function setHasAi(bool $value = true) : void{
		$this->hasAi = $value;
		$this->networkPropertiesDirty = true;
	}

	public function getMaxPitchRot() : int {
		return 40;
	}

	public function getMaxYawRot() : int {
		return 75;
	}

	public function getRotSpeed() : int {
		return 10;
	}

	public function getAmbientSoundInterval() : float{
		return 8;
	}

	public function getAmbientSoundIntervalRange() : float{
		return 16;
	}

	/**
	 * The sound event the client plays as this mob's ambient sound.
	 */
	public function getAmbientSoundEvent() : string{
		return LevelSoundEvent::AMBIENT;
	}

	public function getAttackDamage() : float{
		return $this->attackDamageAttr->getValue();
	}

	public function setAttackDamage(float $damage) : void{
		$this->attackDamageAttr->setValue($damage);
	}

	public function getAttackKnockback() : float{
		return $this->attackKnockbackAttr->getValue();
	}

	public function setAttackKnockback(float $kb) : void{
		$this->attackKnockbackAttr->setValue($kb);
	}

	public function getFollowRange() : float{
		return $this->followRangeAttr->getValue();
	}

	public function setFollowRange(float $range) : void{
		$this->followRangeAttr->setValue($range);
	}

	protected function makeBrain() : Brain{
		return new Brain([], [], []);
	}

	public function getBrain() : Brain{
		return $this->brain;
	}

	public function getRandom() : Random {
		return $this->random;
	}

	public function getDefaultMovementSpeed() : float {
		return 0.25;
	}

	public function canAttack(Entity $target) : bool {
		if (!$target->isAlive()) {
			return false;
		}

		if ($this->getWorld()->getDifficulty() === World::DIFFICULTY_PEACEFUL) {
			return false;
		}

		if ($target instanceof Player && $target->isCreative()) {
			return false;
		}

		return true;
	}

	public function canSee(Entity $entity) : bool{
		$start = $this->getEyePos();
		$end = $entity->getEyePos();
		if ($start->distanceSquared($end) > 0.01) {
			foreach(VoxelRayTrace::betweenPoints($start, $end) as $vector3){
				$block = $this->getWorld()->getBlockAt((int) $vector3->x, (int) $vector3->y, (int) $vector3->z);

				if($block->getTypeId() !== BlockTypeIds::AIR && $block->calculateIntercept($start, $end) !== null){
					return false;
				}
			}
		}
		return true;
	}

	public function getFluidJumpThreshold() : float{
		return $this->getEyeHeight() < 0.4 ? 0 : 0.4;
	}

	public function canStandOnFluid(Liquid $liquid) : bool{
		return false;
	}

	public function setLastDamageCause(EntityDamageEvent $type) : void{
		parent::setLastDamageCause($type);

		if ($type instanceof EntityDamageByEntityEvent) {
			$this->setLastDamageByEntity($type);

			if ($type->getDamager() instanceof Player) {
				$this->hasBeenDamagedByPlayer = true;
			}
		}
	}

	public function getLastDamageByEntity() : ?EntityDamageByEntityEvent{
		return $this->lastDamageByEntity;
	}

	public function setLastDamageByEntity(?EntityDamageByEntityEvent $type) : void{
		$this->lastDamageByEntity = $type;
		if ($type === null) {
			$this->lastDamageByEntityTick = -1;
		} else {
			$this->lastDamageByEntityTick = $this->getWorld()->getServer()->getTick();
		}
	}

	public function getLastDamageByEntityTick() : int{
		return $this->lastDamageByEntityTick;
	}

	public function hasBeenDamagedByPlayer() : bool{
		return $this->hasBeenDamagedByPlayer;
	}

	public function getLightLevelDependentMagicValue() : float{
		return Utils::getLightLevelDependentMagicValue(Position::fromObject($this->getEyePos(), $this->location->world));
	}

	public function spawnTo(Player $player) : void{
		parent::spawnTo($player);
		$this->playSpawnSound([$player]);
	}

	/**
	 * Called when this entity has just been created and is completely clean.
	 */
	public function onCreate() : void{
		$this->generateEquipment();
	}

	/**
	 * Called when the entity is created to give it its starting gear.
	 * Subclasses must equip the items themselves.
	 */
	public function generateEquipment() : void{}

	/**
	 * @param Player[]|null $targets
	 */
	public function playSpawnSound(?array $targets = null) : void{
		$this->broadcastSound(new EntitySpawnSound($this), $targets);
	}

	public function getMaxFallDistance() : int{
		$defaultMax = 3;
		if ($this->targetId === null) {
			return $defaultMax;
		}

		$maxFallDistance = (int) ($this->getHealth() - $this->getMaxHealth() / 3);
		$maxFallDistance -= (3 - $this->getWorld()->getDifficulty()) * 4;

		if ($maxFallDistance < 0) {
			$maxFallDistance = 0;
		}

		return $maxFallDistance + $defaultMax;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->hasAi){
			CustomTimings::$entityAiTick->startTiming();
			$this->tickAi();
			CustomTimings::$entityAiTick->stopTiming();
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);

		if ($this->lastDamageByEntity !== null &&
			$this->lastDamageByEntityTick !== -1 &&
			$this->getWorld()->getServer()->getTick() - $this->lastDamageByEntityTick > 100
		) {
			//The last damager is only remembered for a limited time.
			$this->setLastDamageByEntity(null);
		}

		if ($this->getSettings()->isDebugModeEnabled() && $this->ticksLived % 10 === 0) {
			$this->networkPropertiesDirty = true; // the debug info is sent in place of the name tag
		}

		//TODO: leash check

		return $hasUpdate;
	}

	/**
	 * @return string[]
	 */
	protected function getCurrentDebugInfo() : array{
		$data = [];
		foreach ($this->goalSelector->getRunningGoals() as $wrappedGoal) {
			$goalInfo = $wrappedGoal->getCurrentDebugInfo();
			$data[] = basename(str_replace('\\', '/', $wrappedGoal->goal::class)) . ($goalInfo === null ?
				"" : " (" . $goalInfo . ")"
			);
		}

		$data[] = "Healt: " . round($this->getHealth(), 1);

		return $data;
	}

	/**
	 * The AI pipeline, then tickState(). Runs every tick while the mob has AI.
	 */
	final protected function tickAi() : void{
		$this->sensing->tick();

		CustomTimings::$goalSelector->startTiming();

		$n = $this->ticksLived + $this->getId();
		if ($n % 2 !== 0 && !$this->justCreated) {
			$this->targetSelector->tickRunningGoals(false);
			$this->goalSelector->tickRunningGoals(false);
		} else {
			$this->targetSelector->tick();
			$this->goalSelector->tick();
		}

		CustomTimings::$goalSelector->stopTiming();

		CustomTimings::$entityControls->startTiming();
		$this->moveControl->tick();
		$this->lookControl->tick();
		$this->jumpControl->tick();
		CustomTimings::$entityControls->stopTiming();

		// Movement update
		$this->movementInput->decay(0.98);
		//TODO: is being controlled by passenger check!
		if (!$this->movementInput->isIdle() || $this->isCollidedHorizontally) {
			CustomTimings::$entityTravel->startTiming();
			$this->travel($this->movementInput->getSideways(), $this->movementInput->getUpward(), $this->movementInput->getForward());
			CustomTimings::$entityTravel->stopTiming();
		}

		$this->tickState();
	}

	/**
	 * The mob's own timers and reactions to its surroundings (aging, love, sun burning...), run after the AI
	 * pipeline. Call the parent first.
	 */
	protected function tickState() : void{
	}

	public function travel(float $sideways, float $upward, float $forward) : void{
		// TODO: More complex movement suff :P

		if ($this->isInWater()) {
			$accel = $this->getWaterMovementSpeed();
		} else {
			$accel = $this->onGround || $this->isInLava() ?
				$this->getMovementSpeed() : $this->getAirMovementSpeed();
		}

		$motionX = $motionY = $motionZ = 0.0;
		$lengthSquared = ($sideways ** 2) + ($upward ** 2) + ($forward ** 2);
		if ($lengthSquared >= 1.0E-7) {
			$scale = $lengthSquared > 1 ? $accel / sqrt($lengthSquared) : $accel;
			$sideways *= $scale;
			$forward *= $scale;
			$sin = sin($this->location->yaw * (M_PI / 180));
			$cos = cos($this->location->yaw * (M_PI / 180));
			$motionX = $sideways * $cos - $forward * $sin;
			$motionY = $upward * $scale;
			$motionZ = $forward * $cos + $sideways * $sin;
		}

		//Climb stuff
		if ($this->isCollidedHorizontally && $this->onClimbable()) {
			$motionY = 0.2 - $this->motion->y;
		}

		$this->addMotion($motionX, $motionY, $motionZ);
	}

	public function canUseReleasable(Releasable $item) : bool{
		return false;
	}

	public function getPerceivedDistanceSqrForMeleeAttack(Entity $target) : float{
		//TODO: Camels Y extra!
		return $this->location->distanceSquared($target->getPosition());
	}

	public function doAttackAnimation() : void{
		$this->broadcastAnimation(new ArmSwingAnimation($this));
	}

	public function canAddEffect(EffectInstance $effect) : bool{
		if ($this->getMobType()->equals(MobType::UNDEAD()) &&
			(($type = $effect->getType()) instanceof RegenerationEffect || $type instanceof PoisonEffect || $type instanceof WitherEffect)
		) {
			return false;
		}

		return true;
	}

	/**
	 * TODO: HACK! PMMP core's `InstantHealthEffect::applyEffect()` completely skips
	 * the call to `Entity::heal()` when the entity is already at its real max
	 * health, roughly:
	 *
	 *     if ($entity->getHealth() < $entity->getMaxHealth()) {
	 *         $entity->heal(...);
	 *     }
	 *
	 * Since undead mobs invert Instant Health into damage via our `heal()`
	 * override, an undead entity already at full health would never receive
	 * that damage: PM's guard above prevents `heal()` from being called
	 * at all in that case.
	 */
	public function getMaxHealth() : int {
		$actualMaxHealth = parent::getMaxHealth();
		if ($this->getHealth() < $actualMaxHealth) {
			return $actualMaxHealth;
		}

		if ($this->getMobType()->equals(MobType::UNDEAD()) &&
			(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? null) === InstantHealthEffect::class
		) {
			return $actualMaxHealth + 1;
		}

		return $actualMaxHealth;
	}

	/**
	 * A very hacky way to implement the reversal of instant damage and
	 * instant healing effects, due to pocketmine API limitations.
	 */
	public function attack(EntityDamageEvent $source) : void {
		$invertHealAndDamageEffect = !$this->invertingHealAndDamageEffect &&
			$this->getMobType()->equals(MobType::UNDEAD()) &&
			$source->getCause() === EntityDamageEvent::CAUSE_MAGIC
		;

		if ($invertHealAndDamageEffect) {
			$source->cancel();
		}

		parent::attack($source);

		if ($source->isCancelled() && $invertHealAndDamageEffect) {
			$this->invertingHealAndDamageEffect = true;
			$this->heal(new EntityRegainHealthEvent(
				$this,
				$source->getOriginalBaseDamage() * (4 / 6),
				EntityRegainHealthEvent::CAUSE_MAGIC)
			);
			$this->invertingHealAndDamageEffect = false;
		}
	}

	/**
	 * A very hacky way to implement the reversal of instant damage and
	 * instant healing effects, due to pocketmine API limitations.
	 */
	public function heal(EntityRegainHealthEvent $source) : void {
		$invertHealAndDamageEffect = !$this->invertingHealAndDamageEffect &&
			$this->getMobType()->equals(MobType::UNDEAD()) &&
			$source->getRegainReason() === EntityRegainHealthEvent::CAUSE_MAGIC
		;

		if ($invertHealAndDamageEffect) {
			$source->cancel();
		}

		parent::heal($source);

		if ($source->isCancelled() && $invertHealAndDamageEffect) {
			$this->invertingHealAndDamageEffect = true;
			$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_MAGIC, $source->getAmount() * (6 / 4)));
			$this->invertingHealAndDamageEffect = false;
		}
	}

	/**
	 * Attacks the given entity with the currently-held item.
	 * TODO: make a PR that implements this un PM core.
	 *
	 * @return bool if the entity was dealt damage
	 */
	public function attackEntity(Entity $entity) : bool{
		if(!$entity->isAlive()){
			return false;
		}

		$heldItem = $this->inventory->getItemInHand();
		$oldItem = clone $heldItem;

		$itemAttackPoints = $heldItem->getAttackPoints();
		$attackPoints = $this->getAttackDamage();
		$baseDamage = $itemAttackPoints <= 1 ? $attackPoints : $attackPoints + $itemAttackPoints;

		$ev = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $baseDamage);
		$ev->setKnockBack($this->getAttackKnockback());

		$meleeEnchantmentDamage = 0;
		/** @var EnchantmentInstance[] $meleeEnchantments */
		$meleeEnchantments = [];
		foreach($heldItem->getEnchantments() as $enchantment){
			$type = $enchantment->getType();
			if($type instanceof MeleeWeaponEnchantment && $type->isApplicableTo($entity)){
				$meleeEnchantmentDamage += $type->getDamageBonus($enchantment->getLevel());
				$meleeEnchantments[] = $enchantment;
			}
		}
		$ev->setModifier($meleeEnchantmentDamage, EntityDamageEvent::MODIFIER_WEAPON_ENCHANTMENTS);

		$entity->attack($ev);

		$this->doAttackAnimation();

		if ($this->isOnFire()) {
			$entity->setOnFire(8);
		}

		foreach($meleeEnchantments as $enchantment){
			$type = $enchantment->getType();
			assert($type instanceof MeleeWeaponEnchantment);
			$type->onPostAttack($this, $entity, $enchantment->getLevel());
		}

		if($this->isAlive()){
			//reactive damage like thorns might cause us to be killed by attacking another mob, which
			//would mean we'd already have dropped the inventory by the time we reached here

			$returnedItems = []; //TODO: do something with returned items
			if($heldItem->onAttackEntity($entity, $returnedItems) && $oldItem->equalsExact($this->inventory->getItemInHand())){
				if($heldItem instanceof Durable && $heldItem->isBroken()){
					$this->broadcastSound(new ItemBreakSound());
				}
				$this->inventory->setItemInHand($heldItem);
			}
		}

		return true;
	}

	public function performRangedAttack(Entity $target, float $force) : void{
	}

	public function canStandAt(Vector3 $pos) : bool{
		$world = $this->getWorld();

		$below = $world->getBlockAt($pos->getFloorX(), $pos->getFloorY() - 1, $pos->getFloorZ());
		if (!$below->isSolid()) {
			return false;
		}

		$diff = $pos->subtractVector($this->location);
		return count($world->getCollisionBlocks($this->boundingBox->offsetCopy($diff->x, $diff->y, $diff->z), true)) === 0;
	}

	public function onRandomTeleport(Vector3 $from, Vector3 $to) : void{
	}

	public function setTargetEntity(?Entity $target) : void{
		if ($target !== null && $target->getId() !== $this->targetId) {
			$this->broadcastSound(new MobWarningSound($this));
		}

		parent::setTargetEntity($target);
	}

	/**
	 * Rolls a table for this mob's death, credited to whoever killed it.
	 *
	 * @return Item[]
	 */
	protected function generateDeathLoot(LootTable $table) : array{
		$cause = $this->getLastDamageCause();

		return $this->generateLoot($table, $cause instanceof EntityDamageByEntityEvent ? $cause->getDamager() : null);
	}

	/**
	 * Rolls a table for a drop that $source caused with $tool (its held item if null).
	 *
	 * @return Item[]
	 */
	protected function generateLoot(LootTable $table, ?Entity $source = null, ?Item $tool = null) : array{
		return $table->generateFor($this, $this->random, $source, $tool);
	}

	/**
	 * Drops what a table rolls for a drop that $source caused with $tool, above this mob's head.
	 */
	protected function dropLoot(LootTable $table, ?Entity $source, ?Item $tool) : void{
		$position = $this->location->add(0, $this->getSize()->getHeight(), 0);
		foreach ($this->generateLoot($table, $source, $tool) as $item) {
			$this->getWorld()->dropItem($position, $item);
		}
	}

	public function onEat() : void{
	}

	public function onLightningBoltHit() : bool{
		return false;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setFloat(EntityMetadataProperties::AMBIENT_SOUND_INTERVAL_MIN, $this->getAmbientSoundInterval());
		$properties->setFloat(EntityMetadataProperties::AMBIENT_SOUND_INTERVAL_RANGE, $this->getAmbientSoundIntervalRange());
		$properties->setString(EntityMetadataProperties::AMBIENT_SOUND_EVENT, $this->getAmbientSoundEvent());

		$properties->setGenericFlag(EntityMetadataFlags::NO_AI, $this->noClientPredictions || !$this->hasAi);

		if ($this->getSettings()->isDebugModeEnabled()) {
			// Only on the network: the real name tag is saved, and makes the mob persistent.
			$properties->setString(EntityMetadataProperties::NAMETAG, implode("\n", $this->getCurrentDebugInfo()));
			$properties->setByte(EntityMetadataProperties::ALWAYS_SHOW_NAMETAG, 1);
			$properties->setGenericFlag(EntityMetadataFlags::CAN_SHOW_NAMETAG, true);
		}
	}

	protected function onDispose() : void{
		MobPlugin::getInstance()->untrackEntity($this);

		parent::onDispose();
	}

	public function saveNBT() : CompoundTag{
		$nbt = $this->saveDespawnPersistenceNBT();

		$nbt->setTag(self::TAG_COMPONENT_GROUPS, $this->componentGroups->toListTag());

		return $nbt;
	}

	protected function destroyCycles() : void{
		$this->lastDamageByEntity = null;
		unset($this->brain);
		$this->goalSelector->stopRunningGoals();
		$this->targetSelector->stopRunningGoals();
		unset(
			$this->goalSelector,
			$this->targetSelector,
			$this->lookControl,
			$this->moveControl,
			$this->jumpControl,
			$this->sensing
		);

		parent::destroyCycles();
	}
}
