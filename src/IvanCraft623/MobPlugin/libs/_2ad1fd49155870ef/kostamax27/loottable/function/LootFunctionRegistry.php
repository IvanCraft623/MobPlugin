<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\function;

use Closure;
use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\IntRange;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootData;
use IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable\LootTableFactory;
use pocketmine\block\utils\BannerPatternLayer;
use pocketmine\block\utils\BannerPatternType;
use pocketmine\block\utils\DyeColor;
use pocketmine\crafting\FurnaceRecipeManager;
use pocketmine\crafting\FurnaceType;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\data\bedrock\SuspiciousStewTypeIdMap;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\PotionType;
use pocketmine\Server;
use UnitEnum;
use function get_debug_type;
use function is_array;
use function is_string;
use function is_int;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;

final class LootFunctionRegistry{

	// vanilla functions with no pocketmine equivalent, registered as no-ops so vanilla tables load
	public const UNSUPPORTED_VANILLA = ["set_data_from_color_index", "random_block_state", "set_actor_id", "set_ominous_bottle_amplifier",
		"set_armor_trim", "exploration_map", "fill_container", "trader_material_type", "explosion_decay", "set_spawn_egg"];

	public static function createDefault(?FurnaceRecipeManager $furnace_recipe_manager = null) : self{
		$registry = new self();
		$registry->register("set_count", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new SetCountLootFunction(
			$data->intRangeOr("count", IntRange::exact(0)),
			$data->boolOr("add", false)
		));
		$registry->register("looting_enchant", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new LootingEnchantLootFunction(
			$factory->looting_evaluator,
			$data->intRangeOr("count", IntRange::exact(0)),
			$data->intNullable("limit")
		));
		$registry->register("set_data", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new SetDataLootFunction(
			$factory->item_resolver,
			$data->intRangeOr("data", IntRange::exact(0))
		));
		$registry->register("random_aux_value", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new SetDataLootFunction(
			$factory->item_resolver,
			$data->intRangeOr("values", IntRange::exact(0))
		));
		$registry->register("set_stew_effect", static function(LootData $data, LootTableFactory $factory) : LootFunction{
			$map = SuspiciousStewTypeIdMap::getInstance();
			$types = [];
			foreach($data->objects("effects") as $effect){
				$id = $effect->int("id");
				$types[] = $map->fromId($id) ?? throw new InvalidArgumentException("'{$effect->at("id")}' names an unknown stew effect {$id}");
			}
			return new SetStewEffectLootFunction($types);
		});
		$registry->register("set_name", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new SetNameLootFunction($data->string("name")));
		$registry->register("set_lore", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new SetLoreLootFunction($data->strings("lore")));
		$registry->register("set_damage", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new SetDamageLootFunction($data->floatRange("damage")));
		$registry->register("furnace_smelt", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new FurnaceSmeltLootFunction(
			$furnace_recipe_manager ?? Server::getInstance()->getCraftingManager()->getFurnaceRecipeManager(FurnaceType::FURNACE)
		));
		$registry->register("enchant_randomly", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new EnchantRandomlyLootFunction($factory->item_enchanter, $data->boolOr("treasure", false)));
		$registry->register("enchant_book_for_trading", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new EnchantRandomlyLootFunction($factory->item_enchanter)); // trade cost keys are ignored
		$registry->register("enchant_with_levels", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new EnchantWithLevelsLootFunction(
			$factory->item_enchanter,
			$data->intRange("levels"),
			$data->boolOr("treasure", false)
		));
		$registry->register("enchant_random_gear", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new EnchantRandomGearLootFunction(
			new EnchantWithLevelsLootFunction($factory->item_enchanter, new IntRange(5, 22)),
			$data->floatOr("chance", 1.0)
		));
		$registry->register("specific_enchants", static function(LootData $data, LootTableFactory $factory) : LootFunction{
			$parser = StringToEnchantmentParser::getInstance();
			$enchants = [];
			foreach($data->values("enchants") as [$path, $value]){
				if(is_string($value)){
					$value = ["id" => $value];
				}
				is_array($value) || throw new InvalidArgumentException("'{$path}' must be a string or an object, got " . get_debug_type($value));
				$entry = new LootData($value, $path);
				$id = $entry->string("id");
				$enchants[] = new SpecificEnchant(
					$parser->parse($id) ?? throw new InvalidArgumentException("'{$entry->at("id")}' names an unknown enchantment '{$id}'"),
					$entry->intRangeOr("level", IntRange::exact(1))
				);
			}
			return new SpecificEnchantsLootFunction($factory->item_enchanter, $enchants);
		});
		$registry->register("random_dye", static fn(LootData $data, LootTableFactory $factory) : LootFunction => RandomDyeLootFunction::instance());
		$registry->register("set_potion", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new SetPotionLootFunction(self::potionType($data, "id")));
		$registry->register("set_book_contents", static fn(LootData $data, LootTableFactory $factory) : LootFunction => new SetBookContentsLootFunction(
			$data->strings("pages"),
			$data->stringNullable("title"),
			$data->stringNullable("author")
		));
		$registry->register("set_banner_details", static function(LootData $data, LootTableFactory $factory) : LootFunction{
			$type = $data->intOr("type", 0);
			if($type === 1){
				return SetBannerDetailsLootFunction::ominous();
			}
			$type === 0 || throw new InvalidArgumentException("'{$data->at("type")}' must be 0 or 1, got {$type}");
			$patterns = [];
			foreach($data->objects("patterns") as $pattern){
				$patterns[] = new BannerPatternLayer(self::bannerPattern($pattern, "pattern"), self::dyeColor($pattern, "color"));
			}
			return new SetBannerDetailsLootFunction($data->has("base_color") ? self::dyeColor($data, "base_color") : DyeColor::WHITE, $patterns);
		});
		foreach(self::UNSUPPORTED_VANILLA as $identifier){
			$registry->register($identifier, static fn(LootData $data, LootTableFactory $factory) : LootFunction => NoopLootFunction::instance());
		}
		return $registry;
	}

	/** @var array<string, Closure(LootData, LootTableFactory) : LootFunction> */
	private array $registered = [];

	public function __construct(){
	}

	/**
	 * @param Closure(LootData, LootTableFactory) : LootFunction $parser
	 */
	public function register(string $identifier, Closure $parser) : void{
		$identifier = self::normalize($identifier);
		isset($this->registered[$identifier]) && throw new InvalidArgumentException("Function with the identifier \"{$identifier}\" is already registered");
		$this->registered[$identifier] = $parser;
	}

	/**
	 * @return Closure(LootData, LootTableFactory) : LootFunction
	 */
	public function unregister(string $identifier) : Closure{
		$identifier = self::normalize($identifier);
		$parser = $this->registered[$identifier] ?? throw new InvalidArgumentException("Function with the identifier \"{$identifier}\" is not registered");
		unset($this->registered[$identifier]);
		return $parser;
	}

	/**
	 * @return Closure(LootData, LootTableFactory) : LootFunction
	 */
	public function get(string $identifier) : Closure{
		return $this->registered[self::normalize($identifier)] ?? throw new InvalidArgumentException("Function with the identifier \"{$identifier}\" is not registered");
	}

	public static function normalize(string $identifier) : string{
		return str_starts_with($identifier, "minecraft:") ? substr($identifier, strlen("minecraft:")) : $identifier;
	}

	private static function potionType(LootData $data, string $key) : PotionType{
		$name = self::normalize($data->string($key));
		$name = match($name){
			"nightvision" => "night_vision",
			"long_nightvision" => "long_night_vision",
			default => $name
		};
		return self::caseNamed(PotionType::cases(), $name) ?? throw new InvalidArgumentException("'{$data->at($key)}' names an unsupported potion '{$name}'");
	}

	private static function bannerPattern(LootData $data, string $key) : BannerPatternType{
		$name = $data->string($key);
		return self::caseNamed(BannerPatternType::cases(), $name) ?? throw new InvalidArgumentException("'{$data->at($key)}' names an unknown banner pattern '{$name}'");
	}

	private static function dyeColor(LootData $data, string $key) : DyeColor{
		$value = $data->raw($key);
		if(is_int($value)){
			return DyeColorIdMap::getInstance()->fromId($value) ?? throw new InvalidArgumentException("'{$data->at($key)}' names an unknown dye color id {$value}");
		}
		$name = $data->string($key);
		return self::caseNamed(DyeColor::cases(), $name) ?? throw new InvalidArgumentException("'{$data->at($key)}' names an unknown dye color '{$name}'");
	}

	/**
	 * @template T of UnitEnum
	 * @param list<T> $cases
	 * @return T|null
	 */
	private static function caseNamed(array $cases, string $name) : ?UnitEnum{
		$name = strtoupper($name);
		foreach($cases as $case){
			if($case->name === $name){
				return $case;
			}
		}
		return null;
	}
}