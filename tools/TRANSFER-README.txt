ATTENDANCE — MOVE TO A NEW WINDOWS PC

This package contains the app, database, employee photos and scan images.
The admin username and password stay the same.

1. Install XAMPP with PHP 8.1 or newer and MariaDB on the new PC.
2. Copy the attendance folder into the new XAMPP htdocs folder.
   Example: C:\xampp\htdocs\attendance
3. Start Apache and MySQL from the XAMPP Control Panel.
4. Open http://localhost/phpmyadmin and import database.sql.
   Use a fresh server: the import creates a database named attendance.
   If attendance already exists, stop and back it up first. This import
   deliberately does not overwrite existing tables.
5. If the database username/password differs from XAMPP defaults,
   update config/config.php on the NEW PC.
6. Open http://localhost/attendance and sign in with your existing account.
   Do not run install.php or the demo seeding scripts.
7. Check Brock (1001), Sulaiman (1011), attendance and photos.
8. Open Settings > Attendance device. Test the terminal IP, then repair
   the receiving address using the NEW PC's local network address.
   Allow Apache on your local network and make a real test scan.
9. Stop the old PC's attendance server and any AttendanceAlerts task at
   cutover so it cannot change the terminal destination back.
10. If you use scheduled alerts, run tools/install-task.bat on the new PC.
    Recreate the backup task there. tools/backup.php currently uses
    F:/xampp/attendance-backups: change that path for the new PC first.

The data is a snapshot taken when this package was made. Later scans or
edits on the old PC are not included; take a fresh export at final cutover
if you continue using the old installation.

Keep this package private: it includes employee data and saved device/email
configuration. Keep database.sql and this package OUTSIDE htdocs; only copy
the attendance folder into htdocs.
