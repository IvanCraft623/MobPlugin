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

namespace IvanCraft623\MobPlugin\entity\ai\control;

use IvanCraft623\MobPlugin\entity\ai\navigation\PathNavigation;
use IvanCraft623\MobPlugin\entity\PathfinderMob;

/**
 * A control that follows a navigation: it lets the navigation advance along the path, which sets the wanted
 * position, and then drives the mob toward it.
 */
abstract class PathMoveControl extends MoveControl {

	public function __construct(PathfinderMob $mob, protected PathNavigation $navigation) {
		parent::__construct($mob);
	}

	final public function tick() : void {
		$this->navigation->tick();

		$wanted = $this->navigation->getWantedPosition();
		if ($wanted !== null) {
			$this->setWantedPosition($wanted, $this->navigation->getSpeedModifier());
		}

		$this->drive();
	}

	abstract protected function drive() : void;
}
