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

namespace IvanCraft623\MobPlugin\spawning\parse;

use function array_is_list;
use function count;
use function get_debug_type;
use function is_array;

/**
 * Populates generated XxxData payload models from a condition's raw JSON via JsonMapper;
 * schema/type-errors surface as SpawnParseException with the JSON path.
 */
final class SpawnConditionData{

	/**
	 * Maps a single-object component (e.g. brightness_filter) into its typed model.
	 * T is the concrete generated XxxData class passed as $model, so the caller's local is
	 * inferred exactly — no per-site type hint needed; field access is type-checked.
	 *
	 * @template T of object
	 * @phpstan-param class-string<T> $model
	 * @phpstan-return T
	 * @phpstan-throws SpawnParseException
	 */
	public static function map(SpawnData $condition, string $component, string $model) : object{
		$value = $condition->raw($component);
		if(!is_array($value) || (array_is_list($value) && count($value) !== 0)){
			throw new SpawnParseException("'{$condition->at($component)}' must be an object, got " . get_debug_type($value));
		}

		return self::mapObject($condition, $component, $value, $model);
	}

	/**
	 * Maps a component that is either a single object or a list of objects (herd,
	 * permute_type) into a list of typed models.
	 *
	 * @template T of object
	 * @phpstan-param class-string<T> $model
	 * @phpstan-return list<T>
	 * @phpstan-throws SpawnParseException
	 */
	public static function mapList(SpawnData $condition, string $component, string $model) : array{
		$value = $condition->raw($component);
		if(is_array($value) && (!array_is_list($value) || count($value) === 0)){
			return [self::mapObject($condition, $component, $value, $model)];
		}
		if(!is_array($value) || !array_is_list($value)){
			throw new SpawnParseException("'{$condition->at($component)}' must be an object or a list of objects, got " . get_debug_type($value));
		}

		$result = [];
		foreach($value as $index => $entry){
			if(!is_array($entry) || array_is_list($entry)){
				throw new SpawnParseException("'{$condition->at($component)}[{$index}]' must be an object, got " . get_debug_type($entry));
			}
			$result[] = self::mapObject($condition, "{$component}[{$index}]", $entry, $model);
		}

		return $result;
	}

	/**
	 * @template T of object
	 * @phpstan-param class-string<T> $model
	 * @phpstan-param array<array-key, mixed> $json
	 * @phpstan-return T
	 * @phpstan-throws SpawnParseException
	 */
	private static function mapObject(SpawnData $condition, string $pathKey, array $json, string $model) : object{
		$mapper = new \JsonMapper();
		$mapper->bEnforceMapType = false;
		$mapper->bExceptionOnMissingData = true;
		$mapper->bStrictObjectTypeChecking = true;

		try{
			return $mapper->map($json, new $model());
		}catch(\JsonMapper_Exception $e){
			throw new SpawnParseException("'{$condition->at($pathKey)}' " . $e->getMessage(), 0, $e);
		}
	}
}
