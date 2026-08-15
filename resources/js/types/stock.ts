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
    warehouse: Pick<Warehouse, 'id' | 'name' | 'code'> | null;
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

export type PaymentMethod = 'cash' | 'card' | 'transfer' | 'tmoney' | 'flooz';

export type TaxRate = {
    id: number;
    name: string;
    rate: number;
    is_default: boolean;
    is_active: boolean;
};

export type TaxRateOption = Pick<
    TaxRate,
    'id' | 'name' | 'rate' | 'is_default'
>;

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
    discount_percent: number;
    tax_percent: number;
    tax_amount: number;
    mobile_phone: string | null;
    mobile_reference: string | null;
    status: SaleStatus;
    notes: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
    items: SaleItem[];
    invoice: { id: number; reference: string } | null;
};

export type Customer = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    quotes_count?: number;
    invoices_count?: number;
    created_at: string;
};

export type CustomerOption = Pick<Customer, 'id' | 'name' | 'email'>;

export type QuoteStatus =
    'draft' | 'sent' | 'accepted' | 'converted' | 'rejected';

export type QuoteItem = {
    id: number;
    product_id: number | null;
    product_name: string;
    quantity: number;
    unit_price: number;
};

export type Quote = {
    id: number;
    reference: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    customer_address: string | null;
    status: QuoteStatus;
    valid_until: string | null;
    notes: string | null;
    subtotal: number;
    discount_percent: number;
    discount_amount: number;
    tax_rate_id: number | null;
    tax_percent: number;
    tax_amount: number;
    total: number;
    created_at: string;
    user: { id: number; name: string } | null;
    items: QuoteItem[];
    sale_reference: string | null;
};

export type QuoteFormProduct = Pick<
    Product,
    'id' | 'name' | 'sku' | 'sale_price'
>;

export type Invoice = {
    id: number;
    reference: string;
    sale_id: number | null;
    sale_reference: string | null;
    customer_name: string;
    customer_email: string | null;
    customer_address: string | null;
    issued_at: string;
    total: number;
    discount_percent: number;
    notes: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
};

export type SalesReportSummary = {
    revenue: number;
    sales_count: number;
    average_basket: number;
    margin: number;
    margin_rate: number;
};

export type DailyRevenue = {
    day: string;
    sales_count: number;
    revenue: number;
};

export type PaymentBreakdown = {
    payment_method: PaymentMethod;
    payment_label: string;
    sales_count: number;
    revenue: number;
};

export type TopProduct = {
    product_id: number;
    name: string;
    sku: string;
    quantity: number;
    revenue: number;
    cost: number;
    margin: number;
    margin_rate: number;
};

export type UninvoicedSale = Pick<
    Sale,
    'id' | 'reference' | 'total' | 'created_at' | 'payment_method'
>;

export type FinancialSummary = {
    revenue: number;
    revenue_ht: number;
    vat_collected: number;
    sales_count: number;
    purchases: number;
    purchases_vat: number;
    purchases_ttc: number;
    cost_of_goods: number;
    gross_margin: number;
    margin_rate: number;
};

export type VatByRate = {
    name: string;
    rate: number;
    base: number;
    vat: number;
    ttc?: number;
};

export type FinancialPurchase = {
    reference: string | null;
    supplier: string;
    date: string | null;
    rate: number;
    rate_name: string;
    amount_ht: number;
    vat: number;
    amount_ttc: number;
};

export type VatByRateSummary = {
    rate: number;
    name: string;
    collected: number;
    deductible: number;
    net: number;
};

export type VatSummary = {
    collected: number;
    deductible: number;
    net: number;
    by_rate: VatByRateSummary[];
    filters: {
        from: string;
        to: string;
    };
};

export type CashSession = {
    id: number;
    opened_at: string;
    closed_at: string | null;
    opening_cash: number;
    expected_cash: number | null;
    counted_cash: number | null;
    difference: number | null;
    cash_total: number;
    sales_count: number;
    is_open: boolean;
    notes: string | null;
    user: { id: number; name: string } | null;
};

export type PosProduct = Pick<
    Product,
    'id' | 'name' | 'sku' | 'barcode' | 'unit' | 'sale_price' | 'stock_quantity'
> & {
    stock_by_warehouse?: Record<number, number>;
};

export type Warehouse = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    is_default: boolean;
    products_count?: number;
    total_units?: number;
};

export type StockTransfer = {
    id: number;
    product_id: number;
    from_warehouse_id: number;
    to_warehouse_id: number;
    quantity: number;
    notes: string | null;
    created_at: string;
    product: Pick<Product, 'id' | 'name' | 'sku' | 'unit'>;
    from_warehouse: Pick<Warehouse, 'id' | 'name' | 'code'>;
    to_warehouse: Pick<Warehouse, 'id' | 'name' | 'code'>;
    user: { id: number; name: string } | null;
};

export type PurchaseOrderStatus = 'draft' | 'ordered' | 'partial' | 'received';

export type PurchaseOrder = {
    id: number;
    supplier_id: number | null;
    reference: string | null;
    status: PurchaseOrderStatus;
    tax_rate_id: number | null;
    tax_percent: number;
    amount_ht: number;
    tax_amount: number;
    amount_ttc: number;
    total_quantity: number;
    received_quantity: number;
    progress: number;
    created_at: string;
    user: { id: number; name: string } | null;
    supplier: Pick<Supplier, 'id' | 'name'> | null;
    warehouse: Pick<Warehouse, 'id' | 'name' | 'code'> | null;
    items: {
        id: number;
        product_id: number;
        quantity: number;
        received_quantity: number;
        remaining: number;
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
