<?php

namespace Tests\Unit\Services\Ai;

use App\Domain\Ai\Exceptions\AiServiceException;
use App\Services\Ai\Drivers\GeminiLlmClient;
use App\Services\Ai\LlmMessage;
use App\Services\Ai\LlmToolCall;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiLlmClientTest extends TestCase
{
    private function client(?string $key = 'test-key'): GeminiLlmClient
    {
        return new GeminiLlmClient([
            'api_key' => $key,
            'model' => 'gemini-3.6-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 25,
            'max_output_tokens' => 8192,
            'thinking_level' => 'low',
        ]);
    }

    public function test_is_configured_reflects_api_key_presence(): void
    {
        $this->assertTrue($this->client('k')->isConfigured());
        $this->assertFalse($this->client(null)->isConfigured());
    }

    public function test_chat_without_key_throws(): void
    {
        $this->expectException(AiServiceException::class);
        $this->client(null)->chat([LlmMessage::user('salut')]);
    }

    public function test_chat_sends_contents_system_and_tools_and_parses_text(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Bonjour !']]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 3, 'totalTokenCount' => 15],
            ]),
        ]);

        $response = $this->client()->chat(
            [LlmMessage::user('Salut')],
            [['name' => 'ping', 'description' => 'test', 'parameters' => ['type' => 'object', 'properties' => []]]],
            ['system' => 'Sois bref.'],
        );

        $this->assertSame('Bonjour !', $response->text);
        $this->assertFalse($response->hasToolCalls());
        $this->assertSame(15, $response->usage['total_tokens']);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return str_contains($request->url(), 'models/gemini-3.6-flash:generateContent?key=test-key')
                && $body['systemInstruction']['parts'][0]['text'] === 'Sois bref.'
                && $body['contents'][0]['role'] === 'user'
                && $body['contents'][0]['parts'][0]['text'] === 'Salut'
                && $body['tools'][0]['functionDeclarations'][0]['name'] === 'ping'
                && $body['generationConfig']['maxOutputTokens'] === 8192
                && $body['generationConfig']['thinkingConfig']['thinkingLevel'] === 'low';
        });
    }

    public function test_chat_parses_function_calls(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['functionCall' => ['name' => 'list_sources', 'args' => ['q' => 'chomage']]],
                    ]],
                ]],
            ]),
        ]);

        $response = $this->client()->chat([LlmMessage::user('cherche')]);

        $this->assertNull($response->text);
        $this->assertTrue($response->hasToolCalls());
        $this->assertSame('list_sources', $response->toolCalls[0]->name);
        $this->assertSame(['q' => 'chomage'], $response->toolCalls[0]->arguments);
    }

    public function test_thought_signature_is_captured_and_echoed_back(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['candidates' => [['content' => ['parts' => [
                    ['functionCall' => ['name' => 'list_sources', 'args' => []], 'thoughtSignature' => 'SIG-123'],
                ]]]]])
                ->push(['candidates' => [['content' => ['parts' => [['text' => 'fini']]]]]]),
        ]);

        $first = $this->client()->chat([LlmMessage::user('go')]);
        $this->assertSame('SIG-123', $first->toolCalls[0]->thoughtSignature);

        $this->client()->chat([
            LlmMessage::user('go'),
            LlmMessage::model(null, $first->toolCalls),
            LlmMessage::toolResults([['name' => 'list_sources', 'content' => ['ok' => true]]]),
        ]);

        Http::assertSent(function (Request $request) {
            foreach ($request->data()['contents'] as $turn) {
                foreach ($turn['parts'] as $part) {
                    if (($part['thoughtSignature'] ?? null) === 'SIG-123') {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    public function test_max_tokens_without_content_raises_a_clear_error(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => new \stdClass, 'finishReason' => 'MAX_TOKENS']],
            ]),
        ]);

        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessageMatches('/MAX_TOKENS/');

        $this->client()->chat([LlmMessage::user('salut')]);
    }

    public function test_tool_results_are_encoded_as_function_responses(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ]),
        ]);

        $this->client()->chat([
            LlmMessage::user('cherche'),
            LlmMessage::model(null, [new LlmToolCall('list_sources-0', 'list_sources', ['q' => 'x'])]),
            LlmMessage::toolResults([['name' => 'list_sources', 'content' => ['sources' => []]]]),
        ]);

        Http::assertSent(function (Request $request) {
            $parts = $request->data()['contents'][2]['parts'];

            return $request->data()['contents'][2]['role'] === 'user'
                && $parts[0]['functionResponse']['name'] === 'list_sources'
                && $parts[0]['functionResponse']['response'] === ['sources' => []];
        });
    }

    public function test_provider_error_is_wrapped(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'bad model']], 400),
        ]);

        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessageMatches('/bad model/');

        $this->client()->chat([LlmMessage::user('salut')]);
    }

    public function test_429_maps_to_a_friendly_rate_limit_error(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'RESOURCE_EXHAUSTED']], 429),
        ]);

        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessageMatches('/[Qq]uota/');

        $this->client()->chat([LlmMessage::user('salut')]);
    }
}
