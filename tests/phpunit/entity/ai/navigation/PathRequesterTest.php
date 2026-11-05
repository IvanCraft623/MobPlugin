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

use IvanCraft623\Pathfinder\Node;
use IvanCraft623\Pathfinder\Path;
use IvanCraft623\Pathfinder\PathCursor;
use IvanCraft623\Pathfinder\PathResult;
use pocketmine\math\Vector3;
use pocketmine\promise\Promise;

final class PathRequesterTest extends NavigationTestCase{
	private FakeSearchExecutor $executor;

	private PathRequester $requester;

	private ?PathCursor $current = null;

	private int $searchStarts = 0;

	protected function setUp() : void{
		$this->executor = new FakeSearchExecutor();
		$this->current = null;
		$this->searchStarts = 0;
		$this->requester = new PathRequester(
			$this->executor,
			new RepathPolicy(),
			fn() : ?PathCursor => $this->current,
			fn() : SearchSetup => self::searchSetup(),
			function() : void{
				++$this->searchStarts;
			}
		);
	}

	/**
	 * @phpstan-param Promise<PathCursor> $promise
	 * @param PathCursor|null $result set when the request completes
	 */
	private static function watch(Promise $promise, ?PathCursor &$result, bool &$rejected = false) : void{
		$promise->onCompletion(function(PathCursor $cursor) use (&$result) : void{
			$result = $cursor;
		}, function() use (&$rejected) : void{
			$rejected = true;
		});
	}

	/**
	 * Requests a path to x=$toX and finishes the search.
	 */
	private function pathTo(int $toX) : PathCursor{
		$result = null;
		self::watch($this->requester->request(new Vector3($toX, 64, 0), 1, $this->current), $result);
		$this->executor->finish(self::path(0, $toX));
		self::assertNotNull($result);
		$this->current = $result;
		return $result;
	}

	public function testSearchesFromTheMob() : void{
		$cursor = $this->pathTo(10);

		self::assertCount(1, $this->executor->requests);
		self::assertFalse($this->executor->requests[0]->startAtNode);
		self::assertSame(1, $this->searchStarts);
		self::assertSame(10, $cursor->getPath()->getEndNode()?->x());
	}

	public function testCurrentPathAnswersForTheSameTarget() : void{
		$cursor = $this->pathTo(10);

		$result = null;
		self::watch($this->requester->request(new Vector3(10.5, 64.2, 0.5), 1, $cursor), $result);

		self::assertSame($cursor, $result);
		self::assertCount(1, $this->executor->requests);
		self::assertSame(1, $this->searchStarts);
	}

	public function testRequestsDuringASearchShareIt() : void{
		$first = $second = null;
		self::watch($this->requester->request(new Vector3(10, 64, 0), 1, null), $first);
		self::watch($this->requester->request(new Vector3(30, 64, 0), 1, null), $second);
		self::assertTrue($this->requester->isPending());
		self::assertCount(1, $this->executor->requests);
		self::assertSame(1, $this->searchStarts);

		$this->executor->finish(self::path(0, 10));
		self::assertNotNull($first);
		self::assertSame($first, $second);
		self::assertFalse($this->requester->isPending());
	}

	public function testCancelledSearchesNeverResolve() : void{
		$result = null;
		self::watch($this->requester->request(new Vector3(10, 64, 0), 1, null), $result);
		$this->requester->cancel();
		self::assertSame(1, $this->executor->cancelled);
		self::assertFalse($this->requester->isPending());

		$this->executor->finish(self::path(0, 10));
		self::assertNull($result);
		self::assertFalse($this->requester->isPending());
	}

	public function testMovedTargetExtendsTheCurrentPath() : void{
		$cursor = $this->pathTo(10);
		for ($i = 0; $i < 3; ++$i) {
			$cursor->advance();
		}

		$result = null;
		self::watch($this->requester->request(new Vector3(12, 64, 0), 1, $cursor), $result);
		$extension = $this->executor->requests[1];
		self::assertTrue($extension->startAtNode);
		self::assertSame(10, $extension->start->getFloorX());

		$this->executor->finish(self::path(10, 12));
		self::assertNotNull($result);
		self::assertSame(13, $result->getPath()->getNodeCount());
		self::assertSame(3, $result->getNextNodeIndex(), "keeps the progress");
		self::assertSame(12, $result->getPath()->getEndNode()?->x());
	}

	public function testFailedExtensionSearchesFromTheMob() : void{
		$cursor = $this->pathTo(10);

		$result = null;
		self::watch($this->requester->request(new Vector3(12, 64, 0), 1, $cursor), $result);
		$this->executor->finish(self::path(10, 11, PathResult::BLOCKED));
		self::assertCount(3, $this->executor->requests);
		self::assertFalse($this->executor->requests[2]->startAtNode);

		$this->executor->finish(self::path(0, 12));
		self::assertNotNull($result);
		self::assertSame(13, $result->getPath()->getNodeCount());
	}

	public function testExtensionOfAPathThatChangedIsDiscarded() : void{
		$cursor = $this->pathTo(10);

		$result = null;
		self::watch($this->requester->request(new Vector3(12, 64, 0), 1, $cursor), $result);
		$this->current = self::cursor(0, 10);
		$this->executor->finish(self::path(10, 12));

		self::assertNull($result);
		self::assertFalse($this->executor->requests[2]->startAtNode, "searched from the mob");
	}

	public function testBlockChangeRepairsAroundTheInvalidNodes() : void{
		$cursor = $this->pathTo(30);
		self::assertTrue($this->requester->invalidate($cursor, 15, 64, 0));

		$dropped = false;
		$result = null;
		$promise = $this->requester->recompute($cursor, 100, function() use (&$dropped) : void{
			$dropped = true;
		});
		self::assertNotNull($promise);
		self::watch($promise, $result);
		self::assertFalse($dropped, "keeps walking the path meanwhile");
		$bridge = $this->executor->requests[1];
		self::assertSame([13, 17], [$bridge->start->getFloorX(), $bridge->target->getFloorX()], "the ring of nodes 14 to 16 holds the block");
		self::assertSame(0, $bridge->reachRange);

		$this->executor->finish(new Path(
			[new Node(13, 64, 0), new Node(14, 64, 1), new Node(15, 64, 1), new Node(16, 64, 1), new Node(17, 64, 0)],
			new Vector3(17, 64, 0),
			PathResult::REACHED
		));
		self::assertNotNull($result);
		self::assertSame(31, $result->getPath()->getNodeCount(), "three nodes replaced by as many");
		self::assertSame(30, $result->getPath()->getEndNode()?->x(), "the rest of the old path is kept");
		self::assertSame(1, $result->getPath()->getNode(15)->z());
	}

	public function testInvalidationsOfOnePathAreMerged() : void{
		$cursor = $this->pathTo(30);
		$this->requester->invalidate($cursor, 10, 64, 0);
		$this->requester->invalidate($cursor, 20, 64, 0);

		$this->requester->recompute($cursor, 100, static function() : void{
		});
		$bridge = $this->executor->requests[1];
		self::assertSame([8, 22], [$bridge->start->getFloorX(), $bridge->target->getFloorX()]);
	}

	public function testChangesOutsideThePathAreIgnored() : void{
		$cursor = $this->pathTo(30);
		self::assertFalse($this->requester->invalidate($cursor, 15, 64, 9));
	}

	public function testRecomputeWithoutInvalidationSearchesFromTheMob() : void{
		$cursor = $this->pathTo(30);

		$dropped = false;
		$this->requester->recompute($cursor, 100, function() use (&$dropped) : void{
			$dropped = true;
		});
		self::assertTrue($dropped);
		self::assertFalse($this->executor->requests[1]->startAtNode);
	}

	public function testRecomputeIsRateLimited() : void{
		$cursor = $this->pathTo(30);
		$this->requester->recompute($cursor, 100, static function() : void{
		});
		$this->executor->finish($cursor->getPath());

		self::assertNull($this->requester->recompute($cursor, 100 + PathRequester::RECOMPUTE_INTERVAL, static function() : void{
		}));
		self::assertTrue($this->requester->hasDelayedRecompute());

		self::assertNotNull($this->requester->recompute($cursor, 101 + PathRequester::RECOMPUTE_INTERVAL, static function() : void{
		}));
		self::assertFalse($this->requester->hasDelayedRecompute());
	}

	public function testRepairWaitsForTheRunningSearch() : void{
		$cursor = $this->pathTo(30);
		$this->requester->invalidate($cursor, 15, 64, 0);
		$this->requester->request(new Vector3(40, 64, 0), 1, null);

		self::assertNull($this->requester->recompute($cursor, 100, static function() : void{
		}));
		self::assertTrue($this->requester->hasDelayedRecompute());
	}
}
