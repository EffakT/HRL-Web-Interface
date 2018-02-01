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
            <p>Coming Soon...</p>
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
