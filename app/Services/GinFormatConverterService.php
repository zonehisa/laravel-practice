<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class GinFormatConverterService
{
    /**
     * Excelファイルを総合振込レコードフォーマットに変換する
     *
     * @param  string  $filePath  Excelファイルのパス
     * @return string 総合振込レコードフォーマットのテキストデータ
     */
    public function convertToGinFormat(string $filePath): string
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // ヘッダー行をスキップ（1行目を想定）
        $dataRows = array_slice($rows, 1);

        $dataRecords = [];
        $totalAmount = 0;
        $recordCount = 0;

        foreach ($dataRows as $row) {
            if (empty(array_filter($row))) {
                continue; // 空行をスキップ
            }

            $dataRecord = $this->convertRowToDataRecord($row);
            if ($dataRecord !== null) {
                $dataRecords[] = $dataRecord;
                $recordCount++;
                // 振込金額を合計（7列目）
                $totalAmount += (int) ($row[7] ?? 0);
            }
        }

        // 総合振込レコードフォーマットの構成
        $lines = [];
        $lines[] = $this->createHeaderRecord(); // ヘッダ・レコード
        $lines = array_merge($lines, $dataRecords); // データ・レコード
        $lines[] = $this->createTrailerRecord($recordCount, $totalAmount); // トレーラ・レコード
        $lines[] = $this->createEndRecord(); // エンド・レコード

        return implode("\r\n", $lines);
    }

    /**
     * ヘッダ・レコードを作成する
     *
     * @return string ヘッダ・レコード（120バイト固定長）
     */
    protected function createHeaderRecord(): string
    {
        // レコード区分（1）: 1=ヘッダ
        $recordType = '1';
        // データ種別（2）: 21=総合振込
        $dataType = '21';
        // コード区分（1）: 0=全銀協コード
        $codeType = '0';
        // 委託者コード（10）: 空白でパディング
        $clientCode = $this->padString('', 10, ' ', STR_PAD_RIGHT);
        // 委託者名（40）: 空白でパディング
        $clientName = $this->padString('', 40, ' ', STR_PAD_RIGHT);
        // 振込指定日（4）: MMDD形式、今日の日付（実際の仕様に合わせて調整が必要）
        $transferDate = $this->padString(date('md'), 4, '0', STR_PAD_LEFT);
        // 振込元銀行番号（4）: 空白でパディング
        $sourceBankCode = $this->padString('', 4, ' ', STR_PAD_RIGHT);
        // 振込元支店番号（3）: 空白でパディング
        $sourceBranchCode = $this->padString('', 3, ' ', STR_PAD_RIGHT);
        // 予備（55）: 空白でパディング
        $reserved = $this->padString('', 55, ' ', STR_PAD_RIGHT);

        return $recordType . $dataType . $codeType . $clientCode . $clientName . $transferDate . $sourceBankCode . $sourceBranchCode . $reserved;
    }

    /**
     * データ・レコードを作成する
     *
     * @param  array<int, mixed>  $row  Excelの行データ
     * @return string|null データ・レコード（120バイト固定長、変換できない場合はnull）
     */
    protected function convertRowToDataRecord(array $row): ?string
    {
        // Excelの列順序を想定:
        // 0: 受取人名（カナ）
        // 1: 銀行コード
        // 2: 銀行名
        // 3: 支店コード
        // 4: 支店名
        // 5: 預金種別（1:普通, 2:当座）
        // 6: 口座番号
        // 7: 振込金額

        // レコード区分（1）: 2=データ
        $recordType = '2';
        // 振込先銀行番号（4）
        $bankCode = $this->padString((string) ($row[1] ?? ''), 4, '0', STR_PAD_LEFT);
        // 振込先支店番号（3）
        $branchCode = $this->padString((string) ($row[3] ?? ''), 3, '0', STR_PAD_LEFT);
        // 預金種目（1）: 1=普通, 2=当座, 4=納税準備預金
        $accountType = (string) ($row[5] ?? '1');
        // 口座番号（7）
        $accountNumber = $this->padString((string) ($row[6] ?? ''), 7, '0', STR_PAD_LEFT);
        // 受取人名（30）: カナ名を半角に変換
        $recipientKana = $this->padString($this->convertToHalfWidthKana($row[0] ?? ''), 30, ' ', STR_PAD_RIGHT);
        // 振込金額（10）
        $amount = $this->padString((string) ($row[7] ?? 0), 10, '0', STR_PAD_LEFT);
        // 新規コード（1）: 0=通常
        $newCode = '0';
        // 顧客番号（20）: 空白でパディング
        $customerNumber = $this->padString('', 20, ' ', STR_PAD_RIGHT);
        // 振込区分（1）: 空白でパディング
        $transferType = ' ';
        // 識別情報（7）: 空白でパディング
        $identification = $this->padString('', 7, ' ', STR_PAD_RIGHT);
        // 預金者名（30）: 空白でパディング
        $depositorName = $this->padString('', 30, ' ', STR_PAD_RIGHT);
        // 予備（5）: 空白でパディング
        $reserved = $this->padString('', 5, ' ', STR_PAD_RIGHT);

        return $recordType . $bankCode . $branchCode . $accountType . $accountNumber . $recipientKana . $amount . $newCode . $customerNumber . $transferType . $identification . $depositorName . $reserved;
    }

    /**
     * トレーラ・レコードを作成する
     *
     * @param  int  $recordCount  データ件数
     * @param  int  $totalAmount  合計金額
     * @return string トレーラ・レコード（120バイト固定長）
     */
    protected function createTrailerRecord(int $recordCount, int $totalAmount): string
    {
        // レコード区分（1）: 8=トレーラ
        $recordType = '8';
        // データ件数（6）
        $count = $this->padString((string) $recordCount, 6, '0', STR_PAD_LEFT);
        // 合計金額（12）
        $total = $this->padString((string) $totalAmount, 12, '0', STR_PAD_LEFT);
        // 予備（101）: 空白でパディング
        $reserved = $this->padString('', 101, ' ', STR_PAD_RIGHT);

        return $recordType . $count . $total . $reserved;
    }

    /**
     * エンド・レコードを作成する
     *
     * @return string エンド・レコード（120バイト固定長）
     */
    protected function createEndRecord(): string
    {
        // レコード区分（1）: 9=エンド
        $recordType = '9';
        // 予備（119）: 空白でパディング
        $reserved = $this->padString('', 119, ' ', STR_PAD_RIGHT);

        return $recordType . $reserved;
    }

    /**
     * 全角カナを半角カナに変換する
     *
     * @param  string  $string  変換対象の文字列
     * @return string 半角カナに変換された文字列
     */
    protected function convertToHalfWidthKana(string $string): string
    {
        return mb_convert_kana($string, 'k', 'UTF-8');
    }

    /**
     * 文字列を指定長にパディングする（バイト単位）
     *
     * @param  string  $string  元の文字列
     * @param  int  $length  目標のバイト長
     * @param  string  $padString  パディング文字
     * @param  int  $padType  パディングタイプ
     * @return string パディング後の文字列（指定バイト長）
     */
    protected function padString(string $string, int $length, string $padString = ' ', int $padType = STR_PAD_RIGHT): string
    {
        // バイト長でチェック
        $byteLength = strlen($string);
        if ($byteLength >= $length) {
            return substr($string, 0, $length);
        }

        $paddingLength = $length - $byteLength;
        $padding = str_repeat($padString, $paddingLength);

        return match ($padType) {
            STR_PAD_LEFT => $padding . $string,
            STR_PAD_RIGHT => $string . $padding,
            STR_PAD_BOTH => str_repeat($padString, (int) floor($paddingLength / 2)) . $string . str_repeat($padString, (int) ceil($paddingLength / 2)),
            default => $string . $padding,
        };
    }
}
