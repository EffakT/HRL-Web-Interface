@php $claim = $server->isPendingClaimBy($user) @endphp
<h3>How To Claim</h3>
<p>
    @php $expiryHours = \Carbon\Carbon::now()->diffInHours($claim->created_at->addHours(24)) @endphp
    @php $expiryMinutes = \Carbon\Carbon::now()->diffInMinutes($claim->created_at->addHours(24)) @endphp
    Please Note, Unverified claims will expire after 24 hours of being created.<br>
    This claim will expire in
    @if ($expiryHours > 1)
        {{ $expiryHours }} Hours
    @else
        @if ($expiryHours > 0)
            {{ $expiryHours }} Hour
        @else
            @if ($expiryMinutes != 1)
                {{ $expiryMinutes }} Minutes
            @else
                {{ $expiryMinutes }} Minute
            @endif
        @endif
    @endif
</p>
<p>
    To claim this server, please add the below text to the beginning of the server name.<br>
    <code>{!! $claim->claim_code !!}</code>
</p>
<p>
    Once this code has been added to the server name, click the below button to verify ownership.<br>
    <a href="{{route('server:claim-verify', $server)}}" class="btn btn-primary">Verify Ownership</a>
</p>
<p>
    After the server is successfully claimed, you can change the server name back.
</p>
