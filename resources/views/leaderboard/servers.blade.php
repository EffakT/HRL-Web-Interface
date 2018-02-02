<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Servers - Halo Race Leaderboard</title>

        <link rel="stylesheet" href="//cdn.datatables.net/1.10.16/css/dataTables.bootstrap4.min.css">
        <link rel="stylesheet" type="text/css" href="{{ URL::asset('css/app.css') }}"/>
        <style>
          .table tr {
            cursor: pointer;
          }
        </style>

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
            <h1>Servers</h1>
            <table id="servers-table" class="table table-hover">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Ip</th>
                  <th>Port</th>
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
          var table = $('#servers-table').DataTable({
              serverSide: true,
              processing: true,
              ajax: '{!! route('servers') !!}',
              columns: [
                  {data: 'name'},
                  {data: 'ip'},
                  {data: 'port'},
              ],
              "autoWidth": true
          });

          var server_url = "{!! route('server', "server_id") !!}";
          $('.table').on('click', 'tbody tr', function() {
            var url = server_url.replace('server_id', table.row(this).data().id);
            window.location.href = url;
          })
      });
      </script>

    </body>
</html>
