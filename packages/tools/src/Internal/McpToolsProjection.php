<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\JsonCanonical;
use CoreX\Tools\Contracts\ToolCatalogue;
use CoreX\Tools\Contracts\ToolInvoker;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolResult;

/** Fake in-memory MCP2025-11-25 tools/list and tools/call parity adapter. */
final class McpToolsProjection
{
    public const PROFILE = 'mcp-tools-projection/1';

    public const PROTOCOL_VERSION = 'MCP2025-11-25';

    public function __construct(
        private readonly ToolCatalogue $catalogue,
        private readonly ToolInvoker $invoker,
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
                fn (ToolDescriptor $tool): array => $this->toolEntry($tool),
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
        $descriptor = $this->resolveByWireName($context, $wireName);

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
            arguments: JsonObject::fromArray($arguments),
            requestId: $requestId,
            operationKey: $operationKey,
        );

        $result = $this->invoker->invoke($context, $call);

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
                'openWorldHint' => (bool) ($tool->availability['openWorld'] ?? false),
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
        if ($result->outcome === ToolResult::OUTCOME_COMMITTED && $result->structuredData !== null) {
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
                'errorCode' => $result->errorCode ?? 'internal_error',
                'outcome' => $result->outcome,
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
