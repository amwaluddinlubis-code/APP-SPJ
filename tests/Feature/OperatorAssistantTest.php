<?php

namespace Tests\Feature;

use App\Jobs\AnswerOperatorQuestion;
use App\Models\User;
use App\Services\OllamaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperatorAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_returns_answer_on_success(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response(['message' => ['content' => 'Buka menu Transaksi.']], 200),
        ]);

        $answer = app(OllamaClient::class)->chat('sistem', 'bagaimana?');

        $this->assertSame('Buka menu Transaksi.', $answer);
    }

    public function test_client_returns_null_on_failure(): void
    {
        Http::fake(['localhost:11434/*' => Http::response(null, 500)]);

        $this->assertNull(app(OllamaClient::class)->chat('sistem', 'bagaimana?'));
    }

    public function test_guest_cannot_open_assistant(): void
    {
        $this->get('/asisten')->assertRedirect('/masuk');
    }

    public function test_operator_asks_and_polls_answer(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response(['message' => ['content' => 'Siap dinomori dulu.']], 200),
        ]);
        $this->actingAs(User::factory()->create());

        $this->get('/asisten')->assertOk();

        $response = $this->post('/asisten/tanya', ['question' => 'Kapan paket siap dinomori?']);
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);
        $this->assertArrayHasKey('t', $query);

        // Queue sync pada testing: job jalan inline sehingga jawaban langsung ada.
        $status = $this->getJson('/asisten/status/'.$query['t']);
        $status->assertOk()->assertJsonPath('status', 'done')->assertJsonPath('answer', 'Siap dinomori dulu.');
        $this->assertSame('Siap dinomori dulu.', Cache::get(AnswerOperatorQuestion::cacheKey($query['t']))['answer']);
    }

    public function test_question_is_validated(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/asisten/tanya', ['question' => ''])->assertSessionHasErrors('question');
    }

    public function test_status_rejects_unknown_token(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/asisten/status/bukan-uuid')->assertNotFound();
        $this->getJson('/asisten/status/'.'00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_widget_json_flow_returns_token_then_answer(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response(['message' => ['content' => 'Jawaban widget.']], 200),
        ]);
        $this->actingAs(User::factory()->create());

        $ask = $this->postJson('/asisten/tanya', ['question' => 'Halo widget?']);
        $ask->assertStatus(202)->assertJsonStructure(['token']);

        $this->getJson('/asisten/status/'.$ask->json('token'))
            ->assertOk()->assertJsonPath('status', 'done')->assertJsonPath('answer', 'Jawaban widget.');
    }

    public function test_widget_present_in_app_layout(): void
    {
        $this->actingAs(User::factory()->create());

        // Halaman mana pun berlayout app harus memuat widget asisten global.
        $this->get('/asisten')->assertOk()->assertSee('asisten/status', false);
    }
}
