<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\condition;

use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootContext;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

enum EntityTarget : string{

	case THIS = "this";
	case KILLER = "killer";
	case DAMAGER = "damager"; // whatever dealt the last damage to THIS: the projectile if there was one, else the attacker

	public function select(LootContext $context) : ?Entity{
		return match($this){
			self::THIS => $context->entity,
			self::KILLER => $context->killer,
			self::DAMAGER => self::damager($context->entity)
		};
	}

	private static function damager(?Entity $entity) : ?Entity{
		$cause = $entity?->getLastDamageCause();
		return match(true){
			$cause instanceof EntityDamageByChildEntityEvent => $cause->getChild(),
			$cause instanceof EntityDamageByEntityEvent => $cause->getDamager(),
			default => null
		};
	}
}