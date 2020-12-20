<form method="POST" action="{{ route('server:notify-outage', $server) }}">
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

    <div class="form-group row">
        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">
                {{ ($server->notify_outage) ? __('Disable Notify Outage') : __('Enable Notify Outage') }}
            </button>
        </div>
    </div>
    <p class="text-muted small">
        By enabling this, you will be receive an email if we are unable to access your server.<br>
        HRL will query every hour, and will notify you once per 24 hours.
    </p>

</form>
