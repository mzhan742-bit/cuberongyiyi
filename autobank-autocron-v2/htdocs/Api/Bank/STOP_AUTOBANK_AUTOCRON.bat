@echo off
chcp 65001 >nul
setlocal EnableExtensions
title YiYi - Stop Autobank AutoCron V2

set "BANKDIR=%~dp0"
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "STARTCMD=%STARTUP%\YIYI_Autobank_AutoCron.cmd"
set "OLDVBS=%STARTUP%\YIYI_Autobank_AutoCron.vbs"

type nul > "%BANKDIR%autocron_worker.stop"

if exist "%STARTCMD%" del /F /Q "%STARTCMD%" >nul 2>&1
if exist "%OLDVBS%" del /F /Q "%OLDVBS%" >nul 2>&1

echo Da gui lenh STOP.
echo Worker se dung trong toi da khoang 1 giay.
echo Startup AutoCron cung da duoc go.
pause
