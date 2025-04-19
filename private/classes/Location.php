<?php
// filepath: e:\Application\laragon\www\surveying_account\private\classes\Location.php
require_once __DIR__ . '/Database.php';

class Location {
    private $conn;
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
    }

    /**
     * Fetches all locations (provinces).
     * Assumes a 'location' table with 'id' and 'province' columns.
     *
     * @return array An array of locations, each with 'id' and 'province'.
     */
    public function getAllProvinces(): array {
        $provinces = [];
        try {
            // Make sure the table and column names match your schema
            $stmt = $this->conn->prepare("SELECT id, province FROM location ORDER BY province ASC");
            $stmt->execute();
            $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching locations: " . $e->getMessage());
            // Handle error appropriately, maybe return empty array or throw exception
        }
        return $provinces;
    }

     /**
     * Checks if a location ID exists.
     *
     * @param int|null $location_id The ID of the location to check.
     * @return bool True if the location exists, false otherwise.
     */
    public function locationExists(?int $location_id): bool {
        if ($location_id === null) {
            return false;
        }
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM location WHERE id = :id");
            $stmt->bindParam(':id', $location_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error checking location existence (ID: " . $location_id . "): " . $e->getMessage());
            return false;
        }
    }


    public function closeConnection(): void {
        $this->db->close();
    }
}
?>