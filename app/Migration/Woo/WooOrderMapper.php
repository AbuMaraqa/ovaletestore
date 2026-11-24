<?php

namespace App\Migration\Woo;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Support\Carbon;

/**
 * Turns one WooCommerce REST order (+ its notes) into the neutral seed record OrderSeeder writes.
 * Pure: no database access, so the mapping is unit-testable. Money becomes integer agorot.
 */
class WooOrderMapper
{
    /** WooCommerce status → ours. Refunded/failed have no equivalent: they count as cancelled (+ a note). */
    private const STATUSES = [
        'pending' => OrderStatus::Pending,
        'on-hold' => OrderStatus::Pending,
        'checkout-draft' => OrderStatus::Pending,
        'pos-open' => OrderStatus::Pending,
        'pos-partial' => OrderStatus::Pending,
        'processing' => OrderStatus::Processing,
        'completed' => OrderStatus::Completed,
        'cancelled' => OrderStatus::Cancelled,
        'refunded' => OrderStatus::Cancelled,
        'failed' => OrderStatus::Cancelled,
    ];

    /**
     * @param  array<string, mixed>  $order
     * @param  list<array<string, mixed>>  $notes
     * @return array<string, mixed>
     */
    public static function map(array $order, array $notes = []): array
    {
        $billing = (array) ($order['billing'] ?? []);
        $shipping = (array) ($order['shipping'] ?? []);
        $pick = fn (string $key): string => trim((string) (($billing[$key] ?? '') ?: ($shipping[$key] ?? '')));

        $city = $pick('city');
        $area = $pick('state');
        $address = collect([$area !== $city ? $area : null, $pick('address_1'), $pick('address_2')])
            ->filter(fn ($part) => $part !== null && $part !== '')->implode('، ');

        $items = [];
        foreach ((array) ($order['line_items'] ?? []) as $line) {
            $quantity = max(1, (int) $line['quantity']);
            $items[] = [
                'product_source_id' => (int) $line['product_id'] ?: null,
                'name' => trim((string) $line['name']),
                'sku' => ($line['sku'] ?? '') !== '' ? (string) $line['sku'] : null,
                'unit_price' => intdiv(self::agorot($line['subtotal'] ?? $line['total']), $quantity),
                'quantity' => $quantity,
                'line_total' => self::agorot($line['total']),
                'subtotal' => self::agorot($line['subtotal'] ?? $line['total']),
            ];
        }

        $sourceStatus = (string) $order['status'];
        $shippingLine = (array) (($order['shipping_lines'] ?? [])[0] ?? []);
        $shippingName = trim((string) ($shippingLine['method_title'] ?? '')) ?: null;
        $isPos = ($order['created_via'] ?? '') === 'woocommerce-pos';

        return [
            'source_id' => (int) $order['id'],
            'number' => 'WC-'.($order['number'] ?? $order['id']),
            'status' => (self::STATUSES[$sourceStatus] ?? OrderStatus::Pending)->value,
            'source_status' => $sourceStatus,
            'channel' => ($isPos ? OrderChannel::Pos : OrderChannel::Web)->value,
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'source_payment' => (string) ($order['payment_method_title'] ?? '') ?: null,
            'customer_name' => trim($pick('first_name').' '.$pick('last_name')) ?: trim((string) ($billing['company'] ?? '')) ?: '—',
            'customer_email' => strtolower(trim((string) ($billing['email'] ?? ''))) ?: null,
            'customer_phone' => mb_substr($pick('phone'), 0, 32) ?: null,
            'shipping_city' => mb_substr($city ?: $area, 0, 120) ?: null,
            'shipping_address' => mb_substr($address, 0, 500) ?: null,
            'shipping_method_code' => self::shippingCode($shippingName),
            'shipping_method_name' => $shippingName ? mb_substr($shippingName, 0, 120) : null,
            'customer_note' => trim((string) ($order['customer_note'] ?? '')) ?: null,
            'currency' => (string) ($order['currency'] ?? 'ILS'),
            'subtotal' => array_sum(array_column($items, 'subtotal')),
            'discount_total' => self::agorot($order['discount_total'] ?? 0),
            'shipping_total' => self::agorot($order['shipping_total'] ?? 0),
            'total' => self::agorot($order['total']),
            'locale' => self::locale($order),
            'placed_at' => self::utc($order['date_created_gmt'] ?? null, $order['date_created'] ?? null),
            'updated_at' => self::utc($order['date_modified_gmt'] ?? null, $order['date_modified'] ?? null),
            'customer_source_id' => (int) ($order['customer_id'] ?? 0) ?: null,
            'items' => array_map(fn (array $item) => array_diff_key($item, ['subtotal' => true]), $items),
            'notes' => self::notes($order, $notes, $sourceStatus),
        ];
    }

    /** "161.00" → 16100 (rounded, never float-truncated). */
    public static function agorot(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private static function shippingCode(?string $name): ?string
    {
        return match (true) {
            $name === null => null,
            str_contains($name, 'الضفة') || str_contains($name, 'الضفه') => 'west_bank',
            str_contains($name, 'القدس') => 'jerusalem',
            str_contains($name, 'الداخل') => 'inside',
            str_contains($name, 'استلام') => 'pickup',
            default => null,
        };
    }

    /** @param  array<string, mixed>  $order */
    private static function locale(array $order): string
    {
        foreach ((array) ($order['meta_data'] ?? []) as $meta) {
            if (($meta['key'] ?? null) === 'trp_language') {
                $locale = strtolower(substr((string) $meta['value'], 0, 2));

                return in_array($locale, (array) config('translatable.locales', ['ar', 'en', 'he']), true) ? $locale : 'ar';
            }
        }

        return 'ar';
    }

    private static function utc(?string $gmt, ?string $local): ?string
    {
        if ($gmt) {
            return Carbon::parse($gmt, 'UTC')->toIso8601String();
        }

        return $local ? Carbon::parse($local, (string) config('app.timezone'))->utc()->toIso8601String() : null;
    }

    /**
     * Source history, oldest first, plus one internal note recording where the order came from.
     *
     * @param  array<string, mixed>  $order
     * @param  list<array<string, mixed>>  $notes
     * @return list<array{body:string, is_customer_visible:bool, created_at:?string}>
     */
    private static function notes(array $order, array $notes, string $sourceStatus): array
    {
        $out = [];
        $imported = "WooCommerce #{$order['id']} ({$sourceStatus})";
        foreach ((array) ($order['refunds'] ?? []) as $refund) {
            $imported .= ' — refund '.number_format(abs((float) $refund['total']), 2).(($refund['reason'] ?? '') !== '' ? ': '.$refund['reason'] : '');
        }
        $out[] = ['body' => $imported, 'is_customer_visible' => false, 'created_at' => self::utc($order['date_created_gmt'] ?? null, $order['date_created'] ?? null)];

        foreach (array_reverse($notes) as $note) {
            $body = trim(html_entity_decode(strip_tags((string) ($note['note'] ?? '')), ENT_QUOTES | ENT_HTML5));
            if ($body === '') {
                continue;
            }
            $author = trim((string) ($note['author'] ?? ''));
            $out[] = [
                'body' => ($author !== '' && $author !== 'WooCommerce' && $author !== 'system' ? $author.': ' : '').$body,
                'is_customer_visible' => (bool) ($note['customer_note'] ?? false),
                'created_at' => self::utc($note['date_created_gmt'] ?? null, $note['date_created'] ?? null),
            ];
        }

        return $out;
    }
}
