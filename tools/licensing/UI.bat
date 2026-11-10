@echo off
REM ─────────────────────────────────────────────────────────────
REM  SparKnife Ledger — مدیریت لایسنس
REM  دوبار کلیک کنید. مرورگر خودش باز می‌شود.
REM ─────────────────────────────────────────────────────────────

title SparKnife Ledger - License Manager

cd /d "%~dp0..\.."

where node >nul 2>nul
if errorlevel 1 (
	echo.
	echo   Node.js پیدا نشد.
	echo.
	echo   یا Node.js را نصب کنید: https://nodejs.org
	echo   یا از خط فرمان استفاده کنید:
	echo     bash tools/licensing/sparkknife.sh
	echo.
	pause
	exit /b 1
)

node "tools\licensing\ui\server.js"

pause
