<?php

require_once __DIR__ . '/../config/database.php';

class ZonesController {

    private PDO $db;

    private const ALLOWED_FIELDS = ['zone_key', 'zone_name', 'user_label', 'notes'];

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
        $stmt = $this->db->query(
            "SELECT id, zone_key, zone_name, user_label, notes, created_at, updated_at
             FROM zones_monitored
             ORDER BY created_at DESC"
        );
        echo json_encode($stmt->fetchAll());
    }

    private function show(string $id): void {
        $zone = $this->findById($id);
        if (!$zone) {
            $this->notFound();
            return;
        }
        echo json_encode($zone);
    }

    private function store(): void {
        $data = $this->readJsonBody();

        $zoneKey = trim($data['zone_key'] ?? '');
        $zoneName = trim($data['zone_name'] ?? '');
        $label = $data['user_label'] ?? null;
        $notes = $data['notes'] ?? null;

        if ($zoneKey === '' || $zoneName === '') {
            http_response_code(400);
            echo json_encode(['error' => 'zone_key e zone_name sono obbligatori']);
            return;
        }

        $check = $this->db->prepare("SELECT id FROM zones_monitored WHERE zone_key = :zone_key");
        $check->execute([':zone_key' => $zoneKey]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Zona già salvata']);
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO zones_monitored (zone_key, zone_name, user_label, notes)
             VALUES (:zone_key, :zone_name, :user_label, :notes)"
        );
        $stmt->execute([
            ':zone_key' => $zoneKey,
            ':zone_name' => $zoneName,
            ':user_label' => $label,
            ':notes' => $notes,
        ]);

        $newId = $this->db->lastInsertId();
        http_response_code(201);
        echo json_encode($this->findById($newId));
    }

    private function update(string $id): void {
        $existing = $this->findById($id);
        if (!$existing) {
            $this->notFound();
            return;
        }

        $data = $this->readJsonBody();

        foreach (self::ALLOWED_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                http_response_code(400);
                echo json_encode(['error' => "Campo mancante: $field"]);
                return;
            }
        }

        $zoneKey = trim($data['zone_key']);
        $zoneName = trim($data['zone_name']);
        if ($zoneKey === '' || $zoneName === '') {
            http_response_code(400);
            echo json_encode(['error' => 'zone_key e zone_name non possono essere vuoti']);
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE zones_monitored
             SET zone_key = :zone_key,
                 zone_name = :zone_name,
                 user_label = :user_label,
                 notes = :notes
             WHERE id = :id"
        );
        $stmt->execute([
            ':zone_key' => $zoneKey,
            ':zone_name' => $zoneName,
            ':user_label' => $data['user_label'],
            ':notes' => $data['notes'],
            ':id' => $id,
        ]);

        echo json_encode($this->findById($id));
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
        foreach (self::ALLOWED_FIELDS as $field) {
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

        $sql = "UPDATE zones_monitored SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        echo json_encode($this->findById($id));
    }

    private function destroy(string $id): void {
        $existing = $this->findById($id);
        if (!$existing) {
            $this->notFound();
            return;
        }

        $stmt = $this->db->prepare("DELETE FROM zones_monitored WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(['deleted' => true, 'id' => (int)$id]);
    }

    private function findById(string $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT id, zone_key, zone_name, user_label, notes, created_at, updated_at
             FROM zones_monitored
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function readJsonBody(): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function notFound(): void {
        http_response_code(404);
        echo json_encode(['error' => 'Zona non trovata']);
    }

    private function methodNotAllowed(): void {
        http_response_code(405);
        echo json_encode(['error' => 'Metodo non consentito']);
    }
}
