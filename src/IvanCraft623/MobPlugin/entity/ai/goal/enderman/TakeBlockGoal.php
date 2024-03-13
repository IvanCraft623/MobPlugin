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

namespace IvanCraft623\MobPlugin\entity\ai\goal\enderman;

use IvanCraft623\MobPlugin\entity\ai\goal\Goal;
use IvanCraft623\MobPlugin\entity\monster\Enderman;

use pocketmine\player\Player;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\world\BlockTransaction;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\VoxelRayTrace;

class TakeBlockGoal extends Goal {

	public function __construct(
		protected Enderman $entity
	) { }

	public function canUse() : bool{
		if ($this->entity->getCarriedBlock() !== null) {
			return false;
		}

		//TODO: Mob griefing gamerule check

		return $this->entity->getRandom()->nextBoundedInt($this->reducedTickDelay(20)) === 0;
	}

	public function start() : void{
		$this->entity->getNavigation()->stop();
	}

	public function tick() : void{
		$world = $this->entity->getWorld();
		$entityPos = $this->entity->getPosition();

		$random = $this->entity->getRandom();
		$targetPos = new Vector3(
			floor($entityPos->getX() - 2 + $random->nextFloat() * 4),
			floor($entityPos->getY() + $random->nextFloat() * 3),
			floor($entityPos->getZ() - 2 + $random->nextFloat() * 4)
		);

		$targetBlock = $world->getBlock($targetPos);
		if (!Enderman::isHoldableBlock($targetBlock)) {
			return;
		}

		foreach(VoxelRayTrace::betweenPoints($this->entity->getEyePos(), $targetPos->add(0.5, 0.5, 0.5)) as $vector3){
			if ($vector3->equals($targetPos)) {
				break;
			}
			$block = $world->getBlockAt((int) $vector3->x, (int) $vector3->y, (int) $vector3->z);
			if ($block->getTypeId() !== BlockTypeIds::AIR) {
				return;
			}
		}

		$world->setBlock($targetPos, VanillaBlocks::AIR());
		$this->entity->setCarriedBlock($targetBlock);
	}
}
