<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\OperationCurrency;
use App\Enums\OperationType;
use App\Enums\UserType;

class CalculateCommissionHelper
{
    public const DEPOSIT_COMMISSION_RATE = 0.0003;
    public const WITHDRAWAL_PRIVATE_COMMISSION_RATE = 0.003;
    public const WITHDRAWAL_BUSINESS_COMMISSION_RATE = 0.005;
    public const LIMIT_AMOUNT = 1000;
    public const MAX_TRIES = 3;
    public function __construct(private readonly string $ratesUrl = '')
    {
    }

    public function rates()
    {
        if (!empty($this->ratesUrl)) {
            return HTTPHelper::getRates($this->ratesUrl)['rates'] ?? [];
        }
        return [];
    }


    public function calculatePrivateWithdrawCommissions(array $data): array
    {
        if (!$rates = $this->rates()) {
            LogHelper::logError('Please get the rates first');

            return [];
        }

        foreach ($data as $index => $record) {
            if (!$this->checkCurrency($record['operation_currency'])) {
                continue;
            }

            if (!$this->checkOperationType($record['operation_type'])) {
                continue;
            }

            if (!$this->checkUserType($record['user_type'])) {
                continue;
            }

            //so if the currency is not a euro
            if ($record['operation_currency'] !== OperationCurrency::CURRENCY_EUR->value) {
                //convert the amount into euros
                $amountInEuros = $this->convertToEuros(
                    $rates,
                    $record['operation_amount'],
                    $record['operation_currency']
                );

                //this part is for the first or the 0 element of the array
                if (!isset($data[$index - 1])) {
                    $data = $this->newWeekOC(
                        $amountInEuros,
                        $rates,
                        $data,
                        $index
                    );
                } else {
                    $previous = $data[$index - 1];

                    $diffFreq =  $record['frequency_by_week'] - $previous['frequency_by_week'];
                    //this means that if the difference is 1 then both attempts belong in the same week for the same user
                    if ($diffFreq === 1) {
                        //if the value is less or equal to three or the ma number of attempts per week
                        if ($record['frequency_by_week'] <= self::MAX_TRIES) {
                            //if the limit is already surpassed or greater than 0
                            //calculate the commission for the whole operation amount
                            //and increase the limit also for the whole amount
                            if ($previous['limit'] > 0) {
                                $amountInOC = $this->convertFromEuros(
                                    $rates,
                                    $amountInEuros,
                                    $record['operation_currency']
                                );
                                $data[$index]['commission'] = round($amountInOC*self::WITHDRAWAL_PRIVATE_COMMISSION_RATE, 2);
                                $data[$index]['limit'] = $previous['limit'] + $amountInOC;
                            } else {
                                //otherwise find the result off the sum it's diff because the limit is negative
                                $diff = $previous['limit'] + $amountInEuros;
                                //if the result is positive or greater than 0 calculate the commission for that value
                                if ($diff > 0) {
                                    $amountInOC = $this->convertFromEuros(
                                        $rates,
                                        $diff,
                                        $record['operation_currency']
                                    );
                                    $data[$index]['commission'] = round($amountInOC*self::WITHDRAWAL_PRIVATE_COMMISSION_RATE, 2);
                                } else {
                                    //otherwise set the commission to 0
                                    $data[$index]['commission'] = 0.00;
                                }
                                //and set the limit to the new limit from $diff
                                $data[$index]['limit'] = $diff;
                            }
                        } else {
                            //if the number of attempts is greater than 3 for the same week calculate the commission on the whole amount
                            //as you can notice I don't have a limit here because I don't need it
                            //in this case I always need to calculate the commission
                            $amountInOC = $this->convertFromEuros(
                                $rates,
                                $amountInEuros,
                                $record['operation_currency']
                            );
                            $data[$index]['commission'] = round($amountInOC*self::WITHDRAWAL_PRIVATE_COMMISSION_RATE, 2);
                        }
                    } else {
                        //this is applied if we have a new week or an attempt in a day from a different or a future week
                        $data = $this->newWeekOC(
                            $amountInEuros,
                            $rates,
                            $data,
                            $index
                        );
                    }
                }
            } else {
                //this block of code is very similar to the one above
                //with the only difference that here I do not convert the amounts
                //all the amounts are in euros
                //I know it's not the best looking code,
                //but I ran out of time
                //definitely it can be refactored
                $amount = $record['operation_amount'];
                if (!isset($data[$index - 1])) {
                    $data = $this->newWeek($data, $index);
                } else {
                    $previous = $data[$index - 1];

                    if ($record['frequency_by_week'] - $previous['frequency_by_week'] === 1) {
                        if ($record['frequency_by_week'] <= self::MAX_TRIES) {
                            if ($previous['limit'] > 0) {
                                $data[$index]['commission'] = round($amount*self::WITHDRAWAL_PRIVATE_COMMISSION_RATE, 2);
                                $data[$index]['limit'] = $previous['limit'] + $amount;
                            } else {
                                $diff = $previous['limit'] + $amount;
                                if ($diff > 0) {
                                    $data[$index]['commission'] = round($diff*self::WITHDRAWAL_PRIVATE_COMMISSION_RATE, 2);
                                } else {
                                    $data[$index]['commission'] = 0.00;
                                }
                                $data[$index]['limit'] = $diff;
                            }
                        } else {
                            $data[$index]['commission'] = round($amount*self::WITHDRAWAL_PRIVATE_COMMISSION_RATE, 2);
                        }
                    } else {
                        $data = $this->newWeek($data, $index);
                    }
                }
            }
        }

        return $data;
    }

    public function calculateDepositCommissions(array $data): array
    {
        foreach ($data as $index => $record) {
            $data[$index]['commission'] = $record['operation_amount'] * self::DEPOSIT_COMMISSION_RATE;
        }

        return $data;
    }

    public function calculateBusinessWithdrawCommissions(array $data): array
    {
        foreach ($data as $index => $record) {
            $data[$index]['commission'] = $record['operation_amount'] * self::WITHDRAWAL_BUSINESS_COMMISSION_RATE;
        }

        return $data;
    }

    public function newWeekOC(
        float $amountInEuros,
        array $rates,
        $data,
        $index
    ): array {
        //if the amount surpasses the limit
        //get the difference in euros and convert it back to the original currency
        //and for that amount calculate the commission
        //otherwise set the commission to 0.00
        //and also set the limit for the next attempts from the same week
        $diff = ($amountInEuros - self::LIMIT_AMOUNT);
        if ($diff > 0) {
            $amountInOC = self::convertFromEuros(
                $rates,
                $diff,
                $data[$index]['operation_currency']
            );
            $data[$index]['commission'] = round($amountInOC*self::WITHDRAWAL_PRIVATE_COMMISSION_RATE, 2);
        } else {
            $data[$index]['commission'] = 0.00;
        }
        $data[$index]['limit'] = $diff;

        return $data;
    }

    public function newWeek(
        $data,
        $index
    ): array {
        $diff = ($data[$index]['operation_amount'] - self::LIMIT_AMOUNT);

        if ($diff > 0) {
            $data[$index]['commission'] = round($diff*self::WITHDRAWAL_PRIVATE_COMMISSION_RATE, 2);
        } else {
            $data[$index]['commission'] = 0.00;
        }
        $data[$index]['limit'] = $diff;

        return $data;
    }

    public function convertToEuros(array $rates, float $amount, string $currency): float
    {
        $euros = 0.00;

        if (isset($rates[$currency])) {
            $rate = 1/(float) $rates[$currency];
            $euros = round($amount*$rate, 2);
        }

        return $euros;
    }

    public function convertFromEuros(array $rates, float $amount, string $currency): float
    {
        $oc = 0.00;

        if (isset($rates[$currency])) {
            $rate = 1/(float) $rates[$currency];
            $oc = round($amount/$rate, 2);
        }

        return $oc;
    }

    private function checkCurrency(string $currency): bool
    {
        if (!OperationCurrency::tryFrom($currency)) {
            LogHelper::logError("The currency $currency is not allowed");

            return false;
        }

        return true;
    }

    private function checkOperationType(string $operationType): bool
    {
        if (!OperationType::tryFrom($operationType)) {
            LogHelper::logError("The operation type $operationType is not allowed");

            return false;
        }

        return true;
    }

    private function checkUserType(string $userType): bool
    {
        if (!UserType::tryFrom($userType)) {
            LogHelper::logError("The user type $userType is not allowed");

            return false;
        }

        return true;
    }

    public function countOccurrencesByWeek(array $data): array
    {
        foreach ($data as $index => $record) {
            if (isset($data[$index-1])) {
                $previous = $data[$index -1];

                if ($record['user_id'] === $previous['user_id']) {
                    $diff = (int) $previous['date']->diff($record['date'])->format('%a');
                    if (
                        $diff >= 0
                        && $diff <= 6
                        && $previous['day_of_the_week'] <= $record['day_of_the_week']
                    ) {
                        $data[$index]['frequency_by_week'] = $previous['frequency_by_week'] + 1;
                    } else {
                        $data[$index]['frequency_by_week'] = 1;
                    }
                } else {
                    $data[$index]['frequency_by_week'] = 1;
                }
            } else {
                $data[$index]['frequency_by_week'] = 1;
            }
        }

        return $data;
    }
}
