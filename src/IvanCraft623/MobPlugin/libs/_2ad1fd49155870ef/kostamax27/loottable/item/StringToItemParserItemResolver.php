<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\item;

use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;

final class StringToItemParserItemResolver implements ItemResolver{

	private static self $instance;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function resolve(string $name, int $meta = 0) : ?Item{
		if($meta !== 0){
			return null;
		}
		$parser = StringToItemParser::getInstance();
		$item = $parser->parse($name);
		if($item !== null || !ItemNames::isVanilla($name)){
			return $item;
		}
		$name = ItemNames::stripVanillaNamespace($name);
		return $parser->parse($name) ?? $parser->parse(ItemNames::toSnakeCase($name));
	}
}