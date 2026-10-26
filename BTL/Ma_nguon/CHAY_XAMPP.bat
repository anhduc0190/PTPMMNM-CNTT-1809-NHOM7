@echo off
chcp 65001 >nul
title KHOI DONG XAMPP VA MO HE THONG WEB ANALYTICS

echo ====================================================================
echo   DANG KHOI DONG DICH VU XAMPP (APACHE & MYSQL)...
echo ====================================================================

:: Khoi dong Apache neu chua chay
netstat -ano | findstr ":80 " >nul
if %errorlevel% neq 0 (
    echo [*] Dang bat Apache (Port 80)...
    if exist "C:\xampp\apache_start.bat" (
        start "" /b "C:\xampp\apache_start.bat"
    )
) else (
    echo [*] Apache dang hoat dong san tren cong 80.
)

:: Khoi dong MySQL neu chua chay
netstat -ano | findstr ":3306 " >nul
if %errorlevel% neq 0 (
    echo [*] Dang bat MySQL (Port 3306)...
    if exist "C:\xampp\mysql_start.bat" (
        start "" /b "C:\xampp\mysql_start.bat"
    )
) else (
    echo [*] MySQL dang hoat dong san tren cong 3306.
)

timeout /t 2 /nobreak >nul

echo.
echo ====================================================================
echo   DANG MO TRANG WEB ANALYTICS TREN LOCALHOST...
echo ====================================================================
echo.
echo Dia chi: http://localhost/
echo.
start http://localhost/
exit
