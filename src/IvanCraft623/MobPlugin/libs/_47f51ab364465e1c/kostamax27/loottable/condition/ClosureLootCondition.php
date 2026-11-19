<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_47f51ab364465e1c\kostamax27\loottable\condition;

use Closure;
use IvanCraft623\MobPlugin\libs\_47f51ab364465e1c\kostamax27\loottable\LootContext;

final class ClosureLootCondition implements LootCondition{

	/**
	 * @param Closure(LootContext) : bool $closure
	 */
	public function __construct(
		readonly private Closure $closure
	){}

	public function test(LootContext $context) : bool{
		return ($this->closure)($context);
	}
}