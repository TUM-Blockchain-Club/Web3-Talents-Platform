<?php
namespace local_web3talents\local;

defined('MOODLE_INTERNAL') || die();

/** Server-side Zoom client. Never persist or log API responses or tokens. */
class zoom_client {
    public function __construct(private array $config) {
    }

    /** Fetch a fresh start credential for exactly the configured meeting and host. */
    public function start_url(): string {
        $meetingid = $this->config['meeting_id'];
        if (!preg_match('/^[0-9]{9,11}$/D', $meetingid)) {
            throw new \RuntimeException('zoom_notconfigured');
        }
        $token = $this->request('https://zoom.us/oauth/token', [
            'Authorization: Basic ' . base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']),
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query(['grant_type' => 'account_credentials', 'account_id' => $this->config['account_id']]));
        if (empty($token['access_token']) || !is_string($token['access_token']) ||
                preg_match('/[\r\n]/', $token['access_token'])) {
            throw new \RuntimeException('zoom_unavailable');
        }
        $meeting = $this->request('https://api.zoom.us/v2/meetings/' . $meetingid,
            ['Authorization: Bearer ' . $token['access_token']]);
        if ((string)($meeting['id'] ?? '') !== $meetingid ||
                ($meeting['host_id'] ?? '') !== $this->config['host_id']) {
            throw new \RuntimeException('zoom_wronghost');
        }
        $url = $meeting['start_url'] ?? '';
        self::validate_start_url($url, $meetingid);
        return $url;
    }

    /** Reject arbitrary redirects and require a Zoom start credential for this meeting. */
    public static function validate_start_url($url, string $meetingid): void {
        if (!is_string($url) || strlen($url) > 8192 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new \RuntimeException('zoom_unavailable');
        }
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        parse_str($parts['query'] ?? '', $query);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' ||
                !preg_match('/^(?:[a-z0-9-]+\.)*zoom\.us$/D', $host) ||
                isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) ||
                (isset($parts['port']) && $parts['port'] !== 443) ||
                ($parts['path'] ?? '') !== '/s/' . $meetingid ||
                empty($query['zak']) || !is_string($query['zak'])) {
            throw new \RuntimeException('zoom_unavailable');
        }
    }

    /** Fixed URLs, verified TLS, no redirects, debug output or disk caching. */
    protected function request(string $url, array $headers, ?string $body = null): array {
        $response = '';
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function($curl, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 1048576) {
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $success = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if ($success === false || $status !== 200) {
            throw new \RuntimeException('zoom_unavailable');
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('zoom_unavailable');
        }
        return $data;
    }
}
