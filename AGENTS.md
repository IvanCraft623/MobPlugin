# AGENTS.md

Guidance for AI coding agents (and humans) working on **MobPlugin**.

## What this project is

A [PocketMine-MP](https://github.com/pmmp/PocketMine-MP) API 5 plugin (PHP) that
implements vanilla mob AI for Minecraft: **Bedrock Edition**. It mirrors the architecture
of Java Edition's mob AI (goals, senses, memories, navigation).

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
  generated. See `docs/spawning.md`.
- `utils/`, `sound/`, `particle/`, `pattern/`, `item/`, `inventory/`, `CustomTimings.php`
  — shared infrastructure.

Outside `src/`:

- `resources/` — `global-settings.yml` and the generated `spawning/spawn_rules.json`.
- `tools/` — dev tools that generate the spawn data, `parse/schema/` and the entity data
  from the pinned `mojang/bedrock-samples` dev dependency.
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
  `composer compile-spawn-rules` and `composer generate-entity-data`.

Pathfinding is the external virion `ivancraft623/pathfinder`; keep usage aligned with its
API.

## Conventions

- One class per file, `declare(strict_types=1)`, typed properties and return types.
  Use `@phpstan-param` / `@phpstan-return` where native types can't express it.
- Mob behavior goes through the goal FSM, not inline in entity tick methods. Port a mob by
  composing goals.
- Port vanilla AI faithfully, adapting to PocketMine-MP's APIs.

## Before finishing

Run php-cs-fixer, PHPStan and `composer test`, and make sure the phar builds.

The test suite covers natural spawning only: the bundled data loads, the candidate cache
agrees with plain evaluation, caps and density limits hold, the census counts correctly,
the slime-chunk algorithm, and which blocks a mob can stand in. Everything else is checked
by PHPStan and in-server testing.
