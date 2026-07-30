<?php

namespace Tests\Feature\Admin;

use App\Models\KbFaq;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbFaqControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_view_the_faq_list()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/kb/faqs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('faqs', KbFaq::count()));
    }

    public function test_an_admin_can_create_a_faq_appended_to_the_end()
    {
        $admin = $this->admin();
        $maxOrder = (int) KbFaq::max('order');

        $this->actingAs($admin)->post('/admin/kb/faqs', [
            'question' => 'Apakah AkuSehat gratis?',
            'answer' => 'Ada paket gratis dan berbayar.',
            'category' => 'billing',
        ])->assertSessionHasNoErrors();

        $faq = KbFaq::where('question', 'Apakah AkuSehat gratis?')->firstOrFail();
        $this->assertSame($maxOrder + 1, $faq->order);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_faq.created']);
    }

    public function test_an_admin_can_update_a_faq()
    {
        $admin = $this->admin();
        $faq = KbFaq::orderBy('order')->firstOrFail();

        $this->actingAs($admin)->patch("/admin/kb/faqs/{$faq->id}", [
            'question' => 'Pertanyaan diperbarui?',
            'answer' => $faq->answer,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Pertanyaan diperbarui?', $faq->fresh()->question);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_faq.updated']);
    }

    public function test_an_admin_can_toggle_a_faqs_published_status()
    {
        $admin = $this->admin();
        $faq = KbFaq::where('is_published', true)->firstOrFail();

        $this->actingAs($admin)->post("/admin/kb/faqs/{$faq->id}/toggle-published")->assertSessionHasNoErrors();

        $this->assertFalse($faq->fresh()->is_published);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_faq.unpublished']);
    }

    public function test_an_unpublished_faq_is_hidden_from_the_member_facing_kb_endpoint()
    {
        $faq = KbFaq::orderBy('order')->firstOrFail();
        $faq->update(['is_published' => false]);

        $member = User::factory()->create();
        $response = $this->actingAs($member)->getJson('/kb/faqs')->assertOk();

        $ids = collect($response->json())->pluck('id');
        $this->assertNotContains($faq->id, $ids);
    }

    public function test_moving_a_faq_up_swaps_order_with_the_previous_one()
    {
        $admin = $this->admin();
        $faqs = KbFaq::orderBy('order')->take(2)->get();
        [$first, $second] = [$faqs[0], $faqs[1]];

        $this->actingAs($admin)->post("/admin/kb/faqs/{$second->id}/move-up")->assertSessionHasNoErrors();

        $this->assertSame($first->order, $second->fresh()->order);
        $this->assertSame($second->order, $first->fresh()->order);
    }

    public function test_a_non_admin_cannot_manage_the_faq_list()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/kb/faqs')->assertForbidden();
    }
}
