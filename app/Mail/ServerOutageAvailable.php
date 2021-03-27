<?php

namespace App\Mail;

use App\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServerOutageAvailable extends Mailable
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
            ->subject('HRL - Server is reachable')
            ->view('emails.server.available');
    }
}
