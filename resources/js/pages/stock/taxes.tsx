import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Percent, Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type { Auth, TaxRate } from '@/types';

type Props = {
    tax_rates: TaxRate[];
};

function TaxRateForm({
    taxRate,
    onDone,
}: {
    taxRate: TaxRate | null;
    onDone: () => void;
}) {
    const editing = taxRate !== null;
    const form = useForm({
        name: taxRate?.name ?? '',
        rate: taxRate?.rate !== undefined ? String(taxRate.rate) : '18',
        is_active: taxRate?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing && taxRate) {
            form.patch(stock.taxes.update(taxRate.id).url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(stock.taxes.store().url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form id="tax-form" onSubmit={submit} className="grid gap-4">
            <div className="space-y-2">
                <Label htmlFor="tax-name">Nom du taux</Label>
                <Input
                    id="tax-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Ex. : TVA standard (Togo)"
                    required
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="tax-rate">Taux (%)</Label>
                <div className="relative">
                    <Input
                        id="tax-rate"
                        type="number"
                        min="0"
                        max="100"
                        step="0.5"
                        value={form.data.rate}
                        onChange={(e) => form.setData('rate', e.target.value)}
                        className="pr-8"
                        required
                    />
                    <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-muted-foreground">
                        %
                    </span>
                </div>
                <InputError message={form.errors.rate} />
            </div>

            <div className="flex items-center gap-2">
                <input
                    id="tax-active"
                    type="checkbox"
                    checked={form.data.is_active}
                    onChange={(e) =>
                        form.setData('is_active', e.target.checked)
                    }
                    className="size-4 accent-primary"
                />
                <Label htmlFor="tax-active" className="font-normal">
                    Taux actif (proposé à la caisse)
                </Label>
            </div>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {editing ? 'Enregistrer' : 'Créer le taux'}
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function Taxes({ tax_rates }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canManage = permissions.includes('taxes.manage');

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRate, setEditingRate] = useState<TaxRate | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<TaxRate | null>(null);

    const openCreate = () => {
        setEditingRate(null);
        setDialogOpen(true);
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(stock.taxes.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const setDefault = (taxRate: TaxRate) => {
        router.patch(
            stock.taxes.setDefault(taxRate.id).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title="Taux de TVA" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Taux de TVA
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Gérez les taux de TVA proposés à la caisse, sur les
                            devis et les factures (TVA incluse).
                        </p>
                    </div>
                    {canManage && (
                        <Button onClick={openCreate} size="sm">
                            <Plus />
                            Nouveau taux
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Taux</TableHead>
                                <TableHead className="text-right">
                                    Pourcentage
                                </TableHead>
                                <TableHead>Statut</TableHead>
                                {canManage && (
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tax_rates.map((taxRate) => (
                                <TableRow key={taxRate.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-2 font-medium">
                                            <Percent className="size-4 text-muted-foreground" />
                                            {taxRate.name}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Badge
                                            variant="outline"
                                            className="tabular-nums"
                                        >
                                            {taxRate.rate} %
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            {taxRate.is_default && (
                                                <Badge
                                                    className="gap-1 border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                                    variant="outline"
                                                >
                                                    <Star className="size-3" />
                                                    Défaut
                                                </Badge>
                                            )}
                                            <Badge
                                                variant={
                                                    taxRate.is_active
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                                className={cn(
                                                    !taxRate.is_active &&
                                                        'text-muted-foreground',
                                                )}
                                            >
                                                {taxRate.is_active
                                                    ? 'Actif'
                                                    : 'Inactif'}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    {canManage && (
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {!taxRate.is_default && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            setDefault(taxRate)
                                                        }
                                                        aria-label={`Définir ${taxRate.name} comme taux par défaut`}
                                                        title="Définir par défaut"
                                                    >
                                                        <Star />
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => {
                                                        setEditingRate(taxRate);
                                                        setDialogOpen(true);
                                                    }}
                                                    aria-label={`Modifier ${taxRate.name}`}
                                                >
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                    disabled={
                                                        taxRate.is_default
                                                    }
                                                    onClick={() =>
                                                        setDeleteTarget(taxRate)
                                                    }
                                                    aria-label={`Supprimer ${taxRate.name}`}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {tax_rates.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <Percent className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun taux de TVA
                                            </p>
                                            <p className="text-xs">
                                                Créez le taux de TVA applicable
                                                à votre activité (ex. : 18 % au
                                                Togo).
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingRate
                                ? 'Modifier le taux de TVA'
                                : 'Nouveau taux de TVA'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingRate
                                ? `Mise à jour de « ${editingRate.name} ».`
                                : 'La TVA est incluse dans les prix affichés à la caisse.'}
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <TaxRateForm
                            key={editingRate?.id ?? 'new'}
                            taxRate={editingRate}
                            onDone={() => setDialogOpen(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Supprimer le taux de TVA</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer «{' '}
                            {deleteTarget?.name} ({deleteTarget?.rate} %) » ?
                            Les ventes déjà enregistrées avec ce taux sont
                            conservées.
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

Taxes.layout = {
    breadcrumbs: [
        {
            title: 'Taux de TVA',
            href: stock.taxes.index().url,
        },
    ],
};
