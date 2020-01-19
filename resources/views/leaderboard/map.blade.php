@extends('layouts.app')

@section('pageTitle', 'Leaderboard for '.$map->label)

@section('content')
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                <h1>Leaderboard for {{$map->label}}</h1>

                <singlemap route="{{ route('player', "player_id") }}"
                           ajax="{{ route('map', $map->id)  }}"></singlemap>
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
