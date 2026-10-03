# Vanilla Bedrock data

`spawn_rules.json` is the vanilla spawn rules merged into one file: comments stripped, entries
keyed and sorted by identifier, values unchanged.

| | |
|---|---|
| Game version | 1.26.50.4 |
| Spawn schema version | 1.21.50 |
| Source | [Mojang/bedrock-samples](https://github.com/Mojang/bedrock-samples) |
| Commit | `46ba6ea985fb5a92d79a9419198f10dda14c199d` |
| Path | `behavior_pack/spawn_rules` |

The spawn schema classes (`spawning/parse/schema/`) and the entity data (`EntityIds`,
`VanillaEntitySizes`) are generated from the same commit. To regenerate, see
`docs/spawning.md`.
