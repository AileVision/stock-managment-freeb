<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /**
     * The directory where the backups are stored.
     */
    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * Display the list of backups.
     */
    public function index(): Response
    {
        $backups = collect($this->disk()->files('backups'))
            ->filter(fn (string $path): bool => str_ends_with($path, '.sqlite'))
            ->map(function (string $path): array {
                $time = $this->disk()->lastModified($path);

                return [
                    'name' => basename($path),
                    'size' => $this->disk()->size($path),
                    'human_size' => $this->humanSize($this->disk()->size($path)),
                    'created_at' => now()->createFromTimestamp($time),
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('admin/backups', [
            'backups' => $backups,
            'driver' => config('database.default'),
            'database_path' => $this->databasePath(),
        ]);
    }

    /**
     * Create a backup of the current database.
     */
    public function store(): RedirectResponse
    {
        $databasePath = $this->databasePath();

        if ($databasePath === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'La sauvegarde n’est disponible qu’avec SQLite.']);

            return back();
        }

        $this->disk()->makeDirectory('backups');

        $name = 'backup-'.now()->format('Y-m-d-His').'.sqlite';

        $contents = file_get_contents($databasePath);

        if ($contents === false || ! $this->disk()->put('backups/'.$name, $contents)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'La sauvegarde a échoué.']);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sauvegarde {$name} créée avec succès."]);

        return back();
    }

    /**
     * Download a backup file.
     */
    public function download(Request $request, string $backup): BinaryFileResponse|RedirectResponse
    {
        $path = $this->resolvePath($backup);

        if ($path === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Sauvegarde introuvable.']);

            return back();
        }

        return response()->download($this->disk()->path($path), $backup);
    }

    /**
     * Restore the database from a backup (destructive).
     */
    public function restore(Request $request, string $backup): RedirectResponse
    {
        $path = $this->resolvePath($backup);
        $databasePath = $this->databasePath();

        if ($path === null || $databasePath === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Sauvegarde introuvable ou base non SQLite.']);

            return back();
        }

        // Remplace la base courante par la copie de sauvegarde.
        copy(storage_path('app/'.$path), $databasePath);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Base restaurée depuis {$backup}."]);

        return back();
    }

    /**
     * Delete a backup file.
     */
    public function destroy(Request $request, string $backup): RedirectResponse
    {
        $path = $this->resolvePath($backup);

        if ($path === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Sauvegarde introuvable.']);

            return back();
        }

        $this->disk()->delete($path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sauvegarde supprimée.']);

        return back();
    }

    /**
     * Resolve a backup name to a safe storage path.
     */
    private function resolvePath(string $backup): ?string
    {
        if (! str_ends_with($backup, '.sqlite')) {
            return null;
        }

        $path = 'backups/'.basename($backup);

        return $this->disk()->exists($path) ? $path : null;
    }

    /**
     * The current SQLite database file path, or null when unsupported.
     */
    private function databasePath(): ?string
    {
        if (config('database.default') !== 'sqlite') {
            return null;
        }

        $path = config('database.connections.sqlite.database');

        if (! is_string($path) || $path === ':memory:') {
            return null;
        }

        // Chemin absolu (Windows, base de test temporaire…) : utilisé tel quel.
        // Une lettre de lecteur suivie de « : » (ex. C:\…) ou un chemin POSIX.
        if (preg_match('#^[A-Za-z]:#', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Format a byte count in a human-readable way.
     */
    private function humanSize(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go'];

        $value = (float) $bytes;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 1, ',', ' ').' '.$units[$index];
    }
}
