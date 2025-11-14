<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Smalot\PdfParser\Parser;

class RegisterDataImporterService
{
    /**
     * PDFファイルからデータを抽出する
     *
     * @param  string  $filePath  PDFファイルのパス
     * @return array<string, mixed> 抽出されたデータ
     */
    public function extractData(string $filePath): array
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        return $this->parseText($text);
    }

    /**
     * 複数のPDFファイルからデータを集計する
     *
     * @param  array<string>  $filePaths  PDFファイルのパスの配列
     * @return array<string, mixed> 集計されたデータ
     */
    public function aggregateData(array $filePaths): array
    {
        // 商品コードをキーとした集計用配列
        $aggregatedItems = [];

        foreach ($filePaths as $filePath) {
            $data = $this->extractData($filePath);

            // 各商品データを集計
            foreach ($data['items'] ?? [] as $item) {
                $productCode = $item['productCode'] ?? '';

                if (empty($productCode)) {
                    continue;
                }

                if (! isset($aggregatedItems[$productCode])) {
                    // 初めて出現する商品コード
                    $aggregatedItems[$productCode] = [
                        'productCode' => $productCode,
                        'productName' => $item['productName'] ?? '',
                        'unitPrice' => (int) ($item['unitPrice'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 0),
                        'amount' => (int) ($item['amount'] ?? 0),
                    ];
                } else {
                    // 既存の商品コード：数量と金額を加算
                    $aggregatedItems[$productCode]['quantity'] += (int) ($item['quantity'] ?? 0);
                    $aggregatedItems[$productCode]['amount'] += (int) ($item['amount'] ?? 0);
                }
            }
        }

        // 配列の値のみを取得（商品コード順にソート）
        ksort($aggregatedItems);
        $items = array_values($aggregatedItems);

        return [
            'items' => $items,
            'summary' => $this->calculateSummary($items),
        ];
    }

    /**
     * データをExcelファイルとして出力する（各POSごとのシートと集計シート）
     *
     * @param  array<string>  $filePaths  PDFファイルのパスの配列
     * @return string 生成されたExcelファイルのパス
     */
    public function exportToExcel(array $filePaths): string
    {
        $spreadsheet = new Spreadsheet;

        // 既存のシートを削除
        $spreadsheet->removeSheetByIndex(0);

        // 各POSごとのデータを取得
        $posData = [];
        $allItems = [];

        foreach ($filePaths as $filePath) {
            $data = $this->extractData($filePath);
            $registerNumber = $data['registerNumber'] ?? 'POS1';

            // 商品コードごとに集計
            $aggregatedItems = [];
            foreach ($data['items'] ?? [] as $item) {
                $productCode = $item['productCode'] ?? '';

                if (empty($productCode)) {
                    continue;
                }

                if (! isset($aggregatedItems[$productCode])) {
                    $aggregatedItems[$productCode] = [
                        'productCode' => $productCode,
                        'productName' => $item['productName'] ?? '',
                        'unitPrice' => (int) ($item['unitPrice'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 0),
                        'amount' => (int) ($item['amount'] ?? 0),
                    ];
                } else {
                    $aggregatedItems[$productCode]['quantity'] += (int) ($item['quantity'] ?? 0);
                    $aggregatedItems[$productCode]['amount'] += (int) ($item['amount'] ?? 0);
                }
            }

            ksort($aggregatedItems);
            $posData[$registerNumber] = array_values($aggregatedItems);

            // 全体集計用にデータを追加
            foreach ($aggregatedItems as $item) {
                $allItems[] = $item;
            }
        }

        // 各POSごとのシートを作成（POS番号で昇順ソート）
        ksort($posData);
        foreach ($posData as $registerNumber => $items) {
            $worksheet = $spreadsheet->createSheet();
            $worksheet->setTitle($registerNumber);

            // ヘッダー行を設定
            $headers = ['商品コード', '商品名', '単価', '販売数', '売上金額'];
            $worksheet->fromArray($headers, null, 'A1');
            $worksheet->getStyle('A1:E1')->getFont()->setBold(true);

            // データ行を設定
            $row = 2;
            foreach ($items as $item) {
                $worksheet->setCellValue('A'.$row, $item['productCode'] ?? '');
                $worksheet->setCellValue('B'.$row, $item['productName'] ?? '');
                $worksheet->setCellValue('C'.$row, $item['unitPrice'] ?? 0);
                $worksheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0');
                $worksheet->setCellValue('D'.$row, $item['quantity'] ?? 0);
                $worksheet->setCellValue('E'.$row, $item['amount'] ?? 0);
                $worksheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('#,##0');
                $row++;
            }

            // 合計行を追加
            $totalQuantity = array_sum(array_column($items, 'quantity'));
            $totalAmount = array_sum(array_column($items, 'amount'));
            $worksheet->setCellValue('A'.$row, '合計');
            $worksheet->getStyle('A'.$row)->getFont()->setBold(true);
            $worksheet->setCellValue('D'.$row, $totalQuantity);
            $worksheet->setCellValue('E'.$row, $totalAmount);
            $worksheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('#,##0');
            $worksheet->getStyle('E'.$row)->getFont()->setBold(true);

            // 列幅を自動調整
            foreach (range('A', 'E') as $col) {
                $worksheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        // 全体集計シートを作成
        $summaryItems = [];
        foreach ($allItems as $item) {
            $productCode = $item['productCode'] ?? '';

            if (empty($productCode)) {
                continue;
            }

            if (! isset($summaryItems[$productCode])) {
                $summaryItems[$productCode] = [
                    'productCode' => $productCode,
                    'productName' => $item['productName'] ?? '',
                    'unitPrice' => (int) ($item['unitPrice'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'amount' => (int) ($item['amount'] ?? 0),
                ];
            } else {
                $summaryItems[$productCode]['quantity'] += (int) ($item['quantity'] ?? 0);
                $summaryItems[$productCode]['amount'] += (int) ($item['amount'] ?? 0);
            }
        }

        ksort($summaryItems);
        $summaryItems = array_values($summaryItems);

        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('売上集計');

        // ヘッダー行を設定
        $headers = ['商品コード', '商品名', '単価', '販売数', '売上金額'];
        $summarySheet->fromArray($headers, null, 'A1');
        $summarySheet->getStyle('A1:E1')->getFont()->setBold(true);

        // データ行を設定
        $row = 2;
        foreach ($summaryItems as $item) {
            $summarySheet->setCellValue('A'.$row, $item['productCode'] ?? '');
            $summarySheet->setCellValue('B'.$row, $item['productName'] ?? '');
            $summarySheet->setCellValue('C'.$row, $item['unitPrice'] ?? 0);
            $summarySheet->getStyle('C'.$row)->getNumberFormat()->setFormatCode('#,##0');
            $summarySheet->setCellValue('D'.$row, $item['quantity'] ?? 0);
            $summarySheet->setCellValue('E'.$row, $item['amount'] ?? 0);
            $summarySheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('#,##0');
            $row++;
        }

        // 合計行を追加
        $totalQuantity = array_sum(array_column($summaryItems, 'quantity'));
        $totalAmount = array_sum(array_column($summaryItems, 'amount'));
        $summarySheet->setCellValue('A'.$row, '合計');
        $summarySheet->getStyle('A'.$row)->getFont()->setBold(true);
        $summarySheet->setCellValue('D'.$row, $totalQuantity);
        $summarySheet->setCellValue('E'.$row, $totalAmount);
        $summarySheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('#,##0');
        $summarySheet->getStyle('E'.$row)->getFont()->setBold(true);

        // 列幅を自動調整
        foreach (range('A', 'E') as $col) {
            $summarySheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 最初のシートをアクティブに設定
        $spreadsheet->setActiveSheetIndex(0);

        // ファイルを保存
        $fileName = 'register_data_'.date('YmdHis').'.xlsx';
        $filePath = storage_path('app/public/'.$fileName);
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * PDFテキストを解析してデータを抽出する
     *
     * @param  string  $text  PDFから抽出されたテキスト
     * @return array<string, mixed> 解析されたデータ
     */
    protected function parseText(string $text): array
    {
        $lines = explode("\n", $text);
        $items = [];
        $registerNumber = '';
        $date = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // レジ番号を抽出
            if (preg_match('/レジ番号[：:]\s*(POS\d+)/u', $line, $matches)) {
                $registerNumber = $matches[1];
            }

            // 営業日を抽出（令和 X年Y月Z日形式）
            if (preg_match('/営業日[：:]\s*令和\s*(\d+)年(\d+)月(\d+)日/u', $line, $matches)) {
                $year = (int) $matches[1] + 2018; // 令和1年 = 2019年
                $month = (int) $matches[2];
                $day = (int) $matches[3];
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            // 商品データ行を抽出
            // 形式: P001 商品名\t¥8,500 1 ¥8,500P016 商品名2\t¥800 0 ¥0
            // 2列に並んでいる可能性がある
            // 行内の全ての商品コードを検索
            preg_match_all('/P\d+/', $line, $productCodeMatches, PREG_OFFSET_CAPTURE);
            $productCodes = $productCodeMatches[0] ?? [];

            if (empty($productCodes)) {
                continue;
            }

            // 各商品コードに対してデータを抽出
            foreach ($productCodes as $index => $codeMatch) {
                $productCode = $codeMatch[0];
                $codePos = $codeMatch[1];

                // 次の商品コードの位置を取得（最後の商品の場合は行末まで）
                $nextCodePos = strlen($line);
                if ($index < count($productCodes) - 1) {
                    $nextCodePos = $productCodes[$index + 1][1];
                }

                // この商品コードのデータ部分を抽出
                $productLine = substr($line, $codePos, $nextCodePos - $codePos);

                // 商品データを抽出
                // 形式: P001 商品名\t¥8,500 1 ¥8,500 または P013竹細工 箸(5膳セット)\t¥1,200 1 ¥1,200
                // 商品コードの後はスペースまたは直接商品名が続く
                // 商品名の後はタブまたはスペース、その後¥マークが来る
                if (preg_match('/^(P\d+)(\s+)?(.+?)[\t\s]+¥([\d,]+)\s+(\d+)\s+¥([\d,]+)/u', $productLine, $matches)) {
                    $productName = trim($matches[3]);
                    // 商品名に次の商品コードが含まれている場合は除去
                    if (preg_match('/^(P\d+)/', $productName, $nameCodeMatch)) {
                        $productName = trim(str_replace($nameCodeMatch[0], '', $productName));
                    }

                    $items[] = [
                        'productCode' => $matches[1],
                        'productName' => $productName,
                        'unitPrice' => (int) str_replace(',', '', $matches[4]),
                        'quantity' => (int) $matches[5],
                        'amount' => (int) str_replace(',', '', $matches[6]),
                    ];
                }
            }
        }

        return [
            'date' => $date,
            'registerNumber' => $registerNumber,
            'items' => $items,
        ];
    }

    /**
     * データの集計を計算する
     *
     * @param  array<int, array<string, mixed>>  $items  商品データの配列
     * @return array<string, mixed> 集計結果
     */
    protected function calculateSummary(array $items): array
    {
        $totalAmount = 0;
        $totalQuantity = 0;

        foreach ($items as $item) {
            $totalAmount += (int) ($item['amount'] ?? 0);
            $totalQuantity += (int) ($item['quantity'] ?? 0);
        }

        return [
            'totalAmount' => $totalAmount,
            'totalQuantity' => $totalQuantity,
            'itemCount' => count($items),
        ];
    }
}
