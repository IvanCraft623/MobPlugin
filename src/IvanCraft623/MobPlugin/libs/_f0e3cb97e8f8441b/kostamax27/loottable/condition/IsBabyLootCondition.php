<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\kostamax27\loottable\entity\EntityInspector;
use IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\kostamax27\loottable\LootContext;

final class IsBabyLootCondition implements LootCondition{

	public function __construct(
		readonly public EntityInspector $inspector
	){}

	public function test(LootContext $context) : bool{
		return $context->entity !== null && $this->inspector->isBaby($context->entity);
	}
}