# Natural spawning — runtime rewrite plan

Status: **pending approval**.

Scope: the runtime (sampling → census → evaluation → spawn), plus a consolidation of
the `spawning/` package and its public API. What can spawn where comes **only** from
`spawn_rules.json`; there are no plugin-side gates. Public API may break freely.

---

## 1. How it works today

`NaturalSpawner::tick()` runs one synchronous pass per tick. The pass has five batched
stages, and each stage hands arrays of value objects to the next:

| Stage | Class | What it does | Output |
|---|---|---|---|
| Collect | `SpawnCollector` | Splits `attempts-per-tick` across eligible worlds. Each attempt picks a random player, a random point in the 24–44 ring, and a random column in it, then tries 1 surface position and 2 cave positions. | `list<SpawnPosition>` |
| Shortlist | `SpawnRuleIndex` | Folds each rule set into a conservative `SpawnConstraint` (bands, difficulty, biome tags, liquid) and filters on `(biome, band, difficulty, feet)`. Results are memoized under a string key. | positions + viable `SpawnRuleBinding`s |
| Census | `SpawnCensus` | Iterates **all** world entities and buckets them by chunk. For each position it sums mobs in a 72-block sphere, per identifier and per category, split by band. Finding an entity's band costs a column ground scan. | `list<SpawnCandidate>` (`SpawnCounts`) |
| Evaluate | `SpawnEvaluator` | Wraps each position in `CandidateSpawnEnvironment` + `SpawnConditionContext`. Skips capped categories, takes each rule's first matching group, picks one by weight, then does the cap roll. | `list<SpawnRequest>` |
| Apply | `SpawnApplier` | Re-validates the live world. Re-checks the cap and density limit against `SpawnTally`, a linearly scanned list of `Vector3`s spawned earlier in the same pass. Picks the `permute_type` and places the herd. | entities |

All stages share the ground/room rules in `SpawnPlacement`.

### What's wrong with it

**Design**

- **Batching forces a chain of intermediate objects:** `SpawnPosition` →
  `SpawnCandidate` → `SpawnRequest` → `SpawnConditionMatch`, plus `SpawnCounts`,
  `BandCounts` and `SpawnTally`. Batching only exists to amortize the full-world entity
  scan. Because of it, the applier has to re-validate positions and keep a second
  counter (`SpawnTally`) that duplicates the census.
- **The same facts exist in three forms:** `SpawnPosition` (fields),
  `SpawnEnvironment` / `CandidateSpawnEnvironment` (getters over those fields), and
  `SpawnConditionContext` (a third copy of x/y/z/band/difficulty…). Conditions read them
  inconsistently, e.g. `$ctx->y` vs `$ctx->env->getLight()`.
- **The planner is a second, approximate copy of condition semantics.** `SpawnConstraint`
  has its own AND/OR algebra, and conditions opt into it through four `*Constrained`
  marker interfaces. Every condition change must be mirrored there.
- **The package is fragmented:** 68 hand-written classes. Several are near-duplicates
  (five "value in range" filters, two parse exceptions, four block-name resolvers,
  three slime-chunk classes) or one-field wrappers (`Herd`, `PermuteType`, `BandCounts`,
  `SpawnEvent`). There are also two different classes named `SpawnConditionContext`.
  `SpawnRuleRegistry`'s constructor reaches into `MobPlugin::getInstance()`.

**Performance**

- The census iterates *every entity in the world* on each pass, even when only one
  position survived the shortlist.
- Each counted entity costs a full column ground scan, and nothing reuses the result.
  In a deep ocean that is about 60 block reads per column.
- The shortlist memo is keyed by a concatenated string. Biome-tag conditions rebuild a
  tag map on every `test()`.
- `SpawnTally::countNear()` does a linear scan over `Vector3`s.

---

## 2. Goals

1. **Main thread, bounded cost.** The steady-state work per tick is bounded by
   `attempts-per-tick`. Two costs sit on top of it, and both are bounded too:
   - a one-time `CandidateCache` resolve the first time a key is seen (~150 µs per key);
   - a census recount of the chunks later attempts touch, after each herd spawns (at
     most 81 chunk entity lists per region).
2. **No redundant work.** Each expensive computation (ground scan, per-chunk entity count,
   key-only conditions) runs at most once per pass, and only when a cheaper filter has
   not already rejected the position.
3. **One representation per concept, and as few classes as the design allows.**
4. **Exact planning.** Partial evaluation of the real conditions replaces the
   conservative constraint algebra.
5. **Data-driven only.** `spawn_rules.json` is the single authority, including for
   peaceful.
6. **No hot-path allocations, and no doc blocks unless requested.** Methods are camelCase
   `get*`/`is*`/`has*`. Where PocketMine already has the call (`World::getBlockAt()`,
   `World::getFullLightAt()`, `World::getChunk()`), use it directly; its internal caches
   are enough, so the spawner adds no block or chunk cache of its own.

---

## 3. New pipeline: streaming, not batched

Every attempt runs from start to finish before the next one begins:

```
NaturalSpawner::tick()
 └─ for each of attempts-per-tick (round-robin over players, grouped by world)
     └─ WorldSpawnPass (lazily, once per world per tick)
         ├─ sample column → surface + N cave positions
         ├─ new AttemptContext(...)                  (light + population lazy)
         ├─ CandidateCache::getCandidates(ctx)       → list<CandidateRule>
         │     (empty → next position; light and census never touched)
         ├─ SpawnSelector::select(ctx, candidates)  → ?array{CandidateRule, SpawnRuleGroup}
         └─ HerdSpawner::spawn(...)                 → WorldSpawnPass::invalidateWorldMemos()
```

The census is a lazy per-chunk memo over the live world. After a herd spawns, the pass
drops its world-derived memos, and the next attempt recounts from `World`, which already
contains the new mobs because entity constructors call `World::addEntity()`. The world
stays the single source of truth. As a result, **`SpawnTally`, `SpawnRequest`,
`SpawnCandidate` and the applier's re-validation all go away** (see §6.9).

---

## 4. Consolidation

### 4.1 Merges

Every class that exists today appears in exactly one row. Classes not listed are kept
as they are (`NaturalSpawner`, `SpawnBand`, `MobCategory`, `MobCategoryRegistry`,
`SpawnRules`, `SpawnRuleRegistry`, `AllOf`, `AnyOf`, `Not`, `BiomeTagCondition`,
`SpawnsOnBlock`, `DensityLimitCondition`, `BiomeFilterParser`, `SpawnData`, the
generated `schema/`), with their APIs changed as described in §5.

| Today | Becomes | Why |
|---|---|---|
| `BrightnessFilter`, `DifficultyFilter`, `DistanceFilter`, `HeightFilter`, `WorldAgeFilter`, `HabitatBandCondition`, `SpawnsInLiquid` | `condition/RangeCondition` | Each checks that one context value lies in `[min, max]`. Bounds are `?float`, where `null` means open-ended: `HeightFilter` and `WorldAgeFilter` can omit a side, and the distance is a float. A private kind selects the getter, and `getKind()` exposes it. Named constructors: `::brightness(min, max, adjustForWeather)`, `::difficulty()`, `::height()`, `::distance()`, `::worldAge()`, `::band(SpawnBand)`, `::liquid(SpawnLiquid)`. A band or liquid is the single-value range `[v, v]` over the enum's `->value`. |
| `NeverSpawnCondition`, `PassThroughSpawnCondition` | *(deleted)* | Pass-through components register no-op parsers. Unsupported components call `SpawnRuleGroupBuilder::markNeverSpawns()`, and the group is dropped at build time. A group that never matches cannot affect "first match wins". |
| `IsSlimeChunkCondition`, `slime/SlimeChunkChecker`, `slime/MersenneTwister` | `condition/SlimeChunkCondition` | One algorithm used by one condition. The MT19937 walk becomes private static methods, and `isSlimeChunk()` stays public static for the test. |
| `condition/vanilla/` subfolder | flattened into `condition/` | After the merges it holds only a handful of classes. |
| `SpawnCondition::getEvaluationCost()`, the cost sort in `SpawnGroupBuilder` | `SpawnCondition::isCacheable()` | The cache observes what each condition reads (§6.3), so nothing declares inputs or costs. Residual lists are short and keep the data's order. |
| `plan/`: `SpawnRuleIndex`, `SpawnConstraint`, `BiomeConstrained`, `DifficultyConstrained`, `HabitatConstrained`, `LiquidConstrained` | `spawner/CandidateCache` + `spawner/CandidateRule` | Exact partial evaluation of the real conditions replaces the conservative constraint algebra. |
| `SpawnPosition`, `SpawnEnvironment`, `CandidateSpawnEnvironment`, `condition/SpawnConditionContext` (class) | `condition/SpawnConditionContext` (interface) + `spawner/AttemptContext` + `spawner/KeyContext` | One representation of the facts about a position, read only through getters (§6.1). |
| `SpawnCollector` | `spawner/WorldSpawnPass` | Sampling is a loop over the pass's own state (world, players, placement). |
| `SpawnCensus`, `SpawnCounts`, `SpawnTally` | `spawner/PopulationCensus` + `spawner/RegionPopulation` | Lazy per-chunk counts over the live world. Recounting after a herd replaces the tally (§6.9). |
| `SpawnEvaluator`, `SpawnRequest`, `SpawnCandidate` | `spawner/SpawnSelector` | Streaming removes the hand-off objects. The selector returns `array{CandidateRule, SpawnRuleGroup}`. |
| `SpawnApplier` | `spawner/HerdSpawner` | Nothing changes between sampling and spawning, so no re-validation is needed. |
| `SpawnPlacement` (static) | `spawner/SpawnPlacement` (per-pass instance) | Owns the per-pass ground-Y memo. |
| `SpawnRuleBinding` | merged into `SpawnRules` | One immutable object: identifier, category **id**, groups, factory. The category is looked up live, never bound (§6.9, G5). |
| `payload/SpawnConditionGroup`, `payload/Herd`, `payload/PermuteType`, `payload/SpawnEvent` | `SpawnRuleGroup` | Herd becomes `herdMin`/`herdMax` ints, and permutations become `array<string, int>` (identifier → weight). The herd spawn event is deleted because nothing consumes it. Only `requiredLiquid` is precomputed in the constructor: density limits are checked by `DensityLimitCondition` against the live census, so there is no re-check that would need them. |
| `SpawnGroupBuilder` | `parse/SpawnRuleGroupBuilder` | Renamed after the class it builds. |
| `SpawnConditionMatch` | *(deleted)* | Factories receive the `SpawnRuleGroup`. |
| `BandCounts` | `MobCategory::$surfaceCap`, `$caveCap` + `getCap(SpawnBand)` | A pair of ints doesn't need its own class. |
| `BiomeTagMap`, `parse/resolver/BiomeTagResolver`, `parse/resolver/VanillaBiomeTagResolver` | `BiomeTagMap` (plain class, `fromBedrockData()` and `fromFiles()` factories) | The interface existed only so tests could substitute data. Tests now build a `BiomeTagMap` from an array. |
| `SpawnConditionRegistry`, `SpawnRulesFactory` | `parse/SpawnRulesParser` | It owns the component parsers and `parse(json)`. `SpawnRulesParser::createVanilla()` returns an instance, and plugins call `registerComponent()` on it. It is not a singleton. |
| `parse/SpawnConditionContext`, `SpawnConditionData` | `parse/ComponentParseContext` | The JsonMapper `map()`/`mapList()` helpers become its methods, and the name clash with the condition context goes away. |
| `SpawnParseException`, `SpawnModelParseException` | `parse/SpawnRulesParseException` | The loader is strict and has a single failure mode. |
| `resolver/BlockNameResolver`, `ChainBlockNameResolver`, `StringToItemBlockNameResolver`, `VanillaAliasBlockNameResolver` | `parse/BlockNameResolver` | One concrete lookup: `StringToItemParser` first, then the alias table. |
| Vanilla bootstrap in `SpawnRuleRegistry`'s private constructor | `SpawnRuleRegistry::registerVanilla(string $path)` | Called from `MobPlugin::onEnable()`. The constructor stays empty, and the registry no longer depends on `MobPlugin`. |

**New classes with no predecessor:** `SpawnLiquid` and `spawner/PointInputRequired`.

### 4.2 Resulting layout

```
spawning/
├── NaturalSpawner.php           entry point: scheduling (owned by MobPlugin)
├── SpawnBand.php                enum SpawnBand : int
├── SpawnLiquid.php              enum SpawnLiquid : int (NONE, WATER, LAVA)
├── MobCategory.php
├── MobCategoryRegistry.php
├── SpawnRules.php
├── SpawnRuleGroup.php
├── SpawnRuleRegistry.php
├── BiomeTagMap.php
│
├── condition/
│   ├── SpawnCondition.php
│   ├── SpawnConditionContext.php    interface: key + point getters
│   ├── AllOf.php
│   ├── AnyOf.php
│   ├── Not.php
│   ├── RangeCondition.php
│   ├── BiomeTagCondition.php
│   ├── SpawnsOnBlock.php
│   ├── DensityLimitCondition.php
│   └── SlimeChunkCondition.php
│
├── spawner/                     internal runtime
│   ├── WorldSpawnPass.php
│   ├── SpawnPlacement.php
│   ├── AttemptContext.php           real context, lazy light/population
│   ├── KeyContext.php               key-only context, point getters throw
│   ├── PointInputRequired.php
│   ├── CandidateCache.php           partial-evaluation cache
│   ├── CandidateRule.php            a rule's surviving groups + residuals for one key
│   ├── PopulationCensus.php
│   ├── RegionPopulation.php
│   ├── SpawnSelector.php
│   └── HerdSpawner.php
│
└── parse/
    ├── SpawnRulesParser.php
    ├── ComponentParseContext.php
    ├── SpawnRuleGroupBuilder.php
    ├── BiomeFilterParser.php
    ├── BlockNameResolver.php
    ├── SpawnData.php
    ├── SpawnRulesParseException.php
    └── schema/                  generated: path unchanged, generator untouched
        ├── SpawnSchema.php
        ├── VanillaSpawnConditions.php
        └── model/*Data.php
```

**Totals:** 37 hand-written classes, down from 68 today. The 15 generated schema classes
are unchanged.

Tests mirror the three subfolders. `SlimeChunkCheckerTest` becomes
`SlimeChunkConditionTest`. Tests that used `TestBiomeTagResolver` build a `BiomeTagMap`
from an array instead.

### 4.3 Kept separate on purpose

- **`SpawnSelector`** is pure: `Random` and `MobCategoryRegistry` are injected through
  its constructor, and it has no world access and no singletons. It is the main
  unit-test target.
- **`HerdSpawner`** holds about 100 lines of placement logic. Folding it into the pass
  would make the pass the new god class.
- **`SpawnPlacement`** is shared by the pass, the census and the herd spawner.
- **`RegionPopulation`** is a plain value that the census hands out and that conditions
  and the selector read. That keeps conditions server-free and testable.
- **`AttemptContext` / `KeyContext`** are two implementations of one interface. This is
  clearer than a key-only subclass padded with dummy values.
- **`AllOf`, `AnyOf`, `Not`** are needed by biome-filter trees and by the slime rule.
- **`SpawnsOnBlock`, `BiomeTagCondition`** are set membership tests, not ranges.

---

## 5. Public API

### 5.1 Rules: `SpawnRuleRegistry`, `SpawnRules`, `SpawnRuleGroup`

```php
final class SpawnRuleRegistry{
	use SingletonTrait;

	public function register(SpawnRules $rules, bool $override = false) : void;
	public function registerVanilla(string $spawnRulesPath) : void;
	public function unregister(string $identifier) : void;
	public function get(string $identifier) : ?SpawnRules;
	public function getAll() : array;
	public function getRevision() : int;
	public function invalidateCache() : void;
}
```

`registerVanilla()` does three things:

1. Parses the file through `SpawnRulesParser::createVanilla()`.
2. Binds `MobPlugin::ALL_ENTITIES` and `Squid`.
3. Applies the slime-chunk workaround.

```php
SpawnRuleRegistry::getInstance()->register(new SpawnRules(
	"minecraft:myboss",
	MobCategoryRegistry::MONSTER,
	[
		new SpawnRuleGroup([
			RangeCondition::brightness(0, 7),
			RangeCondition::difficulty(World::DIFFICULTY_EASY, World::DIFFICULTY_HARD),
		], weight: 100),
	],
	fn(World $world, Vector3 $pos, SpawnRuleGroup $group) => new MyBoss(Location::fromObject($pos, $world)),
));
```

The parser does not build `SpawnRules`, because it has no factory. It returns
`array<string, array{string, list<SpawnRuleGroup>}>`, mapping each identifier to its
category id and groups. `registerVanilla()` attaches the factory. As today, factories
construct the entity but never spawn it.

`SpawnRules::check(SpawnConditionContext) : ?SpawnRuleGroup` is kept. It is the
uncached reference evaluation (first group whose conditions all pass), used by
`CandidateCacheTest`'s equivalence check and by anyone who needs a one-off evaluation
outside the spawner.

`register()` checks that the category id exists in `MobCategoryRegistry` and throws if
it doesn't, so typos fail at registration. `SpawnRules` stores only the id
(`getCategoryId() : string`) and never a `MobCategory` object (guard G5).

### 5.2 Categories: `MobCategoryRegistry`

`MobCategory(id, surfaceCap, caveCap, despawnDistance, noDespawnDistance)`, with
`getCap(SpawnBand)`. The registry API is unchanged.

Every consumer resolves the category live by id: the selector for caps, and
`Mob::checkDespawn()` for despawn distances. Re-registering a category (for example a
plugin raising the monster cap) therefore takes effect on the next attempt, including for
rules registered before the change.

### 5.3 Conditions: `condition\SpawnCondition`

```php
interface SpawnCondition{
	public function isCacheable() : bool;
	public function test(SpawnConditionContext $ctx) : bool;
}
```

`isCacheable()` tells the cache whether the condition's result depends **only on the
context**:

- **`true`**: the result depends only on the context. The cache may evaluate the
  condition against a key-only context and cache the outcome (§6.3). It doesn't matter
  which context getters the condition calls; the cache finds that out by itself.
- **`false`**: the condition also reads outside state, such as a plugin config, a
  service, or a random roll. The cache never pre-evaluates it; it stays in the residual
  list and runs on every attempt. This is the opt-out for plugins, and it is always
  correct.

**Condition contract (guard G1).** This is the only guarantee the cache cannot check:

1. **Immutable.** Every property is `readonly` and set in the constructor. There are no
   setters, and no mutable objects are captured.
2. **Pure, when cacheable.** If `isCacheable()` returns `true`, `test()` reads only the
   context: no statics, singletons, configs, services, randomness or clocks. Anything
   else must return `false`, or call `SpawnRuleRegistry::invalidateCache()` whenever
   that state changes.
3. **No side effects.** `test()` never mutates the world or any other state (§6.9).
4. **Let `PointInputRequired` propagate.** It extends `\Error`, so an ordinary
   `catch(\Exception)` inside a condition doesn't swallow it by accident.

Every built-in condition meets all four and returns `true` from `isCacheable()`. `AllOf`,
`AnyOf` and `Not` return `true` only when all of their children do. This contract moves
to `docs/spawning.md` in migration step 6.

`RangeCondition` reads its value through the matching context getter, selected by a
`match` on its kind, and compares it as a float against `?float` bounds (`null` means
open-ended). Brightness with `adjust_for_weather` also subtracts
`getWeatherLightPenalty()`. `SpawnRuleGroup` derives `getRequiredLiquid()` once, in its
constructor, from a top-level `RangeCondition` whose `getKind()` is liquid, or `NONE`
when there is none.

`SpawnRuleRegistry::invalidateCache()` bumps the revision, so the spawner drops its
`CandidateCache`. A plugin whose cacheable conditions depend on state that changes
rarely, such as a config reload, can call it instead of opting out of caching entirely.

### 5.4 Custom components: `parse\SpawnRulesParser`

```php
$parser = SpawnRulesParser::createVanilla();
$parser->registerComponent(
	VanillaSpawnConditions::MY_CUSTOM,
	static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
		$builder->addCondition(new MyCustomCondition($ctx->map(MyCustomData::class)->field));
	}
);
$parsed = $parser->parse($json);
```

---

## 6. Runtime

### 6.1 Contexts

`condition\SpawnConditionContext` is an **interface**, and conditions depend only on it.
Its getters fall into two groups:

```php
interface SpawnConditionContext{
	// key getters: fixed for one cache key
	public function getBiomeId() : int;
	public function getBand() : SpawnBand;
	public function getDifficulty() : int;
	public function getFeetLiquid() : SpawnLiquid;

	// point getters: vary per attempt
	public function getX() : int;
	public function getY() : int;
	public function getZ() : int;
	public function getGroundY() : int;
	public function getLight() : int;
	public function getWeatherLightPenalty() : int;
	public function getBelowTypeId() : int;
	public function getNearestPlayerDistance() : float;
	public function getTime() : int;
	public function getPopulation() : RegionPopulation;
}
```

There are two implementations, both final and both in `spawner/`:

- **`AttemptContext`** is the real context. `WorldSpawnPass` builds one for each sampled
  position from values it already has: coordinates, ground Y, band, biome, feet liquid,
  the block below, player distance, difficulty and time. It computes the two expensive
  values **lazily, once, on first call**:
  - `getLight()` calls `World::getFullLightAt()`;
  - `getPopulation()` calls `PopulationCensus::getRegionPopulation()`.

  A position whose candidate list is empty therefore never pays for light or census.
  Building the context is cheap (one small object, about 9 per tick), so the cache can
  take the context directly and read the key from it.
- **`KeyContext`** holds only the four key values. Every point getter throws
  `PointInputRequired`. It is used only inside `CandidateCache` when a key misses.

**Weather placeholder:** `WorldSpawnPass` passes `$weatherLightPenalty = 0; //TODO: weather
(not implemented in PocketMine-MP)` into every `AttemptContext`. This is the only spot to
change once weather lands.

`SpawnLiquid` is an int-backed enum (`NONE`, `WATER`, `LAVA`). It replaces raw
`BlockTypeIds` ints, so the key and `RangeCondition::liquid()` share one small value set.
`SpawnRuleGroup::getRequiredLiquid() : SpawnLiquid` is computed once in its constructor
(§5.3).

### 6.2 `spawner\SpawnPlacement` (per pass)

- `isSpawnableGround(Block) : bool` (static)
- `getGroundY(int $x, int $z) : int` scans down from `World::getHighestBlockAt()` using
  `World::getBlockAt()`. The result is memoized per column for the pass. It memoizes a
  computed value, not blocks, and the pass, the census and the herd spawner all share it.
- `hasRoom(int $x, int $y, int $z, SpawnLiquid $requiredLiquid) : bool`

### 6.3 `spawner\CandidateCache`: partial-evaluation cache

#### Responsibility

For a context, the cache answers which rules could still spawn there, and which
conditions remain to be checked for each of them. The answer depends only on the four
key values, so it is computed once per key and reused.

#### API

```php
final class CandidateCache{
	public const DEFAULT_MAX_KEYS = 4096;

	public function __construct(array $rules, int $maxKeys = self::DEFAULT_MAX_KEYS, ?TimingsHandler $resolveTimings = null); // list<SpawnRules>

	public function getCandidates(SpawnConditionContext $ctx) : array;             // list<CandidateRule>
	public function clear() : void;
	public function getSize() : int;
}

final class CandidateRule{
	public function getRules() : SpawnRules;
	public function match(SpawnConditionContext $ctx) : ?SpawnRuleGroup;         // first group whose residuals pass
}
```

- `NaturalSpawner` owns exactly one `CandidateCache`, shared by all worlds because the
  key already contains everything world-dependent (biome, difficulty). It builds a new
  cache from `SpawnRuleRegistry::getAll()` whenever `getRevision()` changes, which
  includes `invalidateCache()`.
- `getSize()` is for tests and debugging.
- `CandidateRule` keeps its `(group, residual conditions)` pairs private. The selector
  only calls `match()`, so no parallel arrays or residual lists leak out.

#### Lookup (hot path)

```php
public function getCandidates(SpawnConditionContext $ctx) : array{
	$key = self::hash($ctx->getBiomeId(), $ctx->getBand(), $ctx->getDifficulty(), $ctx->getFeetLiquid());
	return $this->entries[$key] ?? $this->resolve($key, KeyContext::from($ctx));
}

private static function hash(int $biomeId, SpawnBand $band, int $difficulty, SpawnLiquid $liquid) : int{
	if($biomeId < 0 || $difficulty < 0 || $difficulty > 3){
		throw new \InvalidArgumentException("Spawn key out of range: biome $biomeId, difficulty $difficulty");
	}
	return ($biomeId << 5) | ($difficulty << 3) | ($band->value << 2) | $liquid->value;
}
```

The key is a single int, packed as 2 bits of liquid, 1 bit of band, 2 bits of difficulty
and the biome id above them. A hit costs four getter calls, three int comparisons and one
array read, with no allocation.

The range check is guard G3. Two different keys can never share a slot: an out-of-range
difficulty or a negative biome id throws instead of silently colliding. `SpawnBand` and
`SpawnLiquid` are enums, so their ranges are fixed at compile time and a test pins them
(below).

#### Resolve (a miss, once per key)

```php
private function resolve(int $key, KeyContext $keyContext) : array{
	if(count($this->entries) >= $this->maxKeys){
		$this->clear();
	}
	$candidates = [];
	foreach($this->rules as $rules){
		$candidate = $this->resolveRule($rules, $keyContext);
		if($candidate !== null){
			$candidates[] = $candidate;
		}
	}
	return $this->entries[$key] = $this->internList($candidates);
}
```

`resolve()` is atomic (guard G4): the entry is written only by its final line. If a
condition throws anything other than `PointInputRequired`, the exception propagates
before the write, so nothing (not even a partial list) is cached for that key. The next
attempt retries the resolve and throws again, so the failure stays visible and is never
hidden behind a cached result.

`resolveRule()` walks the rule's groups in order and, for each group:

1. **Liquid gate.** If the key's liquid isn't `NONE` and the group's
   `getRequiredLiquid()` differs from it, the group is skipped. This keeps land mobs out
   of water, since vanilla land rules carry no "not in water" condition.
2. **Conditions.** Each of the group's conditions is classified:

   | Case | Outcome |
   |---|---|
   | `!isCacheable()` | goes to the residual list |
   | `test($keyContext)` returns `false` | the group is **dropped** for this key |
   | `test($keyContext)` returns `true` | the condition is **removed** (always holds for this key) |
   | throws `PointInputRequired` | goes to the residual list |

   Any other exception propagates, because it is a bug in the condition and must not be
   hidden by the cache.
3. The surviving groups keep their original order, so "first match wins" is unchanged.
   A rule with no surviving groups returns `null`.

A timing, `Natural Spawning - Candidate Resolve`, wraps `resolve()`, so cache warm-up
shows up in timings reports. `NaturalSpawner` passes it in as `$resolveTimings`, so the
cache itself doesn't depend on `CustomTimings` and tests need no timings setup.

#### Why it is exact

A cacheable condition's result depends only on the context. If its evaluation against
`KeyContext` finished, it read only key getters. So every context with the same key
follows the same path and returns the same value. Short-circuits are handled for free:
`AnyOf(biomeTag, height)` in a biome where the tag matches returns before reading the
height, so it is cached for that biome.

The one limitation is an early point read inside a combinator. For example,
`AnyOf(AllOf(height, slimeChunk), biomeTag)` throws on `height`, so the whole `AnyOf`
stays residual even in biomes where `biomeTag` is true. It is still correct, just not
cached. Simplifying combinator trees is a possible later addition, not part of this plan.

#### Bounded memory

Three structures grow, and all three are capped by `maxKeys` and emptied together by
`clear()`:

| Structure | Contents | Bound |
|---|---|---|
| `entries` | `array<int, list<CandidateRule>>`, key → shared list | `maxKeys` (4096) |
| `rulePool` | `array<string, CandidateRule>`, interned by signature | ≤ rules × distinct residual shapes seen |
| `listPool` | `array<string, list<CandidateRule>>`, interned by signature | ≤ `entries` |

- **Interning.** Many keys resolve to identical results, for example every difficulty for
  a passive mob in the same biome. A `CandidateRule` is interned by the signature
  `spl_object_id(rules)` plus, per surviving group, `spl_object_id(group)` and the ids of
  its residual conditions. Whole lists are interned by the joined ids of their
  `CandidateRule`s. Object ids are stable because the cache holds the rules for its whole
  lifetime.
- **Measured** with the vanilla rules and the benchmark prototype:

  | | Without interning | With interning |
  |---|---|---|
  | Per key | 5.9 KB | 0.5 KB |
  | Whole vanilla key space (88 biomes × 2 × 4 × 3 = 2,112 keys) | 5.6 MB | **~300 KB** |

  About half of the interned total is the `entries` array itself. Filling every key takes
  about 150 ms in total, but in practice keys fill lazily and only a few hundred are ever
  seen.
- **The cap** exists only for non-vanilla biome ids, such as custom generators. When
  `entries` reaches `maxKeys`, everything is cleared and refills lazily at roughly
  70–150 µs per key. There is no LRU; a full clear is simpler, and reaching the cap at all
  is exceptional.
- **Invalidation.** A new registry revision replaces the whole cache, and nothing else
  invalidates it.

#### Tests (`CandidateCacheTest`)

- **Equivalence.** Across the vanilla rules and random contexts, the rules for which
  `getCandidates()` + `CandidateRule::match()` return a group are exactly the rules whose
  full `SpawnRules::check()` passes, taking the same group. This is the benchmark's check,
  kept as a test.
- **Non-cacheable conditions.** A spy condition with `isCacheable() === false` is never
  called with a `KeyContext` and is called on every attempt.
- **Key-only conditions.** A cacheable spy that reads only the biome is called once per
  key, however many attempts there are.
- **Liquid gate.** A water position yields only water-requiring groups.
- **Bound.** With `maxKeys: 4`, five distinct keys leave `getSize() <= 4`.
- **Interning.** Two keys with identical outcomes return the same list (`assertSame`).
- **`KeyContext`.** Covered by `KeyContextTest` (§6.9, G2).

### 6.4 `spawner\PopulationCensus` (per pass)

- `getChunkPopulation()` (private) runs the first time a chunk is touched. It walks
  `World::getChunkEntities()`, resolves each entity with
  `SpawnRuleRegistry::get($entity::getNetworkTypeId())` and skips entities with no rules.
  Its band comes from `getGroundY()`. Counts go into
  `[band->value][categoryId] => int` and `[band->value][identifier] => int`. The string
  keys are interned identifiers whose hashes PHP caches, and no strings are concatenated.
- `getRegionPopulation(int $chunkX, int $chunkZ) : RegionPopulation` sums the **9×9
  chunk grid** around the given chunk, memoized per center chunk (int
  `World::chunkHash` key).
- `clear() : void` drops every chunk count and region sum. `WorldSpawnPass` calls it
  after a herd spawns (§6.9). There is no `recordSpawn()`: the next query recounts from
  the world, which already contains the new entities.

`RegionPopulation` provides `getCategoryCount(string $categoryId, SpawnBand $band)` and
`getIdentifierCount(string $identifier, SpawnBand $band)`.

### 6.5 `spawner\WorldSpawnPass`

It is built once per world per tick and holds the `World`, player positions,
`SpawnPlacement`, `PopulationCensus`, the shared `CandidateCache`, and the difficulty,
time and weather values for the pass. `attempt(Vector3 $anchor)` (the anchor player's
position, so tests need no `Player`) does the following:

1. Picks a ring offset (`getRingOffset()`, static and testable).
2. Picks a column, then its surface position and cave positions.
3. Rejects positions cheaply first: chunk ready → feet/ground blocks → player distance.
4. Builds an `AttemptContext` and calls `$cache->getCandidates($ctx)`. If the list is
   empty, it moves to the next position; light and census are never touched.
5. Calls `SpawnSelector::select($ctx, $candidates)` and hands the result to
   `HerdSpawner`.

### 6.6 `spawner\SpawnSelector` (pure)

```php
final class SpawnSelector{
	public function __construct(Random $random, MobCategoryRegistry $categories);

	public function select(SpawnConditionContext $ctx, array $candidates) : ?array; // array{CandidateRule, SpawnRuleGroup}
}
```

For each candidate:

1. Resolve its category live, via `$this->categories->get($rules->getCategoryId())`,
   and skip the candidate if the category was unregistered. Skip it too if the category
   is at its cap in the position's band, read from
   `getPopulation()->getCategoryCount()`.
2. Otherwise `match()` returns the first group whose residuals pass.

Among the matches, it picks one by group weight and then applies the cap roll
`(cap - count) / cap`. It has no world access and no singletons, so tests pass in a
seeded `Random` and their own `MobCategoryRegistry`.

### 6.7 `spawner\HerdSpawner`

It uses the current herd logic: `permute_type` pick, herd size, member offsets, surface
members placed on their own column's ground, cave and aquatic members placed at the
lead's depth, and a player-distance check.

It works in two phases: first it **computes every member position**, then it **calls the
factories** and `spawnToAll()`. Placement reads the world before any plugin code (the
factory) runs, so a factory that edits the world can't invalidate a position that was
computed from memos. `spawn()` returns whether any factory ran, and if one did,
`WorldSpawnPass` calls `invalidateWorldMemos()`.

### 6.8 `NaturalSpawner`

- **Registry revision:** checked once at the start of each tick. If the registry changes
  mid-tick (for example from a factory), the old cache is still valid for the old rules
  until the tick ends, so the tick stays consistent.
- **Eligible worlds:** any world with players, including peaceful.
- **Attempts:** each tick builds a flat list of `(world, player)` anchors. A persistent
  cursor takes the next `attempts-per-tick` anchors from it, wrapping around, and if the
  list is shorter than the budget a player gets more than one attempt. The selected
  anchors are then grouped by world, and each world's `WorldSpawnPass` is built once and
  runs its anchors in order. The cursor persists across ticks, so every player gets the
  same share over time, whatever their world.
- **Timings:** `Natural Spawning` (total), `Sample`, `Census`, `Select`, `Spawn` and
  `Candidate Resolve` (cache misses only, §6.3).

### 6.9 Cache validity

The rule is: **caches that live longer than a pass are keyed by values, never by
location. Caches keyed by location never outlive a pass, and they are dropped whenever
code we don't control may have run.** Under that rule the world never has to notify us
of changes: no `ChunkListener`, no event handlers, no invalidation calls from
`setChunk()`.

#### Value-keyed caches (long-lived)

| Cache | Key → value | What could make it wrong | Guard |
|---|---|---|---|
| `CandidateCache` | (biome, band, difficulty, liquid) → candidates | the rules, a condition's behaviour, the biome → tags data | registry revision; conditions and `SpawnRules`/`SpawnRuleGroup` are immutable (`readonly`); `isCacheable()` + `invalidateCache()` for outside state; `BiomeTagMap` is immutable (built once from bedrock data, no mutators) |
| `SlimeChunkCondition` memo | chunk coords → bool | nothing: pure arithmetic on the coords | none needed |

**Why `setChunk()` (or `setBiomeId()`, `setBlock()`, `setDifficulty()`) can't make
`CandidateCache` stale:** the cache never records which biome, difficulty or liquid a
position has. Every attempt reads those values **fresh from `World`** and uses them as the
lookup key. If a chunk's biome changes from plains to desert, the next attempt there
reads desert and uses the desert entry. The plains entry is still correct for every
position that really is plains. A world edit changes which key an attempt uses; it can't
change what a key means. Only the rules can do that, and they are revision-guarded.

`MobCategory` caps are not part of the cache. The selector reads them live on every
attempt.

`BiomeTagCondition` gets no memo of its own. It is key-only, so it runs only when the
cache resolves a key, and a memo would be a second cache with nothing to gain.

#### Location-keyed memos (one pass only)

| Memo | Owner | Key |
|---|---|---|
| ground Y | `SpawnPlacement` | column (x, z) |
| chunk population | `PopulationCensus` | chunk |
| region population | `PopulationCensus` | center chunk |
| light, population | `AttemptContext` | its own position (one attempt) |

These are created per `WorldSpawnPass` and garbage-collected at the end of the tick, so
nothing survives a tick. That covers every world edit made between ticks: other plugins,
players, generation, `setChunk()`.

**Within a pass**, the spawner runs as one scheduler task on the main thread, so the world
can only change through code the pass itself calls:

| Code the pass calls | Can it change the world? | Handling |
|---|---|---|
| `World` reads (`getBlockAt`, `getFullLightAt`, `getChunkEntities`…) | no | — |
| our entity constructors | only by adding the entity (`World::addEntity()`); `EntitySpawnEvent` fires later, on the entity's first update tick | the census is cleared after the herd, and the recount includes the new mobs |
| plugin **factories** | yes (arbitrary code) | members are placed before any factory runs (§6.7); `invalidateWorldMemos()` runs afterwards |
| plugin **conditions** (`test()`) | forbidden by contract | documented: conditions must not mutate the world; they read only the context |

`WorldSpawnPass::invalidateWorldMemos()` clears `SpawnPlacement`'s ground-Y memo and
`PopulationCensus`. It runs at most once per herd, and there are only a few herds per
tick. The recount it causes is limited to the chunks later attempts actually touch.

**Deliberately rejected: a `ChunkListener`.** PocketMine's `ChunkListener`
(`onChunkChanged`, `onBlockChanged`, …) could invalidate location-keyed data precisely.
But it has to be registered per chunk, fires on every block change in those chunks, and
is only needed if location-keyed data outlives a pass. The rule above makes it
unnecessary.

#### Guards

Beyond world edits, a cached entry could only become wrong in five ways. Each one has a
guard:

| # | Risk | Guard | Kind |
|---|---|---|---|
| G1 | A cacheable condition reads outside state, or is mutable | The condition contract (§5.3); `isCacheable()` + `invalidateCache()` as the escape hatches | documented contract (the only unenforceable guard) |
| G2 | `KeyContext` returns a placeholder for a per-attempt getter instead of throwing | Reflection test over every `SpawnConditionContext` method | CI |
| G3 | Two different keys pack to the same int | Range check in `hash()`; test pinning the enum ranges | runtime + CI |
| G4 | A condition throws halfway through a resolve and a partial entry gets cached | `resolve()` writes the entry last | by construction |
| G5 | A re-registered `MobCategory` isn't seen by rules registered earlier | Rules store the category id; consumers resolve it live | by construction |

With G2–G5 enforced, the only way to get a wrong entry is to break the documented
condition contract, and no runtime check could catch that anyway.

#### Tests

- `CandidateCacheTest` (equivalence) already shows that an entry depends only on its key.
- **G2** `KeyContextTest`: reflects over `SpawnConditionContext`. `getBiomeId`, `getBand`,
  `getDifficulty` and `getFeetLiquid` must return the constructed values, and **every
  other method** must throw `PointInputRequired`. The list of key getters is a constant
  in the test, so a new interface method is treated as per-attempt and must throw; adding
  a key getter is a deliberate, reviewed change.
- **G3** `CandidateCacheTest`: every `SpawnLiquid` case value is `< 4` and every
  `SpawnBand` value is `< 2`. `hash()` is private, so the range check is tested through
  `getCandidates()`: a stub `SpawnConditionContext` returning difficulty `-1` or `4`, or
  a negative biome id, must throw `\InvalidArgumentException`.
- **G4** `CandidateCacheTest`: a condition that throws `\RuntimeException` makes
  `getCandidates()` throw, and afterwards `getSize()` is unchanged and a second call
  throws again.
- **G5** `SpawnSelectorTest`: after a category is re-registered with a lower cap, a rule
  registered before the change is capped at the new value.
- `WorldSpawnPassTest`, with a fake world: after a factory changes a column's blocks,
  the next attempt's ground Y reflects the change. After a herd spawns, the next region
  population includes it.

---

## 7. Hot-path rules

- No `Vector3`, closure or array-of-arrays allocations per attempt. The only allocations
  are one `AttemptContext` per sampled position and a `Vector3` when an entity is
  constructed. A cache hit allocates nothing.
- Memo keys are ints (cache key, `World::chunkHash`, column key), never concatenated
  strings.
- Per-pass memos (ground Y, chunk populations, regions) are dropped at the end of each
  tick. The only long-lived memo is `CandidateCache`, which is capped and interned
  (§6.3).

---

## 8. Decisions (resolved)

| # | Decision | Resolution |
|---|---|---|
| D1 | Population region | 9×9 chunk grid around the position's chunk |
| D2 | Budget distribution | Round-robin per player |
| D3 | Wall-clock budget | No; attempts only |
| D4 | API compatibility | Free to break (§4–§5) |
| D5 | Block access | `World::getBlockAt()`; no spawner-side block/chunk cache |
| D6 | `batchInterval` | Removed |
| D7 | Peaceful | No plugin gate; `spawn_rules.json` decides |

### Behaviour changes vs today

These are intended, and in-server testing should confirm each one:

| Change | What to expect in-game |
|---|---|
| Population region: 72-block sphere → 9×9 chunk grid (D1) | Caps count mobs in a 144×144 block area of full columns, with no vertical limit (the sphere had one). Surface and cave counts stay separate by band, as today. Expect slightly different densities near region edges and in tall cave systems. |
| Budget per player instead of per world (D2) | A world with many players gets proportionally more attempts. Single-player behaviour is unchanged. |
| Liquid gate per group instead of per rule | A rule whose groups mix land and water spawns (none in vanilla today) now spawns its water groups in water. Vanilla results are unchanged; the equivalence test confirms it. |
| Peaceful decided by data (D7) | Rule sets without a `difficulty_filter` (some enderman and guardian groups) can spawn on peaceful, as the data says. |
| Herd spawn event removed | No change in-game: it was parsed but never applied. |
| Recount after a herd instead of a tally | Density limits and caps within one tick now also see mobs that plugins or other code added mid-pass. |

---

## 9. Verification

Correctness is covered by the tests in §6.3 and §6.9 and by migration step 6's in-server
test. Performance gets its own checks, so the numbers in this plan stay true:

- **Benchmark in the repo.** The prototype (`bench-conditions.php`) is ported to the new
  API as `tools/bench/candidate-cache.php` in step 3. It prints µs per position for
  `CandidateCache` against uncached `SpawnRules::check()`, plus the memory for the full
  vanilla key space. It is not run in CI, because timing on shared runners is noise.
- **Targets**, from the prototype on PHP 8.2 with opcache:

  | Metric | Prototype | Accept up to |
  |---|---|---|
  | Candidate evaluation per position (warm cache) | 2.5 µs | 5 µs |
  | Full vanilla key space memory (interned) | ~300 KB | 1 MB |
  | Resolve per key (cold) | ~70–160 µs | 500 µs |

- **In-server timings** to compare before and after, with the same seed, players and
  route. `Natural Spawning` in total per tick should drop. `Census` should fall the most
  (it no longer walks the whole world), and `Candidate Resolve` should show up only
  while new biomes are being explored.

---

## 10. Migration steps

Each step passes php-cs-fixer, PHPStan level 9 and `composer test`.

1. **Rule model and parse consolidation** (no runtime behavior change):
   - Add `SpawnRuleGroup` (absorbing the payload classes) and the resolved `SpawnRules`.
   - Replace `BandCounts` with category caps.
   - Merge `BiomeTagMap`, the parse classes, the exceptions and the block resolver.
   - Add `registerVanilla()` and update `MobPlugin`, `Mob` and the tests.
   - `SpawnRules` stores the category id, and `Mob::checkDespawn()` and the evaluator
     resolve it live (G5).
2. **Conditions:**
   - Replace `getEvaluationCost()` with `isCacheable()`, and drop the cost sort from the
     builder.
   - Add `RangeCondition`, `SlimeChunkCondition` and `markNeverSpawns()`.
   - Flatten `condition/`.
   - Add `SpawnLiquid`, the `SpawnConditionContext` interface and `RegionPopulation`.
   - The old evaluator can't use the lazy `AttemptContext` yet, because it needs the
     pass, which doesn't exist until step 5. So it gets a temporary
     `spawner/SnapshotContext` implementing the interface over `SpawnPosition` +
     `SpawnCounts` with eager values. `SnapshotContext` is deleted in step 5.
   - Delete `SpawnEnvironment` and `CandidateSpawnEnvironment`.
3. **Candidate cache:**
   - Add `KeyContext`, `PointInputRequired`, `CandidateCache` and `CandidateRule`, plus
     `SpawnRuleRegistry::invalidateCache()`.
   - The old evaluator switches from `SpawnRuleIndex` to the cache, then `plan/` is
     deleted.
   - Add `CandidateCacheTest` (§6.3) and `KeyContextTest`, with the G2–G4 guards and
     tests (§6.9).
4. **Placement and census:**
   - Add `SpawnPlacement` (per pass) and `PopulationCensus`.
   - Test the 9×9 sums, and that after `clear()` a recount includes newly added
     entities.
5. **Streaming pass:**
   - Add `AttemptContext`, `WorldSpawnPass`, `SpawnSelector` and `HerdSpawner`, and
     rewrite `NaturalSpawner`.
   - Delete the old stage classes, `SnapshotContext` and `SpawnTallyTest`.
   - Port `SpawnCollectorTest` into `WorldSpawnPassTest` (ring offset) and
     `SpawnEvaluatorTest` into `SpawnSelectorTest`.
6. **Settings and docs:**
   - Rename the timings and rewrite `docs/spawning.md`.
   - Build the phar and test in-server.
