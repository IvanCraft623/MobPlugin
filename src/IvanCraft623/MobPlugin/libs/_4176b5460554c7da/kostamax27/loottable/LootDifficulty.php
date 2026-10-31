<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable;

use InvalidArgumentException;
use pocketmine\world\World;

enum LootDifficulty : int{

	case PEACEFUL = World::DIFFICULTY_PEACEFUL;
	case EASY = World::DIFFICULTY_EASY;
	case NORMAL = World::DIFFICULTY_NORMAL;
	case HARD = World::DIFFICULTY_HARD;

	public static function fromWorld(World $world) : self{
		return self::tryFrom($world->getDifficulty()) ?? throw new InvalidArgumentException("Unknown world difficulty {$world->getDifficulty()}");
	}
}