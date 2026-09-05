@echo off
chcp 65001 >nul
setlocal EnableExtensions
title YiYi - Cai AutoCron Autobank V2

set "BANKDIR=%~dp0"
for %%I in ("%BANKDIR%..\..\..") do set "XAMPP=%%~fI"
set "PHP=%XAMPP%\php\php.exe"
set "WORKER=%BANKDIR%AutoCronMBBankWorker.php"
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "STARTCMD=%STARTUP%\YIYI_Autobank_AutoCron.cmd"
set "OLDVBS=%STARTUP%\YIYI_Autobank_AutoCron.vbs"

echo ==========================================================
echo  YIYI AUTOBANK AUTOCRON V2 - TEAM2026
echo ==========================================================
echo.

if not exist "%PHP%" (
    echo [LOI] Khong tim thay PHP:
    echo %PHP%
    echo.
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
    echo Patch KHONG ghi de file nay vi no chua API key that.
    pause
    exit /b 4
)

if not exist "%STARTUP%" mkdir "%STARTUP%"

rem Xoa file VBS loi cua ban V1 neu co.
if exist "%OLDVBS%" del /F /Q "%OLDVBS%" >nul 2>&1

rem Tao startup CMD an bang PowerShell. Cach nay tranh loi quote VBS.
> "%STARTCMD%" echo @echo off
>>"%STARTCMD%" echo powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -Command "Start-Process -WindowStyle Hidden -FilePath '%PHP%' -ArgumentList '"%WORKER%"'"

if not exist "%STARTCMD%" (
    echo [LOI] Khong tao duoc Startup CMD.
    pause
    exit /b 5
)

del /F /Q "%BANKDIR%autocron_worker.stop" >nul 2>&1

rem Khoi dong worker ngay bay gio, khong thong qua VBS.
powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -Command "Start-Process -WindowStyle Hidden -FilePath '%PHP%' -ArgumentList '"%WORKER%"'"

timeout /t 2 /nobreak >nul

echo.
echo [OK] Da cai AutoCron vao Windows Startup.
echo [OK] Da khoi dong worker ngay bay gio.
echo [OK] Chu ky: 10 giay.
echo.
echo Startup:
echo %STARTCMD%
echo.
echo Log:
echo %BANKDIR%autocron_worker.log
echo.
echo De kiem tra:
echo Chay STATUS_AUTOBANK_AUTOCRON.bat
echo.
echo De dung:
echo Chay STOP_AUTOBANK_AUTOCRON.bat
echo.
pause
