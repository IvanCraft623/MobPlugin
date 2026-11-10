<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_46146a3065e781f1\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}