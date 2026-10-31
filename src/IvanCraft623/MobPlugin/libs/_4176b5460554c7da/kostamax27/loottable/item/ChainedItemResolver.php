<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\item;

use pocketmine\item\Item;

final class ChainedItemResolver implements ItemResolver{

	public static function createDefault() : self{
		return new self(
			SavedItemDataItemResolver::instance(),
			BlockRegistryItemResolver::instance(),
			StringToItemParserItemResolver::instance(),
			LegacyStringToItemParserItemResolver::instance()
		);
	}

	/** @var list<ItemResolver> */
	readonly public array $resolvers;

	public function __construct(ItemResolver ...$resolvers){
		$this->resolvers = $resolvers;
	}

	public function resolve(string $name, int $meta = 0) : ?Item{
		foreach($this->resolvers as $resolver){
			$item = $resolver->resolve($name, $meta);
			if($item !== null){
				return $item;
			}
		}
		return null;
	}
}