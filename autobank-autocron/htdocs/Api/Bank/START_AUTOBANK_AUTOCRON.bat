@echo off
chcp 65001 >nul
setlocal EnableExtensions
title YiYi - Start Autobank AutoCron

set "BANKDIR=%~dp0"
for %%I in ("%BANKDIR%..\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
set "WORKER=%BANKDIR%AutoCronMBBankWorker.php"

if not exist "%PHP%" (
    echo Khong tim thay %PHP%
    pause
    exit /b 2
)

del /F /Q "%BANKDIR%autocron_worker.stop" >nul 2>&1

powershell -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -Command ^
  "Start-Process -WindowStyle Hidden -FilePath '%PHP%' -ArgumentList '"%WORKER%"'"

echo Da gui lenh START AutoCron.
echo Kiem tra log: %BANKDIR%autocron_worker.log
pause
