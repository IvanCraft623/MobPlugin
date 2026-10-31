<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\item;

use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockStateSerializeException;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\item\Item;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

final class BlockRegistryItemResolver implements ItemResolver{

	private static self $instance;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	/** @var array<string, int>|null */
	private ?array $states = null;

	private function __construct(){
	}

	public function resolve(string $name, int $meta = 0) : ?Item{
		if($meta !== 0){
			return null;
		}
		$name = ItemNames::withVanillaNamespace($name);
		$states = $this->states ??= $this->index();
		$state_id = $states[BlockItemIdMap::getInstance()->lookupBlockId($name) ?? $name] ?? $states[$name] ?? null;
		return $state_id !== null ? RuntimeBlockStateRegistry::getInstance()->fromStateId($state_id)->asItem() : null;
	}

	public function invalidate() : void{
		$this->states = null;
	}

	/**
	 * @return array<string, int>
	 */
	private function index() : array{
		$serializer = GlobalBlockStateHandlers::getSerializer();
		$result = [];
		foreach(RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $state_id => $block){
			try{
				$name = $serializer->serializeBlock($block)->getName();
			}catch(BlockStateSerializeException){
				continue;
			}
			$result[$name] ??= $state_id;
		}
		return $result;
	}
}