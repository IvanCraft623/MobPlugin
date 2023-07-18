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

namespace IvanCraft623\MobPlugin\entity;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use function substr;

final class ComponentGroups{

	private const ENABLED_CHARACTER = "+";
	private const DISABLED_CHARACTER = "+";

	public static function fromListTag(ListTag $tag) : ComponentGroups{
		$componentGroupList = new ComponentGroups();

		/** @var StringTag $componentGroupTag */
		foreach($tag as $i => $componentGroupTag){
			$data = $componentGroupTag->getValue();

			$enabled = substr($data, 0, 1) === self::ENABLED_CHARACTER;
			$componentGroup = substr($data, 1);

			$componentGroupList->set($componentGroup, $enabled);
		}

		return $componentGroupList;
	}

	/**
	 * @var bool[]
	 * @phpstan-var array<string, bool>
	 */
	private array $componentGroups = [];

	public function add(string $componentGroup) : void{
		$this->set($componentGroup, true);
	}

	public function remove(string $componentGroup) : void{
		$this->set($componentGroup, false);
	}

	public function set(string $componentGroup, bool $enabled) : void{
		$this->componentGroups[$componentGroup] = $enabled;
	}

	public function unset(string $componentGroup) : void{
		unset($this->componentGroups[$componentGroup]);
	}

	public function isset(string $componentGroup) : bool{
		return isset($this->componentGroups[$componentGroup]);
	}

	public function has(string $componentGroup) : bool{
		return $this->componentGroups[$componentGroup] ?? false;
	}

	/**
	 * @return bool[]
	 * @phpstan-return array<string, bool>
	 */
	public function getAll() : array{
		return $this->componentGroups;
	}

	/**
	 * @return bool[]
	 * @phpstan-return array<string, true>
	 */
	public function getEnabledComponents() : array{
		$componentGroups = [];
		foreach ($this->componentGroups as $componentGroup => $enabled) {
			if ($enabled) {
				$componentGroups[$componentGroup] = $enabled;
			}
		}

		return $componentGroups;
	}

	public function toListTag() : ListTag{
		$tag = new ListTag([], NBT::TAG_String);
		foreach ($this->componentGroups as $componentGroup => $enabled) {
			$tag->push(new StringTag(($enabled ? self::ENABLED_CHARACTER : self::DISABLED_CHARACTER) . $componentGroup));
		}

		return $tag;
	}
}
