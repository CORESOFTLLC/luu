<?php

use App\Models\Lottery;
use App\Services\StatementImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Build a synthetic Khan Bank statement in the deployed export layout. Each row is
 * [bank-local datetime, credit, description] with optional explicit
 * [opening balance, closing balance]; otherwise balances run from 100,000.
 *
 * @param  list<array{0: string, 1: int, 2: string, 3?: float, 4?: float}>  $rows
 */
function khanStatement(array $rows, bool $withBalances = true): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->fromArray([
        ['Хэвлэсэн огноо:', '11/20/2026', 'Депозит дансны дэлгэрэнгүй хуулга'],
        $withBalances
            ? ['Гүйлгээний огноо', 'Салбар', 'Эхний үлдэгдэл', 'Дебит гүйлгээ', 'Кредит гүйлгээ', 'Эцсийн үлдэгдэл', 'Гүйлгээний утга', 'Харьцсан данс']
            : ['Гүйлгээний огноо', 'Салбар', 'Дебит гүйлгээ', 'Кредит гүйлгээ', 'Гүйлгээний утга'],
    ]);

    $balance = 100000.0;
    $line = 3;

    foreach ($rows as $row) {
        [$datetime, $credit, $description] = $row;
        $opening = $row[3] ?? $balance;
        $closing = $row[4] ?? $opening + $credit;
        $balance = $closing;

        $sheet->fromArray($withBalances
            ? [$datetime, '5085', number_format($opening, 2), '0', number_format($credit, 2), number_format($closing, 2), $description, '']
            : [$datetime, '5085', '0', number_format($credit, 2), $description], null, 'A'.$line++);
    }

    $sheet->setCellValue('A'.$line, 'Нийт дүн:');

    $path = sys_get_temp_dir().'/'.uniqid('statement_', true).'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'statement.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/**
 * @param  list<array{0: string, 1: int, 2: string, 3?: float, 4?: float}>  $rows
 * @return array{imported: int, tickets_created: int, skipped: int, invalidated: int}
 */
function importStatement(Lottery $lottery, array $rows, bool $withBalances = true): array
{
    return app(StatementImportService::class)->import($lottery, khanStatement($rows, $withBalances));
}
