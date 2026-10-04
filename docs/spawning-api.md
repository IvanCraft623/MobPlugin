# Natural spawning API

How a plugin gives its own mobs natural spawns, or changes the spawns of existing ones.
How the spawner works inside is in [spawning.md](spawning.md).

## Before you start

- Add `depend: MobPlugin` to your `plugin.yml`, so the vanilla rules and categories are
  registered before your `onEnable()` runs.
- Rules are keyed by the entity's network type id (`YourMob::getNetworkTypeId()`). The
  spawner counts mobs by that id, so the two must match.
- Register in `onEnable()`. Rules registered or removed later apply from the next tick.
- Register the entity itself with PocketMine's `EntityFactory` as usual: spawn rules only
  decide where and when it appears.
- Nothing spawns, yours included, when the server owner turns `mob-natural-spawning` off.

Everything below lives under `IvanCraft623\MobPlugin\spawning`: the registries, rules and
enums at its root, conditions in `condition\`, population in `population\`, the JSON
loader in `parse\` and `VanillaMobCategories` in `parse\schema\`.

## Registering a mob

```php
SpawnRuleRegistry::getInstance()->register(new SpawnRules(
	MyBoss::getNetworkTypeId(),
	VanillaMobCategories::MONSTER,
	[
		new SpawnRuleGroup([
			new BandCondition(SpawnBand::CAVE),
			new BiomeTagCondition("mountain"),
			new BlockLightCondition(0, 0),
			new SpawnsOnBlock([VanillaBlocks::STONE(), VanillaBlocks::DEEPSLATE()]),
		], weight: 100),
	],
	static fn(World $world, Vector3 $pos, SpawnRuleGroup $group) : Entity => new MyBoss(Location::fromObject($pos, $world)),
	new EntitySizeInfo(2.0, 1.0)
));
```

| Argument | Meaning |
|---|---|
| identifier | The entity's network type id. |
| category | Which population cap the mob counts against. See [Categories](#categories). |
| groups | One or more `SpawnRuleGroup`s. Every group whose conditions match competes in a weighted pick. |
| factory | Builds the entity at the position **without spawning it**; the spawner spawns it. An exception thrown here is not caught. |
| size | The mob's collision box as `EntitySizeInfo(height, width)`. The spawner checks that it fits before calling the factory, so no entity is built just to test for room. |

`register()` throws an `\InvalidArgumentException` when the category is unknown, or when
the identifier already has rules and `override: true` was not passed.

## Group options

A `SpawnRuleGroup` takes its conditions first; everything else is a named argument.

| Option | Default | Meaning |
|---|---|---|
| `weight` | `1` | Share of the weighted pick among matching groups. |
| `herdMin`, `herdMax` | `1`, `1` | How many mobs spawn together. |
| `rarity` | `0` | Once picked, the group spawns one time in this many. `0` for always. |
| `permutations` | `[]` | `identifier => weight`: each herd member spawns as one of these mobs, picked by weight, using that mob's registered factory. List the group's own identifier to keep it in the pick. |
| `requiredLiquid` | `SpawnLiquid::NONE` | The liquid the feet must be in. `NONE` means out of any liquid; use `WATER` or `LAVA` for aquatic mobs. |
| `minPlayerDistance`, `maxPlayerDistance` | `24.0`, `128.0` | Distance to the nearest player, in blocks. `0.0` and `INF` for no bound. |
| `surfaceDensityLimit`, `caveDensityLimit` | `null` | Most mobs of this type allowed around the position. `null` for no limit. |

## Conditions

A group spawns only where all its conditions pass.

| Condition | Passes when |
|---|---|
| `BandCondition(SpawnBand)` | The position is on the surface (`SURFACE`: on a column's ground) or underground (`CAVE`: anywhere below it). |
| `BiomeTagCondition(string $tag)` | The biome has the tag. See [Biome tags](#biome-tags). |
| `DifficultyCondition(int $min, int $max)` | The world's difficulty is in the range (`World::DIFFICULTY_*`). |
| `BrightnessCondition(int $min, int $max)` | The light level, sky included, is in the range (0 to 15). |
| `BlockLightCondition(int $min, int $max)` | The light from blocks alone is in the range. |
| `HeightCondition(?int $min, ?int $max)` | The block the mob stands on is in the Y range (and its feet too, in a liquid). `null` for no bound. |
| `WorldAgeCondition(?int $min, ?int $max)` | The world's age in ticks is in the range. |
| `SpawnsOnBlock(Block[] $blocks)` | The block under the feet is one of the blocks. Blocks match by type and variant, not by placement state. |
| `SlimeChunkCondition()` | The chunk is a Bedrock slime chunk. |
| `LightChanceCondition(int $bound)` | A roll that is likelier the lighter the position is: never at light 0, always at `$bound` or above. |
| `MoonPhaseChanceCondition()` | A roll that is likelier the fuller the moon is. |
| `AllOf(array)`, `AnyOf(array)`, `Not(SpawnCondition)` | Combine other conditions. `Not` is how any of the above is negated: `new Not(new SpawnsOnBlock([...]))` forbids those blocks. |

Two things are easy to miss:

- **Surface or underground is a condition.** A group without a `BandCondition` spawns in
  both.
- **Nothing is added for you.** Vanilla monsters only spawn in the dark because
  MobPlugin adds `new BlockLightCondition(0, 0)` to its own. A custom monster needs it in
  its groups, as in the example above.

## Writing a condition

Implement `SpawnCondition`:

```php
final class NightCondition implements SpawnCondition{
	public function test(SpawnConditionContext $ctx) : bool{
		$time = $ctx->getWorld()->getTimeOfDay();

		return $time >= World::TIME_NIGHT && $time < World::TIME_SUNRISE;
	}
}
```

`SpawnConditionContext` gives the position (`getX()`, `getY()`, `getZ()`), the light
(`getLight()`, `getBlockLight()`), the block under the feet (`getBelowItemStateId()`),
the distance to the nearest player, the world age (`getTime()`), the population around
the position (`getPopulation()`), `hasRoomFor($size)`, the random source (`getRandom()`)
and the world itself (`getWorld()`).

Every condition must be immutable (make every property `readonly`) and free of side
effects: `test()` changes nothing, doesn't depend on which conditions ran before it, and
takes any randomness from `$ctx->getRandom()`.

### Cacheable conditions

A plain `SpawnCondition` is always correct. `CacheableCondition` is an optimisation for
one specific case, and using it anywhere else gives wrong spawns.

The spawner sorts positions into kinds by four values: the **biome**, the **band**
(surface or cave), the world's **difficulty** and the **liquid at the feet**. A
`CacheableCondition` is asked once per kind, and its answer is reused for every later
position of that kind, without calling it again.

So the question to ask is: **could two positions with the same biome, band, difficulty
and liquid ever get different answers?**

- No: implement `CacheableCondition`. It only receives those four values, through
  `CacheableConditionContext`.
- Yes: implement `SpawnCondition`.

| The condition reads | Interface |
|---|---|
| The biome, the band, the difficulty or the liquid at the feet | `CacheableCondition` |
| The position, the light, the block below, the time, the population, the world | `SpawnCondition` |
| A random roll | `SpawnCondition` |
| Something of your own that changes rarely, like a config option | `CacheableCondition`, see below |

```php
final class DesertCondition implements CacheableCondition{
	public function test(CacheableConditionContext $ctx) : bool{
		return $ctx->getBiomeId() === BiomeIds::DESERT;
	}
}
```

A cacheable condition may also depend on its own constructor arguments, since those never
change. If it depends on anything else that can change while the server runs (a config
option, a list your plugin edits), the reused answers go stale. Tell the spawner to ask
again whenever that happens:

```php
SpawnRuleRegistry::getInstance()->invalidateCache();
```

A custom combinator should implement `ReducibleCondition` or extend `CompositeCondition`,
so the cacheable conditions inside it are still decided once; otherwise it runs whole on
every attempt.

## Biome tags

`BiomeTagCondition` tests the shared `BiomeTagMap`, which starts from the bundled Bedrock
data (`forest`, `mountain`, `ocean`, `monster`…). Tag a custom biome, or add your own tag
to a vanilla one:

```php
BiomeTagMap::getInstance()->addTag($biomeId, "myplugin:haunted");
```

`removeTag()` takes one away. Both apply from the next tick.

## Categories

A category caps how many of its mobs may be around a position, over the 9×9 chunks around
it, separately on the surface and underground. Once the cap is reached nothing of that
category spawns there. A cap of 0 means never in that band.

| `VanillaMobCategories` | Surface cap | Cave cap |
|---|---|---|
| `MONSTER` | 8 | 16 |
| `ANIMAL` | 4 | 4 |
| `AMBIENT` | 0 | 2 |
| `WATER_ANIMAL` | 36 | 36 |
| `CAT` | 4 | 0 |

Register your own, or replace a vanilla one to change its caps:

```php
MobCategoryRegistry::getInstance()->register(new MobCategory(
	"myplugin:spirit",
	surfaceCap: 2,
	caveCap: 6,
	despawnDistance: 64
));
```

`despawnDistance` is how far from every player its mobs despawn. Rules hold the
category's id, so replacing a category applies to the rules already registered.

## Changing existing rules

```php
$registry = SpawnRuleRegistry::getInstance();

// Stop creepers from spawning naturally.
$registry->unregister(EntityIds::CREEPER);

// Add a condition to every zombie group.
$zombie = $registry->get(EntityIds::ZOMBIE);
if($zombie !== null){
	$groups = array_map(
		static fn(SpawnRuleGroup $group) : SpawnRuleGroup => $group->withConditions([new NightCondition()]),
		$zombie->getGroups()
	);
	$registry->register(
		new SpawnRules($zombie->getIdentifier(), $zombie->getCategoryId(), $groups, $zombie->getFactory(), $zombie->getSize()),
		override: true
	);
}
```

`EntityIds` here is `IvanCraft623\MobPlugin\data\bedrock\EntityIds`.

## Population queries

```php
$counts = MobPopulation::getInstance()->around($world, $chunkX, $chunkZ);
$counts->getCategoryCount(VanillaMobCategories::MONSTER, SpawnBand::CAVE);
$counts->getIdentifierCount(EntityIds::ZOMBIE, SpawnBand::SURFACE);
```

`around()` scans 81 chunks on every call: keep its result. Inside a condition use
`$ctx->getPopulation()`, which the spawner has already counted.

Only entities whose identifier has registered rules are counted. A mob you spawn your own
way is given a band from where it stands when first counted; to choose it yourself:

```php
MobPopulation::getInstance()->getBands()->set($entity, SpawnBand::SURFACE);
```

## Loading rules from JSON

`SpawnRulesParser` reads Bedrock spawn rules: one JSON object keyed by entity identifier,
each value a `minecraft:spawn_rules` file as Mojang ships it
(`resources/spawning/spawn_rules.json` is an example).

```php
$parser = SpawnRulesParser::createVanilla();
foreach($parser->parseFile($path) as $identifier => [$categoryId, $groups]){
	// $factory and $size: yours, for the mob named by $identifier
	SpawnRuleRegistry::getInstance()->register(new SpawnRules($identifier, $categoryId, $groups, $factory, $size));
}
```

The parser gives the category and the groups; the factory and the size are yours to
supply. It is strict: anything it can't compile throws `SpawnRulesParseException` with
the JSON path. A group needs `minecraft:spawns_on_surface`, `minecraft:spawns_underground`
or both; one with neither is dropped.

Your own JSON components are registered on the parser before parsing:

```php
$parser->registerComponent(
	"myplugin:my_filter",
	static function(ComponentParseContext $ctx, SpawnRuleGroupBuilder $builder) : void{
		$builder->addCondition(new MyCondition($ctx->map(MyFilterData::class)->field));
	}
);
```

`ComponentParseContext` reads the component's payload: `map()` and `mapList()` into a
class of typed public properties, `resolveBlocks()` for block names, and `getValue()`,
`objectOrList()` and `getPath()` for anything else. `SpawnRuleGroupBuilder` sets what the
component means for the group: `addCondition()`, `setWeight()`, `setRarity()`,
`setHerd()`, `setPermutations()`, `setLiquid()`, `setPlayerDistance()`,
`setDensityLimit()`, `allowHabitatBand()` and `markNeverSpawns()`.
