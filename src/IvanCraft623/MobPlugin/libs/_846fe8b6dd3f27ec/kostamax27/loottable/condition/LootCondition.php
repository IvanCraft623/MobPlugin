<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_846fe8b6dd3f27ec\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}