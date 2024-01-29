<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\OperationType;
use App\Enums\UserType;

class FilterHelper
{
    public static function segregateUsers(array $csvArray): array
    {
        $users = [
            'private_withdraw' => [],
            'private_deposit' => [],
            'business_withdraw' => [],
            'business_deposit' => [],
        ];

        foreach ($csvArray as $record) {
            if (
                UserType::tryFrom($record['user_type'])
                && OperationType::tryFrom($record['operation_type'])
            ) {
                if (
                    $record['user_type'] === UserType::USER_TYPE_PRIVATE->value
                    && $record['operation_type'] === OperationType::OPERATION_TYPE_WITHDRAW->value
                ) {
                    $users['private_withdraw'][] = $record;
                } elseif (
                    $record['user_type'] === UserType::USER_TYPE_PRIVATE->value
                    && $record['operation_type'] === OperationType::OPERATION_TYPE_DEPOSIT->value
                ) {
                    $users['private_deposit'][] = $record;
                } elseif (
                    $record['user_type'] === UserType::USER_TYPE_BUSINESS->value
                    && $record['operation_type'] === OperationType::OPERATION_TYPE_WITHDRAW->value
                ) {
                    $users['business_withdraw'][] = $record;
                } else {
                    $users['business_deposit'][] = $record;
                }
            } else {
                LogHelper::logError('Invalid user type or operation type');
            }
        }
        return $users;
    }
}
