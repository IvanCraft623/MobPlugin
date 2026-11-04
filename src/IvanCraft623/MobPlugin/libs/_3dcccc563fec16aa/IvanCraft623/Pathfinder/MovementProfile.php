<?php

/*
 *  _____      _   _      __ _           _
 * |  __ \    | | | |    / _(_)         | |
 * | |__) |_ _| |_| |__ | |_ _ _ __   __| | ___ _ __
 * |  ___/ _` | __| '_ \|  _| | '_ \ / _` |/ _ \ '__|
 * | |  | (_| | |_| | | | | | | | | | (_| |  __/ |
 * |_|   \__,_|\__|_| |_|_| |_|_| |_|\__,_|\___|_|
 *
 * A PocketMine-MP virion that implements a mob-oriented pathfinding.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author IvanCraft623
 */

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_3dcccc563fec16aa\IvanCraft623\Pathfinder;

use pocketmine\block\Liquid;
use function floor;
use function min;

/**
 * How the searching mob moves.
 */
final class MovementProfile{
	public readonly int $nodeWidth; //blocks a node spans on the x and z axes
	public readonly int $nodeHeight; //blocks a node spans on the y axis
	public readonly float $eyeHeight;
	public readonly BlockPathTypeCostMap $costMap; //a copy, later changes to the given map do not apply

	/** @var true[] block type id => true */
	private array $liquidsThatCanStandOn = [];

	/**
	 * @param int[] $liquidsThatCanStandOn block type ids
	 */
	public function __construct(
		public readonly float $width,
		public readonly float $height,
		public readonly float $maxUpStep = 0.6,
		public readonly int $maxFallDistance = 3,
		BlockPathTypeCostMap $costMap = new BlockPathTypeCostMap(),
		public readonly bool $canPassDoors = false,
		public readonly bool $canOpenDoors = false,
		public readonly bool $canFloat = false,
		public readonly bool $canWalkOverFences = false,
		array $liquidsThatCanStandOn = [],
		?float $eyeHeight = null
	){
		$this->nodeWidth = (int) floor($width + 1);
		$this->nodeHeight = (int) floor($height + 1);
		$this->eyeHeight = $eyeHeight ?? min($height / 2 + 0.1, $height);
		$this->costMap = clone $costMap;
		foreach ($liquidsThatCanStandOn as $blockTypeId) {
			$this->liquidsThatCanStandOn[$blockTypeId] = true;
		}
	}

	public function canStandOnFluid(Liquid $liquid) : bool{
		return isset($this->liquidsThatCanStandOn[$liquid->getTypeId()]);
	}
}