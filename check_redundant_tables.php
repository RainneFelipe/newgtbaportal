<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->connect();

echo "=== DATABASE TABLE ANALYSIS ===\n\n";

// Get all tables in the database
$stmt = $conn->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Total tables found: " . count($tables) . "\n\n";

// Categorize tables
$backup_tables = [];
$main_tables = [];
$test_tables = [];

foreach ($tables as $table) {
    if (strpos($table, 'backup') !== false || strpos($table, '_backup') !== false) {
        $backup_tables[] = $table;
    } elseif (strpos($table, 'test') !== false || strpos($table, '_test') !== false) {
        $test_tables[] = $table;
    } else {
        $main_tables[] = $table;
    }
}

echo "=== TABLE CATEGORIES ===\n";
echo "Main Tables (" . count($main_tables) . "):\n";
foreach ($main_tables as $table) {
    // Get table info
    $stmt = $conn->query("SELECT COUNT(*) FROM `$table`");
    $count = $stmt->fetchColumn();
    echo "  - $table ($count records)\n";
}

if (!empty($backup_tables)) {
    echo "\nBackup Tables (" . count($backup_tables) . "):\n";
    foreach ($backup_tables as $table) {
        $stmt = $conn->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "  - $table ($count records)\n";
    }
}

if (!empty($test_tables)) {
    echo "\nTest Tables (" . count($test_tables) . "):\n";
    foreach ($test_tables as $table) {
        $stmt = $conn->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "  - $table ($count records)\n";
    }
}

// Check for potentially redundant tables by analyzing structure
echo "\n=== POTENTIAL REDUNDANCY ANALYSIS ===\n";

$table_structures = [];
foreach ($main_tables as $table) {
    $stmt = $conn->query("DESCRIBE `$table`");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $table_structures[$table] = $structure;
}

// Look for similar structures
$similar_tables = [];
foreach ($table_structures as $table1 => $structure1) {
    foreach ($table_structures as $table2 => $structure2) {
        if ($table1 !== $table2) {
            $columns1 = array_column($structure1, 'Field');
            $columns2 = array_column($structure2, 'Field');
            
            $common_columns = array_intersect($columns1, $columns2);
            $similarity = count($common_columns) / max(count($columns1), count($columns2));
            
            if ($similarity > 0.7 && !isset($similar_tables["$table2-$table1"])) {
                $similar_tables["$table1-$table2"] = [
                    'table1' => $table1,
                    'table2' => $table2,
                    'similarity' => round($similarity * 100, 1),
                    'common_columns' => count($common_columns),
                    'total_columns' => max(count($columns1), count($columns2))
                ];
            }
        }
    }
}

if (!empty($similar_tables)) {
    echo "Similar table structures found:\n";
    foreach ($similar_tables as $comparison) {
        echo "  - {$comparison['table1']} vs {$comparison['table2']}: {$comparison['similarity']}% similar ";
        echo "({$comparison['common_columns']}/{$comparison['total_columns']} columns)\n";
    }
} else {
    echo "No similar table structures found.\n";
}

// Check for unused tables (tables with no foreign key references)
echo "\n=== FOREIGN KEY ANALYSIS ===\n";

$foreign_keys = [];
$stmt = $conn->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_SCHEMA = DATABASE() 
    AND REFERENCED_TABLE_NAME IS NOT NULL
");

$fk_relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);

$referenced_tables = [];
$referencing_tables = [];

foreach ($fk_relationships as $fk) {
    $referenced_tables[] = $fk['REFERENCED_TABLE_NAME'];
    $referencing_tables[] = $fk['TABLE_NAME'];
    echo "  {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
}

$referenced_tables = array_unique($referenced_tables);
$referencing_tables = array_unique($referencing_tables);

$isolated_tables = array_diff($main_tables, $referenced_tables, $referencing_tables);

echo "\nTables with no foreign key relationships:\n";
foreach ($isolated_tables as $table) {
    $stmt = $conn->query("SELECT COUNT(*) FROM `$table`");
    $count = $stmt->fetchColumn();
    echo "  - $table ($count records)\n";
}

// Summary
echo "\n=== SUMMARY ===\n";
echo "Main tables: " . count($main_tables) . "\n";
echo "Backup tables: " . count($backup_tables) . "\n";
echo "Test tables: " . count($test_tables) . "\n";
echo "Tables with FK relationships: " . count(array_merge($referenced_tables, $referencing_tables)) . "\n";
echo "Isolated tables: " . count($isolated_tables) . "\n";

if (!empty($backup_tables)) {
    echo "\n⚠️  RECOMMENDATION: Consider removing backup tables if no longer needed:\n";
    foreach ($backup_tables as $table) {
        echo "  DROP TABLE `$table`;\n";
    }
}
?>
