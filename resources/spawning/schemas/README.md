# Mojang spawn-rule JSON schemas (pinned)

This directory is a committed, offline copy of the official Mojang
[bedrock-samples](https://github.com/Mojang/bedrock-samples) spawn-rule schemas used to
validate `resources/spawning/spawn_rules.json` in unit tests and the merge tool.

| Field | Value |
|---|---|
| Source repository | https://github.com/Mojang/bedrock-samples |
| Source path | `metadata/json_schemas` |
| Source commit | `46ba6ea985fb5a92d79a9419198f10dda14c199d` |
| Schema version | `server/spawn/1.21.50` |

Only the files reachable from `server/spawn/1.21.50/Spawn Rules.json` are committed
(≈32 files, the spawn schemas plus the shared `Filter Group`, `Block Descriptor` and
legacy `Reference` closures they `$ref`). The directories keep their original relative
layout so `$ref`s resolve unchanged; `SpawnRuleSchemaValidator` rewrites them to
absolute `file://` URIs at load time.

The source material is © Mojang AB and subject to the
[Minecraft End User License Agreement](https://www.minecraft.net/en-us/eula). It is
redistributed solely for interoperability with MobPlugin; MobPlugin is not affiliated
with, endorsed by, or sponsored by Mojang AB or Microsoft.

To bump the schema version, replace this tree from a fresh checkout at a new pinned
commit, regenerate `spawn_rules.json` and the generated schema artifacts
(`src/IvanCraft623/MobPlugin/spawning/parse/schema/`), and update
`tools/spawn-rules/{compile.php,generate-schema.php}` defaults plus this README's
version/commit.