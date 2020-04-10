<form method="POST" action="{{ route('server:migrate-laps', $server) }}" data-form-modal novalidate class="needs-validation">
    @csrf

    <div class="form-group">
        <label for="to-server">{{__('Migrate to server')}}</label>
        <select name="to-server" id="to-server"
                class="form-control {{ ($errors->has('to-server')) ? "is-invalid" : "" }}" required>
            <option value="" disabled selected>
                Select a server
            </option>
            @foreach ($servers AS $this_server)
                @if ($this_server->id == $server->id) @continue @endif
                <option value="{{$this_server->id}}">
                    {{$this_server->name}} ({{$this_server->ip}}:{{$this_server->port}})
                </option>
            @endforeach
        </select>
        @if ($errors->has('to-server'))
            <span class="invalid-feedback" style="display: block;">
                <strong>{{ $errors->first('to-server') }}</strong>
            </span>
        @endif
    </div>

    <div class="form-group">
        <div class="g-recaptcha" data-sitekey="{{ config('captcha.site') }}"></div>
        @if ($errors->has('g-recaptcha-response'))
            <span class="invalid-feedback" style="display: block;">
                    <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                </span>
        @endif
    </div>

    <div class="form-group">
        <button type="submit" class="btn btn-primary">
            {{ __('Migrate Lap Times') }}
        </button>
    </div>

    <p class="text-muted small">
        <b>In order to migrate the lap times from this server to another:</b><br>
        You must claim both servers.<br><br>

        <b>After migrating lap times:</b><br>
        This server's lap times will be left as they are <br>
        All of this server's lap times will be copies to the selected server. <br>
        The selected server's lap times will NOT be overwritten.
    </p>


    @php $body = "Do you really want to migrate all lap times? This process cannot be undone." @endphp
    @include('manage-server.partials.confirm-modal')
</form>
