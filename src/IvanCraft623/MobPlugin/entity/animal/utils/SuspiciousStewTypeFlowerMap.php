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

namespace IvanCraft623\MobPlugin\entity\animal\utils;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\SuspiciousStewType;
use pocketmine\utils\SingletonTrait;

final class SuspiciousStewTypeFlowerMap{
	use SingletonTrait;

	/**
	 * @var SuspiciousStewType[]
	 * @phpstan-var array<int, SuspiciousStewType>
	 */
	private array $flowerToEnum = [];

	/**
	 * @var Block[]
	 * @phpstan-var array<int, Block>
	 */
	private array $enumToFlower = [];

	private function __construct(){
		$this->register(VanillaBlocks::POPPY(), SuspiciousStewType::POPPY());
		$this->register(VanillaBlocks::CORNFLOWER(), SuspiciousStewType::CORNFLOWER());

		$this->register(VanillaBlocks::ORANGE_TULIP(), SuspiciousStewType::TULIP());
		$this->register(VanillaBlocks::PINK_TULIP(), SuspiciousStewType::TULIP());
		$this->register(VanillaBlocks::RED_TULIP(), SuspiciousStewType::TULIP());
		$this->register(VanillaBlocks::WHITE_TULIP(), SuspiciousStewType::TULIP());

		$this->register(VanillaBlocks::AZURE_BLUET(), SuspiciousStewType::AZURE_BLUET());
		$this->register(VanillaBlocks::LILY_OF_THE_VALLEY(), SuspiciousStewType::LILY_OF_THE_VALLEY());
		$this->register(VanillaBlocks::DANDELION(), SuspiciousStewType::DANDELION());
		$this->register(VanillaBlocks::BLUE_ORCHID(), SuspiciousStewType::BLUE_ORCHID());
		$this->register(VanillaBlocks::ALLIUM(), SuspiciousStewType::ALLIUM());
		$this->register(VanillaBlocks::OXEYE_DAISY(), SuspiciousStewType::OXEYE_DAISY());
		$this->register(VanillaBlocks::WITHER_ROSE(), SuspiciousStewType::WITHER_ROSE());
	}

	private function register(Block $flower, SuspiciousStewType $type) : void{
		$this->flowerToEnum[$flower->getTypeId()] = $type;
		$this->enumToFlower[$type->id()] = $flower;
	}

	public function fromFlower(Block $flower) : ?SuspiciousStewType{
		return $this->flowerToEnum[$flower->getTypeId()] ?? null;
	}

	public function toFlower(SuspiciousStewType $type) : Block{
		if(!isset($this->enumToFlower[$type->id()])){
			throw new \InvalidArgumentException("Type does not have a mapped ID");
		}
		return $this->enumToFlower[$type->id()];
	}
}
