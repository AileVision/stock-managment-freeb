import { Head, router, usePage } from '@inertiajs/react';
import {
    BadgePercent,
    Download,
    Gift,
    RotateCcw,
    Search,
    ShoppingBag,
    Undo2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency, formatDateOnly, formatNumber } from '@/lib/format';
import stock from '@/routes/stock';
import type { Auth, CreditNote, Paginated, SaleReturn } from '@/types';

type Props = {
    returns: Paginated<SaleReturn>;
    credit_notes: CreditNote[];
    stats: {
        returns_count: number;
        returns_value: number;
        refunded: number;
        avoirs_available: number;
    };
    filters: {
        search: string;
    };
};

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

function PaginationBar({ pagination }: { pagination: Paginated<SaleReturn> }) {
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
                {formatNumber(pagination.total)} retour
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

export default function Returns({
    returns,
    credit_notes,
    stats,
    filters,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canCreate = permissions.includes('returns.create');

    const [search, setSearch] = useState(filters.search ?? '');

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

            router.get(
                stock.returns.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['returns', 'filters'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    return (
        <>
            <Head title="Retours & avoirs" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Retours & avoirs
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Annulation d’une vente, réintégration du stock et
                            remboursement ou avoir client.
                        </p>
                    </div>
                    {canCreate && (
                        <Button size="sm" asChild>
                            <a href={stock.returns.create().url}>
                                <Undo2 />
                                Nouveau retour
                            </a>
                        </Button>
                    )}
                </div>

                {/* Stats */}
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={<RotateCcw className="size-5" />}
                        label="Retours enregistrés"
                        value={formatNumber(stats.returns_count)}
                    />
                    <StatCard
                        icon={<ShoppingBag className="size-5" />}
                        label="Valeur retournée"
                        value={formatCurrency(stats.returns_value)}
                    />
                    <StatCard
                        icon={<BadgePercent className="size-5" />}
                        label="Remboursé"
                        value={formatCurrency(stats.refunded)}
                        tone="danger"
                    />
                    <StatCard
                        icon={<Gift className="size-5" />}
                        label="Avoirs disponibles"
                        value={formatCurrency(stats.avoirs_available)}
                        tone="accent"
                    />
                </div>

                <div className="relative max-w-md">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Rechercher par référence, vente ou client…"
                        className="pl-9"
                    />
                </div>

                {/* Returns table */}
                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Référence</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Vente d’origine</TableHead>
                                <TableHead>Client</TableHead>
                                <TableHead className="text-right">
                                    Articles
                                </TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead>Sortie</TableHead>
                                <TableHead className="text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {returns.data.map((saleReturn) => (
                                <TableRow key={saleReturn.id}>
                                    <TableCell className="font-medium">
                                        {saleReturn.reference}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateOnly(saleReturn.returned_at)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {saleReturn.sale_reference ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {saleReturn.customer?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(saleReturn.items.length)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {formatCurrency(saleReturn.total)}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col gap-1">
                                            <span className="text-sm">
                                                {saleReturn.refund_label}
                                            </span>
                                            {saleReturn.refund_amount >
                                                0.01 && (
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {formatCurrency(
                                                        saleReturn.refund_amount,
                                                    )}
                                                </span>
                                            )}
                                            {saleReturn.applied_to_balance >
                                                0.01 && (
                                                <Badge
                                                    variant="outline"
                                                    className="w-fit border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                                >
                                                    Solde réduit de{' '}
                                                    {formatCurrency(
                                                        saleReturn.applied_to_balance,
                                                    )}
                                                </Badge>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                            aria-label={`Ticket de retour ${saleReturn.reference}`}
                                        >
                                            <a
                                                href={
                                                    stock.returns.pdf(
                                                        saleReturn.id,
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
                            {returns.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <RotateCcw className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun retour
                                            </p>
                                            <p className="text-xs">
                                                {search.trim()
                                                    ? 'Essayez une autre recherche.'
                                                    : 'Les retours clients apparaîtront ici.'}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar pagination={returns} />

                {/* Recent credit notes */}
                {credit_notes.length > 0 && (
                    <div className="overflow-hidden rounded-xl border">
                        <div className="flex items-center gap-2 border-b px-4 py-3">
                            <Gift className="size-4 text-violet-600 dark:text-violet-400" />
                            <h3 className="text-sm font-semibold">
                                Derniers avoirs émis
                            </h3>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Avoir</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Client</TableHead>
                                    <TableHead className="text-right">
                                        Montant
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Restant
                                    </TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {credit_notes.map((note) => (
                                    <TableRow key={note.id}>
                                        <TableCell className="font-medium">
                                            {note.reference}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {formatDateOnly(note.issued_at)}
                                        </TableCell>
                                        <TableCell>
                                            {note.customer?.name ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatCurrency(note.amount)}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatCurrency(note.remaining)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    note.status === 'issued'
                                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                        : 'border-muted bg-muted/50 text-muted-foreground'
                                                }
                                            >
                                                {note.status === 'issued'
                                                    ? 'Disponible'
                                                    : 'Utilisé'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                aria-label={`Avoir ${note.reference}`}
                                            >
                                                <a
                                                    href={
                                                        stock.returns.avoirPdf(
                                                            note.id,
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
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

Returns.layout = {
    breadcrumbs: [
        {
            title: 'Retours & avoirs',
            href: stock.returns.index().url,
        },
    ],
};
