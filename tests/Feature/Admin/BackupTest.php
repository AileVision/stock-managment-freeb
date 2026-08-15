<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use InteractsWithPermissions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();

        // La base de test est en mémoire : le contrôleur de sauvegarde exige
        // un fichier réel. On pointe la connexion vers un fichier temporaire.
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'backup-test-');

        config(['database.connections.sqlite.database' => $this->databaseFile]);
    }

    protected function tearDown(): void
    {
        if (isset($this->databaseFile) && is_file($this->databaseFile)) {
            @unlink($this->databaseFile);
        }

        parent::tearDown();
    }

    public function test_guests_are_redirected_to_login_for_backups(): void
    {
        $this->get(route('backups.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_view_backups(): void
    {
        $this->actingAs($this->userWithPermissions([]))
            ->get(route('backups.index'))
            ->assertForbidden();
    }

    public function test_user_can_create_a_backup(): void
    {
        $user = $this->userWithPermissions(['backups.manage']);
        Storage::fake('local');

        $this->from(route('backups.index'))->actingAs($user)
            ->post(route('backups.store'))
            ->assertRedirect(route('backups.index'));

        $files = Storage::disk('local')->files('backups');

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.sqlite', $files[0]);
    }

    public function test_backups_index_lists_existing_backups(): void
    {
        $user = $this->userWithPermissions(['backups.manage']);
        Storage::fake('local');

        Storage::disk('local')->put('backups/backup-2026-08-14-100000.sqlite', 'data');

        $this->actingAs($user)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/backups')
                ->has('backups', 1)
                ->where('backups.0.name', 'backup-2026-08-14-100000.sqlite'));
    }

    public function test_user_can_download_a_backup(): void
    {
        $user = $this->userWithPermissions(['backups.manage']);
        Storage::fake('local');

        Storage::disk('local')->put('backups/backup-2026-08-14-100000.sqlite', 'data');

        $this->actingAs($user)
            ->get(route('backups.download', 'backup-2026-08-14-100000.sqlite'))
            ->assertOk()
            ->assertDownload('backup-2026-08-14-100000.sqlite');
    }

    public function test_download_rejects_unknown_backups(): void
    {
        $user = $this->userWithPermissions(['backups.manage']);
        Storage::fake('local');

        $this->from(route('backups.index'))->actingAs($user)
            ->get(route('backups.download', 'missing.sqlite'))
            ->assertRedirect(route('backups.index'));
    }

    public function test_download_rejects_non_sqlite_names(): void
    {
        $user = $this->userWithPermissions(['backups.manage']);
        Storage::fake('local');

        Storage::disk('local')->put('backups/backup-2026-08-14-100000.txt', 'data');

        $this->from(route('backups.index'))->actingAs($user)
            ->get(route('backups.download', 'backup-2026-08-14-100000.txt'))
            ->assertRedirect(route('backups.index'));
    }

    public function test_user_can_delete_a_backup(): void
    {
        $user = $this->userWithPermissions(['backups.manage']);
        Storage::fake('local');

        Storage::disk('local')->put('backups/backup-2026-08-14-100000.sqlite', 'data');

        $this->from(route('backups.index'))->actingAs($user)
            ->delete(route('backups.destroy', 'backup-2026-08-14-100000.sqlite'))
            ->assertRedirect(route('backups.index'));

        $this->assertEmpty(Storage::disk('local')->files('backups'));
    }
}
