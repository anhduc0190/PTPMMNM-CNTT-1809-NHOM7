@echo off
chcp 65001 >nul
title KET NOI WEBSITE ANALYTICS VAO XAMPP APACHE

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
echo       DE TAI 17: CONG CU THONG KE LUOT TRUY CAP VA THAO TAC WEBSITE
echo       TIEN TRINH KET NOI VAO XAMPP LOCALHOST
echo ====================================================================
echo.

set "TARGET_DIR=%~dp0website-analytics"
set "HTDOCS=C:\xampp\htdocs"
set "HTDOCS_LINK=%HTDOCS%\website-analytics"
set "HTDOCS_INDEX=%HTDOCS%\index.php"

echo Thu muc ma nguon: %TARGET_DIR%
echo Thu muc XAMPP:    %HTDOCS%
echo.

if not exist "%HTDOCS%" (
    echo [THONG BAO] Dang tao thu muc %HTDOCS%...
    mkdir "%HTDOCS%" 2>nul
)

:: 2. Cap quyen truy cap thu muc htdocs
echo [Buoc 1/4] Phan quyen ghi tren thu muc XAMPP htdocs...
icacls "%HTDOCS%" /grant "Users:(OI)(CI)M" /T >nul 2>&1

:: 3. Tao lien ket Directory Junction giua htdocs\website-analytics va thu muc du an
echo [Buoc 2/4] Tao lien ket C:\xampp\htdocs\website-analytics...
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
    echo [*] Da tao lien ket Junction thanh cong! (Cap nhat code o o D se lap tuc co hieu luc tren XAMPP)
)

:: 4. Chuyen huong trang chu localhost/ truc tiep vao website-analytics
echo [Buoc 3/4] Cau hinh trang chu http://localhost/ de tu dong mo website-analytics...
powershell -NoProfile -Command "Set-Content -Path '%HTDOCS_INDEX%' -Value '<?php header(\"Location: /website-analytics/\"); exit; ?>'" >nul

:: 5. Kiem tra MySQL va khoi tao Database
echo [Buoc 4/4] Khoi tao co so du lieu MySQL website_analytics...
if exist "C:\xampp\mysql\bin\mysql.exe" (
    Get-Content "%TARGET_DIR%\database\schema.sql" | "C:\xampp\mysql\bin\mysql.exe" -u root 2>nul
    Get-Content "%TARGET_DIR%\database\seed.sql" | "C:\xampp\mysql\bin\mysql.exe" -u root 2>nul
)

echo.
echo ====================================================================
echo   [HOAN TAT 100%%] HE THONG DA DUOC KET NOI THANH CONG VAO XAMPP!
echo ====================================================================
echo.
echo Cac dia chi truy cap:
echo   - Dashboard quan tri:   http://localhost/website-analytics/
echo   - Website Demo A:       http://localhost/website-analytics/demo-sites/site-a/index.html
echo   - Website Demo B:       http://localhost/website-analytics/demo-sites/site-b/index.html
echo   - Quan ly phpMyAdmin:   http://localhost/phpmyadmin/
echo.
echo Dang mo trang web trong trinh duyet...
start http://localhost/website-analytics/
timeout /t 3 >nul
exit
