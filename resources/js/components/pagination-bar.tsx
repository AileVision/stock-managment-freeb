import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import type { Paginated } from '@/types';

export function PaginationBar<T>({
    pagination,
    label,
}: {
    pagination: Paginated<T>;
    label?: string;
}) {
    const go = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true });
    };

    const numberedLinks = pagination.links.filter(
        (link) => !Number.isNaN(Number(link.label)),
    );

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-2 py-4">
            <p className="text-sm text-muted-foreground">
                {formatNumber(pagination.total)} {label ?? 'résultat(s)'} — page{' '}
                {pagination.current_page} sur {pagination.last_page}
            </p>
            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!pagination.prev_page_url}
                    onClick={() => go(pagination.prev_page_url)}
                >
                    ← Précédent
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
                    Suivant →
                </Button>
            </div>
        </div>
    );
}
