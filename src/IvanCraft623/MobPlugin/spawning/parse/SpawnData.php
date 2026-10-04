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
use function is_string;
use function json_decode;
use const JSON_THROW_ON_ERROR;

/**
 * Reader over decoded spawn-rule JSON that tracks the path, for error messages.
 */
final class SpawnData{

	/**
	 * @phpstan-throws SpawnRulesParseException
	 */
	public static function fromJson(string $json) : self{
		try{
			$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}catch(\JsonException $e){
			throw new SpawnRulesParseException("Malformed JSON: {$e->getMessage()}", 0, $e);
		}
		if(!is_array($decoded)){
			throw new SpawnRulesParseException("Expected a JSON object at the root, got " . get_debug_type($decoded));
		}

		return new self($decoded, "");
	}

	/**
	 * @phpstan-param array<array-key, mixed> $data
	 */
	public function __construct(
		public readonly array $data,
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
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function raw(string $key) : mixed{
		if(!array_key_exists($key, $this->data)){
			throw new SpawnRulesParseException("'{$this->at($key)}' directive not found");
		}

		return $this->data[$key];
	}

	/**
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function string(string $key) : string{
		$value = $this->raw($key);
		if(!is_string($value)){
			throw new SpawnRulesParseException("'{$this->at($key)}' must be a string, got " . get_debug_type($value));
		}

		return $value;
	}

	/**
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function object(string $key) : self{
		$value = $this->raw($key);
		// An empty PHP array is a list per array_is_list(), but JSON {} is an object —
		// marker components are exactly that.
		if(!is_array($value) || (array_is_list($value) && count($value) !== 0)){
			throw new SpawnRulesParseException("'{$this->at($key)}' must be an object, got " . get_debug_type($value));
		}

		return new self($value, $this->at($key));
	}

	/**
	 * A list of objects, or a single object promoted to a one-element list. Decoded
	 * JSON can't tell {} from [], so an empty value is an empty list.
	 *
	 * @phpstan-return list<self>
	 * @phpstan-throws SpawnRulesParseException
	 */
	public function objectOrList(string $key) : array{
		$value = $this->raw($key);
		if(is_array($value) && !array_is_list($value)){
			return [new self($value, $this->at($key))];
		}
		if(!is_array($value)){
			throw new SpawnRulesParseException("'{$this->at($key)}' must be an object or a list of objects, got " . get_debug_type($value));
		}
		$result = [];
		foreach($value as $index => $entry){
			if(!is_array($entry) || array_is_list($entry)){
				throw new SpawnRulesParseException("'{$this->at($key)}[{$index}]' must be an object, got " . get_debug_type($entry));
			}
			$result[] = new self($entry, "{$this->at($key)}[{$index}]");
		}

		return $result;
	}

}
