@section('head')
    <script src='https://www.google.com/recaptcha/api.js'></script>
@endsection

<div class="form-group">
    <label for="api_token">API Token</label>
    <input type="text" name="api_token" class="form-control" readonly value="{{$user->api_token}}">
</div>
@if ($user->api_token)

@else
    <form method="POST" action="{{ route('generate-token') }}">
        @csrf
        <div class="form-group">
            <div class="g-recaptcha" data-sitekey="{{ config('captcha.site') }}"></div>
            @if ($errors->has('g-recaptcha-response'))
                <span class="invalid-feedback" style="display: block;">
                                                <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                                            </span>
            @endif
        </div>
        <div class="form-group mb-0">
            <button type="submit" class="btn btn-primary">
                {{ __('Generate Token') }}
            </button>
        </div>
    </form>
@endif
