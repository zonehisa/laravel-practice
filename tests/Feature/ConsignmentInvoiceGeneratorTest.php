<?php

declare(strict_types=1);

use Livewire\Volt\Volt;

test('委託精算書一括発行ページが表示できる', function () {
    $response = $this->get('/consignment-invoice-generator');

    $response->assertSuccessful();
});

test('ファイルが選択されていない場合はエラーになる', function () {
    $component = Volt::test('consignment-invoice-generator.index');

    $component->call('generate');

    expect($component->get('error'))->toBe('顧客管理データと売上データの両方を選択してください。');
});

test('顧客管理データと売上データから精算書を生成できる', function () {
    $service = new \App\Services\ConsignmentInvoiceGeneratorService;

    $customerFilePath = base_path('tests/Fixtures/03_sample/顧客管理データ.xlsx');
    $salesFilePath = base_path('tests/Fixtures/03_sample/委託販売売上データ.xlsx');

    expect(file_exists($customerFilePath))->toBeTrue();
    expect(file_exists($salesFilePath))->toBeTrue();

    // 精算書を生成
    $outputPath = $service->generateInvoices($customerFilePath, $salesFilePath);

    expect(file_exists($outputPath))->toBeTrue();

    // 生成されたExcelファイルの内容を確認
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($outputPath);
    $sheetNames = $spreadsheet->getSheetNames();

    // 少なくとも1つのシートが存在することを確認
    expect(count($sheetNames))->toBeGreaterThan(0);

    // 最初のシートの内容を確認
    $firstSheet = $spreadsheet->getSheetByName($sheetNames[0]);
    $rows = $firstSheet->toArray();

    // タイトル行が存在することを確認
    $hasTitle = false;
    foreach ($rows as $row) {
        if (isset($row[0]) && strpos((string) $row[0], '委託販売精算書') !== false) {
            $hasTitle = true;
            break;
        }
    }
    expect($hasTitle)->toBeTrue();

    // 一時ファイルを削除
    unlink($outputPath);
});

test('顧客データと売上データを正しく結合できる', function () {
    $service = new \App\Services\ConsignmentInvoiceGeneratorService;

    $customerFilePath = base_path('tests/Fixtures/03_sample/顧客管理データ.xlsx');
    $salesFilePath = base_path('tests/Fixtures/03_sample/委託販売売上データ.xlsx');

    // データを読み込んで結合
    $mergedData = $service->mergeCustomerAndSalesData($customerFilePath, $salesFilePath);

    expect($mergedData)->toBeArray();
    expect(count($mergedData))->toBeGreaterThan(0);

    // 最初の顧客データを確認
    $firstClient = $mergedData[0] ?? null;
    expect($firstClient)->not->toBeNull();
    expect($firstClient)->toHaveKey('clientId');
    expect($firstClient)->toHaveKey('companyName');
    expect($firstClient)->toHaveKey('commissionRate');
    expect($firstClient)->toHaveKey('items');
});

test('精算書の計算が正しい', function () {
    $service = new \App\Services\ConsignmentInvoiceGeneratorService;

    // テストデータ
    $items = [
        ['productCode' => 'P001', 'productName' => '商品A', 'unitPrice' => 1000, 'quantity' => 2, 'amount' => 2000],
        ['productCode' => 'P002', 'productName' => '商品B', 'unitPrice' => 1500, 'quantity' => 1, 'amount' => 1500],
    ];
    $commissionRate = 20; // 20%

    $calculations = $service->calculateInvoiceAmounts($items, $commissionRate);

    expect($calculations)->toBeArray();
    expect($calculations)->toHaveKey('subtotal');
    expect($calculations)->toHaveKey('commission');
    expect($calculations)->toHaveKey('tax');
    expect($calculations)->toHaveKey('transferFee');
    expect($calculations)->toHaveKey('total');

    // 計算結果を確認
    // 小計 = 2000 + 1500 = 3500
    expect($calculations['subtotal'])->toBe(3500);
    // 手数料 = 3500 * 0.2 = 700
    expect($calculations['commission'])->toBe(700);
    // 消費税 = (3500 - 700) * 0.1 = 280
    expect($calculations['tax'])->toBe(280);
    // 振込手数料 = -440（固定）
    expect($calculations['transferFee'])->toBe(-440);
    // 支払金額 = 3500 - 700 + 280 - 440 = 2640
    expect($calculations['total'])->toBe(2640);
});
