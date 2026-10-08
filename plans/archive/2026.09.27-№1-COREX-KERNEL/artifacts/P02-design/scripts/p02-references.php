<?php

declare(strict_types=1);

// Rector retains imports used only by short PHPDoc references; project their
// exact qualified tokens after its semantic pass. Same-namespace code still
// belongs to Rector, and the map has no old -> new -> new chains.
[$_, $rulesPath, $listing] = $argv;
$rules = json_decode(file_get_contents($rulesPath), true, flags: JSON_THROW_ON_ERROR);
$strictOnly = in_array('--strict-only', $argv, true);
$files = $listing === '--current'
    ? explode("\n", shell_exec('git ls-files --cached --others --exclude-standard'))
    : explode("\n", file_get_contents($listing));
$strictFiles = [];
foreach (array_filter($files) as $file) {
    if (! is_file($file) || ($listing === '--current'
        && preg_match('~^(packages|products|tests|scripts|fixtures)/.*\.php(?:\.fixture)?$~', $file) !== 1)) {
        continue;
    }
    $source = file_get_contents($file);
    $out = $strictOnly ? $source : '';
    $declaration = false;
    foreach ($strictOnly ? [] : token_get_all($source) as $token) {
        if (is_array($token) && $token[0] === T_NAMESPACE) {
            $declaration = true;
        } elseif (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && ! $declaration) {
            $lead = str_starts_with($token[1], '\\');
            $name = ltrim($token[1], '\\');
            $token[1] = ($lead ? '\\' : '').($rules['classes'][$name] ?? $name);
        }
        $out .= is_array($token) ? $token[1] : $token;

        if ($token === ';' || $token === '{') {
            $declaration = false;
        }
    }
    // Imported names must follow strict_types even in global CLI scripts.
    $offset = 0;
    $openEnd = null;
    foreach (token_get_all($out) as $token) {
        $text = is_array($token) ? $token[1] : $token;

        if (is_array($token) && $token[0] === T_OPEN_TAG) {
            $openEnd = $offset + strlen($text);
        }

        if (is_array($token) && $token[0] === T_DECLARE && $openEnd !== null
            && preg_match('/\Gdeclare\s*\(\s*strict_types\s*=\s*1\s*\);/', $out, $match, offset: $offset) === 1) {
            if (trim(substr($out, $openEnd, $offset - $openEnd)) !== '') {
                $without = substr($out, 0, $offset).substr($out, $offset + strlen($match[0]));
                $out = substr($without, 0, $openEnd)."\n".$match[0]."\n".substr($without, $openEnd);
                $strictFiles[] = $file;
            }

            break;
        }
        $offset += strlen($text);
    }

    if ($out !== $source) {
        file_put_contents($file, $out);
    }
}

if ($strictOnly) {
    file_put_contents('build/p02/strict-directive-files.json', json_encode($strictFiles, JSON_PRETTY_PRINT)."\n");
    echo 'strict directives: '.count($strictFiles)." restored\n";
}
