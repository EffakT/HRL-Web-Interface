@extends('layouts.app')

@section('content')
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                <h1>Leaderboard for {{$server->name}} ({{$server->ip}}:{{$server->port}})</h1>

                <server route="{{ route('player', "player_id") }}"
                        ajax="{{ route('server', $server->id)  }}"
                        v-bind:maps="{{ json_encode($maps) }}"></server>
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
