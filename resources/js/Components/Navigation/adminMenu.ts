/** Admin navigation definition. `permission` only controls visibility — the server authorizes every action. */
export interface MenuItem {
    label: string;
    icon: string;
    route: string;
    match: string;
    permission: string;
    params?: Record<string, string>;
}

export interface MenuSection {
    heading?: string;
    items: MenuItem[];
}

export const adminMenu: MenuSection[] = [
    {
        items: [{ label: 'admin.dashboard', icon: 'element-11', route: 'admin.dashboard', match: 'admin.dashboard', permission: 'admin.access' }],
    },
    {
        heading: 'admin.catalog',
        items: [
            { label: 'admin.products', icon: 'basket', route: 'admin.products.index', match: 'admin.products.*', permission: 'products.view' },
            { label: 'admin.categories', icon: 'category', route: 'admin.categories.index', match: 'admin.categories.*', permission: 'categories.manage' },
            { label: 'admin.attributes', icon: 'setting-4', route: 'admin.attributes.index', match: 'admin.attributes.*', permission: 'attributes.manage' },
            { label: 'admin.brands', icon: 'award', route: 'admin.brands.index', match: 'admin.brands.*', permission: 'brands.manage' },
        ],
    },
    {
        heading: 'admin.sales',
        items: [{ label: 'admin.orders', icon: 'handcart', route: 'admin.orders.index', match: 'admin.orders.*', permission: 'orders.view' }],
    },
    {
        heading: 'admin.system',
        items: [
            { label: 'admin.users', icon: 'people', route: 'admin.users.index', match: 'admin.users.*', permission: 'users.manage' },
            { label: 'admin.settings', icon: 'setting-2', route: 'admin.settings.edit', params: { group: 'general' }, match: 'admin.settings.*', permission: 'settings.manage' },
        ],
    },
];
