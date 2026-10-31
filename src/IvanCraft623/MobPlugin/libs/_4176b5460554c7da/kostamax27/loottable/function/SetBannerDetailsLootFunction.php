<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootContext;
use pocketmine\block\utils\BannerPatternLayer;
use pocketmine\block\utils\BannerPatternType;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\Banner;
use pocketmine\item\Item;
use function count;

final class SetBannerDetailsLootFunction implements LootFunction{

	public const MAX_PATTERNS = 6;

	/**
	 * The banner carried by raid captains ("type": 1).
	 */
	public static function ominous() : self{
		return new self(DyeColor::WHITE, [
			new BannerPatternLayer(BannerPatternType::RHOMBUS, DyeColor::CYAN),
			new BannerPatternLayer(BannerPatternType::STRIPE_BOTTOM, DyeColor::LIGHT_GRAY),
			new BannerPatternLayer(BannerPatternType::STRIPE_CENTER, DyeColor::GRAY),
			new BannerPatternLayer(BannerPatternType::BORDER, DyeColor::LIGHT_GRAY),
			new BannerPatternLayer(BannerPatternType::STRIPE_MIDDLE, DyeColor::BLACK),
			new BannerPatternLayer(BannerPatternType::HALF_HORIZONTAL, DyeColor::LIGHT_GRAY),
			new BannerPatternLayer(BannerPatternType::CIRCLE, DyeColor::LIGHT_GRAY),
			new BannerPatternLayer(BannerPatternType::BORDER, DyeColor::BLACK)
		], false);
	}

	/**
	 * @param list<BannerPatternLayer> $patterns bottom layer first
	 * @param bool $limit whether to enforce the vanilla limit of 6 patterns
	 */
	public function __construct(
		readonly public DyeColor $base_color,
		readonly public array $patterns,
		bool $limit = true
	){
		!$limit || count($this->patterns) <= self::MAX_PATTERNS || throw new InvalidArgumentException("A banner may carry at most " . self::MAX_PATTERNS . " patterns, got " . count($this->patterns));
	}

	public function apply(Item $item, LootContext $context) : Item{
		if($item instanceof Banner){
			$item->setColor($this->base_color);
			$item->setPatterns($this->patterns);
		}
		return $item;
	}
}