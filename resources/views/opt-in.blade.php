@extends('layouts.app')

@section('pageTitle', 'Opt In')


@section('content')
    <!-- Page Content -->
    <div class="container">

        <div class="row">
            <div class="col-lg-12">
                <h1>Opt In</h1>
                <p>In order to Opt-In to HRL, all you need to do is install a LUA script.</p>
                <h2>Requirements</h2>
                <ul>
                    <li>Halo Dedicated Server</li>
                    <li>Halo Server App - SAPP</li>
                </ul>
                <h2>Set Up</h2>
                <ul>
                    <li><a href="{{ URL::asset('js/hrl-files.zip') }}">Click here</a> to download the HRL files.</li>
                    <li>Copy the <strong>hrl_api.dll</strong> to your <strong>exe</strong> folder (where your <strong>haloded.exe</strong>
                        file is).
                    </li>
                    <li>Copy the <strong>lua folder</strong> to your <strong>exe</strong> folder.</li>
                    <li>Copy the <strong>hrl.lua</strong> to your <strong>lua</strong> folder (inside your sapp
                        configuration).
                    </li>
                    <li>If your Halo server is not running on port 2302, modify the <strong>hrl.lua</strong> and change
                        the <strong>server_port</strong> variable to the port you are using.
                    </li>
                    <li>You may need to update your init.txt file to contain <strong>lua 1</strong> if it does not
                        already contain this.
                    </li>
                    <li>Update your init.txt file to contain <strong>lua_load hrl</strong> after <strong>lua 1</strong>.
                    </li>
                    <li>If all is working correctly, up to 15 minutes after the first lap is complete, you should see
                        your Server in the Web App.
                    </li>
                </ul>
                <h2>Notes</h2>
                <p>Your server will not appear on this app instantly. Please give up to 15 minutes after first lap is
                    recorded.</p>
                <p>If your server is still not displaying 15 minutes after the first lap is recorded, please <a
                        href="#">contact me</a></p>
            </div>
        </div>
        <!-- /.row -->

    </div>
    <!-- /.container -->
@endsection
