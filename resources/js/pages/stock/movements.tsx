import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowLeftRight,
    ArrowUpRight,
    Plus,
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
import {
    formatDate,
    formatNumber,
    MOVEMENT_TYPE_LABELS,
    unitLabel,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type {
    Auth,
    MovementType,
    Paginated,
    Product,
    StockMovement,
} from '@/types';

type Props = {
    movements: Paginated<StockMovement>;
    products: Pick<
        Product,
        'id' | 'name' | 'sku' | 'unit' | 'stock_quantity'
    >[];
    filters: {
        product_id: string | null;
        type: string | null;
        from: string | null;
        to: string | null;
    };
};

const typeBadgeClass: Record<MovementType, string> = {
    in: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
    out: 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20',
    adjustment:
        'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
};

function MovementForm({
    products,
    onDone,
}: {
    products: Props['products'];
    onDone: () => void;
}) {
    const form = useForm({
        product_id: '',
        type: 'in' as MovementType,
        quantity: '',
        reason: '',
        reference: '',
    });

    const selectedProduct = products.find(
        (product) => String(product.id) === form.data.product_id,
    );
    const isAdjustment = form.data.type === 'adjustment';

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(stock.movements.store().url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form id="movement-form" onSubmit={submit} className="grid gap-4">
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
                                    {product.name} ({product.sku}) —{' '}
                                    {formatNumber(product.stock_quantity)}{' '}
                                    {unitLabel(product.unit ?? 'pcs')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.product_id} />
                    {selectedProduct && form.data.type === 'out' && (
                        <p className="text-xs text-muted-foreground">
                            Stock actuel :{' '}
                            {formatNumber(selectedProduct.stock_quantity)}{' '}
                            {unitLabel(selectedProduct.unit ?? 'pcs')}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label>Type de mouvement</Label>
                    <Select
                        value={form.data.type}
                        onValueChange={(v) =>
                            form.setData('type', v as MovementType)
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="in">
                                Entrée (réception)
                            </SelectItem>
                            <SelectItem value="out">Sortie</SelectItem>
                            <SelectItem value="adjustment">
                                Ajustement
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.type} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="quantity">Quantité</Label>
                    <Input
                        id="quantity"
                        type="number"
                        step="1"
                        min={isAdjustment ? undefined : 1}
                        value={form.data.quantity}
                        onChange={(e) =>
                            form.setData('quantity', e.target.value)
                        }
                        placeholder={
                            isAdjustment ? 'Ex. : -5 ou 5' : 'Ex. : 10'
                        }
                        required
                    />
                    <p className="text-xs text-muted-foreground">
                        {isAdjustment
                            ? 'Ajustement signé : positif ou négatif.'
                            : form.data.type === 'in'
                              ? 'La quantité sera ajoutée au stock.'
                              : 'La quantité sera retirée du stock.'}
                    </p>
                    <InputError message={form.errors.quantity} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="reason">Motif</Label>
                    <Input
                        id="reason"
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                        placeholder="Ex. : Réception commande fournisseur"
                        required
                    />
                    <InputError message={form.errors.reason} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="reference">Référence</Label>
                    <Input
                        id="reference"
                        value={form.data.reference}
                        onChange={(e) =>
                            form.setData('reference', e.target.value)
                        }
                        placeholder="Ex. : BL-2026-005 (optionnel)"
                    />
                    <InputError message={form.errors.reference} />
                </div>
            </div>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    Enregistrer le mouvement
                </Button>
            </DialogFooter>
        </form>
    );
}

function PaginationBar({
    pagination,
}: {
    pagination: Paginated<StockMovement>;
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
                {formatNumber(pagination.total)} mouvement
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

export default function Movements({ movements, products, filters }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canCreate = permissions.includes('movements.create');

    const [productId, setProductId] = useState(filters.product_id ?? '');
    const [type, setType] = useState(filters.type ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
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

            if (type) {
                query.type = type;
            }

            if (from) {
                query.from = from;
            }

            if (to) {
                query.to = to;
            }

            router.get(
                stock.movements.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['movements', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [productId, type, from, to]);

    const hasFilters = Boolean(productId || type || from || to);

    return (
        <>
            <Head title="Mouvements" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Mouvements de stock
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Historique des entrées, sorties et ajustements.
                        </p>
                    </div>
                    {canCreate && (
                        <Button onClick={() => setDialogOpen(true)} size="sm">
                            <Plus />
                            Nouveau mouvement
                        </Button>
                    )}
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row">
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

                    <Select
                        value={type || 'none'}
                        onValueChange={(v) => setType(v === 'none' ? '' : v)}
                    >
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="Tous les types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Tous les types</SelectItem>
                            <SelectItem value="in">Entrées</SelectItem>
                            <SelectItem value="out">Sorties</SelectItem>
                            <SelectItem value="adjustment">
                                Ajustements
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-9"
                            onClick={() => {
                                setProductId('');
                                setType('');
                                setFrom('');
                                setTo('');
                            }}
                        >
                            Réinitialiser
                        </Button>
                    )}

                    <Input
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                        aria-label="Date de début"
                        className="h-9 w-full sm:w-40"
                    />
                    <Input
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        aria-label="Date de fin"
                        className="h-9 w-full sm:w-40"
                    />
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Produit</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="text-right">
                                    Quantité
                                </TableHead>
                                <TableHead>Référence</TableHead>
                                <TableHead>Motif</TableHead>
                                <TableHead>Par</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {movements.data.map((movement) => (
                                <TableRow key={movement.id}>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(movement.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">
                                            {movement.product.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {movement.product.sku}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={
                                                typeBadgeClass[movement.type]
                                            }
                                        >
                                            {
                                                MOVEMENT_TYPE_LABELS[
                                                    movement.type
                                                ]
                                            }
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1 font-semibold tabular-nums',
                                                movement.quantity >= 0
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-red-600 dark:text-red-400',
                                            )}
                                        >
                                            {movement.quantity > 0 ? (
                                                <ArrowUpRight className="size-4" />
                                            ) : (
                                                <ArrowDownRight className="size-4" />
                                            )}
                                            {movement.quantity > 0 ? '+' : ''}
                                            {formatNumber(movement.quantity)}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {movement.reference ?? '—'}
                                    </TableCell>
                                    <TableCell className="max-w-48 truncate text-muted-foreground">
                                        {movement.reason ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {movement.user?.name ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {movements.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <ArrowLeftRight className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun mouvement
                                            </p>
                                            <p className="text-xs">
                                                Enregistrez une entrée ou une
                                                sortie pour démarrer
                                                l’historique.
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar pagination={movements} />
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Nouveau mouvement</DialogTitle>
                        <DialogDescription>
                            Enregistrez une entrée, une sortie ou un ajustement
                            de stock.
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <MovementForm
                            products={products}
                            onDone={() => setDialogOpen(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Movements.layout = {
    breadcrumbs: [
        {
            title: 'Mouvements',
            href: stock.movements.index().url,
        },
    ],
};
