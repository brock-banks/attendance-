<?php
$active='exceptions';$pageTitle='Attendance exceptions';require dirname(__DIR__).'/includes/header.php';
$page=max(1,(int)($_GET['page']??1));$offset=($page-1)*50;
$rows=Database::q('SELECT a.*,COALESCE(e.name,a.emp_no) name FROM attendance a LEFT JOIN employees e ON e.emp_no=a.emp_no WHERE a.review_required=1 OR a.check_out IS NULL OR a.work_minutes>720 ORDER BY a.work_date DESC LIMIT 50 OFFSET '.$offset)->fetchAll();
?>
<h1>Attendance exceptions</h1><p>Review missing punches, inferred directions and days over 12 worked hours. Thresholds flag records for review; they do not reduce worked time.</p>
<div class="table-scroll"><table class="tbl"><thead><tr><th>Employee</th><th>Date</th><th>Needs attention</th><th>Action</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="4">No exceptions on this page.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['work_date']) ?></td><td><?= !empty($r['review_required'])?status_label('Review scan directions').' ':'' ?><?= (int)$r['work_minutes']>720?status_label('Long work day').' ':'' ?><?= !$r['check_out']?status_label(Report::checkoutState($r)):'' ?></td><td><a href="corrections.php?<?= e(http_build_query(['emp'=>$r['emp_no'],'day'=>$r['work_date']])) ?>">Review sessions</a></td></tr><?php endforeach; ?>
</tbody></table></div><nav class="quick-filters"><?php if($page>1): ?><a class="btn ghost" href="?page=<?= $page-1 ?>">Previous</a><?php endif; ?><?php if(count($rows)===50): ?><a class="btn ghost" href="?page=<?= $page+1 ?>">Next</a><?php endif; ?></nav>
<h2>Unmatched employee scans</h2><p>Latest 50 scans with an employee number that has no employee record.</p>
<?php foreach(Database::q("SELECT ev.emp_no,MAX(ev.event_time) last_seen,COUNT(*) total FROM events ev LEFT JOIN employees e ON e.emp_no=ev.emp_no WHERE ev.emp_no IS NOT NULL AND ev.emp_no<>'' AND e.id IS NULL GROUP BY ev.emp_no ORDER BY last_seen DESC LIMIT 50")->fetchAll() as $unknown): ?><p><?= e($unknown['emp_no']) ?> · <?= e($unknown['last_seen']) ?> · <?= (int)$unknown['total'] ?> scans <?php if(Auth::role()!=='viewer'): ?><a href="enroll.php">Enroll employee</a><?php endif; ?></p><?php endforeach; ?>
<?php require BASE_PATH.'/includes/footer.php'; ?>
