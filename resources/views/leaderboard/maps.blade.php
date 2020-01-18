@extends('layouts.app')

@section('content')
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                <h1>Maps</h1>
                <maps route="{{ route('map', "server_id") }}"
                         ajax="{{ route('maps')  }}"></maps>

            </div>
        </div>
        <!-- /.row -->

    </div>
    <!-- /.container -->

@endsection
