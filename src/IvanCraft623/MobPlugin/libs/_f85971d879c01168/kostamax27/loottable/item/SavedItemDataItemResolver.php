<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f85971d879c01168\kostamax27\loottable\item;

use pocketmine\data\bedrock\item\ItemTypeDeserializeException;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
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
			// loads it like a saved item, which is what gives block items the state of their data
			$data = GlobalItemDataHandlers::getUpgrader()->upgradeItemStackNbt(CompoundTag::create()
				->setString(SavedItemData::TAG_NAME, ItemNames::withVanillaNamespace($name))
				->setShort(SavedItemData::TAG_DAMAGE, $meta)
				->setByte(SavedItemStackData::TAG_COUNT, 1)
			);
			return $data === null ? null : GlobalItemDataHandlers::getDeserializer()->deserializeType($data->getTypeData());
		}catch(SavedDataLoadingException | ItemTypeDeserializeException){
			return null;
		}
	}
}