<?php

declare(strict_types=1);

use Livewire\Volt\Volt;

test('レジデータ自動入力ページが表示できる', function () {
    $response = $this->get('/register-data-importer');

    $response->assertSuccessful();
});

test('ファイルが選択されていない場合はエラーになる', function () {
    $component = Volt::test('register-data-importer.index');

    $component->call('process');

    expect($component->get('error'))->toBe('ファイルを選択してください。');
});

test('PDFファイルからデータを抽出できる', function () {
    $service = new \App\Services\RegisterDataImporterService;

    $filePath = base_path('tests/Fixtures/sample_register_data.pdf');
    expect(file_exists($filePath))->toBeTrue();

    // PDFからデータを抽出
    $result = $service->extractData($filePath);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('date');
    expect($result)->toHaveKey('registerNumber');
    expect($result)->toHaveKey('items');
    expect($result['registerNumber'])->toBe('POS1');
    expect(count($result['items']))->toBeGreaterThan(0);

    // 商品データの構造を確認
    if (count($result['items']) > 0) {
        $firstItem = $result['items'][0];
        expect($firstItem)->toHaveKey('productCode');
        expect($firstItem)->toHaveKey('productName');
        expect($firstItem)->toHaveKey('unitPrice');
        expect($firstItem)->toHaveKey('quantity');
        expect($firstItem)->toHaveKey('amount');
    }
});

test('複数のPDFファイルからデータを集計できる', function () {
    $service = new \App\Services\RegisterDataImporterService;

    // 複数のPDFファイルを処理
    $filePaths = [
        base_path('tests/Fixtures/sample_register_data.pdf'),
        base_path('tests/Fixtures/sample_register_data_2.pdf'),
    ];

    foreach ($filePaths as $filePath) {
        expect(file_exists($filePath))->toBeTrue();
    }

    $result = $service->aggregateData($filePaths);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('items');
    expect($result)->toHaveKey('summary');
    expect(count($result['items']))->toBeGreaterThan(0);
    expect($result['summary'])->toHaveKey('totalAmount');
    expect($result['summary'])->toHaveKey('totalQuantity');
    expect($result['summary'])->toHaveKey('itemCount');
});

test('集計結果をExcelファイルとして出力できる（各POSごとのシートと集計シート）', function () {
    $service = new \App\Services\RegisterDataImporterService;

    // テスト用のPDFファイルを使用
    $filePaths = [
        base_path('tests/Fixtures/sample_register_data.pdf'),
        base_path('tests/Fixtures/sample_register_data_2.pdf'),
    ];

    foreach ($filePaths as $filePath) {
        expect(file_exists($filePath))->toBeTrue();
    }

    $filePath = $service->exportToExcel($filePaths);
    expect(file_exists($filePath))->toBeTrue();

    // Excelファイルの内容を確認
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
    $sheetNames = $spreadsheet->getSheetNames();

    // POS1とPOS2のシートが存在することを確認
    expect($sheetNames)->toContain('POS1');
    expect($sheetNames)->toContain('POS2');
    expect($sheetNames)->toContain('売上集計');

    // POS1シートの内容を確認
    $pos1Sheet = $spreadsheet->getSheetByName('POS1');
    $rows = $pos1Sheet->toArray();

    // ヘッダー行を確認
    expect($rows[0])->toBe(['商品コード', '商品名', '単価', '販売数', '売上金額']);

    // データ行が存在することを確認
    expect(count($rows))->toBeGreaterThan(1);

    // 売上集計シートの内容を確認
    $summarySheet = $spreadsheet->getSheetByName('売上集計');
    $summaryRows = $summarySheet->toArray();

    // ヘッダー行を確認
    expect($summaryRows[0])->toBe(['商品コード', '商品名', '単価', '販売数', '売上金額']);

    // データ行が存在することを確認
    expect(count($summaryRows))->toBeGreaterThan(1);

    // 一時ファイルを削除
    unlink($filePath);
});
