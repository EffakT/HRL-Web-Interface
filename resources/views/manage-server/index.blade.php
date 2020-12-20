@extends('layouts.app')

@section('pageTitle', $server->name)

@section('head')
    <script src='https://www.google.com/recaptcha/api.js'></script>
@endsection

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1>{{$server->name}}</h1>
                <h2>({{$server->ip}}:{{$server->port}})</h2>

                @include('flash::message')
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">{{ __('Claim Server') }}</div>
                    <div class="card-body">
                        @if (!$server->isClaimed())
                            @if (!$server->isPendingClaimBy($user) && !$server->isClaimedBy($user))
                                @include('manage-server/claim/form')
                            @else
                                @include('manage-server/claim/howto')
                            @endif
                        @else
                            @if ($server->isClaimedBy($user))
                                You have already claimed this server
                            @else
                                This server has been claimed by {{$server->isClaimed()->user->name}}
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($server->isClaimed())
            @if ($server->isClaimedBy($user))
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card mt-3">
                            <div class="card-header">{{ __('Notify of server outage') }}</div>
                            <div class="card-body">
                                @include('manage-server/notify-outage/form')
                            </div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-header">{{ __('Reset all lap times') }}</div>
                            <div class="card-body">
                                @include('manage-server/reset-laps/form')
                            </div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-header">{{ __('Delete Server') }}</div>
                            <div class="card-body">
                                @include('manage-server/delete/form')
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mt-3">
                            <div class="card-header">{{ __('Migrate lap times') }}</div>
                            <div class="card-body">
                                @include('manage-server/migrate-laps/form')
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection
