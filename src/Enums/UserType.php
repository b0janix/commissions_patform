<?php

namespace App\Enums;

enum UserType: string
{
    case USER_TYPE_PRIVATE = 'private';

    case USER_TYPE_BUSINESS = 'business';
}
