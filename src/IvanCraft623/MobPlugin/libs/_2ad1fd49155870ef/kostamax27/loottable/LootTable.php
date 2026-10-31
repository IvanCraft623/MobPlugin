<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\pool\LootPool;
use pocketmine\item\Item;
use function array_push;

final class LootTable{

	public static function empty() : self{
		return new self([]);
	}

	/**
	 * @param list<LootPool> $pools
	 */
	public function __construct(
		readonly public array $pools
	){}

	/**
	 * Rolls every pool and returns the combined yield. Counts beyond an
	 * item's stack size are split into multiple stacks.
	 *
	 * @return list<Item>
	 */
	public function generate(LootContext $context) : array{
		$result = [];
		foreach($this->pools as $pool){
			array_push($result, ...$pool->generate($context));
		}
		return $result;
	}
}