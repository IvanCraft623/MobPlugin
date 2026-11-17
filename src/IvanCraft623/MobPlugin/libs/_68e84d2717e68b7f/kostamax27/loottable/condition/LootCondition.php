<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_68e84d2717e68b7f\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_68e84d2717e68b7f\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}