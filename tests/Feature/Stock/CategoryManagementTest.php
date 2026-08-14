<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
    }

    private function category(array $attributes = []): Category
    {
        return Category::create([
            'name' => 'Alimentation',
            'slug' => 'alimentation',
            ...$attributes,
        ]);
    }

    public function test_guests_are_redirected_to_login_for_categories(): void
    {
        $this->get(route('stock.categories.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_categories(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.categories.index'))
            ->assertForbidden();
    }

    public function test_user_can_create_a_category(): void
    {
        $user = $this->userWithPermissions(['categories.create']);

        $this->actingAs($user)
            ->post(route('stock.categories.store'), [
                'name' => 'Boissons',
                'description' => 'Eaux et sodas',
                'color' => '#06b6d4',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Boissons',
            'slug' => 'boissons',
            'description' => 'Eaux et sodas',
            'color' => '#06b6d4',
        ]);
    }

    public function test_category_name_must_be_unique(): void
    {
        $user = $this->userWithPermissions(['categories.create']);
        $this->category(['name' => 'Alimentation']);

        $this->actingAs($user)
            ->post(route('stock.categories.store'), ['name' => 'Alimentation'])
            ->assertSessionHasErrors('name');
    }

    public function test_category_name_is_required(): void
    {
        $user = $this->userWithPermissions(['categories.create']);

        $this->actingAs($user)
            ->post(route('stock.categories.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_update_a_category(): void
    {
        $user = $this->userWithPermissions(['categories.update']);
        $category = $this->category();

        $this->actingAs($user)
            ->patch(route('stock.categories.update', $category), [
                'name' => 'Épicerie',
                'description' => 'Produits secs',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Épicerie',
            'slug' => 'epicerie',
        ]);
    }

    public function test_category_can_keep_its_own_name_on_update(): void
    {
        $user = $this->userWithPermissions(['categories.update']);
        $category = $this->category(['name' => 'Alimentation']);

        $this->actingAs($user)
            ->patch(route('stock.categories.update', $category), ['name' => 'Alimentation'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Alimentation']);
    }

    public function test_user_can_delete_a_category_and_products_become_uncategorized(): void
    {
        $user = $this->userWithPermissions(['categories.delete']);
        $category = $this->category();
        $product = Product::create([
            'name' => 'Riz',
            'sku' => 'RIZ-DEL-001',
            'unit' => 'kg',
            'purchase_price' => 100,
            'sale_price' => 150,
            'category_id' => $category->id,
        ]);

        $this->actingAs($user)
            ->delete(route('stock.categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertNull($product->fresh()->category_id);
    }

    public function test_user_without_delete_permission_cannot_delete_category(): void
    {
        $user = $this->userWithPermissions(['categories.view']);
        $category = $this->category();

        $this->actingAs($user)
            ->delete(route('stock.categories.destroy', $category))
            ->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
