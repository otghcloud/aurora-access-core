<div class="row g-3">
    <div class="col-md-6">
        <label for="user_id" class="form-label">Assigned User</label>
        <select class="form-select" id="user_id" name="user_id" required>
            <option value="">Select a user</option>
            @foreach ($accessUsers as $accessUserOption)
                <option value="{{ $accessUserOption->id }}" @selected((string) old('user_id', $accessCard->user_id ?? '') === (string) $accessUserOption->id)>
                    {{ $accessUserOption->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label for="card_number" class="form-label">Card Number</label>
        <input type="text" class="form-control" id="card_number" name="card_number" value="{{ old('card_number', $accessCard->card_number ?? ($initialCardNumber ?? '')) }}" required maxlength="255">
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea class="form-control" id="description" name="description" rows="3" maxlength="500">{{ old('description', $accessCard->description ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <label class="form-label d-block">Status</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="active" id="active_yes" value="1" @checked((string) old('active', isset($accessCard) ? (int) $accessCard->active : '1') === '1')>
            <label class="form-check-label" for="active_yes">Active</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="active" id="active_no" value="0" @checked((string) old('active', isset($accessCard) ? (int) $accessCard->active : '1') === '0')>
            <label class="form-check-label" for="active_no">Inactive</label>
        </div>
    </div>
</div>
