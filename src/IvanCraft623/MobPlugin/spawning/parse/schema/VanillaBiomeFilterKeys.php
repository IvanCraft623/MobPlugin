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
 * Auto-generated from the Mojang filter schemas biome_filter refers to — do not edit by
 * hand.
 *
 * The keys of a filter node: FIELD_* are the fields of a single test, GROUP_* the keys
 * that hold a group of nodes.
 * Regenerate with: php tools/spawn-rules/generate-schema.php
 */
final class VanillaBiomeFilterKeys{

	public const FIELD_DOMAIN = "domain";
	public const FIELD_OPERATOR = "operator";
	public const FIELD_SUBJECT = "subject";
	public const FIELD_TEST = "test";
	public const FIELD_VALUE = "value";

	public const GROUP_ALL = "all";
	public const GROUP_ALL_OF = "all_of";
	public const GROUP_AND = "AND";
	public const GROUP_ANY = "any";
	public const GROUP_ANY_OF = "any_of";
	public const GROUP_NONE_OF = "none_of";
	public const GROUP_NOT = "NOT";
	public const GROUP_OR = "OR";

	private function __construct(){}
}
