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

/**
 * Generates a registry class from its RegistrySource with PocketMine-MP's registry generator
 * (build/codegen/registry-interface.php). The generator requires the autoloader of
 * PocketMine-MP's own checkout and writes its license header, which a Composer install has
 * neither of, so it runs from a temporary copy of that layout with this project's autoloader
 * and header.
 *
 * Usage: php tools/registry/generate.php [--bootstrap=<file>] <source file/folder> <destination file/folder>
 *
 * The source and destination are the generator's own arguments. The bootstrap file is
 * required after the autoloader, for sources that need setting up first.
 */

namespace IvanCraft623\MobPlugin\tools\registry\generate;

require __DIR__ . "/../../vendor/autoload.php";

use Composer\InstalledVersions;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Filesystem\Path;
use function array_slice;
use function basename;
use function bin2hex;
use function copy;
use function count;
use function dirname;
use function escapeshellarg;
use function file_put_contents;
use function fwrite;
use function getcwd;
use function is_dir;
use function mkdir;
use function passthru;
use function random_bytes;
use function realpath;
use function rmdir;
use function str_starts_with;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function unlink;
use function var_export;
use const PHP_BINARY;
use const STDERR;

const BOOTSTRAP_OPTION = "--bootstrap=";

/**
 * Everything every generated file starts with, up to its namespace.
 */
const FILE_HEADER = <<<'PHP'
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

PHP;

/**
 * @phpstan-param list<string> $args
 */
function main(array $args) : int{
	$bootstrap = null;
	$paths = [];
	foreach($args as $arg){
		if(str_starts_with($arg, BOOTSTRAP_OPTION)){
			$bootstrap = substr($arg, strlen(BOOTSTRAP_OPTION));
		}else{
			$paths[] = $arg;
		}
	}
	$workingDirectory = getcwd();
	if(count($paths) !== 2 || $workingDirectory === false){
		return fail("Usage: php tools/registry/generate.php [--bootstrap=<file>] <source file/folder> <destination file/folder>");
	}
	[$source, $destination] = [Path::makeAbsolute($paths[0], $workingDirectory), Path::makeAbsolute($paths[1], $workingDirectory)];
	if($bootstrap !== null){
		$bootstrap = Path::makeAbsolute($bootstrap, $workingDirectory);
	}

	$installPath = InstalledVersions::getInstallPath("pocketmine/pocketmine-mp");
	$generator = $installPath !== null ? realpath($installPath . "/build/codegen/registry-interface.php") : false;
	if($generator === false){
		return fail("pocketmine/pocketmine-mp has no build/codegen/registry-interface.php; run composer install");
	}

	$layout = sys_get_temp_dir() . "/mobplugin-registry-" . bin2hex(random_bytes(4));
	try{
		foreach(["build/codegen/templates", "vendor"] as $folder){
			if(!mkdir($layout . "/" . $folder, 0777, true)){
				return fail("Failed to create: $layout/$folder");
			}
		}
		if(!copy($generator, $layout . "/build/codegen/registry-interface.php") ||
			file_put_contents($layout . "/build/codegen/templates/header.php", FILE_HEADER) === false ||
			file_put_contents($layout . "/vendor/autoload.php", buildAutoloader(dirname(__DIR__, 2), $bootstrap)) === false
		){
			return fail("Failed to write the generator layout in $layout");
		}

		passthru(
			escapeshellarg(PHP_BINARY) . " " . escapeshellarg($layout . "/build/codegen/registry-interface.php") .
			" " . escapeshellarg($source) . " " . escapeshellarg($destination),
			$exitCode
		);

		return $exitCode;
	}finally{
		removeDirectory($layout);
	}
}

/**
 * The autoloader the generator requires: this project's, then the bootstrap file.
 */
function buildAutoloader(string $root, ?string $bootstrap) : string{
	return "<?php\n" .
		"require " . var_export($root . "/vendor/autoload.php", true) . ";\n" .
		($bootstrap !== null ? "require " . var_export($bootstrap, true) . ";\n" : "");
}

function removeDirectory(string $directory) : void{
	if(!is_dir($directory)){
		return;
	}
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	/** @var SplFileInfo $fileInfo */
	foreach($iterator as $fileInfo){
		$fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
	}
	rmdir($directory);
}

function fail(string $message) : int{
	fwrite(STDERR, basename(__FILE__) . ": $message\n");

	return 1;
}

exit(main(array_slice($argv, 1)));
