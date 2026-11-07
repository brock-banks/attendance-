<?php
$active = 'enroll'; $pageTitle = 'Enroll';
require_once dirname(__DIR__) . '/includes/header.php';

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empNo = trim($_POST['emp_no'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $dept  = trim($_POST['department'] ?? '');
    $card  = trim($_POST['card_no'] ?? '');
    $shiftId = ($_POST['shift_id'] ?? '') !== '' ? (int)$_POST['shift_id'] : null;

    if ($empNo === '' || $name === '') {
        $err = 'Employee number and name are required.';
    } else {
        // Save face photo locally.
        $photoPath = null;
        if (!empty($_FILES['photo']['tmp_name']) && @getimagesize($_FILES['photo']['tmp_name'])) {
            $fn = 'emp_' . preg_replace('/\W+/', '', $empNo) . '.jpg';
            $dest = UPLOAD_DIR . '/' . $fn;
            move_uploaded_file($_FILES['photo']['tmp_name'], $dest);
            $photoPath = 'storage/photos/' . $fn;
        }

        try {
            Database::q(
                "INSERT INTO employees (emp_no, name, department, card_no, photo_path, shift_id)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE name=VALUES(name), department=VALUES(department),
                    card_no=VALUES(card_no), photo_path=COALESCE(VALUES(photo_path), photo_path),
                    shift_id=VALUES(shift_id)",
                [$empNo, $name, $dept ?: null, $card ?: null, $photoPath, $shiftId]
            );

            // Push to terminal.
            $isapi = new IsapiClient();
            $u = $isapi->addUser($empNo, $name, $card ?: null);
            $onDevice = in_array($u['code'], [200, 201], true);
            $faceNote = '';
            if ($photoPath) {
                $f = $isapi->setFace($empNo, BASE_PATH . '/' . $photoPath);
                $faceNote = in_array($f['code'], [200, 201], true)
                    ? ' Face uploaded.'
                    : ' (Face upload returned HTTP ' . $f['code'] . ' — check photo is a clear frontal JPG.)';
            }
            Database::q("UPDATE employees SET on_device=? WHERE emp_no=?", [$onDevice ? 1 : 0, $empNo]);

            $msg = $onDevice
                ? "Saved and pushed $name to the terminal.$faceNote"
                : "Saved locally, but device returned HTTP {$u['code']}. Check device IP/credentials in config.php.";
        } catch (Throwable $ex) {
            $err = 'Error: ' . $ex->getMessage();
        }
    }
}
?>
<h1>Enroll employee</h1>
<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<form class="panel form" method="post" enctype="multipart/form-data">
  <label>Employee number <input name="emp_no" required placeholder="e.g. 1001"></label>
  <label>Full name <input name="name" required></label>
  <label>Department <input name="department"></label>
  <label>Shift
    <select name="shift_id">
      <option value="">Default shift</option>
      <?php foreach (Shift::all(true) as $s): ?>
        <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e(substr($s['start_time'],0,5)) ?>–<?= e(substr($s['end_time'],0,5)) ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Card number (optional) <input name="card_no"></label>
  <label>Face photo (JPG, clear frontal) <input type="file" name="photo" accept="image/jpeg,image/png"></label>
  <button class="btn" type="submit">Save &amp; push to device</button>
  <p class="muted">The photo is stored locally and uploaded to the terminal's face library. The terminal does the recognition; scans then flow back to this app automatically.</p>
</form>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
