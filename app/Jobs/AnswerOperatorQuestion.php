<?php

namespace App\Jobs;

use App\Services\OllamaClient;
use App\Services\OperatorAssistantGuide;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class AnswerOperatorQuestion implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public string $token, public string $question) {}

    public static function cacheKey(string $token): string
    {
        return 'asisten:'.$token;
    }

    public function handle(OllamaClient $client): void
    {
        $answer = $client->chat(OperatorAssistantGuide::systemPrompt(), $this->question);

        Cache::put(self::cacheKey($this->token), [
            'status' => $answer === null ? 'failed' : 'done',
            'question' => $this->question,
            'answer' => $answer ?? 'Asisten AI sedang tidak tersedia (server Ollama mati atau model gagal menjawab). Silakan coba lagi nanti atau bertanya ke admin sekolah.',
        ], 600);
    }
}
