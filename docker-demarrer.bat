@echo off
chcp 65001 > nul
echo ========================================================
echo   Lancement de Zanika avec Docker...
echo ========================================================
echo.

docker compose up -d --build

echo.
echo ========================================================
echo   Zanika est prêt sur Docker !
echo.
echo   * Site Web principal  : http://localhost:8080
echo   * Serveur Mailpit     : http://localhost:8026
echo.
echo   Pour arrêter les conteneurs, exécutez : docker-arreter.bat
echo ========================================================
pause
