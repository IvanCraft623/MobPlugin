# Mob navigation

How a mob gets from A to B. The AI code lives under
`src/IvanCraft623/MobPlugin/entity/ai/`; the pathfinding algorithm itself is the external
virion `ivancraft623/pathfinder`, a port of Java Edition's.

## One tick

`Mob::tickAi()` runs, in order:

```
sensing->tick()          line of sight
targetSelector->tick()   target goals
goalSelector->tick()     behavior goals
moveControl->tick()      a path-following control first ticks the navigation (follow the path, set the
                         wanted position), then turns the wanted position into rotation and speeds
lookControl->tick()      wanted look target → yaw and pitch
jumpControl->tick()      one-shot jumps
travel(...)              apply the speeds as motion
```

Goals never move a mob directly: they ask the navigation, and the navigation drives the
controls. A `PathfinderMob` gets a `PathNavigation` in `PathfinderMob::initEntity()`, and its `Locomotion`
creates the `MoveControl` that follows it. Other mobs get theirs from `createMoveControl()`. Every mob gets a
`LookControl` and `JumpControl` in `Mob::initEntity()`.

## Navigation

A `PathfinderMob` has one `PathNavigation`; `PathfinderMob::createNavigation()` gives it a `Locomotion` (how it moves) and
`TraversalRules` (what it may do). One class per job behind it:

| Class | Job |
|---|---|
| `PathRequester` | Runs searches; reuses the current path as `RepathPolicy` says. |
| `RepathPolicy` | New search, extend or repair. Needs no mob. |
| `EntityRepathThrottle` | Stops goals chasing an entity from requesting every tick. |
| `PathFollower` | Walks the path and feeds `MoveControl`. |
| `StuckDetector` | Tells when the mob is getting nowhere. |

| Locomotion | Node evaluator | Notes |
|---|---|---|
| `GroundLocomotion` | `WalkNodeEvaluator` | Default. Snaps targets to the ground. |
| `WallClimberLocomotion` | inherited | Keeps pushing toward the target after the path ends (spiders). |
| `FlyingLocomotion` | `FlightNodeEvaluator` | Steers in 3D, cuts corners through open air. |
| `AmphibiousLocomotion` | `AmphibiousNodeEvaluator` | Swims in 3D, walks on land. Creates an `AquaticMoveControl`. |

`TraversalRules` (`getRules()`): `canFloat`, `canOpenDoors`, `canPassDoors` (default true),
`canWalkOverFences` and `avoidSun`. Goals change them at runtime, e.g. `FloatGoal` sets `canFloat`.

A new way of moving is a `Locomotion` subclass overriding what differs from a walker's (the hooks
are documented in the class).

## Requesting a path

`moveToXYZ()` and `moveToEntity()` are the only calls into the pathfinder. Searches run on a worker, but
submitting one serializes the chunks between start and target on the main thread, so:

- One search per mob at a time; requests made meanwhile share it.
- A result that was superseded, or arrives after `stop()`, is discarded.
- `isDone()` is `false` while a search is pending.
- `moveToEntity()` may be called every tick: it requests again when the entity changes block, or when
  the path ended and `EntityRepathThrottle::INTERVAL = 20` ticks passed.

`RepathPolicy` picks:

- **Extend:** the target moved at most `SPLICE_MAX_TARGET_MOVE = 4` blocks and the path reached the old
  one. Search from its remaining node closest to the new target, with `SPLICE_NODE_BUDGET_FACTOR = 0.25`
  of the node budget, and splice it on.
- **Repair:** blocks changed on the path (`Path::findInvalidation()`). Search around the invalidated nodes
  (at least `REPAIR_MIN_RADIUS = 8` blocks) and splice it in; the old path is walked meanwhile.
- **Search from the mob:** after `MAX_SPLICES_IN_A_ROW = 4` of either, when one fails, or when the
  invalidation reaches the node the mob just left.

`PathfinderMob::onBlockChanged()` triggers a repair for blocks the search looked at, and only listens in
their chunks (`Path::getCorridorChunks()`). Recomputes run at most every
`PathRequester::RECOMPUTE_INTERVAL = 20` ticks.

## Following a path

`PathNavigation::tick()` runs a waiting recompute; with no path, lets the locomotion act
(`tickWithoutPath()`, how spiders keep climbing); otherwise `PathFollower::follow()` advances the nodes
(cutting corners, except through fire, danger or doors) and checks `StuckDetector`, and the navigation's
`MoveControl` is sent to the next node.

`StuckDetector` reports:

- `BLOCKED`: no progress toward the next node for `BLOCKED_CHECKS = 20` ticks (e.g. pushed behind a
  wall). The navigation searches again from the mob.
- `STUCK`: moved under a quarter of the expected distance in `DISTANCE_CHECK_INTERVAL = 100` ticks.
  `TIMED_OUT`: stayed on a node `TIMEOUT_FACTOR = 3` times too long. The navigation stops and the goal
  decides whether to path again.

Path costs per block type come from `PathNavigation::getCostMap()`; set them with
`PathNavigation::setPathfindingMalus()`.

## Controls

**`MoveControl`** is abstract: `GroundMoveControl` (default for a `PathfinderMob`), `FlightMoveControl`,
`AquaticMoveControl`, `SlimeMoveControl`, and `IdleMoveControl` for mobs that don't steer. It has four operations:

| Operation | Meaning |
|---|---|
| `MoveOperation::WAIT` | idle |
| `MoveOperation::MOVE_TO` | steer toward the wanted position |
| `MoveOperation::STRAFE` | move relative to the current yaw |
| `MoveOperation::JUMPING` | hold the jump until back on the ground or in liquid |

For `MOVE_TO` it turns the mob toward the target (at most 90° per tick), sets its speed,
and jumps when the target is more than a step up and close. `FlightMoveControl` also
steers pitch and vertical speed, and turns gravity off while flying.

**`LookControl`**: `setLookAt()` holds a look target for 2 ticks, so goals call it every
tick. It turns the head (`Living::getHeadYaw()`, which PocketMine lacks and `Living` sends in the movement
packets) and pitch. The body yaw, which is the movement direction, follows the movement, and is only dragged
along when the head is more than `maxYawRot` away while standing. A goal that needs the whole mob to face
something, like bow strafing, calls `Living::lookAt()` with limits.

**`JumpControl`**: `jump()` sets a flag; the next tick jumps once and clears it.

## Writing a goal that moves

```php
public function start() : void{
	$this->mob->getNavigation()->moveToXYZ($x, $y, $z, $this->speedModifier);
}

public function canContinueToUse() : bool{
	return !$this->mob->getNavigation()->isDone();
}

public function stop() : void{
	$this->mob->getNavigation()->stop();
}
```

- Pick destinations with the `entity/ai/utils/PositionGenerator` family (Java's
  `RandomPos`).
- Don't call the pathfinder or `MoveControl::setWantedPosition()` from a goal unless you mean
  to bypass the navigation, as the slime and spider goals do.
- `PathfinderMob::teleport()` stops the navigation.
- Debug mode (`Settings::isDebugModeEnabled()`) draws the current path and shows the
  running goals in the nametag.

For a new way of moving, extend `Locomotion` (see above) and return
`new PathNavigation($this, new YourLocomotion())` from `createNavigation()`.
