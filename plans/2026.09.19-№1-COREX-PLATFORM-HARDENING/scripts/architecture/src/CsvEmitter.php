<?php

declare(strict_types=1);

final class CsvEmitter
{
    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string>>  $rows
     */
    public static function write(string $path, array $headers, array $rows): void
    {
        usort($rows, static function (array $a, array $b): int {
            return [$a['package'], $a['file'], $a['symbol'], $a['symbol_kind']]
                <=> [$b['package'], $b['file'], $b['symbol'], $b['symbol_kind']];
        });

        $dir = dirname($path);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('cannot create '.$dir);
        }

        $fh = fopen($path, 'wb');

        if ($fh === false) {
            throw new RuntimeException('cannot write '.$path);
        }

        self::line($fh, $headers);
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $header) {
                $cells[] = $row[$header] ?? '';
            }
            self::line($fh, $cells);
        }
        fclose($fh);
    }

    /**
     * Записать CSV без пересортировки (для tables/config, где порядок задан снаружи).
     *
     * @param  list<string>  $headers
     * @param  list<array<string, string>>  $rows
     */
    public static function writeRaw(string $path, array $headers, array $rows): void
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('cannot create '.$dir);
        }

        $fh = fopen($path, 'wb');

        if ($fh === false) {
            throw new RuntimeException('cannot write '.$path);
        }

        self::line($fh, $headers);
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $header) {
                $cells[] = $row[$header] ?? '';
            }
            self::line($fh, $cells);
        }
        fclose($fh);
    }

    /** @param  list<string>  $cells */
    private static function line($fh, array $cells): void
    {
        $out = [];
        foreach ($cells as $cell) {
            $text = str_replace(["\r", "\n"], [' ', ' '], $cell);

            if (str_contains($text, ',') || str_contains($text, '"')) {
                $out[] = '"'.str_replace('"', '""', $text).'"';
            } else {
                $out[] = $text;
            }
        }
        fwrite($fh, implode(',', $out)."\n");
    }
}
