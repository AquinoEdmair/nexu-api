<?php

declare(strict_types=1);

namespace App\Filament\Resources\TeamMemberResource\Pages;

use App\Filament\Resources\TeamMemberResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTeamMember extends CreateRecord
{
    protected static string $resource = TeamMemberResource::class;
}
