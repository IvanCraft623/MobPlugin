<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\looting;

use Closure;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use function max;

final class ClosureLootingEvaluator implements LootingEvaluator{

	/**
	 * @param Closure(LootContext) : int $closure negative results are clamped to 0
	 */
	public function __construct(
		readonly private Closure $closure
	){}

	public function evaluate(LootContext $context) : int{
		return max(0, ($this->closure)($context));
	}
}