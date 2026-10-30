<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_see_a_delete_button_on_reviews_and_can_remove_one(): void
    {
        $product = Product::factory()->create();
        $review = Review::factory()->for($product)->create(['comment' => 'Spam spam spam']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('products.show', $product))
            ->assertSee(route('admin.reviews.destroy', $review), false)
            ->assertSee('Spam spam spam');

        $this->delete(route('admin.reviews.destroy', $review))->assertSessionHas('status');

        $this->assertModelMissing($review);
        $this->get(route('products.show', $product))->assertDontSee('Spam spam spam');
    }

    public function test_customers_do_not_see_the_button_and_cannot_delete_reviews(): void
    {
        $product = Product::factory()->create();
        $review = Review::factory()->for($product)->create();

        $this->get(route('products.show', $product))->assertDontSee(route('admin.reviews.destroy', $review), false);

        $this->actingAs(User::factory()->create())
            ->get(route('products.show', $product))
            ->assertDontSee(route('admin.reviews.destroy', $review), false);

        $this->delete(route('admin.reviews.destroy', $review))->assertForbidden();

        $this->assertModelExists($review);
    }
}
