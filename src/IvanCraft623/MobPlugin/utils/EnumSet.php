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

namespace IvanCraft623\MobPlugin\utils;

use pocketmine\utils\EnumTrait;

/**
 * @phpstan-template T of object
 * @phpstan-implements \IteratorAggregate<int, T>
 */
final class EnumSet implements \IteratorAggregate{

	/**
	 * @var bool[]
	 * @phpstan-var array<int, bool>
	 * enumId => isInSet
	 */
	private array $elements = [];

	/**
	 * @var array<int, T>
	 */
	private array $allElements = [];

	/**
	 * @phpstan-param class-string$enumClass
	 */
	public function __construct(private string $enumClass) {
		if (!isset(class_uses($enumClass)[EnumTrait::class])) {
			throw new \InvalidArgumentException("$enumClass doen't uses EnumTrait");
		}

		foreach ($enumClass::getAll() as $element) {
			$this->elements[$element->id()] = false;
			$this->allElements[$element->id()] = $element;
		}
	}

	/**
	 * @phpstan-param EnumTrait $elements
	 */
	public function add(object ...$elements) : void{
		foreach ($elements as $element) {
			if (!$element instanceof $this->enumClass) {
				throw new \InvalidArgumentException("Element must be an instance of $this->enumClass");
			}

			$this->elements[$element->id()] = true;
		}
	}

	/**
	 * @phpstan-param EnumTrait $elements
	 */
	public function remove(object ...$elements) : void{
		foreach ($elements as $element) {
			if (!$element instanceof $this->enumClass) {
				throw new \InvalidArgumentException("Element must be an instance of $this->enumClass");
			}

			$this->elements[$element->id()] = false;
		}
	}

	public function clear() : void{
		foreach ($this->elements as $key => $element) {
			$this->elements[$key] = false;
		}
	}

	/**
	 * @phpstan-param EnumTrait $elements
	 */
	public function contains(object $element) : bool{
		return $this->elements[$element->id()] ?? false;
	}

	/** @phpstan-return \ArrayIterator<int, T> */
	public function getIterator() : \ArrayIterator{
		return new \ArrayIterator($this->toArray());
	}

	/**
	 * @phpstan-return array<int, T>
	 */
	public function toArray() : array{
		$elements = [];
		foreach ($this->elements as $elementId => $inSet) {
			if ($inSet) {
				$elements[$elementId] = $this->allElements[$elementId];
			}
		}

		return $elements;
	}
}