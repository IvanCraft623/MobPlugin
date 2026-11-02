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
controls. A mob gets a `PathNavigation` with a `GroundLocomotion`, `MoveControl`, `LookControl` and
`JumpControl` in `Mob::initEntity()`, and overrides `createNavigation()` for another
locomotion or other rules.

## Requesting a path

`moveToXYZ()` and `moveToEntity()` are the only calls into the pathfinder. They go through the
navigation's `PathRequester`, which runs the searches on the plugin's shared `AsyncSearchExecutor`
(`MobPlugin::getPathSearchExecutor()`) and returns the result to the navigation, which follows it.

Paths are computed on worker threads, but the chunks between start and target are
serialized on the main thread at submission, which is expensive. So:

- One computation per mob at a time. A new request while one runs attaches to its promise.
- Each request has an id; a result superseded by a newer request is discarded.
- `stop()` bumps the id, so a late result can't revive a stopped navigation, and cancels
  the search.
- `isDone()` is `false` while a computation is pending, so a goal doesn't cancel itself
  before its path arrives.
- `moveToEntity()` can be called every tick: `EntityRepathThrottle` requests again only when the
  entity changes block, or when the path has ended and the last request is
  `EntityRepathThrottle::INTERVAL = 20` ticks old.

`RepathPolicy` decides how to get a path again, without a mob or a world:

- When the target moved at most `SPLICE_MAX_TARGET_MOVE = 4` blocks and the current path reached
  the old one, the path is extended: the search starts at its remaining node closest to the new
  target, with `SPLICE_NODE_BUDGET_FACTOR = 0.25` of the node budget, and is spliced on.
- After `MAX_SPLICES_IN_A_ROW = 4` extensions or repairs the next request searches from the mob; so
  does an extension that can't reach the target or a path that changed meanwhile.

`PathRequester::recompute()` runs at most every `RECOMPUTE_INTERVAL = 20` ticks; an earlier call is
deferred to the next eligible tick. `PathfinderMob::onBlockChanged()` triggers it when a changed block
is one the search looked at for the nodes still to walk (`Path::findInvalidation()`), and only listens
in the chunks those blocks are in (`Path::getCorridorChunks()`).

The recompute patches the path instead of searching from the mob: the invalidated nodes of all the
changes are searched around, between the node before them and the node after them, with the splice
node budget and a radius of `REPAIR_MIN_RADIUS = 8` at least, and spliced into the old path, which is
walked meanwhile. It searches from the mob when the invalidation reaches the node the mob just left,
`MAX_SPLICES_IN_A_ROW` was hit, or the patch fails.

## Following a path

`PathNavigation::tick()`:

1. Runs a deferred recompute.
2. If there is no path (or it is done), lets the locomotion act (`tickWithoutPath()`, how a spider
   keeps climbing) when nothing is being computed, and returns.
3. `PathFollower::follow()` advances to the next node when the mob is close enough, or when it
   may cut the corner (never through fire, danger or door nodes), then checks for stuck.
4. Gives `MoveControl` the next position, with Y set by the locomotion (dropped to the floor on the
   ground).

A `StuckDetector` says the mob is stuck when it moved less than a quarter of its expected distance in
`DISTANCE_CHECK_INTERVAL = 100` ticks, or stayed on one node for `TIMEOUT_FACTOR = 3` times the time getting
there should take. The navigation then stops, and the goal decides whether to path again.

## Navigation

A mob has one `PathNavigation` (`final`); what differs between mobs is its `Locomotion`, the way
it moves, and its `TraversalRules`.

Behind it, one class per job: `PathRequester` (searches, with `RepathPolicy` choosing whether to
search anew, extend or repair, and `EntityRepathThrottle` for entity chases), `PathFollower`
(walks the path, with `StuckDetector`).

| Locomotion | Node evaluator | Notes |
|---|---|---|
| `GroundLocomotion` | `WalkNodeEvaluator` | Default. Snaps an air or solid target to the nearest walkable block. With `avoidSun`, paths stop before the first node in full light. |
| `WallClimberLocomotion` | inherited | Keeps pushing `MoveControl` toward the target after the path ends, which is how spiders climb. |
| `FlyingLocomotion` | `FlightNodeEvaluator` | Steers in 3D and cuts corners through open air. |
| `AmphibiousLocomotion` | `AmphibiousNodeEvaluator` | Swims in 3D through water, walks on land; always updates its path. Pair with `AquaticMoveControl`. |

`TraversalRules` (`getRules()`) are what the mob may do: `canFloat`, `canOpenDoors`, `canPassDoors`
(default true), `canWalkOverFences` and `avoidSun`. Goals change them while the mob lives, e.g.
`FloatGoal` sets `canFloat`.

A new way of moving is a subclass of `Locomotion` overriding the hooks that differ from a walker's:
evaluator, `canFollowPath()`, `pathPosition()`, `isStableDestination()` and the optional `wantedY()`,
`adjustTarget()`, `adjustPath()`, `canMoveDirectly()`, `advanceWithoutFollowing()` and
`tickWithoutPath()`. `PathNavigation::setLocomotion()` changes it for a living mob, dropping its path.

Path costs per block type come from `Mob::getPathTypeCostMap()`; set them with
`Mob::setPathfindingMalus()`.

## Controls

**`MoveControl`** has four operations:

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
