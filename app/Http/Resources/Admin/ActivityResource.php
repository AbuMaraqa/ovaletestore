<?php

namespace App\Http\Resources\Admin;

use App\Support\ActivityLog;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * One audit-trail entry for the admin (who, what, when, from where, and the old → new values).
 *
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /** Integer money columns (agorot) shown as amounts. */
    private const MONEY = ['price', 'regular_price', 'sale_price', 'cost', 'free_over', 'min_order', 'amount',
        'subtotal', 'discount_total', 'shipping_total', 'total', 'credit_limit', 'min_order_total'];

    public function toArray(Request $request): array
    {
        $changes = $this->attribute_changes ?? collect();
        $new = (array) ($changes['attributes'] ?? []);
        $old = (array) ($changes['old'] ?? []);

        return [
            'id' => $this->id,
            'log' => $this->log_name,
            'event' => $this->event ?? $this->description,
            // For child rows (a variant, a translation…) `subject` is the parent record and `child` the row type.
            'subject' => ActivityLog::keyFor($this->resource->getAttribute('parent_type') ?? $this->subject_type),
            'child' => $this->resource->getAttribute('parent_type') ? ActivityLog::keyFor($this->subject_type) : null,
            'label' => $this->getProperty('label'),
            'locale' => $this->getProperty('locale'),
            'url' => $this->resource->getAttributes()['url'] ?? null, // set by ActivityController::attachUrls()
            'causer' => $this->causer ? [
                'id' => $this->causer->getKey(),
                'name' => $this->causer->getAttribute('name'),
                'avatar' => method_exists($this->causer, 'avatar') ? $this->causer->avatar() : null,
            ] : null,
            'changes' => collect(array_unique([...array_keys($new), ...array_keys($old)]))
                ->map(fn (string $field) => [
                    'field' => $field,
                    'old' => $this->format($field, $old[$field] ?? null),
                    'new' => $this->format($field, $new[$field] ?? null),
                ])->values(),
            'details' => collect($this->properties ?? [])
                ->except(['label', 'parent_type', 'parent_id', 'locale', 'ip', 'agent', 'settings_group'])
                ->all(),
            'ip' => $this->getProperty('ip'),
            'agent' => $this->getProperty('agent'),
            'created_at' => $this->created_at?->toIso8601String(),
            'date' => $this->created_at?->translatedFormat('Y-m-d H:i'),
            'ago' => $this->created_at?->diffForHumans(),
        ];
    }

    private function format(string $field, mixed $value): string|bool|null
    {
        return match (true) {
            $value === null || $value === '' => null,
            is_bool($value) => $value,
            in_array($field, self::MONEY, true) && is_numeric($value) => Money::format((int) $value),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null,
            default => mb_strimwidth(strip_tags((string) $value), 0, 300, '…'),
        };
    }
}
