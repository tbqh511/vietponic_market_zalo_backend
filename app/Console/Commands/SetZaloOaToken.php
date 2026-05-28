<?php

namespace App\Console\Commands;

use App\Models\ZaloOaToken;
use Illuminate\Console\Command;

/**
 * zalo:oa-token — seed/inspect OA access_token + refresh_token.
 *
 * Token lần đầu lấy thủ công từ Zalo OA console (Settings → API). refresh_token
 * đổi sau MỖI lần refresh nên ZaloOaClient sẽ tự ghi đè — command này chỉ dùng để
 * seed lần đầu hoặc khi token bị mất.
 *
 *   php artisan zalo:oa-token --access=... --refresh=... [--expires=90000]
 *   php artisan zalo:oa-token                              # in trạng thái hiện tại
 */
class SetZaloOaToken extends Command
{
    protected $signature = 'zalo:oa-token
        {--access= : OA access_token}
        {--refresh= : OA refresh_token}
        {--expires=90000 : Số giây access_token còn sống (mặc định ~25h)}';

    protected $description = 'Seed hoặc xem trạng thái Zalo OA access_token / refresh_token';

    public function handle(): int
    {
        $access  = $this->option('access');
        $refresh = $this->option('refresh');

        if ($access === null && $refresh === null) {
            return $this->showStatus();
        }

        if ($access === null || $refresh === null) {
            $this->error('Cần cả --access và --refresh để seed token.');

            return self::FAILURE;
        }

        $token = ZaloOaToken::current();
        $token->fill([
            'access_token'  => $access,
            'refresh_token' => $refresh,
            'expires_at'    => now()->addSeconds((int) $this->option('expires')),
        ])->save();

        $this->info('Đã lưu OA token. Hết hạn lúc: ' . $token->expires_at->toDateTimeString());

        return self::SUCCESS;
    }

    private function showStatus(): int
    {
        $token = ZaloOaToken::query()->oldest('id')->first();

        if (!$token || !$token->access_token) {
            $this->warn('Chưa có OA token. Seed bằng --access=... --refresh=...');

            return self::SUCCESS;
        }

        $expired = $token->expires_at && $token->expires_at->isPast();
        $this->info('OA token đang lưu:');
        $this->line('  expires_at: ' . optional($token->expires_at)->toDateTimeString());
        $this->line('  trạng thái: ' . ($expired ? 'HẾT HẠN (sẽ tự refresh)' : 'còn hạn'));

        if ($token->expires_at && !$expired) {
            $this->line('  còn lại:    ' . now()->diffForHumans($token->expires_at, true));
        }

        return self::SUCCESS;
    }
}
