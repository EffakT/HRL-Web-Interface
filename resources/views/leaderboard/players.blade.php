@extends('layouts.app')

@section('pageTitle', 'Players Leaderboard')


@section('content')
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                <h1>Players</h1>
                <players route="{{ route('player', "player_id") }}"
                      ajax="{{ route('players')  }}"></players>

            </div>
        </div>
        <!-- /.row -->

    </div>
    <!-- /.container -->

@endsection
