<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin client for a local Ollama server. All failures degrade to null so
 * AI features can disable themselves gracefully without breaking SPJ flows.
 */
class OllamaClient
{
    public function enabled(): bool
    {
        return filled(config('ollama.host')) && filled(config('ollama.model'));
    }

    /**
     * Ask the model one chat completion. Returns the answer text or null.
     */
    public function chat(string $system, string $user, ?int $maxTokens = null): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('ollama.timeout', 180))
                ->post(rtrim((string) config('ollama.host'), '/').'/api/chat', [
                    'model' => config('ollama.model'),
                    'stream' => false,
                    'options' => ['num_predict' => $maxTokens ?? (int) config('ollama.max_tokens', 150)],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Ollama menolak permintaan asisten.', ['status' => $response->status()]);

                return null;
            }

            $answer = trim((string) $response->json('message.content', ''));

            return $answer === '' ? null : $answer;
        } catch (Throwable $exception) {
            Log::warning('Ollama tidak dapat dihubungi.', ['error' => $exception->getMessage()]);

            return null;
        }
    }
}
