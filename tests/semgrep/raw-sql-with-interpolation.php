<?php

// ruleid: raw-sql-with-interpolation
DB::select("select * from users where id = $id");

// ruleid: raw-sql-with-interpolation
DB::statement("update users set name = '$name' where id = 1");

// ok: raw-sql-with-interpolation
DB::select('select * from users where id = ?', [$id]);

// ok: raw-sql-with-interpolation
DB::table('users')->where('id', $id)->first();
