<?php
/**
 * Minimal ISAPI client for Hikvision access/face terminals.
 * Uses HTTP Digest auth (required by ISAPI).
 *
 * Endpoints used here work on most DS-K1T3xx firmware. If a call fails,
 * check the device's ISAPI docs for your exact firmware and adjust the path.
 */
class IsapiClient
{
    private string $base;

    public function __construct()
    {
        $scheme = DEVICE_HTTPS ? 'https' : 'http';
        $this->base = sprintf('%s://%s:%d', $scheme, DEVICE_IP, DEVICE_PORT);
    }

    /**
     * @return array{code:int, body:string}
     */
    public function request(string $method, string $path, $body = null,
                            string $contentType = 'application/json', array $extraHeaders = []): array
    {
        $ch = curl_init($this->base . $path);
        $headers = array_merge(['Content-Type: ' . $contentType], $extraHeaders);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_DIGEST,
            CURLOPT_USERPWD        => DEVICE_USER . ':' . DEVICE_PASS,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            return ['code' => 0, 'body' => 'cURL error: ' . $err];
        }
        return ['code' => (int)$code, 'body' => (string)$resp];
    }

    /** Quick reachability/credential check. */
    public function deviceInfo(): array
    {
        return $this->request('GET', '/ISAPI/System/deviceInfo?format=json');
    }

    /** Create or update a user record on the terminal. */
    public function addUser(string $empNo, string $name, ?string $cardNo = null): array
    {
        $payload = [
            'UserInfo' => [
                'employeeNo'  => $empNo,
                'name'        => $name,
                'userType'    => 'normal',
                'Valid'       => [
                    'enable'    => true,
                    'beginTime' => '2020-01-01T00:00:00',
                    'endTime'   => '2037-12-31T23:59:59',
                    'timeType'  => 'local',
                ],
            ],
        ];
        $res = $this->request('POST', '/ISAPI/AccessControl/UserInfo/Record?format=json', $payload);
        if ($cardNo) {
            $this->request('POST', '/ISAPI/AccessControl/CardInfo/Record?format=json', [
                'CardInfo' => ['employeeNo' => $empNo, 'cardNo' => $cardNo, 'cardType' => 'normalCard'],
            ]);
        }
        return $res;
    }

    public function deleteUser(string $empNo): array
    {
        $payload = ['UserInfoDelCond' => ['EmployeeNoList' => [['employeeNo' => $empNo]]]];
        return $this->request('PUT', '/ISAPI/AccessControl/UserInfo/Delete?format=json', $payload);
    }

    /**
     * Upload a face photo for an existing user (binary multipart).
     * $imagePath must be a readable JPG on disk.
     */
    public function setFace(string $empNo, string $imagePath): array
    {
        if (!is_readable($imagePath)) {
            return ['code' => 0, 'body' => 'image not readable: ' . $imagePath];
        }
        $boundary = '----hikface' . bin2hex(random_bytes(8));
        $faceMeta = json_encode([
            'faceLibType' => 'blackFD',
            'FDID'        => '1',
            'FPID'        => $empNo,
        ]);
        $img = file_get_contents($imagePath);

        $body  = "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"FaceDataRecord\"\r\n";
        $body .= "Content-Type: application/json\r\n\r\n";
        $body .= $faceMeta . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"img\"; filename=\"face.jpg\"\r\n";
        $body .= "Content-Type: image/jpeg\r\n\r\n";
        $body .= $img . "\r\n";
        $body .= "--$boundary--\r\n";

        return $this->request(
            'POST',
            '/ISAPI/Intelligent/FDLib/FaceDataRecord?format=json',
            $body,
            'multipart/form-data; boundary=' . $boundary
        );
    }

    /** List HTTP listening hosts currently registered on the device. */
    public function listHttpHosts(): array
    {
        return $this->request('GET', '/ISAPI/Event/notification/httpHosts?format=json');
    }

    /**
     * Register (or overwrite) this server as HTTP listening host #1 so the
     * terminal pushes events to our event receiver.
     *
     * @param string $serverIp  this PC's LAN IP (reachable by the terminal)
     * @param int    $port      Apache port (usually 80)
     * @param string $path      receiver path, incl. ?token=... if used
     */
    public function setHttpHost(string $serverIp, int $port = 80,
                                string $path = '/attendance/api/event-receiver.php',
                                string $id = '1'): array
    {
        // This firmware (V4.x) uses XML for httpHosts, not JSON.
        $ns = 'http://www.isapi.org/ver20/XMLSchema';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
             . '<HttpHostNotification version="2.0" xmlns="' . $ns . '">'
             . '<id>' . htmlspecialchars($id) . '</id>'
             . '<url>' . htmlspecialchars($path) . '</url>'
             . '<protocolType>HTTP</protocolType>'
             . '<parameterFormatType>JSON</parameterFormatType>'
             . '<addressingFormatType>ipaddress</addressingFormatType>'
             . '<ipAddress>' . htmlspecialchars($serverIp) . '</ipAddress>'
             . '<portNo>' . (int)$port . '</portNo>'
             . '<httpAuthenticationMethod>none</httpAuthenticationMethod>'
             . '</HttpHostNotification>';

        return $this->request('PUT', '/ISAPI/Event/notification/httpHosts/' . $id,
                              $xml, 'application/xml');
    }

    /**
     * Polling fallback: search access events in a time window.
     * Only needed if you ever switch off event push.
     */
    public function searchEvents(string $startIso, string $endIso, int $pos = 0, int $max = 30): array
    {
        $payload = ['AcsEventCond' => [
            'searchID'   => bin2hex(random_bytes(4)),
            'searchResultPosition' => $pos,
            'maxResults' => $max,
            'major'      => 0,
            'minor'      => 0,
            'startTime'  => $startIso,
            'endTime'    => $endIso,
        ]];
        return $this->request('POST', '/ISAPI/AccessControl/AcsEvent?format=json', $payload);
    }
}
