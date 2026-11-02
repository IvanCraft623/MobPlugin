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

namespace IvanCraft623\MobPlugin\entity\ai\navigation;


final class EntityRepathThrottleTest extends NavigationTestCase{
	public function testFirstRequestAndANewBlockAreRequested() : void{
		$throttle = new EntityRepathThrottle();
		self::assertTrue($throttle->shouldRequest(1, false, null, 0));

		$throttle->requested(1, false, 0);
		self::assertTrue($throttle->shouldRequest(2, false, null, 1), "the entity moved");
	}

	public function testNothingNewWhileTheRequestIsRunningOrBeingFollowed() : void{
		$throttle = new EntityRepathThrottle();
		$throttle->requested(1, false, 0);
		self::assertFalse($throttle->shouldRequest(1, true, null, 1), "pending");

		$path = self::path(0, 5);
		$throttle->resolved(1, $path);
		self::assertFalse($throttle->shouldRequest(1, false, $path, 2), "following that path");

		$path->setNextNodeIndex(6);
		self::assertFalse($throttle->shouldRequest(1, false, $path, 3), "ended, too soon");
		self::assertTrue($throttle->shouldRequest(1, false, $path, EntityRepathThrottle::INTERVAL), "ended, long enough");
	}

	public function testAnotherPathIsNotTheRequestedOne() : void{
		$throttle = new EntityRepathThrottle();
		$throttle->requested(1, false, 0);
		$throttle->resolved(1, self::path(0, 5));

		self::assertFalse($throttle->shouldRequest(1, false, self::path(0, 5), 1), "too soon");
		self::assertTrue($throttle->shouldRequest(1, false, self::path(0, 5), EntityRepathThrottle::INTERVAL));
	}

	public function testRequestsMadeWhileAnotherRunsAreNotRemembered() : void{
		$throttle = new EntityRepathThrottle();
		$throttle->requested(1, true, 0);
		self::assertTrue($throttle->shouldRequest(1, false, null, 1), "it got the other request's path");
	}

	public function testResetForgetsTheRequest() : void{
		$throttle = new EntityRepathThrottle();
		$throttle->requested(1, false, 0);
		$throttle->reset();
		self::assertTrue($throttle->shouldRequest(1, false, null, 1));
	}
}
