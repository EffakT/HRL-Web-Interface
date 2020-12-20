<?php

namespace App\Mail;

use App\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServerOutage extends Mailable
{
    use Queueable, SerializesModels;

    public $server;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('webmaster@damiansmall.dev')
            ->subject('HRL - Unable to reach server')
            ->view('emails.server.outage');
    }
}
