@extends('layouts.app')

@section('pageTitle', 'Leaderboard for '.$player->name)

@section('content')
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                <h1>Leaderboard for {{$player->name}}</h1>

                <singleplayer ajax="{{ route('player', $player->id)  }}"></singleplayer>
            </div>
        </div>
        <!-- /.row -->

    </div>
    <!-- /.container -->

@endsection

@section('scripts')
    <script>
    </script>
@endsection
