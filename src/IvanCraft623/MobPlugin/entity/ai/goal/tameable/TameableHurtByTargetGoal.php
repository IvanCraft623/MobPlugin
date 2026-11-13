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

namespace IvanCraft623\MobPlugin\entity\ai\goal\tameable;

use IvanCraft623\MobPlugin\entity\ai\goal\target\HurtByTargetGoal;
use IvanCraft623\MobPlugin\entity\Mob;
use IvanCraft623\MobPlugin\entity\Tameable;
use pocketmine\entity\Living;

/**
 * A hurt-by goal where tamed mobs neither call for help nor answer it.
 */
class TameableHurtByTargetGoal extends HurtByTargetGoal{

	/**
	 * @phpstan-param class-string<Living> $ignoredDamagers
	 */
	public function __construct(private Mob&Tameable $tameable, string ...$ignoredDamagers){
		parent::__construct($tameable, ...$ignoredDamagers);
	}

	protected function alertOthers(Living $target) : void{
		if (!$this->tameable->isTamed()) {
			parent::alertOthers($target);
		}
	}

	protected function alertOther(Mob $entity, Living $target) : void{
		if (!$entity instanceof Tameable || !$entity->isTamed()) {
			parent::alertOther($entity, $target);
		}
	}
}