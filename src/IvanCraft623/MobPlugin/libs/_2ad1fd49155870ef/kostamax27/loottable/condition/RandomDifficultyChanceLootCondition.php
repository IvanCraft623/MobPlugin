<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\condition;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootDifficulty;

final class RandomDifficultyChanceLootCondition implements LootCondition{

	public function __construct(
		readonly public float $default_chance,
		readonly public ?float $peaceful = null,
		readonly public ?float $easy = null,
		readonly public ?float $normal = null,
		readonly public ?float $hard = null
	){
		foreach(["default_chance" => $this->default_chance, "peaceful" => $this->peaceful, "easy" => $this->easy, "normal" => $this->normal, "hard" => $this->hard] as $key => $chance){
			$chance === null || ($chance >= 0.0 && $chance <= 1.0) || throw new InvalidArgumentException("'{$key}' must be within [0, 1], got {$chance}");
		}
	}

	public function chanceFor(LootDifficulty $difficulty) : float{
		return match($difficulty){
			LootDifficulty::PEACEFUL => $this->peaceful,
			LootDifficulty::EASY => $this->easy,
			LootDifficulty::NORMAL => $this->normal,
			LootDifficulty::HARD => $this->hard
		} ?? $this->default_chance;
	}

	public function test(LootContext $context) : bool{
		return $context->random->nextFloat() < $this->chanceFor($context->difficulty);
	}
}