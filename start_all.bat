@echo off
chcp 65001 >nul
echo Starting Alumni API...
start /min "AlumniAPI" cmd /c "c:\wamp64\www\WEB~1\api\start_server.bat"
echo Starting Alumni Frontend...
start /min "AlumniFrontend" cmd /c "c:\wamp64\www\WEB~1\frontend\start_frontend.bat"
echo.
echo Both servers are starting in minimized windows.
echo Look for the AlumniAPI and AlumniFrontend icons in the taskbar.
echo To stop each server, close its respective window or end the task in Task Manager.