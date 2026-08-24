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

export type DashboardReceivables = {
    total_outstanding: number;
    overdue_total: number;
    overdue_sales: number;
    customers_with_balance: number;
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

export type PaymentMethod =
    'cash' | 'card' | 'transfer' | 'tmoney' | 'flooz' | 'credit' | 'avoir';

export type CreditStatus = 'paid' | 'outstanding' | 'overdue';

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
    customer: { id: number; name: string; phone: string | null } | null;
    credit_due_at: string | null;
    paid_amount: number;
    balance: number;
    credit_status: CreditStatus;
    credit_label: string;
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
    payments: CustomerPayment[] | null;
};

export type CustomerPayment = {
    id: number;
    amount: number;
    payment_method: PaymentMethod;
    payment_label: string;
    mobile_phone: string | null;
    mobile_reference: string | null;
    notes: string | null;
    paid_at: string;
    user: { id: number; name: string } | null;
};

export type SaleReturnItem = {
    id: number;
    product_id: number;
    quantity: number;
    unit_price: number;
    product: Pick<Product, 'id' | 'name' | 'sku' | 'unit'>;
};

export type SaleReturn = {
    id: number;
    reference: string;
    sale_reference: string | null;
    customer: { id: number; name: string } | null;
    total: number;
    refund_method: string;
    refund_label: string;
    refund_amount: number;
    applied_to_balance: number;
    notes: string | null;
    returned_at: string;
    user: { id: number; name: string } | null;
    items: SaleReturnItem[];
};

export type CreditNote = {
    id: number;
    reference: string;
    amount: number;
    used_amount: number;
    remaining: number;
    status: 'issued' | 'used';
    issued_at: string;
    notes: string | null;
    customer: Pick<Customer, 'id' | 'name'> | null;
    return_reference?: string | null;
    user?: { id: number; name: string } | null;
};

export type PosCustomer = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    available_avoirs: number;
};

export type ReceivableStats = {
    total_outstanding: number;
    overdue_total: number;
    customers_with_balance: number;
    overdue_sales: number;
};

export type ReceivableCustomer = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    sales_count: number;
    total_due: number;
    total_paid: number;
    balance: number;
    overdue_balance: number;
    overdue_sales: number;
    available_avoirs: number;
};

export type ReceivableSale = {
    id: number;
    reference: string;
    created_at: string;
    credit_due_at: string | null;
    total: number;
    paid_amount: number;
    returned_value: number;
    balance: number;
    credit_status: CreditStatus;
    credit_label: string;
    items: SaleItem[];
    payments: CustomerPayment[];
    returns: {
        reference: string;
        total: number;
        applied_to_balance: number;
        refund_method: string | null;
        refund_amount: number;
        returned_at: string;
    }[];
};

export type CustomerDetail = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    balance: number;
    overdue_balance: number;
    available_avoirs: number;
    sales: ReceivableSale[];
    credit_notes: CreditNote[];
};

export type ReturnFormSale = {
    id: number;
    reference: string;
    created_at: string;
    payment_method: PaymentMethod;
    payment_label: string;
    total: number;
    customer: { id: number; name: string; phone: string | null } | null;
    balance: number;
    items: {
        id: number;
        product_id: number;
        quantity: number;
        returned_quantity: number;
        returnable: number;
        unit_price: number;
        product: Pick<Product, 'id' | 'name' | 'sku' | 'unit'>;
    }[];
};

export type ReturnsStats = {
    returns_count: number;
    returns_value: number;
    refunded: number;
    avoirs_available: number;
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
    credit_excluded: number;
    credit_excluded_count: number;
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

export type PurchaseOrderPaymentStatus = 'unpaid' | 'partial' | 'paid';

export type SupplierPayment = {
    id: number;
    amount: number;
    payment_method: PaymentMethod;
    payment_label: string;
    reference: string | null;
    notes: string | null;
    paid_at: string;
    user: { id: number; name: string } | null;
};

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
    due_at: string | null;
    paid_amount: number;
    balance: number;
    payment_status: PurchaseOrderPaymentStatus;
    payment_label: string;
    is_overdue: boolean;
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
    payments?: SupplierPayment[];
};

export type SupplierPayableStats = {
    total_outstanding: number;
    overdue_total: number;
    suppliers_with_balance: number;
    overdue_orders: number;
};

export type SupplierPayableSupplier = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    orders_count: number;
    total_due: number;
    total_paid: number;
    balance: number;
    overdue_balance: number;
    overdue_orders: number;
};

export type SupplierPayableOrder = {
    id: number;
    reference: string | null;
    status: PurchaseOrderStatus;
    created_at: string;
    due_at: string | null;
    total: number;
    paid_amount: number;
    balance: number;
    payment_status: PurchaseOrderPaymentStatus;
    payment_label: string;
    is_overdue: boolean;
    items: {
        id: number;
        product_id: number;
        quantity: number;
        received_quantity: number;
        unit_price: number;
        product: Pick<Product, 'id' | 'name' | 'sku' | 'unit'>;
    }[];
    payments: SupplierPayment[];
};

export type SupplierPayableDetail = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    balance: number;
    overdue_balance: number;
    orders: SupplierPayableOrder[];
};

export type ExpenseCategory =
    | 'loyer'
    | 'salaires'
    | 'electricite'
    | 'eau'
    | 'transport'
    | 'fournitures'
    | 'entretien'
    | 'communication'
    | 'fiscalite'
    | 'divers';

export type ExpenseCategoryLabel = Record<ExpenseCategory, string>;

export type Expense = {
    id: number;
    reference: string;
    category: ExpenseCategory;
    category_label: string;
    amount: number;
    payment_method: PaymentMethod;
    payment_label: string;
    expense_date: string;
    description: string | null;
    notes: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
};

export type ExpenseStats = {
    total: number;
    count: number;
    by_category: Record<string, number>;
};

export type MonthlyClosing = {
    id: number;
    year: number;
    month: number;
    label: string;
    status: string;
    revenue: number;
    sales_count: number;
    revenue_ht: number;
    purchases: number;
    purchases_ht: number;
    purchases_count: number;
    expenses: number;
    expenses_count: number;
    vat_collected: number;
    vat_deductible: number;
    vat_net: number;
    gross_margin: number;
    net_result: number;
    notes: string | null;
    closed_at: string;
    user: { id: number; name: string } | null;
};

export type MonthlyClosingPreview = {
    revenue: number;
    sales_count: number;
    revenue_ht: number;
    purchases_ht: number;
    purchases_ttc: number;
    purchases_count: number;
    expenses: number;
    expenses_count: number;
    vat_collected: number;
    vat_deductible: number;
    vat_net: number;
    gross_margin: number;
    net_result: number;
    credit_excluded: number;
    credit_excluded_count: number;
    already_closed: boolean;
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

export type NotificationType = 'stock_low' | 'stock_out' | 'receivable_overdue' | 'supplier_overdue';

export type AppNotification = {
    id: number;
    type: NotificationType;
    title: string;
    message: string | null;
    link: string | null;
    is_read: boolean;
    created_at: string;
};
