import { useCallback, useEffect, useState } from 'react';
import stock from '@/routes/stock';

export function NotificationSidebarBadge() {
    const [count, setCount] = useState(0);

    const fetchCount = useCallback(async () => {
        try {
            const res = await fetch(
                stock.notifications.unreadCount().url,
                { headers: { Accept: 'application/json' } },
            );
            if (res.ok) {
                const data = await res.json();
                setCount(data.count ?? 0);
            }
        } catch {
            // Silently ignore
        }
    }, []);

    useEffect(() => {
        fetchCount();
        const interval = setInterval(fetchCount, 30_000);
        return () => clearInterval(interval);
    }, [fetchCount]);

    if (count === 0) {
        return null;
    }

    return (
        <span className="ml-auto flex size-5 shrink-0 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground">
            {count > 99 ? '99+' : count}
        </span>
    );
}
