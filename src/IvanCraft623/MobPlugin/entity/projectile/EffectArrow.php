<?php

/*
 *   __  __       _     _____  _             _
 *  |  \/  |     | |   |  __ \| |           (_)
 *  | \  / | ___ | |__ | |__) | |_   _  __ _ _ _ __
 *  | |\/| |/ _ \| '_ \|  ___/| | | | |/ _` | | '_ \
 *  | |  | | (_) | |_) | |    | | |_| | (_| | | | | |
 *  |_|  |_|\___/|_.__/|_|    |_|\__,_|\__, |_|_| |_|
 *                                      __/ |
 *                                     |___/
 *
 * A PocketMine-MP plugin that implements mobs AI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity\projectile;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\projectile\Arrow;
use pocketmine\math\RayTraceResult;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

/**
 * An arrow that applies effects to the living entity it hits, like a tipped arrow.
 *
 * Workaround until PocketMine-MP implements typed arrows.
 */
class EffectArrow extends Arrow implements NeverSavedWithChunkEntity{

	/**
	 * @param EffectInstance[] $effects
	 * @param int              $potionAuxValue what the client tints the arrow with
	 */
	public function __construct(
		Location $location,
		?Entity $shootingEntity,
		bool $critical,
		private readonly array $effects,
		private readonly int $potionAuxValue
	){
		parent::__construct($location, $shootingEntity, $critical);
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		parent::onHitEntity($entityHit, $hitResult);

		if($entityHit instanceof Living){
			foreach($this->effects as $effect){
				$entityHit->getEffects()->add(clone $effect);
			}
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setShort(EntityMetadataProperties::POTION_AUX_VALUE, $this->potionAuxValue);
	}

	public function canSaveWithChunk() : bool{
		return false;
	}
}
