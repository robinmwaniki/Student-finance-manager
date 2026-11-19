<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_browser_local_qr_generator(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('tools.qr'))
            ->assertOk()
            ->assertSee('Plain text')
            ->assertSee('Public website link')
            ->assertSee('M-Pesa payment reference')
            ->assertSee('I confirm this content contains no passwords')
            ->assertSee('The text or payment reference is encoded locally')
            ->assertSee('qrcode-generator@2.0.4/dist/qrcode_UTF8.js')
            ->assertSee('qr-payload.js')
            ->assertSee('qr-generator.js')
            ->assertSee('Download QR image');
    }

    public function test_qr_generator_is_linked_from_financial_tools_page(): void
    {
        $this->actingAs($this->makeUser())->get(route('tools.index'))
            ->assertOk()
            ->assertSee('QR code generator')
            ->assertSee(route('tools.qr'));
    }

    public function test_qr_generator_requires_authentication_and_does_not_accept_payload_posts(): void
    {
        $this->get(route('tools.qr'))->assertRedirect(route('login'));

        $this->actingAs($this->makeUser())
            ->post(route('tools.qr'), ['payload' => 'private user text'])
            ->assertStatus(405);
    }

    private function makeUser(): User
    {
        return User::create([
            'fullname' => 'QR Test User',
            'email' => 'qr-'.Str::uuid().'@example.test',
            'password' => 'StrongPassword123',
        ]);
    }
}
