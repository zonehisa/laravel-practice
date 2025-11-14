<?php

use App\Services\ConsignmentInvoiceGeneratorService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $customerFile = null;
    public $salesFile = null;
    public $downloadUrl = null;
    public $error = null;
    public $isProcessed = false;

    public function layout(): string
    {
        return 'components.layouts.app';
    }

    public function generate(): void
    {
        $this->error = null;
        $this->downloadUrl = null;
        $this->isProcessed = false;

        if (! $this->customerFile || ! $this->salesFile) {
            $this->error = '顧客管理データと売上データの両方を選択してください。';

            return;
        }

        try {
            $service = new ConsignmentInvoiceGeneratorService();

            // 精算書を生成
            $excelPath = $service->generateInvoices(
                $this->customerFile->getRealPath(),
                $this->salesFile->getRealPath()
            );

            // ファイル名を取得
            $fileName = basename($excelPath);

            // ファイルオブジェクトをリセット（シリアライズエラーを防ぐ）
            $this->customerFile = null;
            $this->salesFile = null;

            // ダウンロードURLを設定
            $this->downloadUrl = route('consignment-invoice-generator.download', ['file' => $fileName]);
            $this->isProcessed = true;
        } catch (\Exception $e) {
            $this->error = '処理中にエラーが発生しました: '.$e->getMessage();
            // エラー時もファイルオブジェクトをリセット
            $this->customerFile = null;
            $this->salesFile = null;
        }
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">委託精算書一括発行</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-4">
            <label for="customerFile" class="block text-sm font-medium text-gray-700 mb-2">
                顧客管理データ（Excel）
            </label>
            <input type="file" id="customerFile" wire:model="customerFile" accept=".xlsx,.xls"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
            @error('customerFile')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="salesFile" class="block text-sm font-medium text-gray-700 mb-2">
                委託販売売上データ（Excel）
            </label>
            <input type="file" id="salesFile" wire:model="salesFile" accept=".xlsx,.xls"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
            @error('salesFile')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button wire:click="generate" wire:loading.attr="disabled"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
            <span wire:loading.remove>精算書を生成</span>
            <span wire:loading>生成中...</span>
        </button>

        @if ($error)
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                <p class="text-sm text-red-600">{{ $error }}</p>
            </div>
        @endif

        @if ($isProcessed && $downloadUrl)
            <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                <p class="text-sm text-green-800 mb-2">精算書の生成が完了しました。</p>
                <a href="{{ $downloadUrl }}" download
                    class="inline-block px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    ダウンロード
                </a>
            </div>
        @endif
    </div>
</div>
