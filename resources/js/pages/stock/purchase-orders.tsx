import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    PackagePlus,
    Plus,
    ShoppingCart,
    Trash2,
    Truck,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type {
    Auth,
    Paginated,
    Product,
    PurchaseOrder,
    PurchaseOrderStatus,
    Supplier,
    Warehouse,
} from '@/types';

type Props = {
    orders: Paginated<PurchaseOrder>;
    suppliers: Pick<Supplier, 'id' | 'name'>[];
    warehouses: Pick<Warehouse, 'id' | 'name' | 'code'>[];
    products: Pick<Product, 'id' | 'name' | 'sku' | 'unit'>[];
    filters: {
        status: string | null;
        supplier_id: string | null;
    };
};

const statusBadge: Record<
    PurchaseOrderStatus,
    { label: string; className: string }
> = {
    draft: {
        label: 'Brouillon',
        className: 'bg-muted text-muted-foreground border-muted',
    },
    ordered: {
        label: 'Commandée',
        className:
            'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
    },
    partial: {
        label: 'Partielle',
        className:
            'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
    },
    received: {
        label: 'Réceptionnée',
        className:
            'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
    },
};

function PaginationBar({
    pagination,
}: {
    pagination: Paginated<PurchaseOrder>;
}) {
    const go = (url: string | null) => {
        if (!url) {
            return;
        }

        router.get(url, {}, { preserveState: true });
    };

    const numberedLinks = pagination.links.filter(
        (link) => !Number.isNaN(Number(link.label)),
    );

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground">
                {formatNumber(pagination.total)} commande
                {pagination.total > 1 ? 's' : ''} — page{' '}
                {pagination.current_page} sur {pagination.last_page}
            </p>
            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!pagination.prev_page_url}
                    onClick={() => go(pagination.prev_page_url)}
                >
                    Précédent
                </Button>
                {numberedLinks.map((link) => (
                    <Button
                        key={link.label}
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        onClick={() => go(link.url)}
                    >
                        {link.label}
                    </Button>
                ))}
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!pagination.next_page_url}
                    onClick={() => go(pagination.next_page_url)}
                >
                    Suivant
                </Button>
            </div>
        </div>
    );
}

function CreateOrderForm({
    suppliers,
    warehouses,
    products,
    onDone,
}: {
    suppliers: Props['suppliers'];
    warehouses: Props['warehouses'];
    products: Props['products'];
    onDone: () => void;
}) {
    const form = useForm<{
        supplier_id: string;
        warehouse_id: string;
        reference: string;
        items: { product_id: number; quantity: number }[];
    }>({
        supplier_id: '',
        warehouse_id: '',
        reference: '',
        items: [],
    });

    const [rows, setRows] = useState<
        { product_id: string; quantity: string }[]
    >([{ product_id: '', quantity: '' }]);

    const setRow = (
        index: number,
        field: 'product_id' | 'quantity',
        value: string,
    ) => {
        setRows((current) =>
            current.map((row, i) =>
                i === index ? { ...row, [field]: value } : row,
            ),
        );
    };

    const addRow = () => {
        setRows((current) => [...current, { product_id: '', quantity: '' }]);
    };

    const removeRow = (index: number) => {
        setRows((current) => current.filter((_, i) => i !== index));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.setData(
            'items',
            rows
                .filter((row) => row.product_id && Number(row.quantity) > 0)
                .map((row) => ({
                    product_id: Number(row.product_id),
                    quantity: Number(row.quantity),
                })),
        );

        form.post(stock.purchaseOrders.store().url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    const totalQuantity = rows.reduce(
        (sum, row) => sum + (Number(row.quantity) || 0),
        0,
    );

    return (
        <form id="order-form" onSubmit={submit} className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="space-y-2">
                    <Label>Fournisseur</Label>
                    <Select
                        value={form.data.supplier_id}
                        onValueChange={(v) => form.setData('supplier_id', v)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Choisir…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">— Choisir… —</SelectItem>
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
                        onValueChange={(v) => form.setData('warehouse_id', v)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Défaut" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">— Défaut —</SelectItem>
                            {warehouses.map((warehouse) => (
                                <SelectItem
                                    key={warehouse.id}
                                    value={String(warehouse.id)}
                                >
                                    {warehouse.name} ({warehouse.code})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.warehouse_id} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="order-reference">Référence</Label>
                    <Input
                        id="order-reference"
                        value={form.data.reference}
                        onChange={(e) =>
                            form.setData('reference', e.target.value)
                        }
                        placeholder="Ex. : CMD-2026-001"
                    />
                    <InputError message={form.errors.reference} />
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Produit</TableHead>
                            <TableHead className="w-32 text-right">
                                Quantité
                            </TableHead>
                            <TableHead className="w-10" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row, index) => (
                            <TableRow key={index}>
                                <TableCell>
                                    <Select
                                        value={row.product_id}
                                        onValueChange={(v) =>
                                            setRow(
                                                index,
                                                'product_id',
                                                v === 'none' ? '' : v,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Choisir un produit…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {products.map((product) => (
                                                <SelectItem
                                                    key={product.id}
                                                    value={String(product.id)}
                                                >
                                                    {product.name} (
                                                    {product.sku})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </TableCell>
                                <TableCell>
                                    <Input
                                        type="number"
                                        min="1"
                                        step="1"
                                        value={row.quantity}
                                        onChange={(e) =>
                                            setRow(
                                                index,
                                                'quantity',
                                                e.target.value,
                                            )
                                        }
                                        className="ml-auto h-8 w-24 text-right tabular-nums"
                                        placeholder="0"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7 text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                        disabled={rows.length === 1}
                                        onClick={() => removeRow(index)}
                                        aria-label="Retirer la ligne"
                                    >
                                        <X />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                className="w-full"
                onClick={addRow}
            >
                <Plus />
                Ajouter une ligne
            </Button>

            <InputError message={form.errors.items} />

            <DialogFooter className="items-center">
                <p className="mr-auto text-sm text-muted-foreground">
                    {formatNumber(totalQuantity)} unité
                    {totalQuantity > 1 ? 's' : ''} commandée
                    {totalQuantity > 1 ? 's' : ''}
                </p>
                <Button
                    type="submit"
                    disabled={
                        form.processing || rows.every((row) => !row.product_id)
                    }
                >
                    Créer la commande
                </Button>
            </DialogFooter>
        </form>
    );
}

function ReceiveDialog({
    order,
    onDone,
}: {
    order: PurchaseOrder;
    onDone: () => void;
}) {
    // Le dialogue est monté à l'ouverture : l'état part des restants à
    // recevoir sans effet secondaire.
    const [quantities, setQuantities] = useState<Record<number, number>>(() =>
        Object.fromEntries(
            order.items.map((item) => [item.id, item.remaining]),
        ),
    );
    const form = useForm<{
        items: { id: number; received_quantity: number }[];
    }>({
        items: [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.setData(
            'items',
            Object.entries(quantities).map(([id, quantity]) => ({
                id: Number(id),
                received_quantity: quantity,
            })),
        );

        form.post(stock.purchaseOrders.receive(order.id).url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    const setQuantity = (id: number, value: string) => {
        setQuantities((current) => ({
            ...current,
            [id]: Math.max(0, Number.parseInt(value, 10) || 0),
        }));
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onDone()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Réceptionner {order.reference}</DialogTitle>
                    <DialogDescription>
                        Saisissez les quantités réellement reçues. La réception
                        alimente le stock de l’entrepôt.
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="receive-form"
                    onSubmit={submit}
                    className="grid gap-4"
                >
                    <div className="overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Produit</TableHead>
                                    <TableHead className="text-right">
                                        Commandé
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Déjà reçu
                                    </TableHead>
                                    <TableHead className="text-right">
                                        À recevoir
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {order.items.map((item) => (
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
                                            {formatNumber(item.quantity)}
                                        </TableCell>
                                        <TableCell className="text-right text-muted-foreground tabular-nums">
                                            {formatNumber(
                                                item.received_quantity,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Input
                                                type="number"
                                                min="0"
                                                max={item.remaining}
                                                step="1"
                                                value={quantities[item.id] ?? 0}
                                                onChange={(e) =>
                                                    setQuantity(
                                                        item.id,
                                                        e.target.value,
                                                    )
                                                }
                                                className="ml-auto h-8 w-20 text-right tabular-nums"
                                                aria-label={`Quantité reçue pour ${item.product.name}`}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    <InputError message={form.errors.items} />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onDone}
                        >
                            Annuler
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Enregistrer la réception
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PurchaseOrders({
    orders,
    suppliers,
    warehouses,
    products,
    filters,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canCreate = permissions.includes('purchase_orders.create');
    const canUpdate = permissions.includes('purchase_orders.update');
    const canReceive = permissions.includes('purchase_orders.receive');
    const canDelete = permissions.includes('purchase_orders.delete');

    const [status, setStatus] = useState(filters.status ?? '');
    const [supplierId, setSupplierId] = useState(filters.supplier_id ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [receiveOrder, setReceiveOrder] = useState<PurchaseOrder | null>(
        null,
    );
    const [deleteTarget, setDeleteTarget] = useState<PurchaseOrder | null>(
        null,
    );

    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        const timer = setTimeout(() => {
            const query: Record<string, string> = {};

            if (status) {
                query.status = status;
            }

            if (supplierId) {
                query.supplier_id = supplierId;
            }

            router.get(
                stock.purchaseOrders.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['orders', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [status, supplierId]);

    const markOrdered = (order: PurchaseOrder) => {
        router.post(
            stock.purchaseOrders.order(order.id).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(stock.purchaseOrders.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const hasFilters = Boolean(status || supplierId);

    return (
        <>
            <Head title="Commandes fournisseur" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Commandes fournisseur
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Suivez vos commandes, de la création à la réception
                            complète.
                        </p>
                    </div>
                    {canCreate && (
                        <Button onClick={() => setCreateOpen(true)} size="sm">
                            <PackagePlus />
                            Nouvelle commande
                        </Button>
                    )}
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row">
                    <Select
                        value={status || 'none'}
                        onValueChange={(v) => setStatus(v === 'none' ? '' : v)}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Tous les statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Tous les statuts
                            </SelectItem>
                            <SelectItem value="draft">Brouillon</SelectItem>
                            <SelectItem value="ordered">Commandée</SelectItem>
                            <SelectItem value="partial">Partielle</SelectItem>
                            <SelectItem value="received">
                                Réceptionnée
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={supplierId || 'none'}
                        onValueChange={(v) =>
                            setSupplierId(v === 'none' ? '' : v)
                        }
                    >
                        <SelectTrigger className="w-full sm:w-64">
                            <SelectValue placeholder="Tous les fournisseurs" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Tous les fournisseurs
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
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-9"
                            onClick={() => {
                                setStatus('');
                                setSupplierId('');
                            }}
                        >
                            Réinitialiser
                        </Button>
                    )}
                </div>

                {/* Orders */}
                <div className="space-y-4">
                    {orders.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-14 text-center text-muted-foreground">
                            <ShoppingCart className="size-6 opacity-40" />
                            <p className="text-sm font-medium">
                                Aucune commande
                            </p>
                            <p className="text-xs">
                                {hasFilters
                                    ? 'Essayez de modifier vos filtres.'
                                    : 'Créez votre première commande fournisseur.'}
                            </p>
                        </div>
                    ) : (
                        orders.data.map((order) => {
                            const badge = statusBadge[order.status];

                            return (
                                <div
                                    key={order.id}
                                    className="rounded-xl border p-4"
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="flex items-start gap-3">
                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                                <Truck className="size-4" />
                                            </span>
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-semibold">
                                                        {order.reference}
                                                    </p>
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            badge.className,
                                                        )}
                                                    >
                                                        {badge.label}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {order.supplier?.name ??
                                                        'Fournisseur inconnu'}
                                                    {order.warehouse
                                                        ? ` · ${order.warehouse.name}`
                                                        : ''}{' '}
                                                    ·{' '}
                                                    {formatDate(
                                                        order.created_at,
                                                    )}{' '}
                                                    · par{' '}
                                                    {order.user?.name ?? '—'}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex flex-col items-start gap-2 sm:items-end">
                                            <div className="flex items-center gap-2">
                                                {canUpdate &&
                                                    order.status ===
                                                        'draft' && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                markOrdered(
                                                                    order,
                                                                )
                                                            }
                                                        >
                                                            Passer commande
                                                        </Button>
                                                    )}
                                                {canReceive &&
                                                    (order.status ===
                                                        'ordered' ||
                                                        order.status ===
                                                            'partial') && (
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                setReceiveOrder(
                                                                    order,
                                                                )
                                                            }
                                                        >
                                                            Réceptionner
                                                        </Button>
                                                    )}
                                                {canDelete &&
                                                    (order.status === 'draft' ||
                                                        order.status ===
                                                            'ordered') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8 text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                            onClick={() =>
                                                                setDeleteTarget(
                                                                    order,
                                                                )
                                                            }
                                                            aria-label={`Supprimer ${order.reference}`}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    )}
                                            </div>
                                            <div className="w-full space-y-1">
                                                <div className="flex w-40 items-center justify-between text-xs text-muted-foreground">
                                                    <span>
                                                        {formatNumber(
                                                            order.received_quantity,
                                                        )}{' '}
                                                        /{' '}
                                                        {formatNumber(
                                                            order.total_quantity,
                                                        )}
                                                    </span>
                                                    <span>
                                                        {formatNumber(
                                                            order.progress,
                                                        )}
                                                        %
                                                    </span>
                                                </div>
                                                <div className="h-1.5 w-40 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className={cn(
                                                            'h-full rounded-full',
                                                            order.progress >=
                                                                100
                                                                ? 'bg-emerald-500'
                                                                : 'bg-indigo-500',
                                                        )}
                                                        style={{
                                                            width: `${order.progress}%`,
                                                        }}
                                                    />
                                                </div>
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
                                                {item.product.name} ·{' '}
                                                {formatNumber(item.quantity)}{' '}
                                                {unitLabel(item.product.unit)}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                <PaginationBar pagination={orders} />
            </div>

            {/* Create order dialog */}
            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Nouvelle commande</DialogTitle>
                        <DialogDescription>
                            La commande est créée en brouillon : vous pourrez la
                            passer puis la réceptionner.
                        </DialogDescription>
                    </DialogHeader>
                    {createOpen && (
                        <CreateOrderForm
                            suppliers={suppliers}
                            warehouses={warehouses}
                            products={products}
                            onDone={() => setCreateOpen(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            {/* Receive dialog */}
            {receiveOrder && (
                <ReceiveDialog
                    order={receiveOrder}
                    onDone={() => setReceiveOrder(null)}
                />
            )}

            {/* Delete confirmation */}
            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Supprimer la commande</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer «{' '}
                            {deleteTarget?.reference} » ? Seules les commandes
                            non réceptionnées peuvent être supprimées.
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

PurchaseOrders.layout = {
    breadcrumbs: [
        {
            title: 'Commandes fournisseur',
            href: stock.purchaseOrders.index().url,
        },
    ],
};
