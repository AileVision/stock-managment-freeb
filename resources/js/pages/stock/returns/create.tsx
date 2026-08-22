import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    HandCoins,
    PackageSearch,
    RotateCcw,
    Search,
    ShoppingCart,
    Undo2,
    Warehouse as WarehouseIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    formatCurrency,
    formatDate,
    formatNumber,
    unitLabel,
} from '@/lib/format';
import stock from '@/routes/stock';
import type { ReturnFormSale, Warehouse } from '@/types';

type Props = {
    sales: ReturnFormSale[];
    warehouses: Pick<Warehouse, 'id' | 'name' | 'code' | 'is_default'>[];
};

function SalePicker({
    sales,
    value,
    onChange,
}: {
    sales: ReturnFormSale[];
    value: ReturnFormSale | null;
    onChange: (sale: ReturnFormSale) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return sales;
        }

        return sales.filter(
            (sale) =>
                sale.reference.toLowerCase().includes(query) ||
                (sale.customer?.name ?? '').toLowerCase().includes(query),
        );
    }, [sales, search]);

    return (
        <>
            <Button
                type="button"
                variant="outline"
                className="w-full justify-start font-normal"
                onClick={() => {
                    setSearch('');
                    setOpen(true);
                }}
            >
                {value ? (
                    <span className="flex min-w-0 flex-1 items-center gap-2 text-left">
                        <ShoppingCart className="size-4 shrink-0 text-muted-foreground" />
                        <span className="truncate">{value.reference}</span>
                        {value.customer && (
                            <span className="truncate text-muted-foreground">
                                · {value.customer.name}
                            </span>
                        )}
                        <span className="ml-auto shrink-0 text-muted-foreground tabular-nums">
                            {formatCurrency(value.total)}
                        </span>
                    </span>
                ) : (
                    <span className="text-muted-foreground">
                        Choisir une vente à retourner…
                    </span>
                )}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Choisir la vente à retourner</DialogTitle>
                        <DialogDescription>
                            Seules les ventes validées avec des articles
                            retournables sont listées.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher par référence ou client…"
                            className="pl-9"
                            autoFocus
                        />
                    </div>

                    <div className="flex max-h-80 flex-col gap-1 overflow-y-auto">
                        {filtered.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Aucune vente retournable trouvée.
                            </p>
                        ) : (
                            filtered.map((sale) => (
                                <button
                                    key={sale.id}
                                    type="button"
                                    onClick={() => {
                                        onChange(sale);
                                        setOpen(false);
                                    }}
                                    className="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-accent/50"
                                >
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <ShoppingCart className="size-4" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-medium">
                                            {sale.reference}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {sale.customer?.name ??
                                                'Client de passage'}{' '}
                                            · {formatDate(sale.created_at)} ·{' '}
                                            {sale.payment_label}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-right">
                                        <span className="block text-sm font-semibold tabular-nums">
                                            {formatCurrency(sale.total)}
                                        </span>
                                        <span className="block text-xs text-muted-foreground tabular-nums">
                                            {formatNumber(
                                                sale.items.reduce(
                                                    (sum, item) =>
                                                        sum + item.returnable,
                                                    0,
                                                ),
                                            )}{' '}
                                            retournable
                                        </span>
                                    </span>
                                </button>
                            ))
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default function CreateReturn({ sales, warehouses }: Props) {
    const form = useForm<{
        sale_id: number | null;
        items: { product_id: number; quantity: number }[];
        disposition: 'refund' | 'avoir';
        refund_method: string;
        warehouse_id: number | null;
        notes: string;
    }>({
        sale_id: null,
        items: [],
        disposition: 'refund',
        refund_method: 'cash',
        warehouse_id:
            warehouses.find((warehouse) => warehouse.is_default)?.id ??
            warehouses[0]?.id ??
            null,
        notes: '',
    });

    const [selectedSale, setSelectedSale] = useState<ReturnFormSale | null>(
        null,
    );
    const [quantities, setQuantities] = useState<Record<number, number>>({});

    const defaultWarehouse =
        warehouses.find((warehouse) => warehouse.is_default) ??
        warehouses[0] ??
        null;

    const selectSale = (sale: ReturnFormSale) => {
        setSelectedSale(sale);
        setQuantities({});
        form.setData('sale_id', sale.id);
    };

    const changeQuantity = (productId: number, value: string) => {
        const item = selectedSale?.items.find(
            (candidate) => candidate.product_id === productId,
        );

        if (!item) {
            return;
        }

        const parsed = Math.max(
            0,
            Math.min(Number(value) || 0, item.returnable),
        );

        setQuantities((current) => ({ ...current, [productId]: parsed }));
    };

    const selectedItems = useMemo(() => {
        if (!selectedSale) {
            return [];
        }

        return selectedSale.items
            .map((item) => ({
                ...item,
                quantity: quantities[item.product_id] ?? 0,
            }))
            .filter((item) => item.quantity > 0);
    }, [selectedSale, quantities]);

    const returnValue = selectedItems.reduce(
        (sum, item) => sum + item.unit_price * item.quantity,
        0,
    );

    // Pour une vente à crédit, le retour réduit d'abord le solde impayé ;
    // le surplus éventuel est remboursé ou converti en avoir.
    const balance = selectedSale?.balance ?? 0;
    const appliedToBalance = Math.min(returnValue, Math.max(balance, 0));
    const refundAmount = Math.max(returnValue - appliedToBalance, 0);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!selectedSale || selectedItems.length === 0) {
            return;
        }

        form.setData({
            items: selectedItems.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
            })),
            warehouse_id: form.data.warehouse_id,
            refund_method: form.data.refund_method,
            disposition: form.data.disposition,
            notes: form.data.notes,
        });

        form.post(stock.returns.store().url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Nouveau retour" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Nouveau retour
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Retournez tout ou partie d’une vente : le stock est
                            réintégré et le client est remboursé ou reçoit un
                            avoir.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <a href={stock.returns.index().url}>
                            <ArrowLeft />
                            Retours
                        </a>
                    </Button>
                </div>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <div className="grid gap-6 lg:grid-cols-5">
                        {/* Left column : sale selection */}
                        <div className="flex flex-col gap-4 lg:col-span-3">
                            <div className="space-y-2">
                                <Label>Vente à retourner</Label>
                                <SalePicker
                                    sales={sales}
                                    value={selectedSale}
                                    onChange={selectSale}
                                />
                                <InputError message={form.errors.sale_id} />
                            </div>

                            {selectedSale && (
                                <div className="overflow-hidden rounded-xl border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Produit</TableHead>
                                                <TableHead className="text-right">
                                                    Prix unitaire
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Vendu
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    À retourner
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Total
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {selectedSale.items.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell>
                                                        <div className="font-medium">
                                                            {item.product.name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {item.product.sku}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatCurrency(
                                                            item.unit_price,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatNumber(
                                                            item.quantity,
                                                        )}{' '}
                                                        {unitLabel(
                                                            item.product.unit ??
                                                                'pcs',
                                                        )}
                                                        {item.returned_quantity >
                                                            0 && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {' '}
                                                                (déjà{' '}
                                                                {
                                                                    item.returned_quantity
                                                                }
                                                                )
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            max={
                                                                item.returnable
                                                            }
                                                            value={
                                                                quantities[
                                                                    item
                                                                        .product_id
                                                                ] ?? 0
                                                            }
                                                            onChange={(e) =>
                                                                changeQuantity(
                                                                    item.product_id,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="ml-auto h-9 w-24 text-right"
                                                        />
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium tabular-nums">
                                                        {formatCurrency(
                                                            item.unit_price *
                                                                (quantities[
                                                                    item
                                                                        .product_id
                                                                ] ?? 0),
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}

                            {selectedSale && (
                                <div className="flex items-center justify-between rounded-xl border p-4">
                                    <span className="text-sm text-muted-foreground">
                                        Valeur du retour
                                    </span>
                                    <span className="text-lg font-semibold tabular-nums">
                                        {formatCurrency(returnValue)}
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Right column : disposition */}
                        <div className="flex flex-col gap-4 lg:col-span-2">
                            <div className="flex flex-col gap-4 rounded-xl border p-4">
                                <div className="space-y-2">
                                    <Label>Sort du retour</Label>
                                    <Select
                                        value={form.data.disposition}
                                        onValueChange={(v) =>
                                            form.setData(
                                                'disposition',
                                                v as 'refund' | 'avoir',
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="refund">
                                                Rembourser (espèces / mobile)
                                            </SelectItem>
                                            <SelectItem value="avoir">
                                                Générer un avoir
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.disposition}
                                    />
                                </div>

                                {form.data.disposition === 'refund' && (
                                    <div className="space-y-2">
                                        <Label>Mode de remboursement</Label>
                                        <Select
                                            value={form.data.refund_method}
                                            onValueChange={(v) =>
                                                form.setData('refund_method', v)
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="cash">
                                                    Espèces
                                                </SelectItem>
                                                <SelectItem value="tmoney">
                                                    T-Money
                                                </SelectItem>
                                                <SelectItem value="flooz">
                                                    Flooz
                                                </SelectItem>
                                                <SelectItem value="transfer">
                                                    Virement
                                                </SelectItem>
                                                <SelectItem value="card">
                                                    Carte bancaire
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={form.errors.refund_method}
                                        />
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label>Entrepôt de réintégration</Label>
                                    <Select
                                        value={
                                            form.data.warehouse_id !== null
                                                ? String(form.data.warehouse_id)
                                                : 'none'
                                        }
                                        onValueChange={(v) =>
                                            form.setData(
                                                'warehouse_id',
                                                v === 'none' ? null : Number(v),
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Tous les entrepôts
                                            </SelectItem>
                                            {warehouses.map((warehouse) => (
                                                <SelectItem
                                                    key={warehouse.id}
                                                    value={String(warehouse.id)}
                                                >
                                                    {warehouse.name} (
                                                    {warehouse.code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="return-notes">
                                        Remarque
                                    </Label>
                                    <Input
                                        id="return-notes"
                                        value={form.data.notes}
                                        onChange={(e) =>
                                            form.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Motif du retour…"
                                    />
                                    <InputError message={form.errors.notes} />
                                </div>
                            </div>

                            {returnValue > 0 && (
                                <div className="flex flex-col gap-2 rounded-xl border bg-muted/30 p-4 text-sm">
                                    {appliedToBalance > 0 && (
                                        <p className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Appliqué au solde impayé
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                −{' '}
                                                {formatCurrency(
                                                    appliedToBalance,
                                                )}
                                            </span>
                                        </p>
                                    )}
                                    <p className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            {form.data.disposition === 'refund'
                                                ? 'À rembourser'
                                                : 'Converti en avoir'}
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {formatCurrency(refundAmount)}
                                        </span>
                                    </p>
                                    {refundAmount <= 0.01 && (
                                        <p className="flex items-center gap-2 rounded-lg border border-amber-500/20 bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                            <HandCoins className="size-4 shrink-0" />
                                            Le retour réduit uniquement le solde
                                            impayé de cette vente à crédit, rien
                                            à rembourser.
                                        </p>
                                    )}
                                </div>
                            )}

                            <Button
                                type="submit"
                                size="lg"
                                disabled={
                                    selectedSale === null ||
                                    selectedItems.length === 0 ||
                                    form.processing
                                }
                            >
                                <RotateCcw />
                                Enregistrer le retour
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

CreateReturn.layout = {
    breadcrumbs: [
        {
            title: 'Retours & avoirs',
            href: stock.returns.index().url,
        },
        {
            title: 'Nouveau retour',
            href: stock.returns.create().url,
        },
    ],
};
