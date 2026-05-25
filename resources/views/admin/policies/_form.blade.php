@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-3">
    <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
    <input type="text" name="title" class="form-control" list="policy-title-suggestions"
           value="{{ old('title', $policy->title) }}" required>
    <datalist id="policy-title-suggestions">
        @foreach($suggested as $s)
            <option value="{{ $s }}"></option>
        @endforeach
    </datalist>
</div>

<div class="mb-3">
    <label class="form-label">Slug</label>
    <input type="text" name="slug" class="form-control" list="policy-slug-suggestions"
           value="{{ old('slug', $policy->slug) }}" placeholder="Để trống sẽ tự sinh từ tiêu đề">
    <datalist id="policy-slug-suggestions">
        @foreach($suggested as $slug => $label)
            <option value="{{ $slug }}">{{ $label }}</option>
        @endforeach
    </datalist>
    <small class="form-text text-muted">Khoá cố định để mini-app gọi tới (chữ thường, số, gạch ngang).</small>
</div>

<div class="mb-3">
    <label class="form-label">Nội dung</label>
    <textarea id="tinymce_editor" name="content" class="form-control" rows="12">{{ old('content', $policy->content) }}</textarea>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Thứ tự hiển thị</label>
        <input type="number" name="sort_order" class="form-control" min="0"
               value="{{ old('sort_order', $policy->sort_order) }}">
    </div>
    <div class="col-md-4 mb-3 d-flex align-items-center">
        <div class="form-check mt-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                   {{ old('is_active', $policy->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Hiển thị trên mini-app</label>
        </div>
    </div>
</div>
