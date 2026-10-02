<?php

namespace App\Events;

use App\Models\GroupMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChitApplicationEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public GroupMember $member,
        public string $source = 'admin'
    ) {}
}
