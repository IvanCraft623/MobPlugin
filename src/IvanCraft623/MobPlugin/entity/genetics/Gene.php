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

use pocketmine\utils\Random;

/**
 * A trait that is inherited: its alleles, how they are passed on, and what the alleles of an entity make of it.
 *
 * @template-covariant T of \UnitEnum
 */
final class Gene{

	/**
	 * @param T                    $default      outcome when no variant matches
	 * @param list<GeneVariant<T>> $variants     every one that matches applies, the last one wins
	 * @param int                  $minAllele    least allele, inclusive
	 * @param int                  $maxAllele    most allele, inclusive
	 * @param float                $mutationRate chance in [0, 1] that each allele passed on is replaced by a random one
	 * @param bool                 $simplified   whether the child takes the main allele of one parent and the hidden of the other
	 */
	public function __construct(
		private \UnitEnum $default,
		private array $variants,
		private int $minAllele,
		private int $maxAllele,
		private float $mutationRate,
		private bool $simplified = false
	){}

	public function randomAllele(Random $random) : int{
		return $this->minAllele + $random->nextBoundedInt($this->maxAllele - $this->minAllele + 1);
	}

	/**
	 * @return Alleles<T>
	 */
	public function random(Random $random) : Alleles{
		return $this->alleles($this->randomAllele($random), $this->randomAllele($random));
	}

	/**
	 * @return Alleles<T>
	 */
	public function alleles(int $main, int $hidden) : Alleles{
		return new Alleles($this, $main, $hidden);
	}

	/**
	 * @param Alleles<*> $alleles
	 */
	public function isValid(Alleles $alleles) : bool{
		return $alleles->main >= $this->minAllele && $alleles->main <= $this->maxAllele &&
			$alleles->hidden >= $this->minAllele && $alleles->hidden <= $this->maxAllele;
	}

	/**
	 * The alleles of a child: the main one comes from a random parent, the hidden one from the other. Each may mutate.
	 *
	 * @param Alleles<*> $parent1
	 * @param Alleles<*> $parent2
	 *
	 * @return Alleles<T>
	 */
	public function inherit(Alleles $parent1, Alleles $parent2, Random $random) : Alleles{
		if ($random->nextBoolean()) {
			[$parent1, $parent2] = [$parent2, $parent1];
		}

		$main = $this->simplified ? $parent1->main : ($random->nextBoolean() ? $parent1->main : $parent1->hidden);
		$hidden = $this->simplified ? $parent2->hidden : ($random->nextBoolean() ? $parent2->main : $parent2->hidden);

		return $this->alleles($this->mutate($main, $random), $this->mutate($hidden, $random));
	}

	/**
	 * @param Alleles<*> $alleles
	 *
	 * @return T
	 */
	public function getOutcome(Alleles $alleles) : \UnitEnum{
		$outcome = $this->default;
		foreach ($this->variants as $variant) {
			if ($variant->matches($alleles)) {
				$outcome = $variant->outcome;
			}
		}

		return $outcome;
	}

	private function mutate(int $allele, Random $random) : int{
		return $this->mutationRate >= $random->nextFloat() ? $this->randomAllele($random) : $allele;
	}
}
