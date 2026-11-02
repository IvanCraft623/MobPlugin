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

use IvanCraft623\Pathfinder\PathInvalidation;
use IvanCraft623\Pathfinder\PathResult;
use pocketmine\math\Vector3;

final class RepathPolicyTest extends NavigationTestCase{
	private RepathPolicy $policy;

	protected function setUp() : void{
		$this->policy = new RepathPolicy();
	}

	private static function request(int $x, int $reach = 1) : PathRequest{
		return new PathRequest(new Vector3($x, 64, 0), $reach, null);
	}

	public function testNearTargetExtendsFromTheClosestNode() : void{
		$path = self::path(0, 10);
		$request = $this->policy->extend(self::request(12), $path, new Vector3(10, 64, 0), 1, 0);

		self::assertSame($path, $request->base);
		self::assertSame(10, $request->spliceIndex);
		self::assertNull($request->rejoinIndex);
	}

	public function testExtensionIsSkippedWhenItWouldNotHelp() : void{
		$path = self::path(0, 10);
		$last = new Vector3(10, 64, 0);

		self::assertNull($this->policy->extend(self::request(20), $path, $last, 1, 0)->base, "target moved too far");
		self::assertNull($this->policy->extend(self::request(12, 2), $path, $last, 1, 0)->base, "other reach");
		self::assertNull($this->policy->extend(self::request(12), $path, $last, 1, RepathPolicy::MAX_SPLICES_IN_A_ROW)->base, "too many in a row");
		self::assertNull($this->policy->extend(self::request(12), self::path(0, 10, PathResult::BLOCKED), $last, 1, 0)->base, "path never reached");
		self::assertNull($this->policy->extend(self::request(12), $path, null, 1, 0)->base, "no last target");
		self::assertNull($this->policy->extend(self::request(12), null, $last, 1, 0)->base, "no path");

		$path->setNextNodeIndex(11);
		self::assertNull($this->policy->extend(self::request(12), $path, $last, 1, 0)->base, "path done");
	}

	public function testRepairPlan() : void{
		$path = self::path(0, 10);
		$repair = $this->policy->repair($path, new PathInvalidation(4, 5), 0);

		self::assertSame([3, 6], [$repair?->from, $repair?->rejoin]);
	}

	public function testNoRepairWhenItIsNotWorthIt() : void{
		$path = self::path(0, 10);
		$invalidation = new PathInvalidation(4, 5);

		self::assertNull($this->policy->repair(null, $invalidation, 0));
		self::assertNull($this->policy->repair($path, null, 0));
		self::assertNull($this->policy->repair($path, $invalidation, RepathPolicy::MAX_SPLICES_IN_A_ROW), "too many in a row");
		self::assertNull($this->policy->repair($path, new PathInvalidation(0, 1), 0), "reaches the start");
	}

	public function testSearchFromTheMob() : void{
		$request = $this->policy->createSearchRequest(self::request(10), self::searchSetup(100, 16));

		self::assertFalse($request->startAtNode);
		self::assertSame(100, $request->maxVisitedNodes);
		self::assertSame(16.0, $request->maxDistanceFromStart);
		self::assertTrue($request->onGround);
	}

	public function testExtensionSearchGetsAPartOfTheBudget() : void{
		$path = self::path(0, 10);
		$request = $this->policy->createSearchRequest(self::request(12)->splicingOnto($path, 10), self::searchSetup(100));

		self::assertTrue($request->startAtNode);
		self::assertSame(25, $request->maxVisitedNodes);
		self::assertSame(1, $request->reachRange);
		self::assertSame(12, $request->target->getFloorX());
		self::assertSame(10, $request->start->getFloorX());
	}

	public function testRepairSearchGoesToTheRejoinNode() : void{
		$path = self::path(0, 30);
		$request = $this->policy->createSearchRequest(self::request(30)->splicingOnto($path, 3, 6), self::searchSetup(100, 32));

		self::assertSame(6, $request->target->getFloorX());
		self::assertSame(0, $request->reachRange);
		self::assertSame((float) RepathPolicy::REPAIR_MIN_RADIUS, $request->maxDistanceFromStart, "gap of 3, at least the minimum");

		$far = $this->policy->createSearchRequest(self::request(30)->splicingOnto($path, 3, 15), self::searchSetup(100, 32));
		self::assertSame(24.0, $far->maxDistanceFromStart, "twice the gap");

		$capped = $this->policy->createSearchRequest(self::request(30)->splicingOnto($path, 3, 15), self::searchSetup(100, 10));
		self::assertSame(10.0, $capped->maxDistanceFromStart, "never beyond the follow range");
	}

	public function testResultsAreAcceptedOnlyForThePathTheyWereMadeFor() : void{
		$path = self::path(0, 10);
		$continuation = self::path(10, 12);
		$extension = self::request(12)->splicingOnto($path, 10);

		self::assertTrue($this->policy->isResultUsable(self::request(12), self::path(0, 12, PathResult::BLOCKED), null), "searches from the mob always");
		self::assertTrue($this->policy->isResultUsable($extension, $continuation, $path));
		self::assertFalse($this->policy->isResultUsable($extension, $continuation, self::path(0, 10)), "another path now");
		self::assertFalse($this->policy->isResultUsable($extension, $continuation, null), "stopped");
		self::assertFalse($this->policy->isResultUsable($extension, self::path(10, 12, PathResult::BLOCKED), $path), "did not reach");
		self::assertFalse($this->policy->isResultUsable($extension, self::path(11, 12), $path), "does not start at the node");
	}
}
