import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Banknote,
    Boxes,
    Package,
    SlidersHorizontal,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    MOVEMENT_TYPE_LABELS,
    unitLabel,
} from '@/lib/format';
import { dashboard } from '@/routes';
import stock from '@/routes/stock';
import type {
    DashboardStats,
    MovementType,
    Product,
    StockMovement,
} from '@/types';

type Props = {
    stats: DashboardStats;
    low_stock_products: Product[];
    recent_movements: StockMovement[];
};

const movementBadgeClass: Record<MovementType, string> = {
    in: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
    out: 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20',
    adjustment:
        'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
};

const movementIconClass: Record<MovementType, string> = {
    in: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    out: 'bg-red-500/10 text-red-600 dark:text-red-400',
    adjustment: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
};

export default function Dashboard({
    stats,
    low_stock_products,
    recent_movements,
}: Props) {
    const alerts = stats.low_stock + stats.out_of_stock;

    const cards = [
        {
            label: 'Produits au catalogue',
            value: formatNumber(stats.total_products),
            hint: 'Références actives',
            icon: Package,
            iconClass: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
        },
        {
            label: 'Valeur du stock',
            value: formatCurrency(stats.stock_value),
            hint: 'Au prix d’achat',
            icon: Banknote,
            iconClass:
                'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'Unités en stock',
            value: formatNumber(stats.total_units),
            hint: 'Toutes références confondues',
            icon: Boxes,
            iconClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        },
        {
            label: 'Alertes de stock',
            value: formatNumber(alerts),
            hint: `${stats.low_stock} bas · ${stats.out_of_stock} en rupture`,
            icon: AlertTriangle,
            iconClass:
                alerts > 0
                    ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                    : 'bg-neutral-500/10 text-neutral-600 dark:text-neutral-400',
        },
    ];

    return (
        <>
            <Head title="Tableau de bord" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Tableau de bord
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Vue d’ensemble de votre stock en temps réel.
                        </p>
                    </div>
                    <Button asChild size="sm">
                        <Link href={stock.movements.index()}>
                            <SlidersHorizontal />
                            Nouveau mouvement
                        </Link>
                    </Button>
                </div>

                {/* KPI cards */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                    {/* Low stock products */}
                    <Card className="lg:col-span-3">
                        <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                            <div className="space-y-1">
                                <CardTitle className="text-base">
                                    Stock bas & ruptures
                                </CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Références à réapprovisionner en priorité.
                                </p>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link
                                    href={stock.products.index({
                                        query: { low_stock: '1' },
                                    })}
                                >
                                    Tout voir
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="px-5">
                            {low_stock_products.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 py-10 text-center">
                                    <span className="flex size-10 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <Package className="size-5" />
                                    </span>
                                    <p className="text-sm font-medium">
                                        Aucune alerte
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Tous vos produits sont au-dessus de leur
                                        seuil.
                                    </p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Produit</TableHead>
                                            <TableHead>Catégorie</TableHead>
                                            <TableHead className="text-right">
                                                Stock
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Seuil
                                            </TableHead>
                                            <TableHead className="text-right">
                                                État
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {low_stock_products.map((product) => (
                                            <TableRow key={product.id}>
                                                <TableCell>
                                                    <Link
                                                        href={stock.products.index(
                                                            {
                                                                query: {
                                                                    search: product.sku,
                                                                },
                                                            },
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {product.name}
                                                    </Link>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {product.sku}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {product.category?.name ??
                                                        '—'}
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {formatNumber(
                                                        product.stock_quantity,
                                                    )}{' '}
                                                    <span className="text-xs text-muted-foreground">
                                                        {unitLabel(
                                                            product.unit,
                                                        )}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right text-muted-foreground tabular-nums">
                                                    {formatNumber(
                                                        product.min_stock,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {product.stock_quantity ===
                                                    0 ? (
                                                        <Badge variant="destructive">
                                                            Rupture
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                                        >
                                                            Stock bas
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent movements */}
                    <Card className="lg:col-span-2">
                        <CardHeader className="flex-row items-center justify-between gap-3 px-5">
                            <div className="space-y-1">
                                <CardTitle className="text-base">
                                    Mouvements récents
                                </CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Les 10 derniers.
                                </p>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={stock.movements.index()}>
                                    Tout voir
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="px-5">
                            <ul className="space-y-4">
                                {recent_movements.map((movement) => (
                                    <li
                                        key={movement.id}
                                        className="flex items-start gap-3"
                                    >
                                        <span
                                            className={`mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg ${movementIconClass[movement.type]}`}
                                        >
                                            {movement.quantity > 0 ? (
                                                <ArrowUpRight className="size-4" />
                                            ) : (
                                                <ArrowDownRight className="size-4" />
                                            )}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {movement.product.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {
                                                    MOVEMENT_TYPE_LABELS[
                                                        movement.type
                                                    ]
                                                }{' '}
                                                ·{' '}
                                                {formatDate(
                                                    movement.created_at,
                                                )}
                                            </p>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className={`shrink-0 tabular-nums ${movementBadgeClass[movement.type]}`}
                                        >
                                            {movement.quantity > 0 ? '+' : ''}
                                            {formatNumber(movement.quantity)}
                                        </Badge>
                                    </li>
                                ))}
                                {recent_movements.length === 0 && (
                                    <li className="py-8 text-center text-sm text-muted-foreground">
                                        Aucun mouvement pour le moment.
                                    </li>
                                )}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Tableau de bord',
            href: dashboard(),
        },
    ],
};
