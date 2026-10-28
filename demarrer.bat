@echo off
title Boutiq - Serveur Local
cd /d "%~dp0"
cls
echo ========================================================
echo          LANCEMENT DU SERVEUR BOUTIQ
echo ========================================================
echo.
echo  - Sur ce PC        : http://localhost:8000
echo  - Sur votre mobile : http://192.168.10.199:8000 (ou votre IP Wi-Fi)
echo.
echo  Pour arreter le serveur : Fermez cette fenetre ou faites Ctrl + C
echo ========================================================
echo.
php artisan serve --host=0.0.0.0 --port=8000
pause
