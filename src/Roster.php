<?php
/**
 * Resolves each employee/day into a single status, combining scans,
 * shift working-days, holidays and leave.
 */
class Roster
{
    /**
     * @param array|null $att attendance row for that day (or null if no scan)
     * @return string present|late|half|absent|leave|holiday|off
     */
    public static function resolve(string $empNo, string $date, ?array $shift, ?array $att): string
    {
        if ($att && !empty($att['check_in'])) {
            return $att['status'];              // they scanned — trust the computed status
        }
        if (Leave::on($empNo, $date))        return 'leave';
        if (Calendar::isHoliday($date))      return 'holiday';
        if (!Shift::isWorkday($shift, $date)) return 'off';
        return 'absent';
    }

    /**
     * Status counts + worked/overtime totals across a date range.
     * @return array{counts:array,worked:int,ot:int,days:int,employees:int}
     */
    public static function summary(string $from, string $to, ?string $empNo = null): array
    {
        $employees = $empNo
            ? Database::q("SELECT emp_no FROM employees WHERE emp_no=?", [$empNo])->fetchAll()
            : Database::q("SELECT emp_no FROM employees WHERE active=1")->fetchAll();

        // Pre-load attendance rows in range.
        $att = [];
        $rows = Database::q(
            "SELECT * FROM attendance WHERE work_date BETWEEN ? AND ?" .
            ($empNo ? " AND emp_no=?" : ""),
            $empNo ? [$from, $to, $empNo] : [$from, $to]
        )->fetchAll();
        foreach ($rows as $r) { $att[$r['emp_no'] . '|' . $r['work_date']] = $r; }

        // Cache each employee's shift once.
        $shiftOf = [];
        foreach ($employees as $e) { $shiftOf[$e['emp_no']] = Shift::forEmployee($e['emp_no']); }

        $counts = ['present'=>0,'late'=>0,'half'=>0,'absent'=>0,'leave'=>0,'holiday'=>0,'off'=>0];
        $worked = 0; $ot = 0; $dayCount = 0;

        $start = new DateTime($from);
        $end   = new DateTime($to);
        $end->modify('+1 day');
        if ((int)$start->diff($end)->days > 400) { // safety cap
            $end = (clone $start)->modify('+400 days');
        }

        for ($d = clone $start; $d < $end; $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            $dayCount++;
            foreach ($employees as $e) {
                $emp = $e['emp_no'];
                $row = $att[$emp . '|' . $date] ?? null;
                $st  = self::resolve($emp, $date, Shift::forEmployee($emp,$date), $row);
                $counts[$st] = ($counts[$st] ?? 0) + 1;
                if ($row) { $worked += (int)$row['work_minutes']; $ot += (int)$row['overtime_minutes']; }
            }
        }

        return ['counts'=>$counts, 'worked'=>$worked, 'ot'=>$ot,
                'days'=>$dayCount, 'employees'=>count($employees)];
    }
}
