<div class="mb-3">
    <label class="form-label fw-semibold">Form Name <span class="text-danger">*</span></label>
    <input type="text" id="title" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $form->name ?? '') }}" required placeholder="e.g. Contact Form">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold" for="slug">URL Slug <span class="text-danger">*</span></label>
    <div class="input-group">
        <input type="text" id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror"
               value="{{ old('slug', $form->slug ?? '') }}" required placeholder="e.g. contact-form">
        <button class="btn btn-outline-secondary" type="button" id="slug-reset" title="Sync slug from title">↺ Sync</button>
    </div>
    @error('slug')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    <div class="form-text">Preview: <span id="slug-preview">{{ url('/forms') }}/{{ old('slug', $form->slug ?? '') }}</span></div>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Description</label>
    <textarea name="description" class="form-control @error('description') is-invalid @enderror"
              rows="3" placeholder="Optional form description">{{ old('description', $form->description ?? '') }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@push('scripts')
<script>
    (function () {
        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');
        const slugResetButton = document.getElementById('slug-reset');
        const slugPreview = document.getElementById('slug-preview');

        if (!titleInput || !slugInput || !slugResetButton) {
            return;
        }

        function slugify(text) {
            return text
                .toString()
                .toLowerCase()
                .trim()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-')
                .replace(/^-+/, '')
                .replace(/-+$/, '');
        }

        function updatePreview() {
            if (slugPreview) {
                slugPreview.textContent = `{{ url('/forms') }}/${slugInput.value}`;
            }
        }

        let slugManuallyEdited = slugInput.value !== slugify(titleInput.value);

        titleInput.addEventListener('input', function () {
            if (!slugManuallyEdited) {
                slugInput.value = slugify(this.value);
                updatePreview();
            }
        });

        slugInput.addEventListener('input', function () {
            slugManuallyEdited = true;
            updatePreview();
        });

        slugResetButton.addEventListener('click', function () {
            slugManuallyEdited = false;
            slugInput.value = slugify(titleInput.value);
            updatePreview();
        });

        updatePreview();
    })();
</script>
@endpush

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

<hr class="my-4">
<h6 class="fw-semibold mb-3"><i class="bi bi-sliders"></i> Submission Limits</h6>

<div class="mb-3">
    <label class="form-label fw-semibold" for="max_submissions">Max Submissions</label>
    <input type="number" id="max_submissions" name="max_submissions" min="1"
           class="form-control @error('max_submissions') is-invalid @enderror"
           value="{{ old('max_submissions', $form->max_submissions ?? '') }}"
           placeholder="Leave empty for no limit">
    <div class="form-text">Positive integer. Leave empty to allow unlimited submissions.</div>
    @error('max_submissions')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold" for="submission_start_at">Submissions Open At</label>
        <input type="datetime-local" id="submission_start_at" name="submission_start_at"
               class="form-control @error('submission_start_at') is-invalid @enderror"
               value="{{ old('submission_start_at', isset($form) && $form->submission_start_at ? $form->submission_start_at->format('Y-m-d\TH:i') : '') }}">
        <div class="form-text">Leave empty to allow submissions immediately.</div>
        @error('submission_start_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold" for="submission_end_at">Submissions Close At</label>
        <input type="datetime-local" id="submission_end_at" name="submission_end_at"
               class="form-control @error('submission_end_at') is-invalid @enderror"
               value="{{ old('submission_end_at', isset($form) && $form->submission_end_at ? $form->submission_end_at->format('Y-m-d\TH:i') : '') }}">
        <div class="form-text">Leave empty to keep accepting submissions indefinitely.</div>
        @error('submission_end_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
