@echo off
REM Removes the "AttendanceAlerts" scheduled task from this PC.
schtasks /Delete /TN "AttendanceAlerts" /F
echo.
pause
