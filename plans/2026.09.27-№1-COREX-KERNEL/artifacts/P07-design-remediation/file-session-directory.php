<?php

declare(strict_types=1);

require dirname(__DIR__, 4).'/vendor/autoload.php';
$files = new Illuminate\Filesystem\Filesystem();
$root = sys_get_temp_dir().'/p07-session-directory-'.bin2hex(random_bytes(8));
$path = $root.'/corex-tenants/'.hash('sha256', 'A');
$handler = new Illuminate\Session\FileSessionHandler($files, $path, 120);
$warnings = [];
set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; });
try { $reportedSuccess = $handler->write(str_repeat('a', 40), serialize(['marker' => 'A'])); }
finally { restore_error_handler(); }
$lost = ! is_file($path.'/'.str_repeat('a', 40));
if (! $reportedSuccess || ! $lost || $warnings === []) { throw new RuntimeException('Baseline counterexample was not reproduced'); }
$files->ensureDirectoryExists($path, 0700, true);
$handler->write(str_repeat('a', 40), serialize(['marker' => 'A']));
$after = unserialize($handler->read(str_repeat('a', 40))) === ['marker' => 'A'];
$files->deleteDirectory($root);
if (! $after) { throw new RuntimeException('Directory precreation did not repair persistence'); }
echo json_encode(['baseline_handler_returned_true_but_lost_session' => $lost, 'warning_count' => count($warnings), 'precreated_directory_read_write' => $after, 'installed_handler_sha256' => hash_file('sha256', dirname(__DIR__,4).'/vendor/laravel/framework/src/Illuminate/Session/FileSessionHandler.php'), 'project_db_connections' => 0], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
