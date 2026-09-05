@echo off
chcp 65001 >nul
setlocal
title YiYi - Status Autobank AutoCron V5

set "BANKDIR=%~dp0"

echo ===== AUTOBANK AUTOCRON STATUS V5 =====
echo.

powershell -NoProfile -Command ^
 "$p=Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*AutoCronMBBankWorker.php*' -and $_.Name -like 'php*' }; if($p){Write-Host '[RUNNING] Worker dang chay.' -ForegroundColor Green; $p | Select-Object ProcessId,Name,CommandLine | Format-List}else{Write-Host '[STOPPED] Khong tim thay worker.' -ForegroundColor Yellow}"

echo.
echo Log 40 dong gan nhat:
echo ------------------------------------
powershell -NoProfile -Command ^
 "if(Test-Path '%BANKDIR%autocron_worker.log'){Get-Content '%BANKDIR%autocron_worker.log' -Tail 40}else{Write-Host 'Chua co log.'}"
echo.
pause
