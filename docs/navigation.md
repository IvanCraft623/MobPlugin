# Mob Navigation — Architecture Guide

This document explains how a mob in **MobPlugin** moves from point A to point B: who
requests paths, how the pathfinding library is invoked, how paths are followed, and how
the `MoveControl` / `LookControl` / `JumpControl` layer turns a path into actual motion.
It is aimed at people new to this repository.

All AI code lives under `src/IvanCraft623/MobPlugin/entity/ai/`. The actual pathfinding
algorithm (A\*, node evaluators, etc.) is **not** part of this repo — it is the external
virion dependency `ivancraft623/pathfinder` (see `composer.json`), a port of Java Edition's
pathfinding. Only its *usage* is covered here.

---

## 1. The big picture (one tick of a mob)

`Mob::entityBaseTick()` → `Mob::tickAi()` (`src/IvanCraft623/MobPlugin/entity/Mob.php`)
runs every tick, in this order:

```
tickAi()
 ├── sensing->tick()                 // line-of-sight etc. (entity/ai/sensing/Sensing.php)
 ├── targetSelector->tick()          // target goals (nearest attackable target, ...)
 ├── goalSelector->tick()            // behavior goals (stroll, panic, attack, breed, ...)
 ├── navigation->tick()              // follow the current Path, feed MoveControl
 ├── moveControl->tick()             // turn "wanted position" into rotation + speeds
 ├── lookControl->tick()             // turn "wanted look target" into yaw/pitch
 ├── jumpControl->tick()             // one-shot jump requests
 └── travel(...)                     // apply forward/sideways/upward speeds as motion
```

Every `Mob` owns one instance of each subsystem, created in `Mob::initEntity()`:

| Property       | Created by                        | Default implementation   |
|----------------|-----------------------------------|--------------------------|
| `$navigation`  | `Mob::createNavigation()`         | `GroundPathNavigation`   |
| `$lookControl` | direct                            | `LookControl`            |
| `$moveControl` | direct                            | `MoveControl`            |
| `$jumpControl` | direct                            | `JumpControl`            |

Mob classes override `createNavigation()` when they need a different navigator
(e.g. `Spider` returns `WallClimberNavigation`, `Wither` swaps in
`FlyingPathNavigation` + `FlightMoveControl` in its own init).

Goals never move mobs directly. They call `getNavigation()->moveToXYZ(...)`
(or `moveToEntity` / `moveToPath`), and the navigation→controls→travel pipeline does the rest.

---

## 2. Where path computation is called

There is exactly **one** entry point into the Pathfinder library for mob navigation:
`PathNavigation::createPath()`
(`src/IvanCraft623/MobPlugin/entity/ai/navigation/PathNavigation.php`).

```php
PathFinder::findPathAsync(
    onCompletion(...),          // callback on the main thread
    $this->nodeEvaluator,       // WalkNodeEvaluator / FlightNodeEvaluator / ...
    $this->mob->getWorld(),
    $this->mob->getPosition(),  // start
    $position,                  // target
    maxVisitedNodes,            // followRange * 16 (scaled by multiplier)
    maxDistanceFromStart,       // default: followRange
    $reach
);
```

Everything else is a thin wrapper:

| Method (PathNavigation)       | Used by                                             |
|-------------------------------|-----------------------------------------------------|
| `createPathToXYZ / createPathToEntity / createPathToPosition` | goals (`MeleeAttackGoal`, `PanicGoal`, `TemptGoal`, `FleeSunlightGoal`, `PickupItemsGoal`, `BreedGoal`, `FollowParentGoal`, ranged attack goals, `WitherAttackGoal`, ...) |
| `moveToXYZ` / `moveToEntity`  | convenience: `createPathTo...` + `moveToPath` on completion |
| `recomputePath()`             | re-path to `targetPosition` (rate-limited, see below) |

### Key concurrency design

`PathFinder::findPathAsync()` computes paths on worker threads, which means the chunk
corridor between start and target must be serialized **on the main thread** at submission
time — an expensive operation. To avoid doing it repeatedly:

- Only **one** computation may be in flight per mob (`$isPathComputationPending`,
  `$pendingPromise`). A new `createPath()` request while one is running simply **attaches
  to the in-flight promise** instead of stacking another task.
- Each request gets a monotonically increasing `$pathComputationId`; late "stale" results
  (superseded by a newer request) are discarded in the completion callback.
- `stop()` bumps the id and clears the pending state so a stopped navigation can never be
  "resurrected" by a late result.

`PathNavigation::isDone()` reports `false` while a computation is pending — this is
deliberate: the running goal must not cancel itself before its path arrives.

### Rate-limited recomputation

`recomputePath()` will not run more often than `MAX_TIME_RECOMPUTE = 20` ticks
(1 second); if called sooner it just sets `$hasDelayedRecomputation`, which `tick()`
picks up on the next eligible tick. Recomputation is triggered from:

- `PathfinderMob::onBlockChanged()` (path may now be blocked) — via
  `PathNavigation::shouldRecomputePath($blockPos)`, which checks whether the changed
  block is close enough to the remaining path to matter.

### Overridden target snapping (per navigator)

- `GroundPathNavigation::createPathToPosition()` snaps an AIR / solid target block to the
  nearest walkable position (scans down then up) before delegating to the base class.
- `WallClimberNavigation` (spiders) remembers the requested position in `$pathToPosition`
  and, once the path is done but the target isn't reached, keeps shoving the
  `MoveControl` toward it directly — that's what lets spiders climb walls off-path.
- `FlyingPathNavigation` overrides `tick()` so the flying mob steers toward the next
  path position in 3D, and `canMoveDirectly()` (straight-line raytrace check) allows
  corner-cutting through open air.

---

## 3. PathNavigation tick — following a path

`PathNavigation::tick()` (called once per tick from `Mob::tickAi()`):

1. Runs any delayed `recomputePath()`.
2. If there is no path (or it's done), returns — while an async computation is in flight
   the mob simply waits for the path to arrive.
3. `followThePath()` (when `canUpdatePath()`):
   - computes the waypoint acceptance radius from the mob's width
     (`$width > 0.75 ? width/2 : 0.75 - width/2`),
   - **advances** the path node when the mob is close enough to the next node center, or
     when corner-cutting is allowed (`canCutCorner()` — never through fire/danger/door
     nodes) and `shouldTargetNextNodeInDirection()` says the next node is behind us,
   - calls `doStuckDetection()`.
4. Pushes the next path position to `MoveControl`:
   `mob->getMoveControl()->setWantedPosition(nextXZ, groundY, speedModifier)`.
   `getGroundY()` drops the Y to the floor level (via
   `WalkNodeEvaluator::getFloorLevelAt`) unless the block below is air (falling).

### Stuck detection

Two independent mechanisms in `doStuckDetection()`:

- **Distance check** every `STUCK_CHECK_INTERVAL = 100` ticks: if the mob moved less than
  `speed * 100 * 0.25` blocks, it is flagged stuck and the navigation `stop()`s.
- **Node timeout**: accumulates ticks spent with the same next node; once
  `$timeoutTimer > $timeoutLimit * 3` (limit derived from distance/speed), `timeoutPath()`
  stops the path. Goals then decide on their own whether to re-path.

---

## 4. MoveControl — turning a position into motion

`entity/ai/control/MoveControl.php` implements a tiny per-tick state machine with four
operations:

| Operation          | Meaning                                                              |
|--------------------|----------------------------------------------------------------------|
| `OPERATION_WAIT`   | idle — zero forward speed                                            |
| `OPERATION_MOVE_TO`| steer toward `$wantedPosition` (set by the navigation or by goals)    |
| `OPERATION_STRAFE` | move forward/sideways relative to current yaw (e.g. `MeleeAttackGoal` strafing, dancers) |
| `OPERATION_JUMPING`| hold jump momentum until back on ground / in liquid                  |

For `MOVE_TO`, each tick it:

1. Computes `dx,dy,dz` to the wanted position; if within `0.0005²`, stops.
2. Rotates the mob toward the target with a max 90°/tick turn
   (`rotateLerp`, i.e. `clamp(wrapDegrees(delta), -90, 90)`) via `mob->setRotation()`.
3. Sets `mob->setMotionSpeed(speedModifier * movementSpeed)` — the attribute-driven speed
   scaled by the navigation's speed modifier.
4. **Jump handling**: if `dy > maxUpStep` and the horizontal distance is small, it calls
   `mob->getJumpControl()->jump()` and enters `OPERATION_JUMPING` (this is how mobs climb
   single-block steps while pathing).

`setWantedPosition()` refuses to downgrade `OPERATION_JUMPING` back to `MOVE_TO` while
mid-jump.

`isWalkable()` consults the navigation's node evaluator
(`getNodeEvaluator()->getBlockPathType(...)`) so strafing never steers into non-walkable
blocks.

**Variant:** `FlightMoveControl` (`Wither`, any `Mob&Flyable`) — same interface, but it
also steers pitch toward the wanted position (`maxPitchChange` deg/tick), sets
`upwardSpeed` from `dy`, and toggles gravity off while flying.

---

## 5. LookControl and JumpControl

### LookControl (`entity/ai/control/LookControl.php`)

- `setLookAt(Entity|Vector3, ?yawMax, ?pitchMax)` records a look target; the timer lasts
  2 ticks (goals re-call it every tick while they want the look held). Default rotation
  limits come from `Mob::getRotSpeed()` (10°/tick yaw) and `Mob::getMaxPitchRot()` (40).
- `tick()` rotates yaw/pitch toward the wanted direction with
  `rotateTowards(current, target, maxDelta)` and resets pitch to 0 each tick
  (`$resetPitchOnTick`, disable via `setResetPitchOnTick(false)`).
- Known TODO: body-yaw syncing (the commented-out block), like Java's body rotation.

Goals combine the two controls naturally, e.g. `MeleeAttackGoal` paths toward the target
while `LookAtEntityGoal`-style goals call `getLookControl()->setLookAt(...)`; a moving mob
looks where `LookControl` says and walks where the navigation/`MoveControl` say.

### JumpControl (`entity/ai/control/JumpControl.php`)

A one-shot latch: `jump()` sets a flag, the next `tick()` calls `mob->jump()` once and
clears it. Used by `MoveControl` for step-up, by `FloatGoal` (bob up while in water),
slime goals (`SlimeKeepOnJumpingGoal`), `LeapAtTargetGoal`, etc.

---

## 6. The three navigation implementations

Class tree under `entity/ai/navigation/`:

```
PathNavigation (abstract)
├── GroundPathNavigation    ← default (Mob::createNavigation())
│   └── WallClimberNavigation ← Spider
└── FlyingPathNavigation    ← Wither (and other future fliers)
```

| Navigator              | Node evaluator (Pathfinder lib) | `canUpdatePath()`          | Notes |
|------------------------|--------------------------------|----------------------------|-------|
| `GroundPathNavigation` | `WalkNodeEvaluator` (uses the mob's `BlockPathTypeCostMap`, can-pass-doors default) | on ground **or** in liquid | `setAvoidSun(true)` (used by undead via `FleeSunlightGoal`/`AvoidSunlightGoal`) truncates paths that step into light ≥15; door/lock/fence options delegated to the evaluator |
| `WallClimberNavigation`| inherited                      | inherited                  | keeps pushing MoveControl toward the raw target after the path ends (wall climbing) |
| `FlyingPathNavigation` | `FlightNodeEvaluator`          | can-float or in liquid     | 3D steering in `tick()`, straight-line corner cutting, `isStableDestination` checks full support above |

Each mob's **path cost preferences** live in `Mob::getPathTypeCostMap()` /
`Mob::setPathfindingMalus(BlockPathType, float)` — the `WalkNodeEvaluator` reads this map
when scoring nodes. Individual mobs (e.g. those afraid of water) set maluses in their
constructors.

---

## 7. The goal layer — who actually requests paths

Goals (see `AGENTS.md` for the Goal FSM) own the *decision* to move; the navigation owns
the *how*. Typical pattern:

```php
// canContinueToUse(): keep the goal alive while the path runs
public function canContinueToUse() : bool{
    return !$this->entity->getNavigation()->isDone();
}

// start(): request the path
public function start() : void{
    $this->entity->getNavigation()->moveToXYZ($x, $y, $z, $this->speedModifier);
}

// stop(): drop it
public function stop() : void{
    $this->entity->getNavigation()->stop();
}
```

Representative goal→navigation users:

- **Movement**: `RandomStrollGoal` (+ `WaterAvoidingRandomStrollGoal`,
  `WaterAvoidingRandomFlyingGoal`), `PanicGoal`, `TemptGoal`, `FollowParentGoal`,
  `BreedGoal`, `MoveTowardsTargetGoal`, `PickupItemsGoal`, `FleeSunlightGoal`.
- **Combat**: `MeleeAttackGoal` (re-paths to the target every `REPATH` ticks, strafes when
  close), `RangedAttackGoal` / `RangedBowAttackGoal` (keep distance), `LeapAtTargetGoal`.
- **Movement-independent navigation**: `FloatGoal` (JumpControl bobbing), slimes
  (`SlimeRandomDirectionGoal` etc. drive `MoveControl` directly — no pathfinder at all),
  `WitherAttackGoal` (flies, `FlightMoveControl`).

**Destination picking** uses the `entity/ai/utils/PositionGenerator` family
(`DefaultPositionGenerator`, `LandPositionGenerator`, `AirAndWaterPositionGenerator`,
`HoverPositionGenerator`, `AirPositionGenerator`) —
Java's `RandomPos`/`LandRandomPos` equivalents. They sample candidate positions around
the mob and score them with `PathfinderMob::getWalkTargetValue()`.

---

## 8. Recomputation & interplay with the world

- `PathfinderMob` registers as a chunk listener; `onBlockChanged()` is the hook that
  notices the world changed under a walking mob and triggers
  `navigation->recomputePath()` (rate-limited as described in §2).
- `Mob::teleport()` always calls `navigation->stop()` so a teleported mob doesn't keep
  walking a stale path.
- `PathNavigation::trimPath()` (and the Ground subclass override) adjusts paths for
  cauldrons and sunlight avoidance after a path resolves, before it is first followed.
- Debug mode (`Settings::isDebugModeEnabled()`) renders the current path as red dust
  particles and prints running goal names in the mob's nametag (`Mob::entityBaseTick`).

---

## 9. Quick reference: "I want to make a mob go somewhere"

From a **goal** (the normal case):

```php
$this->mob->getNavigation()->moveToEntity($target, $speedModifier); // or moveToXYZ(...)
```

- Guard the goal with `canContinueToUse() => !$this->mob->getNavigation()->isDone()`.
- Call `stop()` → `getNavigation()->stop()` when the goal ends.
- For look behavior during the movement, `getLookControl()->setLookAt(...)` each tick.
- Don't call `PathFinder` yourself, don't touch `MoveControl::setWantedPosition()` from a
  goal unless you are intentionally bypassing the pathfinder (as spider/slime goals do).

From a **new navigation subclass**: extend `PathNavigation`, implement
`createPathFinder()` (choose a node evaluator from `IvanCraft623\Pathfinder\evaluator`),
`canUpdatePath()` and `getTempMobPosition()`, then override `createNavigation()` in the
mob class.
