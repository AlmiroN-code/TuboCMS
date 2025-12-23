@echo off
echo Building frontend (watch mode)...
echo Press Ctrl+C to stop
D:\laragon\bin\nodejs\node-v22\node.exe node_modules/@symfony/webpack-encore/bin/encore.js dev --watch
