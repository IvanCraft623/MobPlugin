<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\utils\Random;

final class LootContext{

	public static function create(?Random $random = null) : self{
		return new self($random ?? new Random());
	}

	/**
	 * @param Entity|null $entity entity being looted, or null when looting a block or container
	 * @param Entity|null $killer entity that caused the drop, or null when there is none
	 * @param Item|null $tool item used to kill $entity or break the block, or null when there is none
	 * @param float $luck scales bonus rolls and entry quality
	 * @param LootDifficulty $difficulty difficulty of the world the drop occurs in
	 */
	public function __construct(
		readonly public Random $random,
		readonly public ?Entity $entity = null,
		readonly public ?Entity $killer = null,
		readonly public ?Item $tool = null,
		readonly public float $luck = 0.0,
		readonly public LootDifficulty $difficulty = LootDifficulty::NORMAL
	){}
}