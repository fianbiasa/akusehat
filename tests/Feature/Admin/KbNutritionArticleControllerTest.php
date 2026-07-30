<?php

namespace Tests\Feature\Admin;

use App\Models\KbNutritionArticle;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbNutritionArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id')]);
    }

    public function test_an_admin_can_view_the_article_list()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/kb/articles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('articles', KbNutritionArticle::count()));
    }

    public function test_an_admin_can_create_an_article_with_a_generated_slug()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/kb/articles', [
            'title' => 'Panduan Gizi Seimbang',
            'category' => 'gizi',
            'content' => 'Isi artikel lengkap.',
            'tags' => ['gizi', 'seimbang'],
        ])->assertSessionHasNoErrors();

        $article = KbNutritionArticle::where('title', 'Panduan Gizi Seimbang')->firstOrFail();
        $this->assertSame('panduan-gizi-seimbang', $article->slug);
        $this->assertTrue($article->is_published);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_article.created']);
    }

    public function test_an_admin_can_update_an_article()
    {
        $admin = $this->admin();
        $article = KbNutritionArticle::firstOrFail();

        $this->actingAs($admin)->patch("/admin/kb/articles/{$article->id}", [
            'title' => $article->title,
            'content' => 'Konten yang diperbarui.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Konten yang diperbarui.', $article->fresh()->content);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_article.updated']);
    }

    public function test_an_admin_can_toggle_an_articles_published_status()
    {
        $admin = $this->admin();
        $article = KbNutritionArticle::where('is_published', true)->firstOrFail();

        $this->actingAs($admin)->post("/admin/kb/articles/{$article->id}/toggle-published")->assertSessionHasNoErrors();

        $this->assertFalse($article->fresh()->is_published);
        $this->assertDatabaseHas('activity_logs', ['action' => 'kb_article.unpublished']);
    }

    public function test_an_unpublished_article_is_hidden_from_the_member_facing_kb_endpoint()
    {
        $article = KbNutritionArticle::firstOrFail();
        $article->update(['is_published' => false]);

        $member = User::factory()->create();
        $response = $this->actingAs($member)->getJson('/kb/articles')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($article->id, $ids);
    }

    public function test_a_non_admin_cannot_manage_the_article_list()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/kb/articles')->assertForbidden();
    }
}
