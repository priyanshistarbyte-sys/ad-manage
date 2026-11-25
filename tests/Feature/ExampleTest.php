<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        User::factory()->admin()->create();

        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_pin_login_and_post_logout(): void
    {
        User::factory()->admin()->withPin('654321')->create();

        $this->post('/login', ['pin' => '654321'])->assertRedirect('/');
        $this->get('/')->assertOk();

        $this->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_setup_is_forbidden_to_guests_once_installed(): void
    {
        User::factory()->admin()->create();

        $this->get('/setup')->assertForbidden();
        $this->get('/setup?reset=1')->assertForbidden();
        $this->post('/setup?reset=1', ['confirm' => 'RESET'])->assertForbidden();
    }

    public function test_connections_are_admin_only(): void
    {
        User::factory()->withPin('111111')->create();   // non-admin
        User::factory()->admin()->withPin('222222')->create();

        $this->post('/login', ['pin' => '111111']);
        $this->get('/connections')->assertForbidden();
        $this->post('/logout');

        $this->post('/login', ['pin' => '222222']);
        $this->get('/connections')->assertOk();
    }

    public function test_edit_form_does_not_leak_secrets(): void
    {
        User::factory()->admin()->withPin('222222')->create();
        $c = \App\Models\Connection::create([
            'name' => 'MCC', 'developer_token' => 'dev', 'client_id' => 'cid',
            'client_secret' => 'GOCSPX-supersecret', 'refresh_token' => '1//refreshsecret', 'active' => true,
        ]);

        $this->post('/login', ['pin' => '222222']);
        $this->get("/connections/{$c->id}/edit")
            ->assertOk()
            ->assertDontSee('supersecret')
            ->assertDontSee('refreshsecret');

        // Blank secret fields keep the stored values.
        $this->put("/connections/{$c->id}", ['name' => 'MCC 2', 'client_secret' => '', 'refresh_token' => '']);
        $c->refresh();
        $this->assertSame('GOCSPX-supersecret', $c->client_secret);
        $this->assertSame('MCC 2', $c->name);
    }

    public function test_settings_round_trip_on_any_database(): void
    {
        setSetting('foo', 'one');
        setSetting('foo', 'two');

        $this->assertSame('two', getSetting('foo'));
    }
}
