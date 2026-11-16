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

use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Random;
use function array_map;

/**
 * The alleles an entity has of each of its genes.
 */
final class Genome{

	/**
	 * @param list<Alleles<*>> $alleles one per gene, in a fixed order
	 */
	public function __construct(
		private array $alleles
	){}

	/**
	 * @param list<Gene<*>> $genes
	 */
	public static function random(array $genes, Random $random) : self{
		return new self(array_map(static fn(Gene $gene) : Alleles => $gene->random($random), $genes));
	}

	/**
	 * The genome of a child of two entities with the same genes.
	 */
	public static function inherit(self $parent1, self $parent2, Random $random) : self{
		return new self(array_map(
			static fn(Alleles $alleles) : Alleles => $alleles->gene->inherit($alleles, $parent2->getAlleles($alleles->gene), $random),
			$parent1->alleles
		));
	}

	/**
	 * @return list<Alleles<*>>
	 */
	public function getAllAlleles() : array{
		return $this->alleles;
	}

	/**
	 * @param Gene<*> $gene one of its genes
	 *
	 * @return Alleles<*>
	 */
	public function getAlleles(Gene $gene) : Alleles{
		foreach ($this->alleles as $alleles) {
			if ($alleles->gene === $gene) {
				return $alleles;
			}
		}

		throw new AssumptionFailedError("This genome has no such gene");
	}

	/**
	 * @template T of \UnitEnum
	 * @param Gene<T> $gene one of its genes
	 *
	 * @return T
	 */
	public function getOutcome(Gene $gene) : \UnitEnum{
		return $gene->getOutcome($this->getAlleles($gene));
	}
}