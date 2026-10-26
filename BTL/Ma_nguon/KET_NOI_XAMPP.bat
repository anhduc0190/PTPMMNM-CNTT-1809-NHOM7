@echo off
chcp 65001 >nul
title CAU HINH WEB ANALYTICS VAO XAMPP APACHE

:: 1. Kiem tra quyen Administrator. Neu chua co, tu dong goi UAC xin quyen
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo ====================================================================
    echo   [XAC THUC QUYEN] Dang mo hop thoai Administrator...
    echo   Vui long nhan [YES] khi Windows hien bang hoi "User Account Control"
    echo ====================================================================
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

echo ====================================================================
echo       BTL MA NGUON MO - FIT DNU - Nhom 7 CNTT 18-09
echo       TIEN TRINH CAU HINH WEB ANALYTICS VAO XAMPP LOCALHOST
echo ====================================================================
echo.

:: 2. Xac dinh thu muc chua ma nguon
set "TARGET_DIR=%~dp0"
if exist "%~dp0web-analytics\public\index.php" (
    set "TARGET_DIR=%~dp0web-analytics"
)
if exist "d:\btl PMP\web-analytics\public\index.php" (
    set "TARGET_DIR=d:\btl PMP\web-analytics"
)

set "HTDOCS=C:\xampp\htdocs"
set "HTDOCS_LINK=%HTDOCS%\web-analytics"
set "HTDOCS_INDEX=%HTDOCS%\index.php"

echo Thu muc ma nguon du an: %TARGET_DIR%
echo Thu muc XAMPP htdocs:   %HTDOCS%
echo.

if not exist "%HTDOCS%" (
    echo [LOI NGHIEP TRONG] Khong tim thay thu muc %HTDOCS%!
    echo Vui long kiem tra lai xem ban da cai dat XAMPP vao o C:\ chua.
    pause
    exit /b 1
)

:: 3. Cap quyen truy cap thu muc htdocs
echo [Buoc 1/4] Phan quyen ghi tren thu muc XAMPP htdocs...
icacls "%HTDOCS%" /grant "Users:(OI)(CI)M" /T >nul 2>&1

:: 4. Tao lien ket Directory Junction giua htdocs\web-analytics va thu muc du an
echo [Buoc 2/4] Tao lien ket C:\xampp\htdocs\web-analytics...
if exist "%HTDOCS_LINK%" (
    rmdir "%HTDOCS_LINK%" 2>nul
    if exist "%HTDOCS_LINK%" (
        del /f /q "%HTDOCS_LINK%" 2>nul
    )
)

mklink /J "%HTDOCS_LINK%" "%TARGET_DIR%"
if %errorlevel% neq 0 (
    echo [*] Thong bao: Dang su dung ban sao chep sang htdocs...
    xcopy /E /I /Y "%TARGET_DIR%" "%HTDOCS_LINK%" >nul
) else (
    echo [*] Da tao lien ket Junction thanh cong! (Cap nhat code o D: se lap tuc co hieu luc tren XAMPP)
)

:: 5. Chuyen huong trang chu localhost/ truc tiep vao WebAnalytics
echo [Buoc 3/4] Cau hinh trang chu http://localhost/ de tu dong mo WebAnalytics...
if not exist "%HTDOCS%\index_xampp_backup.php" (
    if exist "%HTDOCS_INDEX%" (
        copy /Y "%HTDOCS_INDEX%" "%HTDOCS%\index_xampp_backup.php" >nul
    )
)

powershell -NoProfile -Command "Set-Content -Path '%HTDOCS_INDEX%' -Value '<?php header(\"Location: /web-analytics/\"); exit; ?>'" >nul

:: 6. Kiem tra MySQL va khoi tao Database
echo [Buoc 4/4] Kiem tra Database MySQL va chay bang du lieu...
if exist "C:\xampp\php\php.exe" (
    if exist "%TARGET_DIR%\tools\sql.php" (
        "C:\xampp\php\php.exe" "%TARGET_DIR%\tools\sql.php"
    )
    if exist "%TARGET_DIR%\fixtures\seed.php" (
        "C:\xampp\php\php.exe" "%TARGET_DIR%\fixtures\seed.php"
    )
)

echo.
echo ====================================================================
echo   [HOAN TAT 100%%] HE THONG DA DUOC KET NOI THANH CONG VAO XAMPP!
echo ====================================================================
echo.
echo Tu bay gio:
echo   1. Mo XAMPP Control Panel, an [Start] Apache va MySQL.
echo   2. Truy cap truc tiep vao:
echo      - http://localhost/              (Tu dong vao thang WebAnalytics)
echo      - http://localhost/web-analytics/
echo      - http://localhost/web-analytics/demo-sites/site-alpha/ (Shop demo 1)
echo      - http://localhost/web-analytics/demo-sites/site-beta/  (Portal demo 2)
echo      - http://localhost/phpmyadmin/   (Quan ly Database van hoat dong 100%%)
echo.
echo Dang mo trang web trong trinh duyet...
start http://localhost/
timeout /t 3 >nul
exit
