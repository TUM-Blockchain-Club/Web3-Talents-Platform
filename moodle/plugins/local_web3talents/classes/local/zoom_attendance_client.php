<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Read-only Zoom reporting client. Tokens and upstream response errors are never logged. */
class zoom_attendance_client extends zoom_client {
    private ?string $token = null;

    public function __construct(private array $credentials) {
        parent::__construct($credentials);
    }

    private function get(string $path): array {
        if ($this->token === null) {
            $data = $this->request('https://zoom.us/oauth/token', [
                'Authorization: Basic ' . base64_encode($this->credentials['client_id'] . ':' . $this->credentials['client_secret']),
                'Content-Type: application/x-www-form-urlencoded',
            ], http_build_query(['grant_type' => 'account_credentials', 'account_id' => $this->credentials['account_id']]));
            if (empty($data['access_token']) || !is_string($data['access_token']) || preg_match('/[\r\n]/', $data['access_token'])) {
                throw new \RuntimeException('zoom_attendance_api_error');
            }
            $this->token = $data['access_token'];
        }
        return $this->request('https://api.zoom.us/v2/' . $path, ['Authorization: Bearer ' . $this->token]);
    }

    public function meeting(): array {
        if (!preg_match('/^\d{9,11}$/D', $this->credentials['meeting_id'] ?? '')) {
            throw new \RuntimeException('zoom_attendance_config_error');
        }
        $meeting = $this->get('meetings/' . $this->credentials['meeting_id']);
        if ((string)($meeting['id'] ?? '') !== $this->credentials['meeting_id'] || empty($meeting['host_id'])) {
            throw new \RuntimeException('zoom_attendance_config_error');
        }
        if (!empty($this->credentials['host_id']) && $this->credentials['host_id'] !== $meeting['host_id']) {
            throw new \RuntimeException('zoom_wronghost');
        }
        return $meeting;
    }

    public function instances(int $from, int $to): array {
        $data = $this->get('past_meetings/' . $this->credentials['meeting_id'] . '/instances?' . http_build_query([
            'from' => gmdate('Y-m-d', $from), 'to' => gmdate('Y-m-d', $to),
        ]));
        if (!isset($data['meetings']) || !is_array($data['meetings'])) {
            throw new \RuntimeException('zoom_attendance_api_error');
        }
        return $data['meetings'];
    }

    public function participants(string $uuid): array {
        if ($uuid === '' || strlen($uuid) > 128) {
            throw new \RuntimeException('zoom_attendance_api_error');
        }
        // Use the occurrence UUID, never the recurring meeting number (which means "latest").
        $encoded = rawurlencode($uuid);
        if (str_contains($uuid, '/')) {
            $encoded = rawurlencode($encoded);
        }
        $participants = [];
        $token = '';
        $seen = [];
        for ($page = 0; $page < 100; $page++) {
            $data = $this->get('past_meetings/' . $encoded . '/participants?' . http_build_query([
                'page_size' => 300, 'next_page_token' => $token,
            ]));
            if (!isset($data['participants']) || !is_array($data['participants'])) {
                throw new \RuntimeException('zoom_attendance_api_error');
            }
            $participants = array_merge($participants, $data['participants']);
            $token = (string)($data['next_page_token'] ?? '');
            if ($token === '') {
                if (isset($data['total_records']) && count($participants) !== (int)$data['total_records']) {
                    throw new \RuntimeException('zoom_attendance_incomplete');
                }
                return $participants;
            }
            if (isset($seen[$token])) {
                break;
            }
            $seen[$token] = true;
        }
        throw new \RuntimeException('zoom_attendance_incomplete');
    }
}
