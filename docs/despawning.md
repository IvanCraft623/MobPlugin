# Natural despawning

MobPlugin despawns mobs the way vanilla Bedrock does. Rules are registered per entity
type and apply to any entity of it, including PocketMine-MP's and other plugins'. A type
without a rule never despawns.

## Rules

Each entity is checked in this order:

1. **Difficulty:** monsters are removed in peaceful, except the smallest slimes.
2. **Maximum lifetime:** the endermite is removed after 2400 ticks, persistent or not,
   unless it has a name tag.
3. **Persistence:** a persistent entity is kept.
4. **Away from players:** the entity is removed when
   - no player is within 128 blocks (44 at a chunk tick radius of 4 or less),
   - above a tick radius of 4, one of the 3×3 chunks around it isn't ticking, or
   - no player is within 32 blocks, it has been inactive for 600 ticks, and a 1 in 800
     roll per tick succeeds.

A player within 32 blocks resets the inactivity time, and so does damage. For monsters it
runs three times as fast in light 13 or more. Spectators and dead players don't count.

The wither skips rule 4. `mob-natural-despawning: false`, globally or in a world's
settings, turns it off for every type; rules 1 and 2 still apply.

## Persistence

An entity is persistent when it

- was bred, fed or spawned from a spawn egg,
- picked up an item,
- split from a persistent slime,
- has a name tag, or
- meets its rule's own condition (an enderman carrying a block).

The first three set a flag in its `EntityDespawnProfile`, saved with the entity; the last
two are read at each check. Persistent mobs still count toward the spawn caps.

## API

```php
$registry = DespawnRuleRegistry::getInstance();

// The rule most vanilla mobs have.
$registry->register(new DespawnRule(MySpirit::class));

// The rule most vanilla monsters have.
$registry->register(DespawnRule::monster(
	MyThief::class,
	persistentWhile: static fn(MyThief $thief) : bool => $thief->hasLoot()
));

$registry->unregister(Bat::class);
```

- **Arguments:** see `DespawnRule::__construct()`. Rules are immutable; to change one,
  register a new rule with `override: true`.
- **Profile:** `$registry->getProfile($entity)` gives `setPersistent()` and `markActive()`.
  It is null for a type without a rule.
- **Spawning mobs:** call `MobSpawnEvent` with the cause that fits. `BREEDING` and
  `SPAWN_EGG` make the mob persistent; `SPLIT` copies the parent's flag.
- **Saving:** `Mob` saves the flag and the lifetime. An entity that doesn't extend it uses
  `DespawnPersistenceTrait`, aliasing its `initEntity()` and `saveNBT()` if it has its own.
- **`EntityNaturalDespawnEvent`:** called before a removal, with the cause. Cancelling it
  keeps the mob until its next check, a second later.

## Differences from vanilla

- Each entity is checked every 20 ticks instead of every tick, with the chance scaled to
  match.
- Dead players don't keep mobs around.
- Any name tag keeps an entity; vanilla only counts a player naming it.
- The inactivity time also runs for a mob without AI.
- A mob outside the ticking chunks keeps ticking in PocketMine-MP.
