# Tests Fixtures

このディレクトリには、テストで使用する固定データ（fixtures）を配置します。

## ファイル構成

### PDFファイル
- `sample_register_data.pdf`: POS1のレジデータサンプル
- `sample_register_data_2.pdf`: POS2のレジデータサンプル
- `sample_register_data_3.pdf`: POS3のレジデータサンプル
- `sample_register_data_4.pdf`: POS4のレジデータサンプル

### Excelファイル
- `sample_sales_summary.xlsx`: 商品別売上集計のサンプル（期待される出力形式）

## 使用方法

テストコード内では `base_path('tests/Fixtures/ファイル名')` を使用してファイルパスを取得します。

```php
$filePath = base_path('tests/Fixtures/sample_register_data.pdf');
```

## 注意事項

- このディレクトリのファイルは、テストの再現性を保つために固定データとして使用されます
- テストが外部ファイル（`docs/`フォルダなど）に依存しないように、必要なファイルはここにコピーしてください
- ファイル名は明確で分かりやすいものにしてください
