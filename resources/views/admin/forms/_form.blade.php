<div class="mb-3">
    <label class="form-label fw-semibold">Form Name <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $form->name ?? '') }}" required placeholder="e.g. Contact Form">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Description</label>
    <textarea name="description" class="form-control @error('description') is-invalid @enderror"
              rows="3" placeholder="Optional form description">{{ old('description', $form->description ?? '') }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Success Message</label>
    <textarea name="success_message" class="form-control" rows="2"
              placeholder="Message shown after successful submission">{{ old('success_message', $form->success_message ?? 'Thank you for your submission!') }}</textarea>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Form Header Image</label>
    @if(isset($form) && $form->header_image)
        <div class="mb-2">
            <img src="{{ Storage::url($form->header_image) }}" alt="Current header image"
                 class="img-fluid rounded" style="max-height: 200px; max-width: 100%; object-fit: cover;">
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_header_image" id="remove_header_image" value="1">
                <label class="form-check-label text-danger" for="remove_header_image">Remove current header image</label>
            </div>
        </div>
    @endif
    <input type="file" name="header_image" class="form-control @error('header_image') is-invalid @enderror"
           accept="image/jpeg,image/png,image/gif,image/webp">
    <div class="form-text">Accepted formats: JPG, PNG, GIF, WEBP. Max size: 2MB.</div>
    @error('header_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card bg-light border-0 p-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                    {{ old('is_active', ($form->is_active ?? true) ? '1' : '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="is_active">
                    <i class="bi bi-toggle-on"></i> Active
                </label>
                <div class="text-muted small">Enable this form for public access</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-light border-0 p-3">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="captcha_enabled" id="captcha_enabled" value="1"
                    {{ old('captcha_enabled', isset($form) && $form->captcha_enabled ? '1' : '0') == '1' ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold" for="captcha_enabled">
                    <i class="bi bi-shield-check"></i> CAPTCHA Protection
                </label>
            </div>
            <div>
                <label class="form-label small">Type</label>
                <select name="captcha_type" class="form-select form-select-sm">
                    <option value="math" {{ old('captcha_type', $form->captcha_type ?? 'math') == 'math' ? 'selected' : '' }}>Math Question</option>
                    <option value="honeypot" {{ old('captcha_type', $form->captcha_type ?? 'math') == 'honeypot' ? 'selected' : '' }}>Honeypot</option>
                </select>
            </div>
        </div>
    </div>
</div>
