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

namespace IvanCraft623\MobPlugin\spawning;

use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

/**
 * The tags of every biome, which biome filters test.
 */
final class BiomeTagMap{
	use SingletonTrait;

	public static function setInstance(self $instance) : void{
		self::$instance = $instance;
		SpawnRuleRegistry::getInstance()->invalidateCache();
	}

	public static function reset() : void{
		self::$instance = null;
		SpawnRuleRegistry::getInstance()->invalidateCache();
	}

	/** @phpstan-var array<int, array<string, true>> biome id => set of tags */
	private array $tagsByBiomeId;

	/**
	 * @phpstan-param array<int, array<string, true>>|null $tagsByBiomeId biome id => set of tags; null for the bundled Bedrock data
	 */
	public function __construct(?array $tagsByBiomeId = null){
		$this->tagsByBiomeId = $tagsByBiomeId ?? self::readBedrockData();
	}

	/**
	 * @phpstan-return array<int, array<string, true>>
	 */
	private static function readBedrockData() : array{
		$idMap = json_decode(Filesystem::fileGetContents(BedrockDataFiles::BIOME_ID_MAP_JSON), true);
		$definitions = json_decode(Filesystem::fileGetContents(BedrockDataFiles::BIOME_DEFINITIONS_JSON), true);
		if(!is_array($idMap) || !is_array($definitions)){
			throw new AssumptionFailedError("bedrock-data biome definitions are missing or corrupted");
		}

		$map = [];
		foreach(Utils::promoteKeys($idMap) as $name => $id){
			if(!is_string($name) || !is_int($id)){
				continue;
			}
			$definition = $definitions["minecraft:" . $name] ?? null;
			// Multiple biome names can share one id; union their tags.
			$map[$id] ??= [];
			if(is_array($definition) && is_array($definition["tags"] ?? null)){
				foreach($definition["tags"] as $tag){
					if(is_string($tag) && $tag !== ""){
						$map[$id][$tag] = true;
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Tags a biome, for custom biomes or custom tags on vanilla ones.
	 */
	public function addTag(int $biomeId, string $tag) : void{
		$this->tagsByBiomeId[$biomeId][$tag] = true;

		// Biome tests are decided once per cache key.
		SpawnRuleRegistry::getInstance()->invalidateCache();
	}

	public function removeTag(int $biomeId, string $tag) : void{
		unset($this->tagsByBiomeId[$biomeId][$tag]);

		SpawnRuleRegistry::getInstance()->invalidateCache();
	}

	public function hasTag(int $biomeId, string $tag) : bool{
		return isset($this->tagsByBiomeId[$biomeId][$tag]);
	}

	/**
	 * Whether any biome carries the tag; a test on a tag none has can never match.
	 */
	public function isKnownTag(string $tag) : bool{
		foreach($this->tagsByBiomeId as $tags){
			if(isset($tags[$tag])){
				return true;
			}
		}

		return false;
	}
}