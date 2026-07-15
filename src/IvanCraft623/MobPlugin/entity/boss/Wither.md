<div align="center">
  <h1> 💀 Wither 🖤</h1>
  <p>Bedrock behavior</p>
</div>

---

## 🧱 Spawning

Upon being summoned, the Wither enters an **invulnerability** state lasting **200 ticks (10 seconds)**.

During this state:

- It cannot take damage (except internal game damage).
- It performs no actions.
- Once the counter expires, it triggers an **explosion** with a radius of **7 blocks**.

The **boss bar** during invulnerability does not reflect the Wither's health — instead, it shows the **counter's progress** (filling from 0% to 100% over the 200 ticks). Once the state ends, it resumes showing health normally.

---

## 📊 Attributes

| Attribute | Value |
|:---|:---:|
| Detection Range *(Follow Range)* | 70 blocks |
| Flying Speed | 0.6 |
| Base Movement Speed | 0.25 |

### Max Health by Difficulty

| Difficulty | HP |
|:---|:---:|
| Easy | 300 |
| Normal | 450 |
| Hard | 600 |

---

## 🎯 Targets

The Wither can maintain up to **three simultaneous targets**.

The **Main Target** is always selected by the following targeting goals, in priority order:

1. `TargetHighestDamagerGoal`
2. `HurtByTargetGoal`
3. `NearestAttackableGoal`

The Wither must have **line of sight** to an entity in order to select it as a target.

### Secondary Targets

Secondary targets are updated **every 10 ticks**.

The **second** and **third targets** are the nearest `Living` entities that:

- Are not the Main Target.
- Are not `Undead` entities.

> If the third head cannot find a valid target, it uses the same target as the second head.

---

## ⚡ Powered State

The Wither enters the **Powered** state immediately upon reaching **50% health**. Upon activation:

- Triggers an **explosion** with a radius of **7 blocks**.
- On **Normal** and **Hard** difficulty, summons **3 Wither Skeletons**.
- Becomes **immune to projectile damage**.
- Performs a **Dash Attack** after every **two ranged attack cycles**.
- Stops all pathfinding; movement is handled exclusively by the Dash Attack and the `WaterAvoidingRandomFlyingGoal`.

---

## 🔢 Generic Function: Health-Based Interval

The various time intervals of the Wither (shooting, post-attack repositioning, etc.) are all computed using a **single generic function**, which interpolates between a minimum and maximum value based on the Wither's remaining health within the current half-health range.

```text
FUNCTION getIntervalTicksByHealth(min, max, health, maxHealth):
    halfMaxHealth = maxHealth / 2
    relativeHealth = (CEIL(health) - 1) MOD halfMaxHealth
    interval = min + (max - min) * (relativeHealth / halfMaxHealth)

    RETURN CEIL(interval)
```

| Parameter | Description |
|:---|:---|
| `min` | Minimum interval (in ticks) |
| `max` | Maximum interval (in ticks) |
| `health` | Current health of the Wither |
| `maxHealth` | Maximum health of the Wither based on difficulty |

### Behavior at the 50% threshold

The `MOD halfMaxHealth` causes the interval to **reset at exactly 50% health**: the Wither briefly returns to its slowest speed at the moment it enters the Powered state, then accelerates again as it continues to lose health.

| Health state | `relativeHealth` | Result |
|:---|:---:|:---:|
| Full health (100%) | ≈ `halfMaxHealth` | ≈ `max` (slowest) |
| Just above 50% | ≈ `0` | ≈ `min` (fastest) |
| Exactly 50% (Powered transition) | ≈ `halfMaxHealth` | ≈ `max` (resets to slowest) |
| Just above 0% | ≈ `0` | ≈ `min` (fastest again) |

---

## 🗡️ Attack System

The Wither's active attack behavior is driven by `WitherAttackGoal`, which runs continuously while a main target exists and the Wither is no longer invulnerable. The goal cycles through **four distinct phases**:

---

## 🔄 Phase: REPOSITIONING

Entered at **goal start** and after **every completed attack** (SHOOTING burst or DASHING).

### Sequence

**Step 1 — Wait (`moveInterval` ticks)**

Navigation is immediately stopped. The Wither stays completely still and counts down `moveInterval` ticks before doing anything. The interval is health-based:

```text
MIN_MOVE_INTERVAL =  40 ticks  (2 s)
MAX_MOVE_INTERVAL = 100 ticks  (5 s)

moveInterval = getIntervalTicksByHealth(40, 100, health, maxHealth)
```

**Step 2 — Navigate (normal state only)**

Once the countdown expires:

- **If Powered:** skip navigation entirely and proceed directly to SHOOTING.
- **If not Powered:** navigate to a **random position 20 blocks around the Main Target**:
  - Height: `target.y + 5` blocks above the target

**Step 3 — Enter SHOOTING**

Navigation completes → transition to SHOOTING.

> **Note:** `moveInterval` is purely a post-attack waiting period — it is **not** a navigation timeout. There is no time limit on the navigation itself; the Wither waits for it to actually finish.

---

## 💀 Phase: SHOOTING

Entered after REPOSITIONING completes (navigation done, or instantly if Powered). Navigation is stopped when entering this phase.

### Shot Queue

When entering SHOOTING, a **shot queue** is built once and consumed shot by shot. The default queue contains **6 entries** in the following fixed order:

| Slot | Target | Type | Delay after firing |
|:---:|:---|:---:|:---:|
| 1 | Second Target | Normal skull | `SECOND_TO_MAIN_DELAY` = 5 ticks |
| 2 | Main Target | Normal skull | `shootInterval` |
| 3 | Main Target | Normal skull | `shootInterval` |
| 4 | Main Target | Normal skull | `shootInterval` |
| 5 | Third Target | Normal skull | `shootInterval` |
| 6 | Main Target | 🔵 Blue skull | `shootInterval` |

If the third head has no valid target, **it reuses the second target** for slot 5.

The **shoot interval** used when building the queue is health-based:

```text
MIN_SHOOT_INTERVAL =  5 ticks
MAX_SHOOT_INTERVAL = 15 ticks

shootInterval = getIntervalTicksByHealth(5, 15, health, maxHealth)
```

### Shot Firing

On each tick, the countdown decrements. When it reaches `0`:

1. The next slot in the queue is examined.
2. If the target entity is **alive and valid**: fire the skull and set `countdown = slot.delay`. Move to the next slot.
3. If the target entity is **dead or missing**: **skip the slot instantly** (no delay consumed) and check the very next slot in the same tick. This continues until a valid slot is found or the queue is exhausted.

When the queue is exhausted, `onBurstComplete()` is called.

### Burst Completion

```text
burstCount++

IF powered AND (burstCount MOD 2 == 0):
    → Enter DASH_WINDUP
ELSE:
    → Enter REPOSITIONING
```

The Wither performs a dash attack after **every two complete bursts** while Powered. The burst counter is never reset between cycles (only when the goal stops entirely).

---

## 🌀 Phase: DASH_WINDUP

Only entered when Powered, every two complete bursts.

### Sequence

**Lock-on (immediate)**

At the moment DASH_WINDUP is entered, the target position is **locked**: the Wither computes the direction from its current position to the Main Target and calculates the endpoint as:

```text
direction = normalize(targetPos - witherPos)
dashTargetPos = witherPos + direction × DASH_RANGE (20 blocks)
```

If the horizontal distance is `0` (directly on top of the target), the Wither falls back to dashing along its current yaw direction.

A **dash timeout** is also estimated at this point:

```text
dashTimeout = CEIL(dashRange / (speedModifier × movementSpeed))
```

This is a safety guard — not the primary termination condition.

**Wind-up wait (60 ticks = 3 seconds)**

Navigation is stopped. The Wither stays still for **60 ticks** while the target position remains locked to the computed point — the target's live position is **not** updated during this window.

After the countdown: → Enter DASHING.

---

## ⚡ Phase: DASHING

### Movement

Every tick, the Wither's move control is driven toward `dashTargetPos` at full speed. This bypasses pathfinding and moves in a straight line.

### Block Destruction

Every **2 ticks**, `breakBlocksAround()` is called, destroying a **4 × 4 × 6** volume centered on the Wither.

### Collision Damage

Each tick, all `Living` entities whose bounding box overlaps the Wither's are damaged. Deals a 22 HP damagage regardless of it's armor.

### Termination

The dash ends when either condition is met:

- The Wither is within **1 block** (horizontally) of `dashTargetPos`.
- The `dashTimeout` counter reaches `0`.

On termination: navigation is stopped → Enter REPOSITIONING.

---

## 💙 Random Blue Wither Skull

**Independently** of the attack goal, every tick while not invulnerable the Wither has a **1-in-240 chance** of firing a Blue Wither Skull in a **random direction** (random yaw and pitch). This averages to roughly one shot every **12 seconds** and is completely decoupled from the burst sequence.

Upon exploding, the maximum blast resistance of affected blocks is **4**.

---

## 🏃 Movement

The Wither attempts to maintain a certain height above the Main Target while navigation is idle:

| State | Target Height |
|:---|:---:|
| Normal | ~5 blocks above |
| Powered | ~1 block above |

Between bursts, the Wither never pathfinds directly toward its targets — it pathfinds to a **random position around the Main Target** (see REPOSITIONING phase).

Upon reaching **50% health**, the Wither stops this pathfinding and only moves through:

- **Dash Attack**
- **`WaterAvoidingRandomFlyingGoal`**

---

## 🛡️ Damage & Immunities

- Immune to **fall damage**.
- Immune to damage from its **own projectiles** and from **entities it has summoned** (those with itself as owner).
- In **Powered** state, immune to **projectile damage**. Arrows are deflected — a reflected arrow is spawned at the projectile's location with `−0.25×` the original motion.

---

## 💥 Block Destruction

Upon taking damage, the Wither schedules a block destruction event that occurs **20 ticks** after impact. Each activation destroys a **4 × 4 × 6** volume centered on the Wither.

---

## 🔁 Regeneration

If no players are within a radius of **50 blocks**, the Wither regenerates **1 HP per second** (checked every 20 ticks).

---

## 💀 Death

Upon dying, the Wither triggers an **explosion** with a radius of **7 blocks** and drops:

| Drop | Detail |
|:---|:---|
| ⭐ Nether Star | Never despawns |
| ✨ Experience | 50 points |
