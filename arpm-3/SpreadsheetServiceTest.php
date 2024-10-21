<?php

use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Services\SpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SpreadsheetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SpreadsheetService $spreadsheetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spreadsheetService = new SpreadsheetService();
    }

    /** @test */
    public function it_processes_valid_spreadsheet_data_and_dispatches_jobs()
    {
        Queue::fake();

        $filePath = 'path/to/valid_spreadsheet.xlsx'; // Simulate a valid file path
        $validData = [
            ['product_code' => 'P001', 'quantity' => 10],
            ['product_code' => 'P002', 'quantity' => 5],
        ];

        // Mock the importer to return valid data
        app()->instance('importer', $this->mockImporter($validData));

        $this->spreadsheetService->processSpreadsheet($filePath);

        // Assert that two products were created
        $this->assertCount(2, Product::all());

        // Assert that the jobs were dispatched
        Queue::assertPushed(ProcessProductImage::class, 2);
    }

    /** @test */
    public function it_skips_invalid_rows_in_spreadsheet()
    {
        Queue::fake();

        $filePath = 'path/to/invalid_spreadsheet.xlsx'; // Simulate a valid file path
        $invalidData = [
            ['product_code' => 'P001', 'quantity' => 0], // Invalid quantity
            ['product_code' => 'P002', 'quantity' => 5],
            ['product_code' => 'P001', 'quantity' => 5], // Duplicate product code
        ];

        // Mock the importer to return invalid data
        app()->instance('importer', $this->mockImporter($invalidData));

        $this->spreadsheetService->processSpreadsheet($filePath);

        // Assert that only one product was created
        $this->assertCount(1, Product::all());

        // Assert that the job was dispatched only once
        Queue::assertPushed(ProcessProductImage::class, 1);
    }

    /** @test */
    public function it_validates_product_code_uniqueness()
    {
        Queue::fake();

        $filePath = 'path/to/spreadsheet_with_duplicate_codes.xlsx'; // Simulate a valid file path
        $existingProduct = Product::create(['code' => 'P001', 'quantity' => 10]);

        $dataWithDuplicate = [
            ['product_code' => 'P001', 'quantity' => 5], // Duplicate code
            ['product_code' => 'P002', 'quantity' => 5],
        ];

        // Mock the importer to return data with duplicate codes
        app()->instance('importer', $this->mockImporter($dataWithDuplicate));

        $this->spreadsheetService->processSpreadsheet($filePath);

        // Assert that only one product was created (the new one)
        $this->assertCount(2, Product::all()); // One existing + one new

        // Assert that the job was dispatched only once for the unique code
        Queue::assertPushed(ProcessProductImage::class, 1);
    }

    protected function mockImporter(array $data)
    {
        return new class($data) {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function import($filePath)
            {
                return $this->data;
            }
        };
    }
}
