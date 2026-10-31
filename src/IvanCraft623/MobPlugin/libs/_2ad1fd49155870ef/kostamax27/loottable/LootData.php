<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable;

use InvalidArgumentException;
use JsonException;
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
use function var_export;
use const JSON_THROW_ON_ERROR;

final class LootData{

	public static function fromJson(string $json) : self{
		try{
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}catch(JsonException $e){
			throw new InvalidArgumentException("Malformed JSON: {$e->getMessage()}", $e->getCode(), $e);
		}
		is_array($data) || throw new InvalidArgumentException("Expected a JSON object at the root, got " . get_debug_type($data));
		return new self($data, "");
	}

	/**
	 * @param array<array-key, mixed> $data
	 */
	public function __construct(
		readonly private array $data,
		readonly public string $path
	){}

	public function has(string $key) : bool{
		return array_key_exists($key, $this->data);
	}

	public function at(string $key) : string{
		return $this->path === "" ? $key : "{$this->path}.{$key}";
	}

	public function raw(string $key) : mixed{
		array_key_exists($key, $this->data) || throw new InvalidArgumentException("'{$this->at($key)}' directive not found");
		return $this->data[$key];
	}

	public function int(string $key) : int{
		$value = $this->raw($key);
		is_int($value) || throw new InvalidArgumentException("'{$this->at($key)}' must be an integer, got " . get_debug_type($value));
		return $value;
	}

	public function intOr(string $key, int $default) : int{
		return $this->has($key) ? $this->int($key) : $default;
	}

	public function intNullable(string $key) : ?int{
		return $this->has($key) ? $this->int($key) : null;
	}

	public function float(string $key) : float{
		$value = $this->raw($key);
		is_int($value) || is_float($value) || throw new InvalidArgumentException("'{$this->at($key)}' must be a number, got " . get_debug_type($value));
		return (float) $value;
	}

	public function floatOr(string $key, float $default) : float{
		return $this->has($key) ? $this->float($key) : $default;
	}

	public function floatNullable(string $key) : ?float{
		return $this->has($key) ? $this->float($key) : null;
	}

	public function string(string $key) : string{
		$value = $this->raw($key);
		is_string($value) || throw new InvalidArgumentException("'{$this->at($key)}' must be a string, got " . get_debug_type($value));
		return $value;
	}

	public function stringOr(string $key, string $default) : string{
		return $this->has($key) ? $this->string($key) : $default;
	}

	public function stringNullable(string $key) : ?string{
		return $this->has($key) ? $this->string($key) : null;
	}

	public function bool(string $key) : bool{
		$value = $this->raw($key);
		is_bool($value) || throw new InvalidArgumentException("'{$this->at($key)}' must be a boolean, got " . get_debug_type($value));
		return $value;
	}

	public function boolOr(string $key, bool $default) : bool{
		return $this->has($key) ? $this->bool($key) : $default;
	}

	public function boolNullable(string $key) : ?bool{
		return $this->has($key) ? $this->bool($key) : null;
	}

	/**
	 * Reads a bedrock range: a number, a {min, max} object or a [min, max] tuple.
	 *
	 * @param string $key
	 * @return IntRange
	 */
	public function intRange(string $key) : IntRange{
		[$min, $max] = $this->range($key);
		is_int($min) || throw new InvalidArgumentException("'{$this->at($key)}' minimum must be an integer, got " . get_debug_type($min));
		is_int($max) || throw new InvalidArgumentException("'{$this->at($key)}' maximum must be an integer, got " . get_debug_type($max));
		return new IntRange($min, $max);
	}

	public function intRangeOr(string $key, IntRange $default) : IntRange{
		return $this->has($key) ? $this->intRange($key) : $default;
	}

	public function floatRange(string $key) : FloatRange{
		[$min, $max] = $this->range($key);
		return new FloatRange((float) $min, (float) $max);
	}

	public function floatRangeOr(string $key, FloatRange $default) : FloatRange{
		return $this->has($key) ? $this->floatRange($key) : $default;
	}

	public function objectNullable(string $key) : ?self{
		return $this->has($key) ? $this->object($key) : null;
	}

	public function object(string $key) : self{
		$value = $this->raw($key);
		is_array($value) || throw new InvalidArgumentException("'{$this->at($key)}' must be an object, got " . get_debug_type($value));
		return new self($value, $this->at($key));
	}

	/**
	 * @return list<self>
	 */
	public function objects(string $key) : array{
		if(!$this->has($key)){
			return [];
		}
		$value = $this->raw($key);
		is_array($value) && array_is_list($value) || throw new InvalidArgumentException("'{$this->at($key)}' must be an array, got " . get_debug_type($value));
		$result = [];
		foreach($value as $index => $entry){
			is_array($entry) || throw new InvalidArgumentException("'{$this->at($key)}[{$index}]' must be an object, got " . get_debug_type($entry));
			$result[] = new self($entry, "{$this->at($key)}[{$index}]");
		}
		return $result;
	}

	/**
	 * Returns the raw elements of a list paired with their path, for
	 * parsers accepting mixed element types (e.g. specific_enchants).
	 *
	 * @param string $key
	 * @return list<array{string, mixed}>
	 */
	public function values(string $key) : array{
		$value = $this->raw($key);
		is_array($value) && array_is_list($value) || throw new InvalidArgumentException("'{$this->at($key)}' must be an array, got " . get_debug_type($value));
		$result = [];
		foreach($value as $index => $entry){
			$result[] = ["{$this->at($key)}[{$index}]", $entry];
		}
		return $result;
	}

	/**
	 * @return list<string>
	 */
	public function strings(string $key) : array{
		if(!$this->has($key)){
			return [];
		}
		$value = $this->raw($key);
		if(is_string($value)){
			return [$value];
		}
		is_array($value) && array_is_list($value) || throw new InvalidArgumentException("'{$this->at($key)}' must be a string or an array of strings, got " . get_debug_type($value));
		foreach($value as $index => $entry){
			is_string($entry) || throw new InvalidArgumentException("'{$this->at($key)}[{$index}]' must be a string, got " . get_debug_type($entry));
		}
		return $value;
	}

	/**
	 * @return array{int|float, int|float}
	 */
	private function range(string $key) : array{
		$value = $this->raw($key);
		if(is_int($value) || is_float($value)){
			return [$value, $value];
		}
		is_array($value) || throw new InvalidArgumentException("'{$this->at($key)}' must be a number or a range, got " . get_debug_type($value));
		if(array_is_list($value)){
			count($value) === 2 || throw new InvalidArgumentException("'{$this->at($key)}' range tuple must have exactly 2 elements, got " . count($value));
			[$min, $max] = $value;
		}else{
			array_key_exists("min", $value) || throw new InvalidArgumentException("'{$this->at($key)}.min' directive not found");
			array_key_exists("max", $value) || throw new InvalidArgumentException("'{$this->at($key)}.max' directive not found");
			$min = $value["min"];
			$max = $value["max"];
		}
		is_int($min) || is_float($min) || throw new InvalidArgumentException("'{$this->at($key)}' minimum must be a number, got " . var_export($min, true));
		is_int($max) || is_float($max) || throw new InvalidArgumentException("'{$this->at($key)}' maximum must be a number, got " . var_export($max, true));
		$min <= $max || throw new InvalidArgumentException("'{$this->at($key)}' minimum ({$min}) must not exceed maximum ({$max})");
		return [$min, $max];
	}
}