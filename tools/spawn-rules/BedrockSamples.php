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

namespace IvanCraft623\MobPlugin\tools\spawnrules;

use Composer\InstalledVersions;
use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use function file_get_contents;
use function json_decode;
use function realpath;
use function sort;
use function strlen;
use function strtolower;
use const JSON_THROW_ON_ERROR;
use const SORT_STRING;

/**
 * The pinned mojang/bedrock-samples dev dependency. composer.json is the only place its
 * commit and spawn schema version (the package version) are declared.
 */
final class BedrockSamples{
	public const PACKAGE = "mojang/bedrock-samples";

	public const SPAWN_RULES_PATH = "behavior_pack/spawn_rules";

	public static function getSchemaVersion() : string{
		return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? throw new \RuntimeException(self::PACKAGE . " has no version; run composer install");
	}

	public static function getReference() : ?string{
		return InstalledVersions::getReference(self::PACKAGE);
	}

	public static function getInstallPath() : string{
		$path = InstalledVersions::getInstallPath(self::PACKAGE);
		$resolved = $path !== null ? realpath($path) : false;

		return $resolved !== false ? $resolved : throw new \RuntimeException(self::PACKAGE . " is not installed; run composer install");
	}

	/**
	 * Every spawn rule file of the samples, in a stable order.
	 *
	 * @phpstan-return list<string>
	 */
	public static function listSpawnRuleFiles() : array{
		$files = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::getInstallPath() . "/" . self::SPAWN_RULES_PATH, FilesystemIterator::SKIP_DOTS));
		/** @var \SplFileInfo $fileInfo */
		foreach($iterator as $fileInfo){
			if($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === "json"){
				$files[] = $fileInfo->getPathname();
			}
		}
		sort($files, SORT_STRING);

		return $files;
	}

	/**
	 * The "minecraft:spawn_rules" object of a rule file, with the whole decoded file.
	 * Objects (not assoc arrays) are decoded on purpose: an empty JSON object must stay
	 * {} instead of becoming [].
	 *
	 * @phpstan-return array{stdClass, stdClass} the spawn rules and the file's root
	 */
	public static function readSpawnRuleFile(string $file) : array{
		$raw = file_get_contents($file);
		if($raw === false){
			throw new \RuntimeException("Cannot read file: $file");
		}
		try{
			$decoded = json_decode(self::stripJsonComments($raw), false, 512, JSON_THROW_ON_ERROR);
		}catch(JsonException $e){
			throw new \RuntimeException("Invalid JSON in \"$file\": " . $e->getMessage());
		}
		if(!$decoded instanceof stdClass){
			throw new \RuntimeException("Root of \"$file\" should be a JSON object.");
		}
		$spawnRules = $decoded->{"minecraft:spawn_rules"} ?? null;
		if(!$spawnRules instanceof stdClass){
			throw new \RuntimeException("Missing or invalid \"minecraft:spawn_rules\" object in \"$file\".");
		}

		return [$spawnRules, $decoded];
	}

	/**
	 * Strips // and slash-star comments from a JSON document, ignoring comment markers
	 * inside strings. Vanilla spawn rule files contain comments and so aren't strict JSON.
	 */
	public static function stripJsonComments(string $json) : string{
		$out = "";
		$length = strlen($json);
		$i = 0;
		$inString = false;
		$escaped = false;
		while($i < $length){
			$char = $json[$i];
			if($inString){
				$out .= $char;
				if($escaped){
					$escaped = false;
				}elseif($char === "\\"){
					$escaped = true;
				}elseif($char === "\""){
					$inString = false;
				}
				$i++;
			}else{
				if($char === "\""){
					$inString = true;
					$out .= $char;
					$i++;
				}elseif($char === "/" && $i + 1 < $length && $json[$i + 1] === "/"){
					while($i < $length && $json[$i] !== "\n"){
						$i++;
					}
				}elseif($char === "/" && $i + 1 < $length && $json[$i + 1] === "*"){
					$i += 2;
					while($i + 1 < $length && !($json[$i] === "*" && $json[$i + 1] === "/")){
						$i++;
					}
					$i += 2;
				}else{
					$out .= $char;
					$i++;
				}
			}
		}

		return $out;
	}
}
