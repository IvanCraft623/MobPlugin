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

namespace IvanCraft623\MobPlugin\entity\ai\goal;

use IvanCraft623\MobPlugin\entity\PathfinderMob;
use IvanCraft623\MobPlugin\spawning\BiomeTagMap;
use IvanCraft623\MobPlugin\utils\Utils;
use pocketmine\math\Vector3;
use function asin;
use function atan2;
use function cos;
use function floor;
use function sin;
use function sqrt;
use const M_PI;

/**
 * Swims straight ahead outside open sea, steering around obstacles, at a fixed speed instead of pathing.
 */
class RiverFollowingGoal extends Goal {

	private const HIGH_SEAS_TAG = "high_seas";
	private const PROBE_ANGLE = 15 * M_PI / 180;
	private const REPULSION = 1.2;
	/** Degrees the body turns towards the heading per tick */
	private const MAX_TURN = 45.0;
	/** The heading only changes this often, the step is taken every tick */
	private const STEER_INTERVAL = 2;

	private int $steerTimer = 0;
	private ?Vector3 $heading = null;

	/**
	 * @param float $speed     blocks per tick
	 * @param float $lookAhead distance probed for obstacles
	 */
	public function __construct(
		private PathfinderMob $mob,
		private float $speed = 0.14,
		private float $lookAhead = 2.0
	) {
		$this->setFlags(Goal::FLAG_MOVE, Goal::FLAG_LOOK);
	}

	public function canUse() : bool{
		if (!$this->mob->isInWater()) {
			return false;
		}
		$position = $this->mob->getPosition();

		return !BiomeTagMap::getInstance()->hasTag(
			$this->mob->getWorld()->getBiomeId($position->getFloorX(), $position->getFloorY(), $position->getFloorZ()),
			self::HIGH_SEAS_TAG
		);
	}

	public function requiresUpdateEveryTick() : bool{
		return true;
	}

	public function start() : void{
		$this->mob->getNavigation()->stop();
		$this->steerTimer = 0;
		$this->heading = null;
	}

	public function stop() : void{
		$this->heading = null;
	}

	public function tick() : void{
		if ($this->heading === null || --$this->steerTimer <= 0) {
			$this->heading = $this->findHeading();
			$this->steerTimer = self::STEER_INTERVAL;
		}
		$heading = $this->heading;

		$location = $this->mob->getLocation();
		$yaw = $location->yaw + Utils::clamp(
			Utils::wrapDegrees(atan2($heading->z, $heading->x) * 180 / M_PI - 90 - $location->yaw),
			-self::MAX_TURN,
			self::MAX_TURN
		);
		$this->mob->setRotation($yaw, -asin(Utils::clamp($heading->y, -1, 1)) * 180 / M_PI);

		$this->mob->displace($heading->x * $this->speed, $heading->y * $this->speed, $heading->z * $this->speed);
	}

	/**
	 * The way the mob faces, bent away from walls, the surface and the bottom. Unit length.
	 */
	private function findHeading() : Vector3{
		$position = $this->mob->getPosition();
		$view = $this->mob->getDirectionVector();

		if (!$this->isClear($position, $view, $this->lookAhead) ||
			!$this->isClear($position, self::rotateY($view, self::PROBE_ANGLE), $this->lookAhead - 1.5) ||
			!$this->isClear($position, self::rotateY($view, -self::PROBE_ANGLE), $this->lookAhead - 1.5)
		) {
			$view = $this->findClearestDirection($position, $view);
		}

		$push = $this->getCornerPush($position);
		$wanted = $view->addVector($push->multiply(self::REPULSION));

		$length = $wanted->length();

		return $length < 1.0E-4 ? $view : $wanted->divide($length);
	}

	/**
	 * The clear one of the 26 neighbouring directions closest to the view, doubled to turn faster. Turns around if none.
	 */
	private function findClearestDirection(Vector3 $position, Vector3 $view) : Vector3{
		$best = null;
		$bestDot = -2.0;
		for ($x = -1; $x <= 1; ++$x) {
			for ($y = -1; $y <= 1; ++$y) {
				for ($z = -1; $z <= 1; ++$z) {
					if ($x === 0 && $y === 0 && $z === 0) {
						continue;
					}
					$direction = (new Vector3($x, $y, $z))->normalize();
					$dot = $direction->dot($view);
					if ($dot > $bestDot && $this->isClear($position, $direction, $this->lookAhead)) {
						$best = $direction;
						$bestDot = $dot;
					}
				}
			}
		}

		return $best === null ? $view->multiply(-1) : $best->multiply(2);
	}

	/**
	 * Away from the corners of the mob's box that touch something other than water.
	 */
	private function getCornerPush(Vector3 $position) : Vector3{
		$world = $this->mob->getWorld();
		$x = 0.0;
		$y = 0.0;
		$z = 0.0;
		foreach ([-0.5, 0.5] as $dx) {
			foreach ([-0.5, 0.5] as $dy) {
				foreach ([-0.5, 0.5] as $dz) {
					if (!Utils::isWaterAt($world, (int) floor($position->x + $dx), (int) floor($position->y + $dy), (int) floor($position->z + $dz))) {
						$x -= $dx;
						$y -= $dy;
						$z -= $dz;
					}
				}
			}
		}
		$length = sqrt($x * $x + $y * $y + $z * $z);

		return $length < 1.0E-4 ? Vector3::zero() : new Vector3($x / $length, $y / $length, $z / $length);
	}

	private function isClear(Vector3 $from, Vector3 $direction, float $length) : bool{
		$world = $this->mob->getWorld();
		for ($distance = 0.5; $distance <= $length; $distance += 0.5) {
			if (!Utils::isWaterAt($world, (int) floor($from->x + $direction->x * $distance), (int) floor($from->y + $direction->y * $distance), (int) floor($from->z + $direction->z * $distance))) {
				return false;
			}
		}

		return true;
	}

	private static function rotateY(Vector3 $vector, float $angle) : Vector3{
		$cos = cos($angle);
		$sin = sin($angle);

		return new Vector3($vector->x * $cos - $vector->z * $sin, $vector->y, $vector->x * $sin + $vector->z * $cos);
	}
}