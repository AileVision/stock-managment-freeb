import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Bell,
    CheckCheck,
    CircleDot,
    HandCoins,
    MailOpen,
    PackageX,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { PaginationBar } from '@/components/pagination-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type {
    AppNotification,
    NotificationType,
    Paginated,
} from '@/types';

type Props = {
    notifications: Paginated<AppNotification>;
    unread_count: number;
};

const TYPE_CONFIG: Record<
    NotificationType,
    { label: string; icon: typeof Bell; className: string }
> = {
    stock_low: {
        label: 'Stock bas',
        icon: AlertTriangle,
        className:
            'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    },
    stock_out: {
        label: 'Rupture',
        icon: PackageX,
        className:
            'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
    },
    receivable_overdue: {
        label: 'Créance en retard',
        icon: HandCoins,
        className:
            'border-red-500/20 bg-red-500/10 text-red-600 dark:text-red-400',
    },
    supplier_overdue: {
        label: 'Dette fournisseur',
        icon: Truck,
        className:
            'border-orange-500/20 bg-orange-500/10 text-orange-600 dark:text-orange-400',
    },
};

function TypeBadge({ type }: { type: NotificationType }) {
    const config = TYPE_CONFIG[type] ?? {
        label: type,
        icon: Bell,
        className: 'border-gray-500/20 bg-gray-500/10 text-gray-600',
    };
    const Icon = config.icon;

    return (
        <Badge variant="outline" className={cn('gap-1', config.className)}>
            <Icon className="size-3" />
            {config.label}
        </Badge>
    );
}

export default function Notifications({
    notifications,
    unread_count,
}: Props) {
    const [markingAll, setMarkingAll] = useState(false);

    const handleMarkRead = (notification: AppNotification) => {
        if (notification.is_read) return;

        fetch(stock.notifications.markRead(notification.id).url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((c) => c.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                ),
            },
        }).then(() => {
            router.reload({ only: ['notifications', 'unread_count'] });
        });
    };

    const handleMarkAllRead = () => {
        setMarkingAll(true);

        fetch(stock.notifications.markAllRead().url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((c) => c.startsWith('XSRF-TOKEN='))
                        ?.split('=')[1] ?? '',
                ),
            },
        }).then(() => {
            setMarkingAll(false);
            router.reload({ only: ['notifications', 'unread_count'] });
        });
    };

    return (
        <>
            <Head title="Notifications" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Notifications
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Alertes de stock, créances et dettes fournisseurs.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {unread_count > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleMarkAllRead}
                                disabled={markingAll}
                            >
                                <CheckCheck className="size-4" />
                                Tout marquer comme lu
                                <Badge variant="secondary" className="ml-1">
                                    {unread_count}
                                </Badge>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-8" />
                                <TableHead>Type</TableHead>
                                <TableHead>Titre</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Message
                                </TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Date
                                </TableHead>
                                <TableHead className="w-8" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {notifications.data.map((n) => (
                                <TableRow
                                    key={n.id}
                                    className={cn(
                                        !n.is_read && 'bg-muted/30',
                                        n.link && 'cursor-pointer',
                                    )}
                                    onClick={() => {
                                        if (n.link) {
                                            router.get(n.link);
                                        }
                                    }}
                                >
                                    <TableCell>
                                        {!n.is_read && (
                                            <CircleDot className="size-2.5 fill-blue-500 text-blue-500" />
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <TypeBadge type={n.type} />
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'max-w-[300px] truncate text-sm',
                                            !n.is_read && 'font-medium',
                                        )}
                                    >
                                        {n.title}
                                    </TableCell>
                                    <TableCell className="hidden max-w-[300px] truncate text-sm text-muted-foreground md:table-cell">
                                        {n.message ?? '—'}
                                    </TableCell>
                                    <TableCell className="hidden text-xs text-muted-foreground sm:table-cell">
                                        {formatDate(n.created_at)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {!n.is_read && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    handleMarkRead(n);
                                                }}
                                                aria-label="Marquer comme lu"
                                            >
                                                <MailOpen className="size-4" />
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {notifications.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <Bell className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucune notification
                                            </p>
                                            <p className="text-xs">
                                                Tout est en ordre !
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar
                    pagination={notifications}
                    label="notification(s)"
                />
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [
        {
            title: 'Notifications',
            href: stock.notifications.index().url,
        },
    ],
};
