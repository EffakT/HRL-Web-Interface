@extends('layouts.app')

@section('pageTitle', $player->name)

@section('head')
    <script src='https://www.google.com/recaptcha/api.js'></script>
@endsection

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1>{{$player->name}}</h1>

                @include('flash::message')
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">{{ __('Claim Player') }}</div>
                    <div class="card-body">
                        @if (!$player->isClaimed())
                            @if (!$player->isPendingClaimBy($user) && !$player->isClaimedBy($user))
                                @include('manage-player/claim/form')
                            @else
                                @include('manage-player/claim/howto')
                            @endif
                        @else
                            @if ($player->isClaimedBy($user))
                                You have already claimed this player
                            @else
                                This player has been claimed by {{$player->isClaimed()->user->name}}
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
