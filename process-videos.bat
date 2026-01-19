@echo off
echo Processing pending videos...
D:\laragon\bin\php\php-8.4.15-nts-Win32-vs17-x64\php.exe bin/console app:video:process-pending
echo Done!