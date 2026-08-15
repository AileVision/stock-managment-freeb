import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowRightLeft, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import stock from '@/routes/stock';
import type {
    Auth,
    Paginated,
    Product,
    StockTransfer,
    Warehouse,
} from '@/types';

type Props = {
    transfers: Paginated<StockTransfer>;
    products: Pick<Product, 'id' | 'name' | 'sku' | 'unit'>[];
    warehouses: Pick<Warehouse, 'id' | 'name' | 'code'>[];
    filters: {
        product_id: string | null;
    };
};

function PaginationBar({
    pagination,
}: {
    pagination: Paginated<StockTransfer>;
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
                {formatNumber(pagination.total)} transfert
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

function TransferForm({
    products,
    warehouses,
    onDone,
}: {
    products: Props['products'];
    warehouses: Props['warehouses'];
    onDone: () => void;
}) {
    const form = useForm({
        product_id: '',
        from_warehouse_id: '',
        to_warehouse_id: '',
        quantity: '',
        notes: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(stock.transfers.store().url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form id="transfer-form" onSubmit={submit} className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2 sm:col-span-2">
                    <Label>Produit</Label>
                    <Select
                        value={form.data.product_id}
                        onValueChange={(v) => form.setData('product_id', v)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Sélectionner un produit…" />
                        </SelectTrigger>
                        <SelectContent>
                            {products.map((product) => (
                                <SelectItem
                                    key={product.id}
                                    value={String(product.id)}
                                >
                                    {product.name} ({product.sku})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.product_id} />
                </div>

                <div className="space-y-2">
                    <Label>Entrepôt de départ</Label>
                    <Select
                        value={form.data.from_warehouse_id}
                        onValueChange={(v) =>
                            form.setData('from_warehouse_id', v)
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Choisir…" />
                        </SelectTrigger>
                        <SelectContent>
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
                    <InputError message={form.errors.from_warehouse_id} />
                </div>

                <div className="space-y-2">
                    <Label>Entrepôt de destination</Label>
                    <Select
                        value={form.data.to_warehouse_id}
                        onValueChange={(v) =>
                            form.setData('to_warehouse_id', v)
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Choisir…" />
                        </SelectTrigger>
                        <SelectContent>
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
                    <InputError message={form.errors.to_warehouse_id} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="transfer-quantity">Quantité</Label>
                    <Input
                        id="transfer-quantity"
                        type="number"
                        min="1"
                        step="1"
                        value={form.data.quantity}
                        onChange={(e) =>
                            form.setData('quantity', e.target.value)
                        }
                        placeholder="Ex. : 10"
                        required
                    />
                    <InputError message={form.errors.quantity} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="transfer-notes">Remarque</Label>
                    <Input
                        id="transfer-notes"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        placeholder="Optionnel"
                    />
                    <InputError message={form.errors.notes} />
                </div>
            </div>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    Enregistrer le transfert
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function Transfers({
    transfers,
    products,
    warehouses,
    filters,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canCreate = permissions.includes('transfers.create');

    const [productId, setProductId] = useState(filters.product_id ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);

    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        const timer = setTimeout(() => {
            const query: Record<string, string> = {};

            if (productId) {
                query.product_id = productId;
            }

            router.get(
                stock.transfers.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['transfers', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [productId]);

    return (
        <>
            <Head title="Transferts" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Transferts entre entrepôts
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Déplacez du stock d’un dépôt vers un autre, avec
                            traçabilité.
                        </p>
                    </div>
                    {canCreate && (
                        <Button onClick={() => setDialogOpen(true)} size="sm">
                            <Plus />
                            Nouveau transfert
                        </Button>
                    )}
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Select
                        value={productId || 'none'}
                        onValueChange={(v) =>
                            setProductId(v === 'none' ? '' : v)
                        }
                    >
                        <SelectTrigger className="w-full sm:w-72">
                            <SelectValue placeholder="Tous les produits" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Tous les produits
                            </SelectItem>
                            {products.map((product) => (
                                <SelectItem
                                    key={product.id}
                                    value={String(product.id)}
                                >
                                    {product.name} ({product.sku})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {productId && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-9"
                            onClick={() => setProductId('')}
                        >
                            Réinitialiser
                        </Button>
                    )}
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Produit</TableHead>
                                <TableHead>Départ</TableHead>
                                <TableHead>Arrivée</TableHead>
                                <TableHead className="text-right">
                                    Quantité
                                </TableHead>
                                <TableHead>Par</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transfers.data.map((transfer) => (
                                <TableRow key={transfer.id}>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(transfer.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">
                                            {transfer.product.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {transfer.product.sku}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {transfer.from_warehouse.name} (
                                        {transfer.from_warehouse.code})
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {transfer.to_warehouse.name} (
                                        {transfer.to_warehouse.code})
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {formatNumber(transfer.quantity)}{' '}
                                        <span className="text-xs text-muted-foreground">
                                            {unitLabel(transfer.product.unit)}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {transfer.user?.name ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {transfers.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <ArrowRightLeft className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun transfert
                                            </p>
                                            <p className="text-xs">
                                                Déplacez du stock entre vos
                                                entrepôts pour démarrer
                                                l’historique.
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar pagination={transfers} />
            </div>

            {/* Transfer dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Nouveau transfert</DialogTitle>
                        <DialogDescription>
                            Le stock est retiré de l’entrepôt de départ et
                            ajouté à l’entrepôt de destination.
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <TransferForm
                            products={products}
                            warehouses={warehouses}
                            onDone={() => setDialogOpen(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Transfers.layout = {
    breadcrumbs: [
        {
            title: 'Transferts',
            href: stock.transfers.index().url,
        },
    ],
};
