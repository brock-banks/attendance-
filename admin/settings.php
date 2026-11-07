<?php
$active = 'settings'; $pageTitle = 'Settings';
require_once dirname(__DIR__) . '/includes/header.php';

$msg = ''; $err = '';
$DOW = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; // index = 0..6

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_shift') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $start = $_POST['start_time'] ?? '';
        $end   = $_POST['end_time'] ?? '';
        $grace = max(0, (int)($_POST['grace_minutes'] ?? 0));
        $break = max(0, (int)($_POST['break_minutes'] ?? 0));
        $half  = max(0, (int)($_POST['half_day_minutes'] ?? 0));
        $days  = implode(',', array_map('intval', $_POST['workdays'] ?? []));
        $def   = isset($_POST['is_default']) ? 1 : 0;

        if ($name === '' || !$start || !$end) {
            $err = 'Name, start and end time are required.';
        } else {
            if ($def) { Database::q("UPDATE shifts SET is_default=0"); }
            if ($id) {
                Database::q("UPDATE shifts SET name=?,start_time=?,end_time=?,grace_minutes=?,break_minutes=?,half_day_minutes=?,workdays=?,is_default=? WHERE id=?",
                    [$name,$start,$end,$grace,$break,$half,$days,$def,$id]);
            } else {
                Database::q("INSERT INTO shifts (name,start_time,end_time,grace_minutes,break_minutes,half_day_minutes,workdays,is_default) VALUES (?,?,?,?,?,?,?,?)",
                    [$name,$start,$end,$grace,$break,$half,$days,$def]);
            }
            // Guarantee at least one default.
            if (!Database::q("SELECT COUNT(*) c FROM shifts WHERE is_default=1")->fetch()['c']) {
                Database::q("UPDATE shifts SET is_default=1 ORDER BY id LIMIT 1");
            }
            $msg = 'Shift saved.';
        }
    }

    if ($action === 'delete_shift') {
        $id = (int)$_POST['id'];
        Database::q("UPDATE employees SET shift_id=NULL WHERE shift_id=?", [$id]);
        Database::q("DELETE FROM shifts WHERE id=?", [$id]);
        if (!Database::q("SELECT COUNT(*) c FROM shifts WHERE is_default=1")->fetch()['c']) {
            Database::q("UPDATE shifts SET is_default=1 ORDER BY id LIMIT 1");
        }
        $msg = 'Shift deleted.';
    }

    if ($action === 'assign') {
        foreach ($_POST['shift'] ?? [] as $empNo => $sid) {
            $sid = $sid === '' ? null : (int)$sid;
            Database::q("UPDATE employees SET shift_id=? WHERE emp_no=?", [$sid, $empNo]);
        }
        $msg = 'Shift assignments saved. Recompute to apply to past records.';
    }

    if ($action === 'recompute') {
        $n = Attendance::recomputeAll();
        $msg = "Recomputed attendance for $n shift-days.";
    }

    if ($action === 'add_holiday') {
        $hd = $_POST['holiday_date'] ?? '';
        $hn = trim($_POST['holiday_name'] ?? '');
        if ($hd && $hn) { Calendar::add($hd, $hn, isset($_POST['recurring'])); $msg = 'Holiday added.'; }
        else { $err = 'Holiday needs a date and a name.'; }
    }

    if ($action === 'delete_holiday') {
        Calendar::delete((int)$_POST['id']);
        $msg = 'Holiday removed.';
    }

    if ($action === 'save_alerts' || $action === 'test_email') {
        foreach (['alerts_enabled','alert_late','alert_absent','alert_summary'] as $k) {
            Shift::putSetting($k, isset($_POST[$k]) ? '1' : '0');
        }
        Shift::putSetting('alert_recipient', trim($_POST['alert_recipient'] ?? ''));
        Shift::putSetting('absent_after_minutes', (string)max(0, (int)($_POST['absent_after_minutes'] ?? 60)));
        $st = $_POST['summary_time'] ?? '18:00';
        Shift::putSetting('summary_time', preg_match('/^\d{2}:\d{2}$/', $st) ? $st : '18:00');

        // SMTP (password kept if left blank)
        Shift::putSetting('smtp_host', trim($_POST['smtp_host'] ?? ''));
        Shift::putSetting('smtp_port', (string)max(1, (int)($_POST['smtp_port'] ?? 587)));
        $sec = $_POST['smtp_secure'] ?? 'tls';
        Shift::putSetting('smtp_secure', in_array($sec, ['tls','ssl',''], true) ? $sec : 'tls');
        Shift::putSetting('smtp_user', trim($_POST['smtp_user'] ?? ''));
        if (($_POST['smtp_pass'] ?? '') !== '') { Shift::putSetting('smtp_pass', $_POST['smtp_pass']); }
        Shift::putSetting('smtp_from', trim($_POST['smtp_from'] ?? ''));
        Shift::putSetting('smtp_from_name', trim($_POST['smtp_from_name'] ?? '') ?: 'Attendance');

        if ($action === 'test_email') {
            $to = trim($_POST['alert_recipient'] ?? '') ?: Notifier::recipient();
            if ($to === '') { $err = 'Enter a recipient email first.'; }
            else {
                $e2 = null;
                if (Notifier::test($to, $e2)) { $msg = "Test email sent to $to."; }
                else { $err = 'Test email failed: ' . $e2; }
            }
        } else {
            $msg = 'Alert settings saved.';
        }
    }
}

$shifts = Shift::all();
$holidays = Calendar::all();
$alert = [
    'enabled'   => Shift::setting('alerts_enabled', '0'),
    'late'      => Shift::setting('alert_late', '1'),
    'absent'    => Shift::setting('alert_absent', '1'),
    'summary'   => Shift::setting('alert_summary', '1'),
    'recipient' => Shift::setting('alert_recipient', ''),
    'after'     => Shift::setting('absent_after_minutes', '60'),
    'stime'     => Shift::setting('summary_time', '18:00'),
    'smtp_host' => Shift::setting('smtp_host', ''),
    'smtp_port' => Shift::setting('smtp_port', '587'),
    'smtp_secure' => Shift::setting('smtp_secure', 'tls'),
    'smtp_user' => Shift::setting('smtp_user', ''),
    'smtp_from' => Shift::setting('smtp_from', ''),
    'smtp_from_name' => Shift::setting('smtp_from_name', 'Attendance'),
    'has_pass'  => Shift::setting('smtp_pass', '') !== '',
];
$employees = Database::q("SELECT emp_no, name, shift_id FROM employees ORDER BY name")->fetchAll();
$edit = null;
if (isset($_GET['edit'])) { $edit = Shift::get((int)$_GET['edit']); }
$editDays = $edit ? array_map('intval', explode(',', $edit['workdays'])) : [1,2,3,4,5];
?>
<h1>Settings <small>Shifts &amp; attendance rules</small></h1>
<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<section class="grid2" style="align-items:start">
  <!-- Shift list -->
  <div class="panel">
    <h2>Shifts</h2>
    <table class="tbl">
      <thead><tr><th>Name</th><th>Hours</th><th>Grace</th><th>Break</th><th>Days</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($shifts as $s):
            $days = array_map('intval', explode(',', $s['workdays'])); ?>
        <tr>
          <td><?= e($s['name']) ?> <?= $s['is_default'] ? '<span class="pill ok">default</span>' : '' ?></td>
          <td><?= e(substr($s['start_time'],0,5)) ?>–<?= e(substr($s['end_time'],0,5)) ?><?= Shift::crossesMidnight($s) ? ' <span class="pill">+1</span>' : '' ?></td>
          <td><?= (int)$s['grace_minutes'] ?>m</td>
          <td><?= (int)$s['break_minutes'] ?>m</td>
          <td><?= e(implode(' ', array_map(fn($d)=>$DOW[$d], $days))) ?></td>
          <td style="white-space:nowrap">
            <a class="btn sm ghost" href="?edit=<?= (int)$s['id'] ?>">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete shift <?= e($s['name']) ?>?')">
              <input type="hidden" name="action" value="delete_shift">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="btn sm danger">×</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Add / edit shift -->
  <div class="panel">
    <h2><?= $edit ? 'Edit shift' : 'Add shift' ?></h2>
    <form method="post" class="form" style="max-width:none">
      <input type="hidden" name="action" value="save_shift">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <label>Name <input name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Morning"></label>
      <div style="display:flex;gap:12px">
        <label style="flex:1">Start <input type="time" name="start_time" required value="<?= e(substr($edit['start_time'] ?? '09:00',0,5)) ?>"></label>
        <label style="flex:1">End <input type="time" name="end_time" required value="<?= e(substr($edit['end_time'] ?? '17:00',0,5)) ?>"></label>
      </div>
      <div style="display:flex;gap:12px">
        <label style="flex:1">Grace (min) <input type="number" name="grace_minutes" value="<?= (int)($edit['grace_minutes'] ?? 10) ?>"></label>
        <label style="flex:1">Break (min) <input type="number" name="break_minutes" value="<?= (int)($edit['break_minutes'] ?? 60) ?>"></label>
        <label style="flex:1">Half-day under (min) <input type="number" name="half_day_minutes" value="<?= (int)($edit['half_day_minutes'] ?? 240) ?>"></label>
      </div>
      <div>
        <div class="muted" style="font-size:13px;margin-bottom:6px">Working days</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php foreach ($DOW as $i => $d): ?>
            <label style="flex-direction:row;align-items:center;gap:6px;font-weight:400">
              <input type="checkbox" name="workdays[]" value="<?= $i ?>" style="height:auto" <?= in_array($i,$editDays,true)?'checked':'' ?>> <?= $d ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400">
        <input type="checkbox" name="is_default" style="height:auto" <?= !empty($edit['is_default'])?'checked':'' ?>> Default shift (new employees)
      </label>
      <div style="display:flex;gap:10px">
        <button class="btn" type="submit"><?= $edit ? 'Update shift' : 'Add shift' ?></button>
        <?php if ($edit): ?><a class="btn ghost" href="<?= APP_BASE_URL ?>/admin/settings.php">Cancel</a><?php endif; ?>
      </div>
      <p class="muted" style="font-size:12px">Tip: for an overnight shift set End earlier than Start (e.g. 22:00 → 06:00).</p>
    </form>
  </div>
</section>

<!-- Holidays -->
<div class="panel" style="margin-top:16px">
  <h2>Holidays</h2>
  <form method="post" class="filters" style="margin-bottom:14px">
    <input type="hidden" name="action" value="add_holiday">
    <label>Date <input type="date" name="holiday_date" required></label>
    <label>Name <input name="holiday_name" required placeholder="e.g. National Day"></label>
    <label style="flex-direction:row;align-items:center;gap:6px;font-weight:400;align-self:center">
      <input type="checkbox" name="recurring" style="height:auto"> Repeats yearly</label>
    <button class="btn">Add holiday</button>
  </form>
  <table class="tbl">
    <thead><tr><th>Date</th><th>Name</th><th>Repeats</th><th></th></tr></thead>
    <tbody>
    <?php if (!$holidays): ?><tr><td colspan="4" class="muted">No holidays added yet.</td></tr><?php endif; ?>
    <?php foreach ($holidays as $h): ?>
      <tr>
        <td><?= e($h['holiday_date']) ?></td>
        <td><?= e($h['name']) ?></td>
        <td><?= $h['recurring'] ? '<span class="pill">yearly</span>' : '—' ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Remove this holiday?')">
            <input type="hidden" name="action" value="delete_holiday">
            <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
            <button class="btn sm danger">×</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted" style="font-size:12px">On a holiday, non-working employees aren't marked absent. "Repeats yearly" matches the month &amp; day every year.</p>
</div>

<!-- Email alerts -->
<div class="panel" style="margin-top:16px">
  <h2>Email alerts</h2>
  <?php if (!Mailer::configured()): ?>
    <div class="alert info">Fill in the <b>SMTP server</b> fields below and save to enable email alerts.</div>
  <?php endif; ?>
  <form method="post" class="form" style="max-width:none">
    <input type="hidden" name="action" value="save_alerts">
    <label style="flex-direction:row;align-items:center;gap:8px;font-weight:600">
      <input type="checkbox" name="alerts_enabled" style="height:auto" <?= $alert['enabled']==='1'?'checked':'' ?>> Enable email alerts
    </label>
    <div style="display:flex;gap:20px;flex-wrap:wrap">
      <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400"><input type="checkbox" name="alert_late" style="height:auto" <?= $alert['late']==='1'?'checked':'' ?>> Late arrivals</label>
      <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400"><input type="checkbox" name="alert_absent" style="height:auto" <?= $alert['absent']==='1'?'checked':'' ?>> Absences</label>
      <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400"><input type="checkbox" name="alert_summary" style="height:auto" <?= $alert['summary']==='1'?'checked':'' ?>> Daily summary</label>
    </div>
    <label>Send alerts to <input type="email" name="alert_recipient" value="<?= e($alert['recipient']) ?>" placeholder="you@example.com"></label>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <label style="flex:1;min-width:180px">Mark absent after (min past shift start) <input type="number" name="absent_after_minutes" value="<?= (int)$alert['after'] ?>"></label>
      <label style="flex:1;min-width:180px">Daily summary time <input type="time" name="summary_time" value="<?= e($alert['stime']) ?>"></label>
    </div>

    <hr style="border:0;border-top:1px solid var(--divider);width:100%;margin:4px 0">
    <div class="muted" style="font-weight:600;font-size:13px">SMTP server</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <label style="flex:2;min-width:200px">Host <input name="smtp_host" value="<?= e($alert['smtp_host']) ?>" placeholder="smtp.gmail.com"></label>
      <label style="flex:1;min-width:90px">Port <input type="number" name="smtp_port" value="<?= (int)$alert['smtp_port'] ?>"></label>
      <label style="flex:1;min-width:120px">Security
        <select name="smtp_secure">
          <option value="tls" <?= $alert['smtp_secure']==='tls'?'selected':'' ?>>TLS (587)</option>
          <option value="ssl" <?= $alert['smtp_secure']==='ssl'?'selected':'' ?>>SSL (465)</option>
          <option value="" <?= $alert['smtp_secure']===''?'selected':'' ?>>None</option>
        </select>
      </label>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <label style="flex:1;min-width:200px">Username <input name="smtp_user" value="<?= e($alert['smtp_user']) ?>" placeholder="you@gmail.com"></label>
      <label style="flex:1;min-width:200px">Password <input type="password" name="smtp_pass" placeholder="<?= $alert['has_pass']?'saved — leave blank to keep':'app password' ?>"></label>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <label style="flex:1;min-width:200px">From address <input name="smtp_from" value="<?= e($alert['smtp_from']) ?>" placeholder="(defaults to username)"></label>
      <label style="flex:1;min-width:200px">From name <input name="smtp_from_name" value="<?= e($alert['smtp_from_name']) ?>"></label>
    </div>
    <p class="muted" style="font-size:12px">Gmail: host <code>smtp.gmail.com</code>, TLS port 587, and a Google <b>App Password</b> (not your normal password — needs 2-Step Verification). Outlook: <code>smtp-mail.outlook.com</code>, TLS 587.</p>

    <div style="display:flex;gap:10px">
      <button class="btn" type="submit">Save alert settings</button>
      <button class="btn ghost" type="submit" name="action" value="test_email">Send test email</button>
    </div>
    <p class="muted" style="font-size:12px">Alerts are sent by <code>cron.php</code> — schedule it to run every ~10 minutes (Windows Task Scheduler). Each alert goes out once per day.</p>
  </form>
</div>

<!-- Assign shifts -->
<div class="panel" style="margin-top:16px">
  <h2>Assign shifts to employees</h2>
  <?php if (!$employees): ?>
    <p class="muted">No employees yet.</p>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="action" value="assign">
    <table class="tbl">
      <thead><tr><th>Emp #</th><th>Name</th><th>Shift</th></tr></thead>
      <tbody>
      <?php foreach ($employees as $emp): ?>
        <tr>
          <td><?= e($emp['emp_no']) ?></td>
          <td><?= e($emp['name']) ?></td>
          <td>
            <select name="shift[<?= e($emp['emp_no']) ?>]">
              <option value="">— default —</option>
              <?php foreach ($shifts as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (int)$emp['shift_id']===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="margin-top:14px"><button class="btn">Save assignments</button></div>
  </form>
  <?php endif; ?>
</div>

<!-- Recompute -->
<div class="panel" style="margin-top:16px">
  <h2>Recompute attendance</h2>
  <p class="muted">Rebuild all attendance from raw scans using the current shift rules. Run this after changing shifts or assignments.</p>
  <form method="post" onsubmit="return confirm('Rebuild all attendance from scans now?')">
    <input type="hidden" name="action" value="recompute">
    <button class="btn ghost">Recompute now</button>
  </form>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
