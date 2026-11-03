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
use IvanCraft623\Pathfinder\PathResult;
use pocketmine\math\Vector3;
use pocketmine\promise\Promise;

final class PathRequesterTest extends NavigationTestCase{
	private FakeSearchExecutor $executor;

	private PathRequester $requester;

	private ?Path $current = null;

	private bool $canSearch = true;

	private int $searchStarts = 0;

	protected function setUp() : void{
		$this->executor = new FakeSearchExecutor();
		$this->current = null;
		$this->canSearch = true;
		$this->searchStarts = 0;
		$this->requester = new PathRequester(
			$this->executor,
			new RepathPolicy(),
			fn() : ?Path => $this->current,
			fn() : bool => $this->canSearch,
			fn() : SearchSetup => self::searchSetup(),
			function() : void{
				++$this->searchStarts;
			}
		);
	}

	/**
	 * @phpstan-param Promise<Path> $promise
	 * @param Path|null $result set when the request completes
	 */
	private static function watch(Promise $promise, ?Path &$result, bool &$rejected = false) : void{
		$promise->onCompletion(function(Path $path) use (&$result) : void{
			$result = $path;
		}, function() use (&$rejected) : void{
			$rejected = true;
		});
	}

	/**
	 * Requests a path to x=$toX and finishes the search.
	 */
	private function pathTo(int $toX) : Path{
		$result = null;
		self::watch($this->requester->request(new Vector3($toX, 64, 0), 1, $this->current), $result);
		$this->executor->finish(self::path(0, $toX));
		self::assertNotNull($result);
		$this->current = $result;
		return $result;
	}

	public function testSearchesFromTheMob() : void{
		$path = $this->pathTo(10);

		self::assertCount(1, $this->executor->requests);
		self::assertFalse($this->executor->requests[0]->startAtNode);
		self::assertSame(1, $this->searchStarts);
		self::assertSame(10, $path->getEndNode()?->x());
	}

	public function testCurrentPathAnswersForTheSameTarget() : void{
		$path = $this->pathTo(10);

		$result = null;
		self::watch($this->requester->request(new Vector3(10.5, 64.2, 0.5), 1, $path), $result);

		self::assertSame($path, $result);
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

	public function testRejectedWhenTheMobCantSearch() : void{
		$this->canSearch = false;
		$result = null;
		$rejected = false;
		self::watch($this->requester->request(new Vector3(10, 64, 0), 1, null), $result, $rejected);

		self::assertTrue($rejected);
		self::assertCount(0, $this->executor->requests);
	}

	public function testMovedTargetExtendsTheCurrentPath() : void{
		$path = $this->pathTo(10);
		$path->setNextNodeIndex(3);

		$result = null;
		self::watch($this->requester->request(new Vector3(12, 64, 0), 1, $path), $result);
		$extension = $this->executor->requests[1];
		self::assertTrue($extension->startAtNode);
		self::assertSame(10, $extension->start->getFloorX());

		$this->executor->finish(self::path(10, 12));
		self::assertNotNull($result);
		self::assertSame(13, $result->getNodeCount());
		self::assertSame(3, $result->getNextNodeIndex(), "keeps the progress");
		self::assertSame(12, $result->getEndNode()?->x());
	}

	public function testFailedExtensionSearchesFromTheMob() : void{
		$path = $this->pathTo(10);

		$result = null;
		self::watch($this->requester->request(new Vector3(12, 64, 0), 1, $path), $result);
		$this->executor->finish(self::path(10, 11, PathResult::BLOCKED));
		self::assertCount(3, $this->executor->requests);
		self::assertFalse($this->executor->requests[2]->startAtNode);

		$this->executor->finish(self::path(0, 12));
		self::assertNotNull($result);
		self::assertSame(13, $result->getNodeCount());
	}

	public function testExtensionOfAPathThatChangedIsDiscarded() : void{
		$path = $this->pathTo(10);

		$result = null;
		self::watch($this->requester->request(new Vector3(12, 64, 0), 1, $path), $result);
		$this->current = self::path(0, 10);
		$this->executor->finish(self::path(10, 12));

		self::assertNull($result);
		self::assertFalse($this->executor->requests[2]->startAtNode, "searched from the mob");
	}

	public function testBlockChangeRepairsAroundTheInvalidNodes() : void{
		$path = $this->pathTo(30);
		self::assertTrue($this->requester->invalidate($path, 15, 64, 0));

		$dropped = false;
		$result = null;
		$promise = $this->requester->recompute($path, 100, function() use (&$dropped) : void{
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
		self::assertSame(31, $result->getNodeCount(), "three nodes replaced by as many");
		self::assertSame(30, $result->getEndNode()?->x(), "the rest of the old path is kept");
		self::assertSame(1, $result->getNode(15)->z());
	}

	public function testInvalidationsOfOnePathAreMerged() : void{
		$path = $this->pathTo(30);
		$this->requester->invalidate($path, 10, 64, 0);
		$this->requester->invalidate($path, 20, 64, 0);

		$this->requester->recompute($path, 100, static function() : void{
		});
		$bridge = $this->executor->requests[1];
		self::assertSame([8, 22], [$bridge->start->getFloorX(), $bridge->target->getFloorX()]);
	}

	public function testChangesOutsideThePathAreIgnored() : void{
		$path = $this->pathTo(30);
		self::assertFalse($this->requester->invalidate($path, 15, 64, 9));
	}

	public function testRecomputeWithoutInvalidationSearchesFromTheMob() : void{
		$path = $this->pathTo(30);

		$dropped = false;
		$this->requester->recompute($path, 100, function() use (&$dropped) : void{
			$dropped = true;
		});
		self::assertTrue($dropped);
		self::assertFalse($this->executor->requests[1]->startAtNode);
	}

	public function testRecomputeIsRateLimited() : void{
		$path = $this->pathTo(30);
		$this->requester->recompute($path, 100, static function() : void{
		});
		$this->executor->finish($path);

		self::assertNull($this->requester->recompute($path, 100 + PathRequester::RECOMPUTE_INTERVAL, static function() : void{
		}));
		self::assertTrue($this->requester->hasDelayedRecompute());

		self::assertNotNull($this->requester->recompute($path, 101 + PathRequester::RECOMPUTE_INTERVAL, static function() : void{
		}));
		self::assertFalse($this->requester->hasDelayedRecompute());
	}

	public function testRepairWaitsForTheRunningSearch() : void{
		$path = $this->pathTo(30);
		$this->requester->invalidate($path, 15, 64, 0);
		$this->requester->request(new Vector3(40, 64, 0), 1, null);

		self::assertNull($this->requester->recompute($path, 100, static function() : void{
		}));
		self::assertTrue($this->requester->hasDelayedRecompute());
	}
}
