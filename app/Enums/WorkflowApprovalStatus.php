<?php

namespace App\Enums;

enum WorkflowApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ReturnedForRevision = 'returned_for_revision';
    case Skipped = 'skipped';
}
