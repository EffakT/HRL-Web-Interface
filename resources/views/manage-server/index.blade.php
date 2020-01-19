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
                                This server has been claimed by {{$user->name}}
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
