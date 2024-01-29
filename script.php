<?php

declare(strict_types=1);

//uncomment this if you try to test huge csv files that take longer time to process everything
//if I used a framework probably queue would have been the best option
//ini_set('max_execution_time', 0);

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\CalculateCommissionHelper;
use App\Service\FileHelper;
use App\Service\FilterHelper;
use App\Service\LogHelper;
use App\Service\SortHelper;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();

$fileName = $argv[1] ?? '';
//the reason why I have  $offset and $length is that I've assumed that
//the csv file might be huge and I might want to chunk it and display the results by 100
//you can see ho I'm doing that in the FileHelper class in the lazyLoadCSV($filePath) method
//where I'm using generators in order to select which lines of the csv file will be loaded into memory
// and then stored into an array for further processing
$offset = (int) $_ENV['OFFSET'] ?? 0;
$length = (int) $_ENV['LENGTH'] ?? 100;
$increment = (int) $_ENV['INCREMENT'] ?? 100;

while (true) {
    try {
        $fileHelper = new FileHelper();
        $filePath = $fileHelper->getFilePath($fileName);

        //If you want the error logging to work you would need to create this path 'var/logs/errors.log'
        //it's gitignored
        if (empty($filePath)) {
            LogHelper::logError('Empty file path');

            exit("Empty file path \n");
        }

        //basically this method sets the offset and the limit for shipping lines in the file and generates an array from a csv file
        $csvArray = $fileHelper->getCSVAsArray($filePath, $offset, $length);

        if (empty($csvArray)) {
            exit("No more data from the csv file \n");
        }
    } catch (Exception $e) {
        LogHelper::logError($e->getMessage());

        exit($e->getMessage());
    }

    //this method separates the users into 4 categories:
    //private_withdraw, private_deposit, business_withdraw, business_deposit
    //these are the keys of the multidimensional array that I'm creating
    $segregatedUsers = FilterHelper::segregateUsers($csvArray);

    $privateWithdraw = $segregatedUsers['private_withdraw'];

    //I'm sorting the items of this array which by the way is multidimensional associative array
    // first by user_id and then by date
    //in ascending order
    SortHelper::sort($privateWithdraw, ['user_id', 'date']);

    $calcObj = new CalculateCommissionHelper($_ENV['RATES_URL']);

    //what this method is doing is checking the days for the records with the same user ids
    //if the day difference is between 0 and 6
    // and if the order value of the first day is smaller or equal to the order value of the next day
    //that means that the days belong in the same week
    //and you should increment the value of 'frequency_by_week'
    //otherwise it's a new week and reset the value to 1
    $privateWithdraw = $calcObj->countOccurrencesByWeek($privateWithdraw);

    //the following methods basically are calculating the commissions and attaching that value to the 'commission' key from the arrays returned
    $privateWithdraw = $calcObj->calculatePrivateWithdrawCommissions($privateWithdraw);

    if (empty($privateWithdraw)) {
        $message = 'The data for private users that are withdrawing is empty';

        LogHelper::logError($message);

        exit($message);
    }

    $privateDeposit = $calcObj->calculateDepositCommissions($segregatedUsers['private_deposit']);

    $businessDeposit = $calcObj->calculateDepositCommissions($segregatedUsers['business_deposit']);

    $businessWithdraw = $calcObj->calculateBusinessWithdrawCommissions($segregatedUsers['business_withdraw']);

    $data = [...$privateWithdraw, ...$privateDeposit, ...$businessWithdraw, ...$businessDeposit];

    //here I'm sorting by id, it's set by default, in order to get the initial order of the array
    SortHelper::sort($data);

    foreach ($data as $record) {
        echo number_format($record['commission'], 2) . "\n\n";
    }

    //here I'm doing the incrementing
    //if there are no enough file lines that correspond to the increment
    //the csv array will be empty and the script execution will be interrupted with an output message
    $offset += $increment;
    $length += $increment;
}
