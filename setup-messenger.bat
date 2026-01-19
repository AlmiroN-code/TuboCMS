@echo off
echo Creating Messenger tables...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console messenger:setup-transports
echo Done!
pause
