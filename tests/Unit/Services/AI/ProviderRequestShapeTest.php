<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AIProviderException;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\LMStudioProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderRequestShapeTest extends TestCase
{
    // No RefreshDatabase - these adapters don't touch the database, and
    // Http::fake()/assertSent() only need the app container Tests\TestCase
    // already boots.

    public function test_openai_sends_bearer_auth_and_json_object_response_format()
    {
        Http::fake(['api.openai.com/*' => Http::response($this->successBody(), 200)]);

        $provider = new OpenAIProvider(apiKey: 'sk-test', modelKey: 'gpt-5.5');
        $provider->analyze(['prompt' => 'hello']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-test')
                && $request['model'] === 'gpt-5.5'
                && $request['messages'][0]['content'] === 'hello'
                && $request['response_format']['type'] === 'json_object';
        });
    }

    public function test_openai_respects_a_custom_base_url()
    {
        Http::fake(['*' => Http::response($this->successBody(), 200)]);

        $provider = new OpenAIProvider(apiKey: 'sk-test', baseUrl: 'https://my-proxy.example.com/v1', modelKey: 'gpt-5.5');
        $provider->analyze(['prompt' => 'hello']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://my-proxy.example.com/v1/chat/completions');
    }

    public function test_claude_sends_x_api_key_and_anthropic_version_headers()
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => '{"ok":true}']]], 200)]);

        $provider = new ClaudeProvider(apiKey: 'sk-ant-test', modelKey: 'claude-sonnet-5');
        $provider->analyze(['prompt' => 'hello']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'sk-ant-test')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $request['messages'][0]['role'] === 'user';
        });
    }

    public function test_claude_folds_system_content_into_the_first_user_turn()
    {
        Http::fake(['*' => Http::response(['content' => [['text' => '{"reply":"hi"}']]], 200)]);

        $provider = new ClaudeProvider(apiKey: 'sk-ant-test', modelKey: 'claude-sonnet-5');
        $provider->chat([['role' => 'user', 'content' => 'What should I eat?']], ['prompt' => 'SYSTEM CONTEXT']);

        Http::assertSent(function (Request $request) {
            $messages = $request['messages'];

            return count($messages) === 1
                && $messages[0]['role'] === 'user'
                && str_contains($messages[0]['content'], 'SYSTEM CONTEXT')
                && str_contains($messages[0]['content'], 'What should I eat?');
        });
    }

    public function test_groq_uses_the_openai_compatible_endpoint_without_response_format()
    {
        Http::fake(['api.groq.com/*' => Http::response($this->successBody(), 200)]);

        $provider = new GroqProvider(apiKey: 'gsk-test', modelKey: 'llama-4-70b');
        $provider->analyze(['prompt' => 'hello']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer gsk-test')
                && ! isset($request['response_format']);
        });
    }

    public function test_groq_extracts_json_from_a_response_wrapped_in_prose()
    {
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => "Sure, here you go:\n\n{\"summary\":\"ok\"}\n\nLet me know if you need anything else!"]]],
        ], 200)]);

        $provider = new GroqProvider(apiKey: 'gsk-test', modelKey: 'llama-4-70b');
        $result = $provider->analyze(['prompt' => 'hello']);

        $this->assertSame(['summary' => 'ok'], $result);
    }

    public function test_gemini_sends_the_api_key_as_a_query_parameter_and_native_json_mode()
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]]]],
        ], 200)]);

        $provider = new GeminiProvider(apiKey: 'AIza-test', modelKey: 'gemini-2.5-flash');
        $provider->analyze(['prompt' => 'hello']);

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent')
                && str_contains($request->url(), 'key=AIza-test')
                && $request['generationConfig']['responseMimeType'] === 'application/json'
                && $request['contents'][0]['parts'][0]['text'] === 'hello';
        });
    }

    public function test_ollama_targets_the_local_base_url_with_json_format()
    {
        Http::fake(['localhost:11434/*' => Http::response(['message' => ['content' => '{"ok":true}']], 200)]);

        $provider = new OllamaProvider(baseUrl: 'http://localhost:11434', modelKey: 'llama3:8b');
        $provider->analyze(['prompt' => 'hello']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://localhost:11434/api/chat'
                && $request['format'] === 'json'
                && $request['stream'] === false;
        });
    }

    public function test_lm_studio_uses_the_openai_wire_format_against_a_local_url()
    {
        Http::fake(['localhost:1234/*' => Http::response($this->successBody(), 200)]);

        $provider = new LMStudioProvider(baseUrl: 'http://localhost:1234', modelKey: 'local-model');
        $provider->analyze(['prompt' => 'hello']);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://localhost:1234/v1/chat/completions');
    }

    public function test_a_5xx_response_throws_a_retryable_non_timeout_exception()
    {
        Http::fake(['api.openai.com/*' => Http::response('Server error', 500)]);

        $provider = new OpenAIProvider(apiKey: 'sk-test', modelKey: 'gpt-5.5');

        try {
            $provider->analyze(['prompt' => 'hello']);
            $this->fail('Expected AIProviderException was not thrown.');
        } catch (AIProviderException $e) {
            $this->assertTrue($e->isRetryable());
            $this->assertFalse($e->isTimeout());
            $this->assertFalse($e->isInvalidJson());
        }
    }

    public function test_a_401_response_throws_a_non_retryable_exception()
    {
        Http::fake(['api.openai.com/*' => Http::response('Unauthorized', 401)]);

        $provider = new OpenAIProvider(apiKey: 'sk-bad', modelKey: 'gpt-5.5');

        try {
            $provider->analyze(['prompt' => 'hello']);
            $this->fail('Expected AIProviderException was not thrown.');
        } catch (AIProviderException $e) {
            $this->assertFalse($e->isRetryable());
        }
    }

    public function test_malformed_json_content_throws_an_invalid_json_exception()
    {
        Http::fake(['api.openai.com/*' => Http::response($this->successBody('not json at all'), 200)]);

        $provider = new OpenAIProvider(apiKey: 'sk-test', modelKey: 'gpt-5.5');

        try {
            $provider->analyze(['prompt' => 'hello']);
            $this->fail('Expected AIProviderException was not thrown.');
        } catch (AIProviderException $e) {
            $this->assertTrue($e->isInvalidJson());
            $this->assertTrue($e->isRetryable());
        }
    }

    private function successBody(string $content = '{"ok":true}'): array
    {
        return ['choices' => [['message' => ['content' => $content]]]];
    }
}
