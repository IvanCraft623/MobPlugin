<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_47f51ab364465e1c\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_47f51ab364465e1c\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}