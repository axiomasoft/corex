#!/usr/bin/env bash
#
# Roave backward-compatibility gate (PLAN2 P3.4 · D98).
#
# Per live package: disposable git repo with explicit base/candidate commits;
# comparison Composer root autoloads only that package (production paths).
#
# Exit 0 on PHP 8.3 (skip). Roave exit 3 = BC break (report-only if experimental).
# Any other Roave outcome = infrastructure RED.
#
# Env:
#   BC_CHECK_BASE          — git ref for package base tree (default: merge-base origin/main)
#   BC_CHECK_FORCE_STABLE  — composer package name treated as stable (validation probe)
#   BC_CHECK_PROBE_INFRA   — if set, corrupt comparison composer.json for core (probe)
#   BC_CHECK_ONLY          — single package dir (debug)
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ROAVE_BIN="${REPO_ROOT}/vendor/bin/roave-backward-compatibility-check"
PHP_MM="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"

bc_php() {
  BC_REPO_ROOT="$REPO_ROOT" php -d detect_unicode=0 -r "$1"
}

if [[ "$PHP_MM" != "8.4" && "$PHP_MM" != "8.5" ]]; then
  echo "bc-check: skip — roave/backward-compatibility-check ^8.22 requires PHP 8.4 or 8.5 (current: ${PHP_MM})"
  exit 0
fi

if [[ ! -x "$ROAVE_BIN" ]]; then
  echo "bc-check: infrastructure RED — missing ${ROAVE_BIN} (composer install)" >&2
  exit 1
fi

BC_CHECK_BASE="${BC_CHECK_BASE:-}"
if [[ -z "$BC_CHECK_BASE" ]]; then
  if git -C "$REPO_ROOT" rev-parse --verify origin/main^{commit} >/dev/null 2>&1; then
    BC_CHECK_BASE="$(git -C "$REPO_ROOT" merge-base HEAD origin/main)"
  else
    BC_CHECK_BASE="$(git -C "$REPO_ROOT" rev-parse HEAD)"
  fi
fi

if ! git -C "$REPO_ROOT" rev-parse --verify "${BC_CHECK_BASE}^{commit}" >/dev/null 2>&1; then
  echo "bc-check: infrastructure RED — BC_CHECK_BASE not resolvable: ${BC_CHECK_BASE}" >&2
  exit 1
fi

roave_ver="$(composer --working-dir="$REPO_ROOT" show roave/backward-compatibility-check --format=json 2>/dev/null | bc_php '$j=json_decode(stream_get_contents(STDIN),true); echo $j["versions"][0]??"unknown";')"
echo "bc-check: BC_CHECK_BASE=${BC_CHECK_BASE} (PHP ${PHP_MM})"
echo "bc-check: roave ${roave_ver}"

mapfile -t PACKAGE_DIRS < <(bc_php '
$r=getenv("BC_REPO_ROOT");
$c=json_decode(file_get_contents("$r/composer.json"),true);
$s=$c["scripts"]["test:packages"]??"";
if(!preg_match("/for p in (.+?); do/s",$s,$m)){fwrite(STDERR,"bc-check: cannot parse test:packages\n");exit(1);}
foreach(array_filter(explode(" ",trim($m[1]))) as $d){if($d!=="import-export") echo "$d\n";}
')

stage_package_vendors() {
  local dir="$1" work="$2"
  local ns pkgpath leaf

  for ns in axioma-studio axiomasoft; do
    local root="$REPO_ROOT/packages/${dir}/vendor/${ns}"
    [[ -d "$root" ]] || continue
    for pkgpath in "${root}"/*; do
      [[ -f "${pkgpath}/composer.json" ]] || continue
      leaf="$(basename "${pkgpath}")"
      mkdir -p "${work}/packages/_vendor/${leaf}"
      rsync -a "${pkgpath}/" "${work}/packages/_vendor/${leaf}/"
    done
  done

  if rg -q 'PHPStan\\' "$work/packages/$dir/src"; then
    BC_WORK="$work" bc_php '
$r=getenv("BC_REPO_ROOT"); $work=getenv("BC_WORK");
require "$r/vendor/autoload.php";
$destination="$work/packages/_vendor/phpstan";
@mkdir($destination,0777,true);
(new Phar("$r/vendor/phpstan/phpstan/phpstan.phar"))->extractTo($destination,null,true);
$manifest=["name"=>"phpstan/phpstan","version"=>Composer\InstalledVersions::getPrettyVersion("phpstan/phpstan"),"require"=>["nikic/php-parser"=>"^5.0"],"autoload"=>["psr-4"=>["PHPStan\\"=>"src/"]]];
file_put_contents("$destination/composer.json",json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
'
  fi

  BC_WORK="$work" bc_php '
$work=getenv("BC_WORK");
$versions=["stancl-tenancy"=>"4.0.0-alpha1","stancl-jobpipeline"=>"2.0.0-alpha1","azguard-core"=>"0.2.0"];
foreach(glob("$work/packages/_vendor/*/composer.json")?:[] as $cp){
  $leaf=basename(dirname($cp));
  if(!isset($versions[$leaf])){continue;}
  $j=json_decode((string)file_get_contents($cp),true);
  if(!is_array($j)){continue;}
  $j["version"]=$versions[$leaf];
  file_put_contents($cp,json_encode($j,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
}
'
}

run_one_package() {
  local dir="$1" pkg_name="$2" stability="$3" work="$4"
  local base_sha cand_sha roave_exit classified contract_hint=""

  if ! git -C "$REPO_ROOT" cat-file -e "${BC_CHECK_BASE}:packages/${dir}" 2>/dev/null; then
    echo "bc-check: ${pkg_name} (${dir}) |new-package| base missing at ${BC_CHECK_BASE}"
    return 0
  fi

  mkdir -p "${work}/packages"
  git -C "$work" init -q
  git -C "$work" config user.email bc-check@corex.local
  git -C "$work" config user.name "bc-check"

  mapfile -t dep_dirs < <(BC_CHECK_TARGET_DIR="$dir" bc_php '
$r=getenv("BC_REPO_ROOT"); $target=getenv("BC_CHECK_TARGET_DIR");
$map=[];
foreach(glob("$r/packages/*/composer.json")?:[] as $p){
  $j=json_decode(file_get_contents($p),true);
  if(is_array($j)&&isset($j["name"])){$map[$j["name"]]=basename(dirname($p));}
}
$seen=[]; $q=[];
$root="$r/packages/$target/composer.json";
$walk=function(string $d) use (&$walk,&$seen,&$q,$r,$map){
  if(isset($seen[$d])) return; $seen[$d]=true;
  $path="$r/packages/$d/composer.json";
  if(!is_file($path)) return;
  $j=json_decode(file_get_contents($path),true);
  foreach(array_keys(array_merge($j["require"]??[],$j["require-dev"]??[])) as $req){
    if(!str_starts_with($req,"corex/")&&!str_starts_with($req,"flex/")) continue;
    if(!isset($map[$req])) continue;
    $dep=$map[$req];
    $q[]=$dep;
    $walk($dep);
  }
};
$walk($target);
$q[]=$target;
$q=array_values(array_unique($q));
sort($q);
foreach($q as $d) echo "$d\n";
')

  # Uncommitted D89/D90 renames are absent at BC_CHECK_BASE. Archive the predecessor
  # tree under the current directory and composer name so the live require graph installs.
  archive_base_package() {
    local dep="$1" old=""
    if git -C "$REPO_ROOT" cat-file -e "${BC_CHECK_BASE}:packages/${dep}" 2>/dev/null; then
      git -C "$REPO_ROOT" archive "${BC_CHECK_BASE}" "packages/${dep}" | tar -x -C "$work"
      return 0
    fi
    case "$dep" in
      operations) old=api ;;
      access) old=auth ;;
    esac
    if [[ -z "$old" ]] || ! git -C "$REPO_ROOT" cat-file -e "${BC_CHECK_BASE}:packages/${old}" 2>/dev/null; then
      echo "bc-check: infrastructure RED — ${BC_CHECK_BASE} has no packages/${dep}" >&2
      return 1
    fi
    git -C "$REPO_ROOT" archive "${BC_CHECK_BASE}" "packages/${old}" | tar -x -C "$work"
  }

  for dep in "${dep_dirs[@]}"; do
    archive_base_package "$dep" || return 1
  done

  stage_package_vendors "$dir" "$work"

  BC_WORK="$work" BC_CHECK_TARGET_DIR="$dir" bc_php '
$r=getenv("BC_REPO_ROOT"); $d=getenv("BC_CHECK_TARGET_DIR");
$archived=getenv("BC_WORK")."/packages/$d/composer.json";
$pkg=json_decode(file_get_contents(is_file($archived)?$archived:"$r/packages/$d/composer.json"),true);
$requireDev=[];
foreach($pkg["require-dev"]??[] as $name=>$constraint){
  if(str_starts_with($name,"corex/")||str_starts_with($name,"flex/")){$requireDev[$name]=$constraint;}
}
$src=getenv("BC_WORK")."/packages/$d/src";
if(is_dir($src)&&isset($pkg["require-dev"]["orchestra/testbench"])){
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
  foreach($it as $file){
    if(!$file->isFile()||!str_ends_with($file->getFilename(),".php")){continue;}
    if(str_contains((string)file_get_contents($file->getPathname()),"Orchestra\\Testbench")){
      $requireDev["orchestra/testbench"]=$pkg["require-dev"]["orchestra/testbench"];
      break;
    }
  }
}
$repos=[["type"=>"path","url"=>"packages/*"]];
$require=$pkg["require"]??[];
$work=getenv("BC_WORK");
foreach($pkg["repositories"]??[] as $repo){
  if(($repo["type"]??"")==="path"&&str_starts_with((string)($repo["url"]??""),"../")){continue;}
  if(is_dir("$work/packages/_vendor/azguard-core")&&($repo["type"]??"")==="vcs"&&str_contains((string)($repo["url"]??""),"azguard")){continue;}
  $repos[]=$repo;
}
foreach(glob("$work/packages/_vendor/*/composer.json")?:[] as $composerPath){
  $vc=json_decode((string)file_get_contents($composerPath),true);
  $vName=is_array($vc)?($vc["name"]??""):"";
  if($vName===""){continue;}
  if(!isset($require[$vName])&&!isset($requireDev[$vName])&&$vName!=="axiomasoft/stancl-jobpipeline"){continue;}
  $repos[]=["type"=>"path","url"=>"packages/_vendor/".basename(dirname($composerPath))];
  if($vName==="axioma-studio/azguard-core"){$require[$vName]="@dev";}
}
if(is_dir($src)){
  $blob="";
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
  foreach($it as $file){
    if(!$file->isFile()||!str_ends_with($file->getFilename(),".php")){continue;}
    $blob.=(string)file_get_contents($file->getPathname());
  }
  $illum=[
    "Illuminate\\Foundation\\"=>"laravel/framework",
    "Illuminate\\Console\\"=>"illuminate/console",
    "Illuminate\\Queue\\"=>"illuminate/queue",
  ];
  foreach($illum as $needle=>$pkgName){
    if(str_contains($blob,$needle)&&!isset($require[$pkgName])){
      $require[$pkgName]="^12.0|^13.0";
    }
  }
}
if(str_contains($blob??"","PHPStan\\")){$requireDev["phpstan/phpstan"]="@dev";$repos[]=["type"=>"path","url"=>"packages/_vendor/phpstan"]; }
$root=["name"=>"corex/bc-compare-root","type"=>"project","license"=>"MIT","require"=>$require,"repositories"=>$repos,"autoload"=>[],"minimum-stability"=>"dev","prefer-stable"=>true];
if($requireDev!==[]){$root["require-dev"]=$requireDev;}
foreach($pkg["autoload"]["psr-4"]??[] as $prefix=>$rel){
  $paths=is_array($rel)?$rel:[$rel];
  $mapped=[];
  foreach($paths as $one){$mapped[]="packages/$d/".ltrim((string)$one,"./");}
  $root["autoload"]["psr-4"][$prefix]=count($mapped)===1?$mapped[0]:$mapped;
  foreach($mapped as $path){$root["autoload"]["classmap"][]=$path;}
}
foreach(["classmap","files","exclude-from-classmap"] as $kind){
  foreach($pkg["autoload"][$kind]??[] as $rel){
    $root["autoload"][$kind][]="packages/$d/".ltrim((string)$rel,"./");
  }
}
echo json_encode($root,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
' > "${work}/composer.json"

  if [[ -n "${BC_CHECK_PROBE_INFRA:-}" && "$dir" == "core" ]]; then
    echo '{' >> "${work}/composer.json"
  fi

  git -C "$work" add -A
  git -C "$work" commit -q -m "base"
  base_sha="$(git -C "$work" rev-parse HEAD)"

  # Historical api/auth trees belong only to the base commit.
  rm -rf "${work}/packages/api" "${work}/packages/auth"
  for dep in "${dep_dirs[@]}"; do
    mkdir -p "${work}/packages/${dep}"
    rsync -a --delete "${REPO_ROOT}/packages/${dep}/" "${work}/packages/${dep}/"
  done

  stage_package_vendors "$dir" "$work"

  BC_WORK="$work" BC_CHECK_TARGET_DIR="$dir" bc_php '
$r=getenv("BC_REPO_ROOT"); $d=getenv("BC_CHECK_TARGET_DIR");
$pkg=json_decode(file_get_contents("$r/packages/$d/composer.json"),true);
$requireDev=[];
foreach($pkg["require-dev"]??[] as $name=>$constraint){
  if(str_starts_with($name,"corex/")||str_starts_with($name,"flex/")){$requireDev[$name]=$constraint;}
}
$src=getenv("BC_WORK")."/packages/$d/src";
if(is_dir($src)&&isset($pkg["require-dev"]["orchestra/testbench"])){
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
  foreach($it as $file){
    if(!$file->isFile()||!str_ends_with($file->getFilename(),".php")){continue;}
    if(str_contains((string)file_get_contents($file->getPathname()),"Orchestra\\Testbench")){
      $requireDev["orchestra/testbench"]=$pkg["require-dev"]["orchestra/testbench"];
      break;
    }
  }
}
$repos=[["type"=>"path","url"=>"packages/*"]];
$require=$pkg["require"]??[];
$work=getenv("BC_WORK");
foreach($pkg["repositories"]??[] as $repo){
  if(($repo["type"]??"")==="path"&&str_starts_with((string)($repo["url"]??""),"../")){continue;}
  if(is_dir("$work/packages/_vendor/azguard-core")&&($repo["type"]??"")==="vcs"&&str_contains((string)($repo["url"]??""),"azguard")){continue;}
  $repos[]=$repo;
}
foreach(glob("$work/packages/_vendor/*/composer.json")?:[] as $composerPath){
  $vc=json_decode((string)file_get_contents($composerPath),true);
  $vName=is_array($vc)?($vc["name"]??""):"";
  if($vName===""){continue;}
  if(!isset($require[$vName])&&!isset($requireDev[$vName])&&$vName!=="axiomasoft/stancl-jobpipeline"){continue;}
  $repos[]=["type"=>"path","url"=>"packages/_vendor/".basename(dirname($composerPath))];
  if($vName==="axioma-studio/azguard-core"){$require[$vName]="@dev";}
}
if(is_dir($src)){
  $blob="";
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
  foreach($it as $file){
    if(!$file->isFile()||!str_ends_with($file->getFilename(),".php")){continue;}
    $blob.=(string)file_get_contents($file->getPathname());
  }
  $illum=[
    "Illuminate\\Foundation\\"=>"laravel/framework",
    "Illuminate\\Console\\"=>"illuminate/console",
    "Illuminate\\Queue\\"=>"illuminate/queue",
  ];
  foreach($illum as $needle=>$pkgName){
    if(str_contains($blob,$needle)&&!isset($require[$pkgName])){
      $require[$pkgName]="^12.0|^13.0";
    }
  }
}
if(str_contains($blob??"","PHPStan\\")){$requireDev["phpstan/phpstan"]="@dev";$repos[]=["type"=>"path","url"=>"packages/_vendor/phpstan"]; }
$root=["name"=>"corex/bc-compare-root","type"=>"project","license"=>"MIT","require"=>$require,"repositories"=>$repos,"autoload"=>[],"minimum-stability"=>"dev","prefer-stable"=>true];
if($requireDev!==[]){$root["require-dev"]=$requireDev;}
foreach($pkg["autoload"]["psr-4"]??[] as $prefix=>$rel){
  $paths=is_array($rel)?$rel:[$rel];
  $mapped=[];
  foreach($paths as $one){$mapped[]="packages/$d/".ltrim((string)$one,"./");}
  $root["autoload"]["psr-4"][$prefix]=count($mapped)===1?$mapped[0]:$mapped;
  foreach($mapped as $path){$root["autoload"]["classmap"][]=$path;}
}
foreach(["classmap","files","exclude-from-classmap"] as $kind){
  foreach($pkg["autoload"][$kind]??[] as $rel){
    $root["autoload"][$kind][]="packages/$d/".ltrim((string)$rel,"./");
  }
}
echo json_encode($root,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
' > "${work}/composer.json"

  if [[ -n "${BC_CHECK_PROBE_INFRA:-}" && "$dir" == "core" ]]; then
    echo '{' >> "${work}/composer.json"
  fi

  git -C "$work" add -A
  git -C "$work" commit -q -m "candidate"
  cand_sha="$(git -C "$work" rev-parse HEAD)"

  roave_flags=(--from="${base_sha}" --to="${cand_sha}" --format=github-actions)
  if grep -q '"require-dev"' "${work}/composer.json"; then
    roave_flags+=(--install-development-dependencies)
  fi

  set +e
  roave_out="$(cd "$work" && php "$REPO_ROOT/scripts/bc/roave.php" "${roave_flags[@]}" 2>&1)"
  roave_exit=$?
  set -e

  if [[ $roave_exit -eq 0 ]]; then
    classified="compatible"
  elif [[ $roave_exit -eq 3 ]]; then
    classified="bc-break"
  else
    classified="infrastructure"
  fi

  if [[ "$classified" == "bc-break" ]]; then
    contract_hint="$(printf '%s\n' "$roave_out" | grep -oE "packages/${dir}/src/Contracts/[^\"']+" | head -1 || true)"
  fi

  echo "bc-check: ${pkg_name} (${dir}) |${classified}| stability=${stability} base=${base_sha} candidate=${cand_sha}"
  if [[ -n "$contract_hint" ]]; then
    echo "bc-check:   contract-surface: ${contract_hint}"
  fi
  if [[ "$classified" != "compatible" ]]; then
    printf '%s\n' "$roave_out" | tail -20
  fi

  if [[ "$classified" == "infrastructure" ]]; then
    return 1
  fi
  if [[ "$classified" == "bc-break" && "$stability" == "stable" ]]; then
    return 1
  fi
  return 0
}

overall=0

for dir in "${PACKAGE_DIRS[@]}"; do
  if [[ -n "${BC_CHECK_ONLY:-}" && "$dir" != "$BC_CHECK_ONLY" ]]; then
    continue
  fi

  meta="$(BC_CHECK_TARGET_DIR="$dir" bc_php '
$r=getenv("BC_REPO_ROOT"); $d=getenv("BC_CHECK_TARGET_DIR");
$pkg=json_decode(file_get_contents("$r/packages/$d/composer.json"),true);
$name=$pkg["name"]??"";
$st=$pkg["extra"]["corex"]["stability"]??"experimental";
echo "$name|$st";
')"
  pkg_name="${meta%%|*}"
  stability="${meta#*|}"
  if [[ -n "${BC_CHECK_FORCE_STABLE:-}" && "$pkg_name" == "$BC_CHECK_FORCE_STABLE" ]]; then
    stability="stable"
  fi

  work="$(mktemp -d "${TMPDIR:-/tmp}/corex-bc-check.XXXXXX")"
  set +e
  run_one_package "$dir" "$pkg_name" "$stability" "$work"
  pkg_exit=$?
  set -e
  rm -rf "$work" 2>/dev/null || true

  if [[ $pkg_exit -ne 0 ]]; then
    overall=1
  fi
done

if [[ $overall -ne 0 ]]; then
  echo "bc-check: failed" >&2
  exit 1
fi

echo "bc-check: OK"
exit 0
