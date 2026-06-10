<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Hợp đồng API duy nhất cho lỗi "tài khoản bị vô hiệu hoá".
 *
 * Dùng chung ở mọi chốt chặn customer (ZaloJwtMiddleware, EnsureFarmPartner,
 * ZaloApiController::authenticate) để status/message/code KHÔNG bị lệch giữa
 * các nơi. Frontend nhận diện qua đúng cặp { status: 403, code: ACCOUNT_DISABLED }
 * (xem AccountDisabledError trong src/utils/request.ts) — đổi format ở đây phải
 * đổi đồng bộ bên frontend.
 */
trait InteractsWithAccountStatus
{
    protected function accountDisabledResponse(): JsonResponse
    {
        return response()->json([
            'error'   => true,
            'message' => 'Tài khoản đã bị vô hiệu hoá, vui lòng liên hệ admin',
            'code'    => 'ACCOUNT_DISABLED',
        ], 403);
    }
}
