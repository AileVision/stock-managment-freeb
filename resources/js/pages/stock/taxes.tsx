import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Boxes,
    Download,
    FileText,
    Landmark,
    Pencil,
    Percent,
    Plus,
    ReceiptText,
    Star,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { formatCurrency, formatDateOnly } from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type { Auth, TaxRate, VatSummary } from '@/types';

type Props = {
    tax_rates: TaxRate[];
    vat: VatSummary;
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

function VatKpi({
    label,
    value,
    hint,
    icon: Icon,
    iconClass,
    valueClass,
}: {
    label: string;
    value: string;
    hint: string;
    icon: typeof Landmark;
    iconClass: string;
    valueClass?: string;
}) {
    return (
        <Card className="gap-3 py-5">
            <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {label}
                </CardTitle>
                <span
                    className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${iconClass}`}
                >
                    <Icon className="size-5" />
                </span>
            </CardHeader>
            <CardContent className="px-5">
                <div
                    className={`text-2xl font-semibold tracking-tight ${valueClass ?? ''}`}
                >
                    {value}
                </div>
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            </CardContent>
        </Card>
    );
}

export default function Taxes({ tax_rates, vat }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canManage = permissions.includes('taxes.manage');

    const [from, setFrom] = useState(vat.filters.from ?? '');
    const [to, setTo] = useState(vat.filters.to ?? '');
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        const timer = setTimeout(() => {
            const query: Record<string, string> = {};

            if (from) {
                query.from = from;
            }

            if (to) {
                query.to = to;
            }

            router.get(
                stock.taxes.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['vat'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [from, to]);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRate, setEditingRate] = useState<TaxRate | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<TaxRate | null>(null);

    const exportQuery: Record<string, string> = {};

    if (from) {
        exportQuery.from = from;
    }

    if (to) {
        exportQuery.to = to;
    }

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

                {/* Période + TVA nette à reverser */}
                <div className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1.5">
                        <Label className="text-xs">Du</Label>
                        <Input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className="h-9 w-40"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <Label className="text-xs">Au</Label>
                        <Input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            className="h-9 w-40"
                        />
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-9"
                        onClick={() => {
                            setFrom('');
                            setTo('');
                        }}
                    >
                        Réinitialiser
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={
                                stock.taxes.vatDeclaration.pdf({
                                    query: exportQuery,
                                }).url
                            }
                            target="_blank"
                            rel="noreferrer"
                        >
                            <FileText />
                            PDF
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={
                                stock.taxes.vatDeclaration.csv({
                                    query: exportQuery,
                                }).url
                            }
                            download
                        >
                            <Download />
                            CSV
                        </a>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <VatKpi
                        label="TVA collectée"
                        value={formatCurrency(vat.collected)}
                        hint={`Ventes validées du ${formatDateOnly(
                            vat.filters.from,
                        )} au ${formatDateOnly(vat.filters.to)}`}
                        icon={Landmark}
                        iconClass="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                    />
                    <VatKpi
                        label="TVA déductible"
                        value={formatCurrency(vat.deductible)}
                        hint="Réceptions fournisseur sur la période"
                        icon={Boxes}
                        iconClass="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    />
                    <VatKpi
                        label="TVA nette à reverser"
                        value={formatCurrency(vat.net)}
                        hint={
                            vat.net >= 0
                                ? 'À déclarer au fisc'
                                : 'Crédit de TVA reportable'
                        }
                        icon={ReceiptText}
                        iconClass="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        valueClass={
                            vat.net >= 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-red-600 dark:text-red-400'
                        }
                    />
                </div>

                {/* Répartition par taux */}
                <Card>
                    <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                        <div className="space-y-1">
                            <CardTitle className="text-base">
                                TVA nette par taux
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Collectée sur les ventes, déductible sur les
                                réceptions fournisseur.
                            </p>
                        </div>
                        <Percent className="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent className="px-5">
                        {vat.by_rate.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Aucune vente ni réception sur la période.
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-xl border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Taux</TableHead>
                                            <TableHead className="text-right">
                                                TVA collectée
                                            </TableHead>
                                            <TableHead className="text-right">
                                                TVA déductible
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Nette à reverser
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {vat.by_rate.map((row) => (
                                            <TableRow key={row.rate}>
                                                <TableCell className="font-medium">
                                                    {row.name}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatCurrency(
                                                        row.collected,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatCurrency(
                                                        row.deductible,
                                                    )}
                                                </TableCell>
                                                <TableCell
                                                    className={cn(
                                                        'text-right font-medium tabular-nums',
                                                        row.net >= 0
                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                            : 'text-red-600 dark:text-red-400',
                                                    )}
                                                >
                                                    {formatCurrency(row.net)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        <TableRow>
                                            <TableCell className="font-semibold">
                                                Total
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatCurrency(
                                                    vat.collected,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatCurrency(
                                                    vat.deductible,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatCurrency(vat.net)}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

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
