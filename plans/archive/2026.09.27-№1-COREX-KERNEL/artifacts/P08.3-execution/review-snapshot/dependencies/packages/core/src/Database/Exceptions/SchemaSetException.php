<?php

declare(strict_types=1);

namespace CoreX\Database\Exceptions;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SchemaSetException extends CodedException
{
    private function __construct(string $message, private readonly CoreErrorCode $errorCode)
    {
        parent::__construct($message);
    }

    public static function invalidSet(string $id): self
    {
        return new self("invalid_set: Invalid schema set [$id].", CoreErrorCode::SchemaInvalidSet);
    }

    public static function duplicateSet(string $id): self
    {
        return new self("duplicate_set: Schema set [$id] is declared twice.", CoreErrorCode::SchemaDuplicateSet);
    }

    public static function duplicateMigration(string $name, string $first, string $second): self
    {
        return new self("duplicate_migration: Migration [$name] belongs to [$first] and [$second].", CoreErrorCode::SchemaDuplicateMigration);
    }

    public static function unknownSet(string $id): self
    {
        return new self("unknown_set: Schema set [$id] is unavailable.", CoreErrorCode::SchemaUnknownSet);
    }

    public static function unsupportedDriver(string $id, string $driver): self
    {
        return new self("unsupported_driver: Schema set [$id] does not support [$driver].", CoreErrorCode::SchemaUnsupportedDriver);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor($this->errorCode, ErrorCategory::Internal, retryable: false);
    }
}
