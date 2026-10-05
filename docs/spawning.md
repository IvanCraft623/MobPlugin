# Natural spawning

MobPlugin spawns mobs from the vanilla Bedrock spawn rules, on the main thread.

- Running a server: see [Settings](#settings).
- Writing a plugin that adds or changes spawns: see [spawning-api.md](spawning-api.md).
- Working on the spawner itself: the rest of this page.

## Settings

```yaml
mob-natural-spawning:
  enabled: true
  max-attempts-per-tick: 8
  max-mobs: 200
```

A world settings file can set `enabled: false` for that world. `max-attempts-per-tick` is
global: it caps the cost of a crowded tick, not the spawn rate. `enabled` also covers
endermites from ender pearls.

`max-mobs` pauses spawning in a world that holds that many mobs with spawn rules. It can
be set per world; `0` is no limit.

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
├── NaturalSpawnerTask, SpawnRuleRegistry, SpawnRules, SpawnRuleGroup
├── MobCategory, MobCategoryRegistry, BiomeTagMap, SpawnBand, SpawnLiquid
├── condition/   SpawnCondition, its contexts and the built-in conditions
├── population/  MobPopulation: how many mobs are around a chunk, per band
├── spawner/     the runtime (internal)
└── parse/       the strict loader; parse/schema/ is generated
```

## Runtime

`NaturalSpawnerTask` runs once per tick.

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

| Kind | Built-in conditions |
|---|---|
| Cacheable | `BiomeTagCondition`, `DifficultyCondition`, `BandCondition` |
| Per attempt | `BrightnessCondition`, `BlockLightCondition`, `WorldAgeCondition`, `HeightCondition`, `SpawnsOnBlock`, `SlimeChunkCondition`, `LightChanceCondition`, `MoonPhaseChanceCondition` |
| Combinators | `AllOf`, `AnyOf`, `Not` |

The cache can't check that a `CacheableCondition` is pure and that conditions are
immutable and free of side effects: that contract is in
[spawning-api.md](spawning-api.md#cacheable-conditions).

## Loading

`MobPlugin::onEnable()` calls `SpawnRuleRegistry::registerVanilla($path)`, which parses the
resource and binds every implemented mob, plus PocketMine's squid.

The loader is strict: anything it can't compile throws `SpawnRulesParseException` with the
JSON path, and the plugin is disabled. Unknown keys are rejected, and so are values that
would lose information (`8.5` for an integer; `8.0` is fine).

Two vanilla rules live in the engine instead of the rules file, so `registerVanilla()`
adds them:

- Every `Monster` gets `new BlockLightCondition(0, 0)`.
- Slimes spawn at Y 38 or below in slime chunks (Bedrock's algorithm, reverse engineered
  by @protolambda and @jocopa3), and from Y 50 to 68 in `spawns_slimes_on_surface` biomes
  with a light roll and a moon-phase roll.

What the loader accepts without spawning:

- `pillager` rule sets are skipped: vanilla spawns them through patrols and raids.
- Groups using `mob_event_filter`, `delay_filter`, `player_in_village_filter` or
  `spawns_above_block_filter` never spawn.
- A group with neither `spawns_on_surface` nor `spawns_underground` is dropped (guardian).
- `powder_snow` is dropped from block filters: PocketMine has no such block.
- `disallow_spawns_in_bubble`, `is_persistent`, `is_experimental` and `spawn_event` are
  ignored.
- A biome tag no biome carries never matches; the plugin logs a warning for each.

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
- Structure spawn areas are not implemented.
- The mob cap (`max-mobs`) is per world and counted once a second, so a world can go
  slightly past it.
