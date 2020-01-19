@extends('layouts.app')

@section('pageTitle', 'Servers Leaderboard')

@section('content')
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                <h1>Servers</h1>
                <servers route="{{ route('server', "server_id") }}"
                ajax="{{ route('servers')  }}"></servers>

            </div>
        </div>
        <!-- /.row -->

    </div>
    <!-- /.container -->

@endsection
