<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\item;

use pocketmine\data\bedrock\item\ItemTypeDeserializeException;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\Item;
use pocketmine\world\format\io\GlobalItemDataHandlers;

final class SavedItemDataItemResolver implements ItemResolver{

	private static self $instance;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function resolve(string $name, int $meta = 0) : ?Item{
		try{
			$data = GlobalItemDataHandlers::getUpgrader()->upgradeItemTypeDataString(ItemNames::withVanillaNamespace($name), $meta, 1, null);
			return GlobalItemDataHandlers::getDeserializer()->deserializeType($data->getTypeData());
		}catch(SavedDataLoadingException | ItemTypeDeserializeException){
			return null;
		}
	}
}