<?php

require_once __DIR__ . '/../config/database.php';

class ReadingsController {

    private PDO $db;

    private const ALLOWED_PATCH_FIELDS = [
        'carbon_intensity',
        'renewable_percentage',
        'fossil_percentage',
        'power_breakdown_json',
    ];

    private const ALL_FIELDS = [
        'zone_key',
        'carbon_intensity',
        'renewable_percentage',
        'fossil_percentage',
        'power_breakdown_json',
        'reading_datetime',
    ];

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function handle(string $method, ?string $id): void {
        try {
            switch ($method) {
                case 'GET':
                    $id === null ? $this->index() : $this->show($id);
                    break;
                case 'POST':
                    $this->store();
                    break;
                case 'PUT':
                    if ($id === null) { $this->methodNotAllowed(); return; }
                    $this->update($id);
                    break;
                case 'PATCH':
                    if ($id === null) { $this->methodNotAllowed(); return; }
                    $this->patch($id);
                    break;
                case 'DELETE':
                    if ($id === null) { $this->methodNotAllowed(); return; }
                    $this->destroy($id);
                    break;
                default:
                    $this->methodNotAllowed();
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Errore del database']);
        }
    }

    private function index(): void {
        $zoneKey = $_GET['zone_key'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;

        if ($zoneKey !== null && $zoneKey !== '') {
            $stmt = $this->db->prepare(
                "SELECT id, zone_key, carbon_intensity, renewable_percentage, fossil_percentage,
                        power_breakdown_json, reading_datetime, fetched_at
                 FROM carbon_readings
                 WHERE zone_key = :zone_key
                 ORDER BY reading_datetime DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':zone_key', $zoneKey);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare(
                "SELECT id, zone_key, carbon_intensity, renewable_percentage, fossil_percentage,
                        power_breakdown_json, reading_datetime, fetched_at
                 FROM carbon_readings
                 ORDER BY reading_datetime DESC
                 LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $rows = array_map([$this, 'decodeBreakdown'], $stmt->fetchAll());
        echo json_encode($rows);
    }

    private function show(string $id): void {
        $reading = $this->findById($id);
        if (!$reading) {
            $this->notFound();
            return;
        }
        echo json_encode($this->decodeBreakdown($reading));
    }

    private function store(): void {
        $data = $this->readJsonBody();

        $zoneKey = trim($data['zone_key'] ?? '');
        $datetime = trim($data['reading_datetime'] ?? '');

        if ($zoneKey === '' || $datetime === '') {
            http_response_code(400);
            echo json_encode(['error' => 'zone_key e reading_datetime sono obbligatori']);
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO carbon_readings
                 (zone_key, carbon_intensity, renewable_percentage, fossil_percentage,
                  power_breakdown_json, reading_datetime)
             VALUES
                 (:zone_key, :carbon_intensity, :renewable_percentage, :fossil_percentage,
                  :power_breakdown_json, :reading_datetime)"
        );
        $stmt->execute([
            ':zone_key' => $zoneKey,
            ':carbon_intensity' => $data['carbon_intensity'] ?? null,
            ':renewable_percentage' => $data['renewable_percentage'] ?? null,
            ':fossil_percentage' => $data['fossil_percentage'] ?? null,
            ':power_breakdown_json' => $data['power_breakdown_json'] ?? null,
            ':reading_datetime' => $datetime,
        ]);

        $newId = $this->db->lastInsertId();
        http_response_code(201);
        echo json_encode($this->decodeBreakdown($this->findById($newId)));
    }

    private function update(string $id): void {
        $existing = $this->findById($id);
        if (!$existing) {
            $this->notFound();
            return;
        }

        $data = $this->readJsonBody();

        foreach (self::ALL_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                http_response_code(400);
                echo json_encode(['error' => "Campo mancante: $field"]);
                return;
            }
        }

        if (trim($data['zone_key']) === '' || trim($data['reading_datetime']) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'zone_key e reading_datetime non possono essere vuoti']);
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE carbon_readings
             SET zone_key = :zone_key,
                 carbon_intensity = :carbon_intensity,
                 renewable_percentage = :renewable_percentage,
                 fossil_percentage = :fossil_percentage,
                 power_breakdown_json = :power_breakdown_json,
                 reading_datetime = :reading_datetime
             WHERE id = :id"
        );
        $stmt->execute([
            ':zone_key' => $data['zone_key'],
            ':carbon_intensity' => $data['carbon_intensity'],
            ':renewable_percentage' => $data['renewable_percentage'],
            ':fossil_percentage' => $data['fossil_percentage'],
            ':power_breakdown_json' => $data['power_breakdown_json'],
            ':reading_datetime' => $data['reading_datetime'],
            ':id' => $id,
        ]);

        echo json_encode($this->decodeBreakdown($this->findById($id)));
    }

    private function patch(string $id): void {
        $existing = $this->findById($id);
        if (!$existing) {
            $this->notFound();
            return;
        }

        $data = $this->readJsonBody();

        $setParts = [];
        $params = [':id' => $id];
        foreach (self::ALLOWED_PATCH_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $setParts[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($setParts)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nessun campo valido da aggiornare']);
            return;
        }

        $sql = "UPDATE carbon_readings SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        echo json_encode($this->decodeBreakdown($this->findById($id)));
    }

    private function destroy(string $id): void {
        $existing = $this->findById($id);
        if (!$existing) {
            $this->notFound();
            return;
        }

        $stmt = $this->db->prepare("DELETE FROM carbon_readings WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(['deleted' => true, 'id' => (int)$id]);
    }

    private function findById(string $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT id, zone_key, carbon_intensity, renewable_percentage, fossil_percentage,
                    power_breakdown_json, reading_datetime, fetched_at
             FROM carbon_readings
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function decodeBreakdown(array $row): array {
        if (!empty($row['power_breakdown_json'])) {
            $decoded = json_decode($row['power_breakdown_json'], true);
            $row['power_breakdown_json'] = $decoded ?? $row['power_breakdown_json'];
        }
        return $row;
    }

    private function readJsonBody(): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function notFound(): void {
        http_response_code(404);
        echo json_encode(['error' => 'Lettura non trovata']);
    }

    private function methodNotAllowed(): void {
        http_response_code(405);
        echo json_encode(['error' => 'Metodo non consentito']);
    }
}
