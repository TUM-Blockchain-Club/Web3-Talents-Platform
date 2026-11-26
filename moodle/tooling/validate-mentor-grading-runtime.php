<?php
/** Isolated grading persistence checks. Never runs against the production DB. */
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->dbname !== 'reviewtest' || empty($CFG->noemailever) || getenv('W3T_SECURITY_TEST') !== '1') {
    throw new RuntimeException('Requires isolated reviewtest with email disabled.');
}
use local_web3talents\local\mentor_grading_service as grading;
function mentor_check(bool $ok,string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL: '.$label); }
    echo 'PASS: '.$label.PHP_EOL;
}
$before=$DB->count_records('local_w3t_room_grade');
$transaction=$DB->start_delegated_transaction();
$rollback=new RuntimeException('Intentional isolated fixture rollback');
try {
    $state=['rooms'=>[
        ['room'=>(object)['id'=>-911],'assignments'=>[['members'=>[(object)['id'=>-921],(object)['id'=>-922]]]]],
        ['room'=>(object)['id'=>-912],'assignments'=>[['members'=>[(object)['id'=>-923]]]]],
    ]];
    grading::save_grades_bulk(-901,-902,-911,[-921=>['grade'=>7,'notes'=>'Clear presentation'], -922=>['grade'=>0,'notes'=>'Needs support']],-931,$state);
    $grades=grading::get_grades_by_user(-901,-902);
    mentor_check(count($grades)===2 && (int)$grades[-921]->grade===7 && (int)$grades[-922]->grade===0
        && $grades[-921]->notes==='Clear presentation','Grades 0 and 7 and feedback persist');
    $rejected=false;
    try { grading::save_grades_bulk(-901,-902,-911,[-921=>['grade'=>3],-922=>['grade'=>8]],-931,$state); }
    catch(moodle_exception $e) {$rejected=$e->errorcode==='error_invalid_room_grade';}
    $grades=grading::get_grades_by_user(-901,-902);
    mentor_check($rejected && (int)$grades[-921]->grade===7,'Invalid bulk grade rejects the whole request');
    $rejected=false;
    try { grading::save_grade(-901,-902,-911,-923,5,'',-931,$state); }
    catch(moodle_exception $e) {$rejected=$e->errorcode==='error_grade_student_not_in_room';}
    mentor_check($rejected,'Students outside the assigned room are rejected');
    $rooms=grading::rooms_for_mentor($state,[-911=>(object)['mentorid'=>-931],-912=>(object)['mentorid'=>-932]],-931);
    mentor_check(count($rooms)===1 && (int)$rooms[0]['room']->id===-911,'Mentor view contains only their assigned room');
    grading::save_grade(-901,-902,-911,-921,6,'Updated feedback',-931,$state);
    $grades=grading::get_grades_by_user(-901,-902);
    mentor_check(count($grades)===2 && (int)$grades[-921]->grade===6 && $grades[-921]->notes==='Updated feedback','Updating a grade does not duplicate it');
    grading::save_grade(-901,-902,-911,-921,null,'',-931,$state);
    mentor_check(count(grading::get_grades_by_user(-901,-902))===1,'Not graded clears a previous grade');
} finally {
    try {$transaction->rollback($rollback);} catch(RuntimeException $e) {if ($e!==$rollback) {throw $e;}}
}
mentor_check($before===$DB->count_records('local_w3t_room_grade'),'All synthetic grade changes rolled back');
