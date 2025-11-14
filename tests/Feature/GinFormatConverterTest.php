<?php

declare(strict_types=1);

use Livewire\Volt\Volt;

test('全銀フォーマット変換ページが表示できる', function () {
    $response = $this->get('/gin-format-converter');

    $response->assertSuccessful();
});

test('ファイルが選択されていない場合はエラーになる', function () {
    $component = Volt::test('gin-format-converter.index');

    $component->call('convert');

    expect($component->get('error'))->toBe('ファイルを選択してください。');
});

test('総合振込レコードフォーマットが正しい構造で生成される', function () {
    $service = new \App\Services\GinFormatConverterService;

    // サービスの各メソッドを個別にテスト
    $headerRecord = invokeMethod($service, 'createHeaderRecord');
    expect(strlen($headerRecord))->toBe(120);
    expect(substr($headerRecord, 0, 1))->toBe('1'); // レコード区分が1（ヘッダ）であることを確認

    $dataRecord = invokeMethod($service, 'convertRowToDataRecord', [
        ['タナカタロウ', '0001', 'テスト銀行', '001', 'テスト支店', '1', '1234567', '10000'],
    ]);
    expect($dataRecord)->not->toBeNull();
    expect(strlen($dataRecord))->toBe(120);
    expect(substr($dataRecord, 0, 1))->toBe('2'); // レコード区分が2（データ）であることを確認

    $trailerRecord = invokeMethod($service, 'createTrailerRecord', [1, 10000]);
    expect(strlen($trailerRecord))->toBe(120);
    expect(substr($trailerRecord, 0, 1))->toBe('8'); // レコード区分が8（トレーラ）であることを確認

    $endRecord = invokeMethod($service, 'createEndRecord');
    expect(strlen($endRecord))->toBe(120);
    expect(substr($endRecord, 0, 1))->toBe('9'); // レコード区分が9（エンド）であることを確認
});

// プライベートメソッドを呼び出すためのヘルパー関数
function invokeMethod($object, string $methodName, array $parameters = []): mixed
{
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($object, $parameters);
}
