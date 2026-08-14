import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Wallet } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency, formatDate } from '@/lib/format';
import stock from '@/routes/stock';
import type { Auth, CashSession, Paginated } from '@/types';

type Props = {
    sessions: Paginated<CashSession>;
};

export default function CashSessions({ sessions }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canOpen = permissions.includes('sessions.open');
    const canClose = permissions.includes('sessions.close');

    const [openDialog, setOpenDialog] = useState(false);
    const [closeTarget, setCloseTarget] = useState<CashSession | null>(null);

    const openForm = useForm<{ opening_cash: string; notes: string }>({
        opening_cash: '0',
        notes: '',
    });

    const closeForm = useForm<{ counted_cash: string; notes: string }>({
        counted_cash: '',
        notes: '',
    });

    const submitOpen = (event: FormEvent) => {
        event.preventDefault();

        openForm.post(stock.sessions.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpenDialog(false);
                openForm.reset();
                router.reload();
            },
        });
    };

    const submitClose = (event: FormEvent) => {
        event.preventDefault();

        if (!closeTarget) {
            return;
        }

        closeForm.post(stock.sessions.close(closeTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setCloseTarget(null);
                closeForm.reset();
                router.reload();
            },
        });
    };

    return (
        <>
            <Head title="Sessions de caisse" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Sessions de caisse
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Suivez les ouvertures et clôtures de caisse et
                            vérifiez les écarts de fonds.
                        </p>
                    </div>
                    {canOpen && (
                        <Button onClick={() => setOpenDialog(true)} size="sm">
                            <Plus />
                            Ouvrir une session
                        </Button>
                    )}
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Ouverte le</TableHead>
                                <TableHead>Clôturée le</TableHead>
                                <TableHead>Caissier</TableHead>
                                <TableHead className="text-right">
                                    Fonds d’ouverture
                                </TableHead>
                                <TableHead className="text-right">
                                    Ventes
                                </TableHead>
                                <TableHead className="text-right">
                                    Encaissé (espèces)
                                </TableHead>
                                <TableHead className="text-right">
                                    Caisse attendue
                                </TableHead>
                                <TableHead className="text-right">
                                    Compté
                                </TableHead>
                                <TableHead className="text-right">
                                    Écart
                                </TableHead>
                                <TableHead>Statut</TableHead>
                                {canClose && (
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sessions.data.map((session) => (
                                <TableRow key={session.id}>
                                    <TableCell className="font-medium">
                                        {formatDate(session.opened_at)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {session.closed_at
                                            ? formatDate(session.closed_at)
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {session.user?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatCurrency(session.opening_cash)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {session.sales_count}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatCurrency(session.cash_total)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {session.expected_cash !== null
                                            ? formatCurrency(
                                                  session.expected_cash,
                                              )
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {session.counted_cash !== null
                                            ? formatCurrency(
                                                  session.counted_cash,
                                              )
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {session.difference !== null ? (
                                            <span
                                                className={
                                                    session.difference === 0
                                                        ? 'font-medium text-emerald-600 tabular-nums dark:text-emerald-400'
                                                        : 'font-medium text-red-600 tabular-nums dark:text-red-400'
                                                }
                                            >
                                                {session.difference > 0
                                                    ? '+'
                                                    : ''}
                                                {formatCurrency(
                                                    session.difference,
                                                )}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {session.is_open ? (
                                            <Badge
                                                variant="outline"
                                                className="border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                            >
                                                Ouverte
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="border-neutral-500/20 bg-neutral-500/10 text-neutral-600 dark:text-neutral-400"
                                            >
                                                Clôturée
                                            </Badge>
                                        )}
                                    </TableCell>
                                    {canClose && (
                                        <TableCell className="text-right">
                                            {session.is_open && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setCloseTarget(session)
                                                    }
                                                >
                                                    Clôturer
                                                </Button>
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {sessions.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={11}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <Wallet className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucune session
                                            </p>
                                            <p className="text-xs">
                                                Les ouvertures de caisse
                                                apparaîtront ici.
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* Open session dialog */}
            <Dialog open={openDialog} onOpenChange={setOpenDialog}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Ouvrir une session de caisse</DialogTitle>
                    </DialogHeader>
                    <form
                        id="open-session-form"
                        onSubmit={submitOpen}
                        className="grid gap-4"
                    >
                        <div className="space-y-2">
                            <Label htmlFor="opening_cash">
                                Fonds de caisse
                            </Label>
                            <Input
                                id="opening_cash"
                                type="number"
                                min="0"
                                step="0.01"
                                value={openForm.data.opening_cash}
                                onChange={(e) =>
                                    openForm.setData(
                                        'opening_cash',
                                        e.target.value,
                                    )
                                }
                                placeholder="0"
                            />
                            <InputError
                                message={openForm.errors.opening_cash}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="session-notes">Remarque</Label>
                            <Input
                                id="session-notes"
                                value={openForm.data.notes}
                                onChange={(e) =>
                                    openForm.setData('notes', e.target.value)
                                }
                                placeholder="Optionnel"
                            />
                            <InputError message={openForm.errors.notes} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpenDialog(false)}
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={openForm.processing}
                            >
                                Ouvrir la session
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Close session dialog */}
            <Dialog
                open={closeTarget !== null}
                onOpenChange={(open) => !open && setCloseTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Clôturer la session</DialogTitle>
                    </DialogHeader>
                    <form
                        id="close-session-form"
                        onSubmit={submitClose}
                        className="grid gap-4"
                    >
                        {closeTarget && (
                            <div className="space-y-1 rounded-xl border p-4 text-sm">
                                <p className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Fonds d’ouverture
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(
                                            closeTarget.opening_cash,
                                        )}
                                    </span>
                                </p>
                                <p className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Encaissé (espèces)
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(closeTarget.cash_total)}
                                    </span>
                                </p>
                                <p className="flex justify-between border-t pt-2">
                                    <span className="font-medium">
                                        Caisse attendue
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {formatCurrency(
                                            closeTarget.opening_cash +
                                                closeTarget.cash_total,
                                        )}
                                    </span>
                                </p>
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="counted_cash">
                                Montant compté dans le tiroir
                            </Label>
                            <Input
                                id="counted_cash"
                                type="number"
                                min="0"
                                step="0.01"
                                value={closeForm.data.counted_cash}
                                onChange={(e) =>
                                    closeForm.setData(
                                        'counted_cash',
                                        e.target.value,
                                    )
                                }
                                placeholder="0"
                                required
                            />
                            <InputError
                                message={closeForm.errors.counted_cash}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="close-notes">Remarque</Label>
                            <Input
                                id="close-notes"
                                value={closeForm.data.notes}
                                onChange={(e) =>
                                    closeForm.setData('notes', e.target.value)
                                }
                                placeholder="Optionnel"
                            />
                            <InputError message={closeForm.errors.notes} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCloseTarget(null)}
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={closeForm.processing}
                            >
                                Clôturer la session
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

CashSessions.layout = {
    breadcrumbs: [
        {
            title: 'Sessions de caisse',
            href: stock.sessions.index().url,
        },
    ],
};
