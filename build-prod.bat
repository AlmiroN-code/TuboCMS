@echo off
echo Building frontend (production mode)...
D:\laragon\bin\nodejs\node-v22\node.exe node_modules/@symfony/webpack-encore/bin/encore.js production
echo Done!
pause
