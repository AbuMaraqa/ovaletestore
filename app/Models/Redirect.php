<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    use RecordsActivity;

    protected $fillable = ['from_path', 'to_path', 'status_code'];

    protected function casts(): array
    {
        return ['last_hit_at' => 'datetime', 'hits' => 'integer', 'status_code' => 'integer'];
    }

    /** Canonical form used for storage and lookup: decoded, leading slash, no trailing slash. */
    public static function normalizePath(string $path): string
    {
        $path = rawurldecode(parse_url($path, PHP_URL_PATH) ?: '/');

        return '/'.trim($path, '/');
    }
}
