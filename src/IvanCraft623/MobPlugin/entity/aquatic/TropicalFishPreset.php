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

use pocketmine\block\utils\DyeColor;
use function spl_object_id;

/**
 * @phpstan-type TData array{0: TropicalFishShape, 1: TropicalFishPattern, 2: DyeColor, 3: DyeColor}
 */
enum TropicalFishPreset{
	case ANEMONE;
	case CLOWNFISH;
	case BLACK_TANG;
	case CC_BETTA;
	case DOG_FISH;
	case BLUE_DORY;
	case BUTTERFLY_FISH;
	case CICHLID;
	case E_RED_SNAPPER;
	case GOAT_FISH;
	case MOORISH_IDOL;
	case ORNATE_BUTTERFLY;
	case RED_SNAPPER;
	case PARROT_FISH;
	case QUEEN_ANGEL_FISH;
	case RED_CICHLID;
	case RED_LIPPED_BENNY;
	case THREADFIN;
	case YELLOW_TANG;
	case TOMATO_CLOWN;
	case TRIGGERFISH;
	case YELLOW_TAIL_PARROT;

	/**
	 * Shape, pattern, base color and pattern color.
	 *
	 * @phpstan-return TData
	 */
	private function getData() : array{
		/** @phpstan-var array<int, TData> $cache */
		static $cache = [];

		return $cache[spl_object_id($this)] ??= match($this){
			self::ANEMONE => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_1, DyeColor::ORANGE(), DyeColor::GRAY()],
			self::CLOWNFISH => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_0, DyeColor::ORANGE(), DyeColor::WHITE()],
			self::BLACK_TANG => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_0, DyeColor::GRAY(), DyeColor::GRAY()],
			self::CC_BETTA => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_5, DyeColor::PINK(), DyeColor::LIGHT_BLUE()],
			self::DOG_FISH => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_3, DyeColor::PURPLE(), DyeColor::YELLOW()],
			self::BLUE_DORY => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_1, DyeColor::GRAY(), DyeColor::LIGHT_BLUE()],
			self::BUTTERFLY_FISH => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_4, DyeColor::WHITE(), DyeColor::GRAY()],
			self::CICHLID => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_1, DyeColor::BLUE(), DyeColor::GRAY()],
			self::E_RED_SNAPPER => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_5, DyeColor::WHITE(), DyeColor::RED()],
			self::GOAT_FISH => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_5, DyeColor::WHITE(), DyeColor::YELLOW()],
			self::MOORISH_IDOL => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_2, DyeColor::WHITE(), DyeColor::GRAY()],
			self::ORNATE_BUTTERFLY => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_5, DyeColor::WHITE(), DyeColor::ORANGE()],
			self::RED_SNAPPER => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_3, DyeColor::RED(), DyeColor::WHITE()],
			self::PARROT_FISH => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_3, DyeColor::CYAN(), DyeColor::PINK()],
			self::QUEEN_ANGEL_FISH => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_4, DyeColor::LIME(), DyeColor::LIGHT_BLUE()],
			self::RED_CICHLID => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_4, DyeColor::RED(), DyeColor::WHITE()],
			self::RED_LIPPED_BENNY => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_2, DyeColor::GRAY(), DyeColor::RED()],
			self::THREADFIN => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_0, DyeColor::WHITE(), DyeColor::YELLOW()],
			self::YELLOW_TANG => [TropicalFishShape::LARGE, TropicalFishPattern::PATTERN_1, DyeColor::YELLOW(), DyeColor::YELLOW()],
			self::TOMATO_CLOWN => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_1, DyeColor::RED(), DyeColor::WHITE()],
			self::TRIGGERFISH => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_1, DyeColor::GRAY(), DyeColor::WHITE()],
			self::YELLOW_TAIL_PARROT => [TropicalFishShape::SMALL, TropicalFishPattern::PATTERN_3, DyeColor::CYAN(), DyeColor::YELLOW()],
		};
	}

	public function getShape() : TropicalFishShape{ return $this->getData()[0]; }

	public function getPattern() : TropicalFishPattern{ return $this->getData()[1]; }

	public function getBaseColor() : DyeColor{ return $this->getData()[2]; }

	public function getPatternColor() : DyeColor{ return $this->getData()[3]; }
}