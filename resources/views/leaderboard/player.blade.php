@extends('layouts.app')

@section('pageTitle', 'Leaderboard for '.$player->name)

@section('content')
    @php $user = \Illuminate\Support\Facades\Auth::user(); @endphp
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                @if (!$player->isClaimed())
                    <a href="{{route('player:manage', $player)}}" class="btn btn-primary mb-2">Claim Player</a>
                @elseif (!is_null($user) && $player->isClaimedBy($user))
                    <a href="{{route('player:manage', $player)}}" class="btn btn-primary mb-2">Manage Player</a>
                @endif


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
