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

namespace IvanCraft623\MobPlugin\entity\aquatic;

use IvanCraft623\MobPlugin\data\bedrock\TropicalFishPatternIdMap;
use IvanCraft623\MobPlugin\data\bedrock\TropicalFishShapeIdMap;
use IvanCraft623\MobPlugin\data\bedrock\VanillaEntitySizes;
use IvanCraft623\MobPlugin\entity\Flocking;
use IvanCraft623\MobPlugin\entity\FlockingParameters;
use IvanCraft623\MobPlugin\item\ExtraVanillaItems;
use IvanCraft623\MobPlugin\item\MobBucket;
use IvanCraft623\MobPlugin\loot\VanillaLootTables;

use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use function array_filter;
use function array_rand;

class TropicalFish extends AbstractFish {

	private const SCALE = 1.3;

	private const TAG_SHAPE = "Variant"; //TAG_Int
	private const TAG_PATTERN = "MarkVariant"; //TAG_Int
	private const TAG_BASE_COLOR = "Color"; //TAG_Byte
	private const TAG_PATTERN_COLOR = "Color2"; //TAG_Byte

	protected TropicalFishShape $shape;

	protected TropicalFishPattern $pattern;

	protected DyeColor $baseColor;

	protected DyeColor $patternColor;

	private static ?FlockingParameters $flockingParameters = null;

	public static function getNetworkTypeId() : string{ return EntityIds::TROPICALFISH; }

	/**
	 * A random color, never black.
	 */
	private static function getRandomColor() : DyeColor{
		$colors = array_filter(DyeColor::getAll(), static fn(DyeColor $color) : bool => $color !== DyeColor::BLACK());

		return $colors[array_rand($colors)];
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(VanillaEntitySizes::TROPICALFISH_HEIGHT, VanillaEntitySizes::TROPICALFISH_WIDTH);
	}

	public function getName() : string{
		return "Tropical Fish";
	}

	protected function getAvoidDistance() : float{
		return 6;
	}

	protected function initProperties() : void{
		parent::initProperties();

		$this->setScale(self::SCALE);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$colorMap = DyeColorIdMap::getInstance();
		$shapes = TropicalFishShape::cases();
		$this->shape = TropicalFishShapeIdMap::getInstance()->fromId($nbt->getInt(self::TAG_SHAPE, -1)) ?? $shapes[array_rand($shapes)];
		$patterns = TropicalFishPattern::cases();
		$this->pattern = TropicalFishPatternIdMap::getInstance()->fromId($nbt->getInt(self::TAG_PATTERN, -1)) ?? $patterns[array_rand($patterns)];
		$this->baseColor = $colorMap->fromId($nbt->getByte(self::TAG_BASE_COLOR, -1)) ?? self::getRandomColor();
		$this->patternColor = $colorMap->fromId($nbt->getByte(self::TAG_PATTERN_COLOR, -1)) ?? self::getRandomColor();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		$colorMap = DyeColorIdMap::getInstance();
		$nbt->setInt(self::TAG_SHAPE, TropicalFishShapeIdMap::getInstance()->toId($this->shape));
		$nbt->setInt(self::TAG_PATTERN, TropicalFishPatternIdMap::getInstance()->toId($this->pattern));
		$nbt->setByte(self::TAG_BASE_COLOR, $colorMap->toId($this->baseColor));
		$nbt->setByte(self::TAG_PATTERN_COLOR, $colorMap->toId($this->patternColor));

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$colorMap = DyeColorIdMap::getInstance();
		$properties->setInt(EntityMetadataProperties::VARIANT, TropicalFishShapeIdMap::getInstance()->toId($this->shape));
		$properties->setInt(EntityMetadataProperties::MARK_VARIANT, TropicalFishPatternIdMap::getInstance()->toId($this->pattern));
		$properties->setByte(EntityMetadataProperties::COLOR, $colorMap->toId($this->baseColor));
		$properties->setByte(EntityMetadataProperties::COLOR_2, $colorMap->toId($this->patternColor));
	}

	public function getShape() : TropicalFishShape{
		return $this->shape;
	}

	public function getPattern() : TropicalFishPattern{
		return $this->pattern;
	}

	public function getBaseColor() : DyeColor{
		return $this->baseColor;
	}

	public function getPatternColor() : DyeColor{
		return $this->patternColor;
	}

	/**
	 * @return $this
	 */
	public function setVariant(TropicalFishShape $shape, TropicalFishPattern $pattern, DyeColor $baseColor, DyeColor $patternColor) : self{
		$this->shape = $shape;
		$this->pattern = $pattern;
		$this->baseColor = $baseColor;
		$this->patternColor = $patternColor;
		$this->networkPropertiesDirty = true;

		return $this;
	}

	public function getFlockingParameters() : FlockingParameters{
		return self::$flockingParameters ??= new FlockingParameters(
			influenceRadius: 3,
			speed: 0.05,
			separationWeight: 0.65,
			separationThreshold: 0.15,
			cohesionWeight: 2.75,
			innerCohesionThreshold: 1.5,
			goalWeight: 2,
			blockWeight: 0.85,
			blockDistance: 2,
			staysBelowSurface: true,
			useCenterOfMass: false,
			lowFlockLimit: 4,
			highFlockLimit: 8,
			lonerChance: 0.1
		);
	}

	/**
	 * Tropical fish only flock with their exact look.
	 */
	public function canFlockWith(Flocking $other) : bool{
		return $other instanceof TropicalFish &&
			$other->shape === $this->shape &&
			$other->pattern === $this->pattern &&
			$other->baseColor === $this->baseColor &&
			$other->patternColor === $this->patternColor;
	}

	public function getDrops() : array{
		return $this->generateDeathLoot(VanillaLootTables::TROPICALFISH());
	}

	public function getBucketItem() : MobBucket{
		return ExtraVanillaItems::TROPICAL_FISH_BUCKET();
	}

	public function getPickedItem() : ?Item{
		return ExtraVanillaItems::TROPICAL_FISH_SPAWN_EGG();
	}
}