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

use pocketmine\item\Item;

/**
 * @phpstan-implements \IteratorAggregate<int, Item>
 */
final class ItemSet implements \IteratorAggregate{

	/** @var array<int, Item> */
	private array $elements = [];

	/**
	 * @return $this
	 */
	public function add(Item ...$elements) : self{
		foreach ($elements as $element) {
			$this->elements[$element->getId()] = $element;
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function remove(Item ...$elements) : self{
		foreach ($elements as $element) {
			unset($this->elements[$element->getId()]);
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function clear() : self{
		$this->elements = [];

		return $this;
	}

	public function contains(Item $element, bool $compareEquals = false) : bool{
		$id = $element->getId();
		return isset($this->elements[$id]) && (!$compareEquals || $this->elements[$id]->equals($element));
	}

	/** @phpstan-return \ArrayIterator<int, Item> */
	public function getIterator() : \ArrayIterator{
		return new \ArrayIterator($this->toArray());
	}

	/**
	 * @phpstan-return array<int, Item>
	 */
	public function toArray() : array{
		return $this->elements;
	}
}
