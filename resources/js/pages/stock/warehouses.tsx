import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    MapPin,
    Pencil,
    Plus,
    Star,
    Trash2,
    Warehouse as WarehouseIcon,
} from 'lucide-react';
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
import type { Auth, Warehouse } from '@/types';

type Props = {
    warehouses: Warehouse[];
};

function WarehouseForm({
    warehouse,
    onDone,
}: {
    warehouse: Warehouse | null;
    onDone: () => void;
}) {
    const editing = warehouse !== null;
    const form = useForm({
        name: warehouse?.name ?? '',
        code: warehouse?.code ?? '',
        address: warehouse?.address ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing && warehouse) {
            form.patch(stock.warehouses.update(warehouse.id).url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(stock.warehouses.store().url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form id="warehouse-form" onSubmit={submit} className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="name">Nom de l’entrepôt</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Ex. : Entrepôt Annexe"
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="code">Code</Label>
                    <Input
                        id="code"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        placeholder="Ex. : ANNEXE-1"
                        required
                    />
                    <InputError message={form.errors.code} />
                </div>
            </div>
            <div className="space-y-2">
                <Label htmlFor="address">Adresse</Label>
                <Input
                    id="address"
                    value={form.data.address}
                    onChange={(e) => form.setData('address', e.target.value)}
                    placeholder="Ville, zone…"
                />
                <InputError message={form.errors.address} />
            </div>
            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {editing ? 'Enregistrer' : 'Créer l’entrepôt'}
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function Warehouses({ warehouses }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canManage = permissions.includes('warehouses.manage');

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingWarehouse, setEditingWarehouse] = useState<Warehouse | null>(
        null,
    );
    const [deleteTarget, setDeleteTarget] = useState<Warehouse | null>(null);

    const openCreate = () => {
        setEditingWarehouse(null);
        setDialogOpen(true);
    };

    const setDefault = (warehouse: Warehouse) => {
        router.patch(
            stock.warehouses.setDefault(warehouse.id).url,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(stock.warehouses.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <>
            <Head title="Entrepôts" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Entrepôts
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Vos dépôts de stock. Le stock total d’un produit est
                            la somme de ses entrepôts.
                        </p>
                    </div>
                    {canManage && (
                        <Button onClick={openCreate} size="sm">
                            <Plus />
                            Nouvel entrepôt
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Entrepôt</TableHead>
                                <TableHead className="text-right">
                                    Produits
                                </TableHead>
                                <TableHead className="text-right">
                                    Unités
                                </TableHead>
                                <TableHead>Défaut</TableHead>
                                {canManage && (
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {warehouses.map((warehouse) => (
                                <TableRow key={warehouse.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                                <WarehouseIcon className="size-4" />
                                            </span>
                                            <div>
                                                <div className="flex items-center gap-2 font-medium">
                                                    {warehouse.name}
                                                    <span className="text-xs text-muted-foreground">
                                                        {warehouse.code}
                                                    </span>
                                                </div>
                                                {warehouse.address && (
                                                    <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                                        <MapPin className="size-3" />
                                                        {warehouse.address}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(
                                            warehouse.products_count ?? 0,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatNumber(
                                            warehouse.total_units ?? 0,
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {warehouse.is_default ? (
                                            <Badge
                                                variant="outline"
                                                className="gap-1 border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                            >
                                                <Star className="size-3" />
                                                Par défaut
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    {canManage && (
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {!warehouse.is_default && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            setDefault(
                                                                warehouse,
                                                            )
                                                        }
                                                        aria-label={`Définir ${warehouse.name} par défaut`}
                                                    >
                                                        <Star />
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => {
                                                        setEditingWarehouse(
                                                            warehouse,
                                                        );
                                                        setDialogOpen(true);
                                                    }}
                                                    aria-label={`Modifier ${warehouse.name}`}
                                                >
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                    onClick={() =>
                                                        setDeleteTarget(
                                                            warehouse,
                                                        )
                                                    }
                                                    aria-label={`Supprimer ${warehouse.name}`}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {warehouses.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <WarehouseIcon className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun entrepôt
                                            </p>
                                            <p className="text-xs">
                                                Créez votre premier dépôt.
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
                            {editingWarehouse
                                ? 'Modifier l’entrepôt'
                                : 'Nouvel entrepôt'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingWarehouse
                                ? `Mise à jour de « ${editingWarehouse.name} ».`
                                : 'Les informations de votre dépôt.'}
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <WarehouseForm
                            key={editingWarehouse?.id ?? 'new'}
                            warehouse={editingWarehouse}
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
                        <DialogTitle>Supprimer l’entrepôt</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer «{' '}
                            {deleteTarget?.name} » ? Seuls les entrepôts vides
                            peuvent être supprimés.
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

Warehouses.layout = {
    breadcrumbs: [
        {
            title: 'Entrepôts',
            href: stock.warehouses.index().url,
        },
    ],
};
