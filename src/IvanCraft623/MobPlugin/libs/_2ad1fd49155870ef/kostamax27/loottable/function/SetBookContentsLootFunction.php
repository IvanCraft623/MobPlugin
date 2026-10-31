<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\item\Item;
use pocketmine\item\WritableBookBase;
use pocketmine\item\WritableBookPage;
use pocketmine\item\WrittenBook;
use function array_map;

final class SetBookContentsLootFunction implements LootFunction{

	/**
	 * @param list<string> $pages
	 * @param string|null $title applied to written books only
	 * @param string|null $author applied to written books only
	 */
	public function __construct(
		readonly public array $pages,
		readonly public ?string $title = null,
		readonly public ?string $author = null
	){}

	public function apply(Item $item, LootContext $context) : Item{
		if(!$item instanceof WritableBookBase){
			return $item;
		}
		$item->setPages(array_map(static fn(string $text) : WritableBookPage => new WritableBookPage($text), $this->pages));
		if($item instanceof WrittenBook){
			if($this->title !== null){
				$item->setTitle($this->title);
			}
			if($this->author !== null){
				$item->setAuthor($this->author);
			}
		}
		return $item;
	}
}