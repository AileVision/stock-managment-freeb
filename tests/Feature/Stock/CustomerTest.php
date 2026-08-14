<?php

namespace Tests\Feature\Stock;

use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
    }

    public function test_guests_are_redirected_to_login_for_customers(): void
    {
        $this->get(route('stock.customers.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_customers(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('stock.customers.index'))
            ->assertForbidden();
    }

    public function test_user_can_create_a_customer(): void
    {
        $user = $this->userWithPermissions(['customers.view', 'customers.create']);

        $this->actingAs($user)
            ->post(route('stock.customers.store'), [
                'name' => 'Épicerie du Centre',
                'email' => 'contact@epicerie.com',
                'phone' => '0708091011',
                'address' => 'Yopougon, Abidjan',
            ])
            ->assertRedirect(route('stock.customers.index'));

        $this->assertDatabaseHas('customers', [
            'name' => 'Épicerie du Centre',
            'email' => 'contact@epicerie.com',
        ]);
    }

    public function test_customer_name_is_required(): void
    {
        $user = $this->userWithPermissions(['customers.create']);

        $this->actingAs($user)
            ->post(route('stock.customers.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_user_without_create_permission_cannot_create_a_customer(): void
    {
        $user = $this->userWithPermissions(['customers.view']);

        $this->actingAs($user)
            ->post(route('stock.customers.store'), ['name' => 'Client A'])
            ->assertForbidden();

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_user_can_update_a_customer(): void
    {
        $user = $this->userWithPermissions(['customers.view', 'customers.update']);
        $customer = Customer::create(['name' => 'Ancien nom']);

        $this->actingAs($user)
            ->patch(route('stock.customers.update', $customer), [
                'name' => 'Nouveau nom',
                'phone' => '0700000000',
            ])
            ->assertRedirect(route('stock.customers.index'));

        $this->assertSame('Nouveau nom', $customer->fresh()->name);
    }

    public function test_deleting_a_customer_keeps_quote_snapshot(): void
    {
        $user = $this->userWithPermissions(['customers.view', 'customers.delete']);
        $customer = Customer::create(['name' => 'Client fidèle', 'email' => 'client@exemple.com']);

        $quote = Quote::create([
            'reference' => 'D-20260814-0001',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'customer_name' => 'Client fidèle',
            'customer_email' => 'client@exemple.com',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->delete(route('stock.customers.destroy', $customer))
            ->assertRedirect(route('stock.customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertSame('Client fidèle', $quote->fresh()->customer_name);
        $this->assertNull($quote->fresh()->customer_id);
    }

    public function test_customers_can_be_searched(): void
    {
        $user = $this->userWithPermissions(['customers.view']);

        Customer::create(['name' => 'Épicerie Alpha', 'email' => 'a@exemple.com']);
        Customer::create(['name' => 'Restaurant Bêta', 'email' => 'b@exemple.com']);

        $this->actingAs($user)
            ->get(route('stock.customers.index', ['search' => 'alpha']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/customers')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Épicerie Alpha'));
    }

    public function test_customers_page_shows_document_counts(): void
    {
        $user = $this->userWithPermissions(['customers.view']);
        $customer = Customer::create(['name' => 'Client A']);

        Quote::create([
            'reference' => 'D-20260814-0002',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'customer_name' => 'Client A',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('stock.customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/customers')
                ->where('customers.data.0.quotes_count', 1)
                ->where('customers.data.0.invoices_count', 0));
    }
}
