<?php

require_once __DIR__ . '/../config/database.php';

class ProxyController {

    private const API_KEY = 'KJCqBvdSYEgT6Qb5hG3Q';
    private const BASE_URL = 'https://api.electricitymap.org/v3/';
    private const TIMEOUT = 10;
    private const RATE_LIMIT_MAX = 30;
    private const RATE_LIMIT_WINDOW = 60;

    private PDO $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function handle(string $method, ?string $action): void {
        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non consentito']);
            return;
        }

        if (!$this->checkRateLimit()) {
            http_response_code(429);
            echo json_encode(['error' => 'Troppe richieste, attendi un minuto']);
            return;
        }

        switch ($action) {
            case 'zones':
                $this->zones();
                break;
            case 'carbon-intensity':
                $this->carbonIntensity();
                break;
            case 'power-breakdown':
                $this->powerBreakdown();
                break;
            case 'history':
                $this->history();
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Azione proxy non trovata']);
        }
    }

    private function checkRateLimit(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $now = time();
        $bucket = $_SESSION['proxy_rate_limit'] ?? null;
        if (!is_array($bucket) || ($now - ($bucket['start'] ?? 0)) >= self::RATE_LIMIT_WINDOW) {
            $_SESSION['proxy_rate_limit'] = ['start' => $now, 'count' => 1];
            return true;
        }
        if ($bucket['count'] >= self::RATE_LIMIT_MAX) {
            return false;
        }
        $_SESSION['proxy_rate_limit']['count'] = $bucket['count'] + 1;
        return true;
    }

    private function zones(): void {
        $response = $this->callApi('zones', false);
        if ($response === null) return;
        echo json_encode($response);
    }

    private function carbonIntensity(): void {
        $zone = $this->requireZoneParam();
        if ($zone === null) return;

        $response = $this->callApi("carbon-intensity/latest?zone=" . urlencode($zone));
        if ($response === null) return;

        if ($this->isZoneMonitored($zone) && isset($response['datetime'])) {
            $this->insertReading([
                'zone_key' => $zone,
                'carbon_intensity' => $response['carbonIntensity'] ?? null,
                'renewable_percentage' => null,
                'fossil_percentage' => null,
                'power_breakdown_json' => null,
                'reading_datetime' => $this->normalizeDatetime($response['datetime']),
            ]);
        }

        echo json_encode($response);
    }

    private function powerBreakdown(): void {
        $zone = $this->requireZoneParam();
        if ($zone === null) return;

        $response = $this->callApi("power-breakdown/latest?zone=" . urlencode($zone));
        if ($response === null) return;

        if ($this->isZoneMonitored($zone) && isset($response['datetime'])) {
            $breakdown = $response['powerConsumptionBreakdown']
                      ?? $response['powerProductionBreakdown']
                      ?? null;
            $fossilFree = $response['fossilFreePercentage'] ?? null;

            $this->insertReading([
                'zone_key' => $zone,
                'carbon_intensity' => null,
                'renewable_percentage' => $response['renewablePercentage'] ?? null,
                'fossil_percentage' => $fossilFree !== null ? (100 - $fossilFree) : null,
                'power_breakdown_json' => $breakdown !== null ? json_encode($breakdown) : null,
                'reading_datetime' => $this->normalizeDatetime($response['datetime']),
            ]);
        }

        echo json_encode($response);
    }

    private function history(): void {
        $zone = $this->requireZoneParam();
        if ($zone === null) return;

        $response = $this->callApi("carbon-intensity/history?zone=" . urlencode($zone));
        if ($response === null) return;

        if ($this->isZoneMonitored($zone) && !empty($response['history'])) {
            foreach ($response['history'] as $entry) {
                if (!isset($entry['datetime'])) continue;
                $this->insertReading([
                    'zone_key' => $zone,
                    'carbon_intensity' => $entry['carbonIntensity'] ?? null,
                    'renewable_percentage' => null,
                    'fossil_percentage' => null,
                    'power_breakdown_json' => null,
                    'reading_datetime' => $this->normalizeDatetime($entry['datetime']),
                ]);
            }
        }

        echo json_encode($response);
    }

    private function callApi(string $path, bool $withAuth = true): ?array {
        $url = self::BASE_URL . $path;

        $headers = ['Accept: application/json'];
        if ($withAuth) {
            $headers[] = 'auth-token: ' . self::API_KEY;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            http_response_code(502);
            echo json_encode(['error' => 'Errore di connessione all\'API esterna']);
            return null;
        }

        if ($httpCode === 401) {
            http_response_code(401);
            echo json_encode(['error' => 'API key Electricity Maps non valida']);
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            http_response_code(502);
            echo json_encode(['error' => 'Errore dall\'API esterna']);
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            http_response_code(502);
            echo json_encode(['error' => 'Risposta non valida dall\'API esterna']);
            return null;
        }

        return $decoded;
    }

    private function requireZoneParam(): ?string {
        $zone = $_GET['zone'] ?? '';
        if (trim($zone) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Parametro "zone" obbligatorio']);
            return null;
        }
        return $zone;
    }

    private function isZoneMonitored(string $zoneKey): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM zones_monitored WHERE zone_key = :zk");
        $stmt->execute([':zk' => $zoneKey]);
        return (bool)$stmt->fetch();
    }

    private function insertReading(array $r): void {
        $check = $this->db->prepare(
            "SELECT id FROM carbon_readings
             WHERE zone_key = :zk AND reading_datetime = :dt
             LIMIT 1"
        );
        $check->execute([':zk' => $r['zone_key'], ':dt' => $r['reading_datetime']]);
        $existing = $check->fetch();

        if ($existing) {
            $stmt = $this->db->prepare(
                "UPDATE carbon_readings
                 SET carbon_intensity = COALESCE(:ci, carbon_intensity),
                     renewable_percentage = COALESCE(:rp, renewable_percentage),
                     fossil_percentage = COALESCE(:fp, fossil_percentage),
                     power_breakdown_json = COALESCE(:pb, power_breakdown_json)
                 WHERE id = :id"
            );
            $stmt->execute([
                ':ci' => $r['carbon_intensity'],
                ':rp' => $r['renewable_percentage'],
                ':fp' => $r['fossil_percentage'],
                ':pb' => $r['power_breakdown_json'],
                ':id' => $existing['id'],
            ]);
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO carbon_readings
                (zone_key, carbon_intensity, renewable_percentage, fossil_percentage,
                 power_breakdown_json, reading_datetime)
             VALUES
                (:zk, :ci, :rp, :fp, :pb, :dt)"
        );
        $stmt->execute([
            ':zk' => $r['zone_key'],
            ':ci' => $r['carbon_intensity'],
            ':rp' => $r['renewable_percentage'],
            ':fp' => $r['fossil_percentage'],
            ':pb' => $r['power_breakdown_json'],
            ':dt' => $r['reading_datetime'],
        ]);
    }

    private function normalizeDatetime(string $iso): string {
        try {
            $dt = new DateTime($iso);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return date('Y-m-d H:i:s');
        }
    }
}
