# Implementing a new mob

How to port a vanilla Bedrock mob. Navigation, spawning and despawning have their own
docs: [navigation.md](navigation.md), [spawning.md](spawning.md),
[despawning.md](despawning.md). All mob AI is built from goals.

Paths below are relative to `src/IvanCraft623/MobPlugin/`.

## Checklist

1. The entity class, under `entity/{monster,animal,golem,ambient,boss}/`.
2. Add it to `MobPlugin::ALL_ENTITIES`.
3. A despawn rule in `despawning/DespawnRuleRegistry`. Without one the mob never despawns.
4. A spawn egg: three files in `item/`.
5. Run php-cs-fixer, PHPStan, `composer test`, and build the phar.

Natural spawning is wired automatically for vanilla mobs; a non-vanilla mob needs its own
rules. See [Natural spawning](#natural-spawning).

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

The behaviors are **not** a 1:1 source for `registerGoals()`. Bedrock's priorities are
not copied, several goals are Java-derived (`MeleeAttackGoal` is documented as not a
vanilla port), and a few goals are registered that the JSON doesn't declare. Start from
`registerGoals()` of the closest existing mob and use the JSON to check what the mob does.

Where Bedrock's model and ours differ:

- **Not every behavior is a goal.**
  - `burns_in_daylight` is `Monster::isSunSensitive()`.
  - `breedable` is `Animal` state (love, `onInteract()`, `isFood()`); `BreedGoal` is only
    the walking part.
  - `interact` is an `onInteract()` override.
  - `angry`, `on_target_acquired` and `on_calm` are `NeutralMob`.
  - `shooter` is `performRangedAttack()`.
  - `equip_item` is `Mob::pickupItem()`, and `shareables` is `getWantedItems()`.
  - `environment_sensor`, `timer` and component-group swaps have no runtime: each is
    re-expressed as plain fields and inline checks.
- **Some goals are not behaviors.** `RandomTeleportGoal` is the `teleport` component,
  `LookForStaringPlayerGoal` is `looked_at`, `AvoidSunlightGoal` is the `avoid_sun`
  navigation flag, `BreakDoorGoal` is the `break_door` annotation.
- **One behavior, several goals, or the reverse.** `nearest_attackable_target` lists several
  entity types; we add one `NearestAttackableGoal` per type (or one with a validator
  closure). `ranged_attack` is `RangedAttackGoal` for most mobs and `RangedBowAttackGoal`
  for skeletons. `melee_attack` and `melee_box_attack` are both `MeleeAttackGoal`.
  Fleeing the sun is three pieces: `FleeSunlightGoal`, `AvoidSunlightGoal` and burning.
  A slime's `area_attack` lives inside `SlimeAttackGoal`.
- **`PickupItemsGoal` is a goal, the opt-in is not.** Whether a mob picks up loot is a
  persisted flag (`ItemPickupCapableTrait`) that adds or removes the goal at runtime.
- **Behavior arguments we don't expose.** Many Bedrock parameters have no constructor
  equivalent (`must_be_on_ground`, `track_target`, `reach_multiplier`, `max_dist` of the
  pickup). Read the goal before assuming it matches the JSON.
- **Unported pieces are TODOs in the mob's class**, e.g. `avoid_mob_type`, village goals,
  riding and jockeys.

## Choosing a base class

```
Living → Mob → PathfinderMob → Monster      hostile, implements Enemy
                             → AgeableMob → Animal   baby/adult, breeding, feeding
                             → Golem
              → Ambient                     Bat; extends Mob, no pathfinding
```

| Base | Adds |
|---|---|
| `Mob` | goal selectors, controls, navigation, equipment, drops, settings |
| `PathfinderMob` | path-chunk listening, `getWalkTargetValue()`. Most goals require it. |
| `Monster` | 5 XP when hurt by a player, `isSunSensitive()`, the block-light-0 spawn condition |
| `Animal` | age, love and breeding, fire path costs, grass preference. You implement `isFood()` and `getBreedOffspring()`. |
| `Golem` | ambient sound interval only |

Slime extends `Mob` directly and implements `Enemy` itself. Do the same for a mob that
doesn't path like the others.

Optional capabilities. Each is an interface; the canonical example shows how to wire it:

| Interface | For | Example |
|---|---|---|
| `Powerable` | charged variants | `Creeper` |
| `Saddleable`, `ItemSteerable` | riding | `Pig` |
| `Shearable` | shearing | `Sheep` |
| `RangedAttackMob` | `performRangedAttack()`, needed by the ranged goals | `SnowGolem`, `AbstractSkeleton` |
| `NeutralMob` + `NeutralMobTrait` | angry for a while after provocation | `Enderman`, `IronGolem` |
| `ItemPickupCapable` + `ItemPickupCapableTrait` | picks up and equips loot | `Zombie` |
| `Flyable` + `FlyableTrait` | flying | `Wither` |
| `DamageTracker` + `DamageTrackerTrait` | `TargetHighestDamagerGoal` | `Wither` |

## A minimal mob

A silverfish-style monster. `Endermite` is the same shape.

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
		$this->goalSelector->addGoal(4, new MeleeAttackGoal($this, 1, false));
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
- Sizes come from the generated `VanillaEntitySizes`. `getInitialSizeInfo()` takes height,
  width and an optional eye height. Slime sizes and baby variants are not in the data.
- `getName()` is also the bStats key.
- `initProperties()` runs first in `Mob::initEntity()`: health, damage, follow range.
  Anything a goal reads at construction must be set here, because `registerGoals()` runs
  inside `initEntity()`. Fields assigned after `parent::initEntity()` don't exist yet
  (see how `Slime` sets its type before calling the parent).
- Drops: override `getDrops()`; call `shouldDropCookedItems()` for burning deaths. `Mob`
  already drops equipment.
- `getPickedItem()` returns the spawn egg.

Other `Mob` hooks: `createNavigation()`, `getAmbientSoundInterval()`,
`getEquipmentDropProbability()`, `generateEquipment()`, `canStandAt()`.

## Goals

`registerGoals()` fills two selectors: `goalSelector` for behavior and `targetSelector`
for choosing `$mob->getTargetEntity()`. Target goals go in their own selector so they
never fight movement goals for flags.

### Priority and flags

- A lower number is more important. A goal starts if its flags are free, or if every
  running goal holding one of them has a strictly higher number and is interruptable
  (`isInterruptable()`). An equal priority never preempts.
- Flags: `FLAG_MOVE`, `FLAG_LOOK`, `FLAG_JUMP`, `FLAG_TARGET`. Set them in the constructor
  with `setFlags()`. A goal without flags never conflicts. Goals with disjoint flags run
  together: `LookAtEntityGoal` (look) runs next to a stroll (move).
- Add goals in ascending priority. The start pass walks them in insertion order, so of two
  conflicting goals with the same priority the one added first wins.
- Hold on to the instance if you'll remove and re-add a goal at runtime, as `Skeleton`
  does with its bow and melee goals.

### Lifecycle

`canUse()` → `start()` → `tick()` each tick → `canContinueToUse()` re-checked →
`stop()`. `canContinueToUse()` defaults to `canUse()`. Override it when `canUse()` has a
random roll or cooldown, or the goal will stop at random.

**Tick cadence**: the selection pass (`canUse`/`canContinueToUse`) runs every second tick
per mob. A running goal's `tick()` also runs every second tick unless it returns `true`
from `requiresUpdateEveryTick()`. Write tick counts through `reducedTickDelay($ticks)`
(halved) or `adjustedTickDelay($ticks)` (halved only when it is not ticked every tick).

`stop()` must reset per-run state: the goal instance is reused.

### Reusable goals

Under `entity/ai/goal/`; read the constructor of the one you use.

- Movement: `RandomStrollGoal`, `WaterAvoidingRandomStrollGoal`, `PanicGoal`,
  `MoveTowardsTargetGoal`, `FleeSunlightGoal`, `AvoidSunlightGoal`,
  `WaterAvoidingRandomFlyingGoal` (needs flying navigation), `FloatGoal`.
- Looking: `LookAtEntityGoal`, `RandomLookAroundGoal`.
- Combat: `MeleeAttackGoal`, `LeapAtTargetGoal`, `RangedAttackGoal` and
  `RangedBowAttackGoal` (the mob must implement `RangedAttackMob`).
- Animals: `BreedGoal`, `FollowParentGoal`, `TemptGoal`, `EatBlockGoal`.
- Loot and doors: `PickupItemsGoal`, `BreakDoorGoal` (ground navigation only).
- Mob-specific, in subfolders: `creeper/`, `enderman/`, `slime/`, `wither/`.
- Targets, in `target/`: `HurtByTargetGoal` (`->setAlertOthers()` makes same-type mobs
  join), `NearestAttackableGoal` (takes its range from the follow range at construction;
  use named arguments for the trailing ones), `TargetHighestDamagerGoal` (needs
  `DamageTracker`).

Constructor type hints are strict: handing a goal the wrong base class is a `TypeError`.
`TargetingConditions` filters candidates; `$mob->getSensing()->canSee($e)` is the cached
line of sight, prefer it inside goals.

### Customizing a stock goal

Anonymous subclasses are the pattern for small variations. The spider's melee goal gives
up in daylight, and its target goal only runs in the dark:

```php
$this->goalSelector->addGoal(4, new class($this) extends MeleeAttackGoal{
	public function __construct(Spider $mob){
		parent::__construct($mob, 1, true);
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

A goal that makes a mob approach the nearest player and stare at them, with a cooldown:

```php
class CuriousGoal extends Goal{

	private const COOLDOWN = 200;

	private ?Player $player = null;
	private int $cooldown = 0;

	public function __construct(
		private PathfinderMob $mob,
		private float $speedModifier,
		private float $range
	){
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		if($this->cooldown > 0){
			$this->cooldown--;
			return false;
		}
		if($this->mob->getTargetEntity() !== null){
			return false;
		}

		$this->player = Utils::getNearestPlayer($this->mob, $this->range, new TargetingConditions($this->range));
		return $this->player !== null;
	}

	public function canContinueToUse() : bool{
		return $this->player !== null && $this->player->isAlive() &&
			$this->mob->getTargetEntity() === null &&
			$this->mob->getPosition()->distanceSquared($this->player->getPosition()) > 9;
	}

	public function start() : void{
		$this->mob->getNavigation()->moveToEntity($this->player, $this->speedModifier);
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function tick() : void{
		$this->mob->getLookControl()->setLookAt($this->player);
		$this->mob->getNavigation()->moveToEntity($this->player, $this->speedModifier); // cheap: it only repaths when needed
	}

	public function stop() : void{
		$this->mob->getNavigation()->stop();
		$this->player = null;
		$this->cooldown = $this->reducedTickDelay(self::COOLDOWN);
	}
}
```

- Move through the navigation, never `MoveControl` directly. See
  [navigation.md](navigation.md).
- Look with `getLookControl()->setLookAt()` every tick: it holds for two ticks.
- Pick random destinations with `entity/ai/utils/` (`DefaultPositionGenerator`,
  `LandPositionGenerator`, …).
- `SwellGoal` (creeper) is a compact real example of a goal that stops the navigation in
  `start()` and decides per tick.

## Variations

### Animal

```php
class Goat extends Animal{
	// getNetworkTypeId(), getInitialSizeInfo(), getName(), initProperties() as above

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(0, new FloatGoal($this));
		$this->goalSelector->addGoal(1, new PanicGoal($this, 2));
		$this->goalSelector->addGoal(2, new BreedGoal($this, 1));
		$this->goalSelector->addGoal(3, new TemptGoal($this, 1.25, false));
		$this->goalSelector->addGoal(4, new FollowParentGoal($this, 1.25));
		$this->goalSelector->addGoal(5, new WaterAvoidingRandomStrollGoal($this, 1));
		$this->goalSelector->addGoal(6, new LookAtEntityGoal($this, Player::class, 6));
		$this->goalSelector->addGoal(7, new RandomLookAroundGoal($this));
	}

	public function isFood(Item $item) : bool{
		return $item->getTypeId() === ItemTypeIds::WHEAT;
	}

	public function getBreedOffspring(AgeableMob $partner) : Goat{
		return new Goat($this->getLocation());
	}
}
```

`Animal` already handles feeding, love mode, the baby flag, `MobFeedEvent` and the spawn
event for offspring. Return no drops while `isBaby()`; override `onInteract()` for
bucket-style interactions (`Cow`).

### Ranged attacker

```php
class Pyro extends Monster implements RangedAttackMob{

	protected function registerGoals() : void{
		$this->goalSelector->addGoal(1, new FloatGoal($this));
		$this->goalSelector->addGoal(2, new RangedAttackGoal($this, 1.25, 20, 40, 15));
		// ...
	}

	public function performRangedAttack(Entity $target, float $force) : void{
		$projectile = new Snowball(Location::fromObject($this->getEyePos(), $this->getWorld()), $this);
		$delta = $target->getEyePos()->subtractVector($this->getEyePos());
		$projectile->setMotion($delta->normalize()->multiply(1.5));
		$projectile->spawnToAll();
		$this->doAttackAnimation();
	}
}
```

Projectiles are PocketMine's own (`Snowball`, `Arrow`) or a subclass of `Projectile`. A
custom one (`entity/projectile/WitherSkull`) needs `getNetworkTypeId()`,
`getInitialSizeInfo()` and the hit callbacks. Implement `NeverSavedWithChunkEntity`, build it
with `new` and `spawnToAll()`, and don't add it to `ALL_ENTITIES`.

### Different movement

Override `createNavigation()`: `WallClimberNavigation` (`Spider`), `FlyingPathNavigation`
(`Wither`). A flying mob also uses `Flyable`/`FlyableTrait`, replaces `$this->moveControl`
with `FlightMoveControl` right after `parent::initEntity()`, and uses
`WaterAvoidingRandomFlyingGoal`. `BreakDoorGoal` and `AvoidSunlightGoal` need
`GroundPathNavigation`.

Path costs: `setPathfindingMalus(BlockPathType::DANGER_FIRE, 16)` in `initProperties()`.

### Sun-sensitive undead

Burning is `Monster::isSunSensitive()` returning `true`: `Monster::tickAi()` then sets the
mob on fire in daylight with sky access and no helmet (`Zombie`, `Skeleton`). Fleeing the
sun is separate: `FleeSunlightGoal` and `AvoidSunlightGoal`. Pair it with `getMobType()`
returning `MobType::UNDEAD()`: undead are healed by harming and hurt by healing.

### Neutral mob

Implement `NeutralMob`, `use NeutralMobTrait` and provide the anger timer:

```php
class Enderman extends Monster implements NeutralMob{
	use NeutralMobTrait;

	private int $remainingAngerTicks = 0;

	public function getRemainingAngerTicks() : int{ return $this->remainingAngerTicks; }
	public function setRemainingAngerTicks(int $ticks) : void{ $this->remainingAngerTicks = $ticks; }
	public function startAngerTimer() : void{ $this->remainingAngerTicks = mt_rand(400, 800); }
}
```

Use `isAngryAt()` as the `NearestAttackableGoal` validator so only provoked targets are
picked. See `IronGolem` and `Enderman` for the complete wiring.

### Variants and persistent state

A **real variant** (anything the client renders differently, or that changes gameplay)
uses an enum, a hand-written id map, the mob's own NBT key and a metadata property.
`MooshroomCow` red/brown:

```php
private const TAG_TYPE = "Variant"; //TAG_Int

// initEntity(), after the parent call
$this->mooshroomType = MooshroomCowTypeIdMap::getInstance()->fromId($nbt->getInt(self::TAG_TYPE, -1)) ?? MooshroomCowType::RED();

// saveNBT()
$nbt->setInt(self::TAG_TYPE, MooshroomCowTypeIdMap::getInstance()->toId($this->mooshroomType));

// syncNetworkData()
$properties->setInt(EntityMetadataProperties::VARIANT, MooshroomCowTypeIdMap::getInstance()->toId($this->mooshroomType));

// setters mark the state to be resent
$this->networkPropertiesDirty = true;
```

- The enum (`MooshroomCowType`, `SlimeType`) carries the gameplay data; the id map in
  `data/bedrock/` (`fromId()`/`toId()`) holds the wire ids. The ids come from the vanilla
  JSON (`minecraft:variant` in the component groups: slime 1, 2, 4; mooshroom 0, 1).
- Reuse PocketMine's maps where they exist: `Sheep` colour goes through `DyeColorIdMap`.
- Baby, sheared and saddled are the mob's own NBT keys plus metadata flags (`AgeableMob`
  `Age`, `Sheep` `Sheared`, `Pig` `Saddled`), never component groups.
- Offspring, splits and conversions pick the variant in code (`getOffspringType()`,
  `SlimeType::getSplitType()`). A natural spawn doesn't choose one: a new entity picks its
  default in `initEntity()` when there is no NBT.
- Client-synced entity properties (`minecraft:climate_variant` of cow, pig, chicken) are
  not supported: PocketMine always sends empty property data.

**Component groups** are for save compatibility only. Some vanilla entities store state in
the `definitions` NBT list (a creeper's charged and forced-ignite states, a zombie's
`can_break_doors`, an iron golem's `player_created`/`village_created`). There is no
component runtime: `$this->componentGroups` is read for whether a group is present, to
restore our own field, and the minimum is written back, so vanilla worlds round-trip. Read
after `parent::initEntity()`, add before `parent::saveNBT()`:

```php
$this->powered = $this->componentGroups->has("minecraft:charged_creeper");

// saveNBT()
if($this->powered){
	$this->componentGroups->add("minecraft:charged_creeper");
}
```

Use it only when the vanilla save records the state that way. Everything else is a field.

### Client state

What a mob sends to the client is not in the behavior pack. Metadata flags and properties
(`syncNetworkData()`), actor events (`entity/animation/`), sound events (`sound/`) and
the like come from how vanilla Bedrock behaves on the wire, so take them from a BDS
packet capture of the vanilla mob, not from the JSON. Variant ids are the exception.
The repo has no capture tooling or recorded traces: the existing constants were chosen
from the `bedrock-protocol` enums, and unverified ones carry a `TODO`.

The mechanics:

- Override `syncNetworkData(EntityMetadataCollection)`, parent first, and set
  `$this->networkPropertiesDirty = true` when the state changes.
- `$mob->broadcastAnimation(new SomeAnimation($mob))`: the classes in `entity/animation/`
  wrap an `ActorEventPacket`. `Mob::doAttackAnimation()` is the arm swing.
- `$mob->broadcastSound(new XSound(...))` for the classes in `sound/`. Ambient, hurt and
  death sounds are played by the client per entity id.

## Natural spawning

- **Vanilla mobs wire themselves.** At startup `SpawnRuleRegistry::registerVanilla()`
  walks `ALL_ENTITIES`; for every network id present in the bundled
  `resources/spawning/spawn_rules.json` it registers the vanilla rules, the population
  category and a factory (`new YourMob(Location)`), with the size taken from
  `VanillaEntitySizes`. `Monster` subclasses get the block-light-0 condition added.
  So adding a vanilla mob to `ALL_ENTITIES` is all it takes, and its constructor must
  work with only a `Location`.
- Vanilla mobs that aren't in that file (Endermite, the golems, the Wither) simply don't spawn naturally. Nothing to do.
- **Non-vanilla mobs get nothing.** A mob with its own id has no vanilla data, so it
  never spawns naturally until you register `SpawnRules` for it, with its own size
  (`EntitySizeInfo`) and category. See the [spawning API](spawning-api.md).

## Registering

1. `MobPlugin::ALL_ENTITIES`: add `Silverfish::class`. `registerEntity()` registers it with
   PocketMine's `EntityFactory` under the network id and its readable name.
   Metrics and tracking are automatic.

2. `DespawnRuleRegistry`:

   ```php
   $this->register(DespawnRule::monster(Silverfish::class));       // hostile
   $this->register(new DespawnRule(Goat::class));                  // passive
   $this->register(DespawnRule::monster(Endermite::class, maxLifetime: 2400));
   ```

   Options: `persistentWhile` (e.g. an enderman carrying a block), `difficultyCondition`,
   `maxLifetime`, `despawnsAwayFromPlayers: false` (the Wither). Golems have no rule on
   purpose. See [despawning.md](despawning.md).

3. Spawn egg, in `item/`:
   - `ExtraItemTypeIds`: an `@method static int SILVERFISH_SPAWN_EGG()` line and
     `self::register("silverfish_spawn_egg");` in `setup()`.
   - `ExtraVanillaItems`: an `@method` line and, in `registerSpawnEggs()`,
     `self::register("silverfish_spawn_egg", new MobSpawnEgg(new IID(Ids::SILVERFISH_SPAWN_EGG()), "Silverfish Spawn Egg", Silverfish::class));`
   - `ExtraItemRegisterHelper::registerItems()`:
     `self::registerSimpleItem(ItemTypeNames::SILVERFISH_SPAWN_EGG, ExtraVanillaItems::SILVERFISH_SPAWN_EGG(), ["silverfish_spawn_egg"]);`

   Eggs pass only a `Location` to the constructor. If PocketMine already ships the egg
   (the Zombie's), `EventListener::onPlayerInteract` overrides it.

4. Spawning it from your own code: construct it, fire
   `new MobSpawnEvent($mob, MobSpawnCause::X)` (`BUILT`, `SUMMONED`, `CONVERSION`, …),
   then `spawnToAll()`. Mobs built from blocks register a pattern in `BlockPatternFactory`.

## Checking it

There is no mob test suite; PHPStan level 9 and the in-server run are the checks.
Set `debug-mode` in `global-settings.yml`: the nametag then lists the running goals and
the current path is drawn with particles.
