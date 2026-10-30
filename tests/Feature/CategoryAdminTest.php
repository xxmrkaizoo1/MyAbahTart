<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_the_collections_page_lists_every_collection(): void
    {
        $category = Category::factory()->create(['name' => 'Festive Specials']);
        Product::factory()->count(2)->for($category)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Festive Specials')
            ->assertSee('2 products')
            ->assertSee('New collection');
    }

    public function test_an_admin_can_create_a_collection(): void
    {
        Category::factory()->create(['sort_order' => 4]);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => '  Festive Specials ', 'art' => 'mango'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $category = Category::firstWhere('name', 'Festive Specials');

        $this->assertNotNull($category);
        $this->assertSame('festive-specials', $category->slug);
        $this->assertSame(5, $category->sort_order);
    }

    public function test_collection_names_must_be_unique_and_use_a_known_illustration(): void
    {
        Category::factory()->create(['name' => 'Classics']);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Classics', 'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['name', 'image']);

        $this->assertSame(1, Category::count());
    }

    public function test_an_admin_can_rename_and_reorder_a_collection(): void
    {
        $category = Category::factory()->create(['name' => 'Classics', 'slug' => 'classics', 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->patch(route('admin.categories.update', $category), ['name' => 'Timeless Classics', 'art' => 'egg', 'sort_order' => 9])
            ->assertSessionHasNoErrors();

        $category->refresh();

        $this->assertSame('Timeless Classics', $category->name);
        $this->assertSame('timeless-classics', $category->slug);
        $this->assertSame(9, $category->sort_order);
    }

    public function test_edit_errors_are_kept_separate_from_the_create_form(): void
    {
        Category::factory()->create(['name' => 'Classics']);
        $category = Category::factory()->create(['name' => 'Savoury']);

        $this->actingAs($this->admin)
            ->patch(route('admin.categories.update', $category), ['name' => 'Classics', 'art' => 'savory'])
            ->assertSessionHasErrorsIn('category'.$category->id, ['name']);

        $this->assertSame('Savoury', $category->fresh()->name);
    }

    public function test_a_collection_with_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertModelExists($category);
    }

    public function test_an_empty_collection_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('status');

        $this->assertModelMissing($category);
    }

    public function test_customers_cannot_manage_collections(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.categories.store'), ['name' => 'Sneaky', 'art' => 'mango'])
            ->assertForbidden();

        $this->assertSame(0, Category::count());
    }

    public function test_a_collection_can_have_a_photo_or_falls_back_to_the_default_picture(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Raya Specials', 'image' => UploadedFile::fake()->image('raya.jpg')])
            ->assertSessionHasNoErrors();

        $category = Category::sole();
        Storage::disk('public')->assertExists($category->image_path);
        $this->get(route('home'))->assertSee($category->image_path);

        $photo = $category->image_path;
        $this->patch(route('admin.categories.update', $category), ['name' => 'Raya Specials', 'remove_image' => '1'])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($photo);
        $this->assertNull($category->fresh()->image_path);
        $this->assertStringEndsWith('images/default-product.svg', $category->fresh()->imageUrl());
    }
}
