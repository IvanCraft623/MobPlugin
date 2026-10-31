<?php

declare(strict_types=1);

namespace IvanCraft623\MobPlugin\libs\_2ad1fd49155870ef\kostamax27\loottable;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;
use function is_dir;
use function is_file;

final class DirectoryLootTableResolver implements LootTableResolver{

	readonly public string $directory;

	/** @var array<string, LootTable> */
	private array $cache = [];

	/** @var array<string, true> */
	private array $resolving = [];

	/**
	 * @param string $directory behaviour pack root that "loot_table" entry names are relative to
	 * @param LootTableFactory $factory
	 */
	public function __construct(
		string $directory,
		readonly private LootTableFactory $factory
	){
		is_dir($directory) || throw new InvalidArgumentException("Directory '{$directory}' does not exist");
		$this->directory = Path::canonicalize($directory);
	}

	public function resolve(string $name) : LootTable{
		if(isset($this->cache[$name])){
			return $this->cache[$name];
		}
		isset($this->resolving[$name]) && throw new InvalidArgumentException("Loot table '{$name}' references itself");
		$path = Path::join($this->directory, $name);
		Path::isBasePath($this->directory, $path) || throw new InvalidArgumentException("Loot table '{$name}' resolves outside {$this->directory}");
		is_file($path) || throw new InvalidArgumentException("Loot table '{$name}' not found in {$this->directory}");
		$this->resolving[$name] = true;
		try{
			return $this->cache[$name] = $this->factory->fromFile($path);
		}finally{
			unset($this->resolving[$name]);
		}
	}

	public function invalidate() : void{
		$this->cache = [];
	}
}