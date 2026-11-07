<?php
$active = 'leave'; $pageTitle = 'Leave';
require_once dirname(__DIR__) . '/includes/header.php';

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $emp   = trim($_POST['emp_no'] ?? '');
        $start = $_POST['start_date'] ?? '';
        $end   = $_POST['end_date'] ?? '';
        $type  = $_POST['type'] ?? 'paid';
        $note  = trim($_POST['note'] ?? '');
        if ($emp === '' || !$start || !$end) {
            $err = 'Employee, start and end dates are required.';
        } else {
            Leave::add($emp, $start, $end, $type, $note);
            $msg = 'Leave recorded.';
        }
    }
    if ($action === 'delete') {
        Leave::delete((int)$_POST['id']);
        $msg = 'Leave removed.';
    }
}

$employees = Database::q("SELECT emp_no, name FROM employees WHERE active=1 ORDER BY name")->fetchAll();
$leaves = Leave::recent();
?>
<h1>Leave <small>Record time off so it isn't counted as absent</small></h1>
<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<div class="panel">
  <h2>Record leave</h2>
  <?php if (!$employees): ?>
    <p class="muted">Add employees first.</p>
  <?php else: ?>
  <form method="post" class="filters">
    <input type="hidden" name="action" value="add">
    <label>Employee
      <select name="emp_no" required>
        <?php foreach ($employees as $e): ?>
          <option value="<?= e($e['emp_no']) ?>"><?= e($e['name']) ?> (<?= e($e['emp_no']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>From <input type="date" name="start_date" required></label>
    <label>To <input type="date" name="end_date" required></label>
    <label>Type
      <select name="type">
        <?php foreach (Leave::TYPES as $k => $v): ?>
          <option value="<?= e($k) ?>"><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Note <input name="note" placeholder="optional"></label>
    <button class="btn">Add leave</button>
  </form>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:16px">
  <h2>Leave records</h2>
  <table class="tbl">
    <thead><tr><th>Employee</th><th>From</th><th>To</th><th>Days</th><th>Type</th><th>Note</th><th></th></tr></thead>
    <tbody>
    <?php if (!$leaves): ?><tr><td colspan="7" class="muted">No leave recorded.</td></tr><?php endif; ?>
    <?php foreach ($leaves as $l):
          $days = (int)((strtotime($l['end_date']) - strtotime($l['start_date'])) / 86400) + 1; ?>
      <tr>
        <td><?= e($l['name']) ?></td>
        <td><?= e($l['start_date']) ?></td>
        <td><?= e($l['end_date']) ?></td>
        <td><?= $days ?></td>
        <td><span class="pill"><?= e(Leave::TYPES[$l['type']] ?? $l['type']) ?></span></td>
        <td class="muted"><?= e($l['note']) ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Remove this leave record?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
            <button class="btn sm danger">×</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
