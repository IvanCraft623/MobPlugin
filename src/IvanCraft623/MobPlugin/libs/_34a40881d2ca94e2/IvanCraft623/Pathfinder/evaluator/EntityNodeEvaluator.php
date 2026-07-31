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

namespace IvanCraft623\MobPlugin\libs\_34a40881d2ca94e2\IvanCraft623\Pathfinder\evaluator;

use pocketmine\block\Liquid;
use pocketmine\block\Water;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\math\AxisAlignedBB;
use function array_flip;
use function floor;

abstract class EntityNodeEvaluator extends NodeEvaluator {

	protected EntitySizeInfo $entitySizeInfo;
	protected int $entityWidth;
	protected int $entityHeight;
	protected int $entityDepth;

	protected AxisAlignedBB $boundingBox;

	protected bool $canPassDoors = false;
	protected bool $canOpenDoors = false;
	protected bool $canFloat = false;
	protected bool $canWalkOverFences = false;

	protected bool $onGround = true;

	protected float $maxUpStep;
	protected int $maxFallDistance;

	protected array $liquidsThatCanStandOn = [];

	 public function setCanPassDoors(bool $canPassDoors = true) : void {
		$this->canPassDoors = $canPassDoors;
	}

	public function setCanOpenDoors(bool $canOpenDoors = true) : void {
		$this->canOpenDoors = $canOpenDoors;
	}

	public function setCanFloat(bool $canFloat = true) : void {
		$this->canFloat = $canFloat;
	}

	public function setCanWalkOverFences(bool $canWalkOverFences = true) : void {
		$this->canWalkOverFences = $canWalkOverFences;
	}

	public function setEntitySize(EntitySizeInfo $size) : void{
		$this->entitySizeInfo = $size;

		$this->entityWidth = (int) floor($size->getWidth() + 1);
		$this->entityHeight = (int) floor($size->getHeight() + 1);
		$this->entityDepth = $this->entityWidth;
	}

	public function setEntityBoundingBox(AxisAlignedBB $bb) : void{
		$this->boundingBox = $bb;
	}

	public function setEntityOnGround(bool $bool) : void{
		$this->onGround = $bool;
	}

	public function setMaxUpStep(float $step) : void{
		$this->maxUpStep = $step;
	}

	public function setLiquidsThatCanStandOn(int ...$blockTypeIds) : void{
		$this->liquidsThatCanStandOn = array_flip($blockTypeIds);
	}

	public function setMaxFallDistance(int $blocks) : void{
		$this->maxFallDistance = $blocks;
	}

	public function canPassDoors() : bool {
		return $this->canPassDoors;
	}

	public function canOpenDoors() : bool {
		return $this->canOpenDoors;
	}

	public function canFloat() : bool {
		return $this->canFloat;
	}

	public function canWalkOverFences() : bool{
		return $this->canWalkOverFences;
	}

	public function getEntitySizeInfo() : EntitySizeInfo{
		return $this->entitySizeInfo;
	}

	public function getEntityWidth() : float {
		return $this->entityWidth;
	}

	public function getEntityHeight() : float {
		return $this->entityHeight;
	}

	public function getEntityDepth() : float {
		return $this->entityDepth;
	}

	protected function getEntityBoundingBox() : AxisAlignedBB{
		return $this->boundingBox;
	}

	public function isEntityOnGround() : bool{
		return $this->onGround;
	}

	public function isEntityUnderwater() : bool{
		$block = $this->blockGetter->getBlockAt(
			(int) floor($this->startPosition->x),
			$blockY = (int) floor($y = ($this->startPosition->y + $this->entitySizeInfo->getEyeHeight())),
			(int) floor($this->startPosition->z)
		);

		if($block instanceof Water){
			$f = ($blockY + 1) - ($block->getFluidHeightPercent() - 0.1111111);
			return $y < $f;
		}

		return false;
	}

	public function getMaxUpStep() : float{
		return $this->maxUpStep;
	}

	public function getMaxFallDistance() : int{
		return $this->maxFallDistance;
	}

	public function canStandOnFluid(Liquid $liquid) : bool{
		return isset($this->liquidsThatCanStandOn[$liquid->getTypeId()]);
	}
}