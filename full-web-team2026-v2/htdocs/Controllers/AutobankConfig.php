<?php
/**
 * YiYi Autobank config bridge.
 * KHÔNG chứa số tài khoản hoặc API key cứng.
 * Ưu tiên .env hiện có của máy, sau đó mới đọc bảng settings.
 */

if (!function_exists('yiyiEnvFirst')) {
    function yiyiEnvFirst(array $keys, $default = '')
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }
        return $default;
    }
}

if (!function_exists('yiyiSettingFirst')) {
    function yiyiSettingFirst(array $settings, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $settings) && trim((string)$settings[$key]) !== '') {
                return trim((string)$settings[$key]);
            }
        }
        return $default;
    }
}

if (!function_exists('yiyiRealConfigValue')) {
    function yiyiRealConfigValue($value): string
    {
        $value = trim((string)$value);
        $invalid = ['', '0', '1', '#', 'null', 'NULL', 'undefined'];
        return in_array($value, $invalid, true) ? '' : $value;
    }
}

if (!function_exists('yiyiNormalizeBankCode')) {
    function yiyiNormalizeBankCode(string $value): string
    {
        $v = strtoupper(trim($value));
        $compact = preg_replace('/[^A-Z0-9]/', '', $v);

        $map = [
            'MB' => 'MB',
            'MBBANK' => 'MB',
            'MILITARYBANK' => 'MB',
            'TPB' => 'TPB',
            'TPBANK' => 'TPB',
            'VCB' => 'VCB',
            'VIETCOMBANK' => 'VCB',
            'TCB' => 'TCB',
            'TECHCOMBANK' => 'TCB',
            'ACB' => 'ACB',
            'VPB' => 'VPB',
            'VPBANK' => 'VPB',
            'BIDV' => 'BIDV',
            'VTB' => 'ICB',
            'VIETINBANK' => 'ICB',
            'ICB' => 'ICB',
            'VIB' => 'VIB',
            'MSB' => 'MSB',
            'OCB' => 'OCB',
            'SHB' => 'SHB',
            'SCB' => 'SCB',
            'STB' => 'STB',
            'SACOMBANK' => 'STB',
            'EIB' => 'EIB',
            'EXIMBANK' => 'EIB',
            'HDB' => 'HDB',
            'HDBANK' => 'HDB',
            'LPB' => 'LPB',
            'LPBANK' => 'LPB',
            'AGRIBANK' => 'VBA',
            'VBA' => 'VBA',
        ];

        return $map[$compact] ?? $v;
    }
}

if (!function_exists('yiyiBankDisplayName')) {
    function yiyiBankDisplayName(string $bankCode): string
    {
        $map = [
            'MB' => 'MB Bank',
            'TPB' => 'TPBank',
            'VCB' => 'Vietcombank',
            'TCB' => 'Techcombank',
            'ACB' => 'ACB',
            'VPB' => 'VPBank',
            'BIDV' => 'BIDV',
            'ICB' => 'VietinBank',
            'VIB' => 'VIB',
            'MSB' => 'MSB',
            'OCB' => 'OCB',
            'SHB' => 'SHB',
            'SCB' => 'SCB',
            'STB' => 'Sacombank',
            'EIB' => 'Eximbank',
            'HDB' => 'HDBank',
            'LPB' => 'LPBank',
            'VBA' => 'Agribank',
        ];
        return $map[$bankCode] ?? $bankCode;
    }
}

if (!function_exists('yiyiGetAutobankConfig')) {
    function yiyiGetAutobankConfig(array $settings = []): array
    {
        $accountNumber = yiyiRealConfigValue(yiyiEnvFirst([
            'AUTOBANK_ACCOUNT_NUMBER',
            'BANK_ACCOUNT_NUMBER',
            'SEPAY_ACCOUNT_NUMBER',
            'MBBANK_ACCOUNT_NUMBER',
        ], yiyiSettingFirst($settings, ['NumberBank'], '')));

        // Trong source web cũ, NameBank là TÊN CHỦ TÀI KHOẢN.
        $accountName = yiyiRealConfigValue(yiyiEnvFirst([
            'AUTOBANK_ACCOUNT_NAME',
            'BANK_ACCOUNT_NAME',
            'SEPAY_ACCOUNT_NAME',
        ], yiyiSettingFirst($settings, ['NameBank'], '')));

        $bankCodeRaw = yiyiRealConfigValue(yiyiEnvFirst([
            'AUTOBANK_BANK_CODE',
            'BANK_CODE',
            'SEPAY_BANK_CODE',
            'VIETQR_BANK_CODE',
        ], yiyiSettingFirst($settings, ['BankCode'], '')));

        // Nếu hệ thống đang dùng API MB Bank và không khai báo BANK_CODE thì tự nhận MB.
        if ($bankCodeRaw === '') {
            $mbKey = yiyiEnvFirst(['MBBANK_API_KEY', 'API_KEY_MBBANK', 'BANK_API_KEY'], '');
            if ($mbKey !== '') {
                $bankCodeRaw = 'MB';
            }
        }

        $bankCode = $bankCodeRaw !== '' ? yiyiNormalizeBankCode($bankCodeRaw) : '';

        $bankDisplay = yiyiRealConfigValue(yiyiEnvFirst([
            'AUTOBANK_BANK_NAME',
            'BANK_DISPLAY_NAME',
            'SEPAY_BANK_NAME',
        ], yiyiSettingFirst($settings, ['BankDisplayName'], '')));

        if ($bankDisplay === '' && $bankCode !== '') {
            $bankDisplay = yiyiBankDisplayName($bankCode);
        }

        $memoPrefix = yiyiRealConfigValue(yiyiEnvFirst([
            'AUTOBANK_MEMO_PREFIX',
            'SEPAY_MEMO_PREFIX',
        ], 'ngocrongluaga'));

        $bonusPercent = (float)yiyiEnvFirst(['AUTOBANK_BONUS_PERCENT'], '10');
        if ($bonusPercent < 0 || $bonusPercent > 100) {
            $bonusPercent = 0;
        }

        $minAmount = (int)yiyiEnvFirst(['AUTOBANK_MIN_AMOUNT'], '10000');
        if ($minAmount < 0) {
            $minAmount = 0;
        }

        $supportZalo = yiyiRealConfigValue(yiyiEnvFirst(
            ['SUPPORT_ZALO', 'AUTOBANK_SUPPORT_ZALO'],
            yiyiSettingFirst($settings, ['Zalo'], '')
        ));

        // Chỉ lấy key từ .env. Không hard-code / không lấy key mẫu từ source cũ.
        $apiKey = yiyiRealConfigValue(yiyiEnvFirst([
            'SEPAY_API_KEY',
            'AUTOBANK_API_KEY',
            'MBBANK_API_KEY',
            'API_KEY_MBBANK',
            'BANK_API_KEY',
        ], ''));

        return [
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'bank_code' => $bankCode,
            'bank_name' => $bankDisplay,
            'memo_prefix' => $memoPrefix !== '' ? $memoPrefix : 'ngocrongluaga',
            'bonus_percent' => $bonusPercent,
            'min_amount' => $minAmount,
            'support_zalo' => $supportZalo,
            'api_key' => $apiKey,
        ];
    }
}
