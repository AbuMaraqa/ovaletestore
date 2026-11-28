<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Shetabit\Visitor\Models\Visit;

/**
 * One storefront page view, logged through shetabit/visitor (config visitor.model).
 *
 * The package captures the whole request: every header (session cookie included), the request input, the IP and the
 * user agent. None of that is kept: `creating` drops it and moves the report fields the middleware passed as extra
 * attributes (TrackStoreVisit::fields()) into their own indexed columns. Rows older than 400 days are pruned daily.
 *
 * @property string|null $path
 * @property string|null $subject
 * @property string|null $visitor_hash
 * @property string|null $session_hash
 * @property string|null $device_type
 * @property string|null $channel
 * @property string|null $referrer_host
 * @property string|null $locale
 */
class StoreVisit extends Visit
{
    use MassPrunable;

    /** Report columns the middleware fills (through the package's extra attributes). */
    public const REPORT_FIELDS = ['path', 'subject', 'visitor_hash', 'session_hash', 'device_type', 'channel', 'referrer_host', 'locale'];

    public const KEEP_DAYS = 400;

    protected static function booted(): void
    {
        static::creating(function (StoreVisit $visit): void {
            $extra = $visit->getAttribute('extra_attributes');
            $extra = is_string($extra) ? (json_decode($extra, true) ?: []) : (array) $extra;
            foreach (self::REPORT_FIELDS as $field) {
                $visit->setAttribute($field, $extra[$field] ?? null);
                unset($extra[$field]);
            }
            $visit->setAttribute('extra_attributes', $extra === [] ? null : $extra);

            // Privacy: nothing that identifies the person or their session is stored.
            foreach (['headers', 'request', 'ip', 'useragent', 'visitor_id', 'visitor_type'] as $field) {
                $visit->setAttribute($field, null);
            }
            $visit->setAttribute('url', mb_substr((string) $visit->getAttribute('url'), 0, 2000));
            $visit->setAttribute('referer', mb_substr((string) $visit->getAttribute('referer'), 0, 2000) ?: null);
        });
    }

    public function getFillable(): array
    {
        return [...parent::getFillable(), ...self::REPORT_FIELDS];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
