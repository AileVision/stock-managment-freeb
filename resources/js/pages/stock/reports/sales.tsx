import { Head, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    Download,
    FileText,
    Percent,
    Receipt,
    ShoppingBag,
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
import {
    formatCurrency,
    formatDateOnly,
    formatNumber,
    PAYMENT_METHOD_LABELS,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type {
    Auth,
    DailyRevenue,
    PaymentBreakdown,
    SalesReportSummary,
    TopProduct,
} from '@/types';

type Props = {
    summary: SalesReportSummary;
    daily: DailyRevenue[];
    by_payment: PaymentBreakdown[];
    top_products: TopProduct[];
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

export default function SalesReport({
    summary,
    daily,
    by_payment,
    top_products,
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
                stock.reports.sales({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: [
                        'summary',
                        'daily',
                        'by_payment',
                        'top_products',
                        'filters',
                    ],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [from, to]);

    const hasData = summary.sales_count > 0;

    const exportQuery: Record<string, string> = {};

    if (from) {
        exportQuery.from = from;
    }

    if (to) {
        exportQuery.to = to;
    }

    return (
        <>
            <Head title="Rapport de ventes" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Rapport de ventes
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Chiffre d’affaires, marges et meilleures ventes sur
                            la période.
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
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={
                                            stock.reports.sales.export({
                                                query: exportQuery,
                                            }).url
                                        }
                                        download
                                    >
                                        <Download />
                                        CSV
                                    </a>
                                </Button>
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={
                                            stock.reports.sales.pdf({
                                                query: exportQuery,
                                            }).url
                                        }
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <FileText />
                                        PDF
                                    </a>
                                </Button>
                            </div>
                        )}
                    </div>
                </div>

                {/* KPI cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard
                        label="Chiffre d’affaires"
                        value={formatCurrency(summary.revenue)}
                        hint="Ventes validées"
                        icon={Banknote}
                        iconClass="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                    />
                    <KpiCard
                        label="Ventes"
                        value={formatNumber(summary.sales_count)}
                        hint="Encaissements validés"
                        icon={ShoppingCart}
                        iconClass="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                    />
                    <KpiCard
                        label="Panier moyen"
                        value={formatCurrency(summary.average_basket)}
                        hint="CA ÷ nombre de ventes"
                        icon={ShoppingBag}
                        iconClass="bg-amber-500/10 text-amber-600 dark:text-amber-400"
                    />
                    <KpiCard
                        label="Marge brute"
                        value={formatCurrency(summary.margin)}
                        hint={`${formatNumber(summary.margin_rate)} % du CA`}
                        icon={Percent}
                        iconClass="bg-sky-500/10 text-sky-600 dark:text-sky-400"
                    />
                </div>

                {!hasData && (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-14 text-center text-muted-foreground">
                        <TrendingUp className="size-6 opacity-40" />
                        <p className="text-sm font-medium">Aucune vente</p>
                        <p className="text-xs">
                            Aucun encaissement validé sur cette période.
                        </p>
                    </div>
                )}

                {hasData && (
                    <div className="grid gap-6 lg:grid-cols-5">
                        {/* Revenue by day */}
                        <Card className="lg:col-span-3">
                            <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                                <div className="space-y-1">
                                    <CardTitle className="text-base">
                                        Chiffre d’affaires par jour
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        Ventes validées sur la période.
                                    </p>
                                </div>
                                <Receipt className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent className="px-5">
                                <div className="overflow-hidden rounded-xl border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Date</TableHead>
                                                <TableHead className="text-right">
                                                    Ventes
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    CA
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {daily.map((row) => (
                                                <TableRow key={row.day}>
                                                    <TableCell className="font-medium">
                                                        {formatDateOnly(
                                                            row.day,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatNumber(
                                                            row.sales_count,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium tabular-nums">
                                                        {formatCurrency(
                                                            row.revenue,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                            <TableRow>
                                                <TableCell className="font-semibold">
                                                    Total
                                                </TableCell>
                                                <TableCell className="text-right font-semibold tabular-nums">
                                                    {formatNumber(
                                                        summary.sales_count,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-semibold tabular-nums">
                                                    {formatCurrency(
                                                        summary.revenue,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex flex-col gap-6 lg:col-span-2">
                            {/* By payment method */}
                            <Card>
                                <CardHeader className="px-5">
                                    <CardTitle className="text-base">
                                        Par mode de paiement
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 px-5">
                                    {by_payment.map((row) => (
                                        <div
                                            key={row.payment_method}
                                            className="flex items-center justify-between gap-3 text-sm"
                                        >
                                            <span className="font-medium">
                                                {PAYMENT_METHOD_LABELS[
                                                    row.payment_method
                                                ] ?? row.payment_label}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground tabular-nums">
                                                {formatNumber(row.sales_count)}{' '}
                                                vente
                                                {row.sales_count > 1
                                                    ? 's'
                                                    : ''}{' '}
                                                ·{' '}
                                                <span className="font-medium text-foreground">
                                                    {formatCurrency(
                                                        row.revenue,
                                                    )}
                                                </span>
                                            </span>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                )}

                {/* Top products */}
                {top_products.length > 0 && (
                    <Card>
                        <CardHeader className="px-5">
                            <CardTitle className="text-base">
                                Meilleures ventes
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Top 10 des produits par chiffre d’affaires, avec
                                marge estimée.
                            </p>
                        </CardHeader>
                        <CardContent className="px-5">
                            <div className="overflow-hidden rounded-xl border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Produit</TableHead>
                                            <TableHead className="text-right">
                                                Qté vendue
                                            </TableHead>
                                            <TableHead className="text-right">
                                                CA
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Coût
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Marge
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Taux
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {top_products.map((product) => (
                                            <TableRow key={product.product_id}>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {product.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {product.sku}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatNumber(
                                                        product.quantity,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatCurrency(
                                                        product.revenue,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right text-muted-foreground tabular-nums">
                                                    {formatCurrency(
                                                        product.cost,
                                                    )}
                                                </TableCell>
                                                <TableCell
                                                    className={cn(
                                                        'text-right font-medium tabular-nums',
                                                        product.margin >= 0
                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                            : 'text-red-600 dark:text-red-400',
                                                    )}
                                                >
                                                    {formatCurrency(
                                                        product.margin,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            'tabular-nums',
                                                            product.margin_rate >=
                                                                0
                                                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                                : 'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
                                                        )}
                                                    >
                                                        {formatNumber(
                                                            product.margin_rate,
                                                        )}
                                                        %
                                                    </Badge>
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

SalesReport.layout = {
    breadcrumbs: [
        {
            title: 'Rapport de ventes',
            href: stock.reports.sales().url,
        },
    ],
};
