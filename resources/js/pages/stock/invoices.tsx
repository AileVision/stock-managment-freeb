import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Download, FileCheck2, Plus, Search } from 'lucide-react';
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
    formatCurrency,
    formatDateOnly,
    formatNumber,
    PAYMENT_METHOD_LABELS,
} from '@/lib/format';
import stock from '@/routes/stock';
import type {
    Auth,
    CustomerOption,
    Invoice,
    Paginated,
    UninvoicedSale,
} from '@/types';

type Props = {
    invoices: Paginated<Invoice>;
    uninvoiced_sales: UninvoicedSale[];
    customers: CustomerOption[];
    filters: {
        from: string | null;
        to: string | null;
        search: string;
    };
};

type InvoiceFormData = {
    sale_id: string;
    customer_id: string;
    customer_name: string;
    customer_email: string;
    customer_address: string;
    notes: string;
};

function PaginationBar({ pagination }: { pagination: Paginated<Invoice> }) {
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
                {formatNumber(pagination.total)} facture
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

export default function Invoices({
    invoices,
    uninvoiced_sales,
    customers,
    filters,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canCreate = permissions.includes('invoices.create');

    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [search, setSearch] = useState(filters.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);

    const form = useForm<InvoiceFormData>({
        sale_id: '',
        customer_id: '',
        customer_name: '',
        customer_email: '',
        customer_address: '',
        notes: '',
    });

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

            if (search.trim()) {
                query.search = search.trim();
            }

            router.get(
                stock.invoices.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['invoices', 'filters'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [from, to, search]);

    const hasFilters = Boolean(from || to || search.trim());

    const openCreate = () => {
        form.reset();
        setDialogOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(stock.invoices.store().url, {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        });
    };

    const selectedSale = uninvoiced_sales.find(
        (sale) => sale.id === Number(form.data.sale_id),
    );

    return (
        <>
            <Head title="Factures" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Factures
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Émettez des factures à partir de vos ventes et
                            téléchargez-les en PDF.
                        </p>
                    </div>
                    {canCreate && uninvoiced_sales.length > 0 && (
                        <Button onClick={openCreate} size="sm">
                            <Plus />
                            Facturer une vente
                        </Button>
                    )}
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
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher par référence ou client…"
                            className="h-9 pl-9"
                        />
                    </div>
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-9"
                            onClick={() => {
                                setFrom('');
                                setTo('');
                                setSearch('');
                            }}
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
                                <TableHead>Client</TableHead>
                                <TableHead>Émise le</TableHead>
                                <TableHead>Vente</TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead>Par</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invoices.data.map((invoice) => (
                                <TableRow key={invoice.id}>
                                    <TableCell className="font-medium">
                                        {invoice.reference}
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">
                                            {invoice.customer_name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {invoice.customer_email ?? ''}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateOnly(invoice.issued_at)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {invoice.sale_reference ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {formatCurrency(invoice.total)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {invoice.user?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                            aria-label={`Télécharger la facture ${invoice.reference}`}
                                        >
                                            <a
                                                href={
                                                    stock.invoices.pdf(
                                                        invoice.id,
                                                    ).url
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Download />
                                            </a>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {invoices.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <FileCheck2 className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucune facture
                                            </p>
                                            <p className="text-xs">
                                                {hasFilters
                                                    ? 'Essayez de modifier vos filtres.'
                                                    : 'Les factures émises apparaîtront ici.'}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar pagination={invoices} />
            </div>

            {/* Create invoice dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Facturer une vente</DialogTitle>
                        <DialogDescription>
                            Choisissez une vente validée et renseignez le client
                            à facturer.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        id="invoice-form"
                        onSubmit={submit}
                        className="grid gap-4"
                    >
                        <div className="space-y-2">
                            <Label>Vente</Label>
                            <Select
                                value={form.data.sale_id || 'none'}
                                onValueChange={(v) => {
                                    form.setData(
                                        'sale_id',
                                        v === 'none' ? '' : v,
                                    );
                                    form.setData('customer_name', '');
                                    form.setData('customer_id', '');
                                }}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Choisir une vente…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {uninvoiced_sales.map((sale) => (
                                        <SelectItem
                                            key={sale.id}
                                            value={String(sale.id)}
                                        >
                                            {sale.reference} ·{' '}
                                            {formatDateOnly(sale.created_at)} ·{' '}
                                            {formatCurrency(sale.total)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.sale_id} />
                        </div>

                        <div className="space-y-2">
                            <Label>Client (carnet d’adresses)</Label>
                            <Select
                                value={form.data.customer_id || 'none'}
                                onValueChange={(v) => {
                                    const customer = customers.find(
                                        (c) => c.id === Number(v),
                                    );

                                    form.setData({
                                        customer_id: v === 'none' ? '' : v,
                                        customer_name:
                                            customer?.name ??
                                            form.data.customer_name,
                                        customer_email:
                                            customer?.email ??
                                            form.data.customer_email,
                                    });
                                }}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Choisir un client, ou saisir librement…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        — Saisie libre —
                                    </SelectItem>
                                    {customers.map((customer) => (
                                        <SelectItem
                                            key={customer.id}
                                            value={String(customer.id)}
                                        >
                                            {customer.name}
                                            {customer.email
                                                ? ` (${customer.email})`
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.customer_id} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="invoice-customer-name">
                                Nom du client
                            </Label>
                            <Input
                                id="invoice-customer-name"
                                value={form.data.customer_name}
                                onChange={(e) => {
                                    form.setData(
                                        'customer_name',
                                        e.target.value,
                                    );
                                    form.setData('customer_id', '');
                                }}
                                placeholder="Nom du client"
                                required
                            />
                            <InputError message={form.errors.customer_name} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="invoice-customer-email">
                                    E-mail
                                </Label>
                                <Input
                                    id="invoice-customer-email"
                                    type="email"
                                    value={form.data.customer_email}
                                    onChange={(e) =>
                                        form.setData(
                                            'customer_email',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Optionnel"
                                />
                                <InputError
                                    message={form.errors.customer_email}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="invoice-customer-address">
                                    Adresse
                                </Label>
                                <Input
                                    id="invoice-customer-address"
                                    value={form.data.customer_address}
                                    onChange={(e) =>
                                        form.setData(
                                            'customer_address',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Optionnel"
                                />
                                <InputError
                                    message={form.errors.customer_address}
                                />
                            </div>
                        </div>

                        {selectedSale && (
                            <p className="rounded-lg border bg-muted/50 p-3 text-sm">
                                <span className="text-muted-foreground">
                                    Paiement :{' '}
                                </span>
                                <Badge variant="outline">
                                    {PAYMENT_METHOD_LABELS[
                                        selectedSale.payment_method
                                    ] ?? selectedSale.payment_method}
                                </Badge>
                                <span className="text-muted-foreground">
                                    {' '}
                                    · Total :{' '}
                                </span>
                                <span className="font-medium tabular-nums">
                                    {formatCurrency(selectedSale.total)}
                                </span>
                            </p>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="invoice-notes">Remarque</Label>
                            <Input
                                id="invoice-notes"
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                                placeholder="Optionnel"
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <DialogFooter>
                            <Button type="submit" disabled={form.processing}>
                                Émettre la facture
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Invoices.layout = {
    breadcrumbs: [
        {
            title: 'Factures',
            href: stock.invoices.index().url,
        },
    ],
};
