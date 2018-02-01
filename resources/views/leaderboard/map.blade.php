<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{!! $map->label !!} - Halo Race Leaderboard</title>

        <link rel="stylesheet" href="//cdn.datatables.net/1.10.16/css/dataTables.bootstrap4.min.css">
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
            <h1>Leaderboard for {{$map->label}}</h1>
            <table id="map-table" class="table table-hover">
              <thead>
                <tr>
                  <th>Player</th>
                  <th>Server</th>
                  <th>IP</th>
                  <th>Port</th>
                  <th>Time</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
        <!-- /.row -->

      </div>
      <!-- /.container -->



      <script src="{{ URL::asset('js/app.js') }}"></script>
      <script src="//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
      <script src="//cdn.datatables.net/1.10.16/js/dataTables.bootstrap4.min.js"></script>
      <script>
      $(function() {
          var table = $('#map-table').DataTable({
              mapSide: true,
              processing: true,
              ajax: '{!! route('map', $map->id) !!}',
              columns: [
                {data: 'name', name: 'players.name'},
                {data: 'server_name', name: 'servers.name'},
                {data: 'ip', name: 'servers.ip'},
                {data: 'port', name: 'servers.port'},
                {data: 'time'}
              ],
              "autoWidth": true,
              "order": [[ 3, "asc" ]]
          });
      });
      </script>

    </body>
</html>
