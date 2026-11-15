<?php
require dirname(__DIR__).'/includes/bootstrap.php';
$items=[];
foreach(Database::q("SELECT c.*,COALESCE(e.name,c.emp_no) name FROM attendance_corrections c LEFT JOIN employees e ON e.emp_no=c.emp_no WHERE c.approval_status='pending'")->fetchAll() as $r)$items[]=['type'=>'corrections','name'=>$r['name'],'day'=>$r['work_date'],'label'=>'Correction request','url'=>'corrections.php?'.http_build_query(['emp'=>$r['emp_no'],'day'=>$r['work_date']]),'action'=>'Review correction'];
foreach(Database::q('SELECT a.*,COALESCE(e.name,a.emp_no) name FROM attendance a LEFT JOIN employees e ON e.emp_no=a.emp_no WHERE a.check_out IS NULL OR a.overtime_minutes>0')->fetchAll() as $r){
 if(!$r['check_out'] && Report::checkoutState($r)==='Missing check-out')$items[]=['type'=>'punches','name'=>$r['name'],'day'=>$r['work_date'],'label'=>'Missing check-out','url'=>'attendance-detail.php?'.http_build_query(['emp'=>$r['emp_no'],'day'=>$r['work_date']]),'action'=>'Review sessions'];
 if($r['overtime_minutes']>0){$review=Overtime::current($r);if(!$review || !hash_equals($review['attendance_hash'],Overtime::fingerprint($r)))$items[]=['type'=>'overtime','name'=>$r['name'],'day'=>$r['work_date'],'label'=>'Pending overtime','url'=>'overtime.php?view=pending&emp='.rawurlencode($r['emp_no']),'action'=>'Review overtime'];}
}
$types=['all'=>'All items','punches'=>'Missing check-outs','corrections'=>'Corrections','overtime'=>'Overtime'];$type=isset($types[$_GET['type']??''])?$_GET['type']:'all';$counts=array_fill_keys(array_keys($types),0);foreach($items as $item){$counts['all']++;$counts[$item['type']]++;}
$items=array_values(array_filter($items,static fn($item)=>$type==='all'||$item['type']===$type));usort($items,static fn($a,$b)=>strcmp($b['day'],$a['day']));$pages=max(1,(int)ceil(count($items)/30));$page=max(1,min($pages,(int)($_GET['page']??1)));$items=array_slice($items,($page-1)*30,30);
$active='inbox';$pageTitle='Review inbox';require BASE_PATH.'/includes/header.php';
?>
<h1>Review inbox</h1><p>Missing check-outs, correction requests and overtime waiting for a decision.</p>
<nav class="schedule-nav" aria-label="Review categories"><?php foreach($types as $key=>$label): ?><a href="?type=<?= $key ?>" <?= $type===$key?'aria-current="page"':'' ?>><?= e($label) ?> (<?= $counts[$key] ?>)</a><?php endforeach; ?></nav>
<?php if(!$items): ?><div class="panel"><h2>All caught up</h2><p>No pending items in this category. New requests will appear here.</p></div><?php else: ?>
<div class="table-scroll"><table class="tbl"><thead><tr><th>Employee</th><th>Date</th><th>Needs attention</th><th>Action</th></tr></thead><tbody><?php foreach($items as $item): ?><tr><td><?= e($item['name']) ?></td><td><?= e($item['day']) ?></td><td><?= status_label($item['label']) ?></td><td><a <?= $item['type']==='punches'?'data-attendance-panel':'' ?> href="<?= e($item['url']) ?>"><?= e($item['action']) ?></a></td></tr><?php endforeach; ?></tbody></table></div>
<nav class="profile-pagination"><?php if($page>1): ?><a class="btn ghost" href="?type=<?= e($type) ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if($page<$pages): ?><a class="btn ghost" href="?type=<?= e($type) ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?></nav><?php endif; ?>
<?php require BASE_PATH.'/includes/footer.php'; ?>
