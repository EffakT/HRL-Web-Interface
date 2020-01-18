@extends('layouts.app')

@section('content')
<div class="container">

    <div class="row">
        <div class="col-lg-12">
            <h1>About Halo Race Leaderboard</h1>
            <p>Halo Race Leaderboard is a fully public leaderboard that any Halo Server can opt-in to have track their times.</p>
            <p>For information on how to opt-in, click <a href="{!! route('opt-in') !!}">here</a></p>
            <h2>Future Plans</h2>
            <ul>
                <li>App converted to PWA (Progressive Web App - Planning Phase)</li>
                <li>Add users, with ability to claim servers & players</li>
                <li>Add ability for server owners to lap times</li>
                <li>Add ability for users to be notified if their record is broken</li>
            </ul>
            <h2>Known Issues</h2>
            <ul>
            </ul>
            <h2>Change Log</h2>
            <h3>3 Mar 2018</h3>
            <ul>
                <li>Adding ability to record lap times while not in a vehicle</li>
            </ul>
            <h3>25 Feb 2018</h3>
            <ul>
                <li>Resolved issue causing server crash on Lap report when player had special characters in name. E.G &copy;</li>
            </ul>
            <h2>Demo Server</h2>
            <h3>Due to internet issues, the demo server is not currently active.</h3>
            <img src="http://cache.gametracker.com/server_info/163.47.229.219:2302/b_560_95_1.png" border="0" class="img-responsive" alt="HRL Demo Server Information"/>
        </div>
    </div>
    <!-- /.row -->

</div>
<!-- /.container -->
@endsection
