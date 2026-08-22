import { Contact, Search, UserRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatCurrency } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PosCustomer } from '@/types';

type Props = {
    customers: PosCustomer[];
    value: number | null;
    onChange: (id: number | null) => void;
    placeholder?: string;
    disabled?: boolean;
    showAvoirs?: boolean;
};

export function CustomerPicker({
    customers,
    value,
    onChange,
    placeholder = 'Sélectionner un client…',
    disabled = false,
    showAvoirs = false,
}: Props) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const selected =
        customers.find((customer) => customer.id === value) ?? null;

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return customers;
        }

        return customers.filter(
            (customer) =>
                customer.name.toLowerCase().includes(query) ||
                (customer.phone ?? '').toLowerCase().includes(query),
        );
    }, [customers, search]);

    return (
        <>
            <Button
                type="button"
                variant="outline"
                className="w-full justify-start font-normal"
                disabled={disabled}
                onClick={() => {
                    setSearch('');
                    setOpen(true);
                }}
            >
                {selected ? (
                    <span className="flex min-w-0 flex-1 items-center gap-2 text-left">
                        <UserRound className="size-4 shrink-0 text-muted-foreground" />
                        <span className="truncate">{selected.name}</span>
                        {showAvoirs && selected.available_avoirs > 0 && (
                            <Badge
                                variant="secondary"
                                className="ml-auto shrink-0 tabular-nums"
                            >
                                {formatCurrency(selected.available_avoirs)}{' '}
                                d’avoirs
                            </Badge>
                        )}
                    </span>
                ) : (
                    <span className="text-muted-foreground">{placeholder}</span>
                )}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Choisir un client</DialogTitle>
                        <DialogDescription>
                            Recherchez par nom ou téléphone.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher un client…"
                            className="pl-9"
                            autoFocus
                        />
                    </div>

                    <div className="flex max-h-72 flex-col gap-1 overflow-y-auto">
                        {filtered.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Aucun client trouvé.
                            </p>
                        ) : (
                            filtered.map((customer) => (
                                <button
                                    key={customer.id}
                                    type="button"
                                    onClick={() => {
                                        onChange(customer.id);
                                        setOpen(false);
                                    }}
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg border p-3 text-left transition-colors',
                                        customer.id === value
                                            ? 'border-ring bg-accent'
                                            : 'hover:bg-accent/50',
                                    )}
                                >
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <Contact className="size-4" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">
                                            {customer.name}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {customer.phone ??
                                                customer.email ??
                                                '—'}
                                        </span>
                                    </span>
                                    {showAvoirs &&
                                        customer.available_avoirs > 0 && (
                                            <Badge
                                                variant="secondary"
                                                className="shrink-0 tabular-nums"
                                            >
                                                {formatCurrency(
                                                    customer.available_avoirs,
                                                )}
                                            </Badge>
                                        )}
                                </button>
                            ))
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
