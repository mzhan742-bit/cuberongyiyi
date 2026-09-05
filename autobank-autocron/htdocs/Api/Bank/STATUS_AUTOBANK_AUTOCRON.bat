@echo off
chcp 65001 >nul
setlocal
title YiYi - Status Autobank AutoCron

set "BANKDIR=%~dp0"
echo ===== AUTOBANK AUTOCRON STATUS =====
echo.
echo Log 30 dong gan nhat:
echo ------------------------------------
powershell -NoProfile -Command ^
  "if(Test-Path '%BANKDIR%autocron_worker.log'){Get-Content '%BANKDIR%autocron_worker.log' -Tail 30}else{Write-Host 'Chua co log. Hay chay INSTALL hoac START.'}"
echo.
pause
