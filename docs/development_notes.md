# 開発ノート - 事前に意識すべきポイント

本ドキュメントは、全銀フォーマット変換システムの開発を通じて発見した、事前に意識すべき重要なポイントをまとめたものです。

## 実装に必要不可欠なポイント（クイックリファレンス）

Voltコンポーネントでファイルアップロード機能を実装する際に、必ず必要な設定と実装：

1. **レイアウトファイルの設定**
   - `resources/views/components/layouts/app.blade.php`に`@vite(['resources/css/app.css', 'resources/js/app.js'])`を追加
   - Tailwind CSSを読み込むため

2. **Voltコンポーネントのレイアウト指定**
   - Class-based APIで`layout()`メソッドを使用: `public function layout(): string { return 'components.layouts.app'; }`
   - レイアウトコンポーネント（`<x-layouts.app>`）でラップしない

3. **ファイルアップロードの実装**
   - `WithFileUploads`トレイトを使用: `use Livewire\WithFileUploads;`
   - 処理後は`$this->file = null;`でリセット（シリアライズエラー防止）

4. **ファイルダウンロード機能**
   - 専用のダウンロードルートを作成（`response()->download()`を使用）
   - `./vendor/bin/sail artisan storage:link`を実行

5. **状態管理**
   - 長い文字列はプロパティに保持せず、ファイルに保存してURLのみ保持
   - シリアライズ可能な型のみをプロパティに保持

6. **コマンド実行**
   - すべてのArtisanコマンドは`./vendor/bin/sail artisan`を使用
   - ビルドコマンドは`./vendor/bin/sail npm run build`または`./vendor/bin/sail npm run dev`

## 1. Livewire Voltコンポーネントの記述形式

### 問題
- `@volt`ディレクティブの後にインデントがあると、Voltがコンポーネントを認識できない
- エディタの自動フォーマット機能がインデントを追加してしまうことがある

### 解決策
Voltコンポーネントには2つの書き方があります：

#### Functional API（`@volt`ディレクティブ使用）
```php
@volt
<?php
use function Livewire\Volt\{state};

state(['property' => 'value']);

$action = function () {
    // 処理
};
?>

<div>
    <!-- HTML -->
</div>
@endvolt
```

**重要**: `@volt`の直後はインデントなしで開始する必要があります。

#### Class-based API（推奨）
```php
<?php

use Livewire\Volt\Component;

new class extends Component
{
    public $property = 'value';

    public function action(): void
    {
        // 処理
    }
}; ?>

<div>
    <!-- HTML -->
</div>
```

**推奨**: Class-based APIの方がインデントの問題が起きにくく、IDEの補完も効きやすいです。

## 2. Livewireファイルアップロードの実装

### 問題
- ファイルアップロードを使用する場合、`WithFileUploads`トレイトが必要
- Functional APIではトレイトの追加方法が異なる

### 解決策

#### Class-based API（推奨）
```php
<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $file = null;

    public function upload(): void
    {
        // ファイル処理
    }
}; ?>
```

#### Functional API
Functional APIでは、ファイルアップロードを使用する場合はClass-based APIに変更することを推奨します。

## 3. Livewireの状態シリアライズ

### 問題
- Livewireはコンポーネントの状態をJSONでシリアライズしてクライアントに送信する
- シリアライズできないオブジェクト（ファイルオブジェクトなど）をプロパティに保持するとエラーが発生する
- 長い文字列をプロパティに保持すると、シリアライズ時に問題が発生する可能性がある

### 解決策

#### ファイルオブジェクトの処理
```php
public function convert(): void
{
    // ファイル処理
    $filePath = $this->file->getRealPath();
    // ... 処理 ...
    
    // 処理完了後、ファイルオブジェクトをリセット
    $this->file = null;
}
```

#### 長い文字列の扱い
```php
// ❌ 悪い例：長い文字列をプロパティに保持
public $convertedContent = null; // 長い文字列

// ✅ 良い例：ファイルに保存してURLのみ保持
public $downloadUrl = null;
public $isConverted = false;

public function convert(): void
{
    // ファイルに保存
    Storage::disk('public')->put($fileName, $content);
    
    // URLのみ保持
    $this->downloadUrl = route('download', ['file' => $fileName]);
    $this->isConverted = true;
}
```

### シリアライズ可能な型
- `string`, `int`, `float`, `bool`, `array`（シリアライズ可能な値のみ）
- `null`

### シリアライズできない型
- ファイルオブジェクト（`TemporaryUploadedFile`など）
- リソースオブジェクト
- クロージャ
- 長い文字列（数KB以上）

## 4. ファイルダウンロード機能の実装

### 問題
- `Storage::disk('public')->url()`で生成したURLが直接ダウンロードできない場合がある
- ブラウザがファイルを表示しようとしてしまう

### 解決策
専用のダウンロードルートを作成する：

```php
// routes/web.php
Route::get('/gin-format-converter/download/{file}', function (string $file) {
    $filePath = storage_path('app/public/'.$file);

    if (! file_exists($filePath)) {
        abort(404, 'ファイルが見つかりません。');
    }

    return response()->download($filePath, $file, [
        'Content-Type' => 'text/plain; charset=Shift_JIS',
    ]);
})->name('gin-format-converter.download');
```

```php
// Voltコンポーネント内
$this->downloadUrl = route('gin-format-converter.download', ['file' => $fileName]);
```

## 5. ストレージリンクの設定

### 問題
- `public/storage`シンボリックリンクが存在しないと、ファイルにアクセスできない

### 解決策
```bash
./vendor/bin/sail artisan storage:link
```

**注意**: 本番環境でも必ず実行する必要があります。

## 6. キャッシュのクリア

### 開発中にキャッシュをクリアすべきタイミング
- ビューファイルを変更した後
- ルーティングを変更した後
- 設定ファイルを変更した後

### コマンド
```bash
# ビューキャッシュのみ
./vendor/bin/sail artisan view:clear

# すべてのキャッシュ
./vendor/bin/sail artisan optimize:clear

# ルートキャッシュのみ
./vendor/bin/sail artisan route:clear
```

## 7. テスト駆動開発（TDD）の実践

### 原則
1. **テストを先に書く**: 機能実装前にテストを作成
2. **レッド → グリーン → リファクタリング**: サイクルを繰り返す
3. **すべての機能をテスト**: Happy Path、Failure Path、Edge Cases

### テストの種類
- **Happy Path**: 正常系の動作をテスト
- **Failure Path**: エラーケースやバリデーションエラーをテスト
- **Edge Cases**: 境界値や特殊なケースをテスト
- **Integration**: ファイルアップロード、データ変換、ダウンロードなどの統合テスト

## 8. エラーハンドリング

### 問題
- エラーが発生した場合、ファイルオブジェクトがリセットされないとシリアライズエラーが発生する

### 解決策
```php
public function convert(): void
{
    try {
        // 処理
        $this->file = null; // 成功時もリセット
    } catch (\Exception $e) {
        $this->error = 'エラーメッセージ';
        $this->file = null; // エラー時も必ずリセット
    }
}
```

## 9. 文字エンコーディングの扱い

### 問題
- 全銀フォーマットはShift_JISエンコーディングが必要
- 内部処理はUTF-8で行い、ファイル保存時に変換する

### 解決策
```php
// UTF-8で処理
$convertedContent = $service->convertToGinFormat($filePath);

// Shift_JISに変換して保存
Storage::disk('public')->put(
    $fileName,
    mb_convert_encoding($convertedContent, 'SJIS', 'UTF-8')
);
```

## 10. 開発時のチェックリスト

### Voltコンポーネント作成時
- [ ] インデントが正しいか確認（`@volt`直後はインデントなし）
- [ ] レイアウトファイルを指定（`layout()`メソッドを使用）
- [ ] レイアウトファイルに`@vite`ディレクティブが含まれているか確認
- [ ] ファイルアップロードを使用する場合は`WithFileUploads`トレイトを追加
- [ ] シリアライズできないオブジェクトをプロパティに保持していないか確認
- [ ] 長い文字列をプロパティに保持していないか確認
- [ ] コンポーネントのコンテンツは単一のルート要素で囲む

### ファイルダウンロード実装時
- [ ] 専用のダウンロードルートを作成
- [ ] `./vendor/bin/sail artisan storage:link`が設定されているか確認
- [ ] 適切なContent-Typeヘッダーを設定

### Tailwind CSS実装時
- [ ] レイアウトファイルに`@vite(['resources/css/app.css', 'resources/js/app.js'])`を追加
- [ ] Voltコンポーネントで`layout()`メソッドを使用してレイアウトを指定
- [ ] `./vendor/bin/sail npm run build`または`./vendor/bin/sail npm run dev`を実行

### デプロイ前
- [ ] すべてのテストがパスしているか確認
- [ ] `storage:link`が本番環境で実行されているか確認
- [ ] キャッシュをクリア（`./vendor/bin/sail artisan optimize:clear`）
- [ ] エラーハンドリングが適切か確認
- [ ] Tailwind CSSが正しく読み込まれているか確認

## 11. Voltコンポーネントとレイアウトファイル

### 問題
- VoltコンポーネントでTailwind CSSを読み込む方法が不明確
- レイアウトコンポーネント（`<x-layouts.app>`）でラップするとLivewireエラーが発生
- 完全なHTML構造を追加すると、Voltコンポーネントの構造が崩れる

### 解決策

#### レイアウトファイルの設定
レイアウトファイル（`resources/views/components/layouts/app.blade.php`）にViteアセットを追加：

```php
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? 'Page Title' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        {{ $slot }}
    </body>
</html>
```

#### Voltコンポーネントでのレイアウト指定
Class-based APIの場合、`layout()`メソッドを使用：

```php
<?php

use Livewire\Volt\Component;

new class extends Component
{
    public function layout(): string
    {
        return 'components.layouts.app';
    }
    
    // ... その他のメソッド
}; ?>

<div>
    <!-- コンポーネントのコンテンツ -->
</div>
```

**重要**: 
- Voltコンポーネントは完全なHTML構造（`<!DOCTYPE html>`, `<html>`, `<head>`, `<body>`）を含める必要はない
- レイアウトコンポーネント（`<x-layouts.app>`）でラップするのではなく、`layout()`メソッドで指定する
- コンポーネントのコンテンツは単一のルート要素（`<div>`など）で囲む

### エラー例
- ❌ `<x-layouts.app>`でラップ: `Method or action [toJSON] does not exist`エラーが発生
- ❌ 完全なHTML構造を追加: Livewireがコンポーネントを正しく認識できない

## まとめ

Livewire Voltコンポーネントを使用する際は、以下の点に注意が必要です：

1. **コンポーネントの記述形式**: Class-based APIを推奨
2. **レイアウトの指定**: `layout()`メソッドを使用（レイアウトコンポーネントでラップしない）
3. **Tailwind CSSの読み込み**: レイアウトファイルに`@vite`ディレクティブを追加
4. **ファイルアップロード**: `WithFileUploads`トレイトが必要
5. **状態管理**: シリアライズ可能な型のみをプロパティに保持
6. **ファイル処理**: 処理後はファイルオブジェクトをリセット
7. **ダウンロード機能**: 専用のルートを作成
8. **ストレージリンク**: `./vendor/bin/sail artisan storage:link`を実行
9. **キャッシュ管理**: 開発中は適宜クリア

これらのポイントを事前に意識することで、開発効率が向上し、エラーを未然に防ぐことができます。

