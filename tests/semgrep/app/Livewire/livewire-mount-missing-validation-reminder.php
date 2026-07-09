<?php

namespace App\Livewire\Fixtures;

class ExampleComponent
{
    // ruleid: livewire-mount-missing-validation-reminder
    public function mount($playerId)
    {
        $this->playerId = $playerId;
    }

    // ok: livewire-mount-missing-validation-reminder
    public function mount()
    {
        //
    }
}
