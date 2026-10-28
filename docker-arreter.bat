@echo off
chcp 65001 > nul
echo ========================================================
echo   Arrêt des conteneurs Docker Zanika...
echo ========================================================
echo.

docker compose down

echo.
echo ========================================================
echo   Les conteneurs Docker sont arrêtés.
echo ========================================================
pause
