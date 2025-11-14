<?php

use App\Services\RegisterDataImporterService;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $files = [];
    public $downloadUrl = null;
    public $error = null;
    public $isProcessed = false;

    public function layout(): string
    {
        return 'components.layouts.app';
    }

    public function process(): void
    {
        $this->error = null;
        $this->downloadUrl = null;
        $this->isProcessed = false;

        if (empty($this->files)) {
            $this->error = 'ファイルを選択してください。';

            return;
        }

        try {
            $service = new RegisterDataImporterService();
            $filePaths = [];

            // アップロードされたファイルを一時保存
            foreach ($this->files as $file) {
                $filePaths[] = $file->getRealPath();
            }

            // Excelファイルとして出力（各POSごとのシートと集計シートを作成）
            $excelPath = $service->exportToExcel($filePaths);

            // ファイル名を取得
            $fileName = basename($excelPath);

            // ファイルオブジェクトをリセット（シリアライズエラーを防ぐ）
            $this->files = [];

            // ダウンロードURLを設定
            $this->downloadUrl = route('register-data-importer.download', ['file' => $fileName]);
            $this->isProcessed = true;
        } catch (\Exception $e) {
            $this->error = '処理中にエラーが発生しました: '.$e->getMessage();
            // エラー時もファイルオブジェクトをリセット
            $this->files = [];
        }
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">レジデータ自動入力</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4">
            <label for="files" class="block text-sm font-medium text-gray-700 mb-2">
                PDFファイルを選択（複数選択可）
            </label>
            <input type="file" id="files" wire:model="files" accept=".pdf" multiple
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
            @error('files')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @if (count($files) > 0)
                <p class="mt-2 text-sm text-gray-600">選択されたファイル: {{ count($files) }}件</p>
            @endif
        </div>

        <button wire:click="process" wire:loading.attr="disabled"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
            <span wire:loading.remove>処理開始</span>
            <span wire:loading>処理中...</span>
        </button>

        @if ($error)
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                <p class="text-sm text-red-600">{{ $error }}</p>
            </div>
        @endif

        @if ($isProcessed && $downloadUrl)
            <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                <p class="text-sm text-green-800 mb-2">処理が完了しました。</p>
                <a href="{{ $downloadUrl }}" download
                    class="inline-block px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    ダウンロード
                </a>
            </div>
        @endif
    </div>
</div>
