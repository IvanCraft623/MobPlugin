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

namespace IvanCraft623\MobPlugin\utils;

use DateTime;
use IvanCraft623\MobPlugin\entity\Mob;

use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\item\Bow;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\Releasable;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\Position;
use pocketmine\world\World;
use function abs;
use function array_key_last;
use function array_sum;
use function cos;
use function floor;
use function fmod;
use function intdiv;
use function max;
use function method_exists;
use function min;
use function sin;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;
use const M_PI;

class Utils {

	private const MOON_PHASES_COUNT = 8;
	private const NEW_MOON_PHASE = 4; //the full moon is phase 0

	private const REGIONAL_DIFFICULTY_NORMAL_BASE = 0.375; //on normal difficulty, with a new moon
	private const REGIONAL_DIFFICULTY_MOON_FACTOR = 0.25; //multiplies the brightness of the moon

	/**
	 * Picks a key with a probability proportional to its weight, or null when no weight is
	 * positive. The return type is mixed so PHPStan keeps TKey.
	 *
	 * @template TKey
	 * @phpstan-param array<TKey, int> $weights
	 * @phpstan-return TKey|null
	 */
	public static function pickWeighted(Random $random, array $weights) : mixed{
		$total = array_sum($weights);
		if($total <= 0){
			return null;
		}
		$roll = $random->nextBoundedInt($total);
		foreach($weights as $key => $weight){
			$roll -= $weight;
			if($roll < 0){
				return $key;
			}
		}

		return array_key_last($weights);
	}

	public static function clamp(float $value, float $minValue, float $maxValue) : float {
		return max($minValue, min($maxValue, $value));
	}

	public static function wrapDegrees(float $degrees) : float {
		$result = fmod($degrees, 360);
		if ($result >= 180) {
			$result -= 360;
		}
		if ($result < -180) {
			$result += 360;
		}
		return $result;
	}

	public static function degreesDifference(float $degrees1, float $degrees2) : float {
		return self::wrapDegrees($degrees2 - $degrees1);
	}

	public static function getDefaultProjectileRange(Releasable $item) : int {
		if ($item instanceof Bow) {
			return 15;
		}
		return 8;
	}

	public static function rotateIfNecessary(float $currentDegrees, float $targetDegrees, float $maxDifference) : float {
		return $targetDegrees - self::clamp(self::degreesDifference($currentDegrees, $targetDegrees), -$maxDifference, $maxDifference);
	}

	/**
	 * @param object[] $array
	 */
	public static function arrayContains(object $needle, array $array) : bool{
		$useEquals = method_exists($needle, "equals");
		foreach ($array as $value) {
			if (!$value instanceof $needle) {
				continue;
			}
			if ($useEquals) {
				if ($needle->equals($value)) {
					return true;
				}
			} elseif ($needle === $value) {
				return true;
			}
		}

		return false;
	}

	public static function movementInputToMotion(Vector3 $movementInput, float $yaw, float $speed) : Vector3{
		$length = $movementInput->lengthSquared();
		if ($length < 1.0E-7) {
			return Vector3::zero();
		}

		$vec3 = (($length > 1) ? $movementInput->normalize() : $movementInput)->multiply($speed);
		$f = sin($yaw * (M_PI / 180));
		$g = cos($yaw * (M_PI / 180));
		return new Vector3(
			$vec3->x * $g - $vec3->z * $f,
			$vec3->y,
			$vec3->z * $g + $vec3->x * $f
		);
	}

	/**
	 * Returns how bright the moon is at the given world time, from the new moon to the full moon.
	 *
	 * @return float 0.0-1.0
	 */
	public static function getMoonBrightness(int $time) : float{
		return abs((intdiv($time, World::TIME_FULL) & (self::MOON_PHASES_COUNT - 1)) - self::NEW_MOON_PHASE) / self::NEW_MOON_PHASE;
	}

	/**
	 * Returns the regional difficulty of a world: 0.0 on peaceful and easy, 1.0 on hard,
	 * and on normal higher the brighter the moon.
	 *
	 * @return float 0.0-1.0
	 */
	public static function getRegionalDifficulty(World $world) : float{
		return match($world->getDifficulty()){
			World::DIFFICULTY_NORMAL => self::REGIONAL_DIFFICULTY_NORMAL_BASE + self::REGIONAL_DIFFICULTY_MOON_FACTOR * self::getMoonBrightness($world->getTime()),
			World::DIFFICULTY_HARD => 1.0,
			default => 0.0
		};
	}

	/**
	 * The item in the main hand of a player or a mob, or null for anything else.
	 */
	public static function getHeldItem(?Entity $entity) : ?Item{
		return $entity instanceof Human || $entity instanceof Mob ? $entity->getInventory()->getItemInHand() : null;
	}

	public static function popItemInHand(Player $player, int $amount = 1) : void{
		if ($player->hasFiniteResources()) {
			$item = $player->getInventory()->getItemInHand();
			$item->pop($amount);

			if ($item->isNull()) {
				$item = VanillaItems::AIR();
			}

			$player->getInventory()->setItemInHand($item);
		}
	}

	public static function transformItemInHand(Player $player, Item $result) : void{
		if ($player->hasFiniteResources()) {
			$item = $player->getInventory()->getItemInHand();
			$item->pop($result->getCount());

			if ($item->isNull()) {
				$player->getInventory()->setItemInHand($result);
				return;
			}

			$player->getInventory()->setItemInHand($item);
		}

		$player->getInventory()->addItem($result);
	}

	public static function damageItemInHand(Player $player, int $amount = 1) : void{
		if ($player->hasFiniteResources()) {
			$item = $player->getInventory()->getItemInHand();
			if ($item instanceof Durable) {
				$item->applyDamage($amount);

				if ($item->isNull()) {
					$item = VanillaItems::AIR();
				}

				$player->getInventory()->setItemInHand($item);
			}
		}
	}

	/**
	 * Basic logic of World::useBreakOn() but without player-specific logic and drops.
	 */
	public static function destroyBlock(World $world, Vector3 $postiion, bool $createParticles = true) : void{
		$item = VanillaItems::AIR();
		$destroyedBlock = $world->getBlock($postiion);

		if ($createParticles) {
			$world->addParticle($postiion->add(0.5, 0.5, 0.5), new BlockBreakParticle($destroyedBlock));
		}

		foreach ($destroyedBlock->getAffectedBlocks() as $b) {
			$b->onBreak($item);

			$tile = $world->getTile($b->getPosition());
			if ($tile !== null) {
				$tile->onBlockDestroyed();
			}
		}
	}

	public static function getLightLevelDependentMagicValue(Position $pos) : float{
		$lightPercentage = $pos->getWorld()->getFullLight($pos) / 15;

		$ambientLight = 0; //TODO: 0.1 in nether
		return (float) self::lerp($ambientLight, $lightPercentage / (4 - 3 * $lightPercentage), 1);
	}

	public static function isSkyVisible(World $world, Vector3 $pos) : bool{
		return $world->getPotentialBlockSkyLightAt((int) floor($pos->x), (int) floor($pos->y), (int) floor($pos->z)) === 15;
	}

	public static function signum(int|float $i) : int{
		return $i <=> 0;
	}

	public static function lerp(int|float $start, int|float $end, int|float $t) : int|float {
		return $end + $start * ($t - $end);
	}

	public static function getEntityNameFromId(string $id) : string{
		return ucwords(strtolower(str_replace(["_", "minecraft:"], [" ", ""], trim($id))));
	}

	public static function isHalloween(?DateTime $date = null) : bool{
		return ($date ?? new DateTime())->format('m-d') === '10-31';
	}
}
