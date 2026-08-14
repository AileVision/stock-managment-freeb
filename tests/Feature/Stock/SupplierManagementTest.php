<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
    }

    private function supplier(array $attributes = []): Supplier
    {
        return Supplier::create([
            'name' => 'Sodigros Distribution',
            'email' => 'contact@sodigros.sn',
            ...$attributes,
        ]);
    }

    public function test_guests_are_redirected_to_login_for_suppliers(): void
    {
        $this->get(route('stock.suppliers.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_suppliers(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.suppliers.index'))
            ->assertForbidden();
    }

    public function test_user_can_create_a_supplier(): void
    {
        $user = $this->userWithPermissions(['suppliers.create']);

        $this->actingAs($user)
            ->post(route('stock.suppliers.store'), [
                'name' => 'AgroFresh Sénégal',
                'email' => 'info@agrofresh.sn',
                'phone' => '+221 76 222 11 00',
                'address' => 'Thiès',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'name' => 'AgroFresh Sénégal',
            'email' => 'info@agrofresh.sn',
            'phone' => '+221 76 222 11 00',
        ]);
    }

    public function test_supplier_name_must_be_unique(): void
    {
        $user = $this->userWithPermissions(['suppliers.create']);
        $this->supplier(['name' => 'Sodigros Distribution']);

        $this->actingAs($user)
            ->post(route('stock.suppliers.store'), ['name' => 'Sodigros Distribution'])
            ->assertSessionHasErrors('name');
    }

    public function test_invalid_email_is_rejected(): void
    {
        $user = $this->userWithPermissions(['suppliers.create']);

        $this->actingAs($user)
            ->post(route('stock.suppliers.store'), [
                'name' => 'Fournisseur Test',
                'email' => 'pas-un-email',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_update_a_supplier(): void
    {
        $user = $this->userWithPermissions(['suppliers.update']);
        $supplier = $this->supplier();

        $this->actingAs($user)
            ->patch(route('stock.suppliers.update', $supplier), [
                'name' => 'Sodigros Distribution SA',
                'email' => 'nouveau@sodigros.sn',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Sodigros Distribution SA',
            'email' => 'nouveau@sodigros.sn',
        ]);
    }

    public function test_user_can_delete_a_supplier_and_products_lose_their_supplier(): void
    {
        $user = $this->userWithPermissions(['suppliers.delete']);
        $supplier = $this->supplier();
        $product = Product::create([
            'name' => 'Câble HDMI',
            'sku' => 'CAB-DEL-001',
            'unit' => 'pcs',
            'purchase_price' => 100,
            'sale_price' => 150,
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($user)
            ->delete(route('stock.suppliers.destroy', $supplier))
            ->assertRedirect();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        $this->assertNull($product->fresh()->supplier_id);
    }

    public function test_user_without_delete_permission_cannot_delete_supplier(): void
    {
        $user = $this->userWithPermissions(['suppliers.view']);
        $supplier = $this->supplier();

        $this->actingAs($user)
            ->delete(route('stock.suppliers.destroy', $supplier))
            ->assertForbidden();

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }
}
