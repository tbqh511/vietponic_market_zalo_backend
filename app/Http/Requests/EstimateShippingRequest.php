<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstimateShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // JWT auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'required|integer|exists:zalo_products,id',
            'items.*.quantity'            => 'required|integer|min:1',
            'receiver_province_id'        => 'required|integer|exists:vtp_provinces,id',
            'receiver_district_id'        => 'nullable|integer',
            'receiver_ward_id'            => 'required|integer|exists:vtp_wards,id',
            'receiver_lat'                => 'nullable|numeric|between:-90,90',
            'receiver_lng'                => 'nullable|numeric|between:-180,180',
            'product_price'               => 'required|integer|min:0',
            'is_cod'                      => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                    => 'Giỏ hàng không được rỗng.',
            'items.*.product_id.exists'         => 'Sản phẩm không tồn tại.',
            'items.*.quantity.min'              => 'Số lượng phải ít nhất là 1.',
            'receiver_province_id.required'     => 'Vui lòng chọn tỉnh/thành.',
            'receiver_province_id.exists'       => 'Tỉnh/thành không hợp lệ.',
            'receiver_district_id.integer'      => 'Quận/huyện không hợp lệ.',
            'receiver_ward_id.required'         => 'Vui lòng chọn phường/xã.',
            'receiver_ward_id.exists'           => 'Phường/xã không hợp lệ.',
        ];
    }
}
