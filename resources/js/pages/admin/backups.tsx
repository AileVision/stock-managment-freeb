import { Head, router, usePage } from '@inertiajs/react';
import {
    Database,
    Download,
    HardDriveDownload,
    RefreshCcw,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatNumber } from '@/lib/format';
import backups from '@/routes/backups';
import type { Auth, Backup } from '@/types';

type Props = {
    backups: Backup[];
    driver: string;
    database_path: string | null;
};

export default function BackupsPage({
    backups: backupList,
    driver,
    database_path,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canManage = permissions.includes('backups.manage');

    const [restoreTarget, setRestoreTarget] = useState<Backup | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Backup | null>(null);
    const [creating, setCreating] = useState(false);

    const createBackup = () => {
        if (!canManage || creating) {
            return;
        }

        setCreating(true);
        router.post(
            backups.store().url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setCreating(false),
            },
        );
    };

    const confirmRestore = () => {
        if (!restoreTarget) {
            return;
        }

        router.post(
            backups.restore(restoreTarget.name).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setRestoreTarget(null),
            },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(backups.destroy(deleteTarget.name).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <>
            <Head title="Sauvegardes" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Sauvegardes
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Créez et restaurez des copies de la base de données.
                        </p>
                    </div>
                    {canManage && (
                        <Button
                            onClick={createBackup}
                            size="sm"
                            disabled={creating || driver !== 'sqlite'}
                        >
                            <HardDriveDownload />
                            {creating
                                ? 'Sauvegarde en cours…'
                                : 'Créer une sauvegarde'}
                        </Button>
                    )}
                </div>

                {driver !== 'sqlite' ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-14 text-center text-muted-foreground">
                        <ShieldAlert className="size-6 opacity-40" />
                        <p className="text-sm font-medium">
                            Sauvegardes indisponibles
                        </p>
                        <p className="text-xs">
                            Cette fonctionnalité nécessite une base SQLite
                            (driver actuel : {driver}).
                        </p>
                    </div>
                ) : (
                    <>
                        <p className="text-xs text-muted-foreground">
                            Base actuelle :{' '}
                            <code className="rounded bg-muted px-1.5 py-0.5">
                                {database_path ?? '—'}
                            </code>
                        </p>

                        <div className="overflow-hidden rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Sauvegarde</TableHead>
                                        <TableHead className="text-right">
                                            Taille
                                        </TableHead>
                                        <TableHead>Créée le</TableHead>
                                        {canManage && (
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {backupList.map((backup) => (
                                        <TableRow key={backup.name}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                                        <Database className="size-4" />
                                                    </span>
                                                    <span className="font-mono text-sm font-medium">
                                                        {backup.name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {backup.human_size}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {backup.created_at
                                                    ? formatDate(
                                                          backup.created_at,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                            {canManage && (
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            asChild
                                                            aria-label={`Télécharger ${backup.name}`}
                                                        >
                                                            <a
                                                                href={
                                                                    backups.download(
                                                                        backup.name,
                                                                    ).url
                                                                }
                                                            >
                                                                <Download />
                                                            </a>
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-amber-600 hover:bg-amber-500/10 hover:text-amber-600 dark:text-amber-400"
                                                            onClick={() =>
                                                                setRestoreTarget(
                                                                    backup,
                                                                )
                                                            }
                                                            aria-label={`Restaurer ${backup.name}`}
                                                        >
                                                            <RefreshCcw />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                            onClick={() =>
                                                                setDeleteTarget(
                                                                    backup,
                                                                )
                                                            }
                                                            aria-label={`Supprimer ${backup.name}`}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                    {backupList.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={canManage ? 4 : 3}
                                                className="h-40 text-center text-muted-foreground"
                                            >
                                                <div className="flex flex-col items-center gap-2">
                                                    <Database className="size-6 opacity-40" />
                                                    <p className="text-sm font-medium">
                                                        Aucune sauvegarde
                                                    </p>
                                                    <p className="text-xs">
                                                        Créez votre première
                                                        copie de la base.
                                                    </p>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="flex items-start gap-2 rounded-xl border p-4 text-xs text-muted-foreground">
                            <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                            <p>
                                {formatNumber(backupList.length)} sauvegarde
                                {backupList.length > 1 ? 's' : ''} stockée
                                {backupList.length > 1 ? 's' : ''} sur le disque
                                local. La restauration remplace{' '}
                                <strong>toutes</strong> les données actuelles
                                par la copie choisie — elle est irréversible.
                            </p>
                        </div>
                    </>
                )}
            </div>

            {/* Restore confirmation */}
            <Dialog
                open={restoreTarget !== null}
                onOpenChange={(open) => !open && setRestoreTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Restaurer la base</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment restaurer «{' '}
                            {restoreTarget?.name} » ? Toutes les données
                            actuelles seront remplacées par cette sauvegarde.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setRestoreTarget(null)}
                        >
                            Annuler
                        </Button>
                        <Button variant="destructive" onClick={confirmRestore}>
                            Restaurer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete confirmation */}
            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Supprimer la sauvegarde</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer «{' '}
                            {deleteTarget?.name} » ?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            Annuler
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Supprimer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

BackupsPage.layout = {
    breadcrumbs: [
        {
            title: 'Sauvegardes',
            href: backups.index().url,
        },
    ],
};
