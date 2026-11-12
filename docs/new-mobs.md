# Implementing a new mob

How to port a vanilla Bedrock mob. Navigation, spawning and despawning have their own
docs: [navigation.md](navigation.md), [spawning.md](spawning.md),
[despawning.md](despawning.md). All mob AI is built from goals.

Paths below are relative to `src/IvanCraft623/MobPlugin/`.

## Checklist

1. The entity class, under `entity/{monster,animal,golem,ambient,boss}/`.
2. Register it, see [Registering](#registering): `ALL_ENTITIES`, a despawn rule, a spawn egg
   and a handler for each spawn event its rules send.
3. Run php-cs-fixer, PHPStan, `composer test`, and build the phar.

Natural spawning is wired automatically for vanilla mobs, see
[Natural spawning](#natural-spawning).

## Porting from the Bedrock definition

The pinned `mojang/bedrock-samples` is in `vendor/mojang/bedrock-samples/`. A mob's
`behavior_pack/entities/<name>.json` is the source for its stats:

| Bedrock component | Port |
|---|---|
| `minecraft:health` | `setMaxHealth()` |
| `minecraft:movement` | `getDefaultMovementSpeed()` |
| `minecraft:attack` | `setAttackDamage()` |
| `minecraft:type_family` (`undead`, `arthropod`) | `getMobType()` |
| `minecraft:experience_reward`, `minecraft:loot` | `getXpDropAmount()`, `getDrops()` |
| `minecraft:collision_box` | `VanillaEntitySizes`, generated |

The behaviors are **not** a 1:1 source for `registerGoals()`. Priorities are not copied,
some goals aren't vanilla ports, and a few are registered that the JSON doesn't declare.
Start from `registerGoals()` of the closest existing mob and use the JSON to check what
the mob does. Where the models differ:

- **Not every behavior is a goal.** `burns_in_daylight` is `Monster::isSunSensitive()`,
  `breedable` is `Animal` state, `interact` is `onInteract()`, `angry` and `on_calm` are
  `NeutralMob`, `shooter` is `performRangedAttack()`, `equip_item` is `ItemPickupCapable::pickupItem()`.
  `environment_sensor`, `timer` and component-group swaps have no runtime: re-express them
  as fields and inline checks.
- **Some goals are not behaviors.** `RandomTeleportGoal` is the `teleport` component,
  `AvoidSunlightGoal` is the `avoid_sun` navigation flag, `BreakDoorGoal` is `break_door`.
- **One behavior, several goals, or the reverse.** `nearest_attackable_target` lists several
  types; add one `NearestAttackableGoal` per type. `melee_attack` and `melee_box_attack`
  are both `MeleeAttackGoal`.
- **Behavior arguments we don't expose.** Read the goal before assuming it matches the JSON.
- **Unported pieces are TODOs in the mob's class**, e.g. village goals, riding, jockeys.

## Choosing a base class

```
Living → Mob → PathfinderMob → Monster      hostile, implements Enemy
                             → AgeableMob → Animal   baby/adult, breeding, feeding
                             → Golem
              → Ambient                     Bat; extends Mob, no pathfinding
```

| Base | Adds |
|---|---|
| `Mob` | goal selectors, controls, sensing, drops, settings |
| `PathfinderMob` | the navigation and `getWalkTargetValue()`. Most goals require it. |
| `Monster` | 5 XP when hurt by a player, `isSunSensitive()`, the block-light-0 spawn condition |
| `Animal` | age, love and breeding, grass preference. Implement `isFood()` and `getBreedOffspring()`. |
| `Golem` | ambient sound interval only |

A mob that doesn't path like the others can extend `Mob` and implement `Enemy` itself
(`Slime`).

Optional capabilities are interfaces; the example shows the wiring:

| Interface | For | Example |
|---|---|---|
| `Powerable` | charged variants | `Creeper` |
| `Saddleable`, `ItemSteerable` | riding | `Pig` |
| `Shearable` | shearing | `Sheep` |
| `RangedAttackMob` | `performRangedAttack()`, needed by the ranged goals | `AbstractSkeleton` |
| `NeutralMob` + `NeutralMobTrait` | angry for a while after provocation | `Enderman` |
| `ItemPickupCapable` + trait | picks up and equips loot | `Zombie` |
| `DoorBreaker` + trait | a share of the spawns break doors | `Zombie` |
| `DamageTracker` + trait | `TargetHighestDamagerGoal` | `Wither` |
| `Flocking` + `FlockingTrait` | moves in a flock | `AbstractFish` |

## A minimal mob

```php
class Silverfish extends Monster{

	public static function getNetworkTypeId() : string{ return EntityIds::SILVERFISH; }

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::SILVERFISH_HEIGHT, VanillaEntitySizes::SILVERFISH_WIDTH);
	}

	public function getName() : string{
		return "Silverfish";
	}

	public function getMobType() : MobType{
		return MobType::ARTHROPOD();
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setMaxHealth(8);
		$this->setAttackDamage(1);
	}

	public function getDefaultMovementSpeed() : float{
		return 0.25;
	}

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(1, new FloatGoal($this));
		$this->goalSelector->addGoal(4, new MeleeAttackGoal($this, 1));
		$this->goalSelector->addGoal(5, new WaterAvoidingRandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(7, new LookAtEntityGoal($this, Player::class, 8));
		$this->goalSelector->addGoal(8, new RandomLookAroundGoal($this));

		$this->targetSelector->addGoal(1, new HurtByTargetGoal($this));
		$this->targetSelector->addGoal(2, new NearestAttackableGoal($this, Player::class));
	}

	public function getXpDropAmount() : int{
		return $this->hasBeenDamagedByPlayer() ? 5 : 0;
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::SILVERFISH_SPAWN_EGG();
	}
}
```

- `getNetworkTypeId()` uses PocketMine's `EntityIds`, not `data/bedrock/EntityIds`.
- Sizes come from the generated `VanillaEntitySizes`. Slime sizes and baby variants are not in
  the data.
- `getName()` is also the bStats key.
- `initProperties()` runs first in `Mob::initEntity()`. Anything a goal reads at
  construction must be set there: `registerGoals()` runs inside `initEntity()`, so fields
  assigned after `parent::initEntity()` don't exist yet.
- Stats go through attributes so the client sees them: `setMaxHealth()`, `setAttackDamage()`,
  `setWaterMovementSpeed()`.
- Whatever references the mob (goals, controls, helper objects) is released in
  `destroyCycles()`, or the mob waits for the cyclic GC after despawning.
- Drops: see [Drops](#drops). A mob that wears or holds items uses `EquipmentTrait`, one with body armor `BodyArmorTrait`.
- Other hooks: `createNavigation()`, `createMoveControl()`, `getAmbientSoundInterval()`,
  `generateEquipment()`, `canStandAt()`.

## Goals

`registerGoals()` fills two selectors: `goalSelector` for behavior and `targetSelector`
for choosing `$mob->getTargetEntity()`.

### Priority and flags

- A lower number is more important. A goal starts if its flags are free, or if every
  running goal holding one of them has a strictly higher number and is interruptable. An
  equal priority never preempts, and of two conflicting goals with the same priority the
  one added first wins.
- Flags: `FLAG_MOVE`, `FLAG_LOOK`, `FLAG_JUMP`, `FLAG_TARGET`, set in the constructor with
  `setFlags()`. A goal without flags never conflicts.
- Keep the instance if you'll remove and re-add a goal at runtime (`Skeleton`'s bow and
  melee goals).

### Lifecycle

`canUse()` → `start()` → `tick()` each tick → `canContinueToUse()` re-checked → `stop()`.
`canContinueToUse()` defaults to `canUse()`; override it when `canUse()` has a random roll
or a cooldown. `stop()` must reset per-run state: the instance is reused.

The selection pass and a running goal's `tick()` run every second tick, unless the goal
returns `true` from `requiresUpdateEveryTick()`. Write tick counts through
`reducedTickDelay()` or `adjustedTickDelay()`.

### Reusable goals

Under `entity/ai/goal/`; read the constructor of the one you use.

- Movement: `RandomStrollGoal`, `WaterAvoidingRandomStrollGoal`, `PanicGoal`,
  `MoveTowardsTargetGoal`, `FleeSunlightGoal`, `AvoidSunlightGoal`, `AvoidEntityGoal`,
  `FloatGoal`, `WaterAvoidingRandomFlyingGoal` (flying navigation).
- Swimming: `RandomSwimmingGoal`, `SwimIdleGoal`, `RiverFollowingGoal`, `FlopOutOfWaterGoal`,
  and `ManageFlockGoal` with `FollowFlockGoal` for flocks.
- Looking: `LookAtEntityGoal`, `RandomLookAroundGoal`.
- Combat: `MeleeAttackGoal`, `LeapAtTargetGoal`, `RangedAttackGoal`, `RangedBowAttackGoal`.
- Animals: `BreedGoal`, `FollowParentGoal`, `TemptGoal`, `EatBlockGoal`.
- Loot and doors: `PickupItemsGoal`, `BreakDoorGoal` (ground navigation only).
- Mob-specific goals live in subfolders (`creeper/`, `drowned/`, `rabbit/`, …).
- Targets, in `target/`: `HurtByTargetGoal`, `NearestAttackableGoal` (`condition:` skips the
  scan while false; use named arguments for the trailing ones), `TargetHighestDamagerGoal`.

Goals ask `$mob->getSensing()` instead of scanning the world: `findNearest()`,
`getNearbyLiving()`, `getNearbyPlayers()`, `getNearbyItems()`, `canSee()`, `isUnderwater()`. It caches
what is costly to repeat every tick. Constructor type
hints are strict: the wrong base class is a `TypeError`.

### Customizing a stock goal

An anonymous subclass is the pattern for small variations. The spider's melee goal gives up
in daylight:

```php
$this->goalSelector->addGoal(4, new class($this) extends MeleeAttackGoal{
	public function __construct(Spider $mob){
		parent::__construct($mob, 1);
	}

	public function canContinueToUse() : bool{
		if($this->mob->getLightLevelDependentMagicValue() >= 0.5 && $this->mob->getRandom()->nextBoundedInt(100) === 0){
			$this->mob->setTargetEntity(null);
			return false;
		}

		return parent::canContinueToUse();
	}
});
```

### Writing a goal

A goal that approaches the nearest player and stares at them:

```php
class CuriousGoal extends Goal{

	private ?Player $player = null;

	public function __construct(private PathfinderMob $mob, private float $speedModifier, private float $range){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		$this->player = $this->mob->getSensing()->findNearest(
			Player::class,
			$this->range,
			conditions: new TargetingConditions($this->range)
		);
		return $this->player !== null && $this->mob->getTargetEntity() === null;
	}

	public function canContinueToUse() : bool{
		return $this->player !== null && $this->player->isAlive();
	}

	public function start() : void{
		$this->mob->getNavigation()->moveToEntity($this->player, $this->speedModifier);
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$this->mob->getLookControl()->setLookAt($this->player);
	}

	public function stop() : void{
		$this->mob->getNavigation()->stop();
		$this->player = null;
	}
}
```

- Move through the navigation, never `MoveControl` directly. `moveToEntity()` is cheap to
  call every tick.
- Look with `getLookControl()->setLookAt()` every tick: it holds for two ticks.
- Pick random destinations with `entity/ai/utils/`.
- A goal can also be a mob's per-attack behavior: the polar bear's `PolarBearAttackGoal`
  extends `MeleeAttackGoal` to rear up before each hit.

## Movement

Walking, flying, swimming and climbing are a `Locomotion` that `createNavigation()` passes to
the `PathNavigation`, see [navigation.md](navigation.md). Path costs are set in
`initProperties()` with `$this->navigation->setPathfindingMalus()`.

Mobs that stroll through water (zombies, skeletons) use `RandomStrollGoal` and a
`BlockPathType::WATER` malus of 0: stroll targets with a malus are dropped, and
`WaterAvoidingRandomStrollGoal` leads the mob to land. Acceleration in water is the
underwater movement attribute, `setWaterMovementSpeed()`.

## Variations

### Animal

`Animal` already handles feeding, love mode, the baby flag and the spawn event for
offspring. Implement `isFood()` and `getBreedOffspring()`, add `BreedGoal`, `TemptGoal` and
`FollowParentGoal`, return no drops while `isBaby()`, and override `onInteract()` for
bucket-style interactions (`Cow`). A mob that vanilla draws at another size than its box
overrides `getAdultScale()` and `getBabyScale()` (`Rabbit`).

### Ranged attacker

Implement `RangedAttackMob`, add `RangedAttackGoal` and shoot in `performRangedAttack()`:

```php
public function performRangedAttack(Entity $target, float $force) : void{
	$projectile = new Snowball(Location::fromObject($this->getEyePos(), $this->getWorld()), $this);
	$delta = $target->getEyePos()->subtractVector($this->getEyePos());
	$projectile->setMotion($delta->normalize()->multiply(1.5));
	$projectile->spawnToAll();
	$this->doAttackAnimation();
}
```

Projectiles are PocketMine's own or a `Projectile` subclass. A custom one needs
`getNetworkTypeId()`, `getInitialSizeInfo()` and the hit callbacks. Implement
`NeverSavedWithChunkEntity` and override `canSaveWithChunk()` to return `false`, build it with
`new` and `spawnToAll()`, and don't add it to `ALL_ENTITIES`.

### Sun-sensitive undead

`isSunSensitive()` returning `true` makes `Monster::tickState()` burn the mob in daylight
with sky access and no helmet. Fleeing the sun is separate: `FleeSunlightGoal` and
`AvoidSunlightGoal`. Pair it with `MobType::UNDEAD()`.

### Neutral mob

Implement `NeutralMob`, `use NeutralMobTrait` (it keeps the anger ticks) and implement
`startAngerTimer()`. Use `isAngryAt()` as the `NearestAttackableGoal` validator. See `IronGolem`
and `Enderman`.

### Variants and persistent state

A variant the client renders differently, or that changes gameplay, uses an enum, a
hand-written id map in `data/bedrock/`, the mob's own NBT key and a metadata property
(`MooshroomCow`):

```php
$this->mooshroomType = MooshroomCowTypeIdMap::getInstance()->fromId($nbt->getInt(self::TAG_TYPE, -1)) ?? MooshroomCowType::RED; // initEntity(), after the parent call
$nbt->setInt(self::TAG_TYPE, MooshroomCowTypeIdMap::getInstance()->toId($this->mooshroomType)); // saveNBT()
$properties->setInt(EntityMetadataProperties::VARIANT, MooshroomCowTypeIdMap::getInstance()->toId($this->mooshroomType)); // syncNetworkData()
```

- Setters set `$this->networkPropertiesDirty = true` so the state is resent.
- The ids come from the vanilla JSON (`minecraft:variant` in the component groups). Reuse
  PocketMine's maps where they exist (`DyeColorIdMap`).
- Baby, sheared and saddled are the mob's own NBT keys plus flags, not component groups.
- A natural spawn doesn't choose a variant: `initEntity()` picks the default when there is
  no NBT. Biome-dependent ones read `BiomeTagMap` (`Rabbit`).
- Client-synced entity properties (`minecraft:climate_variant`) are not supported:
  PocketMine sends empty property data.

**Component groups** are for save compatibility only. Some vanilla entities store state in
the `definitions` NBT list (a creeper's charge, a zombie's `can_break_doors`). There is no
component runtime: read `$this->componentGroups->has()` after `parent::initEntity()` to
restore a field, and `add()` the group before `parent::saveNBT()`, so vanilla worlds
round-trip. Everything else is a field.

### Client state

What a mob sends to the client is not in the behavior pack. Metadata flags and properties
(`syncNetworkData()`, parent first), actor events (`entity/animation/`), sounds (`sound/`)
and scale come from a BDS packet capture of the vanilla mob. Variant ids are the exception.

- **Capture** with a packet-logging proxy between a client and a BDS in offline mode, and
  filter the log by the mob's runtime id: `AddActor`, `SetActorData` (the flags),
  `ActorEvent`, `LevelSoundEvent`. The polar bear's `REARING` flag, set for the 10 ticks
  before each hit and cleared with the arm swing, was found this way.
- **Many animations are client-side** and need nothing from the server: the rabbit's hop is
  played from its movement. Check the capture before adding state for one.
- **Set** state with `$this->networkPropertiesDirty = true`, animations with
  `$mob->broadcastAnimation()` (`Mob::doAttackAnimation()` is the arm swing) and sounds with
  `$mob->broadcastSound()`.
- **If a mob looks old or doesn't animate** while its own packets match the capture, compare
  what the server sends on join. A resource pack stack with old vanilla packs, which BDS
  doesn't send, makes the client load old entity definitions.

## Drops

Drops roll Mojang's loot tables with the [LootTable](https://github.com/kostamax27/LootTable)
library. `VanillaLootTables` has a method per table (`COW()` is `entities/cow`). A mob keeps
`getDrops()` and rolls one with `generateDeathLoot()`, choosing it by state:

```php
public function getDrops() : array{
	return $this->isBaby() ? [] : $this->generateDeathLoot(VanillaLootTables::COW());
}
```

An entity that doesn't extend `Mob` gets the same helpers from `LootRollingTrait`. A drop that
isn't a death names who caused it and what they used: `generateLoot($table, $source, $tool)`.
Spawn gear is a loot table too: override `generateEquipment()` and call
`equipFromLootTable()` (`EquipmentTrait`).

The data is `resources/bedrock/loot_tables.json`; `composer compile-loot-tables` regenerates
it and `VanillaLootTables`. An item the server doesn't have drops nothing.

## Natural spawning

- **Vanilla mobs wire themselves.** At startup `SpawnRuleRegistry::registerVanilla()` walks
  `ALL_ENTITIES`; for every network id in the bundled
  `resources/bedrock/spawning/spawn_rules.json` it registers the rules, the population
  category and a factory (`new YourMob(Location)`), sized from `VanillaEntitySizes`. So the
  constructor must work with only a `Location`. Vanilla mobs not in that file don't spawn
  naturally.
- **Non-vanilla mobs get nothing** until you register `SpawnRules` for them, with their own
  size and category. See the [spawning API](spawning-api.md).
- **Spawn events.** A rule can send an event to the mob it spawns (`as_baby`, `as_rider`,
  `become_*`). Each event a registered mob's rules send needs a handler in
  `MobPlugin::registerSpawnEvents()`, and `SpawnEventsTest` fails otherwise.

## Registering

1. `MobPlugin::ALL_ENTITIES`: add `Silverfish::class`.

2. `DespawnRuleRegistry`:

   ```php
   $this->register(DespawnRule::monster(Silverfish::class));       // hostile
   $this->register(new DespawnRule(Goat::class));                  // passive
   ```

   Options: `persistentWhile`, `difficultyCondition`, `maxLifetime`,
   `despawnsAwayFromPlayers: false`. Golems have none on purpose. See
   [despawning.md](despawning.md).

3. Spawn egg, in `item/`:
   - `ExtraItemTypeIds`: an `@method static int SILVERFISH_SPAWN_EGG()` line and
     `self::register("silverfish_spawn_egg");` in `setup()`.
   - `ExtraVanillaItems`: an `@method` line and, in `registerSpawnEggs()`,
     `self::register("silverfish_spawn_egg", new MobSpawnEgg(new IID(Ids::SILVERFISH_SPAWN_EGG()), "Silverfish Spawn Egg", Silverfish::class));`
   - `ExtraItemRegisterHelper::registerItems()`:
     `self::registerSimpleItem(ItemTypeNames::SILVERFISH_SPAWN_EGG, ExtraVanillaItems::SILVERFISH_SPAWN_EGG(), ["silverfish_spawn_egg"]);`

4. Spawn events its rules send: `MobPlugin::registerSpawnEvents()`.

To spawn a mob from your own code, construct it, fire
`new MobSpawnEvent($mob, MobSpawnCause::X)`, then `spawnToAll()`. Mobs built from blocks
register a pattern in `BlockPatternFactory`.

## Checking it

PHPStan level 9 and the in-server run are the checks. Set `debug-mode` in
`global-settings.yml`: the nametag then lists the running goals and the current path is
drawn with particles. A despawned mob should be freed at once, see `destroyCycles()` above.
