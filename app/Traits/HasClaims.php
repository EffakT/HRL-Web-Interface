<?php


namespace App\Traits;


trait HasClaims
{
    public function allClaims()
    {
        return $this->hasMany('App\Claim');
    }

    public function pendingClaims()
    {
        return $this->hasMany('App\Claim')->whereNull('claimed_at');
    }

    public function claims()
    {
        return $this->hasMany('App\Claim')->whereNotNull('claimed_at');
    }
}
