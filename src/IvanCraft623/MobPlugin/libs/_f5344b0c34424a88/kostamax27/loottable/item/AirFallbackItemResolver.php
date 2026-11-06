<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f5344b0c34424a88\kostamax27\loottable\item;

use Closure;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

/**
 * Resolves an item the server lacks to air, so its table still loads and drops nothing for it.
 * A missing variant (data other than 0) stays null.
 */
final class AirFallbackItemResolver implements ItemResolver{

	/**
	 * @param (Closure(string) : void)|null $on_missing called with the name of every item not found
	 */
	public function __construct(
		readonly public ItemResolver $inner,
		readonly private ?Closure $on_missing = null
	){}

	public function resolve(string $name, int $meta = 0) : ?Item{
		$item = $this->inner->resolve($name, $meta);
		if($item !== null || $meta !== 0){
			return $item;
		}
		if($this->on_missing !== null){
			($this->on_missing)($name);
		}
		return VanillaItems::AIR();
	}
}