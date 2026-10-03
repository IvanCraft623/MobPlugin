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
navigation->tick()       follow the path, feed MoveControl
moveControl->tick()      wanted position → rotation and speeds
lookControl->tick()      wanted look target → yaw and pitch
jumpControl->tick()      one-shot jumps
travel(...)              apply the speeds as motion
```

Goals never move a mob directly: they ask the navigation, and the navigation drives the
controls. A mob gets a `GroundPathNavigation`, `MoveControl`, `LookControl` and
`JumpControl` in `Mob::initEntity()`, and overrides `createNavigation()` for another
navigator.

## Requesting a path

`PathNavigation::createPath()` is the only call into the pathfinder
(`PathFinder::findPathAsync()`). `createPathToXYZ()`, `createPathToEntity()` and
`createPathToPosition()` wrap it, and `moveToXYZ()` / `moveToEntity()` also start
following the result.

Paths are computed on worker threads, but the chunks between start and target are
serialized on the main thread at submission, which is expensive. So:

- One computation per mob at a time. A new request while one runs attaches to its promise.
- Each request has an id; a result superseded by a newer request is discarded.
- `stop()` bumps the id, so a late result can't revive a stopped navigation.
- `isDone()` is `false` while a computation is pending, so a goal doesn't cancel itself
  before its path arrives.

`recomputePath()` runs at most every `MAX_TIME_RECOMPUTE = 20` ticks; an earlier call is
deferred to the next eligible tick. `PathfinderMob::onBlockChanged()` triggers it when a
changed block is close to the remaining path.

## Following a path

`PathNavigation::tick()`:

1. Runs a deferred `recomputePath()`.
2. Returns if there is no path, or it is done.
3. `followThePath()` advances to the next node when the mob is close enough, or when it
   may cut the corner (never through fire, danger or door nodes), then checks for stuck.
4. Gives `MoveControl` the next position, with Y dropped to the floor unless the mob is
   falling.

A mob is stuck when it moved less than a quarter of its expected distance in
`STUCK_CHECK_INTERVAL = 100` ticks, or stayed on one node past its timeout. The navigation
then stops, and the goal decides whether to path again.

## Navigators

```
PathNavigation
├── GroundPathNavigation     default
│   └── WallClimberNavigation   Spider
└── FlyingPathNavigation     Wither
```

| Navigator | Node evaluator | Notes |
|---|---|---|
| `GroundPathNavigation` | `WalkNodeEvaluator` | Snaps an air or solid target to the nearest walkable block. `setAvoidSun(true)` truncates paths that step into full light. |
| `WallClimberNavigation` | inherited | Keeps pushing `MoveControl` toward the target after the path ends, which is how spiders climb. |
| `FlyingPathNavigation` | `FlightNodeEvaluator` | Steers in 3D and cuts corners through open air. |

Path costs per block type come from `Mob::getPathTypeCostMap()`; set them with
`Mob::setPathfindingMalus()`.

## Controls

**`MoveControl`** has four operations:

| Operation | Meaning |
|---|---|
| `OPERATION_WAIT` | idle |
| `OPERATION_MOVE_TO` | steer toward the wanted position |
| `OPERATION_STRAFE` | move relative to the current yaw |
| `OPERATION_JUMPING` | hold the jump until back on the ground or in liquid |

For `MOVE_TO` it turns the mob toward the target (at most 90° per tick), sets its speed,
and jumps when the target is more than a step up and close. `FlightMoveControl` also
steers pitch and vertical speed, and turns gravity off while flying.

**`LookControl`**: `setLookAt()` holds a look target for 2 ticks, so goals call it every
tick. Body-yaw syncing is still a TODO.

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
- Don't call `PathFinder` or `MoveControl::setWantedPosition()` from a goal unless you mean
  to bypass the pathfinder, as the slime and spider goals do.
- `Mob::teleport()` stops the navigation.
- Debug mode (`Settings::isDebugModeEnabled()`) draws the current path and shows the
  running goals in the nametag.

For a new navigator, extend `PathNavigation`, implement `createPathFinder()`,
`canUpdatePath()` and `getTempMobPosition()`, and return it from `createNavigation()`.
