<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\item;

use function preg_replace;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

final class ItemNames{

	public const VANILLA_NAMESPACE = "minecraft:";

	private function __construct(){
	}

	public static function isVanilla(string $name) : bool{
		return !str_contains($name, ":") || str_starts_with($name, self::VANILLA_NAMESPACE);
	}

	public static function stripVanillaNamespace(string $name) : string{
		return str_starts_with($name, self::VANILLA_NAMESPACE) ? substr($name, strlen(self::VANILLA_NAMESPACE)) : $name;
	}

	public static function withVanillaNamespace(string $name) : string{
		return str_contains($name, ":") ? $name : self::VANILLA_NAMESPACE . $name;
	}

	public static function toSnakeCase(string $name) : string{
		return strtolower(preg_replace("/(?<=[a-z0-9])([A-Z])/", "_\$1", $name));
	}
}