<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Opt-In - Halo Race Leaderboard</title>

        <link rel="stylesheet" type="text/css" href="{{ URL::asset('css/app.css') }}"/>

    </head>
    <body>

      <a href="https://github.com/EffakT/HRL-Web-Interface" target="_bank" class="github-corner">
        <svg width="60" height="60" viewBox="0 0 250 250" style="fill:#fff; color:#151513; position: absolute; top: 0; border: 0; right: 0; z-index:10000">
          <path d="M0,0 L115,115 L130,115 L142,142 L250,250 L250,0 Z"></path>
          <path d="M128.3,109.0 C113.8,99.7 119.0,89.6 119.0,89.6 C122.0,82.7 120.5,78.6 120.5,78.6 C119.2,72.0 123.4,76.3 123.4,76.3 C127.3,80.9 125.5,87.3 125.5,87.3 C122.9,97.6 130.6,101.9 134.4,103.2" fill="currentColor" style="transform-origin: 130px 106px;" class="octo-arm"></path>
          <path d="M115.0,115.0 C114.9,115.1 118.7,116.5 119.8,115.4 L133.7,101.6 C136.9,99.2 139.9,98.4 142.2,98.6 C133.8,88.0 127.5,74.4 143.8,58.0 C148.5,53.4 154.0,51.2 159.7,51.0 C160.3,49.4 163.2,43.6 171.4,40.1 C171.4,40.1 176.1,42.5 178.8,56.2 C183.1,58.6 187.2,61.8 190.9,65.4 C194.5,69.0 197.7,73.2 200.1,77.6 C213.8,80.2 216.3,84.9 216.3,84.9 C212.7,93.1 206.9,96.0 205.4,96.6 C205.1,102.4 203.0,107.8 198.3,112.5 C181.9,128.9 168.3,122.5 157.7,114.1 C157.9,116.9 156.7,120.9 152.7,124.9 L141.0,136.5 C139.8,137.7 141.6,141.9 141.8,141.8 Z" fill="currentColor" class="octo-body"></path>
        </svg>
      </a>

      <?php $mainNav = Menu::get('MyNavBar'); ?>

      <!-- Navigation -->
      <nav class="navbar navbar-inverse" role="navigation">
          <div class="container">
              <!-- Brand and toggle get grouped for better mobile display -->
              <div class="navbar-header">
                  <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#nav1">
                      <span class="sr-only">Toggle navigation</span>
                      <span class="icon-bar"></span>
                      <span class="icon-bar"></span>
                      <span class="icon-bar"></span>
                  </button>
                  <a class="navbar-brand" href="{{ URL::route('home') }}">HRL</a>
              </div>
              <!-- Collect the nav links, forms, and other content for toggling -->
              <div class="collapse navbar-collapse" id="nav1">
                  <ul class="nav navbar-nav">
                    @include(config('laravel-menu.views.bootstrap-items'), ['items' => $mainNav->roots()])

                  </ul>
              </div>
              <!-- /.navbar-collapse -->
          </div>
          <!-- /.container -->
      </nav>

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
              <li>Copy the <strong>hrl_api.dll</strong> to your <strong>exe</strong> folder (where your <strong>haloded.exe</strong> file is).</li>
              <li>Copy the <strong>lua folder</strong> to your <strong>exe</strong> folder.</li>
              <li>Copy the <strong>hrl.lua</strong> to your <strong>lua</strong> folder (inside your sapp configuration).</li>
              <li>If your Halo server is not running on port 2302, modify the <strong>hrl.lua</strong> and change the <strong>server_port</strong> variable to the port you are using.</li>
              <li>You may need to update your init.txt file to contain <strong>lua 1</strong> if it does not already contain this.</li>
              <li>Update your init.txt file to contain <strong>lua_load hrl</strong> after <strong>lua 1</strong>.</li>
            </ul>
            <h2>Notes</h2>
            <p>Your server will not appear on this app instantly. Please give up to 15 minutes after first lap is recorded.</p>
            <p>If your server is still not displaying 15 minutes after the first lap is recorded, please <a href="#">contact me</a></p>
          </div>
        </div>
        <!-- /.row -->

      </div>
      <!-- /.container -->



      <script src="{{ URL::asset('js/app.js') }}"></script>
    </body>
</html>
