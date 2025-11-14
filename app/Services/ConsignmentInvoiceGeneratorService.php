<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ConsignmentInvoiceGeneratorService
{
    /**
     * 顧客管理データと売上データから精算書を一括生成する
     *
     * @param  string  $customerFilePath  顧客管理データExcelファイルのパス
     * @param  string  $salesFilePath  売上データExcelファイルのパス
     * @return string 生成されたExcelファイルのパス
     */
    public function generateInvoices(string $customerFilePath, string $salesFilePath): string
    {
        // 顧客データと売上データを結合
        $mergedData = $this->mergeCustomerAndSalesData($customerFilePath, $salesFilePath);

        // 精算書を生成
        return $this->createInvoiceExcel($mergedData);
    }

    /**
     * 顧客管理データと売上データを結合する
     *
     * @param  string  $customerFilePath  顧客管理データExcelファイルのパス
     * @param  string  $salesFilePath  売上データExcelファイルのパス
     * @return array<int, array<string, mixed>> 結合されたデータ
     */
    public function mergeCustomerAndSalesData(string $customerFilePath, string $salesFilePath): array
    {
        // 顧客管理データを読み込む
        $customerData = $this->loadCustomerData($customerFilePath);

        // 売上データを読み込む
        $salesData = $this->loadSalesData($salesFilePath);

        // クライアントIDごとに売上データを集計
        $salesByClient = [];
        foreach ($salesData as $sale) {
            $clientId = $sale['clientId'] ?? '';
            if (empty($clientId)) {
                continue;
            }

            if (! isset($salesByClient[$clientId])) {
                $salesByClient[$clientId] = [];
            }

            // 商品コードごとに集計
            $productCode = $sale['productCode'] ?? '';
            if (isset($salesByClient[$clientId][$productCode])) {
                $salesByClient[$clientId][$productCode]['quantity'] += (int) ($sale['quantity'] ?? 0);
                $salesByClient[$clientId][$productCode]['amount'] += (int) ($sale['amount'] ?? 0);
            } else {
                $salesByClient[$clientId][$productCode] = [
                    'productCode' => $productCode,
                    'productName' => $sale['productName'] ?? '',
                    'unitPrice' => (int) ($sale['unitPrice'] ?? 0),
                    'quantity' => (int) ($sale['quantity'] ?? 0),
                    'amount' => (int) ($sale['amount'] ?? 0),
                ];
            }
        }

        // 顧客データと売上データを結合
        $mergedData = [];
        foreach ($customerData as $customer) {
            $clientId = $customer['clientId'] ?? '';
            $items = $salesByClient[$clientId] ?? [];

            if (empty($items)) {
                continue; // 売上データがない顧客はスキップ
            }

            $mergedData[] = [
                'clientId' => $clientId,
                'companyName' => $customer['companyName'] ?? '',
                'postalCode' => $customer['postalCode'] ?? '',
                'address' => $customer['address'] ?? '',
                'commissionRate' => $this->parseCommissionRate($customer['commissionRate'] ?? '0%'),
                'bankName' => $customer['bankName'] ?? '',
                'branchName' => $customer['branchName'] ?? '',
                'branchNumber' => $customer['branchNumber'] ?? '',
                'accountType' => $customer['accountType'] ?? '',
                'accountNumber' => $customer['accountNumber'] ?? '',
                'accountHolder' => $customer['accountHolder'] ?? '',
                'items' => array_values($items),
            ];
        }

        return $mergedData;
    }

    /**
     * 精算書の金額を計算する
     *
     * @param  array<int, array<string, mixed>>  $items  商品データの配列
     * @param  float  $commissionRate  手数料率（パーセント）
     * @return array<string, int> 計算結果
     */
    public function calculateInvoiceAmounts(array $items, float $commissionRate): array
    {
        // 小計（売上金額の合計）
        $subtotal = array_sum(array_column($items, 'amount'));

        // 委託販売手数料
        $commission = (int) round($subtotal * ($commissionRate / 100));

        // 消費税（小計 - 手数料）× 10%
        $taxBase = $subtotal - $commission;
        $tax = (int) round($taxBase * 0.1);

        // 振込手数料（固定）
        $transferFee = -440;

        // お支払金額
        $total = $subtotal - $commission + $tax + $transferFee;

        return [
            'subtotal' => $subtotal,
            'commission' => $commission,
            'tax' => $tax,
            'transferFee' => $transferFee,
            'total' => $total,
        ];
    }

    /**
     * 顧客管理データを読み込む
     *
     * @param  string  $filePath  Excelファイルのパス
     * @return array<int, array<string, mixed>> 顧客データ
     */
    protected function loadCustomerData(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // ヘッダー行をスキップ
        $dataRows = array_slice($rows, 1);

        $customers = [];
        foreach ($dataRows as $row) {
            if (empty($row[0])) {
                continue;
            }

            $customers[] = [
                'clientId' => (string) ($row[0] ?? ''),
                'companyName' => (string) ($row[1] ?? ''),
                'postalCode' => (string) ($row[2] ?? ''),
                'address' => (string) ($row[3] ?? ''),
                'contactPerson' => (string) ($row[4] ?? ''),
                'phone' => (string) ($row[5] ?? ''),
                'fax' => (string) ($row[6] ?? ''),
                'email' => (string) ($row[7] ?? ''),
                'commissionRate' => (string) ($row[8] ?? '0%'),
                'contractStartDate' => (string) ($row[9] ?? ''),
                'bankName' => (string) ($row[10] ?? ''),
                'branchName' => (string) ($row[11] ?? ''),
                'branchNumber' => (string) ($row[12] ?? ''),
                'accountType' => (string) ($row[13] ?? ''),
                'accountNumber' => (string) ($row[14] ?? ''),
                'accountHolder' => (string) ($row[15] ?? ''),
                'notes' => (string) ($row[16] ?? ''),
            ];
        }

        return $customers;
    }

    /**
     * 売上データを読み込む
     *
     * @param  string  $filePath  Excelファイルのパス
     * @return array<int, array<string, mixed>> 売上データ
     */
    protected function loadSalesData(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // ヘッダー行をスキップ
        $dataRows = array_slice($rows, 1);

        $sales = [];
        foreach ($dataRows as $row) {
            if (empty($row[2])) { // クライアントIDが空の場合はスキップ
                continue;
            }

            $sales[] = [
                'salesDate' => (string) ($row[0] ?? ''),
                'receiptNumber' => (string) ($row[1] ?? ''),
                'clientId' => (string) ($row[2] ?? ''),
                'companyName' => (string) ($row[3] ?? ''),
                'productCode' => (string) ($row[4] ?? ''),
                'productName' => (string) ($row[5] ?? ''),
                'unitPrice' => (int) str_replace(',', '', (string) ($row[6] ?? '0')),
                'quantity' => (int) ($row[7] ?? 0),
                'amount' => (int) str_replace(',', '', (string) ($row[8] ?? '0')),
                'category' => (string) ($row[9] ?? ''),
            ];
        }

        return $sales;
    }

    /**
     * 手数料率をパーセントから数値に変換する
     *
     * @param  string  $rate  手数料率（例: "20%"）
     * @return float 手数料率（数値）
     */
    protected function parseCommissionRate(string $rate): float
    {
        $rate = str_replace('%', '', trim($rate));

        return (float) $rate;
    }

    /**
     * 精算書Excelファイルを作成する
     *
     * @param  array<int, array<string, mixed>>  $mergedData  結合されたデータ
     * @return string 生成されたExcelファイルのパス
     */
    protected function createInvoiceExcel(array $mergedData): string
    {
        $spreadsheet = new Spreadsheet;

        // 既存のシートを削除
        $spreadsheet->removeSheetByIndex(0);

        // 各顧客ごとにシートを作成
        foreach ($mergedData as $clientData) {
            $worksheet = $spreadsheet->createSheet();
            $worksheet->setTitle($clientData['clientId']);

            // 精算書のレイアウトを作成
            $this->createInvoiceLayout($worksheet, $clientData);
        }

        // 最初のシートをアクティブに設定
        $spreadsheet->setActiveSheetIndex(0);

        // ファイルを保存
        $fileName = 'consignment_invoices_' . date('YmdHis') . '.xlsx';
        $filePath = storage_path('app/public/' . $fileName);
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * 精算書のレイアウトを作成する
     *
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $worksheet  ワークシート
     * @param  array<string, mixed>  $clientData  顧客データ
     */
    protected function createInvoiceLayout($worksheet, array $clientData): void
    {
        // 列幅を設定（元の値の6分の1）
        $worksheet->getColumnDimension('A')->setWidth(12.5);  // 75 / 6
        $worksheet->getColumnDimension('B')->setWidth(28.33); // 170 / 6
        $worksheet->getColumnDimension('C')->setWidth(11.67); // 70 / 6
        $worksheet->getColumnDimension('D')->setWidth(11.67); // 70 / 6
        $worksheet->getColumnDimension('E')->setWidth(16.67); // 100 / 6

        // タイトル（行1）
        $worksheet->setCellValue('A1', '委託販売精算書');
        $worksheet->mergeCells('A1:E1');
        $titleStyle = $worksheet->getStyle('A1');
        $titleStyle->getFont()->setSize(18)->setBold(true)->setName('ＭＳ Ｐゴシック');
        $titleStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $titleStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_BOTTOM);

        // センター情報（行4-7）
        $worksheet->setCellValue('A4', '盛岡地域地場産業振興センター');
        $worksheet->getStyle('A4')->getFont()->setSize(11)->setBold(true);
        $worksheet->setCellValue('D4', '精算番号:');
        $worksheet->setCellValue('E4', date('Y-m') . '-' . $clientData['clientId']);
        $worksheet->setCellValue('A5', '〒020-0055');
        $worksheet->setCellValue('D5', '発行日:');
        $worksheet->setCellValue('E5', date('Y年n月j日'));
        $worksheet->setCellValue('A6', '岩手県盛岡市繋字尾入野64-102');
        $worksheet->setCellValue('A7', 'TEL: 019-689-2201 FAX: 019-689-2229');

        // 委託者情報（行9-12）
        $worksheet->setCellValue('A9', '【委託者】');
        $worksheet->getStyle('A9')->getFont()->setBold(true);
        $worksheet->setCellValue('C9', '【お支払金額】');
        $worksheet->mergeCells('C9:E9');
        $worksheet->getStyle('C9')->getFont()->setBold(true);
        $worksheet->getStyle('C9')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('C9:E9')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFE699');
        $worksheet->setCellValue('A10', $clientData['companyName'] . ' 様');
        $worksheet->getStyle('A10')->getFont()->setBold(true);
        $worksheet->setCellValue('A11', '〒' . $clientData['postalCode']);
        $worksheet->setCellValue('A12', $clientData['address']);
        $worksheet->mergeCells('C10:E12');
        $c10Style = $worksheet->getStyle('C10:E12');
        $c10Style->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4A90E2');
        $c10Style->getFont()->getColor()->setRGB('FFFFFF');

        // 精算期間（行15）
        $worksheet->setCellValue('A15', '【精算期間】 ' . date('Y年n月1日') . ' ～ ' . date('Y年n月t日'));
        $worksheet->getStyle('A15')->getFont()->setBold(true);

        // 商品明細ヘッダー（行16）
        $worksheet->setCellValue('A16', '商品コード');
        $worksheet->setCellValue('B16', '商品名');
        $worksheet->setCellValue('C16', '単価');
        $worksheet->setCellValue('D16', '販売数');
        $worksheet->setCellValue('E16', '売上金額');

        // ヘッダー行のスタイル
        $headerStyle = $worksheet->getStyle('A16:E16');
        $headerStyle->getFont()->setSize(11)->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4A90E2');
        $headerStyle->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $headerStyle->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $headerStyle->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $worksheet->getRowDimension(16)->setRowHeight(17.25);

        // 17-26行目に事前に罫線と行の高さを設定（商品データの有無に関わらず空白スペースを確保）
        for ($i = 17; $i <= 26; $i++) {
            $rowStyle = $worksheet->getStyle('A' . $i . ':E' . $i);
            $rowStyle->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $worksheet->getRowDimension($i)->setRowHeight(17.25);
            // 空白セルとして明示的に設定（視覚的にスペースを確保）
            $worksheet->setCellValue('A' . $i, '');
            $worksheet->setCellValue('B' . $i, '');
            $worksheet->setCellValue('C' . $i, '');
            $worksheet->setCellValue('D' . $i, '');
            $worksheet->setCellValue('E' . $i, '');
        }

        // 商品明細データ（行17以降、最大26行目まで）
        $row = 17;
        foreach ($clientData['items'] as $item) {
            // 26行目を超える場合は次の行に続く
            if ($row > 26) {
                // 27行目以降にも罫線と行の高さを設定
                $rowStyle = $worksheet->getStyle('A' . $row . ':E' . $row);
                $rowStyle->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $worksheet->getRowDimension($row)->setRowHeight(17.25);
            }
            $worksheet->setCellValue('A' . $row, $item['productCode']);
            $worksheet->setCellValue('B' . $row, $item['productName']);
            $worksheet->setCellValue('C' . $row, $item['unitPrice']);
            $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $worksheet->getStyle('C' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $worksheet->setCellValue('D' . $row, $item['quantity']);
            $worksheet->getStyle('D' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $worksheet->setCellValue('E' . $row, $item['amount']);
            $worksheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $worksheet->getStyle('E' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $row++;
        }

        // 合計行は常に27行目から開始（17-26行目は商品データ用のスペースとして確保）
        $row = 27;

        // 計算結果を取得
        $calculations = $this->calculateInvoiceAmounts($clientData['items'], $clientData['commissionRate']);

        // 合計行（行27-31相当）
        $worksheet->mergeCells('A' . $row . ':B' . $row);
        $worksheet->setCellValue('A' . $row, '小計');
        $worksheet->mergeCells('C' . $row . ':E' . $row);
        $worksheet->setCellValue('C' . $row, $calculations['subtotal']);
        $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $worksheet->getStyle('C' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $worksheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $worksheet->getStyle('A' . $row . ':E' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F0F0F0');
        $worksheet->getRowDimension($row)->setRowHeight(17.25);
        $row++;

        $worksheet->mergeCells('A' . $row . ':B' . $row);
        $worksheet->setCellValue('A' . $row, '委託販売手数料(' . number_format($clientData['commissionRate']) . '%)');
        $worksheet->mergeCells('C' . $row . ':E' . $row);
        $worksheet->setCellValue('C' . $row, -$calculations['commission']);
        $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $worksheet->getStyle('C' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $worksheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $worksheet->getStyle('A' . $row . ':E' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F0F0F0');
        $worksheet->getRowDimension($row)->setRowHeight(17.25);
        $row++;

        $worksheet->mergeCells('A' . $row . ':B' . $row);
        $worksheet->setCellValue('A' . $row, '消費税(10%)');
        $worksheet->mergeCells('C' . $row . ':E' . $row);
        $worksheet->setCellValue('C' . $row, $calculations['tax']);
        $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $worksheet->getStyle('C' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $worksheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $worksheet->getStyle('A' . $row . ':E' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F0F0F0');
        $worksheet->getRowDimension($row)->setRowHeight(17.25);
        $row++;

        $worksheet->mergeCells('A' . $row . ':B' . $row);
        $worksheet->setCellValue('A' . $row, '振込手数料');
        $worksheet->mergeCells('C' . $row . ':E' . $row);
        $worksheet->setCellValue('C' . $row, $calculations['transferFee']);
        $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $worksheet->getStyle('C' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $worksheet->getStyle('A' . $row . ':E' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $worksheet->getStyle('A' . $row . ':E' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F0F0F0');
        $worksheet->getRowDimension($row)->setRowHeight(17.25);
        $row++;

        $worksheet->mergeCells('A' . $row . ':B' . $row);
        $worksheet->setCellValue('A' . $row, 'お支払金額');
        $worksheet->mergeCells('C' . $row . ':E' . $row);
        $worksheet->setCellValue('C' . $row, $calculations['total']);
        $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $totalRowStyle = $worksheet->getStyle('A' . $row . ':E' . $row);
        $totalRowStyle->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $worksheet->getStyle('C' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $totalRowStyle->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $totalRowStyle->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4A90E2');
        $worksheet->getRowDimension($row)->setRowHeight(17.25);
        $row++;

        // 支払金額をC10にも設定
        $worksheet->setCellValue('C10', $calculations['total']);
        $worksheet->getStyle('C10')->getNumberFormat()->setFormatCode('"¥"#,##0');
        $worksheet->getStyle('C10')->getFont()->setBold(true)->setSize(20);
        $worksheet->getStyle('C10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('C10')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // 振込情報（行33-36相当）
        $row += 2;
        $worksheet->setCellValue('A' . $row, '【お振込予定日】 ' . date('Y年n月', strtotime('+1 month')) . '10日');
        $worksheet->mergeCells('A' . $row . ':E' . $row);
        $row++;
        $worksheet->setCellValue('A' . $row, '【振込先】');
        $row++;
        $worksheet->setCellValue('A' . $row, $clientData['bankName'] . ' ' . $clientData['branchName'] . '(' . $clientData['branchNumber'] . ') ' . $clientData['accountType'] . ' ' . $clientData['accountNumber']);
        $row++;
        $worksheet->setCellValue('A' . $row, '口座名義: ' . $clientData['accountHolder']);
        $row++;

        // 注意書き（行39相当）
        $worksheet->setCellValue('A' . $row, '※ご不明な点がございましたら、上記連絡先までお問い合わせください。');
        $worksheet->mergeCells('A' . $row . ':E' . $row);
    }
}
