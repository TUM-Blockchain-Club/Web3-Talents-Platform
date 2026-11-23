<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Signed, form-scoped ingestion. Never approves, provisions or emails an applicant. */
class tally_webhook {
    public const MAX_BYTES = 1048576;

    /** Authenticate the original bytes before decoding; fail closed without full configuration. */
    public static function authenticate(string $body, string $signature, \stdClass $config): void {
        if (empty($config->tally_enabled) || strlen($config->tally_secret ?? '') < 32 ||
                empty($config->tally_formid) || empty($config->tally_cohort)) {
            throw new \RuntimeException('Integration not configured.', 503);
        }
        if (strlen($body) > self::MAX_BYTES) {
            throw new \RuntimeException('Payload too large.', 413);
        }
        $expected = base64_encode(hash_hmac('sha256', $body, $config->tally_secret, true));
        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid signature.', 401);
        }
    }

    /** Preserve every answer as plain data using the same schema as CSV application review. */
    public static function parse(string $body, \stdClass $config): array {
        try {
            $event = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Invalid JSON.', 400);
        }
        // Tally's dashboard test/replay payload omits eventType; actual submissions may include it.
        // Both variants still require a signed, identified event from the exact configured form.
        if (!is_array($event) || !is_string($event['eventId'] ?? null) ||
                !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $event['eventId']) ||
                (array_key_exists('eventType', $event) && $event['eventType'] !== 'FORM_RESPONSE')) {
            throw new \InvalidArgumentException('Unsupported event.', 400);
        }
        $data = $event['data'] ?? null;
        if (!is_array($data) || ($data['formId'] ?? '') !== $config->tally_formid) {
            throw new \InvalidArgumentException('Unexpected form.', 403);
        }
        $submission = $data['submissionId'] ?? null;
        if (!is_string($submission) || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $submission)) {
            throw new \InvalidArgumentException('Invalid submission ID.', 400);
        }
        $fields = $data['fields'] ?? null;
        if (!is_array($fields) || !array_is_list($fields) || !$fields || count($fields) > 250) {
            throw new \InvalidArgumentException('Invalid fields.', 400);
        }
        $identity = [];
        $answers = [['question'=>'Submission ID', 'answer'=>$submission]];
        $seenkeys = [];
        foreach ($fields as $field) {
            if (!is_array($field) || !is_string($field['key'] ?? null) ||
                    !is_string($field['label'] ?? null) || !array_key_exists('value', $field) ||
                    isset($seenkeys[$field['key']])) {
                throw new \InvalidArgumentException('Invalid or repeated field.', 400);
            }
            $seenkeys[$field['key']] = true;
            $label = trim($field['label']);
            $normalised = preg_replace('/[^a-z0-9]/', '', strtolower($label));
            $type = match ($normalised) {
                'first', 'firstname', 'givenname' => 'firstname',
                'last', 'lastname', 'surname', 'familyname' => 'lastname',
                'email', 'mail', 'emailaddress' => 'email',
                default => null,
            };
            // Configured stable field keys take precedence over label aliases.
            $explicit = null;
            foreach (['firstname', 'lastname', 'email'] as $name) {
                $key = $config->{'tally_' . $name . '_key'} ?? '';
                if ($key !== '') {
                    if ($type === $name) {
                        $type = null;
                    }
                    if ($key === $field['key']) {
                        if ($explicit !== null) {
                            throw new \InvalidArgumentException('Repeated identity mapping.', 400);
                        }
                        $explicit = $name;
                    }
                }
            }
            $type = $explicit ?? $type;
            if ($type !== null) {
                if (isset($identity[$type]) || !is_string($field['value'])) {
                    throw new \InvalidArgumentException('Ambiguous identity fields.', 400);
                }
                $identity[$type] = trim($field['value']);
            }
            $answers[] = ['question'=>$label ?: $field['key'], 'answer'=>self::answer($field)];
        }
        foreach (['firstname', 'lastname', 'email'] as $name) {
            if (empty($identity[$name])) {
                throw new \InvalidArgumentException('Missing identity field.', 400);
            }
        }
        $identity['email'] = strtolower($identity['email']);
        if (!filter_var($identity['email'], FILTER_VALIDATE_EMAIL) || strlen($identity['email']) > 255 ||
                mb_strlen($identity['firstname']) > 100 || mb_strlen($identity['lastname']) > 100 ||
                mb_strlen($config->tally_cohort) > 100) {
            throw new \InvalidArgumentException('Invalid identity fields.', 400);
        }
        return $identity + ['submissionid'=>$submission, 'cohortid'=>$config->tally_cohort,
            'responses'=>json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'error'=>''];
    }

    private static function answer(array $field): string {
        $value = $field['value'];
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        // Replace choice UUIDs with the human-readable labels, retaining unknown values.
        if (is_array($value) && array_is_list($value) && isset($field['options']) && is_array($field['options'])) {
            $options = [];
            foreach ($field['options'] as $option) {
                if (is_array($option) && is_string($option['id'] ?? null) && is_string($option['text'] ?? null)) {
                    $options[$option['id']] = $option['text'];
                }
            }
            $value = array_map(static fn($id) => is_string($id) ? ($options[$id] ?? $id) : $id, $value);
        }
        // Files/matrices remain readable JSON; never fetch attachment URLs or interpret HTML.
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function receive(string $body, string $signature, \stdClass $config): string {
        global $DB;
        self::authenticate($body, $signature, $config);
        $row = self::parse($body, $config);
        // Same lock as CSV import: concurrent retries and CSV uploads cannot create duplicates.
        $lock = application_review_service::lock('tally-import');
        try {
            $transaction = $DB->start_delegated_transaction();
            try {
                $existing = $DB->get_record('local_web3talents_app', ['submissionid'=>$row['submissionid']]);
                if (!$existing) {
                    $existing = $DB->get_record('local_web3talents_app', ['email'=>$row['email']]);
                }
                if ($existing) {
                    // Never overwrite the original answers or invalidate reviews on redelivery.
                    $result = 'duplicate';
                } else if ($DB->record_exists('user', ['email'=>$row['email'], 'deleted'=>0])) {
                    $result = 'existinguser';
                } else {
                    $applicant = applicant_service::upsert_applicant((object)$row, 'tally', 0);
                    $result = $applicant->wasinserted ? 'created' : 'duplicate';
                }
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        } finally {
            $lock->release();
        }
        return $result;
    }
}
