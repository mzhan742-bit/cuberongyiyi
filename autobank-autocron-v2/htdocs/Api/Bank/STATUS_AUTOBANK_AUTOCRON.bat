@echo off
chcp 65001 >nul
setlocal
title YiYi - Status Autobank AutoCron V2

set "BANKDIR=%~dp0"

echo ===== AUTOBANK AUTOCRON STATUS =====
echo.

powershell -NoProfile -Command ^
 "$p=Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*AutoCronMBBankWorker.php*' -and $_.Name -like 'php*' }; if($p){Write-Host '[RUNNING] Worker dang chay.' -ForegroundColor Green; $p | Select-Object ProcessId,Name,CommandLine | Format-List}else{Write-Host '[STOPPED] Khong tim thay worker.' -ForegroundColor Yellow}"

echo.
echo Log 30 dong gan nhat:
echo ------------------------------------
powershell -NoProfile -Command ^
 "if(Test-Path '%BANKDIR%autocron_worker.log'){Get-Content '%BANKDIR%autocron_worker.log' -Tail 30}else{Write-Host 'Chua co log. Hay chay INSTALL hoac START.'}"
echo.
pause
