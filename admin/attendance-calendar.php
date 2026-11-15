<?php
require dirname(__DIR__).'/includes/bootstrap.php';
$people=Database::q('SELECT emp_no,name FROM employees ORDER BY name')->fetchAll();$emp=(string)($_GET['emp']??($people[0]['emp_no']??''));$person=Database::q('SELECT * FROM employees WHERE emp_no=?',[$emp])->fetch();
$month=(string)($_GET['month']??date('Y-m'));if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month) || substr($month,0,4)<'1000')$month=date('Y-m');$calendarMonthStart=$month.'-01';$calendarMonthEnd=date('Y-m-t',strtotime($calendarMonthStart));$attendance=[];
if($person)foreach(Database::q('SELECT * FROM attendance WHERE emp_no=? AND work_date BETWEEN ? AND ?',[$emp,$calendarMonthStart,$calendarMonthEnd])->fetchAll() as $row)$attendance[$row['work_date']]=$row;
$profile=$person?EmployeeProfile::get($emp):[];$active='calendar';$pageTitle='Attendance calendar';require BASE_PATH.'/includes/header.php';
?>
<h1>Attendance calendar</h1><form class="filters" method="get"><label>Employee<select name="emp"><?php foreach($people as $p): ?><option value="<?= e($p['emp_no']) ?>" <?= $emp===$p['emp_no']?'selected':'' ?>><?= e($p['name'].' · '.$p['emp_no']) ?></option><?php endforeach; ?></select></label><label>Month<input type="month" name="month" value="<?= e($month) ?>" required></label><button class="btn">Show calendar</button></form>
<?php if(!$person): ?><p>Select an employee to see attendance. <a href="employees.php">Open employee directory</a></p><?php else: ?>
<div class="profile-toolbar"><a class="btn ghost" href="?<?= e(http_build_query(['emp'=>$emp,'month'=>date('Y-m',strtotime($calendarMonthStart.' -1 month'))])) ?>">Previous month</a><h2><?= e(date('F Y',strtotime($calendarMonthStart))) ?></h2><a class="btn ghost" href="?<?= e(http_build_query(['emp'=>$emp,'month'=>date('Y-m',strtotime($calendarMonthStart.' +1 month'))])) ?>">Next month</a></div>
<div class="calendar-employee-summary">
  <a class="employee-identity" href="employee.php?emp=<?= e(rawurlencode($emp)) ?>" aria-label="View profile for <?= e($person['name']) ?>">
    <?= EmployeePhoto::avatar($person) ?><span><?= e($person['name']) ?><small><?= e($emp) ?></small></span>
  </a>
  <div class="calendar-worked"><strong><?= Attendance::fmtMinutes((int)array_sum(array_column($attendance,'work_minutes'))) ?></strong><span>Worked in <?= e(date('F Y',strtotime($calendarMonthStart))) ?></span></div>
</div>
<div class="attendance-calendar"><?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $label): ?><div class="calendar-weekday"><?= $label ?></div><?php endforeach; ?>
<?php for($blank=1;$blank<(int)date('N',strtotime($calendarMonthStart));$blank++): ?><div class="calendar-blank" aria-hidden="true"></div><?php endfor; ?>
<?php for($i=1;$i<=(int)date('t',strtotime($calendarMonthStart));$i++): $date=$month.'-'.str_pad((string)$i,2,'0',STR_PAD_LEFT);$row=$attendance[$date]??null;$holiday=Calendar::on($date);$leave=Leave::on($emp,$date);$shift=Shift::forEmployee($emp,$date);
if($row)$status=$row['check_out']?$row['status']:Report::checkoutState($row);elseif(!empty($profile['joining_date']) && $date<$profile['joining_date'])$status='Before joining';elseif(!empty($profile['archived_at']) && $date>=substr($profile['archived_at'],0,10))$status='Archived';elseif($leave)$status='On leave';elseif($holiday)$status='Holiday';elseif(!Shift::isWorkday($shift,$date))$status='Day off';elseif($date>=date('Y-m-d'))$status='Scheduled';else $status='No scans'; ?>
<article class="calendar-day <?= $date===date('Y-m-d')?'calendar-today':'' ?>"><strong><?= $i ?></strong><?= status_label($status) ?><?php if($holiday): ?><small><?= e($holiday['name']) ?></small><?php endif; ?><?php if($row): ?><a data-attendance-panel href="attendance-detail.php?<?= e(http_build_query(['emp'=>$emp,'day'=>$date])) ?>"><?= Attendance::fmtMinutes((int)$row['work_minutes']) ?> worked</a><?php elseif($leave): ?><small><?= e(Leave::TYPES[$leave['type']]??$leave['type']) ?></small><?php endif; ?></article>
<?php endfor; ?></div><?php endif; ?>
<?php require BASE_PATH.'/includes/footer.php'; ?>
