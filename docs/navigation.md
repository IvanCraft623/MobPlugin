# Mob navigation

How a mob gets from A to B. The code is under `src/IvanCraft623/MobPlugin/entity/ai/`; the
pathfinding algorithm is the external virion `ivancraft623/pathfinder`.

## Who does what

Four kinds of class cooperate to move a mob. Each has one job:

| Class | Responsibility |
|---|---|
| Goal | Decides where to go, and calls the navigation. |
| `PathNavigation` | Owns the path of one `PathfinderMob`: requests searches, repairs the path when blocks change, advances along it, and exposes the next position to reach. |
| `Locomotion` | How a kind of mover (walker, flier, swimmer, climber) differs: the node evaluator, how targets and paths are fitted to it, and which `MoveControl` follows it. Has no state of its own. |
| `MoveControl` | Turns "go to this position at this speed" into rotation and a `MovementInput`, with the physics of its movement. |

`TraversalRules` is what the mob may do (doors, fences, floating) and `MovementInput` what the controls
asked for (forward, upward, sideways speed), which `Mob::travel()` applies as motion.

The flow:

1. A goal calls `moveToXYZ()` or `moveToEntity()`.
2. The navigation requests a path. The search runs on a worker, shaped by the locomotion and the rules.
3. Every tick, `Mob::tickAi()` ticks the move control. A `PathMoveControl` first ticks the navigation,
   then takes the next position it exposes as its wanted position.
4. The control rotates the mob toward the wanted position, writes the `MovementInput` and jumps if needed.
5. `Mob::tickAi()` applies the `MovementInput` with `travel()`.

A `PathfinderMob` creates its navigation in `initEntity()`, and its control through the navigation's
locomotion. Mobs without a navigation (`Bat`, `Slime`) get their control from `Mob::createMoveControl()`.

## Navigation

`PathfinderMob::createNavigation()` gives the mob a `Locomotion` and `TraversalRules`. The navigation
delegates to:

| Class | Job |
|---|---|
| `PathRequester` | Runs searches; reuses the current path as `RepathPolicy` says. |
| `RepathPolicy` | New search, extend or repair. Needs no mob. |
| `EntityRepathThrottle` | Stops goals chasing an entity from requesting every tick. |
| `PathFollower` | Holds the path being followed (a `PathCursor`: the immutable `Path` a search found, and how far along it the mob is), advances along it and gives the next position to reach. |
| `StuckDetector` | Tells when the mob is getting nowhere. |

| Locomotion | Node evaluator | Move control | Notes |
|---|---|---|---|
| `GroundLocomotion` | `WalkNodeEvaluator` | `GroundMoveControl` | Default. Snaps targets to the ground. |
| `WallClimberLocomotion` | inherited | inherited | Keeps pushing toward the target after the path ends (spiders). |
| `FlyingLocomotion` | `FlightNodeEvaluator` | `FlightMoveControl` | Steers in 3D, cuts corners through open air. |
| `AmphibiousLocomotion` | `AmphibiousNodeEvaluator` | `AquaticMoveControl` | Walks on land and swims in 3D; with `canSwim` off it wades along the bottom, as a drowned does until it hunts. |
| `SwimLocomotion` | `SwimNodeEvaluator` | `AquaticMoveControl` | Swims in 3D and never leaves the water (fish). |

`TraversalRules` (`getRules()`): `canFloat`, `canOpenDoors`, `canPassDoors` (default true),
`canWalkOverFences`, `avoidSun` and `canSwim` (default true). Goals change them at runtime, e.g. `FloatGoal` sets `canFloat`.
Path costs per block type are in `getCostMap()`; set them with `setPathfindingMalus()`.

A new way of moving is a `Locomotion` subclass overriding what differs from a walker's (the hooks are
documented in the class) and, if it steers differently, a `PathMoveControl`.

## Requesting a path

`moveToXYZ()` and `moveToEntity()` are the only calls into the pathfinder. Submitting a search
serializes the chunks between start and target on the main thread, so:

- One search per mob at a time; requests made meanwhile share it.
- A result that was superseded, or arrives after `stop()`, is discarded.
- `moveToEntity()` may be called every tick: it requests again when the entity changes block, or when
  the path ended and `EntityRepathThrottle::INTERVAL = 20` ticks passed.

`RepathPolicy` picks:

- **Extend:** the target moved at most `SPLICE_MAX_TARGET_MOVE = 4` blocks and the path reached the old
  one. Search from its remaining node closest to the new target and splice it on.
- **Repair:** blocks changed on the path. Search around the invalidated nodes and splice it in; the old
  path is walked meanwhile.
- **Search from the mob:** after `MAX_SPLICES_IN_A_ROW = 4` of either, when one fails, or when the
  invalidation reaches the node the mob just left.

`PathfinderMob::onBlockChanged()` triggers a repair for blocks the search looked at, and only listens in
their chunks. Recomputes run at most every `PathRequester::RECOMPUTE_INTERVAL = 20` ticks.

## Following a path

`PathNavigation::tick()` runs a waiting recompute; with no path, lets the locomotion act
(`tickWithoutPath()`, how spiders keep climbing); otherwise `PathFollower::follow()` advances the nodes
(cutting corners, except through fire, danger or doors) and checks `StuckDetector`, and the next
node becomes the position the control reads.

`StuckDetector` reports:

- `BLOCKED`: no progress toward the next node for `BLOCKED_CHECKS = 20` ticks (e.g. pushed behind a
  wall). The navigation searches again from the mob.
- `STUCK`: moved under a quarter of the expected distance in `DISTANCE_CHECK_INTERVAL = 100` ticks.
  `TIMED_OUT`: stayed on a node `TIMEOUT_FACTOR = 3` times too long. The navigation stops and the goal
  decides whether to path again.

## Controls

**`MoveControl`** is abstract: `GroundMoveControl` (default for a `PathfinderMob`), `FlightMoveControl`,
`AquaticMoveControl`, `SlimeMoveControl`, and `IdleMoveControl` for mobs that don't steer. Operations are
`WAIT`, `MOVE_TO`, `STRAFE` (relative to the current yaw) and `JUMPING` (held until back on the ground or in
liquid).

For `MOVE_TO` it turns the mob toward the target (at most 90° per tick), sets its speed, and jumps when
the target is more than a step up and close. `FlightMoveControl` also steers pitch and vertical speed,
and turns gravity off while flying.

**`LookControl`**: `setLookAt()` holds a look target for 2 ticks, so goals call it every tick. It turns
the head (`Living::getHeadYaw()`, which PocketMine lacks) and pitch. The body yaw is the movement
direction; it is dragged along only when the head is more than `maxYawRot` away while standing. A goal
that needs the whole mob to face something, like bow strafing, calls `Living::lookAt()` with limits.

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

- Pick destinations with the `entity/ai/utils/PositionGenerator` family.
- Don't call the pathfinder or `MoveControl::setWantedPosition()` from a goal unless you mean
  to bypass the navigation, as the slime and spider goals do.
- `PathfinderMob::teleport()` stops the navigation.
- Debug mode (`Settings::isDebugModeEnabled()`) draws the current path and shows the
  running goals in the nametag.
