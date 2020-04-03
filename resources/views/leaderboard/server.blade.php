@extends('layouts.app')

@section('pageTitle', 'Leaderboard for '.$server->name)

@section('content')
    @php $user = \Illuminate\Support\Facades\Auth::user(); @endphp
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                @if (!$server->isClaimed())
                    <a href="{{route('server:manage', $server)}}" class="btn btn-primary mb-2">Claim Server</a>
                @elseif (!is_null($user) && $server->isClaimedBy($user))
                    <a href="{{route('server:manage', $server)}}" class="btn btn-primary mb-2">Manage Server</a>
                @endif


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
