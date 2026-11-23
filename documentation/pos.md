# POS for sales reps (iPad)

Sales reps take orders for **companies** (B2B customers) from an iPad. Orders use the website prices and go **on the company's account** (debt); the office records payments against it.

## Roles and access
| Who | Permission | Can |
|---|---|---|
| `sales-rep` role | `pos.access` | `/{locale}/pos` only — pick a company, place orders, see **their own** orders. No admin panel. Logs in straight to the POS. |
| `order-manager`, `admin`, `super-admin` | `companies.manage` (+ `pos.access` for admin) | Admin → Companies: create/edit companies, account statement, record payments. |

Create a rep: Admin → Users → New user → role **sales-rep**.

## Flow
1. Rep opens `/ar/pos`, searches and taps a company (the list is prepared in Admin → Companies; inactive companies are hidden).
2. Order screen: whole catalog with search + category filter (client-side, instant). Tap a product to add; adjust quantities in the order panel. The draft is kept on the device per company.
3. **Send order** → `PlacePosOrder`: same stock/availability rules as the website (`BuildOrderLines`), no shipping charge, `channel = pos`, `payment_method = on_account`, `sales_rep_id`, company snapshot (name, phone, city, address). Staff get the usual new-order notification.
4. The order appears in Admin → Orders (POS badge, filter `filter[channel]=pos` / `filter[company_id]=…`) and follows the normal status workflow.

## Company balance
`balance = Σ total of on-account orders (excluding cancelled) − Σ payments`. Admin → Companies → company shows a statement (orders as debit, payments as credit, running balance) and a payment form (cash / cheque / transfer / other, reference, date). A company with orders or payments cannot be deleted — deactivate it.

## Code map
- `app/Http/Controllers/Pos/PosController.php`, `app/Http/Requests/Pos/PosOrderRequest.php`
- `app/Actions/Orders/{PlacePosOrder,BuildOrderLines}.php` (BuildOrderLines is shared with the web checkout)
- `app/Queries/PosCatalog.php` — catalog payload, cached 5 min per locale, flushed by `CatalogCacheObserver`
- `app/Models/{Company,CompanyPayment}.php`, `app/Http/Controllers/Admin/{CompanyController,CompanyPaymentController}.php`
- Vue: `Layouts/PosLayout.vue`, `Pages/Pos/*`, `Components/Pos/*`, `Composables/usePosOrder.ts`, `Pages/Admin/Companies/*`
- Tests: `tests/Feature/Pos/PosTest.php`
