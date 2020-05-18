@php $claim = $player->isPendingClaimBy($user) @endphp
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
    To claim this player, please type the following in-game.<br>
    <code>/claimplayer {!! $claim->claim_code !!}</code>
</p>
<p>
    Once this message has been sent, the user should be automatically claimed within 15 minutes.<br>
</p>
