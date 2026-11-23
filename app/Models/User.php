<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravolt\Avatar\Facade as Avatar;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'locale', 'is_active'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithMedia, Notifiable, TwoFactorAuthenticatable;

    /** Mirrors DB defaults so freshly created models are complete (strict missing-attribute mode). */
    protected $attributes = ['is_active' => true, 'locale' => 'ar'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('avatar')->width(160)->height(160)->format('webp')->nonQueued();
    }

    /** Brand-palette backgrounds for initials avatars (white text passes WCAG AA on all of them). */
    private const AVATAR_COLORS = ['#9a4a24', '#7a3d1f', '#b4542a', '#5b6b2f', '#2f5d62', '#4b3f72', '#8a3b52', '#3d5a80'];

    /**
     * Uploaded photo, otherwise initials rendered as HTML by the frontend (<UserAvatar>), so they use the
     * site font (Tajawal) — an SVG <img> cannot load page fonts. Laravolt computes the initials (Arabic-aware).
     *
     * @return array{url: ?string, initials: string, color: string}
     */
    public function avatar(): array
    {
        $media = $this->getFirstMedia('avatar');
        $generator = Avatar::create((string) $this->name);
        $generator->toSvg(); // builds the initials

        return [
            'url' => $media?->getUrl('avatar'),
            'initials' => $generator->getInitial(),
            'color' => self::AVATAR_COLORS[crc32((string) $this->name) % count(self::AVATAR_COLORS)],
        ];
    }
}
