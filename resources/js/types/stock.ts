export type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string | null;
    products_count?: number;
};

export type Supplier = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    products_count?: number;
};

export type Product = {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    category_id: number | null;
    supplier_id: number | null;
    unit: string;
    purchase_price: string | number;
    sale_price: string | number;
    stock_quantity: number;
    min_stock: number;
    description: string | null;
    is_low_stock: boolean;
    category: Pick<Category, 'id' | 'name' | 'color'> | null;
    supplier: Pick<Supplier, 'id' | 'name'> | null;
};

export type MovementType = 'in' | 'out' | 'adjustment';

export type StockMovement = {
    id: number;
    product_id: number;
    type: MovementType;
    quantity: number;
    reason: string | null;
    reference: string | null;
    created_at: string;
    product: Pick<Product, 'id' | 'name' | 'sku' | 'unit'>;
    user: { id: number; name: string } | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    first_page_url: string | null;
    last_page_url: string | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

export type DashboardStats = {
    total_products: number;
    total_units: number;
    stock_value: number;
    out_of_stock: number;
    low_stock: number;
};

export type ReportSummary = {
    total_products: number;
    total_units: number;
    stock_value: number;
};

export type CategoryReport = {
    id: number;
    name: string;
    color: string | null;
    products_count: number;
    stock_value: number;
    percentage: number;
};

export type MovementReport = {
    total: number;
    in_count: number;
    out_count: number;
    adjustment_count: number;
    in_quantity: number;
    out_quantity: number;
};

export type PaymentMethod = 'cash' | 'card' | 'transfer';

export type SaleStatus = 'completed' | 'cancelled';

export type SaleItem = {
    id: number;
    product_id: number;
    quantity: number;
    unit_price: number;
    product: Pick<Product, 'id' | 'name' | 'sku' | 'unit'>;
};

export type Sale = {
    id: number;
    reference: string;
    payment_method: PaymentMethod;
    payment_label: string;
    total: number;
    status: SaleStatus;
    notes: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
    items: SaleItem[];
};

export type PosProduct = Pick<
    Product,
    'id' | 'name' | 'sku' | 'barcode' | 'unit' | 'sale_price' | 'stock_quantity'
>;

export type PurchaseOrder = {
    id: number;
    supplier_id: number | null;
    reference: string | null;
    status: 'received';
    total_quantity: number;
    created_at: string;
    user: { id: number; name: string } | null;
    supplier: Pick<Supplier, 'id' | 'name'> | null;
    items: {
        id: number;
        product_id: number;
        quantity: number;
        product: Pick<Product, 'id' | 'name' | 'sku' | 'unit'>;
    }[];
};

export type ReplenishmentProduct = {
    id: number;
    name: string;
    sku: string;
    unit: string;
    stock_quantity: number;
    min_stock: number;
    suggested_quantity: number;
    category: Pick<Category, 'id' | 'name'> | null;
    supplier: Pick<Supplier, 'id' | 'name'> | null;
};
