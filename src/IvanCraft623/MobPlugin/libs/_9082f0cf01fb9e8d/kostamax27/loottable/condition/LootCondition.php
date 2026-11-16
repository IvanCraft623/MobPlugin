<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_9082f0cf01fb9e8d\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_9082f0cf01fb9e8d\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}