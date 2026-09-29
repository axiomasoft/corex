<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal;

/** @internal */
final readonly class SurfaceDiagnostic
{
    public function __construct(
        public string $code,
        public string $file,
        public int $line,
        public string $symbol,
        public string $path,
        public string $message,
    ) {}

    /** @return array{code: string, file: string, line: int, symbol: string, path: string, message: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'file' => $this->file, 'line' => $this->line, 'symbol' => $this->symbol, 'path' => $this->path, 'message' => $this->message];
    }
}
