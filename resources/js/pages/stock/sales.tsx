import { Head, router, usePage } from '@inertiajs/react';
import {
    Ban,
    Download,
    Eye,
    FileCheck2,
    ShoppingCart,
    Undo2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
    formatCurrency,
    formatDate,
    formatNumber,
    PAYMENT_METHOD_LABELS,
    SALE_STATUS_LABELS,
    unitLabel,
} from '@/lib/format';
import stock from '@/routes/stock';
import type { Auth, Paginated, PaymentMethod, Sale, SaleStatus } from '@/types';

type Props = {
    sales: Paginated<Sale>;
    filters: {
        from: string | null;
        to: string | null;
        payment_method: PaymentMethod | null;
        status: SaleStatus | null;
    };
};

function PaginationBar({ pagination }: { pagination: Paginated<Sale> }) {
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
                {formatNumber(pagination.total)} vente
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

function SaleDetail({
    sale,
    onClose,
}: {
    sale: Sale | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={sale !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Vente {sale?.reference}</DialogTitle>
                    <DialogDescription>
                        {sale
                            ? `${formatDate(sale.created_at)} · ${sale.payment_label} · ${SALE_STATUS_LABELS[sale.status]}`
                            : ''}
                    </DialogDescription>
                </DialogHeader>

                {sale && (
                    <div className="space-y-4">
                        <div className="overflow-hidden rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Produit</TableHead>
                                        <TableHead className="text-right">
                                            Prix unitaire
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Qté
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sale.items.map((item) => (
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
                                                {formatNumber(item.quantity)}{' '}
                                                {unitLabel(
                                                    item.product.unit ?? 'pcs',
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {formatCurrency(
                                                    item.unit_price *
                                                        item.quantity,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {sale.discount_percent > 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="text-muted-foreground"
                                            >
                                                Remise ({sale.discount_percent}
                                                %)
                                            </TableCell>
                                            <TableCell className="text-right font-medium text-red-600 tabular-nums dark:text-red-400">
                                                −{' '}
                                                {formatCurrency(
                                                    (sale.total *
                                                        sale.discount_percent) /
                                                        (100 -
                                                            sale.discount_percent),
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {sale.tax_percent > 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="text-muted-foreground"
                                            >
                                                TVA ({sale.tax_percent}%)
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {formatCurrency(
                                                    sale.tax_amount,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="font-semibold"
                                        >
                                            Total TTC
                                        </TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">
                                            {formatCurrency(sale.total)}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        {sale.mobile_phone && (
                            <p className="text-sm">
                                Paiement mobile :{' '}
                                <span className="font-medium">
                                    {sale.mobile_phone}
                                </span>
                                {sale.mobile_reference && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · Réf. {sale.mobile_reference}
                                    </span>
                                )}
                            </p>
                        )}

                        {sale.notes && (
                            <p className="text-sm text-muted-foreground">
                                {sale.notes}
                            </p>
                        )}

                        <p className="text-xs text-muted-foreground">
                            Encaissée par {sale.user?.name ?? '—'}
                        </p>
                    </div>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Fermer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Sales({ sales, filters }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canDelete = permissions.includes('sales.delete');

    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [paymentMethod, setPaymentMethod] = useState(
        filters.payment_method ?? '',
    );
    const [status, setStatus] = useState(filters.status ?? '');

    const [detailSale, setDetailSale] = useState<Sale | null>(null);
    const [cancelTarget, setCancelTarget] = useState<Sale | null>(null);

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

            if (paymentMethod) {
                query.payment_method = paymentMethod;
            }

            if (status) {
                query.status = status;
            }

            router.get(
                stock.sales.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['sales', 'filters'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [from, to, paymentMethod, status]);

    const hasFilters = Boolean(from || to || paymentMethod || status);

    const resetFilters = () => {
        setFrom('');
        setTo('');
        setPaymentMethod('');
        setStatus('');
    };

    const confirmCancel = () => {
        if (!cancelTarget) {
            return;
        }

        router.delete(stock.sales.destroy(cancelTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setCancelTarget(null),
        });
    };

    return (
        <>
            <Head title="Ventes" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Ventes
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Historique des encaissements en caisse.
                        </p>
                    </div>
                    <Button size="sm" asChild>
                        <a href={stock.sales.pos().url}>Ouvrir la caisse</a>
                    </Button>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="grid grid-cols-2 gap-3 sm:flex sm:items-end">
                        <div className="space-y-1.5">
                            <Label className="text-xs">Du</Label>
                            <Input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                                className="h-9 w-full sm:w-40"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Au</Label>
                            <Input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                                className="h-9 w-full sm:w-40"
                            />
                        </div>
                    </div>
                    <Select
                        value={paymentMethod || 'none'}
                        onValueChange={(v) =>
                            setPaymentMethod(v === 'none' ? '' : v)
                        }
                    >
                        <SelectTrigger className="h-9 w-full sm:w-48">
                            <SelectValue placeholder="Tous les paiements" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Tous les paiements
                            </SelectItem>
                            {(
                                Object.entries(PAYMENT_METHOD_LABELS) as [
                                    PaymentMethod,
                                    string,
                                ][]
                            ).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={status || 'none'}
                        onValueChange={(v) => setStatus(v === 'none' ? '' : v)}
                    >
                        <SelectTrigger className="h-9 w-full sm:w-40">
                            <SelectValue placeholder="Tous les statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Tous les statuts
                            </SelectItem>
                            <SelectItem value="completed">Validées</SelectItem>
                            <SelectItem value="cancelled">Annulées</SelectItem>
                        </SelectContent>
                    </Select>
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-9"
                            onClick={resetFilters}
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
                                <TableHead>Référence</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead className="text-right">
                                    Articles
                                </TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead>Paiement</TableHead>
                                <TableHead>Statut</TableHead>
                                <TableHead>Par</TableHead>
                                <TableHead>Facture</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sales.data.map((sale) => (
                                <TableRow
                                    key={sale.id}
                                    className={
                                        sale.status === 'cancelled'
                                            ? 'opacity-60'
                                            : ''
                                    }
                                >
                                    <TableCell className="font-medium">
                                        {sale.reference}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(sale.created_at)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(sale.items.length)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {formatCurrency(sale.total)}
                                    </TableCell>
                                    <TableCell>
                                        {sale.payment_label}
                                        {sale.mobile_phone && (
                                            <div className="text-xs text-muted-foreground">
                                                {sale.mobile_phone}
                                            </div>
                                        )}
                                        {sale.tax_percent > 0 && (
                                            <div className="text-xs text-muted-foreground">
                                                TVA {sale.tax_percent}%
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={
                                                sale.status === 'completed'
                                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                    : 'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400'
                                            }
                                        >
                                            {SALE_STATUS_LABELS[sale.status]}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {sale.user?.name ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {sale.invoice ? (
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5 border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                            >
                                                <FileCheck2 className="size-3" />
                                                {sale.invoice.reference}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {sale.status === 'completed' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                    aria-label={`Ticket PDF de la vente ${sale.reference}`}
                                                >
                                                    <a
                                                        href={
                                                            stock.sales.ticketPdf(
                                                                sale.id,
                                                            ).url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <Download />
                                                    </a>
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setDetailSale(sale)
                                                }
                                                aria-label={`Voir la vente ${sale.reference}`}
                                            >
                                                <Eye />
                                            </Button>
                                            {canDelete &&
                                                sale.status === 'completed' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                        onClick={() =>
                                                            setCancelTarget(
                                                                sale,
                                                            )
                                                        }
                                                        aria-label={`Annuler la vente ${sale.reference}`}
                                                    >
                                                        <Undo2 />
                                                    </Button>
                                                )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {sales.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={9}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <ShoppingCart className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucune vente
                                            </p>
                                            <p className="text-xs">
                                                {hasFilters
                                                    ? 'Essayez de modifier vos filtres.'
                                                    : 'Les encaissements de la caisse apparaîtront ici.'}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar pagination={sales} />
            </div>

            <SaleDetail sale={detailSale} onClose={() => setDetailSale(null)} />

            {/* Cancel confirmation */}
            <Dialog
                open={cancelTarget !== null}
                onOpenChange={(open) => !open && setCancelTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Ban className="size-4 text-red-600 dark:text-red-400" />
                            Annuler la vente {cancelTarget?.reference}
                        </DialogTitle>
                        <DialogDescription>
                            Les produits vendus seront restitués au stock et un
                            mouvement d’entrée sera enregistré pour chaque
                            article. Cette action est irréversible.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setCancelTarget(null)}
                        >
                            Retour
                        </Button>
                        <Button variant="destructive" onClick={confirmCancel}>
                            Annuler la vente
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Sales.layout = {
    breadcrumbs: [
        {
            title: 'Ventes',
            href: stock.sales.index().url,
        },
    ],
};
