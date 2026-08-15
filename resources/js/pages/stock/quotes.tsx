import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    FileText,
    Pencil,
    Plus,
    Search,
    Send,
    Trash2,
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
import {
    formatCurrency,
    formatDate,
    formatDateOnly,
    formatNumber,
    isMobilePayment,
    PAYMENT_METHOD_LABELS,
    QUOTE_STATUS_LABELS,
} from '@/lib/format';
import stock from '@/routes/stock';
import type {
    Auth,
    CustomerOption,
    Paginated,
    PaymentMethod,
    Quote,
    QuoteFormProduct,
    QuoteStatus,
    TaxRateOption,
} from '@/types';

type Props = {
    quotes: Paginated<Quote>;
    products: QuoteFormProduct[];
    customers: CustomerOption[];
    tax_rates: TaxRateOption[];
    filters: {
        status: string | null;
        search: string;
    };
};

type QuoteItemForm = {
    product_id: string;
    quantity: string;
    unit_price: string;
};

type QuoteFormData = {
    customer_id: string;
    customer_name: string;
    customer_email: string;
    customer_phone: string;
    customer_address: string;
    valid_until: string;
    discount_percent: string;
    tax_rate_id: string;
    notes: string;
    items: QuoteItemForm[];
};

const emptyForm = (defaultTaxRateId: string): QuoteFormData => ({
    customer_id: '',
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    customer_address: '',
    valid_until: '',
    discount_percent: '0',
    tax_rate_id: defaultTaxRateId,
    notes: '',
    items: [],
});

function toFormData(quote: Quote): QuoteFormData {
    return {
        customer_id: '',
        customer_name: quote.customer_name,
        customer_email: quote.customer_email ?? '',
        customer_phone: quote.customer_phone ?? '',
        customer_address: quote.customer_address ?? '',
        valid_until: quote.valid_until ?? '',
        discount_percent: String(quote.discount_percent),
        tax_rate_id:
            quote.tax_rate_id !== null ? String(quote.tax_rate_id) : '',
        notes: quote.notes ?? '',
        items: quote.items.map((item) => ({
            product_id: String(item.product_id ?? ''),
            quantity: String(item.quantity),
            unit_price: String(item.unit_price),
        })),
    };
}

const statusBadgeClass: Record<QuoteStatus, string> = {
    draft: 'border-neutral-500/20 bg-neutral-500/10 text-neutral-600 dark:text-neutral-400',
    sent: 'border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-400',
    accepted:
        'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    converted:
        'border-violet-500/20 bg-violet-500/10 text-violet-600 dark:text-violet-400',
    rejected: 'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
};

function QuoteForm({
    quote,
    products,
    customers,
    taxRates,
    onDone,
}: {
    quote: Quote | null;
    products: QuoteFormProduct[];
    customers: CustomerOption[];
    taxRates: TaxRateOption[];
    onDone: () => void;
}) {
    const editing = quote !== null;
    const defaultTaxRate =
        taxRates.find((taxRate) => taxRate.is_default) ?? taxRates[0] ?? null;
    const form = useForm<QuoteFormData>(
        editing && quote
            ? toFormData(quote)
            : emptyForm(defaultTaxRate ? String(defaultTaxRate.id) : ''),
    );

    const selectCustomer = (value: string) => {
        const customer = customers.find((c) => c.id === Number(value));

        form.setData({
            customer_id: value,
            customer_name: customer?.name ?? '',
            customer_email: customer?.email ?? '',
        });
    };

    const addItem = () => {
        form.setData('items', [
            ...form.data.items,
            { product_id: '', quantity: '1', unit_price: '' },
        ]);
    };

    const updateItem = (
        index: number,
        field: keyof QuoteItemForm,
        value: string,
    ) => {
        form.setData(
            'items',
            form.data.items.map((item, i) => {
                if (i !== index) {
                    return item;
                }

                const next = { ...item, [field]: value };

                // Prix proposé automatiquement au choix du produit.
                if (field === 'product_id') {
                    const product = products.find(
                        (candidate) => candidate.id === Number(value),
                    );

                    if (product) {
                        next.unit_price = String(product.sale_price);
                    }
                }

                return next;
            }),
        );
    };

    const removeItem = (index: number) => {
        form.setData(
            'items',
            form.data.items.filter((_, i) => i !== index),
        );
    };
    const subtotal = form.data.items.reduce(
        (sum, item) =>
            sum + Number(item.quantity || 0) * Number(item.unit_price || 0),
        0,
    );

    const discountPercent = Number(form.data.discount_percent) || 0;
    const discountAmount = (subtotal * discountPercent) / 100;
    const total = subtotal - discountAmount;

    const selectedTaxRate =
        taxRates.find(
            (taxRate) => taxRate.id === Number(form.data.tax_rate_id),
        ) ?? null;
    const taxPercent = selectedTaxRate?.rate ?? 0;
    // TVA incluse : extraite du total toutes taxes comprises.
    const taxAmount =
        taxPercent > 0
            ? Math.round((total - total / (1 + taxPercent / 100)) * 100) / 100
            : 0;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing && quote) {
            form.patch(stock.quotes.update(quote.id).url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(stock.quotes.store().url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form id="quote-form" onSubmit={submit} className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2 sm:col-span-2">
                    <Label>Client (carnet d’adresses)</Label>
                    <Select
                        value={form.data.customer_id || 'none'}
                        onValueChange={(v) =>
                            selectCustomer(v === 'none' ? '' : v)
                        }
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
                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="customer_name">Nom du client</Label>
                    <Input
                        id="customer_name"
                        value={form.data.customer_name}
                        onChange={(e) => {
                            form.setData('customer_name', e.target.value);
                            form.setData('customer_id', '');
                        }}
                        placeholder="Nom du client"
                        required
                    />
                    <InputError message={form.errors.customer_name} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="customer_email">E-mail</Label>
                    <Input
                        id="customer_email"
                        type="email"
                        value={form.data.customer_email}
                        onChange={(e) =>
                            form.setData('customer_email', e.target.value)
                        }
                        placeholder="Optionnel"
                    />
                    <InputError message={form.errors.customer_email} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="customer_phone">Téléphone</Label>
                    <Input
                        id="customer_phone"
                        value={form.data.customer_phone}
                        onChange={(e) =>
                            form.setData('customer_phone', e.target.value)
                        }
                        placeholder="Optionnel"
                    />
                    <InputError message={form.errors.customer_phone} />
                </div>
                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="customer_address">Adresse</Label>
                    <Input
                        id="customer_address"
                        value={form.data.customer_address}
                        onChange={(e) =>
                            form.setData('customer_address', e.target.value)
                        }
                        placeholder="Optionnel"
                    />
                    <InputError message={form.errors.customer_address} />
                </div>{' '}
                <div className="space-y-2">
                    <Label htmlFor="valid_until">Valide jusqu’au</Label>
                    <Input
                        id="valid_until"
                        type="date"
                        value={form.data.valid_until}
                        onChange={(e) =>
                            form.setData('valid_until', e.target.value)
                        }
                    />
                    <InputError message={form.errors.valid_until} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="quote-discount">Remise (%)</Label>
                    <Input
                        id="quote-discount"
                        type="number"
                        min="0"
                        max="100"
                        step="0.5"
                        value={form.data.discount_percent}
                        onChange={(e) =>
                            form.setData('discount_percent', e.target.value)
                        }
                    />
                    <InputError message={form.errors.discount_percent} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="quote-tax">TVA</Label>
                    <Select
                        value={form.data.tax_rate_id || 'none'}
                        onValueChange={(v) =>
                            form.setData('tax_rate_id', v === 'none' ? '' : v)
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Sans TVA</SelectItem>
                            {taxRates.map((taxRate) => (
                                <SelectItem
                                    key={taxRate.id}
                                    value={String(taxRate.id)}
                                >
                                    {taxRate.name} ({taxRate.rate}%)
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.tax_rate_id} />
                </div>
                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="quote-notes">Remarque</Label>
                    <Input
                        id="quote-notes"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        placeholder="Optionnel"
                    />
                    <InputError message={form.errors.notes} />
                </div>
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <Label>Articles</Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addItem}
                    >
                        <Plus />
                        Ajouter un article
                    </Button>
                </div>

                {form.data.items.length === 0 && (
                    <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                        Aucun article pour le moment.
                    </p>
                )}

                <div className="space-y-2">
                    {form.data.items.map((item, index) => (
                        <div
                            key={index}
                            className="grid grid-cols-12 items-end gap-2 rounded-lg border p-2"
                        >
                            <div className="col-span-6 space-y-1.5 sm:col-span-5">
                                <Label className="text-xs">Produit</Label>
                                <Select
                                    value={item.product_id || 'none'}
                                    onValueChange={(v) =>
                                        updateItem(
                                            index,
                                            'product_id',
                                            v === 'none' ? '' : v,
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Choisir…" />
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
                                <InputError
                                    message={
                                        form.errors[`items.${index}.product_id`]
                                    }
                                />
                            </div>
                            <div className="col-span-2 space-y-1.5">
                                <Label className="text-xs">Qté</Label>
                                <Input
                                    type="number"
                                    min="1"
                                    value={item.quantity}
                                    onChange={(e) =>
                                        updateItem(
                                            index,
                                            'quantity',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="col-span-3 space-y-1.5">
                                <Label className="text-xs">Prix unitaire</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={item.unit_price}
                                    onChange={(e) =>
                                        updateItem(
                                            index,
                                            'unit_price',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="col-span-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-9 text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                    onClick={() => removeItem(index)}
                                    aria-label="Retirer l’article"
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>

                {form.errors.items && (
                    <InputError message={form.errors.items} />
                )}

                <div className="flex flex-col items-end gap-1 border-t pt-3 text-sm">
                    <p className="flex items-center gap-3 text-muted-foreground">
                        Sous-total
                        <span className="text-foreground tabular-nums">
                            {formatCurrency(subtotal)}
                        </span>
                    </p>
                    {discountAmount > 0 && (
                        <p className="flex items-center gap-3 text-muted-foreground">
                            Remise ({discountPercent}%)
                            <span className="text-red-600 tabular-nums dark:text-red-400">
                                − {formatCurrency(discountAmount)}
                            </span>
                        </p>
                    )}
                    {taxPercent > 0 && (
                        <p className="flex items-center gap-3 text-muted-foreground">
                            TVA ({selectedTaxRate?.name} · {taxPercent}%)
                            <span className="tabular-nums">
                                {formatCurrency(taxAmount)}
                            </span>
                        </p>
                    )}
                    <p className="flex items-center gap-3 font-semibold">
                        Total TTC
                        <span className="tabular-nums">
                            {formatCurrency(total)}
                        </span>
                    </p>
                    {taxPercent > 0 && (
                        <p className="text-xs text-muted-foreground">
                            Montant TTC, dont {formatCurrency(taxAmount)} de TVA
                            incluse.
                        </p>
                    )}
                </div>
            </div>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {editing
                        ? 'Enregistrer les modifications'
                        : 'Créer le devis'}
                </Button>
            </DialogFooter>
        </form>
    );
}

function PaginationBar({ pagination }: { pagination: Paginated<Quote> }) {
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
                {formatNumber(pagination.total)} devis — page{' '}
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

export default function Quotes({
    quotes,
    products,
    customers,
    tax_rates,
    filters,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const can = {
        create: permissions.includes('quotes.create'),
        update: permissions.includes('quotes.update'),
        delete: permissions.includes('quotes.delete'),
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingQuote, setEditingQuote] = useState<Quote | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Quote | null>(null);
    const [convertTarget, setConvertTarget] = useState<Quote | null>(null);

    const convertForm = useForm<{
        payment_method: PaymentMethod;
        mobile_phone: string;
        mobile_reference: string;
    }>({
        payment_method: 'transfer',
        mobile_phone: '',
        mobile_reference: '',
    });

    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        const timer = setTimeout(() => {
            const query: Record<string, string> = {};

            if (search.trim()) {
                query.search = search.trim();
            }

            if (status) {
                query.status = status;
            }

            router.get(
                stock.quotes.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['quotes', 'filters'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, status]);

    const openCreate = () => {
        setEditingQuote(null);
        setDialogOpen(true);
    };

    const openEdit = (quote: Quote) => {
        setEditingQuote(quote);
        setDialogOpen(true);
    };

    const changeStatus = (quote: Quote, next: QuoteStatus) => {
        router.patch(
            stock.quotes.status(quote.id).url,
            { status: next },
            {
                preserveScroll: true,
            },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(stock.quotes.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const confirmConvert = (event: FormEvent) => {
        event.preventDefault();

        if (!convertTarget) {
            return;
        }

        convertForm.post(stock.quotes.convert(convertTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setConvertTarget(null),
        });
    };

    return (
        <>
            <Head title="Devis" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Devis
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Proposez des prix à vos clients et convertissez les
                            devis acceptés en ventes.
                        </p>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate} size="sm">
                            <Plus />
                            Nouveau devis
                        </Button>
                    )}
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher par référence ou client…"
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={status || 'none'}
                        onValueChange={(v) => setStatus(v === 'none' ? '' : v)}
                    >
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="Tous les statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Tous les statuts
                            </SelectItem>
                            {(
                                Object.entries(QUOTE_STATUS_LABELS) as [
                                    QuoteStatus,
                                    string,
                                ][]
                            ).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {(search.trim() || status) && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                setStatus('');
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
                                <TableHead>Date</TableHead>
                                <TableHead>Valide jusqu’au</TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead>Statut</TableHead>
                                {(can.update || can.delete) && (
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {quotes.data.map((quote) => (
                                <TableRow key={quote.id}>
                                    <TableCell className="font-medium">
                                        {quote.reference}
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">
                                            {quote.customer_name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {quote.customer_phone ??
                                                quote.customer_email ??
                                                ''}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(quote.created_at)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateOnly(quote.valid_until)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {formatCurrency(quote.total)}
                                        {quote.tax_percent > 0 && (
                                            <div className="text-xs font-normal text-muted-foreground">
                                                dont TVA{' '}
                                                {formatCurrency(
                                                    quote.tax_amount,
                                                )}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={
                                                statusBadgeClass[quote.status]
                                            }
                                        >
                                            {QUOTE_STATUS_LABELS[quote.status]}
                                        </Badge>
                                    </TableCell>
                                    {(can.update || can.delete) && (
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {can.update &&
                                                    quote.status ===
                                                        'accepted' && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                setConvertTarget(
                                                                    quote,
                                                                )
                                                            }
                                                        >
                                                            <Check />
                                                            Convertir en vente
                                                        </Button>
                                                    )}
                                                {can.update &&
                                                    quote.status ===
                                                        'draft' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                changeStatus(
                                                                    quote,
                                                                    'sent',
                                                                )
                                                            }
                                                            aria-label={`Envoyer le devis ${quote.reference}`}
                                                        >
                                                            <Send />
                                                        </Button>
                                                    )}
                                                {can.update &&
                                                    quote.status === 'sent' && (
                                                        <>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-emerald-600 hover:bg-emerald-500/10 hover:text-emerald-600 dark:text-emerald-400"
                                                                onClick={() =>
                                                                    changeStatus(
                                                                        quote,
                                                                        'accepted',
                                                                    )
                                                                }
                                                                aria-label={`Accepter le devis ${quote.reference}`}
                                                            >
                                                                <Check />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                                onClick={() =>
                                                                    changeStatus(
                                                                        quote,
                                                                        'rejected',
                                                                    )
                                                                }
                                                                aria-label={`Refuser le devis ${quote.reference}`}
                                                            >
                                                                <X />
                                                            </Button>
                                                        </>
                                                    )}
                                                {can.update &&
                                                    (quote.status ===
                                                        'rejected' ||
                                                        quote.status ===
                                                            'accepted') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                changeStatus(
                                                                    quote,
                                                                    'sent',
                                                                )
                                                            }
                                                            aria-label={`Renvoyer le devis ${quote.reference}`}
                                                        >
                                                            <Send />
                                                        </Button>
                                                    )}
                                                {can.update && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEdit(quote)
                                                        }
                                                        aria-label={`Modifier le devis ${quote.reference}`}
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                )}
                                                {can.delete && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                        onClick={() =>
                                                            setDeleteTarget(
                                                                quote,
                                                            )
                                                        }
                                                        aria-label={`Supprimer le devis ${quote.reference}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {quotes.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <FileText className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun devis
                                            </p>
                                            <p className="text-xs">
                                                {search.trim() || status
                                                    ? 'Essayez de modifier vos filtres.'
                                                    : 'Créez votre premier devis pour proposer un prix à un client.'}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar pagination={quotes} />
            </div>

            {/* Create / edit dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingQuote
                                ? `Modifier le devis ${editingQuote.reference}`
                                : 'Nouveau devis'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingQuote
                                ? 'Mise à jour des informations et des articles du devis.'
                                : 'Composez le devis avec les produits du catalogue.'}
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <QuoteForm
                            key={editingQuote?.id ?? 'new'}
                            quote={editingQuote}
                            products={products}
                            customers={customers}
                            taxRates={tax_rates}
                            onDone={() => setDialogOpen(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            {/* Convert dialog */}
            <Dialog
                open={convertTarget !== null}
                onOpenChange={(open) => !open && setConvertTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Convertir {convertTarget?.reference} en vente
                        </DialogTitle>
                        <DialogDescription>
                            Le stock sera décrémenté, une vente sera créée et
                            une facture émise au nom de{' '}
                            {convertTarget?.customer_name}.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        id="convert-quote-form"
                        onSubmit={confirmConvert}
                        className="grid gap-4"
                    >
                        <div className="space-y-2">
                            <Label>Mode de paiement</Label>
                            <Select
                                value={convertForm.data.payment_method}
                                onValueChange={(v) =>
                                    convertForm.setData(
                                        'payment_method',
                                        v as PaymentMethod,
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {(
                                        Object.entries(
                                            PAYMENT_METHOD_LABELS,
                                        ) as [PaymentMethod, string][]
                                    ).map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={convertForm.errors.payment_method}
                            />
                        </div>
                        {isMobilePayment(convertForm.data.payment_method) && (
                            <div className="space-y-2 rounded-xl border bg-muted/30 p-3">
                                <div className="space-y-2">
                                    <Label htmlFor="convert-mobile-phone">
                                        Numéro{' '}
                                        {convertForm.data.payment_method ===
                                        'tmoney'
                                            ? 'T-Money'
                                            : 'Flooz'}{' '}
                                        du client
                                    </Label>
                                    <Input
                                        id="convert-mobile-phone"
                                        type="tel"
                                        value={convertForm.data.mobile_phone}
                                        onChange={(e) =>
                                            convertForm.setData(
                                                'mobile_phone',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="+228 90 00 00 00"
                                        required
                                    />
                                    <InputError
                                        message={
                                            convertForm.errors.mobile_phone
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="convert-mobile-ref">
                                        Référence de transaction
                                    </Label>
                                    <Input
                                        id="convert-mobile-ref"
                                        value={
                                            convertForm.data.mobile_reference
                                        }
                                        onChange={(e) =>
                                            convertForm.setData(
                                                'mobile_reference',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Optionnel"
                                    />
                                    <InputError
                                        message={
                                            convertForm.errors.mobile_reference
                                        }
                                    />
                                </div>
                            </div>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setConvertTarget(null)}
                            >
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                disabled={convertForm.processing}
                            >
                                Convertir en vente
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete confirmation */}
            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Supprimer le devis</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer le devis{' '}
                            {deleteTarget?.reference} ? Cette action est
                            irréversible.
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

Quotes.layout = {
    breadcrumbs: [
        {
            title: 'Devis',
            href: stock.quotes.index().url,
        },
    ],
};
