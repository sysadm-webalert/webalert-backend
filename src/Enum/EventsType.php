<?php

namespace App\Enum;

enum EventsType: string
{
    case USER_JOINED = "user_joined";
    case USER_DELETED = "user_deleted";
    case SITE_CREATED = "site_created";
    case SITE_UPDATED = "site_updated";
    case SITE_DELETED = "site_deleted";
}
