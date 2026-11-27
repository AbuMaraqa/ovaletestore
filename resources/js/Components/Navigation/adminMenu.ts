/**
 * Admin navigation (Metronic demo1 sidebar structure: headings, single links, accordion groups).
 * `permission` only controls visibility — every action is authorized on the server.
 */
export interface MenuLink {
    label: string;
    route: string;
    match: string;
    permission: string;
    params?: Record<string, string>;
    /** Leaves the admin SPA with a full page load (another area with its own CSS bundle, e.g. the POS). */
    external?: boolean;
}

export interface MenuItem extends Partial<MenuLink> {
    label: string;
    icon: string;
    children?: MenuLink[];
    /** Key of a count in the `admin_badges` shared prop, shown next to the label. */
    badge?: 'pending_orders' | 'pending_traders';
}

export interface MenuSection {
    heading?: string;
    items: MenuItem[];
}

export const adminMenu: MenuSection[] = [
    {
        items: [
            { label: 'admin.dashboard', icon: 'element-11', route: 'admin.dashboard', match: 'admin.dashboard', permission: 'admin.access' },
            { label: 'analytics.title', icon: 'chart-simple', route: 'admin.analytics.index', match: 'admin.analytics.*', permission: 'analytics.view' },
        ],
    },
    {
        heading: 'admin.catalog',
        items: [
            {
                label: 'admin.products',
                icon: 'basket',
                children: [
                    { label: 'admin.all_products', route: 'admin.products.index', match: 'admin.products.index', permission: 'products.view' },
                    { label: 'admin.new_product', route: 'admin.products.create', match: 'admin.products.create', permission: 'products.create' },
                ],
            },
            { label: 'admin.categories', icon: 'category', route: 'admin.categories.index', match: 'admin.categories.*', permission: 'categories.view' },
            { label: 'admin.attributes', icon: 'setting-4', route: 'admin.attributes.index', match: 'admin.attributes.*', permission: 'attributes.view' },
            { label: 'admin.brands', icon: 'award', route: 'admin.brands.index', match: 'admin.brands.*', permission: 'brands.view' },
        ],
    },
    {
        heading: 'admin.sales',
        items: [
            { label: 'admin.orders', icon: 'handcart', route: 'admin.orders.index', match: 'admin.orders.*', permission: 'orders.view', badge: 'pending_orders' },
            { label: 'admin.pos', icon: 'tablet', route: 'pos.index', match: 'pos.*', permission: 'pos.access', external: true },
            { label: 'admin.customers', icon: 'profile-circle', route: 'admin.customers.index', match: 'admin.customers.*', permission: 'customers.view' },
            {
                label: 'traders.title',
                icon: 'shop',
                badge: 'pending_traders',
                children: [
                    { label: 'traders.list', route: 'admin.traders.index', match: 'admin.traders.*', permission: 'traders.view' },
                    { label: 'traders.tiers', route: 'admin.price-tiers.index', match: 'admin.price-tiers.*', permission: 'price_tiers.view' },
                ],
            },
            { label: 'admin.companies', icon: 'office-bag', route: 'admin.companies.index', match: 'admin.companies.*', permission: 'companies.view' },
            { label: 'admin.shipping_methods', icon: 'delivery', route: 'admin.shipping-methods.index', match: 'admin.shipping-methods.*', permission: 'shipping.view' },
        ],
    },
    {
        heading: 'admin.system',
        items: [
            {
                label: 'admin.staff',
                icon: 'people',
                children: [
                    { label: 'admin.users', route: 'admin.users.index', match: 'admin.users.*', permission: 'users.view' },
                    { label: 'admin.roles', route: 'admin.roles.index', match: 'admin.roles.*', permission: 'roles.view' },
                ],
            },
            { label: 'currencies.title', icon: 'dollar', route: 'admin.currencies.index', match: 'admin.currencies.*', permission: 'currencies.view' },
            { label: 'admin.activity_log', icon: 'time', route: 'admin.activity.index', match: 'admin.activity.*', permission: 'activity.view' },
            {
                label: 'admin.settings',
                icon: 'setting-2',
                children: ['general', 'contact', 'store', 'seo', 'mail', 'invoice'].map((group) => ({
                    label: `admin.${group}`,
                    route: 'admin.settings.edit',
                    params: { group },
                    match: 'admin.settings.*',
                    permission: `settings.${group}`,
                })),
            },
        ],
    },
];
