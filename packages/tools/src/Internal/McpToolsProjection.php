<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Operations\Validation\Laravel\JsonObjectFactory;
use CoreX\Tools\Contracts\ToolCatalogue;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use Packman\Attributes\Internal;

/** Fake in-memory MCP2025-11-25 tools/list and tools/call parity adapter. */
#[Internal]
final readonly class McpToolsProjection
{
    public const string PROFILE = 'mcp-tools-projection/1';

    public const string PROTOCOL_VERSION = 'MCP2025-11-25';

    public function __construct(
        private ToolCatalogue $catalogue,
        private ToolInvoker $invoker,
    ) {}

    /**
     * @return array{profile: string, protocol: string, tools: list<array<string, mixed>>}
     */
    public function listTools(RequestContext $context): array
    {
        $view = $this->catalogue->discover($context);

        return [
            'profile' => self::PROFILE,
            'protocol' => self::PROTOCOL_VERSION,
            'tools' => array_map(
                $this->toolEntry(...),
                $view->descriptors,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function callTool(
        RequestContext $context,
        string $wireName,
        array $arguments,
        string $requestId,
        ?string $operationKey = null,
    ): array {
        $descriptor = $this->resolveByWireName(context: $context, wireName: $wireName);

        if ($descriptor === null) {
            return $this->protocolError(
                code: -32602,
                message: 'Unknown tool: '.$wireName,
            );
        }

        $call = new ToolCall(
            code: $descriptor->code,
            toolVersion: $descriptor->toolVersion,
            expectedContractHash: $descriptor->contractHash,
            arguments: JsonObjectFactory::fromArray($arguments),
            requestId: $requestId,
            operationKey: $operationKey,
        );

        $result = $this->invoker->invoke(context: $context, call: $call);

        return $this->mapResult($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function toolEntry(ToolDescriptor $tool): array
    {
        return [
            'name' => $tool->wireName,
            'description' => $tool->description !== '' ? $tool->description : $tool->title,
            'inputSchema' => JsonCanonical::decodeObject($tool->inputSchema->canonicalJson),
            'outputSchema' => JsonCanonical::decodeObject($tool->outputSchema->canonicalJson),
            'annotations' => [
                'readOnlyHint' => $tool->sideEffect === 'read',
                'destructiveHint' => $tool->sideEffect === 'irreversible',
                'idempotentHint' => in_array($tool->idempotency, ['read-only', 'domain-key', 'provider-key'], true),
                'openWorldHint' => (bool) (JsonDocuments::map($tool->availability)['openWorld'] ?? false),
            ],
            'canonicalCode' => $tool->code,
            'toolVersion' => $tool->toolVersion,
            'contractHash' => $tool->contractHash,
        ];
    }

    private function resolveByWireName(RequestContext $context, string $wireName): ?ToolDescriptor
    {
        foreach ($this->catalogue->discover($context)->descriptors as $descriptor) {
            if ($descriptor->wireName === $wireName) {
                return $descriptor;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapResult(ToolResult $result): array
    {
        if ($result->outcome === ToolOutcome::Committed && $result->structuredData !== null) {
            return [
                'isError' => false,
                'structuredContent' => JsonCanonical::decodeObject($result->structuredData->canonicalJson),
                'operationKey' => $result->operationKey,
                'replayed' => $result->replayed,
            ];
        }

        return [
            'isError' => true,
            'structuredContent' => [
                'errorCode' => $result->errorCode ?? ToolsErrorCode::InternalError->value,
                'outcome' => $result->outcome->value,
                'operationKey' => $result->operationKey,
                'replayed' => $result->replayed,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function protocolError(int $code, string $message): array
    {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
