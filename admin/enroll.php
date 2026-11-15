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
        $photoPath = null;
        try {
            if (Database::q('SELECT 1 FROM employees WHERE emp_no=?',[$empNo])->fetchColumn()) throw new InvalidArgumentException('This employee number already exists. Open the employee directory to edit their details.');
            if (mb_strlen($empNo)>32 || mb_strlen($name)>128 || mb_strlen($dept)>128 || mb_strlen($card)>64) throw new InvalidArgumentException('One of the details is too long. Check the employee number, name, department and card.');
            if ($shiftId && !Shift::get($shiftId)) throw new InvalidArgumentException('Choose a valid shift.');
            $profile=EmployeeProfile::validate($_POST);
            $photoPath = EmployeePhoto::upload($_FILES['photo'] ?? []);
            Database::q(
                "INSERT INTO employees (emp_no, name, department, card_no, photo_path, shift_id)
                 VALUES (?,?,?,?,?,?)
",
                [$empNo, $name, $dept ?: null, $card ?: null, $photoPath, $shiftId]
            );

            EmployeeProfile::save($empNo,$profile);
            $msg = "Employee saved.";
            if (isset($_POST['sync_device'])) {
            $employee=Database::q('SELECT * FROM employees WHERE emp_no=?',[$empNo])->fetch();
            $msg=EmployeeSync::run($employee)?'Employee saved and synced.':'Employee saved in the app. Device sync failed; retry from the employee directory.';
            }

        } catch (InvalidArgumentException $ex) {
            $err = $ex->getMessage();
        } catch (Throwable $ex) {
            error_log('Employee enrollment: '.$ex->getMessage());
            $err = 'Could not complete the request. Check the employee directory before trying again.';
        }
    }
}
?>
<p><a href="employees.php">← Employee directory</a></p>
<?php if ($msg): ?><div class="alert ok" role="status"><?= e($msg) ?> <a href="employees.php">View employees</a></div><?php endif; ?>
<?php if ($err): ?><div class="alert err" role="alert"><?= e($err) ?></div><?php endif; ?>
<form class="employee-create employee-form" method="post" enctype="multipart/form-data">
<section class="employee-profile">
  <div class="profile-toolbar"><div><h1>Add employee</h1><p class="muted">Create their profile. Only a name and employee number are required.</p></div></div>
  <div class="create-identity">
    <div class="create-photo">
      <div class="create-portrait"><span data-create-initials aria-hidden="true">?</span><img class="employee-photo-preview" data-photo-preview alt="Selected employee photo" hidden></div>
      <label for="employee-photo">Profile photo <span class="muted">Optional</span></label>
      <input id="employee-photo" type="file" name="photo" accept="image/jpeg,image/png" aria-describedby="photo-help" data-photo-input>
      <p id="photo-help" class="muted">JPG or PNG, up to 5 MB.<br>Without a photo, initials appear instead.</p>
      <button type="button" class="btn ghost sm" data-clear-photo hidden>Remove photo</button>
    </div>
    <div class="create-details">
      <h2>Employee details</h2>
      <div class="employee-fields">
        <label>Full name <input name="name" autocomplete="name" required maxlength="128" placeholder="Enter full name" value="<?= e($err ? ($_POST['name']??'') : '') ?>"></label>
        <label>Employee number <input name="emp_no" required maxlength="32" placeholder="e.g. 1001" value="<?= e($err ? ($_POST['emp_no']??'') : '') ?>"></label>
        <label>Department <span class="muted">Optional</span><input name="department" maxlength="128" placeholder="e.g. Administration" list="employee-departments" value="<?= e($err ? ($_POST['department']??'') : '') ?>"></label>
        <label>Shift <select name="shift_id"><option value="">Default shift</option>
        <?php foreach (Shift::all(true) as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $err && (string)($_POST['shift_id']??'')===(string)$s['id']?'selected':'' ?>><?= e($s['name']) ?> · <?= e(substr($s['start_time'],0,5).' – '.substr($s['end_time'],0,5)) ?></option><?php endforeach; ?></select></label>
      </div>
      <datalist id="employee-departments"><?php foreach(Database::q("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department<>'' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN) as $department): ?><option value="<?= e($department) ?>"><?php endforeach; ?></datalist>
      <p class="create-help muted">The employee number links their scans and attendance history. Use the same number as the attendance device.</p>
    </div>
  </div>
</section>
<section class="employee-profile"><?= EmployeeProfile::fields($err?$_POST:[]) ?></section>
<section class="employee-profile create-device">
  <details class="schedule-disclosure" <?= $err && (isset($_POST['sync_device']) || !empty($_POST['card_no'])) ? 'open' : '' ?>>
    <summary>Attendance device <span class="muted">Optional setup</span></summary>
    <div class="create-device-fields">
      <label>Card number <input name="card_no" maxlength="64" placeholder="Enter card number, if used" value="<?= e($err ? ($_POST['card_no']??'') : '') ?>"></label>
      <div><label class="employee-check"><input type="checkbox" name="sync_device" <?= $err && isset($_POST['sync_device'])?'checked':'' ?>> Also register on the attendance device</label>
      <p class="muted">Sends their name, card and photo to the device. Use a clear, front-facing photo for face recognition. You can also sync later from the directory.</p></div>
    </div>
  </details>
</section>
<div class="create-actions"><p class="muted">You can update the profile after saving.</p><a class="btn ghost" href="employees.php">Cancel</a><button class="btn" type="submit">Add employee</button></div>
<?= Auth::field() ?></form>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
