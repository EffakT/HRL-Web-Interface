<?php

declare(strict_types=1);

namespace App\Livewire\Fixtures;

class ExampleComponent
{
    public $playerId;

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
