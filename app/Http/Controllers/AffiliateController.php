<?php

namespace App\Http\Controllers;

use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\ZaloOrder;
use App\Support\AffiliateCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AffiliateController extends Controller
{
    private function ensureEnabled(): ?\Illuminate\Http\JsonResponse
    {
        $row = Setting::where('type', 'affiliate_enabled')->first();
        if (!$row || (string) $row->data !== '1') {
            return response()->json(['error' => true, 'message' => 'Affiliate disabled'], 404);
        }
        return null;
    }

    private function autoApprove(): bool
    {
        $row = Setting::where('type', 'affiliate_auto_approve')->first();
        return $row && (string) $row->data === '1';
    }

    private function referralBaseUrl(): string
    {
        $miniAppId = config('services.zalo.mini_app_id');
        if ($miniAppId) {
            return 'https://zalo.me/s/' . $miniAppId . '/';
        }
        $row = Setting::where('type', 'affiliate_referral_base_url')->first();
        return $row->data ?? 'https://zalo.me/s/2984181565024919663/';
    }

    public function register(Request $request)
    {
        if ($r = $this->ensureEnabled()) return $r;

        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        if ($customer->affiliate_code) {
            return response()->json([
                'error' => false,
                'message' => 'Đã đăng ký cộng tác viên',
                'data' => $this->profilePayload($customer),
            ]);
        }

        $code = AffiliateCodeGenerator::generate();
        $autoApprove = $this->autoApprove();

        $customer->affiliate_code = $code;
        $customer->affiliate_status = $autoApprove ? 'approved' : 'pending';
        $customer->affiliate_approved_at = $autoApprove ? Carbon::now() : null;
        $customer->save();

        return response()->json([
            'error' => false,
            'message' => $autoApprove ? 'Đăng ký thành công' : 'Đăng ký thành công, đang chờ duyệt',
            'data' => $this->profilePayload($customer->fresh()),
        ]);
    }

    public function me(Request $request)
    {
        if ($r = $this->ensureEnabled()) return $r;
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');
        return response()->json([
            'error' => false,
            'data' => $this->profilePayload($customer),
        ]);
    }

    private function profilePayload(Customer $customer): array
    {
        $stats = [
            'pending' => 0,
            'confirmed' => 0,
            'paid' => 0,
            'cancelled' => 0,
        ];

        if ($customer->affiliate_code) {
            $rows = AffiliateCommission::where('referrer_customer_id', $customer->id)
                ->selectRaw('status, COALESCE(SUM(commission_amount),0) as total')
                ->groupBy('status')
                ->get();
            foreach ($rows as $row) {
                $stats[$row->status] = (int) $row->total;
            }
        }

        $referralsCount = $customer->affiliate_code
            ? Customer::where('referred_by_customer_id', $customer->id)->count()
            : 0;

        $shareUrl = null;
        if ($customer->affiliate_code) {
            $shareUrl = rtrim($this->referralBaseUrl(), '/') . '/?ref=' . $customer->affiliate_code;
        }

        return [
            'is_registered' => (bool) $customer->affiliate_code,
            'affiliate_code' => $customer->affiliate_code,
            'affiliate_status' => $customer->affiliate_status,
            'approved_at' => $customer->affiliate_approved_at,
            'share_url' => $shareUrl,
            'name' => $customer->name,
            'mobile' => $customer->mobile,
            'bank_name' => $customer->affiliate_bank_name,
            'bank_account' => $customer->affiliate_bank_account,
            'bank_holder' => $customer->affiliate_bank_holder,
            'commission_rate' => (float) (Setting::where('type', 'affiliate_commission_rate')->value('data') ?? 5),
            'referrals_count' => $referralsCount,
            'commission_stats' => $stats,
            'balance' => $stats['confirmed'],
            'locked' => $customer->affiliate_status === 'approved',
        ];
    }

    public function updateBank(Request $request)
    {
        if ($r = $this->ensureEnabled()) return $r;
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $data = $request->validate([
            'bank_name' => 'nullable|string|max:120',
            'bank_account' => 'nullable|string|max:60',
            'bank_holder' => 'nullable|string|max:120',
        ]);

        $customer->affiliate_bank_name = $data['bank_name'] ?? $customer->affiliate_bank_name;
        $customer->affiliate_bank_account = $data['bank_account'] ?? $customer->affiliate_bank_account;
        $customer->affiliate_bank_holder = $data['bank_holder'] ?? $customer->affiliate_bank_holder;
        $customer->save();

        return response()->json([
            'error' => false,
            'data' => $this->profilePayload($customer->fresh()),
        ]);
    }

    public function commissions(Request $request)
    {
        if ($r = $this->ensureEnabled()) return $r;
        $customerId = (int) $request->attributes->get('zalo_customer_id');

        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));

        $paginator = AffiliateCommission::where('referrer_customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'error' => false,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function applyReferral(Request $request)
    {
        if ($r = $this->ensureEnabled()) return $r;
        /** @var Customer $customer */
        $customer = $request->attributes->get('zalo_customer');

        $data = $request->validate([
            'ref_code' => 'required|string|max:32',
        ]);
        $refCode = strtoupper(trim($data['ref_code']));

        if ($customer->referred_by_customer_id) {
            return response()->json([
                'error' => true,
                'message' => 'Bạn đã được giới thiệu trước đó',
            ], 409);
        }

        if (ZaloOrder::where('customer_id', $customer->id)->exists()) {
            return response()->json([
                'error' => true,
                'message' => 'Không thể áp mã giới thiệu vì đã có đơn hàng',
            ], 409);
        }

        if ($customer->affiliate_code && strtoupper($customer->affiliate_code) === $refCode) {
            return response()->json([
                'error' => true,
                'message' => 'Không thể tự giới thiệu chính mình',
            ], 422);
        }

        $referrer = Customer::where('affiliate_code', $refCode)
            ->where('affiliate_status', 'approved')
            ->first();
        if (!$referrer) {
            return response()->json([
                'error' => true,
                'message' => 'Mã giới thiệu không hợp lệ hoặc chưa được duyệt',
            ], 404);
        }

        if ((int) $referrer->id === (int) $customer->id) {
            return response()->json([
                'error' => true,
                'message' => 'Không thể tự giới thiệu chính mình',
            ], 422);
        }

        $customer->referred_by_customer_id = $referrer->id;
        $customer->save();

        return response()->json([
            'error' => false,
            'message' => 'Áp mã giới thiệu thành công',
        ]);
    }
}
