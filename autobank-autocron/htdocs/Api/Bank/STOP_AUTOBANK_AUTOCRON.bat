@echo off
chcp 65001 >nul
setlocal
title YiYi - Stop Autobank AutoCron

set "BANKDIR=%~dp0"
type nul > "%BANKDIR%autocron_worker.stop"

set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "VBS=%STARTUP%\YIYI_Autobank_AutoCron.vbs"

if exist "%VBS%" del /F /Q "%VBS%"

echo Da gui lenh STOP.
echo Worker se dung trong toi da khoang 1 giay.
echo Startup cung da duoc go.
pause
