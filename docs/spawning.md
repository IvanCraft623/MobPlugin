# Natural spawning

MobPlugin spawns mobs from vanilla Bedrock spawn rules. This document describes the
implemented architecture; class docblocks cover the fine detail.

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
(`SpawnRulesFactory`), so an uncompilable value fails loudly at startup rather than
being silently mangled by the build.

The merge doubles as a **schema-compatibility gate**: every `minecraft:spawn_rules`
body is validated against the official Mojang spawn schemas pinned by
`--schema-version` (default `1.21.50`), and every condition component and structural
key must be declared by the pinned schema inventory. A failure means the vanilla data
drifted beyond what the plugin was built against — update the loader / component
registry (and, if intended, the pinned schema version) before recompiling. The merger
writes output only if every source file merged and validated cleanly. The schemas are
permissive (see the validation section below), so this is a structural gate; semantic
correctness is enforced by the strict runtime loader.

### How schema validation is run

Validation is a plain PHPUnit test, so it runs offline and locally:

```sh
composer test                      # or: vendor/bin/phpunit --no-progress
```

`tests/phpunit/.../SpawnRulesDataInventoryTest.php`:
- checks `spawn_rules.json` decodes as valid JSON;
- checks every condition component key is a declared `SpawnComponent` enum case
  (catches typo'd / unknown components);
- validates every entry's `minecraft:spawn_rules` body against the **official Mojang
  spawn schemas** via `SpawnRuleSchemaValidator`.

Note that the committed Mojang schemas are intentionally **permissive** (draft-07 allows
extra properties; `Description` only requires `identifier`), so schema validation is a
coarse shape gate — it cannot reject arbitrary garbage in a recognized component. The
authoritative guard is the **strict runtime loader**: `SpawnRulesParseableTest` feeds the
committed resource through `SpawnRulesFactory`, which throws on any value it cannot
compile. Keep both: the schema check catches structural landmines before they reach the
parser, and the parse test is the source of truth for semantics.

The schemas used by the test are **committed** under
`resources/spawning/schemas/metadata/json_schemas` (the `server/spawn/1.21.50` tree
plus the `Filter Group`, `Block Descriptor` and legacy `Reference` files they `$ref`),
so no network or clone is needed. See `resources/spawning/schemas/README.md` for the
source commit and license.

### Updating to a newer Mojang version

When Mojang publishes new spawn data / schemas, bring the pinned commit forward in this
order (each step errors loudly if it is skipped, so CI and `composer test` flag it):

```sh
# 1. move the pinned checkout to the new commit
git -C .cache/bedrock-samples fetch --depth 1 origin main
git -C .cache/bedrock-samples checkout <new-sha>

# 2. regenerate the merged data + NOTICE (validated against the current, possibly newer, schema)
composer compile-spawn-rules

# 3. regenerate the generated artifacts from the schemas
php tools/spawn-rules/generate-schema.php --samples-dir=.cache/bedrock-samples

# 4. refresh the committed schema copy the offline test validates against
#    (copy metadata/json_schemas/** from the checkout — see resources/spawning/schemas/README.md)

# 5. review & commit the diff, then bump the pinned ref everywhere it appears:
#    - resources/spawning/NOTICE.md   (Source commit / Game version / Schema validation)
#    - resources/spawning/schemas/README.md (Source commit / version)
#    - the pinned `ref` in any CI workflow and the tools' `DEFAULT_SCHEMA_VERSION`
```

After a bump, the checks that used to drift now pass: `composer test` validates the new
data against the new committed schemas, and `generate-schema.php --check` confirms the
regenerated artifacts match. If a component was added/renamed/removed, `SpawnComponent`
and `SpawnConditionRegistry` must be updated first so the loader still accepts every
declared component (see [#Conditions](#conditions)).

Two other checks complete the picture:

- `SpawnComponent` / `SpawnSchema` (`src/.../spawning/parse/schema/`) are **generated**
  from the same schemas by `php tools/spawn-rules/generate-schema.php`. `SpawnComponent`
  is one enum case per condition component; `SpawnSchema` also carries schema facts
  including the **envelope keys** the loader navigates with (`KEY_CONDITIONS`,
  `KEY_DESCRIPTION`, `KEY_POPULATION_CONTROL`), so those structural strings are
  schema-derived too rather than hand-written literals. Run
  `generate-schema.php --check --samples-dir=.cache/bedrock-samples` to confirm the
  committed artifacts still match regeneration (a stale artifact means Mojang renamed,
  added or removed a spawn component or an envelope key).
- `SpawnRulesParseableTest` strict-loads the committed resource through the real
  loader, so a regression in data ⇔ parser surfaces too. `SpawnRuleIndexTest` asserts
  the planner index is an over-approximation of brute-force evaluation.

CI runs `phpunit` (these tests) and `phpstan` on every push/PR; keeping the committed
schemas, the merge output, and the generated enum artifacts in sync is an explicit,
conscious step. The artifact-drift check is now **wired into CI** too: the
`spawn-schemas` workflow runs `generate-schema.php --check` against the **committed**
schema subtree (`resources/spawning/schemas/`) offline — no `bedrock-samples` clone
needed — so a Mojang component/envelope change that desyncs the generated artifacts
fails the build. The full merge/schema-validation gate still requires the pinned clone
(`composer compile-spawn-rules`). `compile.php`, `generate-schema.php`, and the schemas
subtree all carry their source-commit provenance in headers / NOTICE / README.

## Pipeline

One synchronous main-thread pass per tick, three stages
(`NaturalSpawner::tick()`; PM entities/worlds are not thread-safe and every expensive
stage needs `World` access):

1. **Collect** — `SpawnCollector` picks budgeted random candidate positions per world
   (spawn shell around players, surface + cave attempts per column) and snapshots every
   piece of world state the conditions need (`SpawnCandidateSnapshot`).
2. **Evaluate** — `SpawnEvaluator` shortlists rule sets through the planner index
   (`SpawnRuleIndex::candidatesFor()`), weighted-picks one rule, runs its compiled
   conditions against the snapshots, and gates by `MobCategory` population caps.
3. **Apply** — `SpawnApplier` re-validates live state, spawns herds, and dispatches
   `permute_type` through the registry.

## Conditions

Rules compile once at load into immutable `SpawnCondition` objects
(`spawning/condition/`): each is a small final class with a `test(SpawnConditionContext): bool`
method — evaluation is polymorphic dispatch, no interpretation. Combinators
(`AllOf`, `AnyOf`, `Not`) compose trees; a rule set is an ordered list of
`SpawnConditionGroup`s (conditions + weight/herd/permute/event payload) and the **first
matching group wins** (vanilla semantics).

Parsing (`spawning/parse/`) maps each vanilla component name to a parser closure in the
open `SpawnConditionRegistry`. Vanilla JSON is read through `SpawnData`, a typed reader
that reports every problem with its JSON path. The loader is **strict**: any value it
cannot compile aborts the whole load with `SpawnModelParseException` (malformed JSON,
unknown component, unresolvable block name, unknown `population_control` or schema
version). Exactly two degradations exist, both first-class documented policy in
`SpawnRulesFactory`: `population_control` values Bedrock spawns through events
(`pillager`, `pillager_patrol` — patrols/raids, never natural spawns) skip their rule
set, and block names PocketMine-MP cannot have (`powder_snow`) are dropped from block
filters. Four components are not implementable in PM (`mob_event_filter`,
`delay_filter`, `player_in_village_filter`, `spawns_above_block_filter`) and degrade to
"never match" through explicit, replaceable registrations.

Block names and biome tags resolve through injectable resolver chains
(`spawning/parse/resolver/`); unresolvable names (e.g. `powder_snow`, absent in PM) warn
once and the condition never matches.

## Planner

`SpawnRuleIndex` (`spawning/plan/`) folds each rule set's conditions into a conservative
`SpawnConstraint` (habitat bands, difficulty range, biome tags, required liquid —
propagated through provable AND paths only) and answers "which rule sets could possibly
match this position" with cheap set comparisons before any condition runs.

The constraint is an over-approximation: everything it rejects provably fails the real
conditions; everything it accepts still goes through them. A property test
(`SpawnRuleIndexTest`) asserts the index is a superset of brute-force evaluation.

There is deliberately **no environment → category switch**. Which rule sets compete at a
position is emergent from the data: water mobs declare `spawns_underwater`, animals
declare their block filter + brightness bounds, cave dwellers declare
`spawns_underground`. `MobCategory` is a population-cap group, never an attempt gate.
The one invariant the conditions cannot express — vanilla land rules carry no water
veto — is a single generic rule: at a liquid position only rule sets that explicitly
declare that liquid are attempted.

## Registration API

Plugins register rules through
`MobPlugin::getInstance()->getSpawnRuleRegistry()`:

```php
$registry->register(
    new SpawnRules("minecraft:myboss", MobCategory::MONSTER, [
        new SpawnConditionGroup([
            new BrightnessFilter(0, 7, false),
            new SpawnsOnBlock([BlockTypeIds::STONE => true], false),
            new DifficultyFilter(1, 3),
        ], weight: 100),
    ]),
    fn(World $world, Vector3 $pos, SpawnConditionMatch $match) => new MyBoss(...),
);
```

The factory constructs the entity (with a random yaw) but never spawns it — the applier
positions herd members and calls `spawnToAll()`. Condition classes are immutable plain
data shared across ticks and worlds, so they must not capture mutable state; inject
services through resolver-style dependencies at construction time.

Vanilla rules are bootstrapped when the `SpawnRuleRegistry` singleton is first built —
`SpawnRuleRegistry::getInstance()` (a `pocketmine\utils\SingletonTrait` singleton) calls
its private `__construct()`, which walks `MobPlugin::ALL_ENTITIES` and registers every
implemented mob with an entry in the compiled resource.

An already-registered rule set can be replaced by calling `register()` again with
`override: true` — the old rule set (and its factory) is swapped out and the planner
index rebuilds. Example — slimes spawn only below Y 40:

```php
$registry->register(
    new SpawnRules("minecraft:slime", MobCategory::MONSTER, [
        new SpawnConditionGroup([
            new HabitatBandCondition([SpawnBand::CAVE]),
            new HeightFilter(null, 40),
        ]),
    ]),
    fn(World $world, Vector3 $pos, SpawnConditionMatch $match) => new Slime(...),
    override: true
);
```

## Settings

```yaml
mob-natural-spawning:
  enabled: true
  attempts-per-tick: 3   # chunk evaluations per tick per world
```

## Approximations

Documented deviations from vanilla, all deliberate:

- Cave herd depths are sampled (2 per column) instead of scanning every spawnable block;
  the 9×9 chunk population region is approximated by a 72-block radius circle.
- A mob's band (surface/cave) is taken from its current position, not its spawn location.
- `permute_type` spawn-event suffixes are stripped: permuted types spawn in base form.
- No PM weather API → the weather light penalty is always 0 (context field is the hook).
- `disallow_spawns_in_bubble` is a pass-through (no bubble-column block in PM).
- Herd spawn events are parsed but not applied (no consumer yet).
- A hatch can overshoot its category's population cap: the cap is checked once for the
  whole herd, then the full herd size spawns (vanilla pack-spawn behavior).
- The global mob cap (200) is not enforced.
- Known by-design degradations in the resource: `powder_snow` is unresolvable (dropped
  from the goat's block filter — PM has no powder-snow block); the `pillager` and
  `pillager_patrol` rule sets are skipped (event-driven populations, no `MobCategory`).
- Slime rule extension: vanilla conditions must hold AND (Y < 40 in a slime chunk OR
  biome tag `spawns_slimes_on_surface`). The slime-chunk check is Bedrock's real
  algorithm (coordinate-seeded MT19937; see `condition/vanilla/slime/`), reverse
  engineered by @protolambda and @jocopa3 — no world seed is needed on Bedrock.
