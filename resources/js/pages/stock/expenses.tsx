import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Banknote, Download, Plus, Search, Trash2 } from 'lucide-react';
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
    formatNumber,
    isMobilePayment,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type {
    Auth,
    Expense,
    ExpenseCategory,
    ExpenseCategoryLabel,
    ExpenseStats,
    Paginated,
    PaymentMethod,
} from '@/types';

type Props = {
    expenses: Paginated<Expense>;
    stats: ExpenseStats;
    categories: ExpenseCategoryLabel;
    filters: {
        from: string;
        to: string;
        category: string | null;
        search: string;
    };
};

const CATEGORY_COLORS: Record<string, string> = {
    loyer: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    salaires: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    electricite: 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400',
    eau: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
    transport: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    fournitures: 'bg-gray-500/10 text-gray-600 dark:text-gray-400',
    entretien: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    communication: 'bg-teal-500/10 text-teal-600 dark:text-teal-400',
    fiscalite: 'bg-red-500/10 text-red-600 dark:text-red-400',
    divers: 'bg-slate-500/10 text-slate-600 dark:text-slate-400',
};

function StatCard({
    icon,
    label,
    value,
    hint,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="flex items-start gap-3 rounded-xl border p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-500/10 text-red-600 dark:text-red-400">
                {icon}
            </span>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="text-lg font-semibold tabular-nums">{value}</p>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </div>
        </div>
    );
}

function CreateDialog({
    categories,
    onClose,
}: {
    categories: ExpenseCategoryLabel;
    onClose: () => void;
}) {
    const form = useForm<{
        category: ExpenseCategory | '';
        amount: string;
        payment_method: PaymentMethod;
        expense_date: string;
        description: string;
        notes: string;
        mobile_phone: string;
        mobile_reference: string;
    }>({
        category: '',
        amount: '',
        payment_method: 'cash',
        expense_date: new Date().toISOString().slice(0, 10),
        description: '',
        notes: '',
        mobile_phone: '',
        mobile_reference: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(stock.expenses.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                router.reload({ only: ['expenses', 'stats'] });
            },
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nouvelle dépense</DialogTitle>
                    <DialogDescription>
                        Enregistrer une dépense d'exploitation.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="space-y-2">
                        <Label>Catégorie</Label>
                        <Select
                            value={form.data.category}
                            onValueChange={(v) =>
                                form.setData('category', v as ExpenseCategory)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Choisir…" />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(categories).map(
                                    ([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.category} />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="expense-amount">
                                Montant (FCFA)
                            </Label>
                            <Input
                                id="expense-amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={form.data.amount}
                                onChange={(e) =>
                                    form.setData('amount', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.amount} />
                        </div>

                        <div className="space-y-2">
                            <Label>Date</Label>
                            <Input
                                type="date"
                                value={form.data.expense_date}
                                max={new Date().toISOString().slice(0, 10)}
                                onChange={(e) =>
                                    form.setData(
                                        'expense_date',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.expense_date} />
                        </div>
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
                                <SelectItem value="tmoney">
                                    T-Money
                                </SelectItem>
                                <SelectItem value="flooz">Flooz</SelectItem>
                                <SelectItem value="transfer">
                                    Virement
                                </SelectItem>
                                <SelectItem value="card">
                                    Carte bancaire
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.payment_method} />
                    </div>

                    {isMobilePayment(form.data.payment_method) && (
                        <div className="space-y-2 rounded-xl border bg-muted/30 p-3">
                            <div className="space-y-2">
                                <Label htmlFor="exp-mobile-phone">
                                    Numéro mobile
                                </Label>
                                <Input
                                    id="exp-mobile-phone"
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
                                <Label htmlFor="exp-mobile-ref">
                                    Référence transaction
                                </Label>
                                <Input
                                    id="exp-mobile-ref"
                                    value={form.data.mobile_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'mobile_reference',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Optionnel"
                                />
                            </div>
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="exp-description">
                            Description
                        </Label>
                        <Input
                            id="exp-description"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            placeholder="Ex : Loyer août 2026"
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="exp-notes">Remarque</Label>
                        <Input
                            id="exp-notes"
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            placeholder="Optionnel"
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Annuler
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PaginationBar({
    pagination,
}: {
    pagination: Paginated<Expense>;
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
                {formatNumber(pagination.total)} dépense
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

export default function Expenses({
    expenses,
    stats,
    categories,
    filters,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canCreate = permissions.includes('expenses.create');
    const canDelete = permissions.includes('expenses.delete');

    const [search, setSearch] = useState(filters.search ?? '');
    const [category, setCategory] = useState(filters.category ?? '');
    const [dateFrom, setDateFrom] = useState(filters.from ?? '');
    const [dateTo, setDateTo] = useState(filters.to ?? '');
    const [createOpen, setCreateOpen] = useState(false);

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

            if (category) {
                query.category = category;
            }

            if (dateFrom) {
                query.from = dateFrom;
            }

            if (dateTo) {
                query.to = dateTo;
            }

            router.get(
                stock.expenses.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['expenses', 'stats'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, category, dateFrom, dateTo]);

    const exportQuery: Record<string, string> = {};

    if (search.trim()) {
        exportQuery.search = search.trim();
    }

    if (category) {
        exportQuery.category = category;
    }

    if (dateFrom) {
        exportQuery.from = dateFrom;
    }

    if (dateTo) {
        exportQuery.to = dateTo;
    }

    const handleDelete = (expense: Expense) => {
        if (
            !window.confirm(
                `Supprimer la dépense ${expense.reference} ?`,
            )
        ) {
            return;
        }

        router.delete(stock.expenses.destroy(expense.id).url, {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['expenses', 'stats'] }),
        });
    };

    return (
        <>
            <Head title="Dépenses d'exploitation" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Dépenses d'exploitation
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Suivi des charges fixes et variables du magasin.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={stock.expenses.export({
                                    query: exportQuery,
                                }).url}
                                download
                            >
                                <Download />
                                CSV
                            </a>
                        </Button>
                        {canCreate && (
                            <Button
                                size="sm"
                                onClick={() => setCreateOpen(true)}
                            >
                                <Plus className="size-4" />
                                Nouvelle dépense
                            </Button>
                        )}
                    </div>
                </div>

                {/* Stats */}
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={<Banknote className="size-5" />}
                        label="Total période"
                        value={formatCurrency(stats.total)}
                        hint={`${formatNumber(stats.count)} dépense${stats.count > 1 ? 's' : ''}`}
                    />
                    {Object.entries(stats.by_category)
                        .sort(([, a], [, b]) => b - a)
                        .slice(0, 3)
                        .map(([cat, total]) => (
                            <StatCard
                                key={cat}
                                icon={
                                    <span
                                        className={cn(
                                            'flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                            CATEGORY_COLORS[cat] ??
                                                'bg-gray-500/10 text-gray-600',
                                        )}
                                    >
                                        {(categories as Record<string, string>)[cat]?.slice(
                                            0,
                                            2,
                                        ) ?? cat.slice(0, 2)}
                                    </span>
                                }
                                label={
                                    (categories as Record<string, string>)[cat] ?? cat
                                }
                                value={formatCurrency(total)}
                            />
                        ))}
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="relative max-w-md flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher par référence…"
                            className="pl-9"
                        />
                    </div>
                    <Select
                        value={category || 'none'}
                        onValueChange={(v) =>
                            setCategory(v === 'none' ? '' : v)
                        }
                    >
                        <SelectTrigger className="h-9 w-full sm:w-48">
                            <SelectValue placeholder="Toutes les catégories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Toutes les catégories
                            </SelectItem>
                            {Object.entries(categories).map(
                                ([key, label]) => (
                                    <SelectItem key={key} value={key}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                    <Input
                        type="date"
                        value={dateFrom}
                        onChange={(e) => setDateFrom(e.target.value)}
                        className="h-9 w-full sm:w-44"
                        placeholder="Du"
                    />
                    <Input
                        type="date"
                        value={dateTo}
                        onChange={(e) => setDateTo(e.target.value)}
                        className="h-9 w-full sm:w-44"
                        placeholder="Au"
                    />
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Référence</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Catégorie</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead className="text-right">
                                    Montant
                                </TableHead>
                                <TableHead>Paiement</TableHead>
                                <TableHead>Par</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {expenses.data.map((expense) => (
                                <TableRow key={expense.id}>
                                    <TableCell>
                                        <span className="font-mono text-xs">
                                            {expense.reference}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {formatDate(expense.expense_date)}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                'text-xs',
                                                CATEGORY_COLORS[
                                                    expense.category
                                                ] ??
                                                    'bg-gray-500/10 text-gray-600',
                                            )}
                                        >
                                            {expense.category_label}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="max-w-[200px] truncate text-sm text-muted-foreground">
                                        {expense.description ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {formatCurrency(expense.amount)}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {expense.payment_label}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {expense.user?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {canDelete && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    handleDelete(expense)
                                                }
                                                aria-label={`Supprimer ${expense.reference}`}
                                            >
                                                <Trash2 className="size-4 text-red-500" />
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {expenses.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <Banknote className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucune dépense
                                            </p>
                                            <p className="text-xs">
                                                {search || category || dateFrom || dateTo
                                                    ? 'Essayez de modifier vos filtres.'
                                                    : 'Enregistrez la première dépense.'}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar pagination={expenses} />
            </div>

            {createOpen && (
                <CreateDialog
                    categories={categories}
                    onClose={() => setCreateOpen(false)}
                />
            )}
        </>
    );
}

Expenses.layout = {
    breadcrumbs: [
        {
            title: 'Dépenses',
            href: stock.expenses.index().url,
        },
    ],
};
