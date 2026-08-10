<div class="mb-3">
    <label for="name" class="form-label">Name</label>
    <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $systemUser->name ?? '') }}" required maxlength="255">
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $systemUser->email ?? '') }}" required maxlength="255">
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label for="password" class="form-label">{{ isset($systemUser) ? 'New Password' : 'Password' }}</label>
        <input type="password" class="form-control" id="password" name="password" {{ isset($systemUser) ? '' : 'required' }}>
        @if (isset($systemUser))
            <div class="form-text">Leave blank to keep existing password.</div>
        @endif
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" {{ isset($systemUser) ? '' : 'required' }}>
    </div>
</div>
