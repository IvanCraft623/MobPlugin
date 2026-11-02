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

use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;

final class StuckDetectorTest extends TestCase{
	private const SPEED = 0.2;

	private StuckDetector $detector;

	protected function setUp() : void{
		$this->detector = new StuckDetector();
	}

	public function testNothingIsCheckedBeforeTheInterval() : void{
		self::assertSame(StuckResult::NONE, $this->detector->check(StuckDetector::DISTANCE_CHECK_INTERVAL, new Vector3(0, 64, 0), self::SPEED, null, 0));
	}

	public function testStuckWhenItMovedLessThanExpected() : void{
		$this->detector->restart(0, new Vector3(0, 64, 0));
		//0.2 blocks per tick is 0.04 expected per 100 ticks of 0.25: one block
		self::assertSame(StuckResult::STUCK, $this->detector->check(101, new Vector3(0.5, 64, 0), self::SPEED, null, 0));
	}

	public function testNotStuckWhenItMovedEnough() : void{
		$this->detector->restart(0, new Vector3(0, 64, 0));
		self::assertSame(StuckResult::NONE, $this->detector->check(101, new Vector3(2, 64, 0), self::SPEED, null, 0));
	}

	public function testEachCheckStartsTheNextInterval() : void{
		$this->detector->restart(0, new Vector3(0, 64, 0));
		$this->detector->check(101, new Vector3(2, 64, 0), self::SPEED, null, 0);

		self::assertSame(StuckResult::NONE, $this->detector->check(150, new Vector3(2, 64, 0), self::SPEED, null, 0), "too soon to compare again");
		self::assertSame(StuckResult::STUCK, $this->detector->check(202, new Vector3(2, 64, 0), self::SPEED, null, 0));
	}

	public function testFastMobsAreExpectedToMoveFurther() : void{
		$this->detector->restart(0, new Vector3(0, 64, 0));
		//speed 1 must move 25 blocks per interval, speed 0.5 only 6.25
		self::assertSame(StuckResult::STUCK, $this->detector->check(101, new Vector3(10, 64, 0), 1.0, null, 0));
		$this->detector->restart(0, new Vector3(0, 64, 0));
		self::assertSame(StuckResult::NONE, $this->detector->check(101, new Vector3(10, 64, 0), 0.5, null, 0));
	}

	public function testTimesOutOnANodeItNeverReaches() : void{
		$node = new Vector3(10, 64, 0);
		$position = new Vector3(0, 64, 0);
		//10.5 blocks away at 0.2 per tick: 1050 ticks to get there, 3 times that to give up
		self::assertSame(StuckResult::NONE, $this->detector->check(1, $position, self::SPEED, $node, 0));
		self::assertSame(StuckResult::NONE, $this->detector->check(2, $position, self::SPEED, $node, 3000));
		self::assertSame(StuckResult::TIMED_OUT, $this->detector->check(3, $position, self::SPEED, $node, 3200));
	}

	public function testANewNodeStartsItsTimeOver() : void{
		$position = new Vector3(0, 64, 0);
		$this->detector->check(1, $position, self::SPEED, new Vector3(10, 64, 0), 0);
		$this->detector->check(2, $position, self::SPEED, new Vector3(10, 64, 0), 3000);

		self::assertSame(StuckResult::NONE, $this->detector->check(3, $position, self::SPEED, new Vector3(11, 64, 0), 3200));
		self::assertSame(StuckResult::NONE, $this->detector->check(4, $position, self::SPEED, new Vector3(11, 64, 0), 3300));
	}

	public function testResetForgetsTheTimeSpent() : void{
		$node = new Vector3(10, 64, 0);
		$position = new Vector3(0, 64, 0);
		$this->detector->check(1, $position, self::SPEED, $node, 0);
		$this->detector->check(2, $position, self::SPEED, $node, 3000);
		$this->detector->resetNode();

		self::assertSame(StuckResult::NONE, $this->detector->check(3, $position, self::SPEED, $node, 3200));
		self::assertSame(StuckResult::NONE, $this->detector->check(4, $position, self::SPEED, $node, 3300));
	}

	public function testAMobThatCantMoveNeverTimesOut() : void{
		$node = new Vector3(10, 64, 0);
		$position = new Vector3(0, 64, 0);
		$this->detector->check(1, $position, 0.0, $node, 0);
		self::assertSame(StuckResult::NONE, $this->detector->check(2, $position, 0.0, $node, 1000000));
	}

	/**
	 * Calls check() until it answers, at most $limit times.
	 *
	 * @return array{StuckResult, int} the result and how many calls it took
	 */
	private function checkUntilAnswered(Vector3 $node, Vector3 $position, int $limit) : array{
		for ($call = 1; $call <= $limit; ++$call) {
			$result = $this->detector->check($call, $position, self::SPEED, $node, 0);
			if ($result !== StuckResult::NONE) {
				return [$result, $call];
			}
		}
		return [StuckResult::NONE, $limit];
	}

	public function testBlockedWhenItNeverGetsCloserToTheNode() : void{
		[$result, $calls] = $this->checkUntilAnswered(new Vector3(10, 64, 0), new Vector3(0, 64, 0), 200);

		self::assertSame(StuckResult::BLOCKED, $result);
		self::assertSame(StuckDetector::BLOCKED_CHECKS + 2, $calls, "one to see the node, then the checks without progress");
	}

	public function testGettingCloserIsNeverBlocked() : void{
		$node = new Vector3(100, 64, 0);
		for ($i = 0; $i < 300; ++$i) {
			self::assertSame(StuckResult::NONE, $this->detector->check($i, new Vector3($i * 0.1, 64, 0), self::SPEED, $node, 0));
		}
	}

	public function testSlowProgressStillCounts() : void{
		$node = new Vector3(100, 64, 0);
		//0.06 closer per check, a little over the epsilon
		for ($i = 0; $i < 300; ++$i) {
			self::assertSame(StuckResult::NONE, $this->detector->check($i, new Vector3($i * 0.06, 64, 0), self::SPEED, $node, 0));
		}
	}

	public function testPushedBackAndForthDoesNotCountAsProgress() : void{
		$node = new Vector3(10, 64, 0);
		$answered = StuckResult::NONE;
		for ($i = 0; $i < 100 && $answered === StuckResult::NONE; ++$i) {
			//wobbling around 5 blocks from it
			$answered = $this->detector->check($i, new Vector3(5 - ($i % 2) * 0.03, 64, 0), self::SPEED, $node, 0);
		}
		self::assertSame(StuckResult::BLOCKED, $answered);
	}

	public function testANewNodeStartsProgressOver() : void{
		$position = new Vector3(0, 64, 0);
		for ($i = 0; $i < StuckDetector::BLOCKED_CHECKS; ++$i) {
			$this->detector->check($i, $position, self::SPEED, new Vector3(10, 64, 0), 0);
		}

		[$result, $calls] = $this->checkUntilAnswered(new Vector3(11, 64, 0), $position, 200);
		self::assertSame(StuckResult::BLOCKED, $result);
		self::assertSame(StuckDetector::BLOCKED_CHECKS + 2, $calls);
	}

	public function testBlockedStartsOverAfterItIsReported() : void{
		$node = new Vector3(10, 64, 0);
		$position = new Vector3(0, 64, 0);
		$this->checkUntilAnswered($node, $position, 200);

		[$result, $calls] = $this->checkUntilAnswered($node, $position, 200);
		self::assertSame(StuckResult::BLOCKED, $result);
		self::assertSame(StuckDetector::BLOCKED_CHECKS + 2, $calls);
	}
}
