<?php

/*
 *   __  __       _     _____  _             _
 *  |  \/  |     | |   |  __ \| |           (_)
 *  | \  / | ___ | |__ | |__) | |_   _  __ _ _ _ __
 *  | |\/| |/ _ \| '_ \|  ___/| | | | |/ _` | | '_ \
 *  | |  | | (_) | |_) | |    | | |_| | (_| | | | | |
 *  |_|  |_|\___/|_.__/|_|    |_|\__,_|\__, |_|_| |_|
 *                                      __/ |
 *                                     |___/
 *
 * A PocketMine-MP plugin that implements mobs AI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\spawning\parse\schema;

/**
 * Auto-generated from the Mojang spawn-rule JSON schemas — do not edit by hand.
 *
 * Envelope keys and difficulty names.
 * Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class SpawnSchema{
	public const SCHEMA_VERSION = "1.21.50";

	/** @var list<string> in ascending order */
	public const DIFFICULTY_CASES = ["peaceful", "easy", "normal", "hard"];

	public const DIFFICULTY_MIN = self::DIFFICULTY_CASES[0];

	public const DIFFICULTY_MAX = self::DIFFICULTY_CASES[3];

	public const KEY_CONDITIONS = "conditions";

	public const KEY_DESCRIPTION = "description";

	public const KEY_IDENTIFIER = "identifier";

	public const KEY_POPULATION_CONTROL = "population_control";
}