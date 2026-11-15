<?php
class Correction
{
    public static function review(int $id, string $decision, int $adminId, string $adminName, string $note): void
    {
        if (!in_array($decision, ['approved','rejected'], true)) throw new InvalidArgumentException('Choose approve or reject.');
        if (strlen(trim($note)) < 5 || strlen($note) > 1000) throw new InvalidArgumentException('Enter a review reason between 5 and 1000 characters.');
        $request = Database::q('SELECT * FROM attendance_corrections WHERE id=?', [$id])->fetch();
        if (!$request) throw new InvalidArgumentException('Correction was not found.');
        if ((int)$request['admin_id'] === $adminId) throw new InvalidArgumentException('A different administrator must review this correction.');
        $lock=Attendance::lockName($request['emp_no']);
        if (!Database::q('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()) throw new RuntimeException('Attendance is busy.');
        $pdo=Database::pdo();
        try {
            $pdo->beginTransaction();
            $request=Database::q('SELECT * FROM attendance_corrections WHERE id=? FOR UPDATE',[$id])->fetch();
            if ($request['approval_status'] !== 'pending') throw new InvalidArgumentException('This correction has already been reviewed.');
            $before=Database::q('SELECT * FROM attendance WHERE emp_no=? AND work_date=?',[$request['emp_no'],$request['work_date']])->fetch();
            Database::q('UPDATE attendance_corrections SET approval_status=?, reviewed_by=?, reviewed_at=NOW(6), review_note=?, previous_json=? WHERE id=?',[$decision,$adminName,trim($note),json_encode($before),$id]);
            if ($decision==='approved') Attendance::updateDay($request['emp_no'],$request['work_date']);
            $pdo->commit();
        } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
        finally { Database::q('SELECT RELEASE_LOCK(?)',[$lock]); }
    }
}
