<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_b6c6bbd2999f1cae\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_b6c6bbd2999f1cae\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}