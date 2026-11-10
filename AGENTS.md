# AGENTS.md

Guidance for AI coding agents (and humans) working on **MobPlugin**.

## What this project is

A [PocketMine-MP](https://github.com/pmmp/PocketMine-MP) API 5 plugin (PHP) that
implements vanilla mob behavior for Minecraft: **Bedrock Edition**, on its own AI framework
(goals, sensing, navigation, movement controls).

## Layout

Plugin code is under `src/IvanCraft623/MobPlugin/` (PSR-0):

- `MobPlugin.php` — entry point: registers entities, attributes, metrics and the
  `EventListener`.
- `Settings.php` — YAML settings, global with per-world overrides.
- `entity/` — the mob hierarchy (`Mob` → `Living`, `Animal`, `Monster`, `Golem`…), with
  concrete mobs under `entity/{type}/`.
  - `entity/ai/` — the AI framework: `goal/` (the goal FSM, with mob-specific goals in
    subfolders), `Brain.php`, `memory/`, `behavior/`, `sensing/`, `control/`,
    `navigation/`, `targeting/`, `utils/`. See `docs/navigation.md`.
- `data/bedrock/` — Bedrock id maps. `EntityIds` and `VanillaEntitySizes` are generated;
  entity classes keep PocketMine-MP's `EntityIds` for `getNetworkTypeId()`.
- `spawning/` — natural spawning from the vanilla spawn rules. `parse/schema/` is
  generated. See `docs/spawning.md`, and `docs/spawning-api.md` for the public API.
- `despawning/` — natural despawning, for any entity with a registered `DespawnRule`. See
  `docs/despawning.md`.
- `loot/` — mob drops: the vanilla loot tables, rolled by the `kostamax27/loottable`
  library. See the Drops section of `docs/new-mobs.md`.
- `utils/`, `sound/`, `particle/`, `pattern/`, `item/`, `inventory/`, `CustomTimings.php`
  — shared infrastructure.

Outside `src/`:

- `resources/` — `global-settings.yml` and the generated `bedrock/` data
  (`spawning/spawn_rules.json`, `loot_tables.json`, `NOTICE.md`).
- `docs/` — how to implement a mob ([new-mobs.md](docs/new-mobs.md)), plus navigation, spawning
  and despawning.
- `tools/` — dev tools that generate the spawn data, `parse/schema/` and the entity data, and
  merge the loot tables, from the pinned `mojang/bedrock-samples` dev dependency, and
  generate registry classes with PocketMine-MP's registry generator (`tools/registry/`).
- `tests/phpunit/` — the PHPUnit suite.
- `.github/workflows/` — `ci.yml` (PHPStan, PHPUnit, generated data drift), `build.yml`
  (nightly phar) and `release.yml` (tagged releases).

## Tooling

- `composer install` — dependencies. Don't edit `vendor/` or `composer.lock` by hand.
- `php-cs-fixer fix` — code style, including the license header. Run it after editing.
- `vendor/bin/phpstan.phar analyze --no-progress` — PHPStan level 9.
- `composer test` — PHPUnit.
- `composer build` — builds `MobPlugin.phar`.
- After changing the `mojang/bedrock-samples` pin: `composer generate-spawn-schema`,
  `composer compile-spawn-rules`, `composer generate-entity-data`,
  `composer compile-loot-tables` and `composer generate-bedrock-notice`.

Pathfinding is the external virion `ivancraft623/pathfinder`; keep usage aligned with its
API.

## Conventions

- Mob behavior goes through the goal FSM, not inline in entity tick methods. Port a mob by
  composing goals.
- Match vanilla Bedrock behavior, but don't copy another edition's design: shape the code around
  PocketMine-MP's APIs and PHP's performance limits. Reuse PocketMine's APIs and maps before
  writing new ones.
- The plugin is pre-release: no backward-compatibility shims.
- One class per file, `declare(strict_types=1)`, typed properties and return types. Also write
  `@var`, `@param` and `@return` where they add information, so every IDE sees it, and
  `@phpstan-*` for what only PHPStan can express. A few words each; don't restate the code.
- No `empty()` and no `in_array()`. Check explicitly (`count($x) !== 0`, `=== null`,
  `!== ""`, never `$x !== []`); for membership use a keyed constant map and `isset()`.
- Whatever holds a quantity states its unit, in the name, a docblock or a trailing comment
  (`//ticks`, `//blocks per tick`): constants, properties, parameters and return values. NBT key
  constants state their tag type (`//TAG_Int`).
- An enum whose cases hold data declares it with `@phpstan-type TData array{…}`, keeps it in a
  private `getData()` (a `match`, cached per case by `spl_object_id($this)`) and exposes
  getters (`TropicalFishPreset`). Wire ids go in a hand-written `*IdMap` in `data/bedrock/`
  built on `IntSaveIdMapTrait`.
- Registries are `SingletonTrait` classes. Broken invariants throw `AssumptionFailedError`;
  don't catch `\Throwable` broadly.
- Leave a `TODO` instead of speculative code, and delete what a change orphans.

## Before finishing

Run php-cs-fixer, PHPStan and `composer test`, and make sure the phar builds.
