import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Mail, MapPin, Pencil, Phone, Plus, Trash2, Truck } from 'lucide-react';
import { useState } from 'react';
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
import type { Auth, Supplier } from '@/types';

type Props = {
    suppliers: Supplier[];
};

function SupplierForm({
    supplier,
    onDone,
}: {
    supplier: Supplier | null;
    onDone: () => void;
}) {
    const editing = supplier !== null;
    const form = useForm({
        name: supplier?.name ?? '',
        email: supplier?.email ?? '',
        phone: supplier?.phone ?? '',
        address: supplier?.address ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing && supplier) {
            form.patch(stock.suppliers.update(supplier.id).url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(stock.suppliers.store().url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form id="supplier-form" onSubmit={submit} className="grid gap-4">
            <div className="space-y-2">
                <Label htmlFor="name">Nom du fournisseur</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Ex. : Sodigros Distribution"
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
                        placeholder="contact@fournisseur.com"
                    />
                    <InputError message={form.errors.email} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="phone">Téléphone</Label>
                    <Input
                        id="phone"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        placeholder="+221 33 000 00 00"
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

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {editing ? 'Enregistrer' : 'Créer le fournisseur'}
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function Suppliers({ suppliers }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const can = {
        create: permissions.includes('suppliers.create'),
        update: permissions.includes('suppliers.update'),
        delete: permissions.includes('suppliers.delete'),
    };

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingSupplier, setEditingSupplier] = useState<Supplier | null>(
        null,
    );
    const [deleteTarget, setDeleteTarget] = useState<Supplier | null>(null);

    const openCreate = () => {
        setEditingSupplier(null);
        setDialogOpen(true);
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(stock.suppliers.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <>
            <Head title="Fournisseurs" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Fournisseurs
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Gérez vos partenaires d’approvisionnement.
                        </p>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate} size="sm">
                            <Plus />
                            Nouveau fournisseur
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fournisseur</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead className="text-right">
                                    Produits
                                </TableHead>
                                {(can.update || can.delete) && (
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {suppliers.map((supplier) => (
                                <TableRow key={supplier.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                                <Truck className="size-4" />
                                            </span>
                                            <div className="font-medium">
                                                {supplier.name}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col gap-0.5 text-xs text-muted-foreground">
                                            {supplier.email && (
                                                <span className="flex items-center gap-1.5">
                                                    <Mail className="size-3.5" />
                                                    {supplier.email}
                                                </span>
                                            )}
                                            {supplier.phone && (
                                                <span className="flex items-center gap-1.5">
                                                    <Phone className="size-3.5" />
                                                    {supplier.phone}
                                                </span>
                                            )}
                                            {supplier.address && (
                                                <span className="flex items-center gap-1.5">
                                                    <MapPin className="size-3.5" />
                                                    {supplier.address}
                                                </span>
                                            )}
                                            {!supplier.email &&
                                                !supplier.phone &&
                                                !supplier.address &&
                                                '—'}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Badge
                                            variant="secondary"
                                            className="tabular-nums"
                                        >
                                            {formatNumber(
                                                supplier.products_count ?? 0,
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
                                                            setEditingSupplier(
                                                                supplier,
                                                            );
                                                            setDialogOpen(true);
                                                        }}
                                                        aria-label={`Modifier ${supplier.name}`}
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
                                                                supplier,
                                                            )
                                                        }
                                                        aria-label={`Supprimer ${supplier.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {suppliers.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <Truck className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun fournisseur
                                            </p>
                                            <p className="text-xs">
                                                Ajoutez votre premier
                                                fournisseur.
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
                            {editingSupplier
                                ? 'Modifier le fournisseur'
                                : 'Nouveau fournisseur'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingSupplier
                                ? `Mise à jour de « ${editingSupplier.name} ».`
                                : 'Les coordonnées de votre partenaire d’approvisionnement.'}
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <SupplierForm
                            key={editingSupplier?.id ?? 'new'}
                            supplier={editingSupplier}
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
                        <DialogTitle>Supprimer le fournisseur</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer «{' '}
                            {deleteTarget?.name} » ? Les produits associés ne
                            seront pas supprimés mais deviendront « sans
                            fournisseur ».
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

Suppliers.layout = {
    breadcrumbs: [
        {
            title: 'Fournisseurs',
            href: stock.suppliers.index().url,
        },
    ],
};
