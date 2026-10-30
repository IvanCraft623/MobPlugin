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

namespace IvanCraft623\MobPlugin\loot;

use function array_keys;
use function count;
use function implode;
use function mb_strtoupper;

/**
 * The vanilla loot tables
 *
 * This class is generated automatically from source class {@link VanillaLootTablesInputs}. Do not modify it manually.
 * It must be regenerated whenever the source class is changed.
 * @see build/codegen/registry-interface.php
 */
final class VanillaLootTables{
	private static LootTable $_mARMADILLO_BRUSH;
	private static LootTable $_mARMOR_SET_CHAIN;
	private static LootTable $_mARMOR_SET_COPPER;
	private static LootTable $_mARMOR_SET_DIAMOND;
	private static LootTable $_mARMOR_SET_GOLD;
	private static LootTable $_mARMOR_SET_IRON;
	private static LootTable $_mARMOR_SET_LEATHER;
	private static LootTable $_mARMOR_STAND;
	private static LootTable $_mBLAZE;
	private static LootTable $_mBOAT;
	private static LootTable $_mBOGGED;
	private static LootTable $_mBOGGED_SHEAR;
	private static LootTable $_mBREEZE;
	private static LootTable $_mBROWN_MOOSHROOM_SHEAR;
	private static LootTable $_mCAMEL_HUSK;
	private static LootTable $_mCAT;
	private static LootTable $_mCAT_GIFT;
	private static LootTable $_mCHICKEN;
	private static LootTable $_mCOPPER_GOLEM;
	private static LootTable $_mCOPPER_GOLEM_SHEAR;
	private static LootTable $_mCOW;
	private static LootTable $_mCREEPER;
	private static LootTable $_mDOLPHIN;
	private static LootTable $_mDROWNED;
	private static LootTable $_mDROWNED_EQUIPMENT;
	private static LootTable $_mDROWNED_RANGED_EQUIPMENT;
	private static LootTable $_mDROWNED_RIDER_EQUIPMENT;
	private static LootTable $_mELDER_GUARDIAN;
	private static LootTable $_mEMPTY;
	private static LootTable $_mENDERMAN;
	private static LootTable $_mEVOCATION_ILLAGER;
	private static LootTable $_mFISH;
	private static LootTable $_mFOX_EQUIPMENT;
	private static LootTable $_mFROG;
	private static LootTable $_mGAMEPLAY_ENTITIES_MOOSHROOM_MILKING;
	private static LootTable $_mGAMEPLAY_ENTITIES_SNIFFER_SEEDS;
	private static LootTable $_mGAMEPLAY_FISHING;
	private static LootTable $_mGAMEPLAY_FISHING_FISH;
	private static LootTable $_mGAMEPLAY_FISHING_JUNGLE_FISH;
	private static LootTable $_mGAMEPLAY_FISHING_JUNGLE_JUNK;
	private static LootTable $_mGAMEPLAY_FISHING_JUNK;
	private static LootTable $_mGAMEPLAY_FISHING_TREASURE;
	private static LootTable $_mGAMEPLAY_JUNGLE_FISHING;
	private static LootTable $_mGHAST;
	private static LootTable $_mGLOW_SQUID;
	private static LootTable $_mGOAT;
	private static LootTable $_mGUARDIAN;
	private static LootTable $_mHOGLIN;
	private static LootTable $_mHORSE;
	private static LootTable $_mHUSK_RIDER;
	private static LootTable $_mIRON_GOLEM;
	private static LootTable $_mLLAMA;
	private static LootTable $_mMAGMA_CUBE;
	private static LootTable $_mMOOSHROOM;
	private static LootTable $_mMOOSHROOM_SHEAR;
	private static LootTable $_mNAUTILUS;
	private static LootTable $_mOCELOT;
	private static LootTable $_mPANDA;
	private static LootTable $_mPANDA_SNEEZE;
	private static LootTable $_mPARCHED;
	private static LootTable $_mPARROT;
	private static LootTable $_mPHANTOM;
	private static LootTable $_mPIG;
	private static LootTable $_mPIGLIN_BARTER;
	private static LootTable $_mPIGLIN_BRUTE_GEAR;
	private static LootTable $_mPIGLIN_GEAR_MELEE;
	private static LootTable $_mPIGLIN_GEAR_RANGED;
	private static LootTable $_mPIG_SADDLED;
	private static LootTable $_mPILLAGER;
	private static LootTable $_mPILLAGER_CAPTAIN;
	private static LootTable $_mPILLAGER_CAPTAIN_EQUIPMENT;
	private static LootTable $_mPILLAGER_GEAR;
	private static LootTable $_mPILLAGER_RAID;
	private static LootTable $_mPOLAR_BEAR;
	private static LootTable $_mPUFFERFISH;
	private static LootTable $_mRABBIT;
	private static LootTable $_mRAIDER_DROPS;
	private static LootTable $_mRAVAGER;
	private static LootTable $_mSADDLE;
	private static LootTable $_mSALMON_LARGE;
	private static LootTable $_mSALMON_NORMAL;
	private static LootTable $_mSEA_TURTLE;
	private static LootTable $_mSHEEP;
	private static LootTable $_mSHEEP_SHEAR;
	private static LootTable $_mSHEEP_SHEARED;
	private static LootTable $_mSHULKER;
	private static LootTable $_mSILVERFISH;
	private static LootTable $_mSKELETON;
	private static LootTable $_mSKELETON_GEAR;
	private static LootTable $_mSKELETON_HORSE;
	private static LootTable $_mSLIME;
	private static LootTable $_mSNOWMAN;
	private static LootTable $_mSNOW_GOLEM_SHEAR;
	private static LootTable $_mSPIDER;
	private static LootTable $_mSQUID;
	private static LootTable $_mSTRAY;
	private static LootTable $_mSTRIDER;
	private static LootTable $_mSTRIDER_SADDLED;
	private static LootTable $_mTROPICALFISH;
	private static LootTable $_mVEX_GEAR;
	private static LootTable $_mVINDICATION_ILLAGER;
	private static LootTable $_mVINDICATOR_CAPTAIN_EQUIPMENT;
	private static LootTable $_mVINDICATOR_GEAR;
	private static LootTable $_mVINDICATOR_RAID;
	private static LootTable $_mWARDEN;
	private static LootTable $_mWITCH;
	private static LootTable $_mWITHER_BOSS;
	private static LootTable $_mWITHER_SKELETON;
	private static LootTable $_mWITHER_SKELETON_GEAR;
	private static LootTable $_mWOLF;
	private static LootTable $_mZOGLIN;
	private static LootTable $_mZOMBIE;
	private static LootTable $_mZOMBIE_EQUIPMENT;
	private static LootTable $_mZOMBIE_HORSE;
	private static LootTable $_mZOMBIE_NAUTILUS;
	private static LootTable $_mZOMBIE_PIGMAN;
	private static LootTable $_mZOMBIE_PIGMAN_GEAR;
	private static LootTable $_mZOMBIE_RIDER;
	private static LootTable $_mZOMBIE_RIDER_EQUIPMENT;
	private static LootTable $_mZOMBIFIED_PIGLIN_RIDER_GEAR;

	/**
	 * @var LootTable[]
	 * @phpstan-var array<string, LootTable>
	 */
	private static array $members;

	private static bool $initialized = false;

	private function __construct(){
		//NOOP
	}

	/**
	 * Hack to allow ignoring PHPStan wrong type assignment error in one place instead of hundreds or thousands
	 * Assumes that the input value already matches the expected type. If not, a TypeError will be thrown on assignment.
	 *
	 * @phpstan-param \Closure(never) : LootTable $closure
	 */
	private static function unsafeAssign(\Closure $closure, LootTable $memberValue) : void{
		/**
		 * This type is not correct either (the param is actually a subtype of LootTable) but it's called
		 * unsafeAssign for a reason :)
		 * @phpstan-var \Closure(LootTable) : LootTable $closure
		 */
		$closure($memberValue);
	}

	/**
	 * @return \Closure[]
	 * @phpstan-return array<string, \Closure(never) : LootTable>
	 */
	private static function getInitAssigners() : array{
		return [
			"armadillo_brush" => fn(LootTable $v) => self::$_mARMADILLO_BRUSH = $v,
			"armor_set_chain" => fn(LootTable $v) => self::$_mARMOR_SET_CHAIN = $v,
			"armor_set_copper" => fn(LootTable $v) => self::$_mARMOR_SET_COPPER = $v,
			"armor_set_diamond" => fn(LootTable $v) => self::$_mARMOR_SET_DIAMOND = $v,
			"armor_set_gold" => fn(LootTable $v) => self::$_mARMOR_SET_GOLD = $v,
			"armor_set_iron" => fn(LootTable $v) => self::$_mARMOR_SET_IRON = $v,
			"armor_set_leather" => fn(LootTable $v) => self::$_mARMOR_SET_LEATHER = $v,
			"armor_stand" => fn(LootTable $v) => self::$_mARMOR_STAND = $v,
			"blaze" => fn(LootTable $v) => self::$_mBLAZE = $v,
			"boat" => fn(LootTable $v) => self::$_mBOAT = $v,
			"bogged" => fn(LootTable $v) => self::$_mBOGGED = $v,
			"bogged_shear" => fn(LootTable $v) => self::$_mBOGGED_SHEAR = $v,
			"breeze" => fn(LootTable $v) => self::$_mBREEZE = $v,
			"brown_mooshroom_shear" => fn(LootTable $v) => self::$_mBROWN_MOOSHROOM_SHEAR = $v,
			"camel_husk" => fn(LootTable $v) => self::$_mCAMEL_HUSK = $v,
			"cat" => fn(LootTable $v) => self::$_mCAT = $v,
			"cat_gift" => fn(LootTable $v) => self::$_mCAT_GIFT = $v,
			"chicken" => fn(LootTable $v) => self::$_mCHICKEN = $v,
			"copper_golem" => fn(LootTable $v) => self::$_mCOPPER_GOLEM = $v,
			"copper_golem_shear" => fn(LootTable $v) => self::$_mCOPPER_GOLEM_SHEAR = $v,
			"cow" => fn(LootTable $v) => self::$_mCOW = $v,
			"creeper" => fn(LootTable $v) => self::$_mCREEPER = $v,
			"dolphin" => fn(LootTable $v) => self::$_mDOLPHIN = $v,
			"drowned" => fn(LootTable $v) => self::$_mDROWNED = $v,
			"drowned_equipment" => fn(LootTable $v) => self::$_mDROWNED_EQUIPMENT = $v,
			"drowned_ranged_equipment" => fn(LootTable $v) => self::$_mDROWNED_RANGED_EQUIPMENT = $v,
			"drowned_rider_equipment" => fn(LootTable $v) => self::$_mDROWNED_RIDER_EQUIPMENT = $v,
			"elder_guardian" => fn(LootTable $v) => self::$_mELDER_GUARDIAN = $v,
			"empty" => fn(LootTable $v) => self::$_mEMPTY = $v,
			"enderman" => fn(LootTable $v) => self::$_mENDERMAN = $v,
			"evocation_illager" => fn(LootTable $v) => self::$_mEVOCATION_ILLAGER = $v,
			"fish" => fn(LootTable $v) => self::$_mFISH = $v,
			"fox_equipment" => fn(LootTable $v) => self::$_mFOX_EQUIPMENT = $v,
			"frog" => fn(LootTable $v) => self::$_mFROG = $v,
			"gameplay_entities_mooshroom_milking" => fn(LootTable $v) => self::$_mGAMEPLAY_ENTITIES_MOOSHROOM_MILKING = $v,
			"gameplay_entities_sniffer_seeds" => fn(LootTable $v) => self::$_mGAMEPLAY_ENTITIES_SNIFFER_SEEDS = $v,
			"gameplay_fishing" => fn(LootTable $v) => self::$_mGAMEPLAY_FISHING = $v,
			"gameplay_fishing_fish" => fn(LootTable $v) => self::$_mGAMEPLAY_FISHING_FISH = $v,
			"gameplay_fishing_jungle_fish" => fn(LootTable $v) => self::$_mGAMEPLAY_FISHING_JUNGLE_FISH = $v,
			"gameplay_fishing_jungle_junk" => fn(LootTable $v) => self::$_mGAMEPLAY_FISHING_JUNGLE_JUNK = $v,
			"gameplay_fishing_junk" => fn(LootTable $v) => self::$_mGAMEPLAY_FISHING_JUNK = $v,
			"gameplay_fishing_treasure" => fn(LootTable $v) => self::$_mGAMEPLAY_FISHING_TREASURE = $v,
			"gameplay_jungle_fishing" => fn(LootTable $v) => self::$_mGAMEPLAY_JUNGLE_FISHING = $v,
			"ghast" => fn(LootTable $v) => self::$_mGHAST = $v,
			"glow_squid" => fn(LootTable $v) => self::$_mGLOW_SQUID = $v,
			"goat" => fn(LootTable $v) => self::$_mGOAT = $v,
			"guardian" => fn(LootTable $v) => self::$_mGUARDIAN = $v,
			"hoglin" => fn(LootTable $v) => self::$_mHOGLIN = $v,
			"horse" => fn(LootTable $v) => self::$_mHORSE = $v,
			"husk_rider" => fn(LootTable $v) => self::$_mHUSK_RIDER = $v,
			"iron_golem" => fn(LootTable $v) => self::$_mIRON_GOLEM = $v,
			"llama" => fn(LootTable $v) => self::$_mLLAMA = $v,
			"magma_cube" => fn(LootTable $v) => self::$_mMAGMA_CUBE = $v,
			"mooshroom" => fn(LootTable $v) => self::$_mMOOSHROOM = $v,
			"mooshroom_shear" => fn(LootTable $v) => self::$_mMOOSHROOM_SHEAR = $v,
			"nautilus" => fn(LootTable $v) => self::$_mNAUTILUS = $v,
			"ocelot" => fn(LootTable $v) => self::$_mOCELOT = $v,
			"panda" => fn(LootTable $v) => self::$_mPANDA = $v,
			"panda_sneeze" => fn(LootTable $v) => self::$_mPANDA_SNEEZE = $v,
			"parched" => fn(LootTable $v) => self::$_mPARCHED = $v,
			"parrot" => fn(LootTable $v) => self::$_mPARROT = $v,
			"phantom" => fn(LootTable $v) => self::$_mPHANTOM = $v,
			"pig" => fn(LootTable $v) => self::$_mPIG = $v,
			"piglin_barter" => fn(LootTable $v) => self::$_mPIGLIN_BARTER = $v,
			"piglin_brute_gear" => fn(LootTable $v) => self::$_mPIGLIN_BRUTE_GEAR = $v,
			"piglin_gear_melee" => fn(LootTable $v) => self::$_mPIGLIN_GEAR_MELEE = $v,
			"piglin_gear_ranged" => fn(LootTable $v) => self::$_mPIGLIN_GEAR_RANGED = $v,
			"pig_saddled" => fn(LootTable $v) => self::$_mPIG_SADDLED = $v,
			"pillager" => fn(LootTable $v) => self::$_mPILLAGER = $v,
			"pillager_captain" => fn(LootTable $v) => self::$_mPILLAGER_CAPTAIN = $v,
			"pillager_captain_equipment" => fn(LootTable $v) => self::$_mPILLAGER_CAPTAIN_EQUIPMENT = $v,
			"pillager_gear" => fn(LootTable $v) => self::$_mPILLAGER_GEAR = $v,
			"pillager_raid" => fn(LootTable $v) => self::$_mPILLAGER_RAID = $v,
			"polar_bear" => fn(LootTable $v) => self::$_mPOLAR_BEAR = $v,
			"pufferfish" => fn(LootTable $v) => self::$_mPUFFERFISH = $v,
			"rabbit" => fn(LootTable $v) => self::$_mRABBIT = $v,
			"raider_drops" => fn(LootTable $v) => self::$_mRAIDER_DROPS = $v,
			"ravager" => fn(LootTable $v) => self::$_mRAVAGER = $v,
			"saddle" => fn(LootTable $v) => self::$_mSADDLE = $v,
			"salmon_large" => fn(LootTable $v) => self::$_mSALMON_LARGE = $v,
			"salmon_normal" => fn(LootTable $v) => self::$_mSALMON_NORMAL = $v,
			"sea_turtle" => fn(LootTable $v) => self::$_mSEA_TURTLE = $v,
			"sheep" => fn(LootTable $v) => self::$_mSHEEP = $v,
			"sheep_shear" => fn(LootTable $v) => self::$_mSHEEP_SHEAR = $v,
			"sheep_sheared" => fn(LootTable $v) => self::$_mSHEEP_SHEARED = $v,
			"shulker" => fn(LootTable $v) => self::$_mSHULKER = $v,
			"silverfish" => fn(LootTable $v) => self::$_mSILVERFISH = $v,
			"skeleton" => fn(LootTable $v) => self::$_mSKELETON = $v,
			"skeleton_gear" => fn(LootTable $v) => self::$_mSKELETON_GEAR = $v,
			"skeleton_horse" => fn(LootTable $v) => self::$_mSKELETON_HORSE = $v,
			"slime" => fn(LootTable $v) => self::$_mSLIME = $v,
			"snowman" => fn(LootTable $v) => self::$_mSNOWMAN = $v,
			"snow_golem_shear" => fn(LootTable $v) => self::$_mSNOW_GOLEM_SHEAR = $v,
			"spider" => fn(LootTable $v) => self::$_mSPIDER = $v,
			"squid" => fn(LootTable $v) => self::$_mSQUID = $v,
			"stray" => fn(LootTable $v) => self::$_mSTRAY = $v,
			"strider" => fn(LootTable $v) => self::$_mSTRIDER = $v,
			"strider_saddled" => fn(LootTable $v) => self::$_mSTRIDER_SADDLED = $v,
			"tropicalfish" => fn(LootTable $v) => self::$_mTROPICALFISH = $v,
			"vex_gear" => fn(LootTable $v) => self::$_mVEX_GEAR = $v,
			"vindication_illager" => fn(LootTable $v) => self::$_mVINDICATION_ILLAGER = $v,
			"vindicator_captain_equipment" => fn(LootTable $v) => self::$_mVINDICATOR_CAPTAIN_EQUIPMENT = $v,
			"vindicator_gear" => fn(LootTable $v) => self::$_mVINDICATOR_GEAR = $v,
			"vindicator_raid" => fn(LootTable $v) => self::$_mVINDICATOR_RAID = $v,
			"warden" => fn(LootTable $v) => self::$_mWARDEN = $v,
			"witch" => fn(LootTable $v) => self::$_mWITCH = $v,
			"wither_boss" => fn(LootTable $v) => self::$_mWITHER_BOSS = $v,
			"wither_skeleton" => fn(LootTable $v) => self::$_mWITHER_SKELETON = $v,
			"wither_skeleton_gear" => fn(LootTable $v) => self::$_mWITHER_SKELETON_GEAR = $v,
			"wolf" => fn(LootTable $v) => self::$_mWOLF = $v,
			"zoglin" => fn(LootTable $v) => self::$_mZOGLIN = $v,
			"zombie" => fn(LootTable $v) => self::$_mZOMBIE = $v,
			"zombie_equipment" => fn(LootTable $v) => self::$_mZOMBIE_EQUIPMENT = $v,
			"zombie_horse" => fn(LootTable $v) => self::$_mZOMBIE_HORSE = $v,
			"zombie_nautilus" => fn(LootTable $v) => self::$_mZOMBIE_NAUTILUS = $v,
			"zombie_pigman" => fn(LootTable $v) => self::$_mZOMBIE_PIGMAN = $v,
			"zombie_pigman_gear" => fn(LootTable $v) => self::$_mZOMBIE_PIGMAN_GEAR = $v,
			"zombie_rider" => fn(LootTable $v) => self::$_mZOMBIE_RIDER = $v,
			"zombie_rider_equipment" => fn(LootTable $v) => self::$_mZOMBIE_RIDER_EQUIPMENT = $v,
			"zombified_piglin_rider_gear" => fn(LootTable $v) => self::$_mZOMBIFIED_PIGLIN_RIDER_GEAR = $v,
		];
	}

	private static function init() : void{
		//This nasty mess of closures allows us to suppress PHPStan type assignment errors in one place instead of
		//on every single assignment. This will only run one time on first init, so it's fine for performance.
		if(self::$initialized){
			throw new \LogicException("Circular dependency detected - use RegistrySource->registerDelayed() if the circular dependency can't be avoided");
		}
		self::$initialized = true;
		$assigners = self::getInitAssigners();
		$assigned = [];
		$source = new VanillaLootTablesInputs();
		foreach($source->getAllValues() as $name => $value){
			$assigner = $assigners[$name] ?? throw new \LogicException("Unexpected source registry member \"$name\" (code probably needs regenerating)");
			if(isset($assigned[$name])){
				//this should be prevented by RegistrySource, but it doesn't hurt to have some redundancy
				throw new \LogicException("Repeated registry source member \"$name\"");
			}
			self::$members[mb_strtoupper($name)] = $value;
			$assigned[$name] = true;
			unset($assigners[$name]);
			self::unsafeAssign($assigner, $value);
		}
		if(count($assigners) > 0){
			throw new \LogicException("Missing values for registry members (code probably needs regenerating): " . implode(", ", array_keys($assigners)));
		}
	}

	/**
	 * @return LootTable[]
	 * @phpstan-return array<string, LootTable>
	 */
	public static function getAll() : array{
		if(!isset(self::$members)){ self::init(); }
		return self::$members;
	}

	public static function ARMADILLO_BRUSH() : LootTable{
		if(!isset(self::$_mARMADILLO_BRUSH)){ self::init(); }
		return self::$_mARMADILLO_BRUSH;
	}

	public static function ARMOR_SET_CHAIN() : LootTable{
		if(!isset(self::$_mARMOR_SET_CHAIN)){ self::init(); }
		return self::$_mARMOR_SET_CHAIN;
	}

	public static function ARMOR_SET_COPPER() : LootTable{
		if(!isset(self::$_mARMOR_SET_COPPER)){ self::init(); }
		return self::$_mARMOR_SET_COPPER;
	}

	public static function ARMOR_SET_DIAMOND() : LootTable{
		if(!isset(self::$_mARMOR_SET_DIAMOND)){ self::init(); }
		return self::$_mARMOR_SET_DIAMOND;
	}

	public static function ARMOR_SET_GOLD() : LootTable{
		if(!isset(self::$_mARMOR_SET_GOLD)){ self::init(); }
		return self::$_mARMOR_SET_GOLD;
	}

	public static function ARMOR_SET_IRON() : LootTable{
		if(!isset(self::$_mARMOR_SET_IRON)){ self::init(); }
		return self::$_mARMOR_SET_IRON;
	}

	public static function ARMOR_SET_LEATHER() : LootTable{
		if(!isset(self::$_mARMOR_SET_LEATHER)){ self::init(); }
		return self::$_mARMOR_SET_LEATHER;
	}

	public static function ARMOR_STAND() : LootTable{
		if(!isset(self::$_mARMOR_STAND)){ self::init(); }
		return self::$_mARMOR_STAND;
	}

	public static function BLAZE() : LootTable{
		if(!isset(self::$_mBLAZE)){ self::init(); }
		return self::$_mBLAZE;
	}

	public static function BOAT() : LootTable{
		if(!isset(self::$_mBOAT)){ self::init(); }
		return self::$_mBOAT;
	}

	public static function BOGGED() : LootTable{
		if(!isset(self::$_mBOGGED)){ self::init(); }
		return self::$_mBOGGED;
	}

	public static function BOGGED_SHEAR() : LootTable{
		if(!isset(self::$_mBOGGED_SHEAR)){ self::init(); }
		return self::$_mBOGGED_SHEAR;
	}

	public static function BREEZE() : LootTable{
		if(!isset(self::$_mBREEZE)){ self::init(); }
		return self::$_mBREEZE;
	}

	public static function BROWN_MOOSHROOM_SHEAR() : LootTable{
		if(!isset(self::$_mBROWN_MOOSHROOM_SHEAR)){ self::init(); }
		return self::$_mBROWN_MOOSHROOM_SHEAR;
	}

	public static function CAMEL_HUSK() : LootTable{
		if(!isset(self::$_mCAMEL_HUSK)){ self::init(); }
		return self::$_mCAMEL_HUSK;
	}

	public static function CAT() : LootTable{
		if(!isset(self::$_mCAT)){ self::init(); }
		return self::$_mCAT;
	}

	public static function CAT_GIFT() : LootTable{
		if(!isset(self::$_mCAT_GIFT)){ self::init(); }
		return self::$_mCAT_GIFT;
	}

	public static function CHICKEN() : LootTable{
		if(!isset(self::$_mCHICKEN)){ self::init(); }
		return self::$_mCHICKEN;
	}

	public static function COPPER_GOLEM() : LootTable{
		if(!isset(self::$_mCOPPER_GOLEM)){ self::init(); }
		return self::$_mCOPPER_GOLEM;
	}

	public static function COPPER_GOLEM_SHEAR() : LootTable{
		if(!isset(self::$_mCOPPER_GOLEM_SHEAR)){ self::init(); }
		return self::$_mCOPPER_GOLEM_SHEAR;
	}

	public static function COW() : LootTable{
		if(!isset(self::$_mCOW)){ self::init(); }
		return self::$_mCOW;
	}

	public static function CREEPER() : LootTable{
		if(!isset(self::$_mCREEPER)){ self::init(); }
		return self::$_mCREEPER;
	}

	public static function DOLPHIN() : LootTable{
		if(!isset(self::$_mDOLPHIN)){ self::init(); }
		return self::$_mDOLPHIN;
	}

	public static function DROWNED() : LootTable{
		if(!isset(self::$_mDROWNED)){ self::init(); }
		return self::$_mDROWNED;
	}

	public static function DROWNED_EQUIPMENT() : LootTable{
		if(!isset(self::$_mDROWNED_EQUIPMENT)){ self::init(); }
		return self::$_mDROWNED_EQUIPMENT;
	}

	public static function DROWNED_RANGED_EQUIPMENT() : LootTable{
		if(!isset(self::$_mDROWNED_RANGED_EQUIPMENT)){ self::init(); }
		return self::$_mDROWNED_RANGED_EQUIPMENT;
	}

	public static function DROWNED_RIDER_EQUIPMENT() : LootTable{
		if(!isset(self::$_mDROWNED_RIDER_EQUIPMENT)){ self::init(); }
		return self::$_mDROWNED_RIDER_EQUIPMENT;
	}

	public static function ELDER_GUARDIAN() : LootTable{
		if(!isset(self::$_mELDER_GUARDIAN)){ self::init(); }
		return self::$_mELDER_GUARDIAN;
	}

	public static function EMPTY() : LootTable{
		if(!isset(self::$_mEMPTY)){ self::init(); }
		return self::$_mEMPTY;
	}

	public static function ENDERMAN() : LootTable{
		if(!isset(self::$_mENDERMAN)){ self::init(); }
		return self::$_mENDERMAN;
	}

	public static function EVOCATION_ILLAGER() : LootTable{
		if(!isset(self::$_mEVOCATION_ILLAGER)){ self::init(); }
		return self::$_mEVOCATION_ILLAGER;
	}

	public static function FISH() : LootTable{
		if(!isset(self::$_mFISH)){ self::init(); }
		return self::$_mFISH;
	}

	public static function FOX_EQUIPMENT() : LootTable{
		if(!isset(self::$_mFOX_EQUIPMENT)){ self::init(); }
		return self::$_mFOX_EQUIPMENT;
	}

	public static function FROG() : LootTable{
		if(!isset(self::$_mFROG)){ self::init(); }
		return self::$_mFROG;
	}

	public static function GAMEPLAY_ENTITIES_MOOSHROOM_MILKING() : LootTable{
		if(!isset(self::$_mGAMEPLAY_ENTITIES_MOOSHROOM_MILKING)){ self::init(); }
		return self::$_mGAMEPLAY_ENTITIES_MOOSHROOM_MILKING;
	}

	public static function GAMEPLAY_ENTITIES_SNIFFER_SEEDS() : LootTable{
		if(!isset(self::$_mGAMEPLAY_ENTITIES_SNIFFER_SEEDS)){ self::init(); }
		return self::$_mGAMEPLAY_ENTITIES_SNIFFER_SEEDS;
	}

	public static function GAMEPLAY_FISHING() : LootTable{
		if(!isset(self::$_mGAMEPLAY_FISHING)){ self::init(); }
		return self::$_mGAMEPLAY_FISHING;
	}

	public static function GAMEPLAY_FISHING_FISH() : LootTable{
		if(!isset(self::$_mGAMEPLAY_FISHING_FISH)){ self::init(); }
		return self::$_mGAMEPLAY_FISHING_FISH;
	}

	public static function GAMEPLAY_FISHING_JUNGLE_FISH() : LootTable{
		if(!isset(self::$_mGAMEPLAY_FISHING_JUNGLE_FISH)){ self::init(); }
		return self::$_mGAMEPLAY_FISHING_JUNGLE_FISH;
	}

	public static function GAMEPLAY_FISHING_JUNGLE_JUNK() : LootTable{
		if(!isset(self::$_mGAMEPLAY_FISHING_JUNGLE_JUNK)){ self::init(); }
		return self::$_mGAMEPLAY_FISHING_JUNGLE_JUNK;
	}

	public static function GAMEPLAY_FISHING_JUNK() : LootTable{
		if(!isset(self::$_mGAMEPLAY_FISHING_JUNK)){ self::init(); }
		return self::$_mGAMEPLAY_FISHING_JUNK;
	}

	public static function GAMEPLAY_FISHING_TREASURE() : LootTable{
		if(!isset(self::$_mGAMEPLAY_FISHING_TREASURE)){ self::init(); }
		return self::$_mGAMEPLAY_FISHING_TREASURE;
	}

	public static function GAMEPLAY_JUNGLE_FISHING() : LootTable{
		if(!isset(self::$_mGAMEPLAY_JUNGLE_FISHING)){ self::init(); }
		return self::$_mGAMEPLAY_JUNGLE_FISHING;
	}

	public static function GHAST() : LootTable{
		if(!isset(self::$_mGHAST)){ self::init(); }
		return self::$_mGHAST;
	}

	public static function GLOW_SQUID() : LootTable{
		if(!isset(self::$_mGLOW_SQUID)){ self::init(); }
		return self::$_mGLOW_SQUID;
	}

	public static function GOAT() : LootTable{
		if(!isset(self::$_mGOAT)){ self::init(); }
		return self::$_mGOAT;
	}

	public static function GUARDIAN() : LootTable{
		if(!isset(self::$_mGUARDIAN)){ self::init(); }
		return self::$_mGUARDIAN;
	}

	public static function HOGLIN() : LootTable{
		if(!isset(self::$_mHOGLIN)){ self::init(); }
		return self::$_mHOGLIN;
	}

	public static function HORSE() : LootTable{
		if(!isset(self::$_mHORSE)){ self::init(); }
		return self::$_mHORSE;
	}

	public static function HUSK_RIDER() : LootTable{
		if(!isset(self::$_mHUSK_RIDER)){ self::init(); }
		return self::$_mHUSK_RIDER;
	}

	public static function IRON_GOLEM() : LootTable{
		if(!isset(self::$_mIRON_GOLEM)){ self::init(); }
		return self::$_mIRON_GOLEM;
	}

	public static function LLAMA() : LootTable{
		if(!isset(self::$_mLLAMA)){ self::init(); }
		return self::$_mLLAMA;
	}

	public static function MAGMA_CUBE() : LootTable{
		if(!isset(self::$_mMAGMA_CUBE)){ self::init(); }
		return self::$_mMAGMA_CUBE;
	}

	public static function MOOSHROOM() : LootTable{
		if(!isset(self::$_mMOOSHROOM)){ self::init(); }
		return self::$_mMOOSHROOM;
	}

	public static function MOOSHROOM_SHEAR() : LootTable{
		if(!isset(self::$_mMOOSHROOM_SHEAR)){ self::init(); }
		return self::$_mMOOSHROOM_SHEAR;
	}

	public static function NAUTILUS() : LootTable{
		if(!isset(self::$_mNAUTILUS)){ self::init(); }
		return self::$_mNAUTILUS;
	}

	public static function OCELOT() : LootTable{
		if(!isset(self::$_mOCELOT)){ self::init(); }
		return self::$_mOCELOT;
	}

	public static function PANDA() : LootTable{
		if(!isset(self::$_mPANDA)){ self::init(); }
		return self::$_mPANDA;
	}

	public static function PANDA_SNEEZE() : LootTable{
		if(!isset(self::$_mPANDA_SNEEZE)){ self::init(); }
		return self::$_mPANDA_SNEEZE;
	}

	public static function PARCHED() : LootTable{
		if(!isset(self::$_mPARCHED)){ self::init(); }
		return self::$_mPARCHED;
	}

	public static function PARROT() : LootTable{
		if(!isset(self::$_mPARROT)){ self::init(); }
		return self::$_mPARROT;
	}

	public static function PHANTOM() : LootTable{
		if(!isset(self::$_mPHANTOM)){ self::init(); }
		return self::$_mPHANTOM;
	}

	public static function PIG() : LootTable{
		if(!isset(self::$_mPIG)){ self::init(); }
		return self::$_mPIG;
	}

	public static function PIGLIN_BARTER() : LootTable{
		if(!isset(self::$_mPIGLIN_BARTER)){ self::init(); }
		return self::$_mPIGLIN_BARTER;
	}

	public static function PIGLIN_BRUTE_GEAR() : LootTable{
		if(!isset(self::$_mPIGLIN_BRUTE_GEAR)){ self::init(); }
		return self::$_mPIGLIN_BRUTE_GEAR;
	}

	public static function PIGLIN_GEAR_MELEE() : LootTable{
		if(!isset(self::$_mPIGLIN_GEAR_MELEE)){ self::init(); }
		return self::$_mPIGLIN_GEAR_MELEE;
	}

	public static function PIGLIN_GEAR_RANGED() : LootTable{
		if(!isset(self::$_mPIGLIN_GEAR_RANGED)){ self::init(); }
		return self::$_mPIGLIN_GEAR_RANGED;
	}

	public static function PIG_SADDLED() : LootTable{
		if(!isset(self::$_mPIG_SADDLED)){ self::init(); }
		return self::$_mPIG_SADDLED;
	}

	public static function PILLAGER() : LootTable{
		if(!isset(self::$_mPILLAGER)){ self::init(); }
		return self::$_mPILLAGER;
	}

	public static function PILLAGER_CAPTAIN() : LootTable{
		if(!isset(self::$_mPILLAGER_CAPTAIN)){ self::init(); }
		return self::$_mPILLAGER_CAPTAIN;
	}

	public static function PILLAGER_CAPTAIN_EQUIPMENT() : LootTable{
		if(!isset(self::$_mPILLAGER_CAPTAIN_EQUIPMENT)){ self::init(); }
		return self::$_mPILLAGER_CAPTAIN_EQUIPMENT;
	}

	public static function PILLAGER_GEAR() : LootTable{
		if(!isset(self::$_mPILLAGER_GEAR)){ self::init(); }
		return self::$_mPILLAGER_GEAR;
	}

	public static function PILLAGER_RAID() : LootTable{
		if(!isset(self::$_mPILLAGER_RAID)){ self::init(); }
		return self::$_mPILLAGER_RAID;
	}

	public static function POLAR_BEAR() : LootTable{
		if(!isset(self::$_mPOLAR_BEAR)){ self::init(); }
		return self::$_mPOLAR_BEAR;
	}

	public static function PUFFERFISH() : LootTable{
		if(!isset(self::$_mPUFFERFISH)){ self::init(); }
		return self::$_mPUFFERFISH;
	}

	public static function RABBIT() : LootTable{
		if(!isset(self::$_mRABBIT)){ self::init(); }
		return self::$_mRABBIT;
	}

	public static function RAIDER_DROPS() : LootTable{
		if(!isset(self::$_mRAIDER_DROPS)){ self::init(); }
		return self::$_mRAIDER_DROPS;
	}

	public static function RAVAGER() : LootTable{
		if(!isset(self::$_mRAVAGER)){ self::init(); }
		return self::$_mRAVAGER;
	}

	public static function SADDLE() : LootTable{
		if(!isset(self::$_mSADDLE)){ self::init(); }
		return self::$_mSADDLE;
	}

	public static function SALMON_LARGE() : LootTable{
		if(!isset(self::$_mSALMON_LARGE)){ self::init(); }
		return self::$_mSALMON_LARGE;
	}

	public static function SALMON_NORMAL() : LootTable{
		if(!isset(self::$_mSALMON_NORMAL)){ self::init(); }
		return self::$_mSALMON_NORMAL;
	}

	public static function SEA_TURTLE() : LootTable{
		if(!isset(self::$_mSEA_TURTLE)){ self::init(); }
		return self::$_mSEA_TURTLE;
	}

	public static function SHEEP() : LootTable{
		if(!isset(self::$_mSHEEP)){ self::init(); }
		return self::$_mSHEEP;
	}

	public static function SHEEP_SHEAR() : LootTable{
		if(!isset(self::$_mSHEEP_SHEAR)){ self::init(); }
		return self::$_mSHEEP_SHEAR;
	}

	public static function SHEEP_SHEARED() : LootTable{
		if(!isset(self::$_mSHEEP_SHEARED)){ self::init(); }
		return self::$_mSHEEP_SHEARED;
	}

	public static function SHULKER() : LootTable{
		if(!isset(self::$_mSHULKER)){ self::init(); }
		return self::$_mSHULKER;
	}

	public static function SILVERFISH() : LootTable{
		if(!isset(self::$_mSILVERFISH)){ self::init(); }
		return self::$_mSILVERFISH;
	}

	public static function SKELETON() : LootTable{
		if(!isset(self::$_mSKELETON)){ self::init(); }
		return self::$_mSKELETON;
	}

	public static function SKELETON_GEAR() : LootTable{
		if(!isset(self::$_mSKELETON_GEAR)){ self::init(); }
		return self::$_mSKELETON_GEAR;
	}

	public static function SKELETON_HORSE() : LootTable{
		if(!isset(self::$_mSKELETON_HORSE)){ self::init(); }
		return self::$_mSKELETON_HORSE;
	}

	public static function SLIME() : LootTable{
		if(!isset(self::$_mSLIME)){ self::init(); }
		return self::$_mSLIME;
	}

	public static function SNOWMAN() : LootTable{
		if(!isset(self::$_mSNOWMAN)){ self::init(); }
		return self::$_mSNOWMAN;
	}

	public static function SNOW_GOLEM_SHEAR() : LootTable{
		if(!isset(self::$_mSNOW_GOLEM_SHEAR)){ self::init(); }
		return self::$_mSNOW_GOLEM_SHEAR;
	}

	public static function SPIDER() : LootTable{
		if(!isset(self::$_mSPIDER)){ self::init(); }
		return self::$_mSPIDER;
	}

	public static function SQUID() : LootTable{
		if(!isset(self::$_mSQUID)){ self::init(); }
		return self::$_mSQUID;
	}

	public static function STRAY() : LootTable{
		if(!isset(self::$_mSTRAY)){ self::init(); }
		return self::$_mSTRAY;
	}

	public static function STRIDER() : LootTable{
		if(!isset(self::$_mSTRIDER)){ self::init(); }
		return self::$_mSTRIDER;
	}

	public static function STRIDER_SADDLED() : LootTable{
		if(!isset(self::$_mSTRIDER_SADDLED)){ self::init(); }
		return self::$_mSTRIDER_SADDLED;
	}

	public static function TROPICALFISH() : LootTable{
		if(!isset(self::$_mTROPICALFISH)){ self::init(); }
		return self::$_mTROPICALFISH;
	}

	public static function VEX_GEAR() : LootTable{
		if(!isset(self::$_mVEX_GEAR)){ self::init(); }
		return self::$_mVEX_GEAR;
	}

	public static function VINDICATION_ILLAGER() : LootTable{
		if(!isset(self::$_mVINDICATION_ILLAGER)){ self::init(); }
		return self::$_mVINDICATION_ILLAGER;
	}

	public static function VINDICATOR_CAPTAIN_EQUIPMENT() : LootTable{
		if(!isset(self::$_mVINDICATOR_CAPTAIN_EQUIPMENT)){ self::init(); }
		return self::$_mVINDICATOR_CAPTAIN_EQUIPMENT;
	}

	public static function VINDICATOR_GEAR() : LootTable{
		if(!isset(self::$_mVINDICATOR_GEAR)){ self::init(); }
		return self::$_mVINDICATOR_GEAR;
	}

	public static function VINDICATOR_RAID() : LootTable{
		if(!isset(self::$_mVINDICATOR_RAID)){ self::init(); }
		return self::$_mVINDICATOR_RAID;
	}

	public static function WARDEN() : LootTable{
		if(!isset(self::$_mWARDEN)){ self::init(); }
		return self::$_mWARDEN;
	}

	public static function WITCH() : LootTable{
		if(!isset(self::$_mWITCH)){ self::init(); }
		return self::$_mWITCH;
	}

	public static function WITHER_BOSS() : LootTable{
		if(!isset(self::$_mWITHER_BOSS)){ self::init(); }
		return self::$_mWITHER_BOSS;
	}

	public static function WITHER_SKELETON() : LootTable{
		if(!isset(self::$_mWITHER_SKELETON)){ self::init(); }
		return self::$_mWITHER_SKELETON;
	}

	public static function WITHER_SKELETON_GEAR() : LootTable{
		if(!isset(self::$_mWITHER_SKELETON_GEAR)){ self::init(); }
		return self::$_mWITHER_SKELETON_GEAR;
	}

	public static function WOLF() : LootTable{
		if(!isset(self::$_mWOLF)){ self::init(); }
		return self::$_mWOLF;
	}

	public static function ZOGLIN() : LootTable{
		if(!isset(self::$_mZOGLIN)){ self::init(); }
		return self::$_mZOGLIN;
	}

	public static function ZOMBIE() : LootTable{
		if(!isset(self::$_mZOMBIE)){ self::init(); }
		return self::$_mZOMBIE;
	}

	public static function ZOMBIE_EQUIPMENT() : LootTable{
		if(!isset(self::$_mZOMBIE_EQUIPMENT)){ self::init(); }
		return self::$_mZOMBIE_EQUIPMENT;
	}

	public static function ZOMBIE_HORSE() : LootTable{
		if(!isset(self::$_mZOMBIE_HORSE)){ self::init(); }
		return self::$_mZOMBIE_HORSE;
	}

	public static function ZOMBIE_NAUTILUS() : LootTable{
		if(!isset(self::$_mZOMBIE_NAUTILUS)){ self::init(); }
		return self::$_mZOMBIE_NAUTILUS;
	}

	public static function ZOMBIE_PIGMAN() : LootTable{
		if(!isset(self::$_mZOMBIE_PIGMAN)){ self::init(); }
		return self::$_mZOMBIE_PIGMAN;
	}

	public static function ZOMBIE_PIGMAN_GEAR() : LootTable{
		if(!isset(self::$_mZOMBIE_PIGMAN_GEAR)){ self::init(); }
		return self::$_mZOMBIE_PIGMAN_GEAR;
	}

	public static function ZOMBIE_RIDER() : LootTable{
		if(!isset(self::$_mZOMBIE_RIDER)){ self::init(); }
		return self::$_mZOMBIE_RIDER;
	}

	public static function ZOMBIE_RIDER_EQUIPMENT() : LootTable{
		if(!isset(self::$_mZOMBIE_RIDER_EQUIPMENT)){ self::init(); }
		return self::$_mZOMBIE_RIDER_EQUIPMENT;
	}

	public static function ZOMBIFIED_PIGLIN_RIDER_GEAR() : LootTable{
		if(!isset(self::$_mZOMBIFIED_PIGLIN_RIDER_GEAR)){ self::init(); }
		return self::$_mZOMBIFIED_PIGLIN_RIDER_GEAR;
	}
}
