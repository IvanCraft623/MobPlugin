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

namespace IvanCraft623\MobPlugin\item;

use IvanCraft623\MobPlugin\entity\Bucketable;
use IvanCraft623\MobPlugin\event\MobSpawnCause;
use IvanCraft623\MobPlugin\event\MobSpawnEvent;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\item\LiquidBucket;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use pocketmine\world\World;

/**
 * A water bucket that may carry an entity.
 */
class MobBucket extends LiquidBucket{

	/**
	 * @phpstan-param null|class-string<Entity&Bucketable> $entityClass the entity a bucket without saved data releases, null while it doesn't exist
	 */
	public function __construct(
		ItemIdentifier $identifier,
		string $name,
		private readonly ?string $entityClass = null
	){
		parent::__construct($identifier, $name, VanillaBlocks::WATER());
	}

	/**
	 * Stores the save tag of the carried entity.
	 */
	public function setEntityData(CompoundTag $saveData) : self{
		$this->setNamedTag($saveData);

		return $this;
	}

	/**
	 * Null if the bucket has no entity to release, or the saved one isn't known.
	 */
	protected function createEntity(World $world, Vector3 $pos) : ?Entity{
		if($this->entityClass === null){
			return null;
		}

		$nbt = clone $this->getNamedTag();
		$factory = EntityFactory::getInstance();
		if($nbt->getTag(EntityFactory::TAG_IDENTIFIER) === null){
			$factory->injectSaveId($this->entityClass, $nbt);
		}
		//HACK: the factory only takes a position from the tag, there is no way to pass a location
		$nbt->setTag(Entity::TAG_POS, new ListTag([new DoubleTag($pos->x), new DoubleTag($pos->y), new DoubleTag($pos->z)]));
		if($nbt->getTag(Entity::TAG_ROTATION) === null){
			$nbt->setTag(Entity::TAG_ROTATION, new ListTag([new FloatTag(0), new FloatTag(0)]));
		}

		return $factory->createFromData($world, $nbt);
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		$result = parent::onInteractBlock($player, $blockReplace, $blockClicked, $face, $clickVector, $returnedItems);
		if($result === ItemUseResult::SUCCESS){
			$position = $blockReplace->getPosition();
			$entity = $this->createEntity($position->getWorld(), $position->add(0.5, 0, 0.5));
			if($entity !== null){
				(new MobSpawnEvent($entity, MobSpawnCause::BUCKET))->call();
				$entity->spawnToAll();
			}
		}

		return $result;
	}
}
