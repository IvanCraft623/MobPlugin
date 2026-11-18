<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_933cfa175d40b474\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_933cfa175d40b474\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}