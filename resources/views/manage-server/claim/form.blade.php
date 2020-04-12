<form method="POST" action="{{ route('server:claim', $server) }}">
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
                {{ __('Claim Server') }}
            </button>
        </div>
    </div>
</form>
