import { Head, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    Boxes,
    Download,
    FileText,
    Landmark,
    Percent,
    ReceiptText,
    ShoppingCart,
    TrendingUp,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
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
import { formatCurrency, formatDateOnly, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type {
    Auth,
    FinancialPurchase,
    FinancialSummary,
    VatByRate,
} from '@/types';

type Props = {
    summary: FinancialSummary;
    vat_by_rate: VatByRate[];
    purchase_orders: FinancialPurchase[];
    filters: {
        from: string;
        to: string;
    };
};

function KpiCard({
    label,
    value,
    hint,
    icon: Icon,
    iconClass,
}: {
    label: string;
    value: string;
    hint: string;
    icon: typeof Banknote;
    iconClass: string;
}) {
    return (
        <Card className="gap-3 py-5">
            <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {label}
                </CardTitle>
                <span
                    className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${iconClass}`}
                >
                    <Icon className="size-5" />
                </span>
            </CardHeader>
            <CardContent className="px-5">
                <div className="text-2xl font-semibold tracking-tight">
                    {value}
                </div>
                <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
            </CardContent>
        </Card>
    );
}

export default function FinancialReport({
    summary,
    vat_by_rate,
    purchase_orders,
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
                stock.reports.financial({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: [
                        'summary',
                        'vat_by_rate',
                        'purchase_orders',
                        'filters',
                    ],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [from, to]);

    const hasData = summary.sales_count > 0 || summary.purchases > 0;

    const exportQuery: Record<string, string> = {};

    if (from) {
        exportQuery.from = from;
    }

    if (to) {
        exportQuery.to = to;
    }

    return (
        <>
            <Head title="Rapport financier" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Rapport financier
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Chiffre d’affaires, TVA collectée, achats et marge
                            brute sur la période.
                        </p>
                    </div>
                    <div className="flex items-end gap-3">
                        <div className="space-y-1.5">
                            <Label className="text-xs">Du</Label>
                            <Input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                                className="h-9 w-40"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Au</Label>
                            <Input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                                className="h-9 w-40"
                            />
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-9"
                            onClick={() => {
                                setFrom('');
                                setTo('');
                            }}
                        >
                            Réinitialiser
                        </Button>
                        {canExport && (
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={
                                        stock.reports.financial.export({
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
                    </div>
                </div>

                {/* KPI cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        label="Chiffre d’affaires TTC"
                        value={formatCurrency(summary.revenue)}
                        hint={`${formatNumber(summary.sales_count)} vente${
                            summary.sales_count > 1 ? 's' : ''
                        } validée${summary.sales_count > 1 ? 's' : ''}`}
                        icon={Banknote}
                        iconClass="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    />
                    <KpiCard
                        label="TVA collectée"
                        value={formatCurrency(summary.vat_collected)}
                        hint={`CA hors taxes : ${formatCurrency(
                            summary.revenue_ht,
                        )}`}
                        icon={Landmark}
                        iconClass="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                    />
                    <KpiCard
                        label="Achats (réceptions)"
                        value={formatCurrency(summary.purchases)}
                        hint="Commandes réceptionnées sur la période"
                        icon={Boxes}
                        iconClass="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    />
                    <KpiCard
                        label="Marge brute"
                        value={formatCurrency(summary.gross_margin)}
                        hint={`Coût des ventes : ${formatCurrency(
                            summary.cost_of_goods,
                        )} · ${formatNumber(summary.margin_rate)} % du CA HT`}
                        icon={Percent}
                        iconClass="bg-sky-500/10 text-sky-600 dark:text-sky-400"
                    />
                </div>

                {!hasData && (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-14 text-center text-muted-foreground">
                        <TrendingUp className="size-6 opacity-40" />
                        <p className="text-sm font-medium">Aucune donnée</p>
                        <p className="text-xs">
                            Aucune vente ni réception sur cette période.
                        </p>
                    </div>
                )}

                {hasData && (
                    <div className="grid gap-6 lg:grid-cols-5">
                        {/* VAT by rate */}
                        <Card className="lg:col-span-3">
                            <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                                <div className="space-y-1">
                                    <CardTitle className="text-base">
                                        TVA collectée par taux
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        Base HT et TVA extraite des ventes
                                        validées.
                                    </p>
                                </div>
                                <ReceiptText className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent className="px-5">
                                <div className="overflow-hidden rounded-xl border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Taux</TableHead>
                                                <TableHead className="text-right">
                                                    Base HT
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    TVA
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {vat_by_rate.map((row) => (
                                                <TableRow key={row.name}>
                                                    <TableCell className="font-medium">
                                                        {row.name}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatCurrency(
                                                            row.base,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium tabular-nums">
                                                        {formatCurrency(
                                                            row.vat,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                            <TableRow>
                                                <TableCell className="font-semibold">
                                                    Total
                                                </TableCell>
                                                <TableCell className="text-right font-semibold tabular-nums">
                                                    {formatCurrency(
                                                        summary.revenue_ht,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-semibold tabular-nums">
                                                    {formatCurrency(
                                                        summary.vat_collected,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Mini margin overview */}
                        <Card>
                            <CardHeader className="px-5">
                                <CardTitle className="text-base">
                                    Synthèse de la période
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 px-5">
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="flex items-center gap-2 text-muted-foreground">
                                        <Banknote className="size-4" />
                                        CA TTC
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(summary.revenue)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="flex items-center gap-2 text-muted-foreground">
                                        <Landmark className="size-4" />
                                        TVA collectée
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(summary.vat_collected)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="flex items-center gap-2 text-muted-foreground">
                                        <Boxes className="size-4" />
                                        Achats
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(summary.purchases)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="flex items-center gap-2 text-muted-foreground">
                                        <ShoppingCart className="size-4" />
                                        Coût des ventes
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(summary.cost_of_goods)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3 border-t pt-3 text-sm">
                                    <span className="flex items-center gap-2 font-medium">
                                        <FileText className="size-4" />
                                        Marge brute
                                    </span>
                                    <span
                                        className={cn(
                                            'font-semibold tabular-nums',
                                            summary.gross_margin >= 0
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-red-600 dark:text-red-400',
                                        )}
                                    >
                                        {formatCurrency(summary.gross_margin)}
                                    </span>
                                </div>
                                <Badge
                                    variant="outline"
                                    className={cn(
                                        'tabular-nums',
                                        summary.margin_rate >= 0
                                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : 'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
                                    )}
                                >
                                    Taux de marge :{' '}
                                    {formatNumber(summary.margin_rate)} %
                                </Badge>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Purchases on the period */}
                {purchase_orders.length > 0 && (
                    <Card>
                        <CardHeader className="px-5">
                            <CardTitle className="text-base">
                                Achats sur la période
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Commandes fournisseur réceptionnées, montant
                                estimé hors taxes.
                            </p>
                        </CardHeader>
                        <CardContent className="px-5">
                            <div className="overflow-hidden rounded-xl border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Référence</TableHead>
                                            <TableHead>Fournisseur</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead className="text-right">
                                                Montant HT estimé
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {purchase_orders.map((order) => (
                                            <TableRow key={order.reference}>
                                                <TableCell className="font-medium">
                                                    {order.reference ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {order.supplier}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {formatDateOnly(order.date)}
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {formatCurrency(
                                                        order.amount,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

FinancialReport.layout = {
    breadcrumbs: [
        {
            title: 'Rapport financier',
            href: stock.reports.financial().url,
        },
    ],
};
