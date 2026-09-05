@echo off
chcp 65001 >nul
setlocal EnableExtensions
title YiYi - Start Autobank AutoCron V2

set "BANKDIR=%~dp0"
for %%I in ("%BANKDIR%..\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
set "WORKER=%BANKDIR%AutoCronMBBankWorker.php"

if not exist "%PHP%" (
    echo [LOI] Khong tim thay PHP:
    echo %PHP%
    pause
    exit /b 2
)

if not exist "%WORKER%" (
    echo [LOI] Khong tim thay worker:
    echo %WORKER%
    pause
    exit /b 3
)

del /F /Q "%BANKDIR%autocron_worker.stop" >nul 2>&1

powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -Command "Start-Process -WindowStyle Hidden -FilePath '%PHP%' -ArgumentList '"%WORKER%"'"

timeout /t 2 /nobreak >nul
echo Da gui lenh START AutoCron.
echo Kiem tra log:
echo %BANKDIR%autocron_worker.log
pause
