<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_a6c4024b7fc66434\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_a6c4024b7fc66434\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}