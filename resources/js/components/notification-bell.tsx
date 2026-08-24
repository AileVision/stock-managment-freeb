import { router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type { AppNotification, NotificationType } from '@/types';

const TYPE_DOT: Record<NotificationType, string> = {
    stock_low: 'bg-amber-500',
    stock_out: 'bg-red-500',
    receivable_overdue: 'bg-red-500',
    supplier_overdue: 'bg-orange-500',
};

function NotificationDot({ type }: { type: NotificationType }) {
    return (
        <span
            className={cn(
                'inline-block size-2 shrink-0 rounded-full',
                TYPE_DOT[type] ?? 'bg-gray-400',
            )}
        />
    );
}

export function NotificationBell() {
    const [unreadCount, setUnreadCount] = useState(0);
    const [notifications, setNotifications] = useState<AppNotification[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const loaded = useRef(false);

    const fetchCount = useCallback(async () => {
        try {
            const res = await fetch(
                stock.notifications.unreadCount().url,
                { headers: { Accept: 'application/json' } },
            );
            if (res.ok) {
                const data = await res.json();
                setUnreadCount(data.count ?? 0);
            }
        } catch {
            // Silently ignore network errors
        }
    }, []);

    const fetchLatest = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(
                stock.notifications.latest().url,
                { headers: { Accept: 'application/json' } },
            );
            if (res.ok) {
                const data = await res.json();
                setNotifications(data.notifications ?? []);
                setUnreadCount(data.unread_count ?? 0);
            }
        } catch {
            // Silently ignore
        } finally {
            setLoading(false);
        }
    }, []);

    // Poll unread count every 30 seconds
    useEffect(() => {
        fetchCount();
        const interval = setInterval(fetchCount, 30_000);
        return () => clearInterval(interval);
    }, [fetchCount]);

    // Fetch latest when dropdown opens (once per open)
    useEffect(() => {
        if (open && !loaded.current) {
            loaded.current = true;
            fetchLatest();
        }
        if (!open) {
            loaded.current = false;
        }
    }, [open, fetchLatest]);

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);
    };

    const handleMarkAllRead = () => {
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
            setUnreadCount(0);
            setNotifications((prev) =>
                prev.map((n) => ({ ...n, is_read: true })),
            );
        });
    };

    const handleNotificationClick = (notification: AppNotification) => {
        if (notification.link) {
            router.get(notification.link);
        }
        setOpen(false);
    };

    return (
        <DropdownMenu open={open} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative size-9">
                    <Bell className="size-[1.2rem] opacity-80" />
                    {unreadCount > 0 && (
                        <span className="absolute -right-0.5 -top-0.5 flex size-5 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between px-4 py-3">
                    <DropdownMenuLabel className="p-0 text-sm">
                        Notifications
                    </DropdownMenuLabel>
                    {unreadCount > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 gap-1 text-xs"
                            onClick={handleMarkAllRead}
                        >
                            <CheckCheck className="size-3" />
                            Tout lire
                        </Button>
                    )}
                </div>
                <DropdownMenuSeparator className="m-0" />

                <div className="max-h-80 overflow-y-auto">
                    {loading && notifications.length === 0 && (
                        <div className="py-6 text-center text-sm text-muted-foreground">
                            Chargement…
                        </div>
                    )}

                    {!loading && notifications.length === 0 && (
                        <div className="flex flex-col items-center gap-1 py-6">
                            <Bell className="size-5 opacity-40" />
                            <p className="text-xs text-muted-foreground">
                                Aucune notification
                            </p>
                        </div>
                    )}

                    {notifications.map((n) => (
                        <DropdownMenuItem
                            key={n.id}
                            className={cn(
                                'flex items-start gap-3 rounded-none px-4 py-3',
                                n.link && 'cursor-pointer',
                                !n.is_read && 'bg-muted/30',
                            )}
                            onClick={() => handleNotificationClick(n)}
                        >
                            <div className="mt-0.5 shrink-0">
                                {!n.is_read ? (
                                    <NotificationDot type={n.type} />
                                ) : (
                                    <span className="inline-block size-2" />
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p
                                    className={cn(
                                        'truncate text-sm',
                                        !n.is_read && 'font-medium',
                                    )}
                                >
                                    {n.title}
                                </p>
                                {n.message && (
                                    <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                        {n.message}
                                    </p>
                                )}
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    {formatDate(n.created_at)}
                                </p>
                            </div>
                        </DropdownMenuItem>
                    ))}
                </div>

                {notifications.length > 0 && (
                    <>
                        <DropdownMenuSeparator className="m-0" />
                        <DropdownMenuItem
                            className="justify-center text-xs font-medium"
                            onClick={() => {
                                router.get(stock.notifications.index().url);
                                setOpen(false);
                            }}
                        >
                            Voir toutes les notifications
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
