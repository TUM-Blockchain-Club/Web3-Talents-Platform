<?php
// Standalone tests: no Moodle config, credentials, network, database or email access.
namespace local_web3talents\local {
    class application_review_service {
        public static int $released = 0;
        public static function lock(string $key): object {
            if ($key !== 'tally-import') { throw new \RuntimeException('Wrong lock namespace'); }
            return new class {
                public function release(): void { application_review_service::$released++; }
            };
        }
    }
    class applicant_service {
        public static int $writes = 0;
        public static function upsert_applicant(object $row, string $source, int $actor): object {
            global $DB;
            if ($source !== 'tally' || $actor !== 0 || isset($row->status) || isset($row->userid)) {
                throw new \RuntimeException('Unsafe ingestion parameters');
            }
            self::$writes++;
            $row->status = 'pending';
            $row->wasinserted = true;
            $DB->applications[] = $row;
            return $row;
        }
    }
}
namespace {
    define('MOODLE_INTERNAL', true);
    require(__DIR__ . '/../plugins/local_web3talents/classes/local/tally_webhook.php');
    use local_web3talents\local\tally_webhook as hook;
    function check(bool $ok, string $name): void {
        if (!$ok) { throw new \RuntimeException('FAIL: ' . $name); }
        echo 'PASS: ' . $name . PHP_EOL;
    }
    function rejected(callable $fn, int $code, string $name): void {
        try { $fn(); } catch (\Throwable $e) {
            check($e->getCode() === $code, $name);
            return;
        }
        throw new \RuntimeException('FAIL: ' . $name);
    }
    $config = (object)['tally_enabled'=>1, 'tally_secret'=>str_repeat('fixture-only-', 4),
        'tally_formid'=>'fixtureForm', 'tally_cohort'=>'fundamentals'];
    $event = ['eventId'=>'fixtureEvent', 'eventType'=>'FORM_RESPONSE', 'data'=>['formId'=>'fixtureForm', 'submissionId'=>'fixtureSubmission',
        'fields'=>[
            ['key'=>'first', 'label'=>'First Name:', 'value'=>'Test'],
            ['key'=>'last', 'label'=>'Last Name: ', 'value'=>'Applicant'],
            ['key'=>'email', 'label'=>'Email: ', 'value'=>'TEST@example.invalid'],
            ['key'=>'motivation', 'label'=>'Motivation', 'value'=>"Line 1\n<script>not code</script>"],
            ['key'=>'choice', 'label'=>'Choice', 'value'=>['id2'], 'options'=>[['id'=>'id2', 'text'=>'Option two']]],
            ['key'=>'consent', 'label'=>'Consent', 'value'=>false],
            ['key'=>'empty', 'label'=>'Optional', 'value'=>null],
        ]]];
    $body = json_encode($event);
    $sign = fn($raw) => base64_encode(hash_hmac('sha256', $raw, $config->tally_secret, true));
    hook::authenticate($body, $sign($body), $config);
    check(true, 'valid signature');
    rejected(fn()=>hook::authenticate($body . ' ', $sign($body), $config), 401, 'tampered raw body');
    rejected(fn()=>hook::authenticate($body, '', $config), 401, 'missing signature');
    rejected(fn()=>hook::authenticate($body, $sign($body), (object)[]), 503, 'disabled/unconfigured fails closed');
    rejected(fn()=>hook::authenticate(str_repeat('x', hook::MAX_BYTES + 1), '', $config), 413, 'bounded payload');
    rejected(fn()=>hook::parse('{', $config), 400, 'malformed JSON');
    $wrong = $event; $wrong['data']['formId'] = 'another';
    rejected(fn()=>hook::parse(json_encode($wrong), $config), 403, 'other form blocked');
    $wrong = $event; $wrong['eventType'] = 'OTHER';
    rejected(fn()=>hook::parse(json_encode($wrong), $config), 400, 'other event blocked');
    $replay = $event; unset($replay['eventType']);
    check(hook::parse(json_encode($replay), $config)['submissionid'] === 'fixtureSubmission',
        'signed dashboard replay format without eventType');
    $wrong = $event; unset($wrong['eventId']);
    rejected(fn()=>hook::parse(json_encode($wrong), $config), 400, 'unidentified event blocked');
    $wrong = $event; $wrong['eventType'] = null;
    rejected(fn()=>hook::parse(json_encode($wrong), $config), 400, 'explicit null event type blocked');
    $row = hook::parse($body, $config);
    $answers = json_decode($row['responses'], true);
    check($row['email'] === 'test@example.invalid' && $row['cohortid'] === 'fundamentals', 'identity mapping');
    check(str_contains($answers[4]['answer'], '<script>') && str_contains($answers[4]['answer'], "\n"), 'answers preserved as data');
    check($answers[5]['answer'] === '["Option two"]' && $answers[6]['answer'] === 'No' && $answers[7]['answer'] === '', 'choices and optional answers');
    $wrong = $event; $wrong['data']['fields'][] = ['key'=>'extra', 'label'=>'Email', 'value'=>'other@example.invalid'];
    rejected(fn()=>hook::parse(json_encode($wrong), $config), 400, 'ambiguous email blocked');
    $wrong = $event; $wrong['data']['fields'][0]['value'] = '';
    rejected(fn()=>hook::parse(json_encode($wrong), $config), 400, 'missing name blocked');
    $wrong = $event; $wrong['data']['fields'][2]['value'] = 'invalid';
    rejected(fn()=>hook::parse(json_encode($wrong), $config), 400, 'invalid email blocked');
    $custom = clone $config; $custom->tally_firstname_key = 'first';
    $changed = $event; $changed['data']['fields'][0]['label'] = 'What should we call you?';
    check(hook::parse(json_encode($changed), $custom)['firstname'] === 'Test', 'stable key mapping');
    $event['status'] = 'accepted'; $event['data']['userid'] = 2;
    check(!isset(hook::parse(json_encode($event), $config)['status']), 'untrusted approval/user fields discarded');

    $DB = new class {
        public array $applications = [];
        public array $users = [];
        public bool $fail = false;
        public function get_record(string $table, array $where) {
            foreach ($this->applications as $row) {
                if (array_diff_assoc($where, (array)$row) === []) { return $row; }
            }
            return false;
        }
        public function record_exists(string $table, array $where): bool {
            if ($this->fail) { throw new \RuntimeException('Simulated database failure'); }
            return in_array($where['email'], $this->users, true);
        }
        public function start_delegated_transaction(): object {
            return new class {
                public function allow_commit(): void {}
                public function rollback(\Throwable $e): void { throw $e; }
            };
        }
    };
    check(hook::receive($body, $sign($body), $config) === 'created', 'new submission ingestion');
    $DB->applications[0]->status = 'discussion';
    check(hook::receive($body, $sign($body), $config) === 'duplicate' && count($DB->applications) === 1,
        'retry idempotency');
    $changed = $event; $changed['data']['fields'][0]['value'] = 'Changed';
    $raw = json_encode($changed);
    check(hook::receive($raw, $sign($raw), $config) === 'duplicate' &&
        $DB->applications[0]->firstname === 'Test' && $DB->applications[0]->status === 'discussion',
        'redelivery preserves answers and review status');
    $changed['data']['submissionId'] = 'another'; $raw = json_encode($changed);
    check(hook::receive($raw, $sign($raw), $config) === 'duplicate', 'same email new submission not duplicated');
    $changed['data']['fields'][2]['value'] = 'admin@example.invalid'; $raw = json_encode($changed);
    $DB->users[] = 'admin@example.invalid';
    check(hook::receive($raw, $sign($raw), $config) === 'existinguser', 'existing Moodle user protected');
    $DB->fail = true;
    rejected(fn()=>hook::receive($raw, $sign($raw), $config), 0, 'transient DB failure propagated for retry');
    check(\local_web3talents\local\application_review_service::$released === 6, 'locks released even on failure');
    check(\local_web3talents\local\applicant_service::$writes === 1, 'only one pending insertion across deliveries');
    echo "All standalone Tally webhook tests passed. No real database or email used.\n";
}
