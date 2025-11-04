<?php
require_once __DIR__ . '/db.php';

function getTableStructure($pdo, $tableName) {
    try {
        $sql = "PRAGMA table_info($tableName)";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

var_dump(getTableStructure($pdo, 'users_new'));