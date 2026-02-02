<?php
require_once '../config/database.php';
$db = (new Database())->getConnection();

function inspect($db, $table) {
    echo "Inspecting $table:\n";
    try {
        $stmt = $db->query("SHOW CREATE VIEW $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "It is a VIEW.\n";
            print_r($row);
            return;
        }
    } catch (Exception $e) {
        echo "Not a view (or error: " . $e->getMessage() . ")\n";
    }

    try {
        $stmt = $db->query("DESCRIBE $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Columns:\n";
        foreach ($rows as $r) {
            echo " - " . $r['Field'] . " (" . $r['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "Error describing table: " . $e->getMessage() . "\n";
    }
    echo "\n-------------------\n";
}

inspect($db, 'teachers');
inspect($db, 'users');
inspect($db, 'user_profiles');
?>
