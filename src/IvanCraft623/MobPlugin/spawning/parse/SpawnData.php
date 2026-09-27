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
use function array_key_exists;
use function count;
use function get_debug_type;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use const JSON_THROW_ON_ERROR;

/**
 * Typed, path-tracking reader over decoded spawn-rule JSON. Every reader validates the
 * value's type and throws a SpawnParseException carrying the full JSON path on mismatch,
 * e.g. 'minecraft:zombie.conditions[3].brightness_filter.min' must be an integer, got string.
 */
final class SpawnData{

	/**
	 * @phpstan-throws SpawnParseException
	 */
	public static function fromJson(string $json) : self{
		try{
			$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}catch(\JsonException $e){
			throw new SpawnParseException("Malformed JSON: {$e->getMessage()}", 0, $e);
		}
		if(!is_array($decoded)){
			throw new SpawnParseException("Expected a JSON object at the root, got " . get_debug_type($decoded));
		}

		return new self($decoded, "");
	}

	/**
	 * @phpstan-param array<array-key, mixed> $data
	 */
	public function __construct(
		private readonly array $data,
		public readonly string $path = ""
	){}

	/**
	 * @phpstan-return list<string>
	 */
	public function keys() : array{
		$keys = [];
		foreach($this->data as $key => $_){
			if(is_string($key)){
				$keys[] = $key;
			}
		}

		return $keys;
	}

	public function has(string $key) : bool{
		return array_key_exists($key, $this->data);
	}

	public function at(string $key) : string{
		return $this->path === "" ? $key : "{$this->path}.{$key}";
	}

	/**
	 * @phpstan-throws SpawnParseException
	 */
	public function raw(string $key) : mixed{
		if(!array_key_exists($key, $this->data)){
			throw new SpawnParseException("'{$this->at($key)}' directive not found");
		}

		return $this->data[$key];
	}

	/**
	 * @phpstan-throws SpawnParseException
	 */
	public function int(string $key) : int{
		$value = $this->raw($key);
		if(!is_int($value)){
			throw new SpawnParseException("'{$this->at($key)}' must be an integer, got " . get_debug_type($value));
		}

		return $value;
	}

	public function intOr(string $key, int $default) : int{
		return $this->has($key) ? $this->int($key) : $default;
	}

	public function intNullable(string $key) : ?int{
		return $this->has($key) ? $this->int($key) : null;
	}

	/**
	 * @phpstan-throws SpawnParseException
	 */
	public function float(string $key) : float{
		$value = $this->raw($key);
		if(!is_int($value) && !is_float($value)){
			throw new SpawnParseException("'{$this->at($key)}' must be a number, got " . get_debug_type($value));
		}

		return (float) $value;
	}

	public function floatOr(string $key, float $default) : float{
		return $this->has($key) ? $this->float($key) : $default;
	}

	public function floatNullable(string $key) : ?float{
		return $this->has($key) ? $this->float($key) : null;
	}

	/**
	 * @phpstan-throws SpawnParseException
	 */
	public function string(string $key) : string{
		$value = $this->raw($key);
		if(!is_string($value)){
			throw new SpawnParseException("'{$this->at($key)}' must be a string, got " . get_debug_type($value));
		}

		return $value;
	}

	public function stringOr(string $key, string $default) : string{
		return $this->has($key) ? $this->string($key) : $default;
	}

	public function stringNullable(string $key) : ?string{
		return $this->has($key) ? $this->string($key) : null;
	}

	/**
	 * @phpstan-throws SpawnParseException
	 */
	public function bool(string $key) : bool{
		$value = $this->raw($key);
		if(!is_bool($value)){
			throw new SpawnParseException("'{$this->at($key)}' must be a boolean, got " . get_debug_type($value));
		}

		return $value;
	}

	public function boolOr(string $key, bool $default) : bool{
		return $this->has($key) ? $this->bool($key) : $default;
	}

	/**
	 * A string or a list of strings, collapsed to a list.
	 *
	 * @phpstan-return list<string>
	 * @phpstan-throws SpawnParseException
	 */
	public function strings(string $key) : array{
		$value = $this->raw($key);
		if(is_string($value)){
			return [$value];
		}
		if(!is_array($value) || !array_is_list($value)){
			throw new SpawnParseException("'{$this->at($key)}' must be a string or an array of strings, got " . get_debug_type($value));
		}
		foreach($value as $index => $entry){
			if(!is_string($entry)){
				throw new SpawnParseException("'{$this->at($key)}[{$index}]' must be a string, got " . get_debug_type($entry));
			}
		}

		return $value;
	}

	/**
	 * @phpstan-throws SpawnParseException
	 */
	public function object(string $key) : self{
		$value = $this->raw($key);
		// An empty PHP array is a list per array_is_list(), but JSON {} is an object —
		// marker components are exactly that.
		if(!is_array($value) || (array_is_list($value) && count($value) !== 0)){
			throw new SpawnParseException("'{$this->at($key)}' must be an object, got " . get_debug_type($value));
		}

		return new self($value, $this->at($key));
	}

	public function objectNullable(string $key) : ?self{
		return $this->has($key) ? $this->object($key) : null;
	}

	/**
	 * A list of objects, or a single object promoted to a one-element list.
	 *
	 * @phpstan-return list<self>
	 * @phpstan-throws SpawnParseException
	 */
	public function objectOrList(string $key) : array{
		$value = $this->raw($key);
		if(is_array($value) && (!array_is_list($value) || count($value) === 0)){
			return [new self($value, $this->at($key))];
		}
		if(!is_array($value) || !array_is_list($value)){
			throw new SpawnParseException("'{$this->at($key)}' must be an object or a list of objects, got " . get_debug_type($value));
		}
		$result = [];
		foreach($value as $index => $entry){
			if(!is_array($entry) || array_is_list($entry)){
				throw new SpawnParseException("'{$this->at($key)}[{$index}]' must be an object, got " . get_debug_type($entry));
			}
			$result[] = new self($entry, "{$this->at($key)}[{$index}]");
		}

		return $result;
	}

}
