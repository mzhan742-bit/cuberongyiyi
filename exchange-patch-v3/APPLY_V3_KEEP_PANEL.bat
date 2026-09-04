@echo off
chcp 65001 >nul
setlocal EnableExtensions
title YIYI V3 - FIX WEB EXCHANGE - KEEP PANEL

echo ==========================================================
echo  YIYI V3 - FIX WEB EXCHANGE - GIU NGUYEN NRO MANAGER PANEL
echo ==========================================================
echo.

set "PATCHDIR=%~dp0"
set "ROOT="

if exist "%CD%\SRC\src\nro\models\server\ServerManager.java" set "ROOT=%CD%\SRC"
if not defined ROOT if exist "%CD%\src\nro\models\server\ServerManager.java" set "ROOT=%CD%"
if not defined ROOT if exist "%PATCHDIR%..\SRC\src\nro\models\server\ServerManager.java" set "ROOT=%PATCHDIR%..\SRC"
if not defined ROOT if exist "%PATCHDIR%..\src\nro\models\server\ServerManager.java" set "ROOT=%PATCHDIR%.."

if not defined ROOT (
  echo LOI: Khong tim thay source game.
  echo Hay copy thu muc PATCH_V3 vao thu muc goc chua SRC roi chay lai file nay.
  pause
  exit /b 2
)

for %%I in ("%ROOT%") do set "ROOT=%%~fI"
set "SM=%ROOT%\src\nro\models\server\ServerManager.java"
set "CL=%ROOT%\src\nro\models\server\Client.java"
set "SVC=%ROOT%\src\nro\models\services\WebExchangeService.java"
set "SRC_SVC=%PATCHDIR%FILES\SRC\src\nro\models\services\WebExchangeService.java"

echo Source game: %ROOT%
echo.

if not exist "%SRC_SVC%" (
  echo LOI: Thieu WebExchangeService.java trong patch.
  pause
  exit /b 3
)

copy /Y "%SM%" "%SM%.before_v3_keep_panel.bak" >nul
if exist "%CL%" copy /Y "%CL%" "%CL%.before_v3_keep_panel.bak" >nul

if not exist "%ROOT%\src\nro\models\services" mkdir "%ROOT%\src\nro\models\services"
copy /Y "%SRC_SVC%" "%SVC%" >nul

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$p=$env:SM; $t=[IO.File]::ReadAllText($p);" ^
  "if($t -notmatch 'WebExchangeService\.gI\(\)'){" ^
  "  $needle='activeServerSocket();';" ^
  "  $idx=$t.IndexOf($needle);" ^
  "  if($idx -lt 0){Write-Host 'LOI: Khong tim thay activeServerSocket();'; exit 41};" ^
  "  $insert=$needle + [Environment]::NewLine + [Environment]::NewLine +" ^
  "  '            // YIYI V3: web exchange worker - KEEP PANEL' + [Environment]::NewLine +" ^
  "  '            nro.models.services.WebExchangeService.gI().ensureTable();' + [Environment]::NewLine +" ^
  "  '            new Thread(nro.models.services.WebExchangeService.gI(), "Web Exchange").start();';" ^
  "  $t=$t.Substring(0,$idx)+$insert+$t.Substring($idx+$needle.Length);" ^
  "  [IO.File]::WriteAllText($p,$t,(New-Object Text.UTF8Encoding($false)));" ^
  "  Write-Host 'Da chen hook Web Exchange vao ServerManager.java';" ^
  "} else {Write-Host 'ServerManager.java da co hook Web Exchange - bo qua.'}"
if errorlevel 1 (
  echo LOI: Khong chen duoc hook. Da co backup .before_v3_keep_panel.bak
  pause
  exit /b 4
)

if exist "%CL%" (
  powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$p=$env:CL; $t=[IO.File]::ReadAllText($p);" ^
    "$pat='(?s)\s*try\s*\{\s*nro\.models\.services\.WebExchangeService\.gI\(\)\.processPending\(\);\s*\}\s*catch\s*\(Exception e\)\s*\{\s*Logger\.logException\(nro\.models\.services\.WebExchangeService\.class, e\);\s*\}';" ^
    "$n=[regex]::Replace($t,$pat,'');" ^
    "if($n -ne $t){[IO.File]::WriteAllText($p,$n,(New-Object Text.UTF8Encoding($false)));Write-Host 'Da go hook V1 cu khoi Client.java';}"
)

echo.
echo ==========================================================
echo XONG V3:
echo - KHONG ghi de ServerManager.java bang ban goc
echo - KHONG ghi de Client.java
echo - GIU NGUYEN code NRO Manager Panel dang co
echo - Chi chen hook vao dung ServerManager hien tai
echo ==========================================================
echo.
echo BAY GIO: Build lai JAR va restart server.
echo Hai giao dich PENDING cu se duoc xu ly khi nhan vat online.
echo.
pause
