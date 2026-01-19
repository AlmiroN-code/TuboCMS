@echo off
echo Starting Messenger Worker...
echo Press Ctrl+C to stop
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console messenger:consume async -vv
