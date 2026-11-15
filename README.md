# Face-Recognition Attendance (Hikvision DS-K1T342MFWX-E1)

PHP + MariaDB attendance app with Hikvision ISAPI enrollment, live scan feed,
shift scheduling, leave, alerts and CSV reports. Recognition happens on the terminal.

## Setup

1. Start Apache and MariaDB in XAMPP.
2. Configure database, device credentials, timezone and shifts in `config/config.php`.
3. Open `http://localhost/attendance/install.php`, create an administrator, then delete the installer.
4. Run the migrations in order: `tools/migrate.php`, `tools/migrate_b.php`, `tools/migrate_c.php`, `tools/migrate_d.php` using `F:/xampp/php/php.exe`.
5. Sign in at `http://localhost/attendance/admin/login.php`.

Configure the terminal HTTP listener to POST to
`http://<your-PC-LAN-IP>/attendance/api/event-receiver.php`.
Append `?token=YOURTOKEN` when EVENT_TOKEN is configured. Allow the Apache port
through Windows Firewall. Listener menus vary by firmware; check HTTP Listening
or Notify Surveillance Center settings. Use HTTPS and restrict endpoint access
for production deployment.

## Enrollment

Use Admin > Enroll to enter an employee number, name, optional card and frontal
JPG photo. The employee number links device events to attendance records.
ISAPI paths can vary by firmware; check `src/IsapiClient.php` if enrollment fails.

## Reliability and corrections

Migration D adds unique event fingerprints, scan eligibility and correction history.
Existing report totals are preserved until a day is recalculated.
Only major event 5 / sub-event 75 counts as successful verification; all other
raw events remain stored. Confirm this mapping before using another terminal.
Each shift day begins three hours before its scheduled start and ends at the next
shift-day boundary. First and last eligible scans determine attendance; scans
closer than MIN_RESCAN_SECONDS do not establish a checkout.

Reports > Correct attendance lets an administrator approve a correction with a
required reason. Original scans and the previous calculated row are retained.
Corrections override scan times during recalculation. This is direct administrator
approval, not a separate requester/reviewer queue.

Schedule `cron.php` every ten minutes for alerts and device watchdog recovery.
Run `F:/xampp/php/php.exe tools/test_reliability.php` for rollback-only database
checks of eligibility, shift separation and correction persistence.

## Layout

- `admin/`: dashboard, enrollment, reports, corrections and settings
- `api/`: device receiver and live dashboard feed
- `src/`: attendance, shifts, authentication, device and notification services
- `storage/`: employee photos and event snapshots
- `sql/` and `tools/`: schema, migrations and verification

## Approval workflow update

Run `tools/migrate_e.php` after migration D. New corrections are pending until a different administrator approves or rejects them with a reason. Only approved corrections affect totals. Review history includes requester, reviewer, timestamps, reasons and previous attendance. Existing direct approvals remain valid.

## Admin security and backups

Run `tools/migrate_security.php` after prior migrations. Existing accounts remain
administrators. Sign in again after this upgrade. Accounts lets administrators
create HR/viewer/admin users, disable accounts, change roles and reset passwords.
New passwords require 12 characters. Self-editing and removal of the last active
administrator are blocked. Account changes invalidate that account's sessions.

Admin POST forms require session CSRF tokens. Device actions and logout are POST
only. Login is limited to five attempts per account or source IP within 15 minutes.
Sessions expire after 30 idle minutes or eight hours total. Use HTTPS for transport
security; secure cookies are enabled automatically on HTTPS.

Run `F:/xampp/php/php.exe tools/backup.php`. Backups go outside Apache's document
root to `F:/xampp/attendance-backups`. Each compressed JSON snapshot contains table
schemas and rows. Each run restores it to a random temporary database, compares
all rows, then drops only that temporary database. Media copies are SHA-256 checked.
The `.verified` file records database verification. Photo/event files are in the
adjacent `.media` directory. Configuration credentials are not copied.

Backups are local and are not automatically deleted. Copy them to protected
external storage periodically and monitor disk space. The Windows scheduled task
Attendance Daily Backup runs daily at 02:00 Pacific while this Windows user is
signed in, with missed runs started when available. See Windows Task Scheduler
for last-run status. To recover, restore schemas and rows into a NEW database
first, verify it, then switch DB_NAME and restore the media directory during
planned downtime; never import directly over an unverified live database.

Security checks: `F:/xampp/php/php.exe tools/test_security.php` uses disposable
accounts, tests real HTTP routes, and removes its records afterward.

## Multiple work sessions

Run `tools/migrate_sessions.php` before using the session calculation. Attendance
now sums paired IN/OUT visits and excludes gaps. Text attendanceStatus values
checkIn/checkOut, breakIn/breakOut and overtimeIn/overtimeOut supply direction.
Without labels, chronological punches alternate IN/OUT after rapid duplicate
suppression, and the report flags Review punches. Unclosed sessions add no
estimated time. Missing/repeated directions require human review.
Recorded gaps satisfy the configured unpaid break before any remaining break
is deducted, avoiding double deduction. Approved corrections still explicitly
replace the day with one continuous interval. Reports expand work sessions and
CSV exports include the sessions and review flag. Historical totals are not
bulk-recalculated automatically by this migration.
Run `tools/test_sessions.php` for split-visit regression checks.

## Review workflows and dated shifts

Run `tools/migrate_workflows.php` after the previous migrations. The migration
adds storage and snapshots the shift definitions available at upgrade time;
it cannot reconstruct undocumented historical shift changes. Existing hour
totals are not recalculated. Employee shift edits can be scheduled for tomorrow
or later. Shift snapshots are immutable; editing a template does not rewrite
an existing assignment. Assign the edited template again for a new effective date.

Corrections support a first visit plus additional IN/OUT sessions. Keep visits
in order without overlaps. Only the final visit can remain open. Approval applies
the proposed session list while retaining raw scans and reviewer history.

Overtime separates calculated and approved minutes. Reviews require a reason,
are appended to history, and become stale if the underlying attendance changes.
Reports include approved overtime separately, including CSV exports.

Exceptions collects missing punches, inferred directions, days over 12 worked
hours and employee numbers with no matching employee. Employee names open detail
pages with recent sessions, leave, corrections and shift assignments. Device
status is enrollment information; it is not a live synchronization guarantee.

Reports use SQL pagination (50 rows), sorting and one saved filter preset per
account. Exports include all matching rows. Backup health is updated only after
a successful database restore comparison and media checksum verification; it
warns after 36 hours without success. Check Windows Task Scheduler on failure.

`tools/test_workflows.php` verifies dated snapshots, split-session corrections,
approval invalidation and SQL report pagination with rollback-only fixtures.
