@echo off
REM ============================================================
REM  Installs the "AttendanceAlerts" scheduled task on THIS PC.
REM  Runs the alert checker every 10 minutes (while logged in).
REM  Just double-click this file on the client PC.
REM ============================================================
setlocal
set TASKNAME=AttendanceAlerts
set VBS=%~dp0run-cron.vbs

echo Registering scheduled task "%TASKNAME%" (every 10 minutes)...
echo Script: %VBS%
echo.

schtasks /Create /TN "%TASKNAME%" /TR "wscript.exe \"%VBS%\"" /SC MINUTE /MO 10 /F

if %errorlevel%==0 (
  echo.
  echo  Done. Alerts will be checked every 10 minutes while you are signed in.
  echo.
  echo  Run once now:   schtasks /Run /TN "%TASKNAME%"
  echo  Remove it:      uninstall-task.bat
  echo.
  echo  Tip: to run even when signed out, open Task Scheduler, open the
  echo  "%TASKNAME%" task, and tick "Run whether user is logged on or not".
) else (
  echo.
  echo  Could not create the task. Right-click this file and
  echo  choose "Run as administrator", then try again.
)
echo.
pause
