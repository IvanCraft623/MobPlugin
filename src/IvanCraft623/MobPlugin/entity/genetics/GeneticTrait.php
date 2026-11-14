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

namespace IvanCraft623\MobPlugin\entity\genetics;

use IvanCraft623\MobPlugin\data\bedrock\GenomeNbt;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\CompoundTag;

/**
 * @phpstan-require-implements Genetic
 * @phpstan-require-extends Entity
 */
trait GeneticTrait{

	private Genome $genome;

	/**
	 * @return list<Gene<*>> the genes it has, in the order they are saved
	 */
	abstract protected function getGenes() : array;

	/**
	 * Applies what the genome makes of the entity.
	 */
	abstract protected function onGenomeChanged() : void;

	/**
	 * Loads the genome from the entity's data, or rolls a new one.
	 */
	protected function initEntity(CompoundTag $nbt) : void{
		$genes = $this->getGenes();
		$this->setGenome(GenomeNbt::read($nbt, $genes) ?? Genome::random($genes, $this->random));
	}

	public function saveNBT() : CompoundTag{
		$nbt = CompoundTag::create();
		GenomeNbt::write($nbt, $this->genome);

		return $nbt;
	}

	public function getGenome() : Genome{
		return $this->genome;
	}

	public function setGenome(Genome $genome) : static{
		$this->genome = $genome;
		$this->onGenomeChanged();

		return $this;
	}
}
