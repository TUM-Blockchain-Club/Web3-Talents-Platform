<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Pure, independently testable identity and duration rules. Never match display names. */
class zoom_attendance_math {
    public static function merge(array $intervals): array {
        usort($intervals, static fn($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($intervals as [$start, $end]) {
            if ($end <= $start) {
                continue;
            }
            $last = count($merged) - 1;
            if ($last >= 0 && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($end, $merged[$last][1]);
            } else {
                $merged[] = [$start, $end];
            }
        }
        return $merged;
    }

    public static function seconds(array $intervals): int {
        return array_sum(array_map(static fn($i) => $i[1] - $i[0], self::merge($intervals)));
    }

    public static function match(string $email, array $students): int {
        $email = strtolower(trim($email));
        if ($email === '') {
            return 0;
        }
        $matches = array_filter($students, static fn($s) => empty($s->deleted) && empty($s->suspended) &&
            strtolower(trim($s->email)) === $email);
        return count($matches) === 1 ? (int)reset($matches)->id : 0;
    }

    public static function group(array $participants, int $starttime, int $now): array {
        $groups = [];
        foreach ($participants as $p) {
            // Waiting-room time is not attendance. Missing status is permitted by the API schema.
            if (isset($p['status']) && $p['status'] !== 'in_meeting') {
                continue;
            }
            $email = strtolower(trim((string)($p['user_email'] ?? '')));
            if (strlen($email) > 255 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                throw new \RuntimeException('zoom_attendance_incomplete');
            }
            $id = trim((string)($p['id'] ?? ''));
            $join = strtotime((string)($p['join_time'] ?? ''));
            $leave = strtotime((string)($p['leave_time'] ?? ''));
            // Fail the entire snapshot closed if reporting is still being processed.
            if (!$join || !$leave || $leave <= $join || $join < $starttime - 3600 ||
                    $leave > $starttime + 86400 || $leave > $now + 60 || (int)($p['duration'] ?? 0) <= 0) {
                throw new \RuntimeException('zoom_attendance_incomplete');
            }
            // Signed-in Zoom UUID safely groups reconnects within this occurrence. Anonymous
            // attendees without email/UUID remain separate; even identical names are not merged.
            $identity = $email !== '' && $id !== '' ? 'email:' . $email : ($id !== '' ? 'id:' . $id :
                'anonymous:' . json_encode([$p['user_id'] ?? '', $email, $p['name'] ?? '', $join, $leave]));
            $key = hash('sha256', $identity);
            if (!isset($groups[$key])) {
                $groups[$key] = ['name' => (string)($p['name'] ?? ''),
                    'email' => $email, 'authenticated' => $id !== '', 'intervals' => []];
            }
            $groups[$key]['intervals'][] = [$join, $leave];
        }
        foreach ($groups as &$group) {
            $group['intervals'] = self::merge($group['intervals']);
        }
        return $groups;
    }

    /** Exactly one session within 45 minutes, otherwise require organiser intervention. */
    public static function session_for(int $starttime, array $sessions): int {
        $matches = array_filter($sessions, static fn($s) => abs($s->sessiondate - $starttime) <= 2700);
        return count($matches) === 1 ? (int)reset($matches)->id : 0;
    }
}
