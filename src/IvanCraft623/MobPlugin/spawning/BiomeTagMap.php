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
use pocketmine\plugin\PluginException;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use function array_merge;
use function array_unique;
use function array_values;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

/**
 * Maps Bedrock biome ids to their vanilla biome tags, loaded lazily from PocketMine's
 * biome_definitions.json. Unknown biome ids resolve to an empty tag set.
 */
final class BiomeTagMap{
	use SingletonTrait;

	/** @var array<int, list<string>> @phpstan-var array<int, list<string>> */
	private array $tagsByBiomeId;

	private function __construct(){
		$idMap = json_decode(Filesystem::fileGetContents(BedrockDataFiles::BIOME_ID_MAP_JSON), true);
		$definitions = json_decode(Filesystem::fileGetContents(BedrockDataFiles::BIOME_DEFINITIONS_JSON), true);
		if(!is_array($idMap) || !is_array($definitions)){
			throw new PluginException("bedrock-data biome definitions are missing or corrupted");
		}

		$map = [];
		foreach(Utils::promoteKeys($idMap) as $name => $id){
			if(!is_string($name) || !is_int($id)){
				continue;
			}
			$definition = $definitions["minecraft:" . $name] ?? null;
			$tags = [];
			if(is_array($definition) && is_array($definition["tags"] ?? null)){
				foreach($definition["tags"] as $tag){
					if(is_string($tag) && $tag !== ""){
						$tags[] = $tag;
					}
				}
			}
			// Multiple biome names can share one id; union their tags.
			if(isset($map[$id]) && is_array($map[$id])){
				$map[$id] = array_values(array_unique(array_merge($map[$id], $tags)));
			}else{
				$map[$id] = $tags;
			}
		}
		$this->tagsByBiomeId = $map;
	}

	/**
	 * @phpstan-return list<string>
	 */
	public function getTags(int $biomeId) : array{
		return $this->tagsByBiomeId[$biomeId] ?? [];
	}
}
