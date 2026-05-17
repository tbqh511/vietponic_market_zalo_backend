{{-- Fieldset cấu hình VTP cho trạm lấy hàng. Dùng chung cho create/edit. --}}
<fieldset class="mt-4">
    <legend class="h6">Cấu hình ViettelPost (kho lấy hàng)</legend>
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Tỉnh/Thành VTP</label>
                <select name="vtp_province_id" id="vtp_province_id" class="form-select"
                        data-current="{{ old('vtp_province_id', $station->vtp_province_id ?? '') }}">
                    <option value="">— Chọn —</option>
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Quận/Huyện VTP</label>
                <select name="vtp_district_id" id="vtp_district_id" class="form-select"
                        data-current="{{ old('vtp_district_id', $station->vtp_district_id ?? '') }}">
                    <option value="">— Chọn —</option>
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label class="form-label">Phường/Xã VTP</label>
                <select name="vtp_ward_id" id="vtp_ward_id" class="form-select"
                        data-current="{{ old('vtp_ward_id', $station->vtp_ward_id ?? '') }}">
                    <option value="">— Chọn —</option>
                </select>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Địa chỉ chi tiết (số nhà, đường) — gửi cho VTP</label>
        <input type="text" name="vtp_address" class="form-control"
               value="{{ old('vtp_address', $station->vtp_address ?? '') }}" maxlength="500">
        <small class="form-text text-muted">
            Để trống nếu trạm chưa cấu hình VTP. Phải nhập đủ Tỉnh + Quận/Huyện để được dùng làm trạm lấy hàng.
        </small>
    </div>
</fieldset>
