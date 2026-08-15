import { Head, useForm, usePage } from '@inertiajs/react';
import { ClipboardList, PackagePlus, ShoppingCart, Truck } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox as CheckboxPrimitive } from '@/components/ui/checkbox';
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
import { formatDate, formatNumber, unitLabel } from '@/lib/format';
import stock from '@/routes/stock';
import type {
    Auth,
    PurchaseOrder,
    ReplenishmentProduct,
    Supplier,
    Warehouse,
} from '@/types';

type Props = {
    products: ReplenishmentProduct[];
    purchase_orders: PurchaseOrder[];
    suppliers: Pick<Supplier, 'id' | 'name'>[];
    warehouses: Pick<Warehouse, 'id' | 'name' | 'code' | 'is_default'>[];
};

type ItemInput = {
    product_id: number;
    quantity: number;
};

export default function Replenishment({
    products,
    purchase_orders,
    suppliers,
    warehouses,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canCreate = permissions.includes('replenishment.create');

    const [quantities, setQuantities] = useState<Record<number, number>>(() =>
        Object.fromEntries(
            products.map((product) => [product.id, product.suggested_quantity]),
        ),
    );
    const [selected, setSelected] = useState<Set<number>>(
        () => new Set(products.map((product) => product.id)),
    );
    const [dialogOpen, setDialogOpen] = useState(false);

    const defaultWarehouse =
        warehouses.find((warehouse) => warehouse.is_default) ??
        warehouses[0] ??
        null;

    const form = useForm<{
        supplier_id: string;
        reference: string;
        warehouse_id: string;
        items: ItemInput[];
    }>({
        supplier_id: '',
        reference: '',
        warehouse_id: defaultWarehouse ? String(defaultWarehouse.id) : '',
        items: [],
    });

    const toggleProduct = (id: number) => {
        setSelected((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const toggleAll = () => {
        setSelected(
            selected.size === products.length
                ? new Set()
                : new Set(products.map((product) => product.id)),
        );
    };

    const setQuantity = (id: number, value: string) => {
        setQuantities((current) => ({
            ...current,
            [id]: Math.max(1, Number.parseInt(value, 10) || 1),
        }));
    };

    const selectedItems: ItemInput[] = products
        .filter((product) => selected.has(product.id))
        .map((product) => ({
            product_id: product.id,
            quantity: quantities[product.id] ?? product.suggested_quantity,
        }));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.setData('items', selectedItems);
        form.post(stock.replenishment.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        });
    };

    const totalQuantity = selectedItems.reduce(
        (sum, item) => sum + item.quantity,
        0,
    );

    return (
        <>
            <Head title="Réapprovisionnement" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Réapprovisionnement
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Produits sous leur seuil et réceptions fournisseur.
                        </p>
                    </div>
                    {canCreate && (
                        <Button
                            onClick={() => setDialogOpen(true)}
                            size="sm"
                            disabled={selectedItems.length === 0}
                        >
                            <PackagePlus />
                            Réceptionner la commande
                        </Button>
                    )}
                </div>

                {/* Reorder list */}
                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {canCreate && (
                                    <TableHead className="w-10">
                                        <CheckboxPrimitive
                                            checked={
                                                selected.size ===
                                                    products.length &&
                                                products.length > 0
                                            }
                                            onCheckedChange={toggleAll}
                                            aria-label="Tout sélectionner"
                                        />
                                    </TableHead>
                                )}
                                <TableHead>Produit</TableHead>
                                <TableHead>Catégorie</TableHead>
                                <TableHead>Fournisseur</TableHead>
                                <TableHead className="text-right">
                                    Stock
                                </TableHead>
                                <TableHead className="text-right">
                                    Seuil
                                </TableHead>
                                {canCreate && (
                                    <TableHead className="text-right">
                                        Quantité à réceptionner
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.map((product) => (
                                <TableRow key={product.id}>
                                    {canCreate && (
                                        <TableCell>
                                            <CheckboxPrimitive
                                                checked={selected.has(
                                                    product.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleProduct(product.id)
                                                }
                                                aria-label={`Sélectionner ${product.name}`}
                                            />
                                        </TableCell>
                                    )}
                                    <TableCell>
                                        <div className="font-medium">
                                            {product.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {product.sku}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {product.category?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {product.supplier?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <span
                                            className={
                                                product.stock_quantity === 0
                                                    ? 'font-semibold text-red-600 dark:text-red-400'
                                                    : 'font-medium tabular-nums'
                                            }
                                        >
                                            {formatNumber(
                                                product.stock_quantity,
                                            )}{' '}
                                            <span className="text-xs text-muted-foreground">
                                                {unitLabel(product.unit)}
                                            </span>
                                        </span>
                                        {product.stock_quantity === 0 && (
                                            <Badge
                                                variant="destructive"
                                                className="ml-2"
                                            >
                                                Rupture
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {formatNumber(product.min_stock)}
                                    </TableCell>
                                    {canCreate && (
                                        <TableCell className="text-right">
                                            <Input
                                                type="number"
                                                min={1}
                                                disabled={
                                                    !selected.has(product.id)
                                                }
                                                value={
                                                    quantities[product.id] ??
                                                    product.suggested_quantity
                                                }
                                                onChange={(e) =>
                                                    setQuantity(
                                                        product.id,
                                                        e.target.value,
                                                    )
                                                }
                                                className="ml-auto h-8 w-24 text-right tabular-nums"
                                                aria-label={`Quantité pour ${product.name}`}
                                            />
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {products.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={canCreate ? 7 : 6}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <ClipboardList className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun produit à réapprovisionner
                                            </p>
                                            <p className="text-xs">
                                                Tous vos produits sont au-dessus
                                                de leur seuil.
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Reception history */}
                <div className="grid gap-4">
                    <h3 className="text-base font-semibold tracking-tight">
                        Dernières réceptions
                    </h3>
                    {purchase_orders.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 rounded-xl border py-10 text-center">
                            <ShoppingCart className="size-6 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                Aucune réception enregistrée pour le moment.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {purchase_orders.map((order) => (
                                <div
                                    key={order.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                                <Truck className="size-4" />
                                            </span>
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {order.supplier?.name ??
                                                        'Fournisseur inconnu'}
                                                    {order.reference &&
                                                        ` — ${order.reference}`}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        order.created_at,
                                                    )}{' '}
                                                    ·{' '}
                                                    {formatNumber(
                                                        order.total_quantity,
                                                    )}{' '}
                                                    unités · par{' '}
                                                    {order.user?.name ?? '—'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {order.items.map((item) => (
                                            <Badge
                                                key={item.id}
                                                variant="outline"
                                                className="tabular-nums"
                                            >
                                                {item.product.name} · +{' '}
                                                {formatNumber(item.quantity)}{' '}
                                                {unitLabel(item.product.unit)}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Reception dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Réceptionner la commande</DialogTitle>
                        <DialogDescription>
                            La réception crée une entrée de stock pour chaque
                            produit sélectionné.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="space-y-2">
                                <Label>Fournisseur</Label>
                                <Select
                                    value={form.data.supplier_id}
                                    onValueChange={(v) =>
                                        form.setData('supplier_id', v)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Choisir…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            — Choisir… —
                                        </SelectItem>
                                        {suppliers.map((supplier) => (
                                            <SelectItem
                                                key={supplier.id}
                                                value={String(supplier.id)}
                                            >
                                                {supplier.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.supplier_id} />
                            </div>
                            <div className="space-y-2">
                                <Label>Entrepôt de réception</Label>
                                <Select
                                    value={form.data.warehouse_id}
                                    onValueChange={(v) =>
                                        form.setData('warehouse_id', v)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Défaut" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            — Défaut —
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
                                <InputError
                                    message={form.errors.warehouse_id}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="reference">
                                    Référence (BL)
                                </Label>
                                <Input
                                    id="reference"
                                    value={form.data.reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'reference',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Ex. : BL-2026-010"
                                />
                                <InputError message={form.errors.reference} />
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Produit</TableHead>
                                        <TableHead className="text-right">
                                            Quantité
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {selectedItems.map((item) => {
                                        const product = products.find(
                                            (p) => p.id === item.product_id,
                                        );

                                        return (
                                            <TableRow key={item.product_id}>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {product?.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {product?.sku}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    +{' '}
                                                    {formatNumber(
                                                        item.quantity,
                                                    )}{' '}
                                                    {unitLabel(
                                                        product?.unit ?? 'pcs',
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                        <InputError message={form.errors.items} />

                        <DialogFooter className="items-center">
                            <p className="mr-auto text-sm text-muted-foreground">
                                {formatNumber(selectedItems.length)} produit
                                {selectedItems.length > 1 ? 's' : ''} ·{' '}
                                {formatNumber(totalQuantity)} unités
                            </p>
                            <Button type="submit" disabled={form.processing}>
                                Enregistrer la réception
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Replenishment.layout = {
    breadcrumbs: [
        {
            title: 'Réapprovisionnement',
            href: stock.replenishment.index().url,
        },
    ],
};
