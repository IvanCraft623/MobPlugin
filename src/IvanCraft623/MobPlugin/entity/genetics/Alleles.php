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
 * The two alleles an entity has of a gene: the main one, which counts first, and the hidden one.
 *
 * @template-covariant T of \UnitEnum
 */
final class Alleles{

	/**
	 * @param Gene<T> $gene
	 */
	public function __construct(
		public readonly Gene $gene,
		public readonly int $main,
		public readonly int $hidden
	){}

	/**
	 * @return T what the gene makes of these alleles
	 */
	public function getOutcome() : \UnitEnum{
		return $this->gene->getOutcome($this);
	}
}