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

use IvanCraft623\MobPlugin\spawning\parse\resolver\BiomeTagResolver;
use IvanCraft623\MobPlugin\spawning\parse\resolver\BlockNameResolver;
use function array_is_list;
use function is_array;
use function is_string;

final class SpawnConditionContext{

	/**
	 * Vanilla block names the block resolver can never resolve (PocketMine-MP has no
	 * equivalent block); dropped from block filters by design — the filter keeps its
	 * resolvable names. Any other unresolvable name aborts the load.
	 *
	 * @var array<string, true>
	 */
	private const KNOWN_MISSING_BLOCKS = ["minecraft:powder_snow" => true];

	public function __construct(
		private readonly SpawnData $condition,
		private readonly string $component,
		private readonly BlockNameResolver $blocks,
		private readonly BiomeTagResolver $biomeTags
	){}

	/** The normalized component name (no "minecraft:" prefix). */
	public function component() : string{
		return $this->component;
	}

	/** JSON path of this component, e.g. 'minecraft:zombie.conditions[3].weight'. */
	public function path() : string{
		return $this->condition->at($this->component);
	}

	/**
	 * Maps this component's payload into the generated typed model via JsonMapper.
	 *
	 * @template T of object
	 * @phpstan-param class-string<T> $model
	 * @phpstan-return T
	 * @phpstan-throws SpawnParseException
	 */
	public function map(string $model) : object{
		return SpawnConditionData::map($this->condition, $this->component, $model);
	}

	/**
	 * Maps this component's payload (a single object or a list of objects) into a list of
	 * typed models.
	 *
	 * @template T of object
	 * @phpstan-param class-string<T> $model
	 * @phpstan-return list<T>
	 * @phpstan-throws SpawnParseException
	 */
	public function mapList(string $model) : array{
		return SpawnConditionData::mapList($this->condition, $this->component, $model);
	}

	/**
	 * This component's raw JSON value. Only for payload shapes with no generated model
	 * (biome_filter, spawns_on_block). Mismatches throw with the full JSON path.
	 *
	 * @phpstan-throws SpawnParseException
	 */
	public function value() : mixed{
		return $this->condition->raw($this->component);
	}

	/**
	 * Resolves this component's block-name value into a set of block type ids;
	 * unresolvable names abort the load.
	 *
	 * @phpstan-return array<int, true>
	 * @phpstan-throws SpawnParseException when a block name cannot be resolved
	 */
	public function resolveBlockSet() : array{
		$set = [];
		foreach($this->readBlockNames() as $name){
			if(isset(self::KNOWN_MISSING_BLOCKS[$name])){
				continue;
			}
			$typeId = $this->blocks->resolve($name);
			if($typeId === null){
				throw new SpawnParseException("'{$this->path()}' block name \"$name\" cannot be resolved");
			}
			$set[$typeId] = true;
		}

		return $set;
	}

	public function biomeTags() : BiomeTagResolver{
		return $this->biomeTags;
	}

	/**
	 * @phpstan-return list<string>
	 */
	private function readBlockNames() : array{
		$value = $this->condition->raw($this->component);
		if(is_string($value)){
			return [$value];
		}
		if(!is_array($value) || !array_is_list($value)){
			throw new SpawnParseException("'{$this->path()}' must be a string or a list of block names");
		}
		$names = [];
		foreach($value as $index => $item){
			if(is_string($item)){
				$names[] = $item;
				continue;
			}
			if(is_array($item) && isset($item["name"]) && is_string($item["name"])){
				$names[] = $item["name"];
				continue;
			}
			throw new SpawnParseException("'{$this->path()}' entries must be strings or {name: string} objects");
		}

		return $names;
	}
}
