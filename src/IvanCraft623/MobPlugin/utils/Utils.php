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

use IvanCraft623\MobPlugin\entity\ai\targeting\TargetingConditions;
use IvanCraft623\MobPlugin\pathfinder\PathComputationType;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Door;
use pocketmine\block\Slab;
use pocketmine\block\Water;
use pocketmine\entity\Living;
use pocketmine\item\Bow;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\Releasable;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function abs;
use function array_reduce;
use function cos;
use function fmod;
use function max;
use function method_exists;
use function min;
use function sin;
use const M_PI;

class Utils {

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

	public static function isPathfindable(Block $block, PathComputationType $pathType) : bool{
		if ($block instanceof Door) {
			if ($pathType->equals(PathComputationType::LAND()) || $pathType->equals(PathComputationType::AIR())) {
				return $block->isOpen();
			}
			return false;
		} elseif ($block instanceof Slab) {
			//TODO: Waterlogging check
			return false;
		}

		switch ($block->getTypeId()) {
			case BlockTypeIds::ANVIL:
			case BlockTypeIds::BREWING_STAND:
			case BlockTypeIds::DRAGON_EGG:
			//TODO: respawn anchor
			case BlockTypeIds::END_ROD:
			//TODO: lightning rod
			//TODO: piston arm
				return false;

			case BlockTypeIds::DEAD_BUSH:
				return $pathType->equals(PathComputationType::AIR()) ? true : self::getDefaultPathfindable($block, $pathType);

			default:
				return self::getDefaultPathfindable($block, $pathType);

		}
	}

	private static function getDefaultPathfindable(Block $block, PathComputationType $pathType) : bool{
		return match(true){
			$pathType->equals(PathComputationType::LAND()) => !$block->isFullCube(),
			$pathType->equals(PathComputationType::WATER()) => $block instanceof Water, //TODO: watterlogging check
			$pathType->equals(PathComputationType::AIR()) => !$block->isFullCube(),
			default => false
		};
	}

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

	public static function getNearestPlayer(Living $entity, float $maxDistance = -1, ?TargetingConditions $conditions = null) : ?Player{
		$pos = $entity->getPosition();
		return array_reduce($pos->getWorld()->getPlayers(), function(?Player $carry, Player $current) use ($entity, $pos, $maxDistance, $conditions) : ?Player{
			if ($conditions !== null && !$conditions->test($entity, $current)) {
				return $carry;
			}

			$distanceSquared = $current->getPosition()->distanceSquared($pos);
			if ($maxDistance > 0 && $distanceSquared > ($maxDistance ** 2)) {
				return $carry;
			}

			if ($carry === null) {
				return $current;
			}

			return $carry->getPosition()->distanceSquared($pos) < $distanceSquared ? $carry : $current;
		}, null);
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
	 * Generate adjacent positions in a sphere around the given starting position within specified maximum distances in each axis.
	 *
	 * The function uses a generator to efficiently compute and yield adjacent positions in a sphere shape
	 * around the starting position. It calculates positions within the maximum distances specified along the X, Y, and Z axes.
	 *
	 * @param Vector3 $startingPosition The starting position around which the adjacent positions are generated.
	 * @param int     $maxDistanceX     The maximum distance in the X-axis from the starting position.
	 * @param int     $maxDistanceY     The maximum distance in the Y-axis from the starting position.
	 * @param int     $maxDistanceZ     The maximum distance in the Z-axis from the starting position.
	 *
	 * @return Vector[]|\Generator The function returns a generator that yields Vector3 objects representing
	 *                             adjacent positions within the specified sphere shape around the starting position.
	 *
	 * @phpstan-return \Generator<int, Vector3, void, void>
	 */
	public static function getAdjacentPositions(Vector3 $startingPosition, int $maxDistanceX, int $maxDistanceY, int $maxDistanceZ) : \Generator {
		$totalDistance = $maxDistanceX + $maxDistanceY + $maxDistanceZ;
		$startX = $startingPosition->getX();
		$startY = $startingPosition->getY();
		$startZ = $startingPosition->getZ();

		$cursor = Vector3::zero();
		$currentDistance = 0;
		$maxX = 0;
		$maxY = 0;
		$x = 0;
		$y = 0;
		$zMirror = false;

		while (true) {
			if ($zMirror) {
				//Generate positions in the mirrored Z-axis direction
				$zMirror = false;
				$cursor->z = ($startZ - ($cursor->getZ() - $startZ));
				yield clone $cursor;
			} else {
				//Generate positions in the positive X, Y, and Z-axis directions
				$generatedPosition = null;
				while ($generatedPosition === null) {
					if ($y > $maxY) {
						//Update X and Y values for the next iteration
						$x++;
						if ($x > $maxX) {
							$currentDistance++;
							if ($currentDistance > $totalDistance) {
								return; //Finished generating all positions within the sphere
							}

							//Update maxX and reset X for the next distance level
							$maxX = min($maxDistanceX, $currentDistance);
							$x = -$maxX;
						}

						//Update maxY and reset Y for the next distance level
						$maxY = min($maxDistanceY, $currentDistance - abs($x));
						$y = -$maxY;
					}

					//Calculate the current X, Y, and Z coordinates for the position
					$currentX = $x;
					$currentY = $y;
					$currentZ = $currentDistance - abs($currentX) - abs($currentY);

					if ($currentZ <= $maxDistanceZ) {
						//Check if the current position is within the specified maximum distance along the Z-axis
						//If yes, set the zMirror flag if Z is non-zero to ensure symmetry in the sphere generation
						$zMirror = $currentZ !== 0;

						//Generate the position and update the cursor
						$generatedPosition = $cursor = new Vector3($startX + $currentX, $startY + $currentY, $startZ + $currentZ);
					}
					$y++;
				}

				yield clone $generatedPosition;
			}
		}
	}
}
