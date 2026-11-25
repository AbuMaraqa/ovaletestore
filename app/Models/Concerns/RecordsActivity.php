<?php

namespace App\Models\Concerns;

use App\Support\ActivityLog;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Audit trail for a model (spatie/laravel-activitylog v5): created / updated / deleted with the
 * old → new values of the logged attributes (`attribute_changes`), the user who did it and their IP.
 *
 * Child rows (variants, translations, notes, payments…) declare their parent with activityParent(),
 * so a product's history also shows its variant price changes and translation edits.
 * Each entry stores a readable `label` so the log stays meaningful after the record is deleted.
 */
trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $only = $this->activityAttributes();

        return ($only ? LogOptions::defaults()->logOnly($only) : LogOptions::defaults()->logFillable())
            ->useLogName(ActivityLog::logNameFor($this))
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** Attributes to record; empty = every fillable attribute. */
    protected function activityAttributes(): array
    {
        return [];
    }

    /** @return array{0: class-string, 1: int|string|null}|null the record this change belongs to */
    public function activityParent(): ?array
    {
        return null;
    }

    /** Human label stored with the entry (the record may be deleted later). */
    public function activityLabel(): string
    {
        foreach (['name', 'number', 'code', 'sku', 'email', 'from_path'] as $key) {
            if (array_key_exists($key, $this->attributes) && filled($this->attributes[$key])) {
                return (string) $this->attributes[$key];
            }
        }

        // Translatable models keep their name on the translation rows. Read them explicitly — never a
        // lazy load (strict mode) — preferring the current locale, then Arabic.
        if (method_exists($this, 'translations') && $this->exists) {
            $names = $this->relationLoaded('translations')
                ? $this->getRelation('translations')->pluck('name', 'locale')
                : $this->translations()->pluck('name', 'locale');
            $name = $names[app()->getLocale()] ?? $names['ar'] ?? $names->first();
            if (filled($name)) {
                return (string) $name;
            }
        }

        return '#'.$this->getKey();
    }

    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $extra = ['label' => mb_substr($this->activityLabel(), 0, 190)];

        if ($parent = $this->activityParent()) {
            [$class, $id] = $parent;
            // Indexed columns (see the add_parent_columns_to_activity_log migration).
            $activity->setAttribute('parent_type', $class);
            $activity->setAttribute('parent_id', $id);
        }
        if (array_key_exists('locale', $this->attributes)) {
            $extra['locale'] = $this->attributes['locale'];
        }

        $activity->properties = collect($activity->properties)->merge($extra);
    }
}
