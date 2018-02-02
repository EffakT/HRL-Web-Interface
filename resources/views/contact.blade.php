<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Contact - Halo Race Leaderboard</title>

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
            <h1>Contact</h1>
            <p>Feel free to contact me with any questions or if you need assistance.</p>
            <h2>Ways to contact me</h2>
            <p>You can contact me by filling out the form on my <a href="http://www.effakt.info/contact" target="_blank">portfolio</a>.
            <br>Or you can contact me on steam <a href="http://steamcommunity.com/id/effakt" target="_blank">EffakT</a>.</p>
          </div>
        </div>
        <!-- /.row -->

      </div>
      <!-- /.container -->



      <script src="{{ URL::asset('js/app.js') }}"></script>
    </body>
</html>
