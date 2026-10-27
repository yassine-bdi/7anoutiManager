import { useEffect, useMemo, useRef, useState } from 'react';

const currencyFormatter = new Intl.NumberFormat('fr-MA', {
    style: 'currency',
    currency: 'MAD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const numberFormatter = new Intl.NumberFormat('fr-MA');

const dateTimeFormatter = new Intl.DateTimeFormat('fr-MA', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const dateFormatter = new Intl.DateTimeFormat('fr-MA', {
    dateStyle: 'medium',
});

const financePaymentMethods = ['cash', 'card', 'bank', 'transfer', 'mobile', 'credit'];
const defaultPerPage = 10;
const posProductsPerPage = 100;
const searchDebounceMs = 280;

const initialPagination = {
    current_page: 1,
    last_page: 1,
    per_page: defaultPerPage,
    total: 0,
};

const initialSummary = {
    generated_at: null,
    stock: {
        total_items: 0,
        low_stock_items: 0,
        inventory_value: 0,
        top_categories: [],
    },
    finance: {
        month_income: 0,
        potential_income: 0,
        month_expenses: 0,
        net_result: 0,
        cash_in_hand: 0,
    },
    credits: {
        active_customers: 0,
        total_due: 0,
        overdue_amount: 0,
        due_this_week: 0,
    },
};

const initialStockOverview = {
    movements_this_month: 0,
    top_categories: [],
};

const initialFinanceOverview = {
    potential_income: 0,
    entries_this_month: 0,
    top_expense_categories: [],
};

const initialCreditsOverview = {
    open_credits: 0,
    upcoming_credits: [],
    top_customers_by_due: [],
};

const initialFinanceTotals = {
    income_total: 0,
    potential_income: 0,
    expense_total: 0,
    net_result: 0,
};

const initialCreditsTotals = {
    total_due: 0,
    overdue_amount: 0,
    due_this_week: 0,
};

const initialStockProducts = [];
const initialCategories = [];
const initialFinanceEntries = [];
const initialFinanceCategories = [];
const initialCreditEntries = [];
const initialCreditCustomers = [];
const initialStockSearch = '';
const initialPosSearch = '';

const initialProductForm = {
    product_category_id: '',
    name: '',
    remove_image: false,
    current_image_url: '',
    default_image_url: '/images/product-default.svg',
    unit: 'piece',
    stock_quantity: '0',
    low_stock_threshold: '0',
    purchase_price: '0',
    selling_price: '0',
    is_active: true,
};

const initialFinanceFilters = {
    entry_type: '',
    category: '',
    occurred_from: '',
    occurred_to: '',
};

const getTodayDate = () => new Date().toISOString().slice(0, 10);
const createEmptySaleLine = (defaultProductId = '') => ({ product_id: defaultProductId, quantity: '1' });
const createEmptyStockInLine = (defaultProductId = '') => ({
    product_id: defaultProductId,
    quantity: '1',
    unit_cost: '',
    selling_price: '',
});

const initialFinanceSaleForm = {
    items: [],
    occurred_on: getTodayDate(),
    payment_method: 'cash',
    customer_mode: 'existing',
    customer_id: '',
    new_customer_name: '',
    new_customer_phone: '',
    new_customer_address: '',
    new_customer_notes: '',
    note: '',
};

const initialCreditsFilters = {
    status: '',
    customer_id: '',
    overdue_only: false,
};

const initialStockInForm = {
    items: [createEmptyStockInLine()],
    moved_at: getTodayDate(),
    note: '',
};

const initialCustomerForm = {
    name: '',
    phone: '',
    address: '',
    notes: '',
};

const initialCreditEntryForm = {
    customer_id: '',
    total_amount: '',
    opened_on: getTodayDate(),
    due_date: '',
    note: '',
};

const initialCreditEditForm = {
    id: '',
    customer_id: '',
    total_amount: '',
    opened_on: getTodayDate(),
    due_date: '',
    note: '',
};

const initialCreditPaymentForm = {
    customer_credit_id: '',
    amount: '',
    paid_at: getTodayDate(),
    note: '',
};

function formatCurrency(value) {
    return currencyFormatter.format(Number(value ?? 0));
}

function formatNumber(value) {
    return numberFormatter.format(Number(value ?? 0));
}

function formatDateTime(value) {
    if (!value) {
        return 'n/a';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return 'n/a';
    }

    return dateTimeFormatter.format(parsed);
}

function formatDate(value) {
    if (!value) {
        return 'n/a';
    }

    const parsed = /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? new Date(`${value}T12:00:00`)
        : new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return 'n/a';
    }

    return dateFormatter.format(parsed);
}

function buildStockProductsPath(page = 1, perPage = defaultPerPage, search = initialStockSearch) {
    const params = new URLSearchParams({
        page: String(page),
        per_page: String(perPage),
    });

    if (search.trim()) {
        params.set('search', search.trim());
    }

    return `/api/v1/stock/products?${params.toString()}`;
}

function buildFinanceEntriesPath(filters = initialFinanceFilters, page = 1, perPage = defaultPerPage) {
    const params = new URLSearchParams();

    params.set('page', String(page));
    params.set('per_page', String(perPage));

    if (filters.entry_type) {
        params.set('entry_type', filters.entry_type);
    }

    if (filters.category) {
        params.set('category', filters.category);
    }

    if (filters.occurred_from) {
        params.set('occurred_from', filters.occurred_from);
    }

    if (filters.occurred_to) {
        params.set('occurred_to', filters.occurred_to);
    }

    const queryString = params.toString();

    return queryString ? `/api/v1/finance/entries?${queryString}` : '/api/v1/finance/entries';
}

function buildCreditsEntriesPath(filters = initialCreditsFilters, page = 1, perPage = defaultPerPage) {
    const params = new URLSearchParams();

    params.set('page', String(page));
    params.set('per_page', String(perPage));

    if (filters.status) {
        params.set('status', filters.status);
    }

    if (filters.customer_id) {
        params.set('customer_id', filters.customer_id);
    }

    if (filters.overdue_only) {
        params.set('overdue_only', '1');
    }

    const queryString = params.toString();

    return queryString ? `/api/v1/credits/entries?${queryString}` : '/api/v1/credits/entries';
}

async function fetchJson(endpoint, options = {}) {
    const shouldSendJsonBody = options.body && !(options.body instanceof FormData);

    const response = await fetch(endpoint, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(shouldSendJsonBody ? { 'Content-Type': 'application/json' } : {}),
            ...(options.headers ?? {}),
        },
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const validationErrors = payload?.errors
            ? Object.values(payload.errors).flat().join(' ')
            : '';

        throw new Error(validationErrors || payload?.message || 'Erreur API.');
    }

    return payload;
}

function DashboardApp() {
    const [activeWorkspace, setActiveWorkspace] = useState('stock');

    const [summary, setSummary] = useState(initialSummary);
    const [stockOverview, setStockOverview] = useState(initialStockOverview);
    const [financeOverview, setFinanceOverview] = useState(initialFinanceOverview);
    const [creditsOverview, setCreditsOverview] = useState(initialCreditsOverview);

    const [stockProducts, setStockProducts] = useState(initialStockProducts);
    const [posProducts, setPosProducts] = useState(initialStockProducts);
    const [stockCategories, setStockCategories] = useState(initialCategories);
    const [productForm, setProductForm] = useState(initialProductForm);
    const [productImageFile, setProductImageFile] = useState(null);
    const [productImagePreviewUrl, setProductImagePreviewUrl] = useState('');
    const [editingProductId, setEditingProductId] = useState(null);
    const [stockInForm, setStockInForm] = useState(initialStockInForm);
    const [stockActionLoading, setStockActionLoading] = useState(false);
    const [stockActionMessage, setStockActionMessage] = useState('');
    const [stockActionError, setStockActionError] = useState('');
    const [stockPagination, setStockPagination] = useState(initialPagination);
    const [stockSearchTerm, setStockSearchTerm] = useState(initialStockSearch);
    const [stockSearchInput, setStockSearchInput] = useState(initialStockSearch);
    const [posSearchTerm, setPosSearchTerm] = useState(initialPosSearch);
    const [posSearchInput, setPosSearchInput] = useState(initialPosSearch);
    const [stockSearchLoading, setStockSearchLoading] = useState(false);
    const [posSearchLoading, setPosSearchLoading] = useState(false);

    const [financeEntries, setFinanceEntries] = useState(initialFinanceEntries);
    const [financeCategories, setFinanceCategories] = useState(initialFinanceCategories);
    const [financeTotals, setFinanceTotals] = useState(initialFinanceTotals);
    const [financeFilters, setFinanceFilters] = useState(initialFinanceFilters);
    const [financeSaleForm, setFinanceSaleForm] = useState(initialFinanceSaleForm);
    const [financeActionLoading, setFinanceActionLoading] = useState(false);
    const [financeActionMessage, setFinanceActionMessage] = useState('');
    const [financeActionError, setFinanceActionError] = useState('');
    const [financePagination, setFinancePagination] = useState(initialPagination);
    const [isFinanceJournalVisible, setIsFinanceJournalVisible] = useState(false);

    const [creditEntries, setCreditEntries] = useState(initialCreditEntries);
    const [creditCustomers, setCreditCustomers] = useState(initialCreditCustomers);
    const [creditTotals, setCreditTotals] = useState(initialCreditsTotals);
    const [creditsFilters, setCreditsFilters] = useState(initialCreditsFilters);
    const [customerForm, setCustomerForm] = useState(initialCustomerForm);
    const [creditEntryForm, setCreditEntryForm] = useState(initialCreditEntryForm);
    const [creditEditForm, setCreditEditForm] = useState(initialCreditEditForm);
    const [creditPaymentForm, setCreditPaymentForm] = useState(initialCreditPaymentForm);
    const [creditsActionLoading, setCreditsActionLoading] = useState(false);
    const [creditsActionMessage, setCreditsActionMessage] = useState('');
    const [creditsActionError, setCreditsActionError] = useState('');
    const [creditsPagination, setCreditsPagination] = useState(initialPagination);
    const [uiFeedback, setUiFeedback] = useState(null);

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const stockSearchDebounceRef = useRef(null);
    const posSearchDebounceRef = useRef(null);

    useEffect(() => {
        return () => {
            if (stockSearchDebounceRef.current) {
                clearTimeout(stockSearchDebounceRef.current);
            }

            if (posSearchDebounceRef.current) {
                clearTimeout(posSearchDebounceRef.current);
            }
        };
    }, []);

    useEffect(() => {
        if (!stockActionMessage) {
            return;
        }

        setUiFeedback({
            id: Date.now(),
            type: 'success',
            message: stockActionMessage,
        });
    }, [stockActionMessage]);

    useEffect(() => {
        if (!financeActionMessage) {
            return;
        }

        setUiFeedback({
            id: Date.now(),
            type: 'success',
            message: financeActionMessage,
        });
    }, [financeActionMessage]);

    useEffect(() => {
        if (!creditsActionMessage) {
            return;
        }

        setUiFeedback({
            id: Date.now(),
            type: 'success',
            message: creditsActionMessage,
        });
    }, [creditsActionMessage]);

    useEffect(() => {
        if (!stockActionError) {
            return;
        }

        setUiFeedback({
            id: Date.now(),
            type: 'error',
            message: stockActionError,
        });
    }, [stockActionError]);

    useEffect(() => {
        if (!financeActionError) {
            return;
        }

        setUiFeedback({
            id: Date.now(),
            type: 'error',
            message: financeActionError,
        });
    }, [financeActionError]);

    useEffect(() => {
        if (!creditsActionError) {
            return;
        }

        setUiFeedback({
            id: Date.now(),
            type: 'error',
            message: creditsActionError,
        });
    }, [creditsActionError]);

    useEffect(() => {
        if (!error) {
            return;
        }

        setUiFeedback({
            id: Date.now(),
            type: 'error',
            message: error,
        });
    }, [error]);

    useEffect(() => {
        if (!uiFeedback) {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            setUiFeedback((current) => {
                if (!current || current.id !== uiFeedback.id) {
                    return current;
                }

                return null;
            });
        }, 2800);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [uiFeedback]);

    useEffect(() => {
        return () => {
            if (productImagePreviewUrl.startsWith('blob:')) {
                URL.revokeObjectURL(productImagePreviewUrl);
            }
        };
    }, [productImagePreviewUrl]);

    const hydrateStockProducts = (productsPayload) => {
        const products = productsPayload?.data ?? initialStockProducts;
        const meta = productsPayload?.meta ?? {};
        const categories = meta.categories ?? initialCategories;

        setStockProducts(products);
        setStockCategories(categories);
        setStockPagination({
            current_page: Number(meta.current_page ?? 1),
            last_page: Number(meta.last_page ?? 1),
            per_page: Number(meta.per_page ?? defaultPerPage),
            total: Number(meta.total ?? products.length),
        });
        setProductForm((current) => ({
            ...current,
            product_category_id:
                current.product_category_id || (categories[0] ? String(categories[0].id) : ''),
        }));
    };

    const hydratePosProducts = (productsPayload) => {
        const products = productsPayload?.data ?? initialStockProducts;

        setPosProducts(products);
    };

    const hydrateFinanceEntries = (entriesPayload) => {
        const entries = entriesPayload?.data ?? initialFinanceEntries;
        const meta = entriesPayload?.meta ?? {};
        const categories = meta.categories ?? initialFinanceCategories;
        const totals = meta.totals ?? initialFinanceTotals;

        setFinanceEntries(entries);
        setFinanceCategories(categories);
        setFinanceTotals(totals);
        setFinancePagination({
            current_page: Number(meta.current_page ?? 1),
            last_page: Number(meta.last_page ?? 1),
            per_page: Number(meta.per_page ?? defaultPerPage),
            total: Number(meta.total ?? entries.length),
        });
    };

    const hydrateCreditsEntries = (entriesPayload) => {
        const entries = entriesPayload?.data ?? initialCreditEntries;
        const meta = entriesPayload?.meta ?? {};
        const customers = meta.customers ?? initialCreditCustomers;
        const totals = meta.totals ?? initialCreditsTotals;

        setCreditEntries(entries);
        setCreditCustomers(customers);
        setCreditTotals(totals);
        setCreditsPagination({
            current_page: Number(meta.current_page ?? 1),
            last_page: Number(meta.last_page ?? 1),
            per_page: Number(meta.per_page ?? defaultPerPage),
            total: Number(meta.total ?? entries.length),
        });

        setCreditEntryForm((current) => ({
            ...current,
            customer_id: current.customer_id || (customers[0] ? String(customers[0].id) : ''),
        }));

        setCreditPaymentForm((current) => {
            const activeEntries = entries.filter((entry) => entry.status === 'open' || entry.status === 'partial');
            const fallbackEntryId = activeEntries[0] ? String(activeEntries[0].id) : '';
            const selectedExists = activeEntries.some(
                (entry) => String(entry.id) === current.customer_credit_id,
            );

            return {
                ...current,
                customer_credit_id: selectedExists ? current.customer_credit_id : fallbackEntryId,
            };
        });
    };

    const hydrateCreditCustomers = (customersPayload) => {
        const customers = customersPayload?.data ?? initialCreditCustomers;

        setCreditEntryForm((current) => ({
            ...current,
            customer_id: current.customer_id || (customers[0] ? String(customers[0].id) : ''),
        }));

        setFinanceSaleForm((current) => ({
            ...current,
            customer_id:
                current.payment_method === 'credit' && current.customer_mode === 'existing'
                    ? (current.customer_id || (customers[0] ? String(customers[0].id) : ''))
                    : current.customer_id,
        }));
    };

    useEffect(() => {
        setFinanceSaleForm((current) => {
            if (stockProducts.length === 0) {
                return current;
            }

            const firstProductId = String(stockProducts[0].id);
            const nextItems = current.items.map((item, index) => {
                if (index > 0 || item.product_id) {
                    return item;
                }

                return {
                    ...item,
                    product_id: firstProductId,
                };
            });

            const hasChanged = nextItems.some((item, index) => (
                item.product_id !== current.items[index]?.product_id
                || item.quantity !== current.items[index]?.quantity
            ));

            if (!hasChanged) {
                return current;
            }

            return {
                ...current,
                items: nextItems,
            };
        });
    }, [stockProducts]);

    useEffect(() => {
        setStockInForm((current) => {
            if (stockProducts.length === 0) {
                return current;
            }

            const firstProductId = String(stockProducts[0].id);
            const nextItems = current.items.map((item, index) => {
                if (index > 0 || item.product_id) {
                    return item;
                }

                return {
                    ...item,
                    product_id: firstProductId,
                };
            });

            const hasChanged = nextItems.some((item, index) => (
                item.product_id !== current.items[index]?.product_id
                || item.quantity !== current.items[index]?.quantity
                || item.unit_cost !== current.items[index]?.unit_cost
                || item.selling_price !== current.items[index]?.selling_price
            ));

            if (!hasChanged) {
                return current;
            }

            return {
                ...current,
                items: nextItems,
            };
        });
    }, [stockProducts]);

    useEffect(() => {
        setFinanceSaleForm((current) => {
            if (current.payment_method !== 'credit' || current.customer_mode !== 'existing') {
                return current;
            }

            if (current.customer_id || creditCustomers.length === 0) {
                return current;
            }

            return {
                ...current,
                customer_id: String(creditCustomers[0].id),
            };
        });
    }, [creditCustomers]);

    const refreshStockArea = async (
        page = stockPagination.current_page,
        search = stockSearchTerm,
    ) => {
        const [summaryPayload, stockPayload, productsPayload] = await Promise.all([
            fetchJson('/api/v1/dashboard-summary'),
            fetchJson('/api/v1/stock/overview'),
            fetchJson(buildStockProductsPath(page, stockPagination.per_page || defaultPerPage, search)),
        ]);

        setSummary(summaryPayload.data ?? initialSummary);
        setStockOverview(stockPayload.data ?? initialStockOverview);
        hydrateStockProducts(productsPayload);
    };

    const refreshStockProducts = async (
        page = stockPagination.current_page,
        search = stockSearchTerm,
    ) => {
        const productsPayload = await fetchJson(
            buildStockProductsPath(page, stockPagination.per_page || defaultPerPage, search),
        );

        hydrateStockProducts(productsPayload);
    };

    const refreshPosProducts = async (search = posSearchTerm) => {
        const productsPayload = await fetchJson(buildStockProductsPath(1, posProductsPerPage, search));

        hydratePosProducts(productsPayload);
    };

    const refreshFinanceArea = async (
        activeFilters = financeFilters,
        page = financePagination.current_page,
    ) => {
        const [summaryPayload, financePayload, entriesPayload] = await Promise.all([
            fetchJson('/api/v1/dashboard-summary'),
            fetchJson('/api/v1/finance/overview'),
            fetchJson(buildFinanceEntriesPath(activeFilters, page, financePagination.per_page || defaultPerPage)),
        ]);

        setSummary(summaryPayload.data ?? initialSummary);
        setFinanceOverview(financePayload.data ?? initialFinanceOverview);
        hydrateFinanceEntries(entriesPayload);
    };

    const refreshCreditsArea = async (
        activeFilters = creditsFilters,
        page = creditsPagination.current_page,
    ) => {
        const [summaryPayload, creditsPayload, entriesPayload, customersPayload] = await Promise.all([
            fetchJson('/api/v1/dashboard-summary'),
            fetchJson('/api/v1/credits/overview'),
            fetchJson(buildCreditsEntriesPath(activeFilters, page, creditsPagination.per_page || defaultPerPage)),
            fetchJson(`/api/v1/credits/customers?per_page=6&page=1`),
        ]);

        setSummary(summaryPayload.data ?? initialSummary);
        setCreditsOverview(creditsPayload.data ?? initialCreditsOverview);
        hydrateCreditsEntries(entriesPayload);
        hydrateCreditCustomers(customersPayload);
    };

    useEffect(() => {
        let active = true;

        async function loadDashboard() {
            setLoading(true);
            setError('');

            try {
                const [
                    summaryPayload,
                    stockPayload,
                    financePayload,
                    creditsPayload,
                    productsPayload,
                    posProductsPayload,
                    financeEntriesPayload,
                    creditsEntriesPayload,
                    creditsCustomersPayload,
                ] = await Promise.all([
                    fetchJson('/api/v1/dashboard-summary'),
                    fetchJson('/api/v1/stock/overview'),
                    fetchJson('/api/v1/finance/overview'),
                    fetchJson('/api/v1/credits/overview'),
                    fetchJson(buildStockProductsPath(1, defaultPerPage, initialStockSearch)),
                    fetchJson(buildStockProductsPath(1, posProductsPerPage, initialPosSearch)),
                    fetchJson(buildFinanceEntriesPath(initialFinanceFilters, 1, defaultPerPage)),
                    fetchJson(buildCreditsEntriesPath(initialCreditsFilters, 1, defaultPerPage)),
                    fetchJson(`/api/v1/credits/customers?per_page=6&page=1`),
                ]);

                if (active) {
                    setSummary(summaryPayload.data ?? initialSummary);
                    setStockOverview(stockPayload.data ?? initialStockOverview);
                    setFinanceOverview(financePayload.data ?? initialFinanceOverview);
                    setCreditsOverview(creditsPayload.data ?? initialCreditsOverview);
                    hydrateStockProducts(productsPayload);
                    hydratePosProducts(posProductsPayload);
                    hydrateFinanceEntries(financeEntriesPayload);
                    hydrateCreditsEntries(creditsEntriesPayload);
                    hydrateCreditCustomers(creditsCustomersPayload);
                }
            } catch (fetchError) {
                if (active) {
                    const message = fetchError instanceof Error ? fetchError.message : 'Erreur inconnue.';
                    setError(message);
                }
            } finally {
                if (active) {
                    setLoading(false);
                }
            }
        }

        loadDashboard();

        return () => {
            active = false;
        };
    }, []);

    useEffect(() => {
        const normalizedSearch = stockSearchInput.trim();

        if (normalizedSearch === stockSearchTerm) {
            return;
        }

        if (stockSearchDebounceRef.current) {
            clearTimeout(stockSearchDebounceRef.current);
        }

        stockSearchDebounceRef.current = setTimeout(async () => {
            setStockSearchLoading(true);
            setStockActionError('');

            try {
                await refreshStockProducts(1, normalizedSearch);
                setStockSearchTerm(normalizedSearch);
            } catch (searchError) {
                setStockActionError(searchError instanceof Error ? searchError.message : 'Echec recherche produits stock.');
            } finally {
                setStockSearchLoading(false);
            }
        }, searchDebounceMs);

        return () => {
            if (stockSearchDebounceRef.current) {
                clearTimeout(stockSearchDebounceRef.current);
            }
        };
    }, [stockSearchInput, stockSearchTerm]);

    useEffect(() => {
        const normalizedSearch = posSearchInput.trim();

        if (normalizedSearch === posSearchTerm) {
            return;
        }

        if (posSearchDebounceRef.current) {
            clearTimeout(posSearchDebounceRef.current);
        }

        posSearchDebounceRef.current = setTimeout(async () => {
            setPosSearchLoading(true);
            setFinanceActionError('');

            try {
                await refreshPosProducts(normalizedSearch);
                setPosSearchTerm(normalizedSearch);
            } catch (searchError) {
                setFinanceActionError(searchError instanceof Error ? searchError.message : 'Echec recherche catalogue POS.');
            } finally {
                setPosSearchLoading(false);
            }
        }, searchDebounceMs);

        return () => {
            if (posSearchDebounceRef.current) {
                clearTimeout(posSearchDebounceRef.current);
            }
        };
    }, [posSearchInput, posSearchTerm]);

    const handleProductFormChange = (event) => {
        const { name, type, value, checked } = event.target;

        if (name === 'remove_image') {
            setProductForm((current) => ({
                ...current,
                remove_image: checked,
            }));

            if (checked) {
                setProductImageFile(null);
                setProductImagePreviewUrl((current) => {
                    if (current.startsWith('blob:')) {
                        URL.revokeObjectURL(current);
                    }

                    return '';
                });
            }

            return;
        }

        setProductForm((current) => ({
            ...current,
            [name]: type === 'checkbox' ? checked : value,
        }));
    };

    const handleProductImageChange = (event) => {
        const nextFile = event.target.files?.[0] ?? null;

        setProductImageFile(nextFile);
        setProductImagePreviewUrl((current) => {
            if (current.startsWith('blob:')) {
                URL.revokeObjectURL(current);
            }

            return nextFile ? URL.createObjectURL(nextFile) : '';
        });

        setProductForm((current) => ({
            ...current,
            remove_image: false,
        }));
    };

    const resetProductImageState = () => {
        setProductImageFile(null);
        setProductImagePreviewUrl((current) => {
            if (current.startsWith('blob:')) {
                URL.revokeObjectURL(current);
            }

            return '';
        });
    };

    const handleCreateProduct = async (event) => {
        event.preventDefault();

        setStockActionLoading(true);
        setStockActionError('');
        setStockActionMessage('');

        try {
            const payload = new FormData();

            payload.append('name', productForm.name);
            payload.append('unit', productForm.unit);
            payload.append('stock_quantity', String(Number(productForm.stock_quantity)));
            payload.append('low_stock_threshold', String(Number(productForm.low_stock_threshold)));
            payload.append('purchase_price', String(Number(productForm.purchase_price)));
            payload.append('selling_price', String(Number(productForm.selling_price)));
            payload.append('is_active', productForm.is_active ? '1' : '0');
            payload.append('remove_image', productForm.remove_image ? '1' : '0');

            if (productForm.product_category_id) {
                payload.append('product_category_id', String(Number(productForm.product_category_id)));
            }

            if (productImageFile) {
                payload.append('image', productImageFile);
            }

            const endpoint = editingProductId
                ? `/api/v1/stock/products/${editingProductId}`
                : '/api/v1/stock/products';

            const requestOptions = editingProductId
                ? { method: 'POST', body: payload }
                : { method: 'POST', body: payload };

            if (editingProductId) {
                payload.append('_method', 'PATCH');
            }

            await fetchJson(endpoint, requestOptions);

            setStockActionMessage(editingProductId ? 'Produit modifie avec succes.' : 'Produit ajoute avec succes.');
            setEditingProductId(null);
            resetProductImageState();
            setProductForm((current) => ({
                ...current,
                product_category_id: stockCategories[0] ? String(stockCategories[0].id) : '',
                name: '',
                remove_image: false,
                current_image_url: '',
                stock_quantity: '0',
                low_stock_threshold: '0',
                purchase_price: '0',
                selling_price: '0',
            }));

            await Promise.all([
                refreshStockArea(stockPagination.current_page, stockSearchTerm),
                refreshPosProducts(posSearchTerm),
            ]);
        } catch (createError) {
            setStockActionError(
                createError instanceof Error ? createError.message : 'Echec creation produit.',
            );
        } finally {
            setStockActionLoading(false);
        }
    };

    const handleEditProduct = (product) => {
        setEditingProductId(product.id);
        resetProductImageState();
        setProductForm({
            product_category_id: product.category?.id ? String(product.category.id) : '',
            name: product.name,
            remove_image: false,
            current_image_url: product.image_url ?? '',
            default_image_url: product.default_image_url ?? '/images/product-default.svg',
            unit: product.unit,
            stock_quantity: String(product.stock_quantity),
            low_stock_threshold: String(product.low_stock_threshold),
            purchase_price: String(product.purchase_price),
            selling_price: String(product.selling_price),
            is_active: Boolean(product.is_active),
        });
        setStockActionError('');
        setStockActionMessage('Edition du produit activee.');
    };

    const handleCancelEditProduct = () => {
        setEditingProductId(null);
        resetProductImageState();
        setProductForm({
            ...initialProductForm,
            product_category_id: stockCategories[0] ? String(stockCategories[0].id) : '',
        });
    };

    const handleDeleteProduct = async (product) => {
        if (!window.confirm(`Confirmer la suppression du produit "${product.name}" ?`)) {
            return;
        }

        setStockActionLoading(true);
        setStockActionError('');
        setStockActionMessage('');

        try {
            await fetchJson(`/api/v1/stock/products/${product.id}`, {
                method: 'DELETE',
            });

            if (editingProductId === product.id) {
                handleCancelEditProduct();
            }

            setStockActionMessage('Produit supprime avec succes.');
            await Promise.all([
                refreshStockArea(stockPagination.current_page, stockSearchTerm),
                refreshPosProducts(posSearchTerm),
            ]);
        } catch (deleteError) {
            setStockActionError(
                deleteError instanceof Error ? deleteError.message : 'Echec suppression produit.',
            );
        } finally {
            setStockActionLoading(false);
        }
    };

    const handleStockInFormChange = (event) => {
        const { name, value } = event.target;

        setStockInForm((current) => ({
            ...current,
            [name]: value,
        }));
    };

    const handleStockInItemChange = (index, field, value) => {
        setStockInForm((current) => ({
            ...current,
            items: current.items.map((item, itemIndex) => {
                if (itemIndex !== index) {
                    return item;
                }

                return {
                    ...item,
                    [field]: value,
                };
            }),
        }));
    };

    const handleAddStockInItem = () => {
        setStockInForm((current) => ({
            ...current,
            items: [...current.items, createEmptyStockInLine(stockProducts[0] ? String(stockProducts[0].id) : '')],
        }));
    };

    const handleRemoveStockInItem = (index) => {
        setStockInForm((current) => {
            if (current.items.length <= 1) {
                return current;
            }

            return {
                ...current,
                items: current.items.filter((_, itemIndex) => itemIndex !== index),
            };
        });
    };

    const handleStockInSubmit = async (event) => {
        event.preventDefault();

        const normalizedItems = stockInForm.items
            .map((item) => ({
                product_id: Number(item.product_id),
                quantity: Number(item.quantity),
                unit_cost: item.unit_cost === '' ? null : Number(item.unit_cost),
                selling_price: item.selling_price === '' ? null : Number(item.selling_price),
            }))
            .filter((item) => item.product_id > 0 && item.quantity > 0);

        if (normalizedItems.length === 0) {
            setStockActionError('Ajoutez au moins un produit valide pour l entree stock.');
            return;
        }

        const itemMissingSellingPrice = normalizedItems.find((item) => item.unit_cost !== null && item.selling_price === null);

        if (itemMissingSellingPrice) {
            setStockActionError('Quand vous modifiez le prix d achat, renseignez aussi le prix de vente.');
            return;
        }

        setStockActionLoading(true);
        setStockActionError('');
        setStockActionMessage('');

        try {
            await fetchJson('/api/v1/stock/movements', {
                method: 'POST',
                body: JSON.stringify({
                    movement_type: 'in',
                    items: normalizedItems,
                    moved_at: stockInForm.moved_at,
                    note: stockInForm.note.trim() || null,
                }),
            });

            setStockActionMessage('Entree de stock enregistree avec succes.');
            setStockInForm({
                items: [createEmptyStockInLine(stockProducts[0] ? String(stockProducts[0].id) : '')],
                moved_at: getTodayDate(),
                note: '',
            });

            await Promise.all([
                refreshStockArea(stockPagination.current_page, stockSearchTerm),
                refreshPosProducts(posSearchTerm),
            ]);
        } catch (stockInError) {
            setStockActionError(
                stockInError instanceof Error ? stockInError.message : 'Echec enregistrement entree stock.',
            );
        } finally {
            setStockActionLoading(false);
        }
    };

    const handleFinanceFilterChange = (event) => {
        const { name, value } = event.target;

        setFinanceFilters((current) => ({
            ...current,
            [name]: value,
        }));
    };

    const handleFinanceFiltersSubmit = async (event) => {
        event.preventDefault();

        setFinanceActionLoading(true);
        setFinanceActionError('');
        setFinanceActionMessage('');

        try {
            await refreshFinanceArea(financeFilters, 1);
            setFinanceActionMessage('Filtres finances appliques.');
        } catch (applyError) {
            setFinanceActionError(applyError instanceof Error ? applyError.message : 'Echec application filtres.');
        } finally {
            setFinanceActionLoading(false);
        }
    };

    const handleFinanceFiltersReset = async () => {
        const nextFilters = { ...initialFinanceFilters };

        setFinanceFilters(nextFilters);
        setFinanceActionLoading(true);
        setFinanceActionError('');
        setFinanceActionMessage('');

        try {
            await refreshFinanceArea(nextFilters, 1);
            setFinanceActionMessage('Filtres finances reinitialises.');
        } catch (resetError) {
            setFinanceActionError(resetError instanceof Error ? resetError.message : 'Echec reset filtres.');
        } finally {
            setFinanceActionLoading(false);
        }
    };

    const handleFinanceSaleFormChange = (event) => {
        const { name, value } = event.target;

        setFinanceSaleForm((current) => {
            if (name === 'payment_method') {
                if (value !== 'credit') {
                    return {
                        ...current,
                        payment_method: value,
                        customer_id: '',
                        customer_mode: 'existing',
                        new_customer_name: '',
                        new_customer_phone: '',
                        new_customer_address: '',
                        new_customer_notes: '',
                    };
                }

                return {
                    ...current,
                    payment_method: value,
                    customer_id: current.customer_id || (creditCustomers[0] ? String(creditCustomers[0].id) : ''),
                };
            }

            if (name === 'customer_mode') {
                if (value === 'new') {
                    return {
                        ...current,
                        customer_mode: value,
                        customer_id: '',
                    };
                }

                return {
                    ...current,
                    customer_mode: value,
                    customer_id: current.customer_id || (creditCustomers[0] ? String(creditCustomers[0].id) : ''),
                    new_customer_name: '',
                    new_customer_phone: '',
                    new_customer_address: '',
                    new_customer_notes: '',
                };
            }

            return {
                ...current,
                [name]: value,
            };
        });
    };

    const handleFinanceSaleItemChange = (index, field, value) => {
        setFinanceSaleForm((current) => ({
            ...current,
            items: current.items.map((item, itemIndex) => {
                if (itemIndex !== index) {
                    return item;
                }

                return {
                    ...item,
                    [field]: value,
                };
            }),
        }));
    };

    const handleAddFinanceSaleItem = (productId = '') => {
        setFinanceSaleForm((current) => ({
            ...current,
            items: [
                ...current.items,
                createEmptySaleLine(productId || (stockProducts[0] ? String(stockProducts[0].id) : '')),
            ],
        }));
    };

    const handleRemoveFinanceSaleItem = (index) => {
        setFinanceSaleForm((current) => {
            return {
                ...current,
                items: current.items.filter((_, itemIndex) => itemIndex !== index),
            };
        });
    };

    const handlePosTapProduct = (product) => {
        setFinanceSaleForm((current) => {
            const existingIndex = current.items.findIndex(
                (item) => Number(item.product_id) === Number(product.id),
            );

            if (existingIndex >= 0) {
                const updatedItems = current.items.map((item, itemIndex) => {
                    if (itemIndex !== existingIndex) {
                        return item;
                    }

                    const nextQuantity = Math.max(0.001, Number(item.quantity || 0) + 1);

                    return {
                        ...item,
                        quantity: String(nextQuantity),
                    };
                });

                return {
                    ...current,
                    items: updatedItems,
                };
            }

            return {
                ...current,
                items: [...current.items, createEmptySaleLine(String(product.id))],
            };
        });
    };

    const handlePosAdjustQuantity = (index, delta) => {
        setFinanceSaleForm((current) => {
            const existingItem = current.items[index];

            if (!existingItem) {
                return current;
            }

            const nextQuantity = Number(existingItem.quantity || 0) + delta;

            if (nextQuantity <= 0) {
                return {
                    ...current,
                    items: current.items.filter((_, itemIndex) => itemIndex !== index),
                };
            }

            return {
                ...current,
                items: current.items.map((item, itemIndex) => {
                    if (itemIndex !== index) {
                        return item;
                    }

                    return {
                        ...item,
                        quantity: String(nextQuantity),
                    };
                }),
            };
        });
    };

    const handlePosClearTicket = () => {
        setFinanceSaleForm((current) => ({
            ...current,
            items: [],
        }));
    };

    const handleFinanceSaleSubmit = async (event) => {
        event.preventDefault();

        const normalizedItems = financeSaleForm.items
            .map((item) => ({
                product_id: Number(item.product_id),
                quantity: Number(item.quantity),
            }))
            .filter((item) => item.product_id > 0 && item.quantity > 0);

        if (normalizedItems.length === 0) {
            setFinanceActionError('Ajoutez au moins un produit valide pour enregistrer la vente.');
            return;
        }

        if (financeSaleForm.payment_method === 'credit') {
            if (financeSaleForm.customer_mode === 'existing' && !financeSaleForm.customer_id) {
                setFinanceActionError('Selectionnez un client existant pour la vente a credit.');
                return;
            }

            if (
                financeSaleForm.customer_mode === 'new'
                && !financeSaleForm.new_customer_name.trim()
            ) {
                setFinanceActionError('Le nom du nouveau client est obligatoire pour la vente a credit.');
                return;
            }
        }

        setFinanceActionLoading(true);
        setFinanceActionError('');
        setFinanceActionMessage('');

        const payload = {
            items: normalizedItems,
            occurred_on: financeSaleForm.occurred_on,
            payment_method: financeSaleForm.payment_method,
            note: financeSaleForm.note.trim() || null,
        };

        if (financeSaleForm.payment_method === 'credit') {
            if (financeSaleForm.customer_mode === 'existing') {
                payload.customer_id = Number(financeSaleForm.customer_id);
            } else {
                payload.new_customer = {
                    name: financeSaleForm.new_customer_name.trim(),
                    phone: financeSaleForm.new_customer_phone.trim() || null,
                    address: financeSaleForm.new_customer_address.trim() || null,
                    notes: financeSaleForm.new_customer_notes.trim() || null,
                };
            }
        }

        try {
            await fetchJson('/api/v1/finance/sales', {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            setFinanceActionMessage('Vente enregistree avec succes.');
            setFinanceSaleForm((current) => ({
                ...current,
                items: [],
                note: '',
                new_customer_name: '',
                new_customer_phone: '',
                new_customer_address: '',
                new_customer_notes: '',
                customer_id:
                    current.payment_method === 'credit' && current.customer_mode === 'existing'
                        ? current.customer_id
                        : '',
            }));

            await Promise.all([
                refreshStockArea(stockPagination.current_page, stockSearchTerm),
                refreshPosProducts(posSearchTerm),
                refreshFinanceArea(financeFilters, 1),
                refreshCreditsArea(creditsFilters, creditsPagination.current_page),
            ]);
        } catch (submitError) {
            setFinanceActionError(
                submitError instanceof Error
                    ? submitError.message
                    : 'Echec enregistrement vente.',
            );
        } finally {
            setFinanceActionLoading(false);
        }
    };

    const handleCreditsFilterChange = (event) => {
        const { name, value, type, checked } = event.target;

        setCreditsFilters((current) => ({
            ...current,
            [name]: type === 'checkbox' ? checked : value,
        }));
    };

    const handleCreditsFiltersSubmit = async (event) => {
        event.preventDefault();

        setCreditsActionLoading(true);
        setCreditsActionError('');
        setCreditsActionMessage('');

        try {
            await refreshCreditsArea(creditsFilters, 1);
            setCreditsActionMessage('Filtres credits appliques.');
        } catch (applyError) {
            setCreditsActionError(applyError instanceof Error ? applyError.message : 'Echec application filtres credits.');
        } finally {
            setCreditsActionLoading(false);
        }
    };

    const handleCreditsFiltersReset = async () => {
        const nextFilters = { ...initialCreditsFilters };

        setCreditsFilters(nextFilters);
        setCreditsActionLoading(true);
        setCreditsActionError('');
        setCreditsActionMessage('');

        try {
            await refreshCreditsArea(nextFilters, 1);
            setCreditsActionMessage('Filtres credits reinitialises.');
        } catch (resetError) {
            setCreditsActionError(resetError instanceof Error ? resetError.message : 'Echec reset filtres credits.');
        } finally {
            setCreditsActionLoading(false);
        }
    };

    const handleCustomerFormChange = (event) => {
        const { name, value } = event.target;

        setCustomerForm((current) => ({
            ...current,
            [name]: value,
        }));
    };

    const handleCreateCustomer = async (event) => {
        event.preventDefault();

        setCreditsActionLoading(true);
        setCreditsActionError('');
        setCreditsActionMessage('');

        try {
            await fetchJson('/api/v1/credits/customers', {
                method: 'POST',
                body: JSON.stringify({
                    name: customerForm.name.trim(),
                    phone: customerForm.phone.trim() || null,
                    address: customerForm.address.trim() || null,
                    notes: customerForm.notes.trim() || null,
                    is_active: true,
                }),
            });

            setCustomerForm(initialCustomerForm);
            setCreditsActionMessage('Client ajoute avec succes.');
            await refreshCreditsArea(creditsFilters);
        } catch (createError) {
            setCreditsActionError(
                createError instanceof Error ? createError.message : 'Echec creation client.',
            );
        } finally {
            setCreditsActionLoading(false);
        }
    };

    const handleCreditEntryFormChange = (event) => {
        const { name, value } = event.target;

        setCreditEntryForm((current) => ({
            ...current,
            [name]: value,
        }));
    };

    const handleCreateCreditEntry = async (event) => {
        event.preventDefault();

        if (!creditEntryForm.customer_id) {
            setCreditsActionError('Selectionnez un client avant d ouvrir une ardoise.');
            return;
        }

        setCreditsActionLoading(true);
        setCreditsActionError('');
        setCreditsActionMessage('');

        try {
            await fetchJson('/api/v1/credits/entries', {
                method: 'POST',
                body: JSON.stringify({
                    customer_id: Number(creditEntryForm.customer_id),
                    total_amount: Number(creditEntryForm.total_amount),
                    opened_on: creditEntryForm.opened_on,
                    due_date: creditEntryForm.due_date || null,
                    note: creditEntryForm.note.trim() || null,
                }),
            });

            setCreditsActionMessage('Ardoise client enregistree.');
            setCreditEntryForm((current) => ({
                ...current,
                total_amount: '',
                opened_on: getTodayDate(),
                due_date: '',
                note: '',
            }));
            await refreshCreditsArea(creditsFilters);
        } catch (createError) {
            setCreditsActionError(
                createError instanceof Error ? createError.message : 'Echec creation ardoise.',
            );
        } finally {
            setCreditsActionLoading(false);
        }
    };

    const handleCreditEditFormChange = (event) => {
        const { name, value } = event.target;

        setCreditEditForm((current) => ({
            ...current,
            [name]: value,
        }));
    };

    const handleStartEditCreditEntry = (entry) => {
        setCreditEditForm({
            id: String(entry.id),
            customer_id: entry.customer_id ? String(entry.customer_id) : '',
            total_amount: String(entry.total_amount ?? ''),
            opened_on: entry.opened_on ?? getTodayDate(),
            due_date: entry.due_date ?? '',
            note: entry.note ?? '',
        });
        setCreditsActionError('');
        setCreditsActionMessage('Edition de l ardoise activee.');
    };

    const handleCancelEditCreditEntry = () => {
        setCreditEditForm(initialCreditEditForm);
    };

    const handleUpdateCreditEntry = async (event) => {
        event.preventDefault();

        if (!creditEditForm.id) {
            setCreditsActionError('Selectionnez d abord une ardoise a modifier.');
            return;
        }

        if (!creditEditForm.customer_id) {
            setCreditsActionError('Selectionnez un client pour la modification.');
            return;
        }

        setCreditsActionLoading(true);
        setCreditsActionError('');
        setCreditsActionMessage('');

        try {
            await fetchJson(`/api/v1/credits/entries/${creditEditForm.id}`, {
                method: 'PATCH',
                body: JSON.stringify({
                    customer_id: Number(creditEditForm.customer_id),
                    total_amount: Number(creditEditForm.total_amount),
                    opened_on: creditEditForm.opened_on,
                    due_date: creditEditForm.due_date || null,
                    note: creditEditForm.note.trim() || null,
                }),
            });

            setCreditsActionMessage('Ardoise modifiee avec succes.');
            setCreditEditForm(initialCreditEditForm);
            await refreshCreditsArea(creditsFilters, creditsPagination.current_page);
        } catch (updateError) {
            setCreditsActionError(
                updateError instanceof Error ? updateError.message : 'Echec modification ardoise.',
            );
        } finally {
            setCreditsActionLoading(false);
        }
    };

    const handleDeleteCreditEntry = async (entry) => {
        if (!window.confirm('Confirmer la suppression de cette ardoise ?')) {
            return;
        }

        setCreditsActionLoading(true);
        setCreditsActionError('');
        setCreditsActionMessage('');

        try {
            await fetchJson(`/api/v1/credits/entries/${entry.id}`, {
                method: 'DELETE',
            });

            if (creditEditForm.id && Number(creditEditForm.id) === entry.id) {
                setCreditEditForm(initialCreditEditForm);
            }

            setCreditsActionMessage('Ardoise supprimee avec succes.');
            await refreshCreditsArea(creditsFilters, creditsPagination.current_page);
        } catch (deleteError) {
            setCreditsActionError(
                deleteError instanceof Error ? deleteError.message : 'Echec suppression ardoise.',
            );
        } finally {
            setCreditsActionLoading(false);
        }
    };

    const handleCreditPaymentFormChange = (event) => {
        const { name, value } = event.target;

        setCreditPaymentForm((current) => ({
            ...current,
            [name]: value,
        }));
    };

    const handlePrefillCreditPayment = (entry) => {
        setCreditPaymentForm((current) => ({
            ...current,
            customer_credit_id: String(entry.id),
            amount: String(entry.remaining_amount ?? ''),
        }));
        setCreditsActionMessage('Paiement pre-rempli pour l ardoise selectionnee.');
        setCreditsActionError('');
    };

    const handleCreateCreditPayment = async (event) => {
        event.preventDefault();

        if (!creditPaymentForm.customer_credit_id) {
            setCreditsActionError('Selectionnez une ardoise a regler.');
            return;
        }

        setCreditsActionLoading(true);
        setCreditsActionError('');
        setCreditsActionMessage('');

        try {
            await fetchJson(`/api/v1/credits/entries/${creditPaymentForm.customer_credit_id}/payments`, {
                method: 'POST',
                body: JSON.stringify({
                    amount: Number(creditPaymentForm.amount),
                    paid_at: creditPaymentForm.paid_at,
                    note: creditPaymentForm.note.trim() || null,
                }),
            });

            setCreditsActionMessage('Paiement client enregistre.');
            setCreditPaymentForm((current) => ({
                ...current,
                amount: '',
                note: '',
                paid_at: getTodayDate(),
            }));
            await refreshCreditsArea(creditsFilters);
        } catch (paymentError) {
            setCreditsActionError(
                paymentError instanceof Error ? paymentError.message : 'Echec enregistrement paiement.',
            );
        } finally {
            setCreditsActionLoading(false);
        }
    };

    const netResultClass = useMemo(() => {
        return summary.finance.net_result >= 0 ? 'is-positive' : 'is-negative';
    }, [summary.finance.net_result]);

    const filteredNetResultClass = useMemo(() => {
        return Number(financeTotals.net_result ?? 0) >= 0 ? 'is-positive' : 'is-negative';
    }, [financeTotals.net_result]);

    const actionableCreditEntries = useMemo(() => {
        return creditEntries.filter((entry) => entry.status === 'open' || entry.status === 'partial');
    }, [creditEntries]);

    const productCatalogLookup = useMemo(() => {
        const lookup = new Map();

        [...stockProducts, ...posProducts].forEach((product) => {
            lookup.set(Number(product.id), product);
        });

        return lookup;
    }, [stockProducts, posProducts]);

    const posCartItems = useMemo(() => {
        return financeSaleForm.items
            .map((item, index) => {
                const product = productCatalogLookup.get(Number(item.product_id));
                const quantity = Number(item.quantity ?? 0);

                if (!product || quantity <= 0) {
                    return null;
                }

                return {
                    lineIndex: index,
                    product,
                    quantity,
                    lineTotal: quantity * Number(product.selling_price ?? 0),
                };
            })
            .filter(Boolean);
            }, [financeSaleForm.items, productCatalogLookup]);

    const posCartTotal = useMemo(() => {
        return posCartItems.reduce((total, item) => total + item.lineTotal, 0);
    }, [posCartItems]);

    const posCartItemsCount = useMemo(() => {
        return posCartItems.reduce((total, item) => total + item.quantity, 0);
    }, [posCartItems]);

    const productImagePreviewSource = productImagePreviewUrl
        || (!productForm.remove_image
            ? (productForm.current_image_url || productForm.default_image_url)
            : productForm.default_image_url);

    const workspaceTabs = [
        { key: 'stock', label: 'Stock', description: 'Produits et niveaux' },
        { key: 'finance', label: 'Finance', description: 'Ventes et resultats' },
        { key: 'credits', label: 'Credits', description: 'Ardoises et paiements' },
    ];

    const activeWorkspaceMeta = workspaceTabs.find((tab) => tab.key === activeWorkspace) ?? workspaceTabs[0];

    const handleStockPageChange = async (nextPage) => {
        if (nextPage < 1 || nextPage > stockPagination.last_page || nextPage === stockPagination.current_page) {
            return;
        }

        setStockActionLoading(true);
        setStockActionError('');

        try {
            await refreshStockProducts(nextPage);
        } catch (pageError) {
            setStockActionError(pageError instanceof Error ? pageError.message : 'Echec changement page stock.');
        } finally {
            setStockActionLoading(false);
        }
    };

    const handleStockSearchReset = async () => {
        setStockSearchLoading(true);
        setStockActionError('');

        try {
            setStockSearchInput(initialStockSearch);
            setStockSearchTerm(initialStockSearch);
            await refreshStockProducts(1, initialStockSearch);
        } catch (resetError) {
            setStockActionError(resetError instanceof Error ? resetError.message : 'Echec reset recherche stock.');
        } finally {
            setStockSearchLoading(false);
        }
    };

    const handleFinancePageChange = async (nextPage) => {
        if (nextPage < 1 || nextPage > financePagination.last_page || nextPage === financePagination.current_page) {
            return;
        }

        setFinanceActionLoading(true);
        setFinanceActionError('');

        try {
            await refreshFinanceArea(financeFilters, nextPage);
        } catch (pageError) {
            setFinanceActionError(pageError instanceof Error ? pageError.message : 'Echec changement page finance.');
        } finally {
            setFinanceActionLoading(false);
        }
    };

    const handleCreditsPageChange = async (nextPage) => {
        if (nextPage < 1 || nextPage > creditsPagination.last_page || nextPage === creditsPagination.current_page) {
            return;
        }

        setCreditsActionLoading(true);
        setCreditsActionError('');

        try {
            await refreshCreditsArea(creditsFilters, nextPage);
        } catch (pageError) {
            setCreditsActionError(pageError instanceof Error ? pageError.message : 'Echec changement page credits.');
        } finally {
            setCreditsActionLoading(false);
        }
    };

    const handlePosSearchReset = async () => {
        setPosSearchLoading(true);
        setFinanceActionError('');

        try {
            setPosSearchInput(initialPosSearch);
            setPosSearchTerm(initialPosSearch);
            await refreshPosProducts(initialPosSearch);
        } catch (resetError) {
            setFinanceActionError(resetError instanceof Error ? resetError.message : 'Echec reset recherche POS.');
        } finally {
            setPosSearchLoading(false);
        }
    };

    return (
        <div className="app-shell">
            <div className="app-glow app-glow-left" aria-hidden="true" />
            <div className="app-glow app-glow-right" aria-hidden="true" />

            {uiFeedback ? (
                <div
                    className={uiFeedback.type === 'success' ? 'ui-feedback ui-feedback-success' : 'ui-feedback ui-feedback-error'}
                    role="status"
                    aria-live="polite"
                >
                    <div className="ui-feedback-body">
                        <strong>{uiFeedback.type === 'success' ? 'Succes' : 'Erreur'}</strong>
                        <p>{uiFeedback.message}</p>
                    </div>
                    <button
                        type="button"
                        className="ui-feedback-close"
                        onClick={() => setUiFeedback(null)}
                        aria-label="Fermer notification"
                    >
                        x
                    </button>
                </div>
            ) : null}

            <main className="app-layout">
                <aside className="admin-sidebar" aria-label="Navigation principale">
                    <div className="sidebar-brand">
                        <p className="eyebrow">Hanout Manager</p>
                        <p className="sidebar-brand-title">Admin Dashboard</p>
                        <p className="sidebar-brand-subtitle">Suivi quotidien de l activite commerciale.</p>
                    </div>

                    <nav className="workspace-switcher" aria-label="Selection module detaille">
                        {workspaceTabs.map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                className={activeWorkspace === tab.key ? 'workspace-tab is-active' : 'workspace-tab'}
                                onClick={() => setActiveWorkspace(tab.key)}
                            >
                                <span>{tab.label}</span>
                                <small>{tab.description}</small>
                            </button>
                        ))}
                    </nav>

                    <div className="sidebar-kpis" aria-label="Indicateurs rapides secondaires">
                        <article>
                            <p>Stock bas</p>
                            <strong>{formatNumber(summary.stock.low_stock_items)}</strong>
                        </article>
                        <article>
                            <p>Ecritures mois</p>
                            <strong>{formatNumber(financeOverview.entries_this_month)}</strong>
                        </article>
                        <article>
                            <p>Credits actifs</p>
                            <strong>{formatNumber(creditsOverview.open_credits)}</strong>
                        </article>
                    </div>

                    <div className="sidebar-footnote">
                        <span>Derniere maj</span>
                        <strong>{formatDateTime(summary.generated_at)}</strong>
                    </div>
                </aside>

                <section className="admin-main">
                    <header className="app-header">
                        <p className="eyebrow">Pilotage operationnel</p>
                        <h1 className="app-title">Tableau de bord administrateur</h1>
                        <p className="app-subtitle">
                            Vue centralisee pour piloter le stock, la finance et les credits clients sur desktop, tablette et mobile.
                        </p>

                        <div className="status-row">
                            <span className="status-pill">Espace actif: {activeWorkspaceMeta.label}</span>
                            {loading ? <span className="status-pill">Chargement...</span> : null}
                            {error ? <span className="status-pill status-error">{error}</span> : null}
                        </div>
                    </header>

                    <section className="kpi-strip" aria-label="Chiffres rapides">
                        <article>
                            <p>Valeur stock</p>
                            <strong>{formatCurrency(summary.stock.inventory_value)}</strong>
                        </article>
                        <article>
                            <p>Resultat mensuel</p>
                            <strong className={netResultClass}>{formatCurrency(summary.finance.net_result)}</strong>
                        </article>
                        <article>
                            <p>Credits clients</p>
                            <strong>{formatCurrency(summary.credits.total_due)}</strong>
                        </article>
                    </section>

                    <section className="dashboard-grid" aria-label="Modules principaux">
                        <article className="module-card card-stock">
                            <h2>1. Gestion de stock</h2>
                            <p className="module-value">{formatNumber(summary.stock.total_items)} references actives</p>
                            <p className="module-meta">
                                {formatNumber(summary.stock.low_stock_items)} articles en seuil bas - {formatNumber(stockOverview.movements_this_month)}
                                {' '}mouvements ce mois
                            </p>
                            <ul>
                                {(stockOverview.top_categories ?? []).map((category) => (
                                    <li key={category.name}>
                                        <span>{category.name}</span>
                                        <span>{formatNumber(category.items)} articles</span>
                                    </li>
                                ))}
                            </ul>
                        </article>

                        <article className="module-card card-finance">
                            <h2>2. Budget et revenus</h2>
                            <p className="module-value">{formatCurrency(summary.finance.month_income)} revenu reel du mois</p>
                            <p className="module-meta">
                                Potentiel: {formatCurrency(summary.finance.potential_income)} - Depenses: {formatCurrency(summary.finance.month_expenses)} - {formatNumber(financeOverview.entries_this_month)}
                                {' '}ecritures ce mois
                            </p>
                            <div className="module-grid-values">
                                <div>
                                    <span>Caisse disponible</span>
                                    <strong>{formatCurrency(summary.finance.cash_in_hand)}</strong>
                                </div>
                                <div>
                                    <span>Resultat net</span>
                                    <strong className={netResultClass}>{formatCurrency(summary.finance.net_result)}</strong>
                                </div>
                            </div>
                            <ul>
                                {(financeOverview.top_expense_categories ?? []).map((item) => (
                                    <li key={item.category}>
                                        <span>{item.category}</span>
                                        <span>{formatCurrency(item.total)}</span>
                                    </li>
                                ))}
                            </ul>
                        </article>

                        <article className="module-card card-credits">
                            <h2>3. Credits clients</h2>
                            <p className="module-value">{formatNumber(summary.credits.active_customers)} clients avec ardoise</p>
                            <p className="module-meta">
                                {formatCurrency(summary.credits.due_this_week)} a recuperer cette semaine - {formatNumber(creditsOverview.open_credits)}
                                {' '}credits actifs
                            </p>
                            <div className="module-grid-values">
                                <div>
                                    <span>Montant total du</span>
                                    <strong>{formatCurrency(summary.credits.total_due)}</strong>
                                </div>
                                <div>
                                    <span>Montant en retard</span>
                                    <strong className="is-negative">{formatCurrency(summary.credits.overdue_amount)}</strong>
                                </div>
                            </div>
                            <ul>
                                {(creditsOverview.upcoming_credits ?? []).map((item) => (
                                    <li key={`${item.customer_name}-${item.due_date ?? 'none'}`}>
                                        <span>{item.customer_name}</span>
                                        <span>{formatCurrency(item.remaining_amount)}</span>
                                    </li>
                                ))}
                            </ul>
                        </article>
                    </section>

                {activeWorkspace === 'stock' ? (
                <section className="stock-workspace" aria-label="Gestion detaillee du stock">
                    <div className="stock-workspace-head">
                        <h2>Module 1: Gestion du stock</h2>
                        <p>
                            Produits, seuils critiques et disponibilite en temps reel.
                        </p>
                    </div>

                    {stockActionMessage ? (
                        <p className="stock-alert stock-alert-success">{stockActionMessage}</p>
                    ) : null}

                    {stockActionError ? (
                        <p className="stock-alert stock-alert-error">{stockActionError}</p>
                    ) : null}

                    <div className="stock-workspace-grid">
                        <div className="stock-forms-panel">
                            <form className="stock-form" onSubmit={handleCreateProduct}>
                                <h3>{editingProductId ? 'Modifier produit' : 'Nouveau produit'}</h3>

                                <label>
                                    Nom produit
                                    <input
                                        name="name"
                                        value={productForm.name}
                                        onChange={handleProductFormChange}
                                        required
                                    />
                                </label>

                                <div className="product-image-preview-wrap">
                                    <img
                                        src={productImagePreviewSource}
                                        alt={`Image ${productForm.name || 'produit'}`}
                                        className="product-image-preview"
                                        onError={(event) => {
                                            event.currentTarget.onerror = null;
                                            event.currentTarget.src = productForm.default_image_url || '/images/product-default.svg';
                                        }}
                                    />
                                    <p>
                                        {productImageFile
                                            ? `Fichier selectionne: ${productImageFile.name}`
                                            : 'Image actuelle ou image par defaut'}
                                    </p>
                                </div>

                                <label>
                                    Image produit
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={handleProductImageChange}
                                    />
                                </label>

                                {editingProductId ? (
                                    <label className="stock-checkbox">
                                        <input
                                            type="checkbox"
                                            name="remove_image"
                                            checked={productForm.remove_image}
                                            onChange={handleProductFormChange}
                                        />
                                        Supprimer l image personnalisee
                                    </label>
                                ) : null}

                                <label>
                                    Categorie
                                    <select
                                        name="product_category_id"
                                        value={productForm.product_category_id}
                                        onChange={handleProductFormChange}
                                    >
                                        <option value="">Sans categorie</option>
                                        {stockCategories.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <div className="stock-inline-grid">
                                    <label>
                                        Unite
                                        <select
                                            name="unit"
                                            value={productForm.unit}
                                            onChange={handleProductFormChange}
                                        >
                                            <option value="piece">piece</option>
                                            <option value="kg">kg</option>
                                            <option value="litre">litre</option>
                                        </select>
                                    </label>

                                    <label>
                                        Stock initial
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            name="stock_quantity"
                                            value={productForm.stock_quantity}
                                            onChange={handleProductFormChange}
                                            required
                                        />
                                    </label>
                                </div>

                                <div className="stock-inline-grid">
                                    <label>
                                        Seuil bas
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            name="low_stock_threshold"
                                            value={productForm.low_stock_threshold}
                                            onChange={handleProductFormChange}
                                            required
                                        />
                                    </label>

                                    <label>
                                        Prix achat
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name="purchase_price"
                                            value={productForm.purchase_price}
                                            onChange={handleProductFormChange}
                                            required
                                        />
                                    </label>
                                </div>

                                <label>
                                    Prix vente
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="selling_price"
                                        value={productForm.selling_price}
                                        onChange={handleProductFormChange}
                                        required
                                    />
                                </label>

                                <label className="stock-checkbox">
                                    <input
                                        type="checkbox"
                                        name="is_active"
                                        checked={productForm.is_active}
                                        onChange={handleProductFormChange}
                                    />
                                    Produit actif
                                </label>

                                <div className="finance-form-actions">
                                    <button type="submit" disabled={stockActionLoading}>
                                        {stockActionLoading
                                            ? 'En cours...'
                                            : (editingProductId ? 'Mettre a jour produit' : 'Ajouter produit')}
                                    </button>

                                    {editingProductId ? (
                                        <button
                                            type="button"
                                            className="finance-ghost-button"
                                            onClick={handleCancelEditProduct}
                                            disabled={stockActionLoading}
                                        >
                                            Annuler
                                        </button>
                                    ) : null}
                                </div>
                            </form>

                            <form className="stock-form" onSubmit={handleStockInSubmit}>
                                <h3>Ajouter au stock</h3>

                                <div className="sale-lines-list">
                                    {stockInForm.items.map((item, index) => (
                                        <div className="sale-line-row" key={`stock-in-line-${index}`}>
                                            <label>
                                                Produit {index + 1}
                                                <select
                                                    value={item.product_id}
                                                    onChange={(event) => handleStockInItemChange(index, 'product_id', event.target.value)}
                                                    required
                                                >
                                                    <option value="">Selectionner produit</option>
                                                    {stockProducts.map((product) => (
                                                        <option key={product.id} value={product.id}>
                                                            {product.name} - stock {formatNumber(product.stock_quantity)}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>

                                            <div className="stock-in-line-controls">
                                                <label>
                                                    Quantite
                                                    <input
                                                        type="number"
                                                        step="0.001"
                                                        min="0.001"
                                                        value={item.quantity}
                                                        onChange={(event) => handleStockInItemChange(index, 'quantity', event.target.value)}
                                                        required
                                                    />
                                                </label>

                                                <label>
                                                    Prix achat (optionnel)
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={item.unit_cost}
                                                        onChange={(event) => handleStockInItemChange(index, 'unit_cost', event.target.value)}
                                                        placeholder="Prix achat actuel si vide"
                                                    />
                                                </label>

                                                <label>
                                                    Prix vente
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={item.selling_price}
                                                        onChange={(event) => handleStockInItemChange(index, 'selling_price', event.target.value)}
                                                        placeholder="Obligatoire si prix achat modifie"
                                                    />
                                                </label>

                                                {stockInForm.items.length > 1 ? (
                                                    <button
                                                        type="button"
                                                        className="finance-ghost-button sale-line-remove"
                                                        onClick={() => handleRemoveStockInItem(index)}
                                                        disabled={stockActionLoading}
                                                    >
                                                        Retirer
                                                    </button>
                                                ) : null}
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <div className="finance-form-actions">
                                    <button
                                        type="button"
                                        className="finance-ghost-button"
                                        onClick={handleAddStockInItem}
                                        disabled={stockActionLoading}
                                    >
                                        + Ajouter produit
                                    </button>
                                </div>

                                <div className="stock-inline-grid">
                                    <label>
                                        Date mouvement
                                        <input
                                            type="date"
                                            name="moved_at"
                                            value={stockInForm.moved_at}
                                            onChange={handleStockInFormChange}
                                            required
                                        />
                                    </label>

                                    <label>
                                        Note
                                        <input
                                            name="note"
                                            value={stockInForm.note}
                                            onChange={handleStockInFormChange}
                                            placeholder="Optionnel"
                                        />
                                    </label>
                                </div>

                                <button type="submit" disabled={stockActionLoading}>
                                    {stockActionLoading ? 'En cours...' : 'Valider entree stock'}
                                </button>
                            </form>

                        </div>

                        <div className="stock-table-panel">
                            <h3>Produits en stock</h3>

                            <div className="stock-search-bar" role="search" aria-label="Recherche produits stock">
                                <input
                                    type="search"
                                    value={stockSearchInput}
                                    onChange={(event) => setStockSearchInput(event.target.value)}
                                    placeholder="Rechercher produit ou categorie"
                                    aria-label="Recherche produits stock"
                                />
                                <div className="stock-search-actions">
                                    <small className="search-live-hint">
                                        {stockSearchLoading ? 'Recherche en cours...' : 'Recherche instantanee active'}
                                    </small>
                                    <button
                                        type="button"
                                        className="finance-ghost-button"
                                        onClick={handleStockSearchReset}
                                        disabled={stockSearchLoading || (!stockSearchTerm && !stockSearchInput)}
                                    >
                                        Reinitialiser
                                    </button>
                                </div>
                            </div>

                            <div className="stock-table-wrap">
                                <table className="stock-table">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th>Categorie</th>
                                            <th>Stock</th>
                                            <th>Seuil</th>
                                            <th>Achat</th>
                                            <th>Vente</th>
                                            <th>Etat</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {stockProducts.length === 0 ? (
                                            <tr>
                                                <td colSpan={8} className="table-empty-cell">
                                                    Aucun produit trouve pour cette recherche.
                                                </td>
                                            </tr>
                                        ) : null}

                                        {stockProducts.map((product) => (
                                            <tr key={product.id}>
                                                <td data-label="Produit">
                                                    <div className="product-cell">
                                                        <img
                                                            src={product.display_image_url || product.default_image_url || '/images/product-default.svg'}
                                                            alt={product.name}
                                                            className="product-cell-image"
                                                            onError={(event) => {
                                                                event.currentTarget.onerror = null;
                                                                event.currentTarget.src = product.default_image_url || '/images/product-default.svg';
                                                            }}
                                                        />
                                                        <div>
                                                            <strong>{product.name}</strong>
                                                            <small>{product.unit}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td data-label="Categorie">{product.category?.name ?? 'n/a'}</td>
                                                <td data-label="Stock">{formatNumber(product.stock_quantity)}</td>
                                                <td data-label="Seuil">{formatNumber(product.low_stock_threshold)}</td>
                                                <td data-label="Achat">{formatCurrency(product.purchase_price)}</td>
                                                <td data-label="Vente">{formatCurrency(product.selling_price)}</td>
                                                <td data-label="Etat">
                                                    {product.is_low_stock ? (
                                                        <span className="stock-badge stock-badge-danger">Seuil bas</span>
                                                    ) : (
                                                        <span className="stock-badge stock-badge-ok">Correct</span>
                                                    )}
                                                </td>
                                                <td data-label="Actions">
                                                    <div className="finance-row-actions">
                                                        <button
                                                            type="button"
                                                            className="finance-row-button"
                                                            onClick={() => handleEditProduct(product)}
                                                            disabled={stockActionLoading}
                                                        >
                                                            Modifier
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="finance-row-button finance-row-button-danger"
                                                            onClick={() => handleDeleteProduct(product)}
                                                            disabled={stockActionLoading}
                                                        >
                                                            Supprimer
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="workspace-pagination">
                                <button
                                    type="button"
                                    className="finance-ghost-button"
                                    onClick={() => handleStockPageChange(stockPagination.current_page - 1)}
                                    disabled={stockActionLoading || stockPagination.current_page <= 1}
                                >
                                    Precedent
                                </button>
                                <span>
                                    Page {formatNumber(stockPagination.current_page)} / {formatNumber(stockPagination.last_page)}
                                    {' '}({formatNumber(stockPagination.total)} produits)
                                </span>
                                <button
                                    type="button"
                                    className="finance-ghost-button"
                                    onClick={() => handleStockPageChange(stockPagination.current_page + 1)}
                                    disabled={stockActionLoading || stockPagination.current_page >= stockPagination.last_page}
                                >
                                    Suivant
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
                ) : null}

                {activeWorkspace === 'finance' ? (
                <section className="stock-workspace finance-workspace" aria-label="Gestion detaillee budget revenus">
                    <div className="stock-workspace-head">
                        <h2>Module 2: Finance</h2>
                        <p>
                            Ventes par produit, impact direct sur le stock et suivi du resultat mensuel.
                        </p>
                    </div>

                    {financeActionMessage ? (
                        <p className="stock-alert stock-alert-success">{financeActionMessage}</p>
                    ) : null}

                    {financeActionError ? (
                        <p className="stock-alert stock-alert-error">{financeActionError}</p>
                    ) : null}

                    <div className="finance-workspace-grid finance-workspace-grid-expanded">
                        <div className="stock-forms-panel">
                            <form className="stock-form pos-sale-form" onSubmit={handleFinanceSaleSubmit}>
                                <div className="pos-sale-header">
                                    <h3>Caisse tactile</h3>
                                    <p>Interface simplifiee pour un encaissement rapide au toucher.</p>
                                </div>

                                <div className="pos-sale-kpis" aria-live="polite">
                                    <article>
                                        <span>Articles</span>
                                        <strong>{formatNumber(posCartItemsCount)}</strong>
                                    </article>
                                    <article>
                                        <span>Total</span>
                                        <strong>{formatCurrency(posCartTotal)}</strong>
                                    </article>
                                </div>

                                <div className="pos-sale-layout">
                                    <div className="pos-sale-main-column">
                                        <section className="pos-section">
                                            <div className="pos-section-head">
                                                <h4>1. Choisir les produits</h4>
                                                <small>Appuyez pour ajouter au ticket.</small>
                                            </div>

                                            <div className="pos-search-bar" role="search" aria-label="Recherche produits POS">
                                                <input
                                                    type="search"
                                                    value={posSearchInput}
                                                    onChange={(event) => setPosSearchInput(event.target.value)}
                                                    placeholder="Rechercher dans le catalogue POS"
                                                    aria-label="Recherche produits POS"
                                                />
                                                <div className="pos-search-actions">
                                                    <small className="search-live-hint">
                                                        {posSearchLoading ? 'Recherche en cours...' : 'Recherche instantanee active'}
                                                    </small>
                                                    <button
                                                        type="button"
                                                        className="finance-ghost-button"
                                                        onClick={handlePosSearchReset}
                                                        disabled={posSearchLoading || (!posSearchTerm && !posSearchInput)}
                                                    >
                                                        Reinitialiser
                                                    </button>
                                                </div>
                                            </div>

                                            <div className="pos-product-grid" role="list" aria-label="Catalogue produits tactile">
                                                {posProducts.map((product) => {
                                                    const isDisabled = Number(product.stock_quantity) <= 0 || !product.is_active;

                                                    return (
                                                        <button
                                                            key={product.id}
                                                            type="button"
                                                            className="pos-product-card"
                                                            onClick={() => handlePosTapProduct(product)}
                                                            disabled={isDisabled || financeActionLoading}
                                                        >
                                                            <img
                                                                src={product.display_image_url || product.default_image_url || '/images/product-default.svg'}
                                                                alt={product.name}
                                                                onError={(event) => {
                                                                    event.currentTarget.onerror = null;
                                                                    event.currentTarget.src = product.default_image_url || '/images/product-default.svg';
                                                                }}
                                                            />
                                                            <strong>{product.name}</strong>
                                                            <span>{formatCurrency(product.selling_price)}</span>
                                                            <small>
                                                                Stock: {formatNumber(product.stock_quantity)} {product.unit}
                                                            </small>
                                                        </button>
                                                    );
                                                })}

                                                {posProducts.length === 0 ? (
                                                    <p className="pos-ticket-empty">Aucun produit trouve pour cette recherche.</p>
                                                ) : null}
                                            </div>
                                        </section>
                                    </div>

                                    <div className="pos-sale-side-column">
                                        <section className="pos-section pos-ticket-section">
                                            <div className="pos-section-head">
                                                <h4>2. Ticket</h4>
                                                <button
                                                    type="button"
                                                    className="finance-ghost-button pos-clear-ticket-button"
                                                    onClick={handlePosClearTicket}
                                                    disabled={financeActionLoading || posCartItems.length === 0}
                                                >
                                                    Vider ticket
                                                </button>
                                            </div>

                                            <div className="sale-lines-list pos-ticket-list">
                                                {posCartItems.length === 0 ? (
                                                    <p className="pos-ticket-empty">Ticket vide. Ajoutez des produits pour commencer.</p>
                                                ) : null}

                                                {posCartItems.map((line) => (
                                                    <div className="sale-line-row pos-ticket-row" key={`pos-line-${line.lineIndex}`}>
                                                        <div className="pos-ticket-line-head">
                                                            <strong>{line.product.name}</strong>
                                                            <span>{formatCurrency(line.lineTotal)}</span>
                                                        </div>
                                                        <div className="pos-ticket-controls">
                                                            <div className="pos-qty-stepper">
                                                                <button
                                                                    type="button"
                                                                    className="finance-row-button"
                                                                    onClick={() => handlePosAdjustQuantity(line.lineIndex, -1)}
                                                                    disabled={financeActionLoading}
                                                                >
                                                                    -
                                                                </button>
                                                                <span>{formatNumber(line.quantity)}</span>
                                                                <button
                                                                    type="button"
                                                                    className="finance-row-button"
                                                                    onClick={() => handlePosAdjustQuantity(line.lineIndex, 1)}
                                                                    disabled={financeActionLoading}
                                                                >
                                                                    +
                                                                </button>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                className="finance-row-button finance-row-button-danger pos-remove-line"
                                                                onClick={() => handleRemoveFinanceSaleItem(line.lineIndex)}
                                                                disabled={financeActionLoading}
                                                            >
                                                                Retirer
                                                            </button>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </section>

                                        <section className="pos-section pos-checkout-section">
                                            <div className="pos-section-head">
                                                <h4>3. Paiement et validation</h4>
                                            </div>

                                            <div className="stock-inline-grid">
                                                <label>
                                                    Date
                                                    <input
                                                        type="date"
                                                        name="occurred_on"
                                                        value={financeSaleForm.occurred_on}
                                                        onChange={handleFinanceSaleFormChange}
                                                        required
                                                    />
                                                </label>

                                                <label>
                                                    Paiement
                                                    <select
                                                        name="payment_method"
                                                        value={financeSaleForm.payment_method}
                                                        onChange={handleFinanceSaleFormChange}
                                                        required
                                                    >
                                                        {financePaymentMethods.map((method) => (
                                                            <option key={method} value={method}>
                                                                {method}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </label>
                                            </div>

                                            <div className="sale-total-preview pos-final-total">
                                                {formatNumber(posCartItemsCount)} article(s) - total {formatCurrency(posCartTotal)}
                                            </div>

                                            {financeSaleForm.payment_method === 'credit' ? (
                                                <>
                                                    <label>
                                                        Type client
                                                        <select
                                                            name="customer_mode"
                                                            value={financeSaleForm.customer_mode}
                                                            onChange={handleFinanceSaleFormChange}
                                                        >
                                                            <option value="existing">Client existant</option>
                                                            <option value="new">Nouveau client</option>
                                                        </select>
                                                    </label>

                                                    {financeSaleForm.customer_mode === 'existing' ? (
                                                        <label>
                                                            Client
                                                            <select
                                                                name="customer_id"
                                                                value={financeSaleForm.customer_id}
                                                                onChange={handleFinanceSaleFormChange}
                                                                required
                                                            >
                                                                <option value="">Selectionner client</option>
                                                                {creditCustomers.map((customer) => (
                                                                    <option key={customer.id} value={customer.id}>
                                                                        {customer.name}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </label>
                                                    ) : (
                                                        <>
                                                            <label>
                                                                Nom client
                                                                <input
                                                                    name="new_customer_name"
                                                                    value={financeSaleForm.new_customer_name}
                                                                    onChange={handleFinanceSaleFormChange}
                                                                    required
                                                                />
                                                            </label>

                                                            <label>
                                                                Telephone
                                                                <input
                                                                    name="new_customer_phone"
                                                                    value={financeSaleForm.new_customer_phone}
                                                                    onChange={handleFinanceSaleFormChange}
                                                                />
                                                            </label>

                                                            <label>
                                                                Adresse
                                                                <input
                                                                    name="new_customer_address"
                                                                    value={financeSaleForm.new_customer_address}
                                                                    onChange={handleFinanceSaleFormChange}
                                                                />
                                                            </label>

                                                            <label>
                                                                Notes client
                                                                <input
                                                                    name="new_customer_notes"
                                                                    value={financeSaleForm.new_customer_notes}
                                                                    onChange={handleFinanceSaleFormChange}
                                                                />
                                                            </label>
                                                        </>
                                                    )}
                                                </>
                                            ) : null}

                                            <label>
                                                Note vente
                                                <input
                                                    name="note"
                                                    value={financeSaleForm.note}
                                                    onChange={handleFinanceSaleFormChange}
                                                />
                                            </label>

                                            <div className="finance-form-actions">
                                                <button type="submit" className="pos-submit-button" disabled={financeActionLoading}>
                                                    {financeActionLoading ? 'En cours...' : 'Enregistrer vente'}
                                                </button>
                                            </div>
                                        </section>
                                    </div>
                                </div>
                            </form>

                            <div className="finance-journal-toggle-wrap">
                                <button
                                    type="button"
                                    className="finance-ghost-button finance-journal-toggle-button"
                                    onClick={() => setIsFinanceJournalVisible((current) => !current)}
                                    aria-expanded={isFinanceJournalVisible}
                                    aria-controls="finance-journal-panel"
                                >
                                    {isFinanceJournalVisible ? 'Masquer tableau des operations' : 'Afficher tableau des operations'}
                                </button>
                            </div>
                        </div>

                        {isFinanceJournalVisible ? (
                        <div id="finance-journal-panel" className="stock-table-panel finance-table-panel">
                            <form className="stock-form finance-journal-filters" onSubmit={handleFinanceFiltersSubmit}>
                                <h3>Filtres journal</h3>

                                <label>
                                    Type
                                    <select
                                        name="entry_type"
                                        value={financeFilters.entry_type}
                                        onChange={handleFinanceFilterChange}
                                    >
                                        <option value="">Tous</option>
                                        <option value="income">revenu</option>
                                        <option value="expense">depense</option>
                                    </select>
                                </label>

                                <label>
                                    Categorie
                                    <select
                                        name="category"
                                        value={financeFilters.category}
                                        onChange={handleFinanceFilterChange}
                                    >
                                        <option value="">Toutes</option>
                                        {financeCategories.map((category) => (
                                            <option key={category} value={category}>
                                                {category}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <div className="stock-inline-grid">
                                    <label>
                                        Du
                                        <input
                                            type="date"
                                            name="occurred_from"
                                            value={financeFilters.occurred_from}
                                            onChange={handleFinanceFilterChange}
                                        />
                                    </label>

                                    <label>
                                        Au
                                        <input
                                            type="date"
                                            name="occurred_to"
                                            value={financeFilters.occurred_to}
                                            onChange={handleFinanceFilterChange}
                                        />
                                    </label>
                                </div>

                                <div className="finance-filter-actions">
                                    <button type="submit" disabled={financeActionLoading}>
                                        {financeActionLoading ? 'En cours...' : 'Appliquer'}
                                    </button>
                                    <button
                                        type="button"
                                        className="finance-ghost-button"
                                        onClick={handleFinanceFiltersReset}
                                        disabled={financeActionLoading}
                                    >
                                        Reinitialiser
                                    </button>
                                </div>
                            </form>

                            <h3>Journal financier</h3>
                            <p className="finance-summary-line">
                                {formatNumber(financeEntries.length)} ecritures chargees selon le filtre actif.
                            </p>

                            <div className="module-grid-values finance-grid-values">
                                <div>
                                    <span>Revenus reels filtres</span>
                                    <strong>{formatCurrency(financeTotals.income_total)}</strong>
                                </div>
                                <div>
                                    <span>Revenus potentiels</span>
                                    <strong>{formatCurrency(financeTotals.potential_income)}</strong>
                                </div>
                                <div>
                                    <span>Depenses filtrees</span>
                                    <strong>{formatCurrency(financeTotals.expense_total)}</strong>
                                </div>
                                <div>
                                    <span>Net filtre</span>
                                    <strong className={filteredNetResultClass}>
                                        {formatCurrency(financeTotals.net_result)}
                                    </strong>
                                </div>
                            </div>

                            <div className="stock-table-wrap">
                                <table className="stock-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Categorie</th>
                                            <th>Montant</th>
                                            <th>Paiement</th>
                                            <th>Client</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {financeEntries.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="table-empty-cell">Aucune ecriture financiere pour ce filtre.</td>
                                            </tr>
                                        ) : null}

                                        {financeEntries.map((entry) => (
                                            <tr key={entry.id}>
                                                <td data-label="Date">{formatDate(entry.occurred_on)}</td>
                                                <td data-label="Type">{entry.entry_type === 'income' ? 'revenu' : 'depense'}</td>
                                                <td data-label="Categorie">{entry.category}</td>
                                                <td data-label="Montant" className={entry.entry_type === 'income' ? 'is-positive' : 'is-negative'}>
                                                    {formatCurrency(entry.amount)}
                                                </td>
                                                <td data-label="Paiement">{entry.payment_method}</td>
                                                <td data-label="Client">
                                                    {entry.payment_method === 'credit'
                                                        ? (entry.customer?.name ?? 'Client inconnu')
                                                        : 'Anonyme'}
                                                </td>
                                                <td data-label="Note">{entry.note || 'n/a'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="workspace-pagination">
                                <button
                                    type="button"
                                    className="finance-ghost-button"
                                    onClick={() => handleFinancePageChange(financePagination.current_page - 1)}
                                    disabled={financeActionLoading || financePagination.current_page <= 1}
                                >
                                    Precedent
                                </button>
                                <span>
                                    Page {formatNumber(financePagination.current_page)} / {formatNumber(financePagination.last_page)}
                                    {' '}({formatNumber(financePagination.total)} ecritures)
                                </span>
                                <button
                                    type="button"
                                    className="finance-ghost-button"
                                    onClick={() => handleFinancePageChange(financePagination.current_page + 1)}
                                    disabled={financeActionLoading || financePagination.current_page >= financePagination.last_page}
                                >
                                    Suivant
                                </button>
                            </div>
                        </div>
                        ) : null}
                    </div>
                </section>
                ) : null}

                {activeWorkspace === 'credits' ? (
                <section className="stock-workspace credits-workspace" aria-label="Gestion detaillee credits clients">
                    <div className="stock-workspace-head">
                        <h2>Module 3: Credits clients</h2>
                        <p>
                            Clients, ouverture d ardoises et suivi des paiements.
                        </p>
                    </div>

                    {creditsActionMessage ? (
                        <p className="stock-alert stock-alert-success">{creditsActionMessage}</p>
                    ) : null}

                    {creditsActionError ? (
                        <p className="stock-alert stock-alert-error">{creditsActionError}</p>
                    ) : null}

                    <div className="credits-workspace-grid">
                        <div className="stock-forms-panel">
                            <form className="stock-form" onSubmit={handleCreditsFiltersSubmit}>
                                <h3>Filtres credits</h3>

                                <label>
                                    Statut
                                    <select
                                        name="status"
                                        value={creditsFilters.status}
                                        onChange={handleCreditsFilterChange}
                                    >
                                        <option value="">Tous</option>
                                        <option value="open">ouvert</option>
                                        <option value="partial">partiel</option>
                                        <option value="paid">regle</option>
                                    </select>
                                </label>

                                <label>
                                    Client
                                    <select
                                        name="customer_id"
                                        value={creditsFilters.customer_id}
                                        onChange={handleCreditsFilterChange}
                                    >
                                        <option value="">Tous les clients</option>
                                        {creditCustomers.map((customer) => (
                                            <option key={customer.id} value={customer.id}>
                                                {customer.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="stock-checkbox">
                                    <input
                                        type="checkbox"
                                        name="overdue_only"
                                        checked={creditsFilters.overdue_only}
                                        onChange={handleCreditsFilterChange}
                                    />
                                    Uniquement en retard
                                </label>

                                <div className="finance-filter-actions">
                                    <button type="submit" disabled={creditsActionLoading}>
                                        {creditsActionLoading ? 'En cours...' : 'Appliquer'}
                                    </button>
                                    <button
                                        type="button"
                                        className="finance-ghost-button"
                                        onClick={handleCreditsFiltersReset}
                                        disabled={creditsActionLoading}
                                    >
                                        Reinitialiser
                                    </button>
                                </div>
                            </form>

                            <form className="stock-form" onSubmit={handleCreateCustomer}>
                                <h3>Nouveau client</h3>

                                <label>
                                    Nom
                                    <input
                                        name="name"
                                        value={customerForm.name}
                                        onChange={handleCustomerFormChange}
                                        required
                                    />
                                </label>

                                <label>
                                    Telephone
                                    <input
                                        name="phone"
                                        value={customerForm.phone}
                                        onChange={handleCustomerFormChange}
                                    />
                                </label>

                                <label>
                                    Adresse
                                    <input
                                        name="address"
                                        value={customerForm.address}
                                        onChange={handleCustomerFormChange}
                                    />
                                </label>

                                <label>
                                    Notes
                                    <input
                                        name="notes"
                                        value={customerForm.notes}
                                        onChange={handleCustomerFormChange}
                                    />
                                </label>

                                <button type="submit" disabled={creditsActionLoading}>
                                    {creditsActionLoading ? 'En cours...' : 'Ajouter client'}
                                </button>
                            </form>

                            <form className="stock-form" onSubmit={handleCreateCreditEntry}>
                                <h3>Nouvelle ardoise</h3>

                                <label>
                                    Client
                                    <select
                                        name="customer_id"
                                        value={creditEntryForm.customer_id}
                                        onChange={handleCreditEntryFormChange}
                                        required
                                    >
                                        <option value="">Selectionner</option>
                                        {creditCustomers.map((customer) => (
                                            <option key={customer.id} value={customer.id}>
                                                {customer.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <div className="stock-inline-grid">
                                    <label>
                                        Montant total
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            name="total_amount"
                                            value={creditEntryForm.total_amount}
                                            onChange={handleCreditEntryFormChange}
                                            required
                                        />
                                    </label>

                                    <label>
                                        Ouverture
                                        <input
                                            type="date"
                                            name="opened_on"
                                            value={creditEntryForm.opened_on}
                                            onChange={handleCreditEntryFormChange}
                                            required
                                        />
                                    </label>
                                </div>

                                <label>
                                    Echeance
                                    <input
                                        type="date"
                                        name="due_date"
                                        value={creditEntryForm.due_date}
                                        onChange={handleCreditEntryFormChange}
                                    />
                                </label>

                                <label>
                                    Note
                                    <input
                                        name="note"
                                        value={creditEntryForm.note}
                                        onChange={handleCreditEntryFormChange}
                                    />
                                </label>

                                <button type="submit" disabled={creditsActionLoading}>
                                    {creditsActionLoading ? 'En cours...' : 'Ouvrir ardoise'}
                                </button>
                            </form>

                            <form className="stock-form" onSubmit={handleUpdateCreditEntry}>
                                <h3>Modifier ardoise</h3>

                                {!creditEditForm.id ? (
                                    <p className="finance-summary-line">
                                        Cliquez sur Modifier dans le tableau pour charger une ardoise.
                                    </p>
                                ) : null}

                                <label>
                                    Client
                                    <select
                                        name="customer_id"
                                        value={creditEditForm.customer_id}
                                        onChange={handleCreditEditFormChange}
                                        required
                                    >
                                        <option value="">Selectionner</option>
                                        {creditCustomers.map((customer) => (
                                            <option key={customer.id} value={customer.id}>
                                                {customer.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <div className="stock-inline-grid">
                                    <label>
                                        Montant total
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            name="total_amount"
                                            value={creditEditForm.total_amount}
                                            onChange={handleCreditEditFormChange}
                                            required
                                        />
                                    </label>

                                    <label>
                                        Ouverture
                                        <input
                                            type="date"
                                            name="opened_on"
                                            value={creditEditForm.opened_on}
                                            onChange={handleCreditEditFormChange}
                                            required
                                        />
                                    </label>
                                </div>

                                <label>
                                    Echeance
                                    <input
                                        type="date"
                                        name="due_date"
                                        value={creditEditForm.due_date}
                                        onChange={handleCreditEditFormChange}
                                    />
                                </label>

                                <label>
                                    Note
                                    <input
                                        name="note"
                                        value={creditEditForm.note}
                                        onChange={handleCreditEditFormChange}
                                    />
                                </label>

                                <div className="finance-form-actions">
                                    <button type="submit" disabled={creditsActionLoading || !creditEditForm.id}>
                                        {creditsActionLoading ? 'En cours...' : 'Mettre a jour ardoise'}
                                    </button>

                                    <button
                                        type="button"
                                        className="finance-ghost-button"
                                        onClick={handleCancelEditCreditEntry}
                                        disabled={creditsActionLoading || !creditEditForm.id}
                                    >
                                        Annuler
                                    </button>
                                </div>
                            </form>

                            <form className="stock-form" onSubmit={handleCreateCreditPayment}>
                                <h3>Enregistrer paiement</h3>

                                <label>
                                    Ardoise
                                    <select
                                        name="customer_credit_id"
                                        value={creditPaymentForm.customer_credit_id}
                                        onChange={handleCreditPaymentFormChange}
                                        required
                                    >
                                        <option value="">Selectionner</option>
                                        {actionableCreditEntries.map((entry) => (
                                            <option key={entry.id} value={entry.id}>
                                                {(entry.customer?.name ?? 'Client inconnu')}
                                                {' - reste '}
                                                {formatCurrency(entry.remaining_amount)}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <div className="stock-inline-grid">
                                    <label>
                                        Montant paye
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            name="amount"
                                            value={creditPaymentForm.amount}
                                            onChange={handleCreditPaymentFormChange}
                                            required
                                        />
                                    </label>

                                    <label>
                                        Date paiement
                                        <input
                                            type="date"
                                            name="paid_at"
                                            value={creditPaymentForm.paid_at}
                                            onChange={handleCreditPaymentFormChange}
                                            required
                                        />
                                    </label>
                                </div>

                                <label>
                                    Note
                                    <input
                                        name="note"
                                        value={creditPaymentForm.note}
                                        onChange={handleCreditPaymentFormChange}
                                    />
                                </label>

                                <button type="submit" disabled={creditsActionLoading}>
                                    {creditsActionLoading ? 'En cours...' : 'Valider paiement'}
                                </button>
                            </form>
                        </div>

                        <div className="stock-table-panel credits-table-panel">
                            <h3>Journal des ardoises</h3>
                            <p className="finance-summary-line">
                                {formatNumber(creditEntries.length)} ardoises visibles.
                            </p>

                            <div className="module-grid-values credits-grid-values">
                                <div>
                                    <span>Total du</span>
                                    <strong>{formatCurrency(creditTotals.total_due)}</strong>
                                </div>
                                <div>
                                    <span>En retard</span>
                                    <strong className="is-negative">{formatCurrency(creditTotals.overdue_amount)}</strong>
                                </div>
                                <div>
                                    <span>A 7 jours</span>
                                    <strong>{formatCurrency(creditTotals.due_this_week)}</strong>
                                </div>
                            </div>

                            <div className="customer-balance-list">
                                {(creditsOverview.top_customers_by_due ?? []).map((customer) => (
                                    <article key={customer.id}>
                                        <h4>{customer.name}</h4>
                                        <p>{formatCurrency(customer.total_due)} a recouvrer</p>
                                    </article>
                                ))}
                            </div>

                            <div className="stock-table-wrap">
                                <table className="stock-table">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Ouverture</th>
                                            <th>Echeance</th>
                                            <th>Total</th>
                                            <th>Paye</th>
                                            <th>Reste</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {creditEntries.length === 0 ? (
                                            <tr>
                                                <td colSpan={8} className="table-empty-cell">Aucune ardoise pour ce filtre.</td>
                                            </tr>
                                        ) : null}

                                        {creditEntries.map((entry) => (
                                            <tr key={entry.id}>
                                                <td data-label="Client">{entry.customer?.name ?? 'Client inconnu'}</td>
                                                <td data-label="Ouverture">{formatDate(entry.opened_on)}</td>
                                                <td data-label="Echeance">{formatDate(entry.due_date)}</td>
                                                <td data-label="Total">{formatCurrency(entry.total_amount)}</td>
                                                <td data-label="Paye">{formatCurrency(entry.paid_amount)}</td>
                                                <td data-label="Reste">{formatCurrency(entry.remaining_amount)}</td>
                                                <td data-label="Statut">
                                                    <span
                                                        className={`credits-status-badge credits-status-${entry.status}`}
                                                    >
                                                        {entry.status}
                                                    </span>
                                                </td>
                                                <td data-label="Actions">
                                                    <div className="finance-row-actions">
                                                        <button
                                                            type="button"
                                                            className="finance-row-button"
                                                            onClick={() => handleStartEditCreditEntry(entry)}
                                                            disabled={creditsActionLoading}
                                                        >
                                                            Modifier
                                                        </button>

                                                        {(entry.status === 'open' || entry.status === 'partial') ? (
                                                            <button
                                                                type="button"
                                                                className="finance-row-button"
                                                                onClick={() => handlePrefillCreditPayment(entry)}
                                                                disabled={creditsActionLoading}
                                                            >
                                                                Pointer paiement
                                                            </button>
                                                        ) : null}

                                                        <button
                                                            type="button"
                                                            className="finance-row-button finance-row-button-danger"
                                                            onClick={() => handleDeleteCreditEntry(entry)}
                                                            disabled={creditsActionLoading}
                                                        >
                                                            Supprimer
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="workspace-pagination">
                                <button
                                    type="button"
                                    className="finance-ghost-button"
                                    onClick={() => handleCreditsPageChange(creditsPagination.current_page - 1)}
                                    disabled={creditsActionLoading || creditsPagination.current_page <= 1}
                                >
                                    Precedent
                                </button>
                                <span>
                                    Page {formatNumber(creditsPagination.current_page)} / {formatNumber(creditsPagination.last_page)}
                                    {' '}({formatNumber(creditsPagination.total)} ardoises)
                                </span>
                                <button
                                    type="button"
                                    className="finance-ghost-button"
                                    onClick={() => handleCreditsPageChange(creditsPagination.current_page + 1)}
                                    disabled={creditsActionLoading || creditsPagination.current_page >= creditsPagination.last_page}
                                >
                                    Suivant
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
                ) : null}
                </section>
            </main>
        </div>
    );
}

export default DashboardApp;
