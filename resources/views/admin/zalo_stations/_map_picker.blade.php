{{-- Map picker — dùng chung cho create/edit. Yêu cầu _map_picker_js được include trong @section('script'). --}}
<div class="mb-3">
    <label class="form-label fw-semibold">Chọn vị trí trên bản đồ</label>
    <div class="d-flex gap-2 align-items-center mb-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-geocode-address">
            Tìm từ địa chỉ
        </button>
        <small class="text-muted">Hoặc click / kéo marker trên bản đồ</small>
    </div>
    <div id="station-map" style="height:320px; border-radius:6px; border:1px solid #dee2e6;"></div>
    <small class="form-text text-muted mt-1 d-block">Kéo marker hoặc click bản đồ để chọn vị trí chính xác</small>
</div>
