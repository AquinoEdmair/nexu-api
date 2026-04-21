<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TicketCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly User          $user,
    ) {}
}
