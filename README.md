<div align="center">
  <h1>🐷 MobPlugin 🤖</h1>
  <p>Mobs like vanilla!</p>

  [![CI](https://img.shields.io/github/actions/workflow/status/IvanCraft623/MobPlugin/build.yml?label=CI&style=flat&logo=github)](https://github.com/IvanCraft623/MobPlugin/actions/workflows/build.yml)
  [![bStats Servers](https://img.shields.io/bstats/servers/32915?style=flat&logo=googleanalytics&logoColor=white)](https://bstats.org/plugin/server-implementation/MobPlugin/32915)
  [![bStats Players](https://img.shields.io/bstats/players/32915?style=flat&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNiIgaGVpZ2h0PSIxNiIgdmlld0JveD0iMCAwIDE2IDE2Ij48cGF0aCBmaWxsPSJ3aGl0ZSIgZD0iTTIgNS41YTMuNSAzLjUgMCAxIDEgNS44OTggMi41NDkgNS41MDggNS41MDggMCAwIDEgMy4wMzQgNC4wODQuNzUuNzUgMCAxIDEtMS40ODIuMjM1IDQgNCAwIDAgMC03LjkgMCAuNzUuNzUgMCAwIDEtMS40ODItLjIzNkE1LjUwNyA1LjUwNyAwIDAgMSAzLjEwMiA4LjA1IDMuNDkzIDMuNDkzIDAgMCAxIDIgNS41Wk0xMSA0YTMuMDAxIDMuMDAxIDAgMCAxIDIuMjIgNS4wMTggNS4wMSA1LjAxIDAgMCAxIDIuNTYgMy4wMTIuNzQ5Ljc0OSAwIDAgMS0uODg1Ljk1NC43NTIuNzUyIDAgMCAxLS41NDktLjUxNCAzLjUwNyAzLjUwNyAwIDAgMC0yLjUyMi0yLjM3Mi43NS43NSAwIDAgMS0uNTc0LS43M3YtLjM1MmEuNzUuNzUgMCAwIDEgLjQxNi0uNjcyQTEuNSAxLjUgMCAwIDAgMTEgNS41Ljc1Ljc1IDAgMCAxIDExIDRabS01LjUtLjVhMiAyIDAgMSAwLS4wMDEgMy45OTlBMiAyIDAgMCAwIDUuNSAzLjVaIi8+PC9zdmc+)](https://bstats.org/plugin/server-implementation/MobPlugin/32915)
  [![License](https://img.shields.io/github/license/IvanCraft623/MobPlugin?style=flat&logo=opensourceinitiative&logoColor=white)](LICENSE)
</div>

---

## 📃 Description

MobPlugin is a plugin for [PocketMine-MP](https://github.com/pmmp/PocketMine-MP) that implements mob AI aiming to replicate vanilla Minecraft: Bedrock Edition behavior as closely as possible.

For a full breakdown of what's implemented and what's planned, see the [Vanilla Minecraft Feature Tracking](https://github.com/IvanCraft623/MobPlugin/issues/36) issue.

> ⚠️ **Warning:** The plugin is still under active development and some features are not yet complete.

---

## ⚙️ How It Works

### Async Pathfinding
Pathfinding is computed off the main thread using [Pathfinder](https://github.com/IvanCraft623/Pahtfinder), a custom virion that ports Java Edition's pathfinding logic to PHP. This ensures that even complex mob navigation doesn't cause server lag spikes.

### Goal-based Finite State Machine (FSM)
Each mob has a set of **goals** with priorities — things like attacking, fleeing, wandering, or looking at players. Every tick, the mob evaluates which goals are applicable and runs the highest-priority one, switching seamlessly when conditions change. This mirrors how vanilla Minecraft handles mob AI, producing natural and predictable behavior.

---

## 📥 Download

> ℹ️ No stable release is available yet. Every commit automatically generates a downloadable nightly build.

<div align="center">

[![Download Nightly](https://img.shields.io/badge/dynamic/yaml?url=https://raw.githubusercontent.com/IvanCraft623/MobPlugin/main/plugin.yml&query=$.version&label=Download&suffix=%2Bdev&color=blueviolet&style=for-the-badge&logo=github&logoColor=white)](https://github.com/IvanCraft623/MobPlugin/releases/download/nightly/MobPlugin.phar)

*Always up to date · Built from the latest commit*

<br/>

<sub>Also available on <a href="https://poggit.pmmp.io/p/MobPlugin">Poggit CI</a> while the service is still running.</sub>

</div>

---

## 💖 Support the Project

MobPlugin is and will always be **free and open-source**. If you enjoy the plugin or it helps your server, consider supporting future development — it goes a long way!

<div align="center">

[![Donate](https://img.shields.io/badge/Donate-Support_Me-ff69b4?style=for-the-badge&logo=ilovepdf&logoColor=white)](https://donate.endergames.org/IvanCraft623)

</div>
