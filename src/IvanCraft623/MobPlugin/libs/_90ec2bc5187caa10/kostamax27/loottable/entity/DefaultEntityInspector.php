<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_90ec2bc5187caa10\kostamax27\loottable\entity;

use pocketmine\block\utils\Colored;
use pocketmine\block\utils\DyeColor;
use pocketmine\entity\Ageable;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;

final class DefaultEntityInspector implements EntityInspector{

	public function __construct(){
	}

	public function type(Entity $entity) : string{
		$factory = EntityFactory::getInstance();
		return $factory->isRegistered($entity::class) ? $factory->getSaveId($entity::class) : $entity::getNetworkTypeId();
	}

	public function variant(Entity $entity) : ?int{
		return self::intProperty($entity, EntityMetadataProperties::VARIANT);
	}

	public function markVariant(Entity $entity) : ?int{
		return self::intProperty($entity, EntityMetadataProperties::MARK_VARIANT);
	}

	public function color(Entity $entity) : ?DyeColor{
		return $entity instanceof Colored ? $entity->getColor() : null;
	}

	public function isBaby(Entity $entity) : bool{
		return $entity instanceof Ageable && $entity->isBaby();
	}

	private static function intProperty(Entity $entity, int $key) : ?int{
		//:(
		$property = $entity->getNetworkProperties()->getAll()[$key] ?? null;
		return $property instanceof IntMetadataProperty ? $property->getValue() : null;
	}
}