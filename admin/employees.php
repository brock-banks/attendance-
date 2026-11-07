<?php
$active = 'employees'; $pageTitle = 'Employees';
require_once dirname(__DIR__) . '/includes/header.php';

$msg = '';
// Delete (also removes from device).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $empNo = $_POST['emp_no'] ?? '';
    $row = Database::q("SELECT on_device FROM employees WHERE emp_no=?", [$empNo])->fetch();
    if ($row && $row['on_device']) {
        (new IsapiClient())->deleteUser($empNo);
    }
    Database::q("DELETE FROM employees WHERE emp_no=?", [$empNo]);
    $msg = "Removed employee $empNo.";
}

$rows = Database::q(
    "SELECT e.*, s.name AS shift_name,
            (SELECT COUNT(*) FROM attendance a WHERE a.emp_no=e.emp_no) AS days
     FROM employees e LEFT JOIN shifts s ON s.id=e.shift_id
     ORDER BY e.name"
)->fetchAll();
?>
<h1>Employees</h1>
<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
<p><a class="btn" href="<?= APP_BASE_URL ?>/admin/enroll.php">+ Enroll employee</a></p>

<table class="tbl">
  <thead><tr><th>Emp #</th><th>Name</th><th>Department</th><th>Shift</th><th>Card</th><th>On device</th><th>Days logged</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="8" class="muted">No employees yet. Enroll one to get started.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['emp_no']) ?></td>
      <td><?= e($r['name']) ?></td>
      <td><?= e($r['department']) ?></td>
      <td><?= $r['shift_name'] ? e($r['shift_name']) : '<span class="muted">default</span>' ?></td>
      <td><?= e($r['card_no']) ?></td>
      <td><?= $r['on_device'] ? '<span class="pill ok">yes</span>' : '<span class="pill">no</span>' ?></td>
      <td><?= (int)$r['days'] ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Remove <?= e($r['name']) ?> (and from device)?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="emp_no" value="<?= e($r['emp_no']) ?>">
          <button class="btn sm danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
