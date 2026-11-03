<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\kostamax27\loottable\looting;

use IvanCraft623\MobPlugin\libs\_f0e3cb97e8f8441b\kostamax27\loottable\LootContext;

final class NullLootingEvaluator implements LootingEvaluator{

	private static self $instance;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function evaluate(LootContext $context) : int{
		return 0;
	}
}