# Natural spawning

MobPlugin spawns mobs from vanilla Bedrock spawn rules. This document describes the
implemented architecture.

## Data & schema validation

`resources/spawning/spawn_rules.json` is a deterministic merge of Mojang's
`behavior_pack/spawn_rules/*.json` from a pinned
[bedrock-samples](https://github.com/Mojang/bedrock-samples) checkout. It is the only
data MobPlugin ships for natural spawning — there is no runtime fetch.

### How it is generated

```sh
# clone the pinned source once (see resources/spawning/NOTICE.md for the exact commit)
git clone --depth 1 https://github.com/Mojang/bedrock-samples .cache/bedrock-samples

# regenerate spawn_rules.json + NOTICE.md into resources/spawning/
composer compile-spawn-rules
#   (or: php tools/spawn-rules/compile.php --samples-dir=.cache/bedrock-samples)
```

`tools/spawn-rules/compile.php` is a **pure merger**: it strips JSON comments (some
vanilla files are not strict JSON), keys every entry by its `description.identifier`,
sorts identifiers and pretty-prints. Keys keep their `minecraft:` prefixes, union
shapes stay raw, and values stay byte-faithful — the tool does **not** rename, alias,
whitelist or resolve anything. All parsing semantics belong to the runtime loader
(`SpawnRulesParser`), so an uncompilable value fails loudly at startup rather than
being silently mangled by the build.

The merge doubles as a **schema-compatibility gate**: every `minecraft:spawn_rules`
body is validated against the official Mojang spawn schemas pinned by
`--schema-version` — a required argument, single-sourced from
`tools/spawn-rules/SCHEMA_VERSION` — and every condition component and
structural key must be declared by the pinned schema inventory. A failure means the
vanilla data drifted beyond what the plugin was built against — update the loader /
component registry (and, if intended, the pinned schema version) before recompiling. The
merger writes output only if every source file merged and validated cleanly. The schemas
are permissive (see the validation section below), so this is a structural gate;
semantic
correctness is enforced by the strict runtime loader.

### How schema validation is run

Validation is a plain PHPUnit test, so it runs offline and locally:

```sh
composer test                      # or: vendor/bin/phpunit --no-progress
```

`tests/phpunit/.../SpawnRulesDataInventoryTest.php`:
- checks `spawn_rules.json` decodes as valid JSON;
- checks every condition component key is a declared `VanillaSpawnConditions` constant
  (catches typo'd / unknown components);
- validates every entry's `minecraft:spawn_rules` body against the **official Mojang
  spawn schemas** via `SpawnRuleSchemaValidator`.

Note that the committed Mojang schemas are intentionally **permissive** (draft-07 allows
extra properties; `Description` only requires `identifier`), so schema validation is a
coarse shape gate — it cannot reject arbitrary garbage in a recognized component. The
authoritative guard is the **strict runtime loader**: `SpawnRulesParseableTest` feeds the
committed resource through `SpawnRulesParser`, which throws on any value it cannot
compile. Keep both: the schema check catches structural landmines before they reach the
parser, and the parse test is the source of truth for semantics.

The schemas are **not committed** in the repo — they are pulled as a composer `--dev`
dependency (`mojang/bedrock-samples`, a pinned `Mojang/bedrock-samples` checkout) and land
in `vendor/mojang/bedrock-samples/metadata/json_schemas`. The test validates against the
`server/spawn/1.21.50` spawn schemas plus the `Filter Group`, `Block Descriptor` and legacy
`Reference` files they `$ref`. Because it is `--dev`, it never ships in the plugin phar; only
`resources/spawning/spawn_rules.json` and `resources/global-settings.yml` are runtime
resources.

### Updating to a newer Mojang version

When Mojang publishes new spawn data / schemas, bring the pinned commit forward in this
order (each step errors loudly if it is skipped, so CI and `composer test` flag it):

```sh
# 1. move the pinned checkout to the new commit
git -C .cache/bedrock-samples fetch --depth 1 origin main
git -C .cache/bedrock-samples checkout <new-sha>

# 2. regenerate the merged data + NOTICE (validated against the current, possibly newer, schema)
composer compile-spawn-rules

# 3. regenerate the generated artifacts from the schemas (version is required)
php tools/spawn-rules/generate-schema.php --schema-version=$(cat tools/spawn-rules/SCHEMA_VERSION)

# 4. bump the pinned bedrock-samples commit in composer.json (the mojang/bedrock-samples
#    source reference) so the --dev schemas match the new merge

# 5. review & commit the diff, then bump the pinned ref everywhere it appears:
#    - resources/spawning/NOTICE.md   (Source commit / Game version / Schema validation)
#    - composer.json                  (mojang/bedrock-samples source reference)
#    - tools/spawn-rules/SCHEMA_VERSION  (single source of truth; read by
#      composer compile-spawn-rules and the spawn-schemas CI gate)
```

After a bump, the checks that used to drift now pass: `composer test` validates the new
data against the new committed schemas, and `generate-schema.php --check` confirms the
regenerated artifacts match. If a component was added, renamed or removed, `VanillaSpawnConditions`
and `SpawnRulesParser::createVanilla()` must be updated first so the loader still accepts
every declared component (see [Conditions](#conditions)).

Two other checks complete the picture:

- `VanillaSpawnConditions` / `SpawnSchema` (`src/.../spawning/parse/schema/`) are **generated**
  from the same schemas by `php tools/spawn-rules/generate-schema.php`. `VanillaSpawnConditions`
  is one constant per condition; `SpawnSchema` also carries schema facts
  including the **envelope keys** the loader navigates with (`KEY_CONDITIONS`,
  `KEY_DESCRIPTION`, `KEY_POPULATION_CONTROL`), so those structural strings are
  schema-derived too rather than hand-written literals. Run
  `generate-schema.php --check --samples-dir=.cache/bedrock-samples` to confirm the
  committed artifacts still match regeneration (a stale artifact means Mojang renamed,
  added or removed a spawn component or an envelope key).
- The generated **payload models** (`spawning/parse/schema/model/`, one `XxxData` class)
  are emitted by the same generator, **derived from the payload-bearing schemas in the
  whole `server/spawn/<version>/` directory** (every component schema that declares
  properties — empty markers and structural/envelope schemas excluded). So when
  implementing a new spawn condition, its typed model already exists to consume. Each
  model is plainly typed public data (nullable optional fields, `@required` required,
  `@var`-annotated arrays) that `ComponentParseContext::map()` populates with **JsonMapper** (the
  same library PocketMine-MP uses for its data models). This is what removes the
  hand-written string-literal payload reads from the parser: a payload field Mojang
  renames desyncs the generated model and the `--check` gate fails, instead of spawning
  with a silently wrong value.
- `SpawnRulesParseableTest` strict-loads the committed resource through the real
  loader, so a regression in data ⇔ parser surfaces too.

CI runs `phpunit` (these tests) and `phpstan` on every push/PR; keeping the vendored
schemas, the merge output, and the generated enum artifacts in sync is an explicit,
conscious step. The artifact-drift check is now **wired into CI** too: the
`spawn-schemas` workflow runs `generate-schema.php --check` against the `--dev`
`mojang/bedrock-samples` checkout (installed into `vendor/` by `composer install`) —
no separate clone needed — so a Mojang component/envelope change that desyncs the
generated artifacts fails the build. The full merge/schema-validation gate still requires
the pinned clone (`composer compile-spawn-rules`). `compile.php`, `generate-schema.php`,
and the vendored schemas all carry their source-commit provenance in headers / NOTICE /
composer.json.

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

Each tick builds a flat list of anchors, one per player in every world (peaceful worlds
included: the data decides what spawns there). A cursor that persists across ticks takes
the next `attempts-per-tick` anchors, wrapping around, so every player gets the same share
over time. The anchors are grouped by world, and each world gets one `WorldSpawnPass` for
the tick.

The registry revision is read once per tick. Rules registered mid-tick (for example by a
factory) apply from the next tick.

### One attempt

Each attempt runs from start to finish before the next one begins:

1. **Sample.** Pick a column in the 24–44 block ring around the anchor (uniform over the
   ring's area) and try one surface position on its ground plus two cave positions at
   random depths below it. A position is dropped early if its chunk isn't light
   populated, it is within 24 blocks of any player, or its feet or head cell is solid.
2. **Candidates.** Build an `AttemptContext` and ask the `CandidateCache` which rules could
   still spawn there (see below). An empty list ends the position: light and census are
   never read.
3. **Select.** `SpawnSelector` skips candidates whose category is unregistered or has a
   cap of 0 in the position's band. Each remaining candidate contributes its first
   matching group, unless its category is at its cap; the population is read only once
   some group has matched. One match is picked by group weight, then the cap roll
   `(cap - count) / cap` decides.
4. **Spawn.** `HerdSpawner` computes every member position first, then picks the
   `permute_type`, calls the factories and `spawnToAll()`. Surface members stand on their
   own column's ground; cave members keep the lead's depth; aquatic members keep the
   lead's depth in the same liquid. Members closer than 24 blocks to a player are skipped.

`AttemptContext` reads light (`World::getFullLightAt()`) and the population only when a
condition or the selector asks for them, and at most once.

### Placement and census

`SpawnPlacement` is the single definition of the ground (the highest solid, full, opaque
block, so air, liquids and canopies are skipped) and of "a mob fits here" (land mobs:
passable, non-liquid feet and head over spawnable ground; aquatic mobs: the required
liquid at the feet and a passable head). The pass, the census and the herd spawner share
one instance, which memoizes ground Y per column.

`PopulationCensus` counts mobs per chunk from `World::getChunkEntities()`, the first time
a chunk is needed, by band, category and identifier. An entity counts when its identifier
has registered spawn rules, so PocketMine's own mobs such as squid count too. A position's
population is the sum over the 9×9 chunk grid around its chunk.

### Memo validity

Memos keyed by location (ground Y, chunk and region counts, an attempt's light and
population) belong to one `WorldSpawnPass` and are discarded when the tick ends, so world
edits between ticks can never make them stale. Within a pass, the only code that can edit
the world is a factory, so after any herd whose factories ran the pass calls
`invalidateWorldMemos()`, and the next attempt recounts from the world, which already
holds the new entities.

The only long-lived cache is `CandidateCache`, and it is keyed by values, never by
location, so no world edit (`setChunk()`, `setBiomeId()`, `setBlock()`,
`setDifficulty()`) can make it stale: every attempt reads its key fresh from the world.

## Candidate cache

`CandidateCache` partially evaluates every rule once per key
`(biome id, band, difficulty, feet liquid)`:

- a cacheable condition that returns false against the key drops its group;
- one that returns true is removed from the group;
- one that reads a per-attempt value (so `KeyContext` throws `PointInputRequired`), or
  that isn't cacheable, stays as a residual and runs on every attempt.

At a liquid key only groups that require that liquid survive: vanilla land rules carry no
"not in water" condition. The surviving groups keep their order, so "first match wins"
is unchanged. Within a group, residuals that read the population (such as
`density_limit`) run after the others, so a failing light or block check never triggers
a census.

The key packs into one int, and out-of-range values throw instead of colliding. Results
are interned, so the whole vanilla key space takes about 300 KB. The cache holds at most
4096 keys and is cleared when full; it is rebuilt when the registry revision changes.

Useful commands:

```sh
php tools/bench/candidate-cache.php   # cached vs uncached evaluation, memory, resolve time
```

## Conditions

A rule set (`SpawnRules`) is an ordered list of `SpawnRuleGroup`s; the **first matching
group wins**. A group carries its conditions plus its weight, herd size and
`permute_type` weights. Conditions implement:

```php
interface SpawnCondition{
	public function isCacheable() : bool;
	public function test(SpawnConditionContext $ctx) : bool;
}
```

Built-ins live in `spawning/condition/`: `RangeCondition` (brightness, difficulty,
height, distance, world age, band, liquid), `BiomeTagCondition`, `SpawnsOnBlock`,
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

`AllOf`, `AnyOf` and `Not` are cacheable only when all their children are.

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
- `SpawnRules::check($ctx)` is the uncached reference evaluation.

### Custom components

Parsers for new components are registered on a parser instance and receive a
`ComponentParseContext` scoped to one component occurrence:

```php
$parser = SpawnRulesParser::createVanilla();
$parser->registerComponent(
	VanillaSpawnConditions::MY_CUSTOM,
	static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
		$builder->addCondition(new MyCustomCondition($ctx->map(MyCustomData::class)->field));
	}
);
foreach($parser->parseFile($path) as $identifier => [$categoryId, $groups]){
	// build SpawnRules with a factory and register them
}
```

`$ctx->mapList()` reads a component that is an object or a list of objects,
`$ctx->resolveBlockSet()` resolves block-name values, and `$ctx->getValue()` /
`$ctx->getPath()` expose the raw value and its JSON path for shapes with no model.

## Settings

```yaml
mob-natural-spawning:
  enabled: true
  attempts-per-tick: 3   # columns sampled per tick, round-robin over every player in every world
```

## Timings

`Natural Spawning` covers the whole tick, with these children:

| Timing | Covers |
|---|---|
| `Sample` | column sampling and cheap position checks |
| `Candidate Resolve` | cache misses only, while new keys are seen |
| `Select` | `SpawnSelector`, including the lazy census |
| `Census` | counting a region the first time a pass needs it |
| `Spawn` | herd placement and factories |

## Approximations

Documented deviations from vanilla, all deliberate:

- Cave positions are sampled (two per column) instead of scanning every spawnable block.
- A counted mob's band comes from its current position, not where it spawned.
- `permute_type` event suffixes are stripped at parse time: permuted types spawn in base
  form.
- Herd members are placed by room only; the rule's conditions aren't re-checked at their
  offsets.
- PocketMine has no weather, so the weather light penalty is always 0
  (`WorldSpawnPass` holds the placeholder).
- A herd can overshoot its category's cap: the cap is checked once, then the whole herd
  spawns (vanilla pack spawning).
- The global mob cap (200) is not enforced.
- Slimes: vanilla conditions must hold AND (Y ≤ 40 in a slime chunk OR biome tag
  `spawns_slimes_on_surface`). The slime-chunk check is Bedrock's real algorithm (a
  coordinate-seeded MT19937, no world seed), reverse engineered by @protolambda and
  @jocopa3.
