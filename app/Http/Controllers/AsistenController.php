<?php

namespace App\Http\Controllers;

use App\Jobs\AnswerOperatorQuestion;
use App\Services\OllamaClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AsistenController extends Controller
{
    public function index(Request $request, OllamaClient $client): View
    {
        $token = is_string($request->query('t')) && Str::isUuid($request->query('t')) ? $request->query('t') : null;

        return view('asisten.index', [
            'token' => $token,
            'ollamaReady' => $client->enabled(),
        ]);
    }

    public function ask(Request $request, OllamaClient $client): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        if (! $client->enabled()) {
            return back()->with('error', 'Asisten AI belum dikonfigurasi (OLLAMA_HOST/OLLAMA_MODEL kosong).');
        }

        $token = (string) Str::uuid();
        cache()->put(AnswerOperatorQuestion::cacheKey($token), [
            'status' => 'pending',
            'question' => $data['question'],
            'answer' => null,
        ], 600);

        AnswerOperatorQuestion::dispatch($token, $data['question']);

        if ($request->wantsJson()) {
            return response()->json(['token' => $token], 202);
        }

        return redirect()->route('asisten.index', ['t' => $token]);
    }

    public function status(string $token): JsonResponse
    {
        if (! Str::isUuid($token)) {
            abort(404);
        }

        $state = cache()->get(AnswerOperatorQuestion::cacheKey($token));
        if (! is_array($state)) {
            abort(404);
        }

        return response()->json($state);
    }
}
