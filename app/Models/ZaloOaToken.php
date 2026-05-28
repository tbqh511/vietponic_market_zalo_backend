<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row store cho OA access_token + refresh_token.
 *
 * Dùng ZaloOaToken::current() để lấy row duy nhất (tạo rỗng nếu chưa có).
 */
class ZaloOaToken extends Model
{
    protected $table = 'zalo_oa_tokens';

    protected $fillable = [
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->oldest('id')->firstOrCreate([]);
    }
}
