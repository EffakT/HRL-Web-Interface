@extends('layouts.app')

@section('pageTitle', 'My Account')

@section('content')
    <div class="container">
        <div class="mb-3 row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Dashboard</div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <p>Welcome to your Halo Race Leaderboard account</p>
                        <p>Here you can manage your servers.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="my-3 row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">My Servers</div>

                    <div class="card-body">
                        @include('my-account/my-servers')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
