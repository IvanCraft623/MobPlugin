<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\condition;

use Closure;
use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\item\ItemNames;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootData;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\LootTableFactory;
use function str_starts_with;
use function strlen;
use function substr;

final class LootConditionRegistry{

	// vanilla conditions pocketmine cannot answer, registered as always-false so vanilla tables load
	public const UNSUPPORTED_VANILLA = ["passenger_of_entity", "bool_property", "biome_has_tag", "random_regional_difficulty_chance"];

	public static function createDefault() : self{
		$registry = new self();
		$registry->register("killed_by_player", static fn(LootData $data, LootTableFactory $factory) : LootCondition => KilledByPlayerLootCondition::instance());
		$registry->register("killed_by_player_or_pets", static fn(LootData $data, LootTableFactory $factory) : LootCondition => KilledByPlayerLootCondition::instance()); // pocketmine has no tamed pets :(
		$registry->register("random_chance", static fn(LootData $data, LootTableFactory $factory) : LootCondition => new RandomChanceLootCondition($data->float("chance")));
		$registry->register("random_chance_with_looting", static fn(LootData $data, LootTableFactory $factory) : LootCondition => new RandomChanceWithLootingLootCondition(
			$factory->looting_evaluator,
			$data->float("chance"),
			$data->floatOr("looting_multiplier", 0.0)
		));
		$registry->register("random_difficulty_chance", static fn(LootData $data, LootTableFactory $factory) : LootCondition => new RandomDifficultyChanceLootCondition(
			$data->float("default_chance"),
			$data->floatNullable("peaceful"),
			$data->floatNullable("easy"),
			$data->floatNullable("normal"),
			$data->floatNullable("hard")
		));
		$registry->register("entity_properties", static function(LootData $data, LootTableFactory $factory) : LootCondition{
			$target = $data->stringOr("entity", EntityTarget::THIS->value);
			$properties = $data->object("properties");
			return new EntityPropertiesLootCondition(
				EntityTarget::tryFrom($target) ?? throw new InvalidArgumentException("'{$data->at("entity")}' must be one of 'this', 'killer', 'damager', got '{$target}'"),
				$properties->boolNullable("on_fire")
			);
		});
		$registry->register("killed_by_entity", static fn(LootData $data, LootTableFactory $factory) : LootCondition => new EntityTypeLootCondition($factory->entity_inspector, EntityTarget::KILLER, ItemNames::withVanillaNamespace($data->string("entity_type"))));
		$registry->register("entity_killed", static fn(LootData $data, LootTableFactory $factory) : LootCondition => new EntityTypeLootCondition($factory->entity_inspector, EntityTarget::THIS, ItemNames::withVanillaNamespace($data->string("entity_type"))));
		$registry->register("damaged_by_entity", static fn(LootData $data, LootTableFactory $factory) : LootCondition => new EntityTypeLootCondition($factory->entity_inspector, EntityTarget::DAMAGER, ItemNames::withVanillaNamespace($data->string("entity_type"))));
		$registry->register("has_variant", static fn(LootData $data, LootTableFactory $factory) : LootCondition => new EntityVariantLootCondition($factory->entity_inspector, false, $data->int("value")));
		$registry->register("has_mark_variant", static fn(LootData $data, LootTableFactory $factory) : LootCondition => new EntityVariantLootCondition($factory->entity_inspector, true, $data->int("value")));
		$registry->register("is_baby", static fn(LootData $data, LootTableFactory $factory) : LootCondition => new IsBabyLootCondition($factory->entity_inspector));
		foreach(self::UNSUPPORTED_VANILLA as $identifier){
			$registry->register($identifier, static fn(LootData $data, LootTableFactory $factory) : LootCondition => NeverLootCondition::instance());
		}
		return $registry;
	}

	/** @var array<string, Closure(LootData, LootTableFactory) : LootCondition> */
	private array $registered = [];

	public function __construct(){
	}

	/**
	 * @param Closure(LootData, LootTableFactory) : LootCondition $parser
	 */
	public function register(string $identifier, Closure $parser) : void{
		$identifier = self::normalize($identifier);
		isset($this->registered[$identifier]) && throw new InvalidArgumentException("Condition with the identifier \"{$identifier}\" is already registered");
		$this->registered[$identifier] = $parser;
	}

	/**
	 * @return Closure(LootData, LootTableFactory) : LootCondition
	 */
	public function unregister(string $identifier) : Closure{
		$identifier = self::normalize($identifier);
		$parser = $this->registered[$identifier] ?? throw new InvalidArgumentException("Condition with the identifier \"{$identifier}\" is not registered");
		unset($this->registered[$identifier]);
		return $parser;
	}

	/**
	 * @return Closure(LootData, LootTableFactory) : LootCondition
	 */
	public function get(string $identifier) : Closure{
		return $this->registered[self::normalize($identifier)] ?? throw new InvalidArgumentException("Condition with the identifier \"{$identifier}\" is not registered");
	}

	public static function normalize(string $identifier) : string{
		return str_starts_with($identifier, "minecraft:") ? substr($identifier, strlen("minecraft:")) : $identifier;
	}
}