<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable;

use InvalidArgumentException;
use pocketmine\utils\Random;
use function abs;

final class FloatRange{

	public static function exact(float $value) : self{
		return new self($value, $value);
	}

	public function __construct(
		readonly public float $min,
		readonly public float $max
	){
		$this->min <= $this->max || throw new InvalidArgumentException("Range minimum ({$this->min}) must not exceed maximum ({$this->max})");
	}

	public function sample(Random $random) : float{
		return $this->min + ($this->max - $this->min) * $random->nextFloat();
	}

	public function equals(self $other) : bool{
		return abs($this->min - $other->min) < 0.0001 && abs($this->max - $other->max) < 0.0001;
	}
}