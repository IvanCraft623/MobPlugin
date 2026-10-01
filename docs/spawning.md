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
├── spawner/     the runtime (internal)
└── parse/       the strict loader; parse/schema/ is generated
```

## Runtime

Everything runs on the main thread, once per tick, inside `NaturalSpawner::tick()`.

### Budget

Each tick lists one anchor per player in every world with spawning enabled (peaceful
worlds included: the data decides what spawns there). A cursor kept across ticks takes
the next `attempts-per-tick` anchors, wrapping around, so every player gets the same share
over time. Each world with selected anchors gets one `WorldSpawnPass` for the tick.

The registry revision is read once per tick, so rules registered mid-tick apply from the
next tick.

### One attempt

Each attempt runs from start to finish before the next one begins:

1. **Sample.** Pick a column in the 24–44 block ring around the anchor (uniform over the
   ring's area): one surface position on its ground plus two cave positions at random
   depths below it. A position is dropped if its chunk isn't light populated, it is within
   24 blocks of any player, or its feet or head cell is solid.
2. **Candidates.** Build an `AttemptContext` and ask the `CandidateCache` which rules could
   still spawn there. An empty list ends the position.
3. **Select.** `SpawnSelector` skips candidates whose category is unregistered or has a
   cap of 0 in the band. Each remaining candidate contributes its first matching group
   unless its category is at its cap. One match is picked by group weight, then the cap
   roll `(cap - count) / cap` decides.
4. **Spawn.** `HerdSpawner` computes every member position, then picks the `permute_type`
   and calls the factories and `spawnToAll()`. Surface members stand on their own
   column's ground; cave and aquatic members keep the lead's depth. Members within 24
   blocks of a player are skipped.

`AttemptContext` reads light (`World::getFullLightAt()`) and the population lazily, at
most once. The population is only read by a density limit, after its group's other
conditions passed, or by the cap check, after a group matched; positions that fail
cheaper checks never trigger a census.

### Placement and census

`SpawnPlacement` defines the ground (the highest solid, full, opaque block, so air,
liquids and canopies are skipped) and whether a mob fits (land mobs: passable, non-liquid
feet and head over spawnable ground; aquatic mobs: the required liquid at the feet and a
passable head). The pass, census and herd spawner share one instance, which memoizes
ground Y per column.

`PopulationCensus` counts mobs per chunk from `World::getChunkEntities()` by band,
category and identifier, the first time a chunk is needed. Any entity whose identifier has
registered spawn rules counts, including PocketMine's squid. A position's population is
the sum over the 9×9 chunk grid around its chunk.

### Memo validity

Memos keyed by location (ground Y, chunk and region counts, an attempt's light and
population) live only as long as one `WorldSpawnPass`, so world edits between ticks can't
make them stale. Within a pass only factories can edit the world, so after every herd
(its lead always spawns) the pass calls `invalidateWorldMemos()` and the next attempt
recounts from the world, new entities included.

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
too). Surviving groups keep their order, so "first match wins" is unchanged. Within a group, residuals that read the population (such as
`density_limit`) run last.

The key packs into one int; out-of-range values throw instead of colliding. Results are
interned, so the whole vanilla key space takes about 300 KB. The cache holds at most 4096
keys, is cleared when full, and is rebuilt when the registry revision changes.

## Conditions

A rule set (`SpawnRules`) is an ordered list of `SpawnRuleGroup`s; the **first matching
group wins**. A group carries its conditions plus its weight, herd size, `permute_type`
weights and required liquid (`spawns_underwater`, `spawns_lava`; a group can't require
both). Conditions implement:

```php
interface SpawnCondition{
	public function isCacheable() : bool;
	public function test(SpawnConditionContext $ctx) : bool;
}
```

Built-ins live in `spawning/condition/`: `RangeCondition` (brightness, difficulty,
height, distance, world age, band), `BiomeTagCondition`, `SpawnsOnBlock`,
`DensityLimitCondition`, `SlimeChunkCondition`, and the `AllOf`, `AnyOf`, `Not`
combinators.

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
   may reorder a group's residual conditions, so `test()` must not depend on which other
   conditions ran before it.
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
- components with no runtime effect (`disallow_spawns_in_bubble`, `is_persistent`,
  `is_experimental`) are accepted and ignored.

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
- Factories construct the entity but never spawn it: `HerdSpawner` calls `spawnToAll()`.
- An aquatic group passes its liquid to the group, not as a condition:
  `new SpawnRuleGroup([...], weight: 10, requiredLiquid: SpawnLiquid::WATER)`.
- `SpawnRules::check($ctx)` is the uncached reference evaluation: the cache returns the
  same group for every context (`CandidateCacheTest`).

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
  attempts-per-tick: 3   # columns sampled per tick, round-robin over every player in every world
```

`enabled` in a world settings file (`worlds-settings/<world>.yml`) turns spawning off for
that world only; the global switch must be on for any world to spawn. `attempts-per-tick`
is read from the global file only, since it is one budget for the whole server.

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

Deliberate deviations from vanilla:

- Cave positions are sampled (two per column) instead of scanning every spawnable block.
- A counted mob's band comes from its current position, not where it spawned.
- `permute_type` event suffixes are stripped at parse time: permuted types spawn in base
  form.
- When `herd` is a list (one herd per spawn event, such as horse coat colours), only the
  first entry is used.
- Herd members are placed by room only; the rule's conditions aren't re-checked at their
  offsets.
- PocketMine has no weather, so `brightness_filter`'s `adjust_for_weather` is ignored.
- A herd can overshoot its category's cap: the cap is checked once, then the whole herd
  spawns (vanilla pack spawning).
- The global mob cap (200) is not enforced.
- Slimes: vanilla conditions must hold AND (Y ≤ 40 in a slime chunk OR biome tag
  `spawns_slimes_on_surface`). The slime-chunk check is Bedrock's real algorithm (a
  coordinate-seeded MT19937, no world seed), reverse engineered by @protolambda and
  @jocopa3.
