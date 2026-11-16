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

namespace IvanCraft623\MobPlugin\data\bedrock;

use IvanCraft623\MobPlugin\entity\genetics\Alleles;
use IvanCraft623\MobPlugin\entity\genetics\Gene;
use IvanCraft623\MobPlugin\entity\genetics\Genome;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use function array_map;
use function count;

/**
 * How the game saves a genome: a list with the alleles of each gene, in the order of its genes.
 */
final class GenomeNbt{

	private const TAG_GENE_ARRAY = "GeneArray"; //TAG_List<TAG_Compound>
	private const TAG_MAIN_ALLELE = "MainAllele"; //TAG_Int
	private const TAG_HIDDEN_ALLELE = "HiddenAllele"; //TAG_Int

	private function __construct(){
		//NOOP
	}

	/**
	 * @param list<Gene<*>> $genes
	 *
	 * @return ?Genome null when it is not saved, or not valid for these genes
	 */
	public static function read(CompoundTag $nbt, array $genes) : ?Genome{
		$list = $nbt->getListTag(self::TAG_GENE_ARRAY, CompoundTag::class);
		if ($list === null || count($list) !== count($genes)) {
			return null;
		}

		$alleles = [];
		foreach ($genes as $index => $gene) {
			/** @var CompoundTag $tag */
			$tag = $list->get($index);
			$own = $gene->alleles($tag->getInt(self::TAG_MAIN_ALLELE, -1), $tag->getInt(self::TAG_HIDDEN_ALLELE, -1));
			if (!$gene->isValid($own)) {
				return null;
			}
			$alleles[] = $own;
		}

		return new Genome($alleles);
	}

	public static function write(CompoundTag $nbt, Genome $genome) : void{
		$nbt->setTag(self::TAG_GENE_ARRAY, new ListTag(array_map(
			static fn(Alleles $alleles) : CompoundTag => CompoundTag::create()
				->setInt(self::TAG_MAIN_ALLELE, $alleles->main)
				->setInt(self::TAG_HIDDEN_ALLELE, $alleles->hidden),
			$genome->getAllAlleles()
		)));
	}
}