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
 * Schema facts the runtime consumes as defaults and the PHPUnit suite uses to pin the
 * artifact version. Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class SpawnSchema{
	/** Official spawn-schema version this artifact was generated from. */
	public const SCHEMA_VERSION = "1.21.50";

	/**
	 * Difficulty names the schema declares for difficulty_filter, in ascending order.
	 *
	 * @var list<string>
	 */
	public const DIFFICULTY_CASES = ["peaceful", "easy", "normal", "hard"];

	/** Lowest difficulty name declared by the schema (difficulty_filter min default). */
	public const DIFFICULTY_MIN = self::DIFFICULTY_CASES[0];

	/** Highest difficulty name declared by the schema (difficulty_filter max default). */
	public const DIFFICULTY_MAX = self::DIFFICULTY_CASES[3];

	/** Envelope key "conditions" the loader uses to navigate spawn-rule documents (declared by Spawn Rules.json). */
	public const KEY_CONDITIONS = "conditions";

	/** Envelope key "description" the loader uses to navigate spawn-rule documents (declared by Spawn Rules.json). */
	public const KEY_DESCRIPTION = "description";

	/** Envelope key "identifier" the loader uses to navigate spawn-rule documents (declared by Spawn Description.json). */
	public const KEY_IDENTIFIER = "identifier";

	/** Envelope key "population_control" the loader uses to navigate spawn-rule documents (declared by Spawn Description.json). */
	public const KEY_POPULATION_CONTROL = "population_control";
}
