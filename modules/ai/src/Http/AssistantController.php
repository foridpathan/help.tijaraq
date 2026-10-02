<?php

namespace Ai\Http;

use App\Conversations\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Throwable;

class AssistantController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAgent(), 403);
        Gate::authorize('index', Conversation::class);

        $data = $request->validate([
            'prompt' => 'required|string|min:2|max:4000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
        ]);

        $providerName = (string) config('services.llm_provider', 'openai');
        $provider = Provider::tryFrom($providerName);
        abort_unless($provider && in_array($providerName, ['openai', 'anthropic', 'gemini', 'openrouter'], true), 422);
        abort_unless(config("services.$providerName.api_key"), 422, 'Configure the AI provider in admin settings first.');

        $model = (string) config("services.$providerName.text_model");
        $context = '';
        if (!empty($data['conversation_id'])) {
            $conversation = Conversation::findOrFail($data['conversation_id']);
            Gate::authorize('show', $conversation);
            $context = $conversation->messages()
                ->latest('id')
                ->limit(12)
                ->get(['type', 'body', 'author'])
                ->reverse()
                ->map(fn($message) => $message->author . ': ' . strip_tags((string) $message->body))
                ->implode("\n");
            $context = mb_substr($context, 0, 8000);
        }

        try {
            $response = Prism::text()
                ->using($provider, $model)
                ->withSystemPrompt('You are a TijaraQ support assistant. Draft clear, accurate replies. Do not invent account facts, prices, or policy terms. If information is missing, say what needs checking. An agent reviews every answer before sending.')
                ->withPrompt(($context ? "Conversation:\n$context\n\n" : '') . $data['prompt'])
                ->asText();
        } catch (Throwable $e) {
            Log::warning('TijaraQ AI request failed', ['provider' => $providerName, 'exception' => get_class($e)]);
            return response()->json(['message' => 'AI request failed. Check the provider key, model and service status.'], 502);
        }

        return response()->json(['answer' => $response->text, 'provider' => $providerName, 'model' => $model]);
    }
}
