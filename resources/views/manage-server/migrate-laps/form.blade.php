<form method="POST" action="{{ route('server:reset-laps', $server) }}" data-form-modal>
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

    <div class="form-group row">
        <div class="col-md-12">
            <button type="submit" class="btn btn-primary">
                {{ __('Migrate Lap Times') }}
            </button>
        </div>
    </div>

    <p class="text-muted small">
        <b>In order to migrate the lap times from this server to another:</b><br>
        You must claim both servers.<br><br>

        <b>After migrating lap times:</b><br>
        This server's lap times will be left as they are <br>
        All of this server's lap times will be copies to the selected server. <br>
        The selected server's lap times will NOT be overwritten.
    </p>

    @php $body = "Do you really want to reset all lap times? This process cannot be undone." @endphp
    @include('manage-server.partials.confirm-modal')

</form>
