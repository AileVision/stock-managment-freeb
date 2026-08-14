import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Banknote,
    Boxes,
    Download,
    Package,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    formatNumber,
    MOVEMENT_TYPE_LABELS,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type {
    Auth,
    CategoryReport,
    MovementReport,
    ReportSummary,
} from '@/types';

type Props = {
    summary: ReportSummary;
    categories: CategoryReport[];
    movements: MovementReport;
    filters: {
        from: string | null;
        to: string | null;
    };
};

const movementRows = (movements: MovementReport) => [
    {
        label: MOVEMENT_TYPE_LABELS.in,
        count: movements.in_count,
        quantity: movements.in_quantity,
        tone: 'text-emerald-600 dark:text-emerald-400',
    },
    {
        label: MOVEMENT_TYPE_LABELS.out,
        count: movements.out_count,
        quantity: -movements.out_quantity,
        tone: 'text-red-600 dark:text-red-400',
    },
    {
        label: MOVEMENT_TYPE_LABELS.adjustment,
        count: movements.adjustment_count,
        quantity: null,
        tone: 'text-sky-600 dark:text-sky-400',
    },
];

export default function Reports({
    summary,
    categories,
    movements,
    filters,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canExport = permissions.includes('reports.export');

    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
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

            router.get(
                stock.reports.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['summary', 'categories', 'movements', 'filters'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [from, to]);

    const exportQuery: Record<string, string> = {};

    if (from) {
        exportQuery.from = from;
    }

    if (to) {
        exportQuery.to = to;
    }

    const cards = [
        {
            label: 'Valeur du stock',
            value: formatCurrency(summary.stock_value),
            hint: 'Au prix d’achat',
            icon: Banknote,
            iconClass:
                'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'Produits au catalogue',
            value: formatNumber(summary.total_products),
            hint: 'Références actives',
            icon: Package,
            iconClass: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
        },
        {
            label: 'Unités en stock',
            value: formatNumber(summary.total_units),
            hint: 'Toutes références confondues',
            icon: Boxes,
            iconClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        },
    ];

    return (
        <>
            <Head title="Rapports" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Rapports
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Valorisation du stock et synthèse des mouvements.
                        </p>
                    </div>
                    {canExport && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={stock.reports.products.export().url}
                                    download
                                >
                                    <Download />
                                    Exporter les produits
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={
                                        stock.reports.movements.export({
                                            query: exportQuery,
                                        }).url
                                    }
                                    download
                                >
                                    <Download />
                                    Exporter les mouvements
                                </a>
                            </Button>
                        </div>
                    )}
                </div>

                {/* KPI cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    {cards.map((card) => (
                        <Card key={card.label} className="gap-3 py-5">
                            <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    {card.label}
                                </CardTitle>
                                <span
                                    className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${card.iconClass}`}
                                >
                                    <card.icon className="size-5" />
                                </span>
                            </CardHeader>
                            <CardContent className="px-5">
                                <div className="text-2xl font-semibold tracking-tight">
                                    {card.value}
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {card.hint}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-5">
                    {/* Stock value by category */}
                    <Card className="lg:col-span-3">
                        <CardHeader className="px-5">
                            <CardTitle className="text-base">
                                Valeur du stock par catégorie
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-5">
                            {categories.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 py-10 text-center">
                                    <span className="flex size-10 items-center justify-center rounded-full bg-neutral-500/10 text-neutral-600 dark:text-neutral-400">
                                        <Package className="size-5" />
                                    </span>
                                    <p className="text-sm font-medium">
                                        Aucune donnée
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Créez des produits pour visualiser la
                                        valorisation.
                                    </p>
                                </div>
                            ) : (
                                <ul className="space-y-4">
                                    {categories.map((category) => (
                                        <li
                                            key={category.id}
                                            className="space-y-1.5"
                                        >
                                            <div className="flex items-center justify-between gap-3 text-sm">
                                                <span className="flex min-w-0 items-center gap-2 font-medium">
                                                    <span
                                                        className="size-2.5 shrink-0 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                category.color ??
                                                                '#888',
                                                        }}
                                                    />
                                                    <span className="truncate">
                                                        {category.name}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 text-muted-foreground tabular-nums">
                                                    {formatNumber(
                                                        category.products_count,
                                                    )}{' '}
                                                    réf. ·{' '}
                                                    <span className="font-medium text-foreground">
                                                        {formatCurrency(
                                                            category.stock_value,
                                                        )}
                                                    </span>
                                                </span>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full transition-all"
                                                    style={{
                                                        width: `${Math.max(category.percentage, 2)}%`,
                                                        backgroundColor:
                                                            category.color ??
                                                            '#888',
                                                    }}
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    {/* Movement summary */}
                    <Card className="lg:col-span-2">
                        <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                            <div className="space-y-1">
                                <CardTitle className="text-base">
                                    Mouvements sur la période
                                </CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Toutes les entrées et sorties.
                                </p>
                            </div>
                            <SlidersHorizontal className="size-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent className="space-y-4 px-5">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="report-from"
                                        className="text-xs"
                                    >
                                        Du
                                    </Label>
                                    <Input
                                        id="report-from"
                                        type="date"
                                        value={from}
                                        onChange={(e) =>
                                            setFrom(e.target.value)
                                        }
                                        className="h-9"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="report-to"
                                        className="text-xs"
                                    >
                                        Au
                                    </Label>
                                    <Input
                                        id="report-to"
                                        type="date"
                                        value={to}
                                        onChange={(e) => setTo(e.target.value)}
                                        className="h-9"
                                    />
                                </div>
                            </div>

                            <div className="overflow-hidden rounded-xl border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Type</TableHead>
                                            <TableHead className="text-right">
                                                Mouvements
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Quantité
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {movementRows(movements).map((row) => (
                                            <TableRow key={row.label}>
                                                <TableCell className="font-medium">
                                                    {row.label}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatNumber(row.count)}
                                                </TableCell>
                                                <TableCell
                                                    className={cn(
                                                        'text-right font-medium tabular-nums',
                                                        row.tone,
                                                    )}
                                                >
                                                    {row.quantity === null ? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1">
                                                            {row.quantity >=
                                                            0 ? (
                                                                <ArrowUpRight className="size-3.5" />
                                                            ) : (
                                                                <ArrowDownRight className="size-3.5" />
                                                            )}
                                                            {formatNumber(
                                                                Math.abs(
                                                                    row.quantity,
                                                                ),
                                                            )}
                                                        </span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                        <TableRow>
                                            <TableCell className="font-semibold">
                                                Total
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatNumber(movements.total)}
                                            </TableCell>
                                            <TableCell />
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            {movements.total === 0 && (
                                <p className="text-center text-xs text-muted-foreground">
                                    Aucun mouvement sur cette période.
                                </p>
                            )}

                            {canExport && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="w-full"
                                    asChild
                                >
                                    <a
                                        href={
                                            stock.reports.movements.export({
                                                query: exportQuery,
                                            }).url
                                        }
                                        download
                                    >
                                        <Download />
                                        Exporter ces mouvements
                                    </a>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Reports.layout = {
    breadcrumbs: [
        {
            title: 'Rapports',
            href: stock.reports.index().url,
        },
    ],
};
