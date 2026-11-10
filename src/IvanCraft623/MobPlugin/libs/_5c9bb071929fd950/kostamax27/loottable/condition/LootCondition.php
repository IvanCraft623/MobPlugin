<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_5c9bb071929fd950\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}