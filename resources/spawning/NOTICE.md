# NOTICE — vanilla spawn rules data

`spawn_rules.json` in this directory is a machine-generated, deterministic merge of the
vanilla **Minecraft: Bedrock Edition** entity spawn rules published by Mojang in the
[bedrock-samples](https://github.com/Mojang/bedrock-samples) repository, under
`behavior_pack/spawn_rules`.

| Field | Value |
|---|---|
| Source repository | https://github.com/Mojang/bedrock-samples |
| Source path | `behavior_pack/spawn_rules` |
| Source commit | `46ba6ea985fb5a92d79a9419198f10dda14c199d` |
| Game version | 1.26.50.4 |
| Schema validation | `metadata/json_schemas/server/spawn/1.21.50` |
| Merged entities | 60 |
| Merged by | `tools/spawn-rules/compile.php` v1.3.0 |

The merger strips comments (some vanilla files are not strict JSON), keys every entry by its
`description.identifier`, sorts identifiers and pretty-prints. **No other transformation is
applied** — keys, values and structure are byte-faithful to the source data. Every merged
entry is validated against the pinned spawn schemas and its condition components are checked
against the pinned schema inventory; the merge fails closed on any drift.

The source material is © Mojang AB and subject to the [Minecraft End User License
Agreement](https://www.minecraft.net/en-us/eula). This merged file is redistributed solely
for interoperability with MobPlugin; MobPlugin is not affiliated with, endorsed by, or
sponsored by Mojang AB or Microsoft. The generated schema artifacts derived from the same
checkout (src/IvanCraft623/MobPlugin/spawning/parse/schema/) carry the same rationale.

The source commit is pinned by the `mojang/bedrock-samples` dev dependency in
`composer.json`. To regenerate these files after `composer install`:

```
php tools/spawn-rules/compile.php
php tools/spawn-rules/generate-schema.php
```
