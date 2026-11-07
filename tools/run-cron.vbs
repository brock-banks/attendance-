' Runs the attendance alert checker silently (no console window).
' All paths are derived from THIS file's location, so it works on any
' machine / install path as long as the standard layout is kept:
'   <xampp>\htdocs\attendance\tools\run-cron.vbs
Option Explicit
Dim fso, toolsDir, projDir, htdocs, xampp, phpExe, cronPhp, cmd
Set fso = CreateObject("Scripting.FileSystemObject")

toolsDir = fso.GetParentFolderName(WScript.ScriptFullName)   ' ...\attendance\tools
projDir  = fso.GetParentFolderName(toolsDir)                 ' ...\attendance
htdocs   = fso.GetParentFolderName(projDir)                  ' ...\htdocs
xampp    = fso.GetParentFolderName(htdocs)                   ' ...\xampp

phpExe  = xampp & "\php\php.exe"
cronPhp = projDir & "\cron.php"

' Fall back to php on PATH if the XAMPP php.exe isn't where expected.
If Not fso.FileExists(phpExe) Then phpExe = "php"

cmd = """" & phpExe & """ """ & cronPhp & """"
CreateObject("WScript.Shell").Run cmd, 0, False
