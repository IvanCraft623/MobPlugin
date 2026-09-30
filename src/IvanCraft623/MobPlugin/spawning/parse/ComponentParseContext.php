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

use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use function array_is_list;
use function count;
use function get_debug_type;
use function is_array;
use function is_string;

final class ComponentParseContext{

	/**
	 * Vanilla block names with no PocketMine-MP equivalent, dropped from block filters by
	 * design. Any other unresolvable name aborts the load.
	 *
	 * @var array<string, true>
	 */
	private const KNOWN_MISSING_BLOCKS = ["minecraft:powder_snow" => true];

	public function __construct(
		private readonly SpawnData $condition,
		private readonly string $component,
		private readonly BlockNameResolver $blocks,
		private readonly BiomeTagMap $biomeTags
	){}

	public function getComponent() : string{
		return $this->component;
	}

	public function getPath() : string{
		return $this->condition->at($this->component);
	}

	/**
	 * Raw JSON value, only for payload shapes with no generated model.
	 *
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function getValue() : mixed{
		return $this->condition->raw($this->component);
	}

	public function getBiomeTags() : BiomeTagMap{
		return $this->biomeTags;
	}

	/**
	 * @template T of object
	 * @phpstan-param class-string<T> $model
	 * @phpstan-return T
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function map(string $model) : object{
		$value = $this->getValue();
		if(!is_array($value) || (array_is_list($value) && count($value) !== 0)){
			throw new SpawnRulesParseException("'{$this->getPath()}' must be an object, got " . get_debug_type($value));
		}

		return $this->mapObject($this->getPath(), $value, $model);
	}

	/**
	 * Maps a single object or a list of objects into a list of models.
	 *
	 * @template T of object
	 * @phpstan-param class-string<T> $model
	 * @phpstan-return list<T>
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function mapList(string $model) : array{
		$value = $this->getValue();
		$path = $this->getPath();
		if(is_array($value) && (!array_is_list($value) || count($value) === 0)){
			return [$this->mapObject($path, $value, $model)];
		}
		if(!is_array($value)){
			throw new SpawnRulesParseException("'$path' must be an object or a list of objects, got " . get_debug_type($value));
		}

		$result = [];
		foreach($value as $index => $entry){
			if(!is_array($entry) || array_is_list($entry)){
				throw new SpawnRulesParseException("'{$path}[{$index}]' must be an object, got " . get_debug_type($entry));
			}
			$result[] = $this->mapObject("{$path}[{$index}]", $entry, $model);
		}

		return $result;
	}

	/**
	 * @phpstan-return array<int, true>
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function resolveBlockSet() : array{
		$set = [];
		foreach($this->readBlockNames() as $name){
			if(isset(self::KNOWN_MISSING_BLOCKS[$name])){
				continue;
			}
			$typeId = $this->blocks->resolve($name);
			if($typeId === null){
				throw new SpawnRulesParseException("'{$this->getPath()}' block name \"$name\" cannot be resolved");
			}
			$set[$typeId] = true;
		}

		return $set;
	}

	/**
	 * @phpstan-return list<string>
	 */
	private function readBlockNames() : array{
		$value = $this->getValue();
		if(is_string($value)){
			return [$value];
		}
		if(!is_array($value) || !array_is_list($value)){
			throw new SpawnRulesParseException("'{$this->getPath()}' must be a string or a list of block names");
		}
		$names = [];
		foreach($value as $item){
			if(is_string($item)){
				$names[] = $item;
			}elseif(is_array($item) && isset($item["name"]) && is_string($item["name"])){
				$names[] = $item["name"];
			}else{
				throw new SpawnRulesParseException("'{$this->getPath()}' entries must be strings or {name: string} objects");
			}
		}

		return $names;
	}

	/**
	 * @template T of object
	 * @phpstan-param class-string<T>         $model
	 * @phpstan-param array<array-key, mixed> $json
	 * @phpstan-return T
	 * @phpstan-throws SpawnRulesParseException
	 */
	private function mapObject(string $path, array $json, string $model) : object{
		$mapper = new \JsonMapper();
		$mapper->bEnforceMapType = false;
		$mapper->bExceptionOnMissingData = true;
		$mapper->bStrictObjectTypeChecking = true;

		try{
			return $mapper->map($json, new $model());
		}catch(\JsonMapper_Exception $e){
			throw new SpawnRulesParseException("'$path' " . $e->getMessage(), 0, $e);
		}
	}
}
