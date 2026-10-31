<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\entity;

use pocketmine\entity\Entity;

interface EntityInspector{

	/**
	 * Returns the namespaced type id compared against "entity_type" in
	 * killed_by_entity, entity_killed and damaged_by_entity.
	 *
	 * @return string e.g. "minecraft:skeleton"
	 */
	public function type(Entity $entity) : string;

	/**
	 * @return int|null bedrock "variant" value, or null when the entity has none
	 */
	public function variant(Entity $entity) : ?int;

	/**
	 * @return int|null bedrock "mark_variant" value, or null when the entity has none
	 */
	public function markVariant(Entity $entity) : ?int;

	public function isBaby(Entity $entity) : bool;
}