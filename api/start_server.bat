@echo off
chcp 65001 >nul
cd /d "c:\wamp64\www\WEB~1\api\public"
echo Starting Alumni REST API on http://localhost:8081
echo Press Ctrl+C to stop the server.
"C:\wamp64\bin\php\php8.2.0\php.exe" -S localhost:8081 router.php