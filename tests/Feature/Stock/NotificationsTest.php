<?php

namespace Tests\Feature\Stock;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
    }

    private function notification(User $user, array $attributes = []): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_STOCK_LOW,
            'title' => 'Stock bas : Produit test',
            'message' => 'Le stock est bas.',
            'link' => '/stock/products?low_stock=1',
            'data' => ['product_id' => 1],
            ...$attributes,
        ]);
    }

    // ─── Guest / permission checks ────────────────────────────────

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('stock.notifications.index'))->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_access_unread_count(): void
    {
        $this->getJson(route('stock.notifications.unread-count'))
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_latest(): void
    {
        $this->getJson(route('stock.notifications.latest'))
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_mark_read(): void
    {
        $user = User::factory()->create();
        $notif = $this->notification($user);

        $this->postJson(route('stock.notifications.mark-read', $notif))
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_mark_all_read(): void
    {
        $this->postJson(route('stock.notifications.mark-all-read'))
            ->assertUnauthorized();
    }

    // ─── Index (page) ─────────────────────────────────────────────

    public function test_index_returns_inertia_page_with_notifications(): void
    {
        $user = $this->superadminUser();
        $this->notification($user, ['type' => Notification::TYPE_STOCK_LOW, 'is_read' => false]);
        $this->notification($user, ['type' => Notification::TYPE_STOCK_OUT, 'is_read' => true]);

        $this->actingAs($user)
            ->get(route('stock.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/notifications')
                ->has('notifications.data', 2)
                ->where('unread_count', 1)
            );
    }

    public function test_index_only_shows_current_user_notifications(): void
    {
        $user = $this->superadminUser();
        $other = User::factory()->create();

        $this->notification($user);
        $this->notification($other);

        $this->actingAs($user)
            ->get(route('stock.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 1)
            );
    }

    public function test_index_returns_notifications_ordered_newest_first(): void
    {
        $user = $this->superadminUser();

        DB::table('notifications')->insert([
            'user_id' => $user->id,
            'type' => Notification::TYPE_STOCK_LOW,
            'title' => 'Ancienne',
            'data' => json_encode(['product_id' => 1]),
            'is_read' => false,
            'created_at' => now()->subDays(2)->toDateTimeString(),
            'updated_at' => now()->subDays(2)->toDateTimeString(),
        ]);

        DB::table('notifications')->insert([
            'user_id' => $user->id,
            'type' => Notification::TYPE_STOCK_LOW,
            'title' => 'Récente',
            'data' => json_encode(['product_id' => 2]),
            'is_read' => false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->actingAs($user)
            ->get(route('stock.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.data.0.title', 'Récente')
                ->where('notifications.data.1.title', 'Ancienne')
            );
    }

    public function test_index_paginates_beyond_20_notifications(): void
    {
        $user = $this->superadminUser();

        // Create 25 notifications.
        for ($i = 0; $i < 25; $i++) {
            $this->notification($user, [
                'title' => "Notif {$i}",
                'created_at' => now()->subSeconds($i),
            ]);
        }

        $this->actingAs($user)
            ->get(route('stock.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 20)
                ->where('notifications.total', 25)
                ->where('notifications.last_page', 2)
            );
    }

    // ─── Unread count (JSON) ──────────────────────────────────────

    public function test_unread_count_returns_correct_number(): void
    {
        $user = $this->superadminUser();

        $this->notification($user, ['is_read' => false]);
        $this->notification($user, ['is_read' => false]);
        $this->notification($user, ['is_read' => true]);

        $this->actingAs($user)
            ->getJson(route('stock.notifications.unread-count'))
            ->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_unread_count_returns_zero_when_all_read(): void
    {
        $user = $this->superadminUser();

        $this->notification($user, ['is_read' => true]);
        $this->notification($user, ['is_read' => true]);

        $this->actingAs($user)
            ->getJson(route('stock.notifications.unread-count'))
            ->assertOk()
            ->assertJson(['count' => 0]);
    }

    public function test_unread_count_only_counts_current_user(): void
    {
        $user = $this->superadminUser();
        $other = User::factory()->create();

        $this->notification($user, ['is_read' => false]);
        $this->notification($other, ['is_read' => false]);

        $this->actingAs($user)
            ->getJson(route('stock.notifications.unread-count'))
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    // ─── Latest (JSON) ────────────────────────────────────────────

    public function test_latest_returns_most_recent_notifications(): void
    {
        $user = $this->superadminUser();

        DB::table('notifications')->insert([
            'user_id' => $user->id,
            'type' => Notification::TYPE_STOCK_LOW,
            'title' => 'Première',
            'message' => 'Test',
            'data' => json_encode(['product_id' => 1]),
            'is_read' => true,
            'created_at' => now()->subDays(2)->toDateTimeString(),
            'updated_at' => now()->subDays(2)->toDateTimeString(),
        ]);

        DB::table('notifications')->insert([
            'user_id' => $user->id,
            'type' => Notification::TYPE_STOCK_LOW,
            'title' => 'Deuxième',
            'message' => 'Test',
            'data' => json_encode(['product_id' => 2]),
            'is_read' => false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->actingAs($user)
            ->getJson(route('stock.notifications.latest'))
            ->assertOk()
            ->assertJsonPath('notifications.0.title', 'Deuxième')
            ->assertJsonPath('notifications.1.title', 'Première')
            ->assertJsonPath('unread_count', 1);
    }

    public function test_latest_limits_to_10_notifications(): void
    {
        $user = $this->superadminUser();

        for ($i = 0; $i < 15; $i++) {
            $this->notification($user, [
                'title' => "Notif {$i}",
                'created_at' => now()->subSeconds($i),
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson(route('stock.notifications.latest'))
            ->assertOk();

        $this->assertCount(10, $response->json('notifications'));
    }

    public function test_latest_only_shows_current_user_notifications(): void
    {
        $user = $this->superadminUser();
        $other = User::factory()->create();

        $this->notification($user, ['title' => 'Mienne']);
        $this->notification($other, ['title' => 'Autrui']);

        $this->actingAs($user)
            ->getJson(route('stock.notifications.latest'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', 'Mienne');
    }

    public function test_latest_includes_expected_fields(): void
    {
        $user = $this->superadminUser();

        $this->notification($user, [
            'type' => Notification::TYPE_STOCK_OUT,
            'title' => 'Rupture de stock',
            'message' => 'Le produit est en rupture.',
            'link' => '/stock/products',
            'is_read' => false,
        ]);

        $this->actingAs($user)
            ->getJson(route('stock.notifications.latest'))
            ->assertOk()
            ->assertJsonPath('notifications.0', fn (array $n) => $n['type'] === 'stock_out'
                && $n['title'] === 'Rupture de stock'
                && $n['message'] === 'Le produit est en rupture.'
                && $n['link'] === '/stock/products'
                && $n['is_read'] === false
                && isset($n['created_at'])
            );
    }

    // ─── Mark read (single) ───────────────────────────────────────

    public function test_mark_read_sets_notification_as_read(): void
    {
        $user = $this->superadminUser();
        $notif = $this->notification($user, ['is_read' => false]);

        $this->actingAs($user)
            ->postJson(route('stock.notifications.mark-read', $notif))
            ->assertOk()
            ->assertJson(['success' => true]);

        $notif->refresh();
        $this->assertTrue($notif->is_read);
        $this->assertNotNull($notif->read_at);
    }

    public function test_mark_read_on_already_read_is_idempotent(): void
    {
        $user = $this->superadminUser();
        $notif = $this->notification($user, [
            'is_read' => true,
            'read_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->postJson(route('stock.notifications.mark-read', $notif))
            ->assertOk();

        $notif->refresh();
        $this->assertTrue($notif->is_read);
    }

    public function test_mark_read_decrements_unread_count(): void
    {
        $user = $this->superadminUser();
        $notif = $this->notification($user, ['is_read' => false]);

        $this->actingAs($user)->postJson(route('stock.notifications.mark-read', $notif));

        $this->actingAs($user)
            ->getJson(route('stock.notifications.unread-count'))
            ->assertJson(['count' => 0]);
    }

    public function test_mark_read_does_not_affect_other_users_notifications(): void
    {
        $user = $this->superadminUser();
        $other = User::factory()->create();

        $myNotif = $this->notification($user, ['is_read' => false]);
        $otherNotif = $this->notification($other, ['is_read' => false]);

        // Mark my notification as read.
        $this->actingAs($user)
            ->postJson(route('stock.notifications.mark-read', $myNotif))
            ->assertOk();

        $myNotif->refresh();
        $otherNotif->refresh();

        $this->assertTrue($myNotif->is_read);
        $this->assertFalse($otherNotif->is_read);
    }

    // ─── Mark all read ────────────────────────────────────────────

    public function test_mark_all_read_sets_all_current_user_notifications_as_read(): void
    {
        $user = $this->superadminUser();

        $n1 = $this->notification($user, ['is_read' => false]);
        $n2 = $this->notification($user, ['is_read' => false]);
        $n3 = $this->notification($user, ['is_read' => true]);

        $this->actingAs($user)
            ->postJson(route('stock.notifications.mark-all-read'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $n1->refresh();
        $n2->refresh();
        $n3->refresh();

        $this->assertTrue($n1->is_read);
        $this->assertTrue($n2->is_read);
        // Already read stays read.
        $this->assertTrue($n3->is_read);
    }

    public function test_mark_all_read_does_not_affect_other_users(): void
    {
        $user = $this->superadminUser();
        $other = User::factory()->create();

        $myNotif = $this->notification($user, ['is_read' => false]);
        $otherNotif = $this->notification($other, ['is_read' => false]);

        $this->actingAs($user)
            ->postJson(route('stock.notifications.mark-all-read'))
            ->assertOk();

        $myNotif->refresh();
        $otherNotif->refresh();

        $this->assertTrue($myNotif->is_read);
        $this->assertFalse($otherNotif->is_read);
    }

    public function test_mark_all_read_sets_unread_count_to_zero(): void
    {
        $user = $this->superadminUser();

        $this->notification($user, ['is_read' => false]);
        $this->notification($user, ['is_read' => false]);

        $this->actingAs($user)->postJson(route('stock.notifications.mark-all-read'));

        $this->actingAs($user)
            ->getJson(route('stock.notifications.unread-count'))
            ->assertJson(['count' => 0]);
    }

    public function test_mark_all_read_is_idempotent(): void
    {
        $user = $this->superadminUser();
        $this->notification($user, ['is_read' => false]);

        $this->actingAs($user)->postJson(route('stock.notifications.mark-all-read'))->assertOk();
        $this->actingAs($user)->postJson(route('stock.notifications.mark-all-read'))->assertOk();

        $this->actingAs($user)
            ->getJson(route('stock.notifications.unread-count'))
            ->assertJson(['count' => 0]);
    }

    // ─── Integration: mark all read + index reflects it ───────────

    public function test_index_reflects_unread_count_after_mark_all_read(): void
    {
        $user = $this->superadminUser();

        $this->notification($user, ['is_read' => false]);
        $this->notification($user, ['is_read' => false]);

        // Mark all as read.
        $this->actingAs($user)->postJson(route('stock.notifications.mark-all-read'));

        // Index should now report 0 unread.
        $this->actingAs($user)
            ->get(route('stock.notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('unread_count', 0)
            );
    }

    public function test_latest_reflects_read_status_after_mark_read(): void
    {
        $user = $this->superadminUser();
        $notif = $this->notification($user, ['is_read' => false]);

        $this->actingAs($user)->postJson(route('stock.notifications.mark-read', $notif));

        $this->actingAs($user)
            ->getJson(route('stock.notifications.latest'))
            ->assertOk()
            ->assertJsonPath('notifications.0.is_read', true)
            ->assertJsonPath('unread_count', 0);
    }
}
