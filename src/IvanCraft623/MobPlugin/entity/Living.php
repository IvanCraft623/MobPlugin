<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\entity;

use IvanCraft623\MobPlugin\MobPlugin;
use IvanCraft623\MobPlugin\inventory\MobInventory;

use pocketmine\entity\Living as PMLiving;
use pocketmine\item\Item;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\utils\Random;

abstract class Living extends PMLiving {
	//TODO!

	protected Random $random;

	protected float $defaultSpeed = 1.0;

	protected float $speed;

	protected float $maxUpStep = 0.6;

	protected MobInventory $inventory;

	public function __construct(CompoundTag $nbt) {
		parent::__construct($nbt);

		$this->random = MobPlugin::getInstance()->getRandom();
		$this->speed = $this->defaultSpeed;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->inventory = new MobInventory($this);
		$syncHeldItem = function() : void{
			foreach($this->getViewers() as $viewer){
				$viewer->getNetworkSession()->onMobMainHandItemChange($this);
			}
		};
		$this->inventory->getListeners()->add(new CallbackInventoryListener(
			function(Inventory $unused, int $slot, Item $unused2) use ($syncHeldItem) : void{
				if($slot === $this->inventory->getHeldItemIndex()){
					$syncHeldItem();
				}
			},
			function(Inventory $unused, array $oldItems) use ($syncHeldItem) : void{
				if(array_key_exists($this->inventory->getHeldItemIndex(), $oldItems)){
					$syncHeldItem();
				}
			}
		));
		$inventoryTag = $nbt->getListTag("Inventory");
		if($inventoryTag !== null){
			$inventoryItems = [];
			$armorInventoryItems = [];

			/** @var CompoundTag $item */
			foreach($inventoryTag as $i => $item){
				$slot = $item->getByte("Slot");
				if($slot >= 0 && $slot < $this->inventory->getSize()){ //Inventory
					$inventoryItems[$slot] = Item::nbtDeserialize($item);
				}elseif($slot >= 100 && $slot < 104){ //Armor
					$armorInventoryItems[$slot - 100] = Item::nbtDeserialize($item);
				}
			}

			self::populateInventoryFromListTag($this->inventory, $inventoryItems);
			self::populateInventoryFromListTag($this->armorInventory, $armorInventoryItems);
		}
	}

	/**
	 * @param Item[] $items
	 * @phpstan-param array<int, Item> $items
	 */
	private static function populateInventoryFromListTag(Inventory $inventory, array $items): void{
		$listeners = $inventory->getListeners()->toArray();
		$inventory->getListeners()->clear();

		$inventory->setContents($items);

		$inventory->getListeners()->add(...$listeners);
	}

	public function getRandom(): Random {
		return $this->random;
	}

	public function getDefaultSpeed(): float {
		return $this->defaultSpeed;
	}

	public function getSpeed(): float {
		return $this->speed;
	}

	public function setSpeed(float $speed): void {
		$this->speed = $speed;
	}

	public function getMaxUpStep(): float {
		return $this->maxUpStep;
	}

	public function getInventory(): MobInventory{
		return $this->inventory;
	}

	public function canAttack(PMLiving $target): bool {
		return true;
	}

	public function canSee(Entity $entity): bool{
		$start = $this->getEyePos();
		$end = $entity->getEyePos();
		$directionVector = $end->subtractVector($start)->normalize();
		if ($directionVector->lengthSquared() > 0) {
			foreach(VoxelRayTrace::betweenPoints($start, $end) as $vector3){
				$block = $this->getWorld()->getBlockAt($vector3->x, $vector3->y, $vector3->z);

				$blockHitResult = $block->calculateIntercept($start, $end);
				if(!$block->isTransparent() && $blockHitResult !== null){
					return false;
				}
			}
		}
		return true;
	}

	public function getDrops(): array {
		return array_filter(array_merge(
			$this->inventory !== null ? array_values($this->inventory->getContents()) : [],
			$this->armorInventory !== null ? array_values($this->armorInventory->getContents()) : []
		), function(Item $item): bool{ return !$item->hasEnchantment(VanillaEnchantments::VANISHING()); });
	}

	public function saveNBT(): CompoundTag {
		$nbt = parent::saveNBT();

		$inventoryTag = new ListTag([], NBT::TAG_Compound);
		$nbt->setTag("Inventory", $inventoryTag);
		if($this->inventory !== null){
			//Normal inventory
			for($slot = 0; $slot < $this->inventory->getSize(); ++$slot){
				$item = $this->inventory->getItem($slot);
				if(!$item->isNull()){
					$inventoryTag->push($item->nbtSerialize($slot));
				}
			}

			//Armor
			for($slot = 100; $slot < 104; ++$slot){
				$item = $this->armorInventory->getItem($slot - 100);
				if(!$item->isNull()){
					$inventoryTag->push($item->nbtSerialize($slot));
				}
			}

			return $nbt;
		}
	}
}