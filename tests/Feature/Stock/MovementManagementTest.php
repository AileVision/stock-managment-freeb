<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class MovementManagementTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
    }

    private function product(array $attributes = []): Product
    {
        return Product::create([
            'name' => 'Produit test',
            'sku' => 'TEST-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'unit' => 'pcs',
            'purchase_price' => 100,
            'sale_price' => 150,
            'stock_quantity' => 10,
            ...$attributes,
        ]);
    }

    public function test_guests_are_redirected_to_login_for_movements(): void
    {
        $this->get(route('stock.movements.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_movements(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.movements.index'))
            ->assertForbidden();
    }

    public function test_movements_can_be_filtered_by_product_and_type(): void
    {
        $user = $this->userWithPermissions(['movements.view']);
        $rice = $this->product(['name' => 'Riz', 'sku' => 'RIZ-FILTRE-001']);
        $oil = $this->product(['name' => 'Huile', 'sku' => 'HUI-FILTRE-001']);

        $rice->stockMovements()->create(['type' => 'in', 'quantity' => 10, 'reason' => 'Réception']);
        $oil->stockMovements()->create(['type' => 'in', 'quantity' => 5, 'reason' => 'Réception']);
        $oil->stockMovements()->create(['type' => 'out', 'quantity' => -2, 'reason' => 'Vente']);

        $this->actingAs($user)
            ->get(route('stock.movements.index', ['product_id' => $oil->id, 'type' => 'out']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/movements')
                ->has('movements.data', 1)
                ->where('movements.data.0.product.name', 'Huile')
                ->where('movements.data.0.type', 'out'));
    }

    public function test_movements_are_ordered_by_most_recent_first(): void
    {
        $user = $this->userWithPermissions(['movements.view']);
        $product = $this->product();

        $first = $product->stockMovements()->create(['type' => 'in', 'quantity' => 1, 'reason' => 'Premier']);
        $first->forceFill(['created_at' => now()->subMinutes(2)])->save();

        $second = $product->stockMovements()->create(['type' => 'in', 'quantity' => 2, 'reason' => 'Deuxième']);
        $second->forceFill(['created_at' => now()->subMinute()])->save();

        $this->actingAs($user)
            ->get(route('stock.movements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 2)
                ->where('movements.data.0.id', $second->id)
                ->where('movements.data.1.id', $first->id));
    }

    public function test_user_can_record_a_stock_in_movement_with_reference(): void
    {
        $user = $this->userWithPermissions(['movements.create']);
        $product = $this->product(['stock_quantity' => 5]);

        $this->actingAs($user)
            ->post(route('stock.movements.store'), [
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => 15,
                'reason' => 'Réception commande fournisseur',
                'reference' => 'BL-2026-100',
            ])
            ->assertRedirect();

        $this->assertSame(20, $product->fresh()->stock_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 15,
            'reference' => 'BL-2026-100',
        ]);
    }

    public function test_movement_requires_a_reason(): void
    {
        $user = $this->userWithPermissions(['movements.create']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.movements.store'), [
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => 5,
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_quantity_must_be_positive_for_in_and_out(): void
    {
        $user = $this->userWithPermissions(['movements.create']);
        $product = $this->product();

        foreach (['in', 'out'] as $type) {
            $this->actingAs($user)
                ->post(route('stock.movements.store'), [
                    'product_id' => $product->id,
                    'type' => $type,
                    'quantity' => 0,
                    'reason' => 'Test',
                ])
                ->assertSessionHasErrors('quantity');
        }
    }

    public function test_adjustment_quantity_must_not_be_zero(): void
    {
        $user = $this->userWithPermissions(['movements.create']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.movements.store'), [
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => 0,
                'reason' => 'Inventaire',
            ])
            ->assertSessionHasErrors('quantity');
    }

    public function test_adjustment_can_increase_stock(): void
    {
        $user = $this->userWithPermissions(['movements.create']);
        $product = $this->product(['stock_quantity' => 10]);

        $this->actingAs($user)
            ->post(route('stock.movements.store'), [
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => 3,
                'reason' => 'Inventaire physique',
            ])
            ->assertRedirect();

        $this->assertSame(13, $product->fresh()->stock_quantity);
    }

    public function test_movement_requires_an_existing_product(): void
    {
        $user = $this->userWithPermissions(['movements.create']);

        $this->actingAs($user)
            ->post(route('stock.movements.store'), [
                'product_id' => 999999,
                'type' => 'in',
                'quantity' => 5,
                'reason' => 'Test',
            ])
            ->assertSessionHasErrors('product_id');
    }

    public function test_movement_records_the_authenticated_user(): void
    {
        $user = $this->userWithPermissions(['movements.create']);
        $product = $this->product();

        $this->actingAs($user)
            ->post(route('stock.movements.store'), [
                'product_id' => $product->id,
                'type' => 'out',
                'quantity' => 2,
                'reason' => 'Vente',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $user->id,
        ]);
    }
}
