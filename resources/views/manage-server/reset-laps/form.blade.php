<form method="POST" action="{{ route('server:reset-laps', $server) }}" data-form-modal>
    @csrf

    <div class="form-group row">
        <div class="col-md-12">
            <div class="g-recaptcha" data-sitekey="{{ config('captcha.site') }}"></div>
            @if ($errors->has('g-recaptcha-response'))
                <span class="invalid-feedback" style="display: block;">
                    <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                </span>
            @endif
        </div>
    </div>

    <div class="form-group row mb-0">
        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">
                {{ __('Reset Lap Times') }}
            </button>
        </div>
    </div>

    @php $body = "Do you really want to reset all lap times? This process cannot be undone." @endphp
    @include('manage-server.partials.confirm-modal')

</form>
