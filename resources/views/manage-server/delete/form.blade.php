<form method="POST" action="{{ route('server:delete', $server) }}" data-form-modal>
    @csrf

    <div class="form-group row">
        <div class="col-md-12">
            <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_SITE_KEY') }}"></div>
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
                {{ __('Delete Server') }}
            </button>
        </div>
    </div>

    @php $body = "Do you really want to delete this server? This process cannot be undone." @endphp
    @include('manage-server.partials.confirm-modal')

</form>
