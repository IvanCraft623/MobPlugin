<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable;

use InvalidArgumentException;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\condition\LootCondition;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\condition\LootConditionRegistry;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\enchant\ItemEnchanter;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\enchant\VanillaItemEnchanter;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entity\EntityInspector;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entity\DefaultEntityInspector;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry\EmptyLootEntry;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry\ItemLootEntry;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry\LootEntry;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry\LootEntryType;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry\LootTableLootEntry;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\entry\WeightedLootEntry;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function\ConditionalLootFunction;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function\LootFunction;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\function\LootFunctionRegistry;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\item\ChainedItemResolver;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\item\ItemResolver;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\looting\LootingEvaluator;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\looting\NullLootingEvaluator;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\pool\LootPool;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\pool\TieredLootPool;
use IvanCraft623\MobPlugin\libs\_4176b5460554c7da\kostamax27\loottable\pool\WeightedLootPool;
use pocketmine\utils\Filesystem;
use RuntimeException;
use function array_map;
use function count;

final class LootTableFactory{

	/**
	 * @param string|null $directory behaviour pack root to resolve "loot_table" entries against, or null to reject them
	 * @param LootingEvaluator|null $looting_evaluator source of looting levels, or null for a constant 0
	 */
	public static function createDefault(?string $directory = null, ?LootingEvaluator $looting_evaluator = null) : self{
		$factory = new self(
			LootFunctionRegistry::createDefault(),
			LootConditionRegistry::createDefault(),
			ChainedItemResolver::createDefault(),
			$looting_evaluator ?? NullLootingEvaluator::instance()
		);
		if($directory !== null){
			$factory->setTableResolver(new DirectoryLootTableResolver($directory, $factory));
		}
		return $factory;
	}

	private LootTableResolver $table_resolver;

	/**
	 * @param ItemResolver $item_resolver translates item names in "item" entries
	 * @param LootingEvaluator $looting_evaluator source of looting levels for looting_enchant and random_chance_with_looting
	 * @param ItemEnchanter $item_enchanter applies enchantments for enchant_randomly, enchant_with_levels and specific_enchants
	 * @param EntityInspector $entity_inspector answers entity type, variant and age conditions
	 */
	public function __construct(
		readonly public LootFunctionRegistry $function_registry,
		readonly public LootConditionRegistry $condition_registry,
		readonly public ItemResolver $item_resolver,
		readonly public LootingEvaluator $looting_evaluator,
		readonly public ItemEnchanter $item_enchanter = new VanillaItemEnchanter(),
		readonly public EntityInspector $entity_inspector = new DefaultEntityInspector()
	){
		$this->table_resolver = NullLootTableResolver::instance();
	}

	/**
	 * Sets how "loot_table" entries locate the table they name. Must be
	 * called before parsing tables containing such entries.
	 */
	public function setTableResolver(LootTableResolver $resolver) : void{
		$this->table_resolver = $resolver;
	}

	/**
	 * Reads and parses a loot table JSON file.
	 *
	 * @throws InvalidArgumentException if the file cannot be read or is not a valid loot table
	 */
	public function fromFile(string $path) : LootTable{
		try{
			return $this->fromJson(Filesystem::fileGetContents($path));
		}catch(InvalidArgumentException | RuntimeException $e){
			throw new InvalidArgumentException("Failed to parse loot table '{$path}': {$e->getMessage()}", $e->getCode(), $e);
		}
	}

	/**
	 * @throws InvalidArgumentException if $json is not a valid loot table
	 */
	public function fromJson(string $json) : LootTable{
		return $this->fromData(LootData::fromJson($json));
	}

	public function fromData(LootData $data) : LootTable{
		return new LootTable(array_map($this->parsePool(...), $data->objects("pools")));
	}

	public function parsePool(LootData $data) : LootPool{
		$conditions = $this->parseConditions($data->objects("conditions"));
		$tiers = $data->objectNullable("tiers");
		if($tiers !== null){
			return new TieredLootPool(
				array_map($this->parseEntry(...), $data->objects("entries")),
				$tiers->intOr("initial_range", 1),
				$tiers->intOr("bonus_rolls", 0),
				$tiers->floatOr("bonus_chance", 0.0),
				$conditions
			);
		}
		return new WeightedLootPool(
			array_map($this->parseWeightedEntry(...), $data->objects("entries")),
			$data->intRangeOr("rolls", IntRange::exact(1)),
			$data->floatRangeOr("bonus_rolls", FloatRange::exact(0.0)),
			$conditions
		);
	}

	public function parseWeightedEntry(LootData $data) : WeightedLootEntry{
		return new WeightedLootEntry(
			$this->parseEntry($data),
			$data->intOr("weight", 1),
			$data->intOr("quality", 0),
			$this->parseConditions($data->objects("conditions"))
		);
	}

	public function parseEntry(LootData $data) : LootEntry{
		$type = $data->stringOr("type", LootEntryType::ITEM->value);
		return match(LootEntryType::tryFrom($type) ?? throw new InvalidArgumentException("'{$data->at("type")}' must be one of 'item', 'loot_table', 'empty', got '{$type}'")){
			LootEntryType::ITEM => new ItemLootEntry(
				$this->item_resolver->resolve($name = $data->string("name")) ?? throw new InvalidArgumentException("'{$data->at("name")}' names an unknown item '{$name}'"),
				$this->parseFunctions($data->objects("functions"))
			),
			LootEntryType::LOOT_TABLE => new LootTableLootEntry($data->string("name"), $this->table_resolver, $this->parseFunctions($data->objects("functions"))),
			LootEntryType::EMPTY => EmptyLootEntry::instance()
		};
	}

	/**
	 * @param list<LootData> $functions
	 * @return list<LootFunction>
	 */
	public function parseFunctions(array $functions) : array{
		$result = [];
		foreach($functions as $data){
			$identifier = $data->string("function");
			try{
				$function = $this->function_registry->get($identifier)($data, $this);
			}catch(InvalidArgumentException $e){
				throw new InvalidArgumentException("'{$data->path}' ({$identifier}): {$e->getMessage()}", $e->getCode(), $e);
			}
			$conditions = $this->parseConditions($data->objects("conditions"));
			$result[] = count($conditions) === 0 ? $function : new ConditionalLootFunction($function, $conditions);
		}
		return $result;
	}

	/**
	 * @param list<LootData> $conditions
	 * @return list<LootCondition>
	 */
	public function parseConditions(array $conditions) : array{
		$result = [];
		foreach($conditions as $data){
			$identifier = $data->string("condition");
			try{
				$result[] = $this->condition_registry->get($identifier)($data, $this);
			}catch(InvalidArgumentException $e){
				throw new InvalidArgumentException("'{$data->path}' ({$identifier}): {$e->getMessage()}", $e->getCode(), $e);
			}
		}
		return $result;
	}
}