<?php

declare(strict_types=1);

namespace CoreX\Tasks\Enums;

enum ProjectParticipantRole: string
{
    case Lead = 'lead';
    case Manager = 'manager';
    case Participant = 'participant';
    case Viewer = 'viewer';
}
