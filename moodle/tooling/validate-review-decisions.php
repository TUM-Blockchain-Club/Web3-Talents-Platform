<?php
// In-memory fixtures only. No Moodle config, real users, network or email.
namespace core\lock {
    class lock { public function release() {} }
    class lock_config {
        public static function get_lock_factory($name) { return new class {
            public function get_lock($key, $timeout) { return new lock(); }
        }; }
    }
}
namespace {
define('MOODLE_INTERNAL', true);
define('MUST_EXIST', 1);
class context {}
class moodle_exception extends \RuntimeException { public function __construct($key, $component = '') { parent::__construct($key); } }
class invalid_parameter_exception extends \RuntimeException {}
function require_login() {}
function isguestuser() { return false; }
function has_capability($cap, $context, $user = null) { global $USER; return ($user ?? $USER)->allowed ?? false; }
function require_capability($cap, $context) { if (!has_capability($cap, $context)) { throw new moodle_exception('nopermissions'); } }
function local_web3talents_admin_context() { return new context(); }
// context() requires lib.php; using this already-loaded file keeps the fixture isolated.
$CFG = (object)['dirroot'=>__DIR__ . '/fixtures/review'];
$USER = (object)['id'=>1, 'allowed'=>true];
$DB = new class {
    public array $tables = [];
    public function get_records($table, $where) {
        return array_filter($this->tables[$table] ?? [], function($row) use ($where) {
            foreach ($where as $k=>$v) { if (($row->$k ?? null) != $v) { return false; } } return true;
        });
    }
    public function get_record($table, $where, ...$args) { $rows = $this->get_records($table, $where); return $rows ? clone reset($rows) : false; }
    public function insert_record($table, $record) {
        $record = clone $record; $record->id = count($this->tables[$table] ?? []) + 1;
        $this->tables[$table][$record->id] = $record; return $record->id;
    }
    public function update_record($table, $record) {
        $this->tables[$table][$record->id] = (object)array_merge((array)$this->tables[$table][$record->id], (array)$record);
    }
    public function delete_records($table, $where) { foreach ($this->get_records($table, $where) as $id=>$row) { unset($this->tables[$table][$id]); } }
    public function start_delegated_transaction() { return new class($this) {
        private array $before;
        public function __construct(private object $db) { $this->before = unserialize(serialize($db->tables)); }
        public function allow_commit() {}
        public function rollback($e) { $this->db->tables = $this->before; throw $e; }
    }; }
};
require(__DIR__ . '/../plugins/local_web3talents/classes/local/application_review_service.php');
use local_web3talents\local\application_review_service as reviews;
function check($ok, $label) { if (!$ok) { throw new \RuntimeException($label); } echo "PASS: $label\n"; }
function blocked($fn, $label) { try { $fn(); } catch (moodle_exception $e) { check(true, $label); return; } throw new \RuntimeException($label); }
$DB->tables['user'] = [1=>(object)['id'=>1,'allowed'=>true,'deleted'=>0,'suspended'=>0]];
$DB->tables['local_web3talents_app'] = [1=>(object)['id'=>1,'email'=>'synthetic@example.invalid','status'=>'pending','userid'=>0]];
reviews::submit(1, 'reject', 'Discuss this');
check($DB->tables['local_web3talents_app'][1]->status === 'discussion' && reviews::summary(1)['objections'] === 1, 'Legacy objections remain discussion flags');
blocked(fn()=>reviews::submit(1, 'decline'), 'Rejection requires a reason');
reviews::submit(1, 'decline', 'Final decision');
check($DB->tables['local_web3talents_app'][1]->status === 'rejected', 'Final rejection is separate');
reviews::submit(1, 'comment', 'Additional note');
check($DB->tables['local_web3talents_app'][1]->status === 'rejected', 'Comments cannot reopen rejected applications');
blocked(fn()=>reviews::submit(1, 'approve'), 'Rejected application cannot be approved without reopening');
blocked(fn()=>reviews::submit(1, 'withdraw'), 'Withdrawal cannot undo final rejection');
blocked(fn()=>reviews::submit(1, 'reopen'), 'Reopening requires a reason');
$history = count($DB->tables['local_w3t_app_comment']);
reviews::submit(1, 'reopen', 'Reconsider');
check($DB->tables['local_web3talents_app'][1]->status === 'pending' && reviews::summary(1)['approvals'] === 0 && reviews::summary(1)['objections'] === 0, 'Reopening resets current reviews');
check(count($DB->tables['local_w3t_app_comment']) === $history + 1, 'Decision history preserved');
blocked(fn()=>reviews::submit(1, 'reopen', 'Again'), 'Cannot reopen an open application');
$USER->allowed = false;
blocked(fn()=>reviews::submit(1, 'decline', 'No'), 'Unauthorised rejection blocked');
$USER->allowed = true;
$DB->tables['local_web3talents_app'][1]->userid = 7;
blocked(fn()=>reviews::submit(1, 'decline', 'No'), 'Existing student account cannot be rejected through application review');
}
