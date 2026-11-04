<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Parse Tally exports without discarding question labels, repeated columns or multiline answers. */
class application_csv {
    public static function parse(string $content, string $delimiter = ',', string $cohort = 'fundamentals'): array {
        if (strlen($content) > 5 * 1024 * 1024 || !in_array($delimiter, [',', ';', "\t", ':'], true)) {
            throw new \invalid_argument_exception('Invalid delimiter or file exceeds 5 MB.');
        }
        $stream = fopen('php://temp', 'r+');
        try {
            fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $content));
            rewind($stream);
            $headers = fgetcsv($stream, 0, $delimiter, '"', '');
            if (!$headers) {
                throw new \invalid_argument_exception('The CSV is empty.');
            }
            $headers = array_map(static fn($v) => trim((string)$v), $headers);
            if (count($headers) > 250) {
                throw new \invalid_argument_exception('Import at most 250 columns at a time.');
            }
            $map = [];
            foreach ($headers as $i => $label) {
                $key = preg_replace('/[^a-z0-9]/', '', strtolower($label));
                $field = match ($key) {
                    'first', 'firstname', 'givenname' => 'firstname',
                    'last', 'lastname', 'surname', 'familyname' => 'lastname',
                    'email', 'mail', 'emailaddress' => 'email',
                    'submissionid' => 'submissionid',
                    default => null,
                };
                if ($field !== null) {
                    if (isset($map[$field])) {
                        throw new \invalid_argument_exception('Ambiguous identity columns: ' . $field);
                    }
                    $map[$field] = $i;
                }
            }
            foreach (['firstname', 'lastname', 'email'] as $field) {
                if (!isset($map[$field])) {
                    throw new \invalid_argument_exception('Missing column: ' . $field);
                }
            }
            $result = [];
            $responsebytes = 0;
            $line = 1;
            while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                $line++;
                if (!array_filter($cells, static fn($v) => trim((string)$v) !== '')) {
                    continue;
                }
                if (count($result) >= 2000) {
                    throw new \invalid_argument_exception('Import at most 2,000 applications at a time.');
                }
                $row = ['row' => $line, 'cohortid' => trim($cohort), 'error' => ''];
                foreach ($map as $field => $i) {
                    $row[$field] = trim((string)($cells[$i] ?? ''));
                }
                $row['email'] = strtolower($row['email']);
                $row['submissionid'] = $row['submissionid'] ?? '';
                $answers = [];
                foreach ($headers as $i => $label) {
                    $answers[] = ['question' => $label ?: 'Column ' . ($i + 1), 'answer' => (string)($cells[$i] ?? '')];
                }
                $row['responses'] = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $responsebytes += strlen($row['responses']);
                if ($responsebytes > 20 * 1024 * 1024) {
                    throw new \invalid_argument_exception('Expanded responses exceed 20 MB. Split the export into smaller files.');
                }
                if (count($cells) !== count($headers)) {
                    $row['error'] = 'Column count does not match the header.';
                } else if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL) || strlen($row['email']) > 255) {
                    $row['error'] = 'A valid email address is required.';
                } else if (!$row['firstname'] || !$row['lastname'] || !$row['cohortid']) {
                    $row['error'] = 'First name, last name and cohort are required.';
                } else if (mb_strlen($row['firstname']) > 100 || mb_strlen($row['lastname']) > 100 ||
                        mb_strlen($row['cohortid']) > 100 || mb_strlen($row['submissionid']) > 100) {
                    $row['error'] = 'An identity field is too long.';
                }
                $result[] = $row;
            }
            return $result;
        } finally {
            fclose($stream);
        }
    }
}
