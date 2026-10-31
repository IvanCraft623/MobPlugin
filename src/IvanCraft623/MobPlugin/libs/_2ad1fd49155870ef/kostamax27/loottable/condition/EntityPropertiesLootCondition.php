<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;

final class EntityPropertiesLootCondition implements LootCondition{

	public function __construct(
		readonly public EntityTarget $target,
		readonly public ?bool $on_fire = null
	){}

	public function test(LootContext $context) : bool{
		$entity = $this->target->select($context);
		if($entity === null){
			return false;
		}
		return $this->on_fire === null || $entity->isOnFire() === $this->on_fire;
	}
}