<?php

declare(strict_types=1);

test('ダッシュボードページが表示できる', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});

test('ダッシュボードに全銀フォーマット変換へのリンクが表示される', function () {
    $response = $this->get('/');

    $response->assertSee('全銀フォーマット自動変換', false);
    $response->assertSee(route('gin-format-converter.index'), false);
});

test('ダッシュボードにレジデータ自動入力へのリンクが表示される', function () {
    $response = $this->get('/');

    $response->assertSee('レジデータ自動入力', false);
    $response->assertSee(route('register-data-importer.index'), false);
});

test('ダッシュボードに委託精算書一括発行へのリンクが表示される', function () {
    $response = $this->get('/');

    $response->assertSee('委託精算書一括発行', false);
    $response->assertSee(route('consignment-invoice-generator.index'), false);
});

test('ダッシュボードに予約管理システムへのリンクが表示される', function () {
    $response = $this->get('/');

    $response->assertSee('予約管理システム', false);
    $response->assertSee(route('reservations.calendar'), false);
});
