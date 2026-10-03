# Natural spawning

MobPlugin spawns mobs from the vanilla Bedrock spawn rules, on the main thread.

## Data

`resources/spawning/spawn_rules.json` is Mojang's `behavior_pack/spawn_rules/*.json`
merged into one file, from a pinned
[bedrock-samples](https://github.com/Mojang/bedrock-samples) commit. Nothing is fetched
at runtime.

The `mojang/bedrock-samples` dev dependency in `composer.json` is the pin: its `reference`
is the commit and its `version` is the spawn schema version. The tools read both through
Composer.

| Command | Generates |
|---|---|
| `composer compile-spawn-rules` | `spawn_rules.json` and `NOTICE.md` |
| `composer generate-spawn-schema` | `spawning/parse/schema/`: component names, envelope keys, the biome filter vocabulary, mob categories and one payload model per component |
| `composer generate-entity-data` | `data/bedrock/EntityIds` and `VanillaEntitySizes` (each entity's base `collision_box`) |

- The merge only strips comments, keys entries by identifier and sorts them. Every entry
  must validate against the pinned schemas, or nothing is written.
- The payload models make a renamed field fail the load instead of spawning with a wrong
  value.
- The generated `EntityIds` name the data's entities. A mob's network id stays
  PocketMine's, and `registerVanilla()` matches rules and boxes by it: if the two differ,
  the mob is skipped.

The output is deterministic: CI regenerates it and fails on any diff.
`SpawnRulesParseableTest` fails on anything the loader can't compile.

To update to a newer Mojang version:

1. In `composer.json`, point the package's `dist` URL and both `reference`s at the new
   commit and set `version` to its spawn schema version.
2. `composer update mojang/bedrock-samples`
3. Run the three commands above.
4. If a component was added, renamed or removed, update
   `SpawnRulesParser::createVanilla()`.
5. `composer test`, then review the diff.

## Layout

```
spawning/
├── NaturalSpawner, SpawnRuleRegistry, SpawnRules, SpawnRuleGroup
├── MobCategory, MobCategoryRegistry, BiomeTagMap, SpawnBand, SpawnLiquid
├── condition/   SpawnCondition, its contexts and the built-in conditions
├── population/  MobPopulation: how many mobs are around a chunk, per band
├── spawner/     the runtime (internal)
└── parse/       the strict loader; parse/schema/ is generated
```

## Runtime

`NaturalSpawner::tick()` runs once per tick.

### Budget

Every ticking chunk (`World::getTickingChunks()`) of every enabled world rolls vanilla's
chance, 11 in 2000, for one attempt. The spawner draws the gap to the next hit instead of
rolling each chunk. If more chunks hit than `max-attempts-per-tick`, a random subset is
kept. A world with a tick radius of 0 never spawns.

### One attempt

1. **Sample.** Pick a random column: the surface position on its ground, then every
   position below it. Columns and Y ranges out of every player's reach are skipped before
   any block is read.
2. **Candidates.** `CandidateCache` returns the rules that could still spawn at the
   position.
3. **Select.** `SpawnSelector` drops candidates whose category is full or whose mob has no
   room. Every matching group under its density limit enters a weighted pick; a group
   with a `rarity` then spawns one time in that many.
4. **Spawn.** `HerdSpawner` rolls the herd size, `min + round(rand² × (max − min))`,
   trims it to the room left and spawns every member on the lead's block. Each member
   picks its own `permute_type`.

A group keeps its mobs 24 to 128 blocks from the nearest player unless its
`distance_filter` says otherwise. Spectators and dead players don't count. At a tick
radius of 4 or less the reach is 44 blocks, as in vanilla; above that the 3×3 chunks
around the attempt must be ticking.

Light and population are read lazily, at most once per position.

### Placement

- **Ground** is a block with a full top surface (`getSupportType(Facing::UP) === FULL`).
  A column's ground is its highest one; everything below it is a cave.
- **Feet** go in a block with nothing to collide with: air, liquids, plants, torches. A
  single snow layer is fine.
- **Room**: the rule's collision box (`SpawnRules::getSize()`), centred on the block, must
  collide with nothing. A `permute_type` target of another size is not checked again.

Aquatic mobs spawn in the liquid block right above the ground.

### Population

`MobPopulation` counts the mobs around a chunk, per category and per type, on the surface
and underground. A position's population is the sum over the 9×9 chunks around it. Any
entity whose identifier has registered spawn rules counts.

`EntitySpawnBands` keeps the band each entity counts in. `HerdSpawner` sets it for the
mobs it places; any other entity gets it from where it stands when first counted. After a
herd its members are added to the counts already made; nothing is recounted.

### Caches

Ground levels, counts and light live for one `WorldSpawnPass` (one world, one tick). The
ground memo is dropped after every herd, since a factory may change blocks.

`CandidateCache` is the only long-lived cache. It is keyed by values, never by location,
so no world edit can make it stale. It is rebuilt when the registry changes.

## Candidate cache

Every rule is evaluated once per key `(biome id, band, difficulty, feet liquid)`:

- a `CacheableCondition` that fails drops its group; one that passes is removed from it;
- a combinator (`AllOf`, `AnyOf`, `Not`) is reduced to what the key leaves undecided;
- any other condition stays and runs on every attempt, in its listed order.

A key only admits groups whose required liquid is its feet liquid
(`SpawnRuleGroup::admitsLiquid()`). The key space is bounded, so the cache never evicts.

## Conditions

`SpawnRules` is a list of `SpawnRuleGroup`s, and every matching group competes in the
pick. A group carries its conditions, weight, rarity, herd size, `permute_type` weights,
player distance range, density limits and required liquid.

```php
// Tested on every attempt.
interface SpawnCondition{
	public function test(SpawnConditionContext $ctx) : bool;
}

// Decided once per cache key.
interface CacheableCondition extends SpawnCondition{
	public function test(CacheableConditionContext $ctx) : bool;
}
```

`CacheableConditionContext` has the four key values. `SpawnConditionContext` adds the
per-attempt ones: coordinates, light, the block below, time, population, room, the random
source and the world.

| Kind | Built-ins |
|---|---|
| Cacheable | `BiomeTagCondition`, `DifficultyCondition`, `BandCondition` |
| Per attempt | `BrightnessCondition`, `BlockLightCondition`, `WorldAgeCondition`, `HeightCondition`, `SpawnsOnBlock`, `SlimeChunkCondition`, `LightChanceCondition`, `MoonPhaseChanceCondition` |
| Combinators | `AllOf`, `AnyOf`, `Not` |

Two vanilla rules live in the engine instead of the rules file, so the registry adds them:

- Every `Monster` gets `new BlockLightCondition(0, 0)`.
- Slimes spawn at Y 38 or below in slime chunks (Bedrock's algorithm, reverse engineered
  by @protolambda and @jocopa3), and from Y 50 to 68 in `spawns_slimes_on_surface` biomes
  with a light roll and a moon-phase roll.

### Contract

The cache relies on these rules and can't check them:

1. **Immutable.** Every property is `readonly`.
2. **A `CacheableCondition` is pure.** Its result depends only on its context and its own
   state. If it reads something that can change, call
   `SpawnRuleRegistry::invalidateCache()` when it does.
3. **No side effects.** `test()` changes nothing and doesn't depend on which conditions
   ran before it.

A plain `SpawnCondition` may read anything, including the world through `getWorld()`, and
takes its randomness from `$ctx->getRandom()`. A custom combinator implements
`ReducibleCondition` (or extends `CompositeCondition`); otherwise it runs whole on every
attempt.

## Loading

`MobPlugin::onEnable()` calls `SpawnRuleRegistry::registerVanilla($path)`, which parses the
resource and binds every implemented mob, plus PocketMine's squid.

The loader is strict: anything it can't compile throws `SpawnRulesParseException` with the
JSON path, and the plugin is disabled. Unknown keys are rejected, and so are values that
would lose information (`8.5` for an integer; `8.0` is fine).

What it accepts without spawning:

- `pillager` rule sets are skipped: vanilla spawns them through patrols and raids.
- Groups using `mob_event_filter`, `delay_filter`, `player_in_village_filter` or
  `spawns_above_block_filter` never spawn.
- A group with neither `spawns_on_surface` nor `spawns_underground` is dropped (guardian).
- `powder_snow` is dropped from block filters: PocketMine has no such block.
- `disallow_spawns_in_bubble`, `is_persistent`, `is_experimental` and `spawn_event` are
  ignored.
- A biome tag no biome carries never matches; the plugin logs a warning for each.

## Registration API

```php
SpawnRuleRegistry::getInstance()->register(new SpawnRules(
	"minecraft:myboss",
	VanillaMobCategories::MONSTER,
	[
		new SpawnRuleGroup([
			new BrightnessCondition(0, 7),
			new DifficultyCondition(World::DIFFICULTY_EASY, World::DIFFICULTY_HARD),
			new SpawnsOnBlock([VanillaBlocks::STONE()->asItem()->getStateId() => true], false),
		], weight: 100),
	],
	fn(World $world, Vector3 $pos, SpawnRuleGroup $group) => new MyBoss(Location::fromObject($pos, $world)),
	new EntitySizeInfo(2.0, 1.0), // height, width: the room it needs
));
```

- `register()` throws on an unknown category, or on a duplicate identifier without
  `override`.
- Rules store the category id, so re-registering a category applies to existing rules.
- The factory builds the entity; `HerdSpawner` spawns it. Its exceptions are not caught.
- `SpawnRuleGroup` options: `minPlayerDistance:` / `maxPlayerDistance:` (`0.0` and `INF`
  for no bound), `surfaceDensityLimit:` / `caveDensityLimit:`, and
  `requiredLiquid: SpawnLiquid::WATER` for aquatic groups.
- `SpawnRules::check($ctx)` evaluates the conditions without the cache.

### Population queries

```php
$counts = MobPopulation::getInstance()->around($world, $chunkX, $chunkZ);
$counts->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::CAVE);
$counts->getIdentifierCount(EntityIds::ZOMBIE, SpawnBand::SURFACE);

// A plugin that spawns a mob its own way can say which band it counts in.
MobPopulation::getInstance()->getBands()->set($entity, SpawnBand::SURFACE);
```

`around()` scans 81 chunks on every call: keep its result. Inside a condition use
`$ctx->getPopulation()`.

### Custom components

```php
$parser = SpawnRulesParser::createVanilla();
$parser->registerComponent(
	"myplugin:my_filter",
	static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
		$builder->addCondition(new MyCustomCondition($ctx->map(MyCustomData::class)->field));
	}
);
foreach($parser->parseFile($path) as $identifier => [$categoryId, $groups]){
	// build SpawnRules with a factory and register them
}
```

`ComponentParseContext` also has `mapList()`, `resolveBlockSet()`, `objectOrList()`,
`getValue()` and `getPath()`.

## Settings

```yaml
mob-natural-spawning:
  enabled: true
  max-attempts-per-tick: 8
```

A world settings file can set `enabled: false` for that world. `max-attempts-per-tick` is
global: it caps the cost of a crowded tick, not the spawn rate.

## Timings

`Natural Spawning` covers the whole tick:

| Timing | Covers |
|---|---|
| `Sample` | column sampling, position checks and the cache lookup |
| `Candidate Resolve` | cache misses (inside `Sample`) |
| `Select` | `SpawnSelector` |
| `Census` | counting a region the first time (inside `Select`) |
| `Spawn` | herd placement and factories |

## Differences from vanilla

The flow follows `BedrockSpawner` as traced in BDS 1.26.51.1.

- Rules that fail a block filter, liquid, distance, density limit, category cap or room
  check are left out of the pick. Vanilla picks first and wastes the attempt.
- The ticking set is PocketMine's circle around each player, not vanilla's diamond.
- A tick radius below 4 spawns like 4. Vanilla doesn't spawn below 4, but PocketMine's
  default is 3.
- At a tick radius of 5 or more, mobs may spawn up to 128 blocks out, past most
  categories' 64 block despawn distance.
- Ground is any block with a full top surface, including glass and barriers.
- A liquid position needs one block of its liquid; vanilla's surface needs two.
- `permute_type` targets spawn in base form (event suffixes are stripped), and a target
  without registered rules spawns as the group's own mob. `min_guaranteed` is ignored.
- When `herd` is a list, only the first entry is used.
- Monsters use the Overworld darkness rule everywhere; the Nether's rule, thunderstorms
  and `adjust_for_weather` are not implemented.
- Slimes are checked with their largest box, so they need 3×3×3 blocks of room. Their
  light and moon rolls are drawn once, where vanilla draws them twice.
- `is_snow_covered` is approximated by the `frozen` biome tag. A biome id without bundled
  definitions has no tags.
- The band a mob spawned in is not saved: a loaded mob takes the band of where it stands
  when first counted.
- Structure spawn areas and the global mob cap (200) are not implemented.
