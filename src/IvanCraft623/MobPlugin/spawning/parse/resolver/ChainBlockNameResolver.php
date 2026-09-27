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

namespace IvanCraft623\MobPlugin\spawning\parse\resolver;

/** Tries each resolver in order, returning the first non-null answer. */
final class ChainBlockNameResolver implements BlockNameResolver{
	/** @phpstan-param list<BlockNameResolver> $resolvers */
	public function __construct(
		private readonly array $resolvers
	){}

	public function resolve(string $name) : ?int{
		foreach($this->resolvers as $resolver){
			$typeId = $resolver->resolve($name);
			if($typeId !== null){
				return $typeId;
			}
		}

		return null;
	}
}
