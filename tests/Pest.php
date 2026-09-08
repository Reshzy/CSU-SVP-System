<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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
    ->use(RefreshDatabase::class)
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
 * Write an APP-CSE worksheet to a temporary CSV and return its path. No real
 * export is committed to the repo, so both importers are exercised against
 * sheets built here.
 *
 * A line is either a category banner, `['category' => 'OFFICE SUPPLIES']`, or
 * an item, `['code' => ..., 'name' => ..., 'unit' => ..., 'price' => ...,
 * 'q1' => ..., 'q2' => ..., 'q3' => ..., 'q4' => ...]`. Item rows are numbered
 * for you, since a numeric first cell is what marks a row as an item.
 *
 * @param  list<array<string, string|int|null>>  $lines
 */
function appCseCsv(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'app-cse').'.csv';
    register_shutdown_function(fn () => @unlink($path));

    $handle = fopen($path, 'w');
    $sequence = 0;

    foreach ($lines as $line) {
        $row = array_fill(0, 26, '');

        if (isset($line['category'])) {
            $row[0] = $line['category'];
        } else {
            $row[0] = ++$sequence;
            $row[1] = $line['code'] ?? '';
            $row[2] = $line['name'] ?? '';
            $row[3] = $line['unit'] ?? 'piece';
            $row[7] = $line['q1'] ?? '';
            $row[12] = $line['q2'] ?? '';
            $row[17] = $line['q3'] ?? '';
            $row[22] = $line['q4'] ?? '';
            $row[25] = $line['price'] ?? '';
        }

        fputcsv($handle, $row, ',', '"', '\\');
    }

    fclose($handle);

    return $path;
}

/**
 * The same worksheet as an upload, for the two CSV import screens.
 *
 * @param  list<array<string, string|int|null>>  $lines
 */
function appCseUpload(array $lines): Illuminate\Http\UploadedFile
{
    return Illuminate\Http\UploadedFile::fake()->createWithContent(
        'app-cse.csv',
        (string) file_get_contents(appCseCsv($lines)),
    );
}
