<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\entity\EntityInspector;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;

final class EntityTypeLootCondition implements LootCondition{

	public function __construct(
		readonly public EntityInspector $inspector,
		readonly public EntityTarget $target,
		readonly public string $entity_type
	){}

	public function test(LootContext $context) : bool{
		$entity = $this->target->select($context);
		return $entity !== null && $this->inspector->type($entity) === $this->entity_type;
	}
}