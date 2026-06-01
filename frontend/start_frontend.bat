@echo off
chcp 65001 >nul
cd /d "c:\wamp64\www\WEB~1\frontend"
echo Starting Alumni Frontend on http://localhost:3000
echo Open this URL in your browser.
echo Press Ctrl+C to stop the server.
"C:\wamp64\bin\php\php8.2.0\php.exe" -S localhost:3000 -t .