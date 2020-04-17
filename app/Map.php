<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use JamesDordoy\LaravelVueDatatable\Traits\LaravelVueDatatableTrait;

class Map extends Model
{
    use LaravelVueDatatableTrait;
    protected $table = 'maps';
    protected $fillable = ['name', 'label'];


    protected $dataTableColumns = [
        'id' => [
            'searchable' => true,
        ],
        'label' => [
            'searchable' => true,
        ]
    ];

}
