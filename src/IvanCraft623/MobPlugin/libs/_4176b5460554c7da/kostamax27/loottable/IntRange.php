<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable;

use InvalidArgumentException;
use pocketmine\utils\Random;

final class IntRange{

	public static function exact(int $value) : self{
		return new self($value, $value);
	}

	public function __construct(
		readonly public int $min,
		readonly public int $max
	){
		$this->min <= $this->max || throw new InvalidArgumentException("Range minimum ({$this->min}) must not exceed maximum ({$this->max})");
	}

	public function sample(Random $random) : int{
		return $this->min === $this->max ? $this->min : $random->nextRange($this->min, $this->max);
	}

	public function equals(self $other) : bool{
		return $this->min === $other->min && $this->max === $other->max;
	}
}