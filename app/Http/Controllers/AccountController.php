<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateTokenRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = Auth::user();
        return view('my-account.home', compact('user'));
    }

    public function generateToken(GenerateTokenRequest $request) {
        $token = Str::random(60);

        $request->user()->forceFill([
            'api_token' => hash('sha256', $token),
        ])->save();


        flash('API Token has been successfully generated')->success();
        return redirect(route('my-account'));
    }
}
