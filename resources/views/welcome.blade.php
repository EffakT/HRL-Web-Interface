@extends('layouts.app')

@section('pageTitle', 'About')

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
                <li>Add the ability to claim players</li>
                <li>Add ability for users to be notified if their record is broken</li>
                <li>Develop a Developer API for pulling data</li>
            </ul>
            <h2>Known Issues</h2>
            <ul>
            </ul>
            <h2>Change Log</h2>
            <h3>4 April 2020</h3>
            <ul>
                <li>Adding ability to create users</li>
                <li>Adding ability to claim, reset, migrate, and delete servers</li>
                <li>Adding Ping Spike detection</li>
            </ul>
            <h3>30 September 2018</h3>
            <ul>
                <li>
                    Added notes to Opt-In instructions regarding <b>gameservers.com</b>
                </li>
                <li>
                    Moved hash encryption to web server, removes dependencies for client
                </li>
                <li>
                    Added player-specific leaderboards
                </li>
            </ul>
            <h3>23 September 2018</h3>
            <ul>
                <li>
                    Fixed download link, now should work.
                </li>
            </ul>
            <h3>3 Mar 2018</h3>
            <ul>
                <li>Adding ability to record lap times while not in a vehicle</li>
            </ul>
            <h3>25 Feb 2018</h3>
            <ul>
                <li>Resolved issue causing server crash on Lap report when player had special characters in name. E.G &copy;</li>
            </ul>
            <h2>Demo Server</h2>
            <img src="https://cache.gametracker.com/server_info/163.47.230.216:2302/b_560_95_1.png" border="0" class="img-fluid" alt="HRL Demo Server Information"/>
        </div>
    </div>
    <!-- /.row -->

</div>
<!-- /.container -->
@endsection
