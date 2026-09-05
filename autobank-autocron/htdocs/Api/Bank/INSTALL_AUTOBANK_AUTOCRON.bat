@echo off
chcp 65001 >nul
setlocal EnableExtensions
title YiYi - Cai AutoCron Autobank

set "BANKDIR=%~dp0"
for %%I in ("%BANKDIR%..\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
set "WORKER=%BANKDIR%AutoCronMBBankWorker.php"
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "VBS=%STARTUP%\YIYI_Autobank_AutoCron.vbs"

echo ==========================================================
echo  YIYI AUTOBANK AUTOCRON - TEAM2026
echo ==========================================================
echo.

if not exist "%PHP%" (
    echo [LOI] Khong tim thay PHP: %PHP%
    echo File BAT nay phai nam trong:
    echo C:\xampp\htdocs\Api\Bank\
    pause
    exit /b 2
)

if not exist "%WORKER%" (
    echo [LOI] Khong tim thay AutoCronMBBankWorker.php
    pause
    exit /b 3
)

if not exist "%BANKDIR%CronMBBank.php" (
    echo [LOI] Khong tim thay CronMBBank.php hien co cua ban.
    echo Patch khong ghi de file nay vi no dang chua API key that.
    pause
    exit /b 4
)

if not exist "%STARTUP%" mkdir "%STARTUP%"

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$php='%PHP%'; $worker='%WORKER%'; $vbs='%VBS%';" ^
  "$line='Set WshShell = CreateObject(""WScript.Shell"")' + [Environment]::NewLine +" ^
  "'WshShell.Run """"' + $php + '"" ""' + $worker + '"""", 0, False';" ^
  "[IO.File]::WriteAllText($vbs,$line,(New-Object Text.UTF8Encoding($false)))"

if errorlevel 1 (
    echo [LOI] Khong tao duoc Startup VBS.
    pause
    exit /b 5
)

del /F /Q "%BANKDIR%autocron_worker.stop" >nul 2>&1

start "" /B wscript.exe "%VBS%"

echo.
echo [OK] Da cai AutoCron vao Windows Startup.
echo [OK] Da khoi dong worker ngay bay gio.
echo [OK] Chu ky: 10 giay.
echo.
echo Log:
echo %BANKDIR%autocron_worker.log
echo.
echo De dung AutoCron:
echo Chay STOP_AUTOBANK_AUTOCRON.bat
echo.
pause
