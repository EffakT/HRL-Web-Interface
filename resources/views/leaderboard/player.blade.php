@extends('layouts.app')

@section('pageTitle', 'Leaderboard for '.$player->name)

@section('content')
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                <h1>Leaderboard for {{$player->name}}</h1>
    
                <strong>Known Aliases:</strong>
                <ul class="list-inline list-unstyled">
                    @foreach ($player->alias() AS $alias)
                        <li class="list-inline-item">
                            <a href="{{route('player', $alias->id)}}">
                                {{$alias->name}}
                            </a>
                        </li>
                    @endforeach
                </ul>

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
