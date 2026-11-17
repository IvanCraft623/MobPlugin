#!/usr/bin/env bash
# Merges main into ng. A composer.lock-only conflict is resolved by keeping ng's lock and
# updating the packages main changed. Any other conflict stays in the working tree for you to fix.
#
# Manual use: git switch ng && .github/scripts/sync-ng.sh
set -euo pipefail

git fetch origin main
if git merge --no-edit origin/main; then
	exit 0
fi

if [ "$(git diff --name-only --diff-filter=U)" != "composer.lock" ]; then
	exit 1
fi

# Packages whose locked version main changed since the merge base.
base=$(git merge-base HEAD origin/main)
changed=$(php -r '
	$versions = static function(string $json) : array{
		$lock = json_decode($json, true);
		$result = [];
		foreach(array_merge($lock["packages"], $lock["packages-dev"]) as $package){
			$result[$package["name"]] = $package["version"];
		}
		return $result;
	};
	$old = $versions((string) shell_exec("git show " . escapeshellarg($argv[1] . ":composer.lock")));
	$new = $versions((string) shell_exec("git show origin/main:composer.lock"));
	foreach($new as $name => $version){
		if(($old[$name] ?? null) !== $version){
			echo $name, "\n";
		}
	}
' "$base")

git checkout --ours composer.lock
# shellcheck disable=SC2086
composer update $changed --with-all-dependencies --no-interaction --no-scripts --ignore-platform-reqs
git add composer.lock
git commit --no-edit
