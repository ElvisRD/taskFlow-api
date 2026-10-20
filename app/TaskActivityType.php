<?php

namespace App;

enum TaskActivityType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Moved = 'moved';
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';
    case Completed = 'completed';
    case Reopened = 'reopened';
    case CommentAdded = 'comment_added';
}
