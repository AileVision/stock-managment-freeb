import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Contact,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { formatNumber } from '@/lib/format';
import stock from '@/routes/stock';
import type { Auth, Customer, Paginated } from '@/types';

type Props = {
    customers: Paginated<Customer>;
    filters: {
        search: string;
    };
};

function CustomerForm({
    customer,
    onDone,
}: {
    customer: Customer | null;
    onDone: () => void;
}) {
    const editing = customer !== null;
    const form = useForm({
        name: customer?.name ?? '',
        email: customer?.email ?? '',
        phone: customer?.phone ?? '',
        address: customer?.address ?? '',
        notes: customer?.notes ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing && customer) {
            form.patch(stock.customers.update(customer.id).url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(stock.customers.store().url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form id="customer-form" onSubmit={submit} className="grid gap-4">
            <div className="space-y-2">
                <Label htmlFor="name">Nom du client</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Ex. : Épicerie du Centre"
                    required
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="email">Adresse e-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        placeholder="client@exemple.com"
                    />
                    <InputError message={form.errors.email} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="phone">Téléphone</Label>
                    <Input
                        id="phone"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        placeholder="+225 07 00 00 00 00"
                    />
                    <InputError message={form.errors.phone} />
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="address">Adresse</Label>
                <Input
                    id="address"
                    value={form.data.address}
                    onChange={(e) => form.setData('address', e.target.value)}
                    placeholder="Ville, quartier, zone…"
                />
                <InputError message={form.errors.address} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="notes">Remarque</Label>
                <Input
                    id="notes"
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                    placeholder="Préférences, conditions particulières…"
                />
                <InputError message={form.errors.notes} />
            </div>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {editing ? 'Enregistrer' : 'Créer le client'}
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function Customers({ customers, filters }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const can = {
        create: permissions.includes('customers.create'),
        update: permissions.includes('customers.update'),
        delete: permissions.includes('customers.delete'),
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<Customer | null>(
        null,
    );
    const [deleteTarget, setDeleteTarget] = useState<Customer | null>(null);

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
                stock.customers.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['customers', 'filters'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    const openCreate = () => {
        setEditingCustomer(null);
        setDialogOpen(true);
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(stock.customers.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <>
            <Head title="Clients" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Clients
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Votre carnet d’adresses, utilisé pour les devis et
                            les factures.
                        </p>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate} size="sm">
                            <Plus />
                            Nouveau client
                        </Button>
                    )}
                </div>

                <div className="relative max-w-md">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Rechercher par nom, e-mail ou téléphone…"
                        className="pl-9"
                    />
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Client</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead className="text-right">
                                    Devis
                                </TableHead>
                                <TableHead className="text-right">
                                    Factures
                                </TableHead>
                                {(can.update || can.delete) && (
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customers.data.map((customer) => (
                                <TableRow key={customer.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                                <Contact className="size-4" />
                                            </span>
                                            <div className="font-medium">
                                                {customer.name}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col gap-0.5 text-xs text-muted-foreground">
                                            {customer.email && (
                                                <span className="flex items-center gap-1.5">
                                                    <Mail className="size-3.5" />
                                                    {customer.email}
                                                </span>
                                            )}
                                            {customer.phone && (
                                                <span className="flex items-center gap-1.5">
                                                    <Phone className="size-3.5" />
                                                    {customer.phone}
                                                </span>
                                            )}
                                            {customer.address && (
                                                <span className="flex items-center gap-1.5">
                                                    <MapPin className="size-3.5" />
                                                    {customer.address}
                                                </span>
                                            )}
                                            {!customer.email &&
                                                !customer.phone &&
                                                !customer.address &&
                                                '—'}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Badge
                                            variant="secondary"
                                            className="tabular-nums"
                                        >
                                            {formatNumber(
                                                customer.quotes_count ?? 0,
                                            )}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Badge
                                            variant="secondary"
                                            className="tabular-nums"
                                        >
                                            {formatNumber(
                                                customer.invoices_count ?? 0,
                                            )}
                                        </Badge>
                                    </TableCell>
                                    {(can.update || can.delete) && (
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {can.update && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => {
                                                            setEditingCustomer(
                                                                customer,
                                                            );
                                                            setDialogOpen(true);
                                                        }}
                                                        aria-label={`Modifier ${customer.name}`}
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                )}
                                                {can.delete && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                        onClick={() =>
                                                            setDeleteTarget(
                                                                customer,
                                                            )
                                                        }
                                                        aria-label={`Supprimer ${customer.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {customers.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <Contact className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun client
                                            </p>
                                            <p className="text-xs">
                                                {search.trim()
                                                    ? 'Essayez une autre recherche.'
                                                    : 'Ajoutez votre premier client.'}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingCustomer
                                ? 'Modifier le client'
                                : 'Nouveau client'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingCustomer
                                ? `Mise à jour de « ${editingCustomer.name} ».`
                                : 'Les coordonnées de votre client.'}
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <CustomerForm
                            key={editingCustomer?.id ?? 'new'}
                            customer={editingCustomer}
                            onDone={() => setDialogOpen(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Supprimer le client</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer «{' '}
                            {deleteTarget?.name} » ? Ses devis et factures
                            seront conservés mais ne seront plus rattachés à sa
                            fiche.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            Annuler
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Supprimer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Customers.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: stock.customers.index().url,
        },
    ],
};
