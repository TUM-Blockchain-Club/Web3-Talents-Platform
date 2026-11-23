<?php
// Source-contract regression checks: no Moodle config, database, accounts or emails.
$root = __DIR__ . '/../plugins/local_web3talents/';
$list = file_get_contents($root . 'applicants.php');
$review = file_get_contents($root . 'application_review.php');
function check(bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}
check(str_contains($list, "\$queueparams = ['q' => \$query, 'status' => \$filterstatus, 'page' => \$page]"),
    'List context contains search, filter and current page');
check(str_contains($list, "['id' => \$applicant->id] + \$queueparams"), 'Every review link carries list context');
foreach (['q', 'status', 'page'] as $key) {
    check(str_contains($review, "optional_param('$key',"), 'Review reads context: ' . $key);
}
check(str_contains($review, "['id' => \$id] + \$queueparams"), 'Review action/redirect URL retains context');
check(str_contains($review, "['id' => \$id, 'sesskey' => sesskey()] + \$queueparams"),
    'POST hidden inputs retain context with CSRF token');
check(substr_count($review, 'redirect($url,') === 2, 'Success and error redirects retain the same context');
check(str_contains($review, "new moodle_url('/local/web3talents/applicants.php', \$queueparams + ['focus' => \$id])"),
    'Return URL is local and preserves filters/page');
check(str_contains($review, "\$back->set_anchor('applicant-' . \$id)"), 'Return URL anchors the reviewed applicant');
check(str_contains($list, "\$row->id = 'applicant-' . \$applicant->id"), 'Every row has the matching anchor via Moodle row property');
check(str_contains($list, "\$row->attributes['class'] = 'table-primary'"), 'Returned row is highlighted');
check(str_contains($list, "if (\$focusid && !\$focusvisible)"), 'Missing filtered row has an explanatory notice');
check(str_contains($review, "!array_key_exists(\$queueparams['status'], applicant_service::review_filters())"),
    'Unknown status filters are rejected');
check(str_contains($list, "\$page = min(\$page, \$pagecount - 1)"), 'Page clamps safely after filtered results shrink');
