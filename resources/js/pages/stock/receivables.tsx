import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownToLine,
    Contact,
    Download,
    FileText,
    HandCoins,
    Search,
    Wallet,
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
    CREDIT_STATUS_LABELS,
    formatCurrency,
    formatDate,
    formatDateOnly,
    formatNumber,
    isMobilePayment,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type {
    Auth,
    CustomerDetail,
    Paginated,
    PaymentMethod,
    ReceivableCustomer,
    ReceivableSale,
    ReceivableStats,
} from '@/types';

type Props = {
    customers: Paginated<ReceivableCustomer>;
    stats: ReceivableStats;
    filters: {
        status: string | null;
        search: string;
    };
    customer_detail?: CustomerDetail | null;
};

function CreditBadge({ status }: { status: ReceivableSale['credit_status'] }) {
    const styles: Record<ReceivableSale['credit_status'], string> = {
        paid: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        outstanding:
            'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
        overdue:
            'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
    };

    return (
        <Badge variant="outline" className={styles[status]}>
            {CREDIT_STATUS_LABELS[status]}
        </Badge>
    );
}

function StatCard({
    icon,
    label,
    value,
    hint,
    tone = 'default',
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    hint?: string;
    tone?: 'default' | 'danger';
}) {
    return (
        <div className="flex items-start gap-3 rounded-xl border p-4">
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-full',
                    tone === 'danger'
                        ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                        : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                )}
            >
                {icon}
            </span>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p
                    className={cn(
                        'text-lg font-semibold tabular-nums',
                        tone === 'danger' && 'text-red-600 dark:text-red-400',
                    )}
                >
                    {value}
                </p>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </div>
        </div>
    );
}

function PaymentDialog({
    sale,
    onClose,
}: {
    sale: ReceivableSale | null;
    onClose: () => void;
}) {
    const form = useForm<{
        sale_id: number | null;
        amount: string;
        payment_method: PaymentMethod;
        mobile_phone: string;
        mobile_reference: string;
        notes: string;
    }>({
        sale_id: null,
        amount: '',
        payment_method: 'cash',
        mobile_phone: '',
        mobile_reference: '',
        notes: '',
    });

    const [printReceipt, setPrintReceipt] = useState(false);
    const printWindowRef = useRef<Window | null>(null);

    // Ouvre l'aperçu du reçu dans l'onglet ouvert à la soumission dès que
    // l'identifiant du paiement est transmis par le serveur.
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const receiptId = flash?.payment_receipt;

            if (
                typeof receiptId === 'number' &&
                printWindowRef.current !== null
            ) {
                printWindowRef.current.location.href =
                    stock.receivables.payments.receipt(receiptId).url;
                printWindowRef.current = null;
            }
        });
    }, []);

    // Pré-remplit le formulaire quand la vente change.
    useEffect(() => {
        if (sale) {
            form.setData({
                sale_id: sale.id,
                amount: String(sale.balance),
                payment_method: 'cash',
                mobile_phone: '',
                mobile_reference: '',
                notes: '',
            });
            setPrintReceipt(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sale?.id]);

    if (!sale) {
        return null;
    }

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Ouvert de façon synchrone pour contourner le bloqueur de popups ;
        // le reçu y sera chargé une fois le paiement enregistré.
        if (printReceipt) {
            printWindowRef.current = window.open('', '_blank');
        }

        form.post(stock.receivables.payments.store().url, {
            preserveScroll: true,
            onError: () => {
                printWindowRef.current?.close();
                printWindowRef.current = null;
            },
            onSuccess: () => {
                onClose();
                router.reload({
                    only: ['customer_detail', 'customers', 'stats'],
                });
            },
        });
    };

    return (
        <Dialog
            open={sale !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Encaisser un paiement</DialogTitle>
                    <DialogDescription>
                        Vente {sale.reference} — solde restant{' '}
                        {formatCurrency(sale.balance)}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="payment-amount">Montant encaissé</Label>
                        <Input
                            id="payment-amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            max={sale.balance}
                            value={form.data.amount}
                            onChange={(e) =>
                                form.setData('amount', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.amount} />
                    </div>

                    <div className="space-y-2">
                        <Label>Mode de paiement</Label>
                        <Select
                            value={form.data.payment_method}
                            onValueChange={(v) =>
                                form.setData(
                                    'payment_method',
                                    v as PaymentMethod,
                                )
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">Espèces</SelectItem>
                                <SelectItem value="tmoney">T-Money</SelectItem>
                                <SelectItem value="flooz">Flooz</SelectItem>
                                <SelectItem value="transfer">
                                    Virement
                                </SelectItem>
                                <SelectItem value="card">
                                    Carte bancaire
                                </SelectItem>
                                <SelectItem value="avoir">Avoir</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.payment_method} />
                    </div>

                    {isMobilePayment(form.data.payment_method) && (
                        <div className="space-y-2 rounded-xl border bg-muted/30 p-3">
                            <div className="space-y-2">
                                <Label htmlFor="payment-mobile-phone">
                                    Numéro mobile
                                </Label>
                                <Input
                                    id="payment-mobile-phone"
                                    type="tel"
                                    value={form.data.mobile_phone}
                                    onChange={(e) =>
                                        form.setData(
                                            'mobile_phone',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="+228 90 00 00 00"
                                    required
                                />
                                <InputError
                                    message={form.errors.mobile_phone}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="payment-mobile-reference">
                                    Référence de transaction
                                </Label>
                                <Input
                                    id="payment-mobile-reference"
                                    value={form.data.mobile_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'mobile_reference',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Optionnel"
                                />
                                <InputError
                                    message={form.errors.mobile_reference}
                                />
                            </div>
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="payment-notes">Remarque</Label>
                        <Input
                            id="payment-notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            placeholder="Optionnel"
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <label className="flex cursor-pointer items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="size-4 rounded border-input"
                            checked={printReceipt}
                            onChange={(e) => setPrintReceipt(e.target.checked)}
                        />
                        Imprimer le reçu d’encaissement
                    </label>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Annuler
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Encaisser {formatCurrency(Number(form.data.amount))}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CustomerDetailDialog({
    customer,
    canManage,
    onClose,
    onPay,
}: {
    customer: CustomerDetail | null;
    canManage: boolean;
    onClose: () => void;
    onPay: (sale: ReceivableSale) => void;
}) {
    return (
        <Dialog
            open={customer !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                {customer && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Contact className="size-5 text-emerald-600 dark:text-emerald-400" />
                                {customer.name}
                            </DialogTitle>
                            <DialogDescription>
                                Solde restant{' '}
                                <span className="font-semibold">
                                    {formatCurrency(customer.balance)}
                                </span>
                                {customer.overdue_balance > 0 && (
                                    <span className="text-red-600 dark:text-red-400">
                                        {' '}
                                        dont{' '}
                                        {formatCurrency(
                                            customer.overdue_balance,
                                        )}{' '}
                                        en retard
                                    </span>
                                )}
                                {customer.available_avoirs > 0 && (
                                    <span>
                                        {' '}
                                        ·{' '}
                                        {formatCurrency(
                                            customer.available_avoirs,
                                        )}{' '}
                                        d’avoirs disponibles
                                    </span>
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            {customer.sales.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Aucune vente à crédit pour ce client.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {customer.sales.map((sale) => (
                                        <div
                                            key={sale.id}
                                            className="rounded-xl border p-4"
                                        >
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p className="text-sm font-semibold">
                                                        {sale.reference}
                                                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                            {formatDate(
                                                                sale.created_at,
                                                            )}
                                                        </span>
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Échéance{' '}
                                                        {formatDateOnly(
                                                            sale.credit_due_at,
                                                        )}{' '}
                                                        ·{' '}
                                                        {formatNumber(
                                                            sale.items.length,
                                                        )}{' '}
                                                        article
                                                        {sale.items.length > 1
                                                            ? 's'
                                                            : ''}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <CreditBadge
                                                        status={
                                                            sale.credit_status
                                                        }
                                                    />
                                                    {canManage &&
                                                        sale.balance > 0.01 && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    onPay(sale)
                                                                }
                                                            >
                                                                <ArrowDownToLine className="size-3.5" />
                                                                Encaisser
                                                            </Button>
                                                        )}
                                                </div>
                                            </div>

                                            <div className="mt-3 grid grid-cols-3 gap-2 text-sm">
                                                <p className="rounded-lg bg-muted/40 p-2 text-center">
                                                    <span className="block text-xs text-muted-foreground">
                                                        Total
                                                    </span>
                                                    <span className="font-semibold tabular-nums">
                                                        {formatCurrency(
                                                            sale.total,
                                                        )}
                                                    </span>
                                                </p>
                                                <p className="rounded-lg bg-muted/40 p-2 text-center">
                                                    <span className="block text-xs text-muted-foreground">
                                                        Encaissé
                                                    </span>
                                                    <span className="font-semibold tabular-nums">
                                                        {formatCurrency(
                                                            sale.paid_amount,
                                                        )}
                                                    </span>
                                                </p>
                                                <p className="rounded-lg bg-muted/40 p-2 text-center">
                                                    <span className="block text-xs text-muted-foreground">
                                                        Solde
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'font-semibold tabular-nums',
                                                            sale.balance >
                                                                0.01 &&
                                                                'text-red-600 dark:text-red-400',
                                                        )}
                                                    >
                                                        {formatCurrency(
                                                            sale.balance,
                                                        )}
                                                    </span>
                                                </p>
                                            </div>

                                            {sale.payments.length > 0 && (
                                                <div className="mt-3 space-y-1 border-t pt-2">
                                                    <p className="text-xs font-medium text-muted-foreground">
                                                        Encaissements
                                                    </p>
                                                    {sale.payments.map(
                                                        (payment) => (
                                                            <p
                                                                key={payment.id}
                                                                className="flex items-center justify-between text-sm"
                                                            >
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        payment.payment_label
                                                                    }
                                                                    <span className="ml-2 text-xs">
                                                                        {formatDate(
                                                                            payment.paid_at,
                                                                        )}
                                                                    </span>
                                                                    {payment.notes && (
                                                                        <span className="ml-2 text-xs">
                                                                            ·{' '}
                                                                            {
                                                                                payment.notes
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                <span className="font-medium tabular-nums">
                                                                    {formatCurrency(
                                                                        payment.amount,
                                                                    )}
                                                                </span>
                                                            </p>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {customer.credit_notes.length > 0 && (
                                <div className="rounded-xl border p-4">
                                    <p className="text-sm font-semibold">
                                        Avoirs
                                    </p>
                                    <div className="mt-2 space-y-1.5">
                                        {customer.credit_notes.map((note) => (
                                            <p
                                                key={note.id}
                                                className="flex items-center justify-between text-sm"
                                            >
                                                <span>
                                                    {note.reference}
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        {formatDateOnly(
                                                            note.issued_at,
                                                        )}
                                                    </span>
                                                </span>
                                                <span className="tabular-nums">
                                                    {note.remaining > 0 ? (
                                                        <span className="font-medium">
                                                            {formatCurrency(
                                                                note.remaining,
                                                            )}{' '}
                                                            restant
                                                        </span>
                                                    ) : (
                                                        <Badge
                                                            variant="secondary"
                                                            className="tabular-nums"
                                                        >
                                                            Utilisé
                                                        </Badge>
                                                    )}
                                                </span>
                                            </p>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function PaginationBar({
    pagination,
}: {
    pagination: Paginated<ReceivableCustomer>;
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
                {formatNumber(pagination.total)} client
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

export default function Receivables({
    customers,
    stats,
    filters,
    customer_detail,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canManage = permissions.includes('receivables.manage');

    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [detailOpen, setDetailOpen] = useState(false);
    const [paymentSale, setPaymentSale] = useState<ReceivableSale | null>(null);

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
                stock.receivables.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['customers', 'stats', 'filters'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, status]);

    const exportQuery: Record<string, string> = {};

    if (search.trim()) {
        exportQuery.search = search.trim();
    }

    if (status) {
        exportQuery.status = status;
    }

    const openDetail = (customerId: number) => {
        router.get(
            stock.receivables.customer(customerId).url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['customer_detail'],
                onSuccess: () => setDetailOpen(true),
            },
        );
    };

    return (
        <>
            <Head title="Créances clients" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Créances clients
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Ventes à crédit, soldes impayés et relances des
                            retardataires.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {canManage && (
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={
                                        stock.receivables.export({
                                            query: exportQuery,
                                        }).url
                                    }
                                    download
                                >
                                    <Download />
                                    CSV
                                </a>
                            </Button>
                        )}
                        <Button size="sm" asChild>
                            <a href={stock.sales.pos().url}>Vendre à crédit</a>
                        </Button>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={<HandCoins className="size-5" />}
                        label="Créances totales"
                        value={formatCurrency(stats.total_outstanding)}
                        hint={`${formatNumber(stats.customers_with_balance)} client${stats.customers_with_balance > 1 ? 's' : ''} à solde impayé`}
                    />
                    <StatCard
                        icon={<AlertTriangle className="size-5" />}
                        label="En retard"
                        value={formatCurrency(stats.overdue_total)}
                        hint={`${formatNumber(stats.overdue_sales)} vente${stats.overdue_sales > 1 ? 's' : ''} échue${stats.overdue_sales > 1 ? 's' : ''}`}
                        tone="danger"
                    />
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="relative max-w-md flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher par nom ou téléphone…"
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={status || 'none'}
                        onValueChange={(v) => setStatus(v === 'none' ? '' : v)}
                    >
                        <SelectTrigger className="h-9 w-full sm:w-48">
                            <SelectValue placeholder="Tous les clients" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Tous les clients
                            </SelectItem>
                            <SelectItem value="outstanding">
                                À solde impayé
                            </SelectItem>
                            <SelectItem value="overdue">En retard</SelectItem>
                            <SelectItem value="settled">Soldés</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Client</TableHead>
                                <TableHead className="text-right">
                                    Ventes
                                </TableHead>
                                <TableHead className="text-right">
                                    Total dû
                                </TableHead>
                                <TableHead className="text-right">
                                    Encaissé
                                </TableHead>
                                <TableHead className="text-right">
                                    Solde
                                </TableHead>
                                <TableHead className="text-right">
                                    En retard
                                </TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customers.data.map((customer) => (
                                <TableRow key={customer.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                                <Contact className="size-4" />
                                            </span>
                                            <div>
                                                <p className="font-medium">
                                                    {customer.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {customer.phone ??
                                                        customer.email ??
                                                        '—'}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(customer.sales_count)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatCurrency(customer.total_due)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatCurrency(customer.total_paid)}
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'text-right font-medium tabular-nums',
                                            customer.balance > 0.01 &&
                                                'text-amber-600 dark:text-amber-400',
                                        )}
                                    >
                                        {formatCurrency(customer.balance)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {customer.overdue_balance > 0.01 ? (
                                            <Badge
                                                variant="outline"
                                                className="border-red-500/20 bg-red-500/10 text-red-600 tabular-nums dark:text-red-400"
                                            >
                                                {formatCurrency(
                                                    customer.overdue_balance,
                                                )}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {canManage && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                    aria-label={`Relevé de compte de ${customer.name}`}
                                                >
                                                    <a
                                                        href={
                                                            stock.receivables.relance(
                                                                customer.id,
                                                            ).url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <FileText />
                                                    </a>
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    openDetail(customer.id)
                                                }
                                                aria-label={`Voir le détail de ${customer.name}`}
                                            >
                                                <Wallet />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {customers.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <HandCoins className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun client à crédit
                                            </p>
                                            <p className="text-xs">
                                                {search || status
                                                    ? 'Essayez de modifier vos filtres.'
                                                    : 'Les ventes à crédit apparaîtront ici.'}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar pagination={customers} />
            </div>

            <CustomerDetailDialog
                customer={detailOpen ? (customer_detail ?? null) : null}
                canManage={canManage}
                onClose={() => setDetailOpen(false)}
                onPay={setPaymentSale}
            />

            <PaymentDialog
                sale={paymentSale}
                onClose={() => setPaymentSale(null)}
            />
        </>
    );
}

Receivables.layout = {
    breadcrumbs: [
        {
            title: 'Créances',
            href: stock.receivables.index().url,
        },
    ],
};
