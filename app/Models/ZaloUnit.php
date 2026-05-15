<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloUnit extends Model
{
    use HasFactory;

    protected $table = 'zalo_units';

    protected $fillable = ['code', 'label', 'system_unit_type', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(ZaloProduct::class, 'unit_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Render a system-unit value in the most readable form.
     * - g → kg when ≥ 1000
     * - ml → l when ≥ 1000
     * - piece → "X cái"
     */
    public static function formatSystemTotal(float $value, string $systemUnit): string
    {
        if ($systemUnit === 'piece') {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' cái';
        }
        if ($systemUnit === 'g' && abs($value) >= 1000) {
            return number_format($value / 1000, 2, ',', '.') . ' kg';
        }
        if ($systemUnit === 'ml' && abs($value) >= 1000) {
            return number_format($value / 1000, 2, ',', '.') . ' l';
        }
        return number_format($value, 0, ',', '.') . ' ' . $systemUnit;
    }
}
