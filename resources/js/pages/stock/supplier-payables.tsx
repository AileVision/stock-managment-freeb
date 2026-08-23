import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Download,
    HandCoins,
    Search,
    Truck,
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
    Paginated,
    PaymentMethod,
    SupplierPayableDetail,
    SupplierPayableOrder,
    SupplierPayableStats,
    SupplierPayableSupplier,
} from '@/types';

type Props = {
    suppliers: Paginated<SupplierPayableSupplier>;
    stats: SupplierPayableStats;
    filters: {
        status: string | null;
        search: string;
    };
    supplier_detail?: SupplierPayableDetail | null;
};

const SUPPLIER_PAYMENT_LABELS: Record<SupplierPayableOrder['payment_status'], string> = {
    paid: 'Soldé',
    partial: 'Acompte versé',
    unpaid: 'Impayé',
};

function PaymentBadge({ status }: { status: SupplierPayableOrder['payment_status'] }) {
    const styles: Record<SupplierPayableOrder['payment_status'], string> = {
        paid: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        partial:
            'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
        unpaid:
            'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
    };

    return (
        <Badge variant="outline" className={cn('text-xs', styles[status])}>
            {SUPPLIER_PAYMENT_LABELS[status]}
        </Badge>
    );
}

function StatCard({
    icon,
    label,
    value,
    tone = 'default',
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    tone?: 'default' | 'danger' | 'accent';
}) {
    const tones: Record<string, string> = {
        default: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        danger: 'bg-red-500/10 text-red-600 dark:text-red-400',
        accent: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    };

    return (
        <div className="flex items-start gap-3 rounded-xl border p-4">
            <span
                className={`flex size-10 shrink-0 items-center justify-center rounded-full ${tones[tone]}`}
            >
                {icon}
            </span>
            <div>
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="text-lg font-semibold tabular-nums">{value}</p>
            </div>
        </div>
    );
}

function PaginationBar({ pagination }: { pagination: Paginated<SupplierPayableSupplier> }) {
    const go = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true });
    };

    return (
        <div className="flex items-center justify-between px-2 py-4 text-sm text-muted-foreground">
            <span>
                {pagination.total} résultat{pagination.total > 1 ? 's' : ''}
            </span>
            <div className="flex gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!pagination.prev_page_url}
                    onClick={() => go(pagination.prev_page_url)}
                >
                    ← Précédent
                </Button>
                <span className="flex items-center px-3 text-xs">
                    {pagination.current_page}/{pagination.last_page}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!pagination.next_page_url}
                    onClick={() => go(pagination.next_page_url)}
                >
                    Suivant →
                </Button>
            </div>
        </div>
    );
}

export default function SupplierPayables({ suppliers, stats, filters, supplier_detail }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const permissions = auth.permissions;

    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? 'all');
    const searchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [paymentDialogOpen, setPaymentDialogOpen] = useState(false);
    const [selectedOrder, setSelectedOrder] = useState<SupplierPayableOrder | null>(null);

    const form = useForm<{
        purchase_order_id: number | null;
        amount: string;
        payment_method: PaymentMethod;
        mobile_phone: string;
        mobile_reference: string;
        reference: string;
        notes: string;
    }>({
        purchase_order_id: null,
        amount: '',
        payment_method: 'cash',
        mobile_phone: '',
        mobile_reference: '',
        reference: '',
        notes: '',
    });

    const applyFilters = (status: string) => {
        const query: Record<string, string> = {};
        if (search) query.search = search;
        if (status && status !== 'all') query.status = status;
        router.get(stock.supplierPayables.index({ query }).url, {}, { preserveState: true });
    };

    useEffect(() => {
        if (searchTimeout.current) clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(() => {
            applyFilters(statusFilter);
        }, 400);
        return () => {
            if (searchTimeout.current) clearTimeout(searchTimeout.current);
        };
    }, [search]);

    const openPayment = (order: SupplierPayableOrder) => {
        setSelectedOrder(order);
        form.setData({
            purchase_order_id: order.id,
            amount: String(order.balance),
            payment_method: 'cash',
            mobile_phone: '',
            mobile_reference: '',
            reference: '',
            notes: '',
        });
        setPaymentDialogOpen(true);
    };

    const submitPayment = (e: FormEvent) => {
        e.preventDefault();
        form.post(stock.supplierPayables.payments.store().url, {
            onSuccess: () => {
                setPaymentDialogOpen(false);
                setSelectedOrder(null);
            },
        });
    };

    const detail = supplier_detail;

    return (
        <Head title="Dettes fournisseurs">
            <div className="flex flex-col gap-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Dettes fournisseurs</h1>
                        <p className="text-sm text-muted-foreground">
                            Suivi des paiements et échéances fournisseurs
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            const params: Record<string, string> = {};
                            if (filters.status) params.status = filters.status;
                            if (filters.search) params.search = filters.search;
                            window.location.href = stock.supplierPayables.export({ query: params }).url;
                        }}
                    >
                        <Download className="mr-2 size-4" />
                        Export CSV
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        icon={<Wallet className="size-5" />}
                        label="Solde total dû"
                        value={formatCurrency(stats.total_outstanding)}
                    />
                    <StatCard
                        icon={<Truck className="size-5" />}
                        label="Fournisseurs avec solde"
                        value={formatNumber(stats.suppliers_with_balance)}
                    />
                    <StatCard
                        icon={<AlertTriangle className="size-5" />}
                        label="En retard"
                        value={formatCurrency(stats.overdue_total)}
                        tone="danger"
                    />
                    <StatCard
                        icon={<HandCoins className="size-5" />}
                        label="Commandes en retard"
                        value={formatNumber(stats.overdue_orders)}
                        tone="accent"
                    />
                </div>

                {/* Filters */}
                <div className="flex items-center gap-3">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Rechercher un fournisseur…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={statusFilter}
                        onValueChange={(v) => {
                            setStatusFilter(v);
                            applyFilters(v);
                        }}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="Tous les statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            <SelectItem value="outstanding">Avec solde</SelectItem>
                            <SelectItem value="overdue">En retard</SelectItem>
                            <SelectItem value="settled">Soldé</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Supplier Detail View */}
                {detail ? (
                    <div className="rounded-xl border">
                        <div className="border-b p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold">{detail.name}</h2>
                                    <p className="text-sm text-muted-foreground">
                                        {[detail.phone, detail.email].filter(Boolean).join(' · ') || '—'}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => router.get(stock.supplierPayables.index().url)}
                                    >
                                        ← Retour
                                    </Button>
                                </div>
                            </div>
                            <div className="mt-3 flex gap-4 text-sm">
                                <span>Solde dû : <strong>{formatCurrency(detail.balance)}</strong></span>
                                <span>Retard : <strong className="text-red-600">{formatCurrency(detail.overdue_balance)}</strong></span>
                            </div>
                        </div>

                        <div className="p-4">
                            {detail.orders.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    Aucune commande enregistrée.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {detail.orders.map((order) => (
                                        <div key={order.id} className="rounded-lg border p-4">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <span className="font-medium">{order.reference}</span>
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        {formatDateOnly(order.created_at)}
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <PaymentBadge status={order.payment_status} />
                                                    {order.due_at && (
                                                        <span className={cn(
                                                            'text-xs',
                                                            order.is_overdue ? 'text-red-600 font-medium' : 'text-muted-foreground',
                                                        )}>
                                                            Échéance : {formatDateOnly(order.due_at)}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="mt-2 flex gap-4 text-sm text-muted-foreground">
                                                <span>Total : {formatCurrency(order.total)}</span>
                                                <span>Payé : {formatCurrency(order.paid_amount)}</span>
                                                <span className={cn(order.balance > 0.01 ? 'text-red-600 font-medium' : 'text-emerald-600')}>
                                                    Reste : {formatCurrency(order.balance)}
                                                </span>
                                            </div>
                                            {order.payments.length > 0 && (
                                                <div className="mt-3">
                                                    <p className="text-xs font-medium text-muted-foreground mb-1">Paiements :</p>
                                                    <div className="space-y-1">
                                                        {order.payments.map((p) => (
                                                            <div key={p.id} className="flex items-center justify-between text-xs">
                                                                <span>{formatDateOnly(p.paid_at)} — {p.payment_label}</span>
                                                                <span className="font-medium">{formatCurrency(p.amount)}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                            {order.balance > 0.01 && permissions.includes('supplier_payables.manage') && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="mt-3"
                                                    onClick={() => openPayment(order)}
                                                >
                                                    <Wallet className="mr-2 size-3" />
                                                    Enregistrer un paiement
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                ) : (
                    /* Supplier List */
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Fournisseur</TableHead>
                                    <TableHead className="text-right">Commandes</TableHead>
                                    <TableHead className="text-right">Total dû</TableHead>
                                    <TableHead className="text-right">Payé</TableHead>
                                    <TableHead className="text-right">Solde</TableHead>
                                    <TableHead className="text-right">Retard</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {suppliers.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="py-12 text-center text-sm text-muted-foreground">
                                            Aucun fournisseur avec des commandes.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    suppliers.data.map((supplier) => (
                                        <TableRow
                                            key={supplier.id}
                                            className="cursor-pointer hover:bg-muted/50"
                                            onClick={() => {
                                                router.get(stock.supplierPayables.supplier(supplier.id).url);
                                            }}
                                        >
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium">{supplier.name}</p>
                                                    <p className="text-xs text-muted-foreground">{supplier.phone ?? supplier.email ?? '—'}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">{formatNumber(supplier.orders_count)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatCurrency(supplier.total_due)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatCurrency(supplier.total_paid)}</TableCell>
                                            <TableCell className={cn('text-right tabular-nums font-medium', supplier.balance > 0.01 ? 'text-red-600' : 'text-emerald-600')}>
                                                {formatCurrency(supplier.balance)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums text-red-600">
                                                {supplier.overdue_balance > 0.01 ? formatCurrency(supplier.overdue_balance) : '—'}
                                            </TableCell>
                                            <TableCell>
                                                {supplier.overdue_orders > 0 && (
                                                    <Badge variant="outline" className="border-red-500/20 bg-red-500/10 text-red-600 text-xs">
                                                        {supplier.overdue_orders}
                                                    </Badge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                        <PaginationBar pagination={suppliers} />
                    </div>
                )}

                {/* Payment Dialog */}
                <Dialog open={paymentDialogOpen} onOpenChange={setPaymentDialogOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Enregistrer un paiement</DialogTitle>
                            <DialogDescription>
                                {selectedOrder && (
                                    <>
                                        Commande <strong>{selectedOrder.reference}</strong> — Solde restant :{' '}
                                        <strong>{formatCurrency(selectedOrder.balance)}</strong>
                                    </>
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submitPayment} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="amount">Montant (FCFA)</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={selectedOrder?.balance}
                                    value={form.data.amount}
                                    onChange={(e) => form.setData('amount', e.target.value)}
                                />
                                <InputError message={form.errors.amount} />
                            </div>
                            <div className="space-y-2">
                                <Label>Mode de paiement</Label>
                                <Select
                                    value={form.data.payment_method}
                                    onValueChange={(v) => form.setData('payment_method', v as PaymentMethod)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="cash">Espèces</SelectItem>
                                        <SelectItem value="card">Carte bancaire</SelectItem>
                                        <SelectItem value="transfer">Virement</SelectItem>
                                        <SelectItem value="tmoney">T-Money</SelectItem>
                                        <SelectItem value="flooz">Flooz</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.payment_method} />
                            </div>
                            {isMobilePayment(form.data.payment_method) && (
                                <div className="space-y-2">
                                    <Label htmlFor="mobile_phone">Téléphone mobile</Label>
                                    <Input
                                        id="mobile_phone"
                                        value={form.data.mobile_phone}
                                        onChange={(e) => form.setData('mobile_phone', e.target.value)}
                                    />
                                    <InputError message={form.errors.mobile_phone} />
                                </div>
                            )}
                            <div className="space-y-2">
                                <Label htmlFor="reference">Référence</Label>
                                <Input
                                    id="reference"
                                    value={form.data.reference}
                                    onChange={(e) => form.setData('reference', e.target.value)}
                                    placeholder="N° chèque, reçu, etc."
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="notes">Notes</Label>
                                <Input
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                />
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setPaymentDialogOpen(false)}>
                                    Annuler
                                </Button>
                                <Button type="submit" disabled={form.processing}>
                                    Enregistrer
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </Head>
    );
}
