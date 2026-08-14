import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox as CheckboxPrimitive } from '@/components/ui/checkbox';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency, formatNumber, unitLabel } from '@/lib/format';
import stock from '@/routes/stock';
import type { Auth, Category, Paginated, Product, Supplier } from '@/types';

type Props = {
    products: Paginated<Product>;
    categories: Pick<Category, 'id' | 'name'>[];
    suppliers: Pick<Supplier, 'id' | 'name'>[];
    filters: {
        search: string;
        category_id: string | null;
        supplier_id: string | null;
        low_stock: boolean;
    };
};

type ProductFormData = {
    name: string;
    sku: string;
    barcode: string;
    category_id: string;
    supplier_id: string;
    unit: string;
    purchase_price: string;
    sale_price: string;
    stock_quantity: string;
    min_stock: string;
    description: string;
};

const emptyForm: ProductFormData = {
    name: '',
    sku: '',
    barcode: '',
    category_id: '',
    supplier_id: '',
    unit: 'pcs',
    purchase_price: '',
    sale_price: '',
    stock_quantity: '',
    min_stock: '0',
    description: '',
};

const UNITS: { value: string; label: string }[] = [
    { value: 'pcs', label: 'Pièce' },
    { value: 'box', label: 'Boîte' },
    { value: 'pack', label: 'Paquet' },
    { value: 'kg', label: 'Kilogramme (kg)' },
    { value: 'g', label: 'Gramme (g)' },
    { value: 'l', label: 'Litre (L)' },
    { value: 'ml', label: 'Millilitre (mL)' },
    { value: 'm', label: 'Mètre (m)' },
];

function toFormData(product: Product): ProductFormData {
    return {
        name: product.name,
        sku: product.sku,
        barcode: product.barcode ?? '',
        category_id: product.category_id ? String(product.category_id) : '',
        supplier_id: product.supplier_id ? String(product.supplier_id) : '',
        unit: product.unit,
        purchase_price: String(product.purchase_price),
        sale_price: String(product.sale_price),
        stock_quantity: '',
        min_stock: String(product.min_stock),
        description: product.description ?? '',
    };
}

function ProductForm({
    product,
    categories,
    suppliers,
    onDone,
}: {
    product: Product | null;
    categories: Pick<Category, 'id' | 'name'>[];
    suppliers: Pick<Supplier, 'id' | 'name'>[];
    onDone: () => void;
}) {
    const editing = product !== null;
    const form = useForm<ProductFormData>(
        editing && product ? toFormData(product) : emptyForm,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing && product) {
            form.patch(stock.products.update(product.id).url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        } else {
            form.post(stock.products.store().url, {
                preserveScroll: true,
                onSuccess: onDone,
            });
        }
    };

    const selectValue = (value: string, placeholder: string) => (
        <Select
            value={form.data[value as keyof ProductFormData] as string}
            onValueChange={(v) =>
                form.setData(value as keyof ProductFormData, v)
            }
        >
            <SelectTrigger className="w-full">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="none">— {placeholder} —</SelectItem>
                {value === 'category_id'
                    ? categories.map((category) => (
                          <SelectItem
                              key={category.id}
                              value={String(category.id)}
                          >
                              {category.name}
                          </SelectItem>
                      ))
                    : suppliers.map((supplier) => (
                          <SelectItem
                              key={supplier.id}
                              value={String(supplier.id)}
                          >
                              {supplier.name}
                          </SelectItem>
                      ))}
            </SelectContent>
        </Select>
    );

    return (
        <form id="product-form" onSubmit={submit} className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="name">Nom du produit</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="Ex. : Riz parfumé 5kg"
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="sku">Référence (SKU)</Label>
                    <Input
                        id="sku"
                        value={form.data.sku}
                        onChange={(e) => form.setData('sku', e.target.value)}
                        placeholder="Ex. : RIZ-001"
                        required
                    />
                    <InputError message={form.errors.sku} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="barcode">Code-barres</Label>
                    <Input
                        id="barcode"
                        value={form.data.barcode}
                        onChange={(e) =>
                            form.setData('barcode', e.target.value)
                        }
                        placeholder="Optionnel"
                    />
                    <InputError message={form.errors.barcode} />
                </div>

                <div className="space-y-2">
                    <Label>Catégorie</Label>
                    {selectValue('category_id', 'Choisir…')}
                    <InputError message={form.errors.category_id} />
                </div>

                <div className="space-y-2">
                    <Label>Fournisseur</Label>
                    {selectValue('supplier_id', 'Choisir…')}
                    <InputError message={form.errors.supplier_id} />
                </div>

                <div className="space-y-2">
                    <Label>Unité de mesure</Label>
                    <Select
                        value={form.data.unit}
                        onValueChange={(v) => form.setData('unit', v)}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {UNITS.map((unit) => (
                                <SelectItem key={unit.value} value={unit.value}>
                                    {unit.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.unit} />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="purchase_price">Prix d’achat</Label>
                        <Input
                            id="purchase_price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.purchase_price}
                            onChange={(e) =>
                                form.setData('purchase_price', e.target.value)
                            }
                            placeholder="0"
                            required
                        />
                        <InputError message={form.errors.purchase_price} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="sale_price">Prix de vente</Label>
                        <Input
                            id="sale_price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.sale_price}
                            onChange={(e) =>
                                form.setData('sale_price', e.target.value)
                            }
                            placeholder="0"
                            required
                        />
                        <InputError message={form.errors.sale_price} />
                    </div>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="min_stock">Seuil d’alerte</Label>
                    <Input
                        id="min_stock"
                        type="number"
                        min="0"
                        value={form.data.min_stock}
                        onChange={(e) =>
                            form.setData('min_stock', e.target.value)
                        }
                    />
                    <p className="text-xs text-muted-foreground">
                        En dessous de ce seuil, le produit est signalé en stock
                        bas.
                    </p>
                    <InputError message={form.errors.min_stock} />
                </div>

                {!editing && (
                    <div className="space-y-2">
                        <Label htmlFor="stock_quantity">Stock initial</Label>
                        <Input
                            id="stock_quantity"
                            type="number"
                            min="0"
                            value={form.data.stock_quantity}
                            onChange={(e) =>
                                form.setData('stock_quantity', e.target.value)
                            }
                            placeholder="0"
                        />
                        <p className="text-xs text-muted-foreground">
                            Génère un mouvement d’entrée à la création.
                        </p>
                        <InputError message={form.errors.stock_quantity} />
                    </div>
                )}

                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="description">Description</Label>
                    <textarea
                        id="description"
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                        placeholder="Informations complémentaires…"
                        rows={2}
                        className="flex min-h-9 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none selection:bg-primary selection:text-primary-foreground file:text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                    />
                    <InputError message={form.errors.description} />
                </div>
            </div>

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {editing
                        ? 'Enregistrer les modifications'
                        : 'Créer le produit'}
                </Button>
            </DialogFooter>
        </form>
    );
}

function PaginationBar({ pagination }: { pagination: Paginated<Product> }) {
    const go = (url: string | null) => {
        if (!url) {
            return;
        }

        router.get(url, {}, { preserveState: true });
    };

    const numberedLinks = pagination.links.filter(
        (link) => !Number.isNaN(Number(link.label)),
    );

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground">
                {formatNumber(pagination.total)} produit
                {pagination.total > 1 ? 's' : ''} — page{' '}
                {pagination.current_page} sur {pagination.last_page}
            </p>
            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!pagination.prev_page_url}
                    onClick={() => go(pagination.prev_page_url)}
                >
                    Précédent
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
                    Suivant
                </Button>
            </div>
        </div>
    );
}

export default function Products({
    products,
    categories,
    suppliers,
    filters,
}: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const can = {
        create: permissions.includes('products.create'),
        update: permissions.includes('products.update'),
        delete: permissions.includes('products.delete'),
    };

    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryId, setCategoryId] = useState(filters.category_id ?? '');
    const [supplierId, setSupplierId] = useState(filters.supplier_id ?? '');
    const [lowStock, setLowStock] = useState(Boolean(filters.low_stock));

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Product | null>(null);

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

            if (categoryId) {
                query.category_id = categoryId;
            }

            if (supplierId) {
                query.supplier_id = supplierId;
            }

            if (lowStock) {
                query.low_stock = '1';
            }

            router.get(
                stock.products.index({ query }).url,
                {},
                {
                    preserveState: true,
                    replace: true,
                    only: ['products', 'filters'],
                },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search, categoryId, supplierId, lowStock]);

    const resetFilters = () => {
        setSearch('');
        setCategoryId('');
        setSupplierId('');
        setLowStock(false);
    };

    const hasFilters = Boolean(
        search.trim() || categoryId || supplierId || lowStock,
    );

    const openCreate = () => {
        setEditingProduct(null);
        setDialogOpen(true);
    };

    const openEdit = (product: Product) => {
        setEditingProduct(product);
        setDialogOpen(true);
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(stock.products.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <>
            <Head title="Produits" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Produits
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Gérez votre catalogue : références, prix et niveaux
                            de stock.
                        </p>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate} size="sm">
                            <Plus />
                            Nouveau produit
                        </Button>
                    )}
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher par nom, SKU ou code-barres…"
                            className="pl-9"
                        />
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Select
                            value={categoryId || 'none'}
                            onValueChange={(v) =>
                                setCategoryId(v === 'none' ? '' : v)
                            }
                        >
                            <SelectTrigger className="w-full sm:w-44">
                                <SelectValue placeholder="Toutes les catégories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Toutes les catégories
                                </SelectItem>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={supplierId || 'none'}
                            onValueChange={(v) =>
                                setSupplierId(v === 'none' ? '' : v)
                            }
                        >
                            <SelectTrigger className="w-full sm:w-44">
                                <SelectValue placeholder="Tous les fournisseurs" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Tous les fournisseurs
                                </SelectItem>
                                {suppliers.map((supplier) => (
                                    <SelectItem
                                        key={supplier.id}
                                        value={String(supplier.id)}
                                    >
                                        {supplier.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <label className="flex h-9 cursor-pointer items-center gap-2 rounded-md border border-input px-3 text-sm shadow-xs transition-colors hover:bg-accent">
                            <CheckboxPrimitive
                                checked={lowStock}
                                onCheckedChange={(checked) =>
                                    setLowStock(Boolean(checked))
                                }
                            />
                            Stock bas uniquement
                        </label>
                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={resetFilters}
                                className="h-9"
                            >
                                Réinitialiser
                            </Button>
                        )}
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Produit</TableHead>
                                <TableHead>Catégorie</TableHead>
                                <TableHead>Fournisseur</TableHead>
                                <TableHead className="text-right">
                                    Prix achat
                                </TableHead>
                                <TableHead className="text-right">
                                    Prix vente
                                </TableHead>
                                <TableHead className="text-right">
                                    Stock
                                </TableHead>
                                <TableHead className="text-right">
                                    Seuil
                                </TableHead>
                                {(can.update || can.delete) && (
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.data.map((product) => (
                                <TableRow key={product.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {product.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {product.sku}
                                            {product.barcode
                                                ? ` · ${product.barcode}`
                                                : ''}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {product.category ? (
                                            <Badge
                                                variant="outline"
                                                className="gap-1.5"
                                                style={{
                                                    borderColor: `${product.category.color ?? '#888'}40`,
                                                    backgroundColor: `${product.category.color ?? '#888'}12`,
                                                }}
                                            >
                                                <span
                                                    className="size-1.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            product.category
                                                                .color ??
                                                            '#888',
                                                    }}
                                                />
                                                {product.category.name}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {product.supplier?.name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatCurrency(product.purchase_price)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {formatCurrency(product.sale_price)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <span className="font-medium tabular-nums">
                                                {formatNumber(
                                                    product.stock_quantity,
                                                )}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {unitLabel(product.unit)}
                                            </span>
                                            {product.stock_quantity === 0 && (
                                                <Badge variant="destructive">
                                                    Rupture
                                                </Badge>
                                            )}
                                            {product.stock_quantity > 0 &&
                                                product.is_low_stock && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                                    >
                                                        <AlertTriangle />
                                                        Bas
                                                    </Badge>
                                                )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {formatNumber(product.min_stock)}
                                    </TableCell>
                                    {(can.update || can.delete) && (
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {can.update && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEdit(product)
                                                        }
                                                        aria-label={`Modifier ${product.name}`}
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
                                                                product,
                                                            )
                                                        }
                                                        aria-label={`Supprimer ${product.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                            {products.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="h-40 text-center text-muted-foreground"
                                    >
                                        <div className="flex flex-col items-center gap-2">
                                            <Search className="size-6 opacity-40" />
                                            <p className="text-sm font-medium">
                                                Aucun produit trouvé
                                            </p>
                                            <p className="text-xs">
                                                {hasFilters
                                                    ? 'Essayez de modifier vos filtres.'
                                                    : 'Commencez par créer votre premier produit.'}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <PaginationBar pagination={products} />
            </div>

            {/* Create / edit dialog */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingProduct
                                ? 'Modifier le produit'
                                : 'Nouveau produit'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingProduct
                                ? `Mise à jour de « ${editingProduct.name} ». Le stock se modifie via les mouvements.`
                                : 'Renseignez les informations de la nouvelle référence.'}
                        </DialogDescription>
                    </DialogHeader>
                    {dialogOpen && (
                        <ProductForm
                            key={editingProduct?.id ?? 'new'}
                            product={editingProduct}
                            categories={categories}
                            suppliers={suppliers}
                            onDone={() => setDialogOpen(false)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            {/* Delete confirmation */}
            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Supprimer le produit</DialogTitle>
                        <DialogDescription>
                            Voulez-vous vraiment supprimer «{' '}
                            {deleteTarget?.name} » ? Son historique de
                            mouvements sera également supprimé. Cette action est
                            irréversible.
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

Products.layout = {
    breadcrumbs: [
        {
            title: 'Produits',
            href: stock.products.index().url,
        },
    ],
};
