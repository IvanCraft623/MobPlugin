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

/**
 * A rule of a gene: it gives its outcome when every range it sets holds.
 *
 * @template-covariant T of \UnitEnum
 */
final class GeneVariant{

	/**
	 * @param T            $outcome
	 * @param ?AlleleRange $main    range the main allele must be in
	 * @param ?AlleleRange $hidden  range the hidden allele must be in
	 * @param ?AlleleRange $either  range one of the two alleles must be in
	 * @param ?AlleleRange $both    range both alleles must be in
	 */
	public function __construct(
		public readonly \UnitEnum $outcome,
		private ?AlleleRange $main = null,
		private ?AlleleRange $hidden = null,
		private ?AlleleRange $either = null,
		private ?AlleleRange $both = null
	){}

	/**
	 * @param Alleles<*> $alleles
	 */
	public function matches(Alleles $alleles) : bool{
		return ($this->main === null || $this->main->contains($alleles->main)) &&
			($this->hidden === null || $this->hidden->contains($alleles->hidden)) &&
			($this->either === null || $this->either->contains($alleles->main) || $this->either->contains($alleles->hidden)) &&
			($this->both === null || ($this->both->contains($alleles->main) && $this->both->contains($alleles->hidden)));
	}
}