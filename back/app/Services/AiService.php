<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use App\Models\Setting;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;

class AiService
{
    private string $provider;
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->provider = Setting::get('ai_provider', 'anthropic');
        $this->apiKey   = Setting::get('ai_api_key') ?: config('services.anthropic.key', '');
        $this->model    = Setting::get('ai_model', 'claude-sonnet-4-6');
    }

    /**
     * One-shot completion. Returns the full response text.
     */
    public function complete(string $system, array $messages, int $maxTokens = 1024): string
    {
        return match ($this->provider) {
            'openai' => $this->openAiComplete($system, $messages, $maxTokens),
            default  => $this->anthropicComplete($system, $messages, $maxTokens),
        };
    }

    /**
     * Streaming completion. Calls $onChunk for each text chunk.
     * Returns the full assembled response.
     */
    public function stream(string $system, array $messages, int $maxTokens, callable $onChunk): string
    {
        return match ($this->provider) {
            'openai' => $this->openAiStream($system, $messages, $maxTokens, $onChunk),
            default  => $this->anthropicStream($system, $messages, $maxTokens, $onChunk),
        };
    }

    // ─── Anthropic ────────────────────────────────────────────────────────────

    private function anthropicComplete(string $system, array $messages, int $maxTokens): string
    {
        $client   = new AnthropicClient(apiKey: $this->apiKey);
        $response = $client->messages->create(
            maxTokens: $maxTokens,
            model: $this->model,
            system: $system,
            messages: $messages,
        );

        return $response->content[0]->text;
    }

    private function anthropicStream(string $system, array $messages, int $maxTokens, callable $onChunk): string
    {
        $client = new AnthropicClient(apiKey: $this->apiKey);
        $stream = $client->messages->createStream(
            maxTokens: $maxTokens,
            model: $this->model,
            system: $system,
            messages: $messages,
        );

        $full = '';
        foreach ($stream as $event) {
            if (
                $event->type === 'content_block_delta'
                && isset($event->delta->type)
                && $event->delta->type === 'text_delta'
            ) {
                $chunk = $event->delta->text;
                $full .= $chunk;
                $onChunk($chunk);
            }
        }

        return $full;
    }

    // ─── OpenAI ───────────────────────────────────────────────────────────────

    private function openAiComplete(string $system, array $messages, int $maxTokens): string
    {
        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'      => $this->model,
                'messages'   => $this->withSystemMessage($system, $messages),
                'max_tokens' => $maxTokens,
            ]);

        return $response->json('choices.0.message.content', '');
    }

    private function openAiStream(string $system, array $messages, int $maxTokens, callable $onChunk): string
    {
        $guzzle   = new GuzzleClient();
        $response = $guzzle->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'      => $this->model,
                'messages'   => $this->withSystemMessage($system, $messages),
                'max_tokens' => $maxTokens,
                'stream'     => true,
            ],
            'stream' => true,
        ]);

        $body   = $response->getBody();
        $full   = '';
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(256);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if (! str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    break 2;
                }

                $json    = json_decode($data, true);
                $content = $json['choices'][0]['delta']['content'] ?? '';
                if ($content !== '') {
                    $full .= $content;
                    $onChunk($content);
                }
            }
        }

        return $full;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * OpenAI expects system as the first message with role=system.
     */
    private function withSystemMessage(string $system, array $messages): array
    {
        return array_merge(
            [['role' => 'system', 'content' => $system]],
            $messages
        );
    }
}
