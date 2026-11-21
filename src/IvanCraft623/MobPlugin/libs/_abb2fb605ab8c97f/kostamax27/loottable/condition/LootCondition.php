<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_abb2fb605ab8c97f\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_abb2fb605ab8c97f\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}