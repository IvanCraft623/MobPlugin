<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\kostamax27\loottable\LootContext;

interface LootCondition{

	public function test(LootContext $context) : bool;
}