<?php

use App\Services\GinFormatConverterService;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $file = null;
    public $downloadUrl = null;
    public $error = null;
    public $isConverted = false;

    public function layout(): string
    {
        return 'components.layouts.app';
    }

    public function convert(): void
    {
        $this->error = null;
        $this->downloadUrl = null;
        $this->isConverted = false;

        if (!$this->file) {
            $this->error = 'ファイルを選択してください。';

            return;
        }

        try {
            $service = new GinFormatConverterService();
            $filePath = $this->file->getRealPath();
            $convertedContent = $service->convertToGinFormat($filePath);

            // 一時ファイルとして保存
            $fileName = 'gin_format_' . date('YmdHis') . '.txt';
            Storage::disk('public')->put($fileName, mb_convert_encoding($convertedContent, 'SJIS', 'UTF-8'));

            // ファイルオブジェクトをリセット（シリアライズエラーを防ぐ）
            $this->file = null;

            // 変換後のコンテンツは保持せず、ダウンロードURLのみ保持
            $this->downloadUrl = route('gin-format-converter.download', ['file' => $fileName]);
            $this->isConverted = true;
        } catch (\Exception $e) {
            $this->error = '変換中にエラーが発生しました: ' . $e->getMessage();
            // エラー時もファイルオブジェクトをリセット
            $this->file = null;
        }
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">全銀フォーマット自動変換</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4">
            <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                Excelファイルを選択
            </label>
            <input type="file" id="file" wire:model="file" accept=".xlsx,.xls"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
            @error('file')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button wire:click="convert" wire:loading.attr="disabled"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
            <span wire:loading.remove>変換</span>
            <span wire:loading>変換中...</span>
        </button>

        @if ($error)
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                <p class="text-sm text-red-600">{{ $error }}</p>
            </div>
        @endif

        @if ($isConverted && $downloadUrl)
            <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                <p class="text-sm text-green-800 mb-2">変換が完了しました。</p>
                <a href="{{ $downloadUrl }}" download
                    class="inline-block px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    ダウンロード
                </a>
            </div>
        @endif
    </div>
</div>
