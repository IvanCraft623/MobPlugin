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

namespace IvanCraft623\MobPlugin\spawning\condition\vanilla\slime;

/**
 * MT19937, exactly as Bedrock seeds it for the slime-chunk check (init_genrand + the
 * first genrand_int32). 32-bit semantics are emulated explicitly — PHP ints are 64-bit,
 * and every intermediate must wrap to match the reference implementation.
 *
 * Reverse engineered by @protolambda and @jocopa3; ported from the Java port at
 * https://gist.github.com/protolambda/00b85bf34a75fd8176342b1ad28bfccc (see
 * slime-finder-pe lib/chunk.ts).
 */
final class MersenneTwister{
	private const N = 624;
	private const M = 397;
	private const MATRIX_A = 0x9908b0df;
	private const UPPER_MASK = 0x80000000;
	private const LOWER_MASK = 0x7fffffff;

	/** @var int[] */
	private array $state;
	private int $index = self::N + 1;

	public function __construct(int $seed){
		$this->state[0] = $seed & 0xFFFFFFFF;
		for($this->index = 1; $this->index < self::N; $this->index++){
			$previous = $this->state[$this->index - 1];
			$s = $previous ^ (($previous >> 30) & 0x3);
			// Split the 32x32->64 multiply so no intermediate exceeds 32 bits.
			$hi = (($s >> 16) & 0xFFFF) * 1812433253;
			$lo = ($s & 0xFFFF) * 1812433253;
			$this->state[$this->index] = ((($hi & 0xFFFF) << 16) + $lo + $this->index) & 0xFFFFFFFF;
		}
	}

	public function randomInt() : int{
		$mag01 = [0, self::MATRIX_A];
		if($this->index >= self::N){
			for($kk = 0; $kk < self::N - self::M; $kk++){
				$y = ($this->state[$kk] & self::UPPER_MASK) | ($this->state[$kk + 1] & self::LOWER_MASK);
				$this->state[$kk] = $this->state[$kk + self::M] ^ ($y >> 1) ^ $mag01[$y & 1];
			}
			for(; $kk < self::N - 1; $kk++){
				$y = ($this->state[$kk] & self::UPPER_MASK) | ($this->state[$kk + 1] & self::LOWER_MASK);
				$this->state[$kk] = $this->state[$kk + (self::M - self::N)] ^ ($y >> 1) ^ $mag01[$y & 1];
			}
			$y = ($this->state[self::N - 1] & self::UPPER_MASK) | ($this->state[0] & self::LOWER_MASK);
			$this->state[self::N - 1] = $this->state[self::M - 1] ^ ($y >> 1) ^ $mag01[$y & 1];
			$this->index = 0;
		}
		$y = $this->state[$this->index++];
		// Tempering, with explicit 32-bit masking on the left shifts.
		$y ^= $y >> 11;
		$y ^= ($y << 7) & 0x9d2c5680;
		$y &= 0xFFFFFFFF;
		$y ^= ($y << 15) & 0xefc60000;
		$y &= 0xFFFFFFFF;
		$y ^= $y >> 18;

		return $y & 0xFFFFFFFF;
	}
}
