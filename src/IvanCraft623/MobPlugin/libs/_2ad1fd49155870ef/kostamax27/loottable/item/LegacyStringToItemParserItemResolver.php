<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\item;

use pocketmine\item\Item;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\LegacyStringToItemParserException;

final class LegacyStringToItemParserItemResolver implements ItemResolver{

	private static self $instance;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function resolve(string $name, int $meta = 0) : ?Item{
		if(!ItemNames::isVanilla($name)){
			return null;
		}
		$name = ItemNames::toSnakeCase(ItemNames::stripVanillaNamespace($name));
		try{
			return LegacyStringToItemParser::getInstance()->parse("{$name}:{$meta}");
		}catch(LegacyStringToItemParserException){
			return null;
		}
	}
}