# Vanilla Bedrock data

| | |
|---|---|
| Game version | 1.26.50.4 |
| Spawn schema version | 1.21.50 |
| Source | [Mojang/bedrock-samples](https://github.com/Mojang/bedrock-samples) |
| Commit | `46ba6ea985fb5a92d79a9419198f10dda14c199d` |

| Here | From | |
|---|---|---|
| `spawning/spawn_rules.json` | `behavior_pack/spawn_rules` | Merged into one file: comments stripped, entries keyed and sorted by identifier, values unchanged. |
| `loot_tables.json` | `behavior_pack/loot_tables` | The tables the entities name, and the tables those name, merged into one minified file, keyed by path without `loot_tables/` and `.json`, as are the tables `loot_table` entries name; values otherwise unchanged. |

The spawn schema classes (`spawning/parse/schema/`) and the entity data (`EntityIds`,
`VanillaEntitySizes`) are generated from the same commit. To regenerate, see
`docs/spawning.md`.
