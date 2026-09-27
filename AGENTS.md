# AGENTS.md

Guidance for AI coding agents (and humans) working in the **MobPlugin** repository.
Read this before making changes.

## What this project is

MobPlugin is a [PocketMine-MP](https://github.com/pmmp/PocketMine-MP) API 5 plugin (PHP) that
implements vanilla-like mob AI for Minecraft: **Bedrock Edition**, aiming to replicate
vanilla behavior as closely as possible. Mirrors the architecture of Java Edition's mob AI
(goals, senses, memories, navigation).

## Repository layout

The plugin code lives under `src/IvanCraft623/MobPlugin/` (PSR-0 autoload). Key areas:

- `MobPlugin.php` — plugin entry point (`SingletonTrait`). Registers entities, custom
  attributes, metrics (bStats), boss-bar API, and the `EventListener`.
- `Settings.php` — YAML-backed settings (global + per-world overrides).
- `EventListener.php` — event handlers (e.g. overrides vanilla spawn-egg behavior).
- `CustomTimings.php`, `CustomAttributes.php`, `utils/`, `sound/`, `particle/`,
  `pattern/`, `item/`, `inventory/` — shared infrastructure.
- `entity/` — the mob hierarchy:
  - `Mob.php` → `Living`, plus `Animal`, `Monster`, `Golem`, `Ambient`, `PathfinderMob`,
    `Boss`, etc. Concrete mobs (Chicken, Cow, Zombie, Wither…) live under
    `entity/{type}/`.
  - `entity/ai/` — the AI framework:
    - `goal/` — **Goal-based FSM**: each mob has prioritized goals; the highest-priority
      applicable goal runs each tick and switches seamlessly. Contains `Goal`, `GoalSelector`,
      `WrappedGoal`, and dozens of concrete goals (melee, ranged, panic, breed, wander,
      target goals…). Mob-specific goals are grouped in subfolders (`creeper/`, `enderman/`,
      `slime/`, `wither/`).
    - `brain/` (as `entity/ai/Brain.php`), `memory/`, `behavior/`, `sensing/`, `control/`,
      `navigation/`, `targeting/`, `schedule/`.
  - `data/bedrock/` — hand-managed Bedrock type-id maps / enums.
- `resources/` — bundled config (e.g. `global-settings.yml`).
- `.github/workflows/` — CI (build/phar, PHPStan, release).

## Build & tooling

- **Dependencies**: `composer install`. Third-party code is in `vendor/` (git-ignored);
  don't edit it.
- **Pathfinding**: implemented as an external virion porting Java's pathfinding,
  dependency `ivancraft623/pathfinder` (`dev-main`), fetched from a VCS repository.
  Keep usage aligned with that library's API.
- **Static analysis**: PHPStan at **level 9**
  (`vendor/bin/phpstan.phar analyze --no-progress`, config in `phpstan.neon.dist`).
  CI runs it on every push/PR. Code must pass level 9.
- **Code style**: enforced by `php-cs-fixer` (`.php-cs-fixer.php`). Style is non-negotiable;
  run it before committin

  After adding/editing files, run `php-cs-fixer fix` — it will insert the required header
  and apply formatting. Don't leave the header out.

## Conventions & architecture rules

- **PSR-0 naming**: namespace `IvanCraft623\MobPlugin\...` maps to `src/...`;
  one class per file, filename matches class name.
- **Typed & strict**: PHP 8 typed properties, explicit return types everywhere,
  `declare(strict_types=1)`. PHPStan level 9 expects precise docblocks —
  `@phpstan-param`/`@phpstan-return` where PHP's native types can't express it
  (e.g. `class-string<Entity>`, generic arrays).
- **Goals over god-logic**: mob behavior is driven through the Goal FSM, not inline in
  entity tick methods. Add/port a mob by composing `goal/` components rather than writing
  imperative per-tick AI in the entity class.
- Port vanilla (Java) AI behavior faithfully, adapting network/item/world APIs to PM5's.

## Testing & quality gate

- A PHPUnit suite lives in `tests/phpunit` (run with `composer test`); it currently guards
  the spawn-rules data ⇔ `MobCategory` enum contract. Correctness is otherwise verified via
  PHPStan, php-cs-fixer, building the phar, and manual in-server testing.
- The build workflow produces a nightly phar via
  `composer build` → `vendor/bin/pharynx -i=. -c -p=MobPlugin.phar`.
- Before finishing: run **php-cs-fixer**, **PHPStan level 9** and **PHPUnit**
  (`composer test`) and make sure the phar builds.

## Workflows / CI

- `.github/workflows/build.yml` — nightly phar on pushes to `main`.
- `.github/workflows/phpstan.yml` — static analysis on push/PR (skipped if the commit
  message contains `[ci skip]`).
- `.github/workflows/phpunit.yml` — PHPUnit tests on push/PR (skipped if the commit
  message contains `[ci skip]`).
- `.github/workflows/release.yml` — tagged release builds (`v1.2.3` or `1.2.3`, with
  optional `-pre.0` suffixes).
- `.github/dependabot.yml` — daily Composer updates.

## Common pitfalls

- **Don't commit `vendor/`** (git-ignored) or generated `.cache` files.
- **Don't edit `composer.lock` by hand**; let Composer manage it.
- Keep changes consistent with the project's goal: faithful vanilla Bedrock mob AI on PM5,
  not ad-hoc spawn logic (override behavior through the Goal FSM and, where truly needed,
  the `EventListener`).
