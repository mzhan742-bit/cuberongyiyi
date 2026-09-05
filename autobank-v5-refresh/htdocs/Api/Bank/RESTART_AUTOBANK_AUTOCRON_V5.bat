@echo off
chcp 65001 >nul
setlocal EnableExtensions
title YiYi - Restart Autobank AutoCron V5

set "BANKDIR=%~dp0"
for %%I in ("%BANKDIR%..\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
set "WORKER=%BANKDIR%AutoCronMBBankWorker.php"

echo ==========================================================
echo  YIYI AUTOBANK - RESTART WORKER SAU HOTFIX SEPAY
echo ==========================================================
echo.

if not exist "%PHP%" (
  echo [LOI] Khong tim thay PHP: %PHP%
  pause
  exit /b 2
)

if not exist "%WORKER%" (
  echo [LOI] Khong tim thay worker: %WORKER%
  pause
  exit /b 3
)

rem Dung worker cu de class MBBank.php cu khong con nam trong RAM.
type nul > "%BANKDIR%autocron_worker.stop"
timeout /t 2 /nobreak >nul

rem Cho phep worker moi chay.
del /F /Q "%BANKDIR%autocron_worker.stop" >nul 2>&1

powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -Command "Start-Process -WindowStyle Hidden -FilePath '%PHP%' -ArgumentList '"%WORKER%"'"

timeout /t 2 /nobreak >nul

echo [OK] Da restart worker.
echo.
echo Bay gio:
echo 1. Chay STATUS_AUTOBANK_AUTOCRON.bat
echo 2. Refresh TestMBBank.php bang Ctrl+F5
echo.
echo TestMBBank V5 phai co dong:
echo "Tong giao dich API tra ve: X"
echo.
pause
