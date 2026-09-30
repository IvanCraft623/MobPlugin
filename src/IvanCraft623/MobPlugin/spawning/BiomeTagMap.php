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
use pocketmine\utils\Utils;
use function array_merge;
use function array_unique;
use function array_values;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

final class BiomeTagMap{
	/**
	 * @phpstan-param array<int, list<string>> $tagsByBiomeId
	 */
	public function __construct(
		private readonly array $tagsByBiomeId
	){}

	public static function fromBedrockData() : self{
		return self::fromFiles(BedrockDataFiles::BIOME_ID_MAP_JSON, BedrockDataFiles::BIOME_DEFINITIONS_JSON);
	}

	public static function fromFiles(string $idMapPath, string $definitionsPath) : self{
		$idMap = json_decode(Filesystem::fileGetContents($idMapPath), true);
		$definitions = json_decode(Filesystem::fileGetContents($definitionsPath), true);
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
			$map[$id] = isset($map[$id]) ? array_values(array_unique(array_merge($map[$id], $tags))) : $tags;
		}

		return new self($map);
	}

	/**
	 * @phpstan-return list<string>
	 */
	public function getTags(int $biomeId) : array{
		return $this->tagsByBiomeId[$biomeId] ?? [];
	}
}
