<?php
namespace App\Http\Controllers;

class HomeController extends Controller {
    public function home() {
        return view('welcome');
    }

    public function optIn() {
        return view('opt-in');
    }

    public function help() {
        return view('help');
    }

    public function contact() {
        return view('contact');
    }
}
