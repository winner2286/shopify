@echo off
title Boutiq - Arret du Serveur
cls
echo ========================================================
echo          ARRET DU SERVEUR BOUTIQ
echo ========================================================
echo.
echo Recherche et arret du processus ecoutant sur le port 8000...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8000" ^| findstr "LISTENING"') do (
    taskkill /F /PID %%a >nul 2>&1
    echo Processus PID %%a arrete.
)
echo.
echo Le serveur Boutiq est maintenant arrete.
timeout /t 3 >nul
