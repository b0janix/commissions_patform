<?php

use App\Service\CalculateCommissionHelper;
use App\Service\FileHelper;
use App\Service\FilterHelper;
use App\Service\SortHelper;

class MainTest extends \PHPUnit\Framework\TestCase
{
    public function testFilePath(): void
    {
        $fileName = 'input.csv';

        $path = __DIR__ . "/testInput/$fileName";

        $file = $this->createPartialMock(FileHelper::class, ['generateFilePath']);

        $file->expects($this->once())
            ->method('generateFilePath')
            ->willReturn($path);

        $filePath = $file->getFilePath($fileName);

        $this->assertEquals($path, $filePath);
    }

    public function testTranformCSVToArray()
    {
        require (__DIR__ . "/testInput/csvArray.php");

        if (!isset($arr)) {
            $arr = [];
        }

        $path = __DIR__ . "/testInput/input.csv";

        $file = $this->createPartialMock(FileHelper::class, ['generateFilePath']);

        $file->expects($this->once())
            ->method('generateFilePath')
            ->willReturn($path);

        $filePath = $file->getFilePath('input.csv');

        $csvArray = $file->getCSVAsArray($filePath, 0, 100);

        foreach ($csvArray as $index => $item) {
            $this->assertInstanceOf(DateTime::class, $item['date']);
            unset($csvArray[$index]['date']);
        }

        $this->assertEquals($arr, $csvArray);
    }

    public function testSegregateUsers()
    {
        require (__DIR__ . "/testInput/csvArray.php");

        if (!isset($arr)) {
            $arr = [];
        }

        $segregatedUsers = FilterHelper::segregateUsers($arr);

        $this->assertArrayHasKey('private_withdraw', $segregatedUsers);
        $privateWithdraw = $segregatedUsers['private_withdraw'];
        $this->assertNotEmpty($privateWithdraw);
        $this->assertArrayHasKey(9, $privateWithdraw);
        $this->assertArrayHasKey('user_type', $privateWithdraw[9]);
        $this->assertEquals('private', $privateWithdraw[9]['user_type']);
        $this->assertArrayHasKey('operation_type', $privateWithdraw[9]);
        $this->assertEquals('withdraw', $privateWithdraw[9]['operation_type']);
    }

    public function testSortRecordsByUserIdAndDate()
    {
        $path = __DIR__ . "/testInput/input.csv";

        $file = $this->createPartialMock(FileHelper::class, ['generateFilePath']);

        $file->expects($this->once())
            ->method('generateFilePath')
            ->willReturn($path);

        $filePath = $file->getFilePath('input.csv');

        $arr = $file->getCSVAsArray($filePath, 0, 100);

        $segregatedUsers = FilterHelper::segregateUsers($arr);
        $privateWithdraw = $segregatedUsers['private_withdraw'];

        SortHelper::sort($privateWithdraw, ['user_id', 'date']);

        foreach ($privateWithdraw as $index => $record) {
            if (isset($privateWithdraw[$index - 1])) {
                $this->assertTrue($privateWithdraw[$index - 1]['user_id'] <= $record['user_id']);
                if ($privateWithdraw[$index - 1]['user_id'] === $record['user_id']) {
                    $this->assertTrue($privateWithdraw[$index - 1]['date'] <= $record['date']);
                }
            }
        }
    }

    public function testCalculatePWCommissions()
    {
        require (__DIR__ . "/testInput/privateWithdrawArray.php");
        require (__DIR__ . "/testInput/rates.php");

        if (!isset($pwCommissions)) {
            $pwCommissions = [];
        }

        if (!isset($rates)) {
            $rates = [];
        }

        $path = __DIR__ . "/testInput/input.csv";

        $file = $this->createPartialMock(FileHelper::class, ['generateFilePath']);

        $file->expects($this->once())
            ->method('generateFilePath')
            ->willReturn($path);

        $filePath = $file->getFilePath('input.csv');

        $arr = $file->getCSVAsArray($filePath, 0, 100);

        $segregatedUsers = FilterHelper::segregateUsers($arr);
        $privateWithdraw = $segregatedUsers['private_withdraw'];

        SortHelper::sort($privateWithdraw, ['user_id', 'date']);

        $calcObj = $this->createPartialMock(CalculateCommissionHelper::class, ['rates']);

        $calcObj->expects($this->once())
            ->method('rates')
            ->willReturn($rates);

        $privateWithdraw = $calcObj->countOccurrencesByWeek($privateWithdraw);

        $privateWithdraw = $calcObj->calculatePrivateWithdrawCommissions($privateWithdraw);

        $compArray = array_map(fn($item) => $item['commission'], $privateWithdraw);

        sort($compArray);
        sort($pwCommissions);

        $this->assertEquals($pwCommissions, $compArray);
    }

    public function testCalculateDepositCommissions()
    {
        $expectedArray = [
            "user_id" => 1,
            "user_type" => "private",
            "operation_type" => "deposit",
            "operation_amount" => 200.0,
            "operation_currency" => "EUR",
            "day_of_the_week" => 2,
            "id" => 4
        ];

        require (__DIR__ . "/testInput/csvArray.php");

        if (!isset($arr)) {
            $arr = [];
        }

        $segregatedUsers = FilterHelper::segregateUsers($arr);

        $this->assertArrayHasKey('private_deposit', $segregatedUsers);
        $privateDeposit = $segregatedUsers['private_deposit'];

        $this->assertArrayHasKey(0, $privateDeposit);
        $this->assertEquals($expectedArray, $privateDeposit[0]);

        $calcObj = $this->createPartialMock(CalculateCommissionHelper::class, [
            'rates',
            'calculatePrivateWithdrawCommissions',
            'calculateBusinessWithdrawCommissions',
            'newWeekOC',
            'newWeek',
            'convertToEuros',
            'convertFromEuros',
            'countOccurrencesByWeek'
        ]);

        $privateDeposit = $calcObj->calculateDepositCommissions($privateDeposit);

        $this->assertEquals(0.06, $privateDeposit[0]['commission']);
    }

    public function testCalculateBusinessWithdraw()
    {
        $expectedArray = [
            "user_id" => 2,
            "user_type" => "business",
            "operation_type" => "withdraw",
            "operation_amount" => 300.0,
            "operation_currency" => "EUR",
            "day_of_the_week" => 3,
            "id" => 5
        ];

        require (__DIR__ . "/testInput/csvArray.php");

        if (!isset($arr)) {
            $arr = [];
        }

        $segregatedUsers = FilterHelper::segregateUsers($arr);

        $this->assertArrayHasKey('business_withdraw', $segregatedUsers);
        $businessWithdraw = $segregatedUsers['business_withdraw'];

        $this->assertArrayHasKey(0, $businessWithdraw);
        $this->assertEquals($expectedArray, $businessWithdraw[0]);

        $calcObj = $this->createPartialMock(CalculateCommissionHelper::class, [
            'rates',
            'calculatePrivateWithdrawCommissions',
            'calculateDepositCommissions',
            'newWeekOC',
            'newWeek',
            'convertToEuros',
            'convertFromEuros',
            'countOccurrencesByWeek'
        ]);

        $businessWithdraw = $calcObj->calculateBusinessWithdrawCommissions($businessWithdraw);

        $this->assertEquals(1.5, $businessWithdraw[0]['commission']);
    }
}