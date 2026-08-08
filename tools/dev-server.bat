@echo off
rem Dev server for lcyapi
start "" /b "E:\tools\php\php.exe" -S 127.0.0.1:8000 -t "E:\Users\a1005\Desktop\lcyapi" > "E:\Users\a1005\Desktop\lcyapi\data\logs\php-server.log" 2>&1
echo dev server starting at http://127.0.0.1:8000