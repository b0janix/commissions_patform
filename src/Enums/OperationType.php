<?php

namespace App\Enums;

enum OperationType: string
{
    case OPERATION_TYPE_WITHDRAW = 'withdraw';

    case OPERATION_TYPE_DEPOSIT = 'deposit';
}
