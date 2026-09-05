@echo off
chcp 65001 >nul
setlocal EnableExtensions
title YiYi - Restart Autobank V6

set "BANKDIR=%~dp0"
for %%I in ("%BANKDIR%..\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
set "WORKER=%BANKDIR%AutoCronMBBankWorker.php"

echo ==========================================================
echo  YIYI AUTOBANK V6 - LYCAFETHU + CAUBERONGYIYI
echo ==========================================================
echo.

type nul > "%BANKDIR%autocron_worker.stop"
timeout /t 2 /nobreak >nul
del /F /Q "%BANKDIR%autocron_worker.stop" >nul 2>&1

powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -Command "Start-Process -WindowStyle Hidden -FilePath '%PHP%' -ArgumentList '"%WORKER%"'"

timeout /t 2 /nobreak >nul

echo [OK] Da restart worker.
echo.
echo Sau 10-15 giay:
echo - Refresh TestMBBank.php bang Ctrl+F5
echo - Giao dich QR - lycafethu3732 phai hien
echo - Neu chua xu ly, worker se xu ly va cong VND.
echo.
pause
