import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-react';
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
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type { Auth, Category } from '@/types';

type Props = {
    categories: Category[];
};

const COLORS = [
    '#6366f1',
    '#f59e0b',
    '#06b6d4',
    '#10b981',
    '#8b5cf6',
    '#ef4444',
    '#ec4899',
    '#64748b',
];

function CategoryForm({
    category,
    onDone,
}: {
    category: Category | null;
    onDone: () => void;
}) {
    const editing = category !== null;
    const form = useForm({
        name: category?.name ?? '',
        description: category?.description ?? '',
        color: category?.color ?? COLORS[0],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing && category) {
            form.patch(stock.categories.update(category.id).url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(stock.categories.store().url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    return (
        <form id="category-form" onSubmit={submit} className="grid gap-4">
            <div className="space-y-2">
                <Label htmlFor="name">Nom de la catégorie</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Ex. : Alimentation"
                    required
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Input
                    id="description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Optionnel"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="space-y-2">
                <Label>Couleur</Label>
                <div className="flex flex-wrap gap-2">
                    {COLORS.map((color) => (
                        <button
                            key={color}
                            type="button"
                            aria-label={`Choisir la couleur ${color}`}
                            onClick={() => form.setData('color', color)}
                            className={cn(
                                'size-7 rounded-full transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                form.data.color === color &&
                                    'ring-2 ring-ring ring-offset-2',
                            )}
                            style={{ backgroundColor: color }}
                        />
                    ))}
                </div>
            </div>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {editing ? 'Enregistrer' : 'Créer la catégorie'}
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function Categories({ categories }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const can = {
        create: permissions.includes('categories.create'),
        update: permissions.includes('categories.update'),
        delete: permissions.includes('categories.delete'),
    };

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<Category | null>(
        null,
    );
    const [deleteTarget, setDeleteTarget] = useState<Category | null>(null);

    const openCreate = () => {
        setEditingCategory(null);
        setDialogOpen(true);
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(stock.categories.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <>
            <Head title="Catégories" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Catégories
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Organisez vos produits par famille.
                        </p>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate} size="sm">
                            <Plus />
                            Nouvelle catégorie
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Catégorie</TableHead>
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
                            {categories.map((category) => (
                                <TableRow key={category.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            <span
                                                className="flex size-8 shrink-0 items-center justify-center rounded-lg"
                                                style={{
                                                    backgroundColor: `${category.color ?? '#888'}18`,
                                                }}
                                            >
                                                <span
                                                    className="size-2.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            category.color ??
                                                            '#888',
                                                    }}
                                                />
                                            </span>
                                            <div>
                                                <div className="font-medium">
                                                    {category.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {category.description ??
                                                        '—'}
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Badge
                                            variant="secondary"
                                            className="tabular-nums"
                                        >
                                            {formatNumber(
                                                category.products_count ?? 0,
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
                                                            setEditingCategory(
                                                                category,
                                                            );
                                                            setDialogOpen(true);
                                                        }}
                                                        aria-label={`Modifier ${category.name}`}
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
                                                                category,
                                                            )
                                                        }
                                                        aria-label={`Supprimer ${category.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {categories.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <Tags className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucune catégorie
                                            </p>
                                            <p className="text-xs">
                                                Créez votre première catégorie.
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
                            {editingCategory
                                ? 'Modifier la catégorie'
                                : 'Nouvelle catégorie'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingCategory
                                ? `Mise à jour de « ${editingCategory.name} ».`
                                : 'Les catégories regroupent vos produits.'}
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <CategoryForm
                            key={editingCategory?.id ?? 'new'}
                            category={editingCategory}
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
                        <DialogTitle>Supprimer la catégorie</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer «{' '}
                            {deleteTarget?.name} » ? Les produits de cette
                            catégorie ne seront pas supprimés mais deviendront «
                            sans catégorie ».
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

Categories.layout = {
    breadcrumbs: [
        {
            title: 'Catégories',
            href: stock.categories.index().url,
        },
    ],
};
