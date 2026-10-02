# Natural spawning

MobPlugin spawns mobs from vanilla Bedrock spawn rules, evaluated on the main thread.

## Data & schema validation

`resources/spawning/spawn_rules.json` is a deterministic merge of Mojang's
`behavior_pack/spawn_rules/*.json` from a pinned
[bedrock-samples](https://github.com/Mojang/bedrock-samples) commit. It is the only data
MobPlugin ships for natural spawning; nothing is fetched at runtime.

### The pin

The `mojang/bedrock-samples` dev dependency in `composer.json` pins the commit, and its
package version is the spawn schema version (the `server/spawn/<version>` directory).
That declaration is the only place both are set: the tools read them through Composer,
`NOTICE.md` records them, and `SpawnSchema::SCHEMA_VERSION` must match
(`VanillaSpawnConditionsCoverageTest`). `composer install` extracts the commit into
`vendor/mojang/bedrock-samples`; being a dev dependency, it never ships in the phar.

### Tools

```sh
composer compile-spawn-rules     # php tools/spawn-rules/compile.php
composer generate-spawn-schema   # php tools/spawn-rules/generate-schema.php
```

Both read only the pinned package and take no options. Their output is deterministic, so
CI regenerates it and fails on any diff.

- **`compile.php`** merges the spawn rules into `spawn_rules.json` and writes `NOTICE.md`.
  It only strips JSON comments (some vanilla files aren't strict JSON), keys entries by
  `description.identifier`, sorts and pretty-prints; all semantics belong to the runtime
  loader. Every entry must validate against the pinned schemas, use only components they
  declare, and use no unknown structural keys. Nothing is written unless every file
  passes.
- **`generate-schema.php`** generates `parse/schema/`: `VanillaSpawnConditions` (one
  constant per component), `SpawnSchema` (schema version, difficulty names and the
  envelope keys the loader navigates with) and one JsonMapper payload model
  (`model/*Data`) per payload-bearing component schema, so a renamed payload field breaks
  the build instead of silently spawning with a wrong value. `model/` holds generated
  classes only: regenerating deletes any model the pinned schemas no longer produce.

### Checks

| Check | Where | Catches |
|---|---|---|
| Regenerate, then `git diff --exit-code` | CI (`ci.yml`) | `spawn_rules.json`, `NOTICE.md` or `parse/schema/` edited by hand, not regenerated after a pin change, or holding a stale model |
| `SpawnRulesParseableTest` | `composer test` | anything the strict loader can't compile; entries or groups skipped or dropped beyond the by-design cases |
| `MobCategoryRegistryTest` | `composer test` | a rule whose `population_control` has no registered category |
| `VanillaSpawnConditionsCoverageTest` | `composer test` | a component without a parser; artifacts generated from another version |

The Mojang schemas are permissive (draft-07 allows extra properties), so schema
validation is a coarse shape check. The strict loader is the authority on semantics.

### Updating to a newer Mojang version

1. In `composer.json`, point the `mojang/bedrock-samples` package's `dist` URL and both
   `reference`s at the new commit, and set its `version` to the spawn schema version that
   commit ships.
2. `composer update mojang/bedrock-samples`
3. `composer generate-spawn-schema`, then `composer compile-spawn-rules`.
4. If a component was added, renamed or removed, update `SpawnRulesParser::createVanilla()`
   so every declared component has a parser (see [Loading](#loading)).
5. Run `composer test` and review the diff.

## Package layout

```
spawning/
├── NaturalSpawner, SpawnRuleRegistry, SpawnRules, SpawnRuleGroup
├── MobCategory, MobCategoryRegistry, BiomeTagMap, SpawnBand, SpawnLiquid
├── condition/   SpawnCondition, SpawnConditionContext and the built-in conditions
├── population/  MobPopulation: how many mobs are around a chunk, per band
├── spawner/     the runtime (internal)
└── parse/       the strict loader; parse/schema/ is generated
```

## Runtime

Everything runs on the main thread, once per tick, inside `NaturalSpawner::tick()`.

### Budget

Each tick, every chunk in `World::getTickingChunks()` of every world with spawning
enabled (peaceful worlds included: the data decides what spawns there) rolls vanilla's
chance, `nextInt(2000) <= 10`, for one attempt. That list is PocketMine's ticking set:
the chunks within `chunk-ticking.tick-radius` of a player, each counted once however many
players share it. A world with a tick radius of 0 never spawns.

The spawner doesn't roll chunk by chunk: it draws the gap to the next chunk that hits from
the matching geometric distribution, so a tick costs one random number per attempt.

If more chunks roll an attempt than `max-attempts-per-tick`, a random subset of that size
is kept. Each world with attempts gets one `WorldSpawnPass` for the tick.

The registry revision is read once per tick, so rules registered mid-tick apply from the
next tick.

### One attempt

Each attempt runs from start to finish before the next one begins:

1. **Sample.** Pick a random column of the chunk: the surface position on its ground,
   then every position below it down to the world bottom, as vanilla does. A position is
   dropped if it fails the placement rules below or is out of reach of every player: at a
   tick radius of 4 or less every rule's maximum distance is clamped to 44 blocks, as
   vanilla does at 4; at 5 or more the attempt needs the 3×3 chunks around its chunk to
   be ticking instead. The reach (44 blocks when clamped, else the largest maximum
   distance of any group) is applied before any block is read: a column no player can
   reach is skipped, and only the Y range some player can reach is scanned.
2. **Candidates.** Build an `AttemptContext` and ask the `CandidateCache` which rules could
   still spawn there. An empty list ends the position.
3. **Select.** `SpawnSelector` skips candidates whose category is unregistered, has a cap
   of 0 in the band or is at its cap. Each remaining candidate contributes every matching
   group that is under its density limit. One is picked by group weight; if it has a
   `rarity`, it then spawns one time in that many. The pick carries the room left for
   its herd under the category cap and the density limit.
4. **Spawn.** `HerdSpawner` rolls the herd size, `min + round(rand² × (max − min))`,
   trims it to the room left under the category cap and the group's `density_limit`, and
   spawns every member on the lead's block. Each member picks its own `permute_type`;
   the factory builds it and `spawnToAll()` is called.

How close to a player a mob may spawn belongs to the group: 24 to 128 blocks unless the
rule's `distance_filter` says otherwise (fish use 12 to 32).

`AttemptContext` reads light (`World::getFullLightAt()`) and the population lazily, at
most once. The population is only read by the selector's cap and density
checks, after a group matched, or by a custom condition, after its group's other
conditions passed; positions that fail cheaper checks never trigger a census.

### Placement

Every position must pass two block checks, whatever the mob:

- **Ground** is a block with a full top surface, `getSupportType(Facing::UP) === FULL`:
  stone, ice, upper slabs and soul sand qualify; leaves, lower slabs, carpets and fences
  don't. A column's ground is its highest such block, so air, liquids and canopies are
  skipped, and everything below it is a cave.
- **Feet and head** go in blocks with no collision boxes,
  `count(getCollisionBoxes()) === 0`: air, liquids, grass, flowers, and also torches,
  rails and buttons.

Aquatic mobs spawn in the liquid block right above the ground (the sea floor), as in
vanilla. The pass and the census share one `GroundLevelCache`, which memoizes ground Y
per column.

### Population

`spawning/population/` answers one question: how many mobs of a category, or of a type,
are around a chunk, on the surface or underground. `MobPopulation` is the entry point; a
pass gets one `PopulationCensus` from it per world and tick.

The census counts a chunk from `World::getChunkEntities()` the first time it is needed,
and a position's population is the sum over the 9×9 chunks around its chunk, as a
`PopulationCounts`. Any entity whose identifier has registered spawn rules counts,
including PocketMine's squid.

The band an entity counts in is kept by `EntitySpawnBands`, on the spawning side: entity
classes know nothing about natural spawning. `HerdSpawner` sets it for the mobs it
places. For any other entity (spawned another way, or loaded from disk) the census sets
it from where the entity stands the first time it counts it. Either way the band then
stays for as long as the entity object lives, so the ground is read once per entity, not
once per count.

After a herd, each spawned member is added to its chunk's counts and to every region
already summed that covers the chunk, as vanilla does; nothing is recounted.

### Memo validity

Memos keyed by location (ground Y, chunk and region counts, an attempt's light and
population) live only as long as one `WorldSpawnPass`, so world edits between ticks can't
make them stale. Within a pass only factories can edit the world, so after every herd the
pass drops the ground memo. The census is not recounted (see Population), so a factory
that spawns or removes other entities is only seen by chunks not counted yet.

Entity bands are the exception: they outlive the pass, tied to the entity object.

The only long-lived cache, `CandidateCache`, is keyed by values, never by location: every
attempt reads its key fresh from the world, so no world edit (`setChunk()`,
`setBiomeId()`, `setBlock()`, `setDifficulty()`) can make it stale.

## Candidate cache

`CandidateCache` partially evaluates every rule once per key
`(biome id, band, difficulty, feet liquid)`:

- a cacheable condition that returns false against the key drops its group;
- one that returns true is removed from the group;
- one that reads a per-attempt value (so `KeyContext` throws `PointInputRequired`), or
  that isn't cacheable, stays as a residual and runs on every attempt.

A key only admits groups whose required liquid is its feet liquid: aquatic groups spawn
only in their liquid, and land groups, which carry no "not in water" condition, only out
of any liquid (`SpawnRuleGroup::admitsLiquid()`, which `SpawnRuleGroup::matches()` applies
too). Surviving groups keep their order, and a group's residuals keep the order of its
conditions: list the costly ones, such as a read of the population, last.

The key packs into one int; out-of-range values throw instead of colliding. Results are
interned, so the whole vanilla key space takes about 300 KB. The cache holds at most 4096
keys, is cleared when full, and is rebuilt when the registry revision changes.

## Conditions

A rule set (`SpawnRules`) is a list of `SpawnRuleGroup`s; **every matching group
competes** in the weighted pick, as each is its own entry in vanilla. A group carries its
conditions plus its weight, rarity, herd size, `permute_type` weights, player distance
range, density limits and required liquid (`spawns_underwater`, `spawns_lava`; a group
can't require both). Conditions implement:

```php
interface SpawnCondition{
	public function isCacheable() : bool;
	public function test(SpawnConditionContext $ctx) : bool;
}
```

Built-ins live in `spawning/condition/`: `RangeCondition` (brightness, difficulty,
world age, band), `HeightCondition` (tests the block stood on, and the feet
too in a liquid, as vanilla does), `BiomeTagCondition`, `SpawnsOnBlock`,
`SlimeChunkCondition`, and the `AllOf`, `AnyOf`, `Not` combinators.

### Condition contract

The candidate cache relies on these four rules. The cache can't check them, so custom
conditions must follow them:

1. **Immutable.** Every property is `readonly` and set in the constructor, with no mutable
   objects captured.
2. **Pure when cacheable.** If `isCacheable()` returns `true`, `test()` reads only the
   context: no statics, singletons, configs, services, randomness or clocks. Return
   `false` for anything else, or call `SpawnRuleRegistry::invalidateCache()` whenever that
   outside state changes.
3. **No side effects.** `test()` never changes the world or any other state. The cache
   removes the conditions a key already decides and runs the rest in their listed order,
   so `test()` must not depend on which other conditions ran before it.
4. **Let `PointInputRequired` propagate.** It extends `\Error`, so `catch(\Exception)`
   won't swallow it; don't catch `\Throwable` or `\Error` inside a condition.

`AllOf`, `AnyOf` and `Not` are cacheable only when all their children are; a custom
combinator can extend `CompositeCondition` to get the same rule.

A condition that needs more than the context's values can read the attempt's world
through `getWorld()`. Like the coordinates it is a per-attempt value, so such a condition
always runs on every attempt against the live world. It may read the world, never change
it (rule 3).

## Loading

The vanilla rules load in `MobPlugin::onEnable()` through
`SpawnRuleRegistry::registerVanilla($path)`, which parses the resource with
`SpawnRulesParser::createVanilla()`, binds every implemented mob (plus PocketMine's
squid), and applies the slime-chunk workaround.

The loader is **strict**: any value it can't compile aborts the load with
`SpawnRulesParseException`, carrying the JSON path. Payloads are mapped into the
generated `XxxData` models with JsonMapper. By-design exceptions:

- `population_control` values vanilla spawns through events (`pillager`,
  `pillager_patrol`) skip their rule set;
- `powder_snow` (no PocketMine block) is dropped from block filters;
- components PocketMine can't implement (`mob_event_filter`, `delay_filter`,
  `player_in_village_filter`, `spawns_above_block_filter`) drop their group through
  `SpawnRuleGroupBuilder::markNeverSpawns()`;
- a group with neither `spawns_on_surface` nor `spawns_underground` is dropped: vanilla
  spawns it nowhere (only guardian, which spawns through structures);
- components with no runtime effect (`disallow_spawns_in_bubble`, `is_persistent`,
  `is_experimental`) are accepted and ignored.

`distance_filter` sets the group's player distance range (a group without one keeps
`SpawnRuleGroup`'s 24 to 128 block default), and `weight.rarity` becomes the group's
rarity.

## Registration API

```php
SpawnRuleRegistry::getInstance()->register(new SpawnRules(
	"minecraft:myboss",
	MobCategoryRegistry::MONSTER,
	[
		new SpawnRuleGroup([
			RangeCondition::brightness(0, 7),
			RangeCondition::difficulty(World::DIFFICULTY_EASY, World::DIFFICULTY_HARD),
			new SpawnsOnBlock([BlockTypeIds::STONE => true], false),
		], weight: 100),
	],
	fn(World $world, Vector3 $pos, SpawnRuleGroup $group) => new MyBoss(Location::fromObject($pos, $world)),
));
```

- `register()` throws if the category id isn't registered in `MobCategoryRegistry`, or if
  rules for the identifier exist and `override` is `false`.
- Rules store only the category id; every consumer looks the category up when it needs
  it, so re-registering a category (for example to raise a cap) applies to rules
  registered before it.
- A group keeps its mobs 24 to 128 blocks from the nearest player, as vanilla does by
  default. Change it with `minPlayerDistance:` / `maxPlayerDistance:` (`null` for no
  bound).
- Factories construct the entity but never spawn it: `HerdSpawner` calls `spawnToAll()`.
- `density_limit` is a group field too: `surfaceDensityLimit:` / `caveDensityLimit:` cap
  the mobs of the rule's own type in the region, and trim a herd to what is left.
- An aquatic group passes its liquid to the group, not as a condition:
  `new SpawnRuleGroup([...], weight: 10, requiredLiquid: SpawnLiquid::WATER)`.
- `SpawnRules::check($ctx)` is the uncached reference evaluation of the conditions: the
  cache returns the same groups for every context (`CandidateCacheTest`). Caps and
  density limits are the selector's, not part of it.

### Population queries

```php
$population = MobPopulation::getInstance();

// Counts over the 9×9 chunks around a chunk, as of now.
$counts = $population->around($world, $chunkX, $chunkZ);
$counts->getCategoryCount(MobCategoryRegistry::MONSTER, SpawnBand::CAVE);
$counts->getIdentifierCount(EntityIds::ZOMBIE, SpawnBand::SURFACE);

// A plugin that spawns a mob its own way can say which band it counts in.
$population->getBands()->set($entity, SpawnBand::SURFACE);
```

`around()` scans the entities of those 81 chunks on every call, so keep its result rather
than asking in a loop. Inside a condition, read `$ctx->getPopulation()` instead: it is
the same counts for the attempt's chunk, shared with the rest of the tick.

### Custom components

Component parsers are registered on a parser instance (`override: true` replaces an
existing one) and receive a `ComponentParseContext` scoped to one component occurrence:

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

`$ctx->mapList()` reads a component that is an object or a list of objects,
`$ctx->resolveBlockSet()` resolves block-name values, and for shapes with no model
`$ctx->objectOrList()` returns path-tracking `SpawnData` readers while `$ctx->getValue()` /
`$ctx->getPath()` expose the raw value and its JSON path. A parser that needs biome tags
captures `$parser->getBiomeTags()`.

## Settings

```yaml
mob-natural-spawning:
  enabled: true
  max-attempts-per-tick: 8   # ceiling on the columns tried in one tick, over every world
```

`enabled` in a world settings file (`worlds-settings/<world>.yml`) turns spawning off for
that world only; the global switch must be on for any world to spawn.
`max-attempts-per-tick` is read from the global file only, since it is one budget for the
whole server. It only caps the cost of a crowded tick: the spawn rate is vanilla's 11 in
2000 per ticking chunk, so it follows PocketMine's `chunk-ticking.tick-radius`.

## Timings

`Natural Spawning` covers every pass of a tick, with these children:

| Timing | Covers |
|---|---|
| `Sample` | column sampling, position checks and the cache lookup |
| `Candidate Resolve` | cache misses only (runs inside `Sample`) |
| `Select` | `SpawnSelector` |
| `Census` | counting a region the first time a pass needs it (runs inside `Select`) |
| `Spawn` | herd placement and factories |

## Approximations

The flow follows `BedrockSpawner` as traced in BDS 1.26.51.1. Deliberate deviations:

- A rule that fails a block filter, its liquid, its distance, its density limit or its
  category cap is left out of the weighted pick. Vanilla picks first and checks those
  after, wasting the attempt, so where several rules match vanilla spawns a little less.
- The ticking set is PocketMine's (a circle around each player), not vanilla's diamond.
- A tick radius below 4 spawns like a radius of 4. Vanilla doesn't spawn below 4, but
  PocketMine's default is 3.
- Ground is any block with a full top surface, so glass, barriers and upside-down stairs
  count although vanilla doesn't spawn on them.
- Every position is checked as a 1×2 block column, not with the mob's bounding box.
- A liquid position needs one block of its liquid; vanilla's surface needs two.
- `permute_type` event suffixes are stripped at parse time: permuted types spawn in base
  form. Every member rolls from the base type; vanilla keeps the last rolled type for an
  entry without `entity_type`, which looks like a bug. `min_guaranteed` is ignored (no
  bundled rule uses it).
- When `herd` is a list (one herd per spawn event, such as horse coat colours), only the
  first entry is used; the bundled lists differ only by event.
- Structure spawn areas (ocean monuments, fortresses...) are not implemented.
- At a tick radius of 5 or more, rules may spawn up to 128 blocks out, past the 64 block
  despawn distance of most categories.
- PocketMine has no weather, so `brightness_filter`'s `adjust_for_weather` is ignored.
- The global mob cap (200) is not enforced.
- Slimes: vanilla conditions must hold AND (Y ≤ 40 in a slime chunk OR biome tag
  `spawns_slimes_on_surface`). The slime-chunk check is Bedrock's real algorithm (a
  coordinate-seeded MT19937, no world seed), reverse engineered by @protolambda and
  @jocopa3.
- The band a mob was spawned in is not saved. A mob loaded from disk, or spawned another
  way, takes the band of where it stands when first counted and keeps it while loaded.
  Vanilla saves the band and counts mobs that didn't spawn naturally as underground.
