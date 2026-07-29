<?php

namespace Tests\Feature\Kb;

use App\Models\KbFood;
use App\Models\KbNutritionArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_search_the_knowledge_base()
    {
        $this->getJson('/kb/foods')->assertUnauthorized();
    }

    public function test_an_authenticated_user_can_search_foods_by_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/kb/foods?q=Nasi');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name_local');
        $this->assertTrue($names->contains('Nasi Putih'));
        $this->assertTrue($names->every(fn ($name) => str_contains($name, 'Nasi')));
    }

    public function test_foods_can_be_filtered_by_category()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/kb/foods?category=drink');

        $response->assertOk();
        $categories = collect($response->json('data'))->pluck('category')->unique();
        $this->assertEqualsCanonicalizing(['drink'], $categories->all());
    }

    public function test_foods_can_be_filtered_by_tag()
    {
        $user = User::factory()->create();
        $lowPurineCount = KbFood::query()->whereJsonContains('tags', 'low_purine')->count();

        $response = $this->actingAs($user)->getJson('/kb/foods?'.http_build_query(['tags' => ['low_purine']]));

        $response->assertOk();
        $this->assertCount($lowPurineCount, $response->json('data'));
    }

    public function test_disease_list_and_detail_are_reachable()
    {
        $user = User::factory()->create();

        $index = $this->actingAs($user)->getJson('/kb/diseases');
        $index->assertOk();
        $first = $index->json('0');

        $this->actingAs($user)->getJson("/kb/diseases/{$first['id']}")->assertOk()->assertJsonPath('slug', $first['slug']);
    }

    public function test_published_articles_are_listed_and_unpublished_are_not()
    {
        $user = User::factory()->create();
        KbNutritionArticle::create([
            'title' => 'Draft article', 'slug' => 'draft-article', 'content' => 'x', 'is_published' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/kb/articles');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertFalse($slugs->contains('draft-article'));
    }

    public function test_faqs_are_ordered()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/kb/faqs');

        $response->assertOk();
        $orders = collect($response->json())->pluck('order');
        $this->assertSame($orders->sort()->values()->all(), $orders->all());
    }
}
