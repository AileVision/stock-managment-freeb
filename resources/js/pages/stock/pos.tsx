import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Barcode,
    CheckCircle2,
    Minus,
    PackageX,
    Plus,
    Printer,
    ScanBarcode,
    ShoppingCart,
    Trash2,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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
    formatCurrency,
    formatDate,
    PAYMENT_METHOD_LABELS,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import stock from '@/routes/stock';
import type { Auth, PaymentMethod, PosProduct, Sale } from '@/types';

type Props = {
    products: PosProduct[];
};

type CartItem = {
    product: PosProduct;
    quantity: number;
};

export default function Pos({ products }: Props) {
    const { permissions } = usePage<{ auth: Auth }>().props.auth;
    const canCreate = permissions.includes('sales.create');

    const sale = usePage<{ sale?: Sale | null }>().props.sale ?? null;

    const [cart, setCart] = useState<Record<number, number>>({});
    const [scanValue, setScanValue] = useState('');
    const [search, setSearch] = useState('');
    // Référence de la vente déjà affichée/fermée : le ticket ne se rouvre
    // que lorsqu'une nouvelle vente est enregistrée.
    const [dismissedSaleRef, setDismissedSaleRef] = useState<string | null>(
        null,
    );
    const receiptOpen = sale !== null && sale.reference !== dismissedSaleRef;

    const scanInputRef = useRef<HTMLInputElement>(null);

    const form = useForm<{
        items: { product_id: number; quantity: number }[];
        payment_method: PaymentMethod;
    }>({
        items: [],
        payment_method: 'cash',
    });

    const dismissReceipt = () => {
        if (sale) {
            setDismissedSaleRef(sale.reference);
        }
    };

    const availableQuantity = (product: PosProduct) =>
        product.stock_quantity - (cart[product.id] ?? 0);

    const addToCart = (product: PosProduct) => {
        if (availableQuantity(product) <= 0) {
            return;
        }

        setCart((current) => ({
            ...current,
            [product.id]: (current[product.id] ?? 0) + 1,
        }));
    };

    const changeQuantity = (product: PosProduct, delta: number) => {
        setCart((current) => {
            const next = (current[product.id] ?? 0) + delta;

            if (next <= 0) {
                const rest = { ...current };
                delete rest[product.id];

                return rest;
            }

            const max = product.stock_quantity;

            return { ...current, [product.id]: Math.min(next, max) };
        });
    };

    const removeFromCart = (productId: number) => {
        setCart((current) => {
            const rest = { ...current };
            delete rest[productId];

            return rest;
        });
    };

    const cartItems: CartItem[] = useMemo(
        () =>
            Object.entries(cart)
                .map(([productId, quantity]) => ({
                    product: products.find(
                        (product) => product.id === Number(productId),
                    ),
                    quantity,
                }))
                .filter((item): item is CartItem => item.product !== undefined),
        [cart, products],
    );

    const total = cartItems.reduce(
        (sum, item) => sum + Number(item.product.sale_price) * item.quantity,
        0,
    );

    const filteredProducts = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return products;
        }

        return products.filter(
            (product) =>
                product.name.toLowerCase().includes(query) ||
                product.sku.toLowerCase().includes(query) ||
                (product.barcode ?? '').toLowerCase().includes(query),
        );
    }, [products, search]);

    const handleScan = (event: FormEvent) => {
        event.preventDefault();

        const value = scanValue.trim();

        if (!value) {
            scanInputRef.current?.focus();

            return;
        }

        const product = products.find(
            (candidate) =>
                (candidate.barcode &&
                    candidate.barcode.toLowerCase() === value.toLowerCase()) ||
                candidate.sku.toLowerCase() === value.toLowerCase(),
        );

        if (product) {
            addToCart(product);
        } else {
            setSearch(value);
        }

        setScanValue('');
        scanInputRef.current?.focus();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (cartItems.length === 0) {
            return;
        }

        form.setData(
            'items',
            cartItems.map((item) => ({
                product_id: item.product.id,
                quantity: item.quantity,
            })),
        );

        form.post(stock.sales.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                setCart({});
                form.reset();
            },
        });
    };

    return (
        <>
            <Head title="Caisse" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="text-xl font-semibold tracking-tight">
                            Caisse
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Scannez un code-barres ou ajoutez des produits pour
                            composer la vente.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <a href={stock.sales.index().url}>
                            Historique des ventes
                        </a>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-5">
                    {/* Product area */}
                    <div className="flex flex-col gap-4 lg:col-span-3">
                        <form
                            onSubmit={handleScan}
                            className="flex flex-col gap-3"
                        >
                            <div className="relative">
                                <ScanBarcode className="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    ref={scanInputRef}
                                    value={scanValue}
                                    onChange={(e) =>
                                        setScanValue(e.target.value)
                                    }
                                    placeholder="Scanner un code-barres, puis Entrée…"
                                    className="h-12 pl-10 text-base"
                                    autoFocus
                                    aria-label="Scanner un code-barres"
                                />
                            </div>
                            <div className="relative">
                                <Barcode className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Rechercher par nom, SKU ou code-barres…"
                                    className="pl-9"
                                    aria-label="Rechercher un produit"
                                />
                            </div>
                        </form>

                        {filteredProducts.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 rounded-xl border py-10 text-center text-muted-foreground">
                                <PackageX className="size-6 opacity-40" />
                                <p className="text-sm font-medium">
                                    Aucun produit trouvé
                                </p>
                                <p className="text-xs">
                                    {search.trim()
                                        ? 'Essayez un autre code-barres ou une autre recherche.'
                                        : 'Créez d’abord des produits dans le catalogue.'}
                                </p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                                {filteredProducts.map((product) => {
                                    const remaining =
                                        availableQuantity(product);
                                    const soldOut = remaining <= 0;

                                    return (
                                        <button
                                            key={product.id}
                                            type="button"
                                            disabled={soldOut}
                                            onClick={() => addToCart(product)}
                                            className={cn(
                                                'flex flex-col gap-2 rounded-xl border p-3 text-left transition-colors',
                                                soldOut
                                                    ? 'cursor-not-allowed border-muted opacity-50'
                                                    : 'hover:border-ring hover:bg-accent/50 active:scale-[0.98]',
                                            )}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <p className="line-clamp-2 text-sm font-medium">
                                                    {product.name}
                                                </p>
                                                <Plus className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {product.sku}
                                                {product.barcode
                                                    ? ` · ${product.barcode}`
                                                    : ''}
                                            </p>
                                            <div className="mt-auto flex items-center justify-between gap-2">
                                                <span className="font-semibold tabular-nums">
                                                    {formatCurrency(
                                                        product.sale_price,
                                                    )}
                                                </span>
                                                {soldOut ? (
                                                    <Badge variant="destructive">
                                                        Rupture
                                                    </Badge>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {remaining} restant
                                                        {remaining > 1
                                                            ? 's'
                                                            : ''}
                                                    </span>
                                                )}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* Cart */}
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        <div className="flex flex-col rounded-xl border">
                            <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <ShoppingCart className="size-4 text-muted-foreground" />
                                    <h3 className="text-sm font-semibold">
                                        Panier
                                    </h3>
                                </div>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {cartItems.length} article
                                    {cartItems.length > 1 ? 's' : ''}
                                </span>
                            </div>

                            <div className="flex max-h-96 flex-col gap-1 overflow-y-auto p-3">
                                {cartItems.length === 0 ? (
                                    <div className="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground">
                                        <ShoppingCart className="size-6 opacity-40" />
                                        <p className="text-sm font-medium">
                                            Panier vide
                                        </p>
                                        <p className="text-xs">
                                            Scannez ou cliquez sur un produit
                                            pour l’ajouter.
                                        </p>
                                    </div>
                                ) : (
                                    cartItems.map(({ product, quantity }) => (
                                        <div
                                            key={product.id}
                                            className="flex items-center gap-3 rounded-lg border p-2.5"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">
                                                    {product.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground tabular-nums">
                                                    {formatCurrency(
                                                        product.sale_price,
                                                    )}{' '}
                                                    × {quantity}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7"
                                                    onClick={() =>
                                                        changeQuantity(
                                                            product,
                                                            -1,
                                                        )
                                                    }
                                                    aria-label="Diminuer la quantité"
                                                >
                                                    <Minus className="size-3.5" />
                                                </Button>
                                                <span className="w-7 text-center text-sm font-semibold tabular-nums">
                                                    {quantity}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7"
                                                    disabled={
                                                        availableQuantity(
                                                            product,
                                                        ) <= 0
                                                    }
                                                    onClick={() =>
                                                        changeQuantity(
                                                            product,
                                                            1,
                                                        )
                                                    }
                                                    aria-label="Augmenter la quantité"
                                                >
                                                    <Plus className="size-3.5" />
                                                </Button>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 text-red-600 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400"
                                                onClick={() =>
                                                    removeFromCart(product.id)
                                                }
                                                aria-label={`Retirer ${product.name}`}
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </div>
                                    ))
                                )}
                            </div>

                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-3 border-t p-4"
                            >
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Total à payer
                                    </span>
                                    <span className="text-lg font-semibold tabular-nums">
                                        {formatCurrency(total)}
                                    </span>
                                </div>

                                <div className="space-y-2">
                                    <Label>Mode de paiement</Label>
                                    <Select
                                        value={form.data.payment_method}
                                        onValueChange={(v) =>
                                            form.setData(
                                                'payment_method',
                                                v as PaymentMethod,
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {(
                                                Object.entries(
                                                    PAYMENT_METHOD_LABELS,
                                                ) as [PaymentMethod, string][]
                                            ).map(([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.items} />
                                </div>

                                {canCreate && (
                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={
                                            cartItems.length === 0 ||
                                            form.processing
                                        }
                                    >
                                        Valider la vente
                                    </Button>
                                )}
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {/* Receipt */}
            <Dialog
                open={receiptOpen}
                onOpenChange={(open) => !open && dismissReceipt()}
            >
                <DialogContent className="sm:max-w-sm">
                    {sale && (
                        <>
                            <DialogHeader className="items-center text-center">
                                <span className="flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle2 className="size-6" />
                                </span>
                                <DialogTitle className="text-center">
                                    Vente {sale.reference}
                                </DialogTitle>
                            </DialogHeader>

                            <div className="print-area space-y-3 rounded-xl border p-4">
                                <div className="text-center">
                                    <p className="text-sm font-semibold">
                                        {sale.payment_label}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatDate(sale.created_at)}
                                    </p>
                                </div>
                                <div className="space-y-1.5 border-t border-dashed pt-3">
                                    {sale.items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center justify-between gap-3 text-sm"
                                        >
                                            <span className="min-w-0 flex-1 truncate">
                                                {item.product.name}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground tabular-nums">
                                                {item.quantity} ×{' '}
                                                {formatCurrency(
                                                    item.unit_price,
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                                <div className="flex items-center justify-between border-t border-dashed pt-3 text-sm font-semibold">
                                    <span>Total</span>
                                    <span className="tabular-nums">
                                        {formatCurrency(sale.total)}
                                    </span>
                                </div>
                            </div>

                            <DialogFooter className="gap-2 sm:justify-center">
                                <Button
                                    variant="outline"
                                    onClick={() => window.print()}
                                >
                                    <Printer />
                                    Imprimer le ticket
                                </Button>
                                <Button
                                    onClick={() => {
                                        dismissReceipt();
                                        router.reload({ only: ['products'] });
                                    }}
                                >
                                    Nouvelle vente
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Pos.layout = {
    breadcrumbs: [
        {
            title: 'Caisse',
            href: stock.sales.pos().url,
        },
    ],
};
