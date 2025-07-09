<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\{User, Prospecto};
use App\Mail\SendCredentialsMail;

class SendCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_send_credentials_endpoint()
    {
        Mail::fake();
        Http::fake();

        $user = User::create([
            'username' => 'u3',
            'email' => 'u3@example.com',
            'password_hash' => bcrypt('secret'),
            'first_name' => 'U',
            'last_name' => 'Three',
        ]);

        $this->actingAs($user);

        $student = Prospecto::create([
            'fecha' => now()->toDateString(),
            'nombre_completo' => 'Foo Bar',
            'telefono' => '1234567890',
            'correo_electronico' => 'foo@example.com',
            'genero' => 'M',
        ]);

        $response = $this->postJson("/api/students/{$student->id}/send-credentials", [
            'username' => 'foo',
            'password' => 'bar',
        ]);

        $response->assertStatus(200);

        Mail::assertSent(SendCredentialsMail::class);
    }
}
