<?php
function fetchRows(PDO $pdo, string $table, array $select, array $conditions = [], array $order = [], int $limit = 12): array {
    $columns = implode(', ', array_map(fn($col) => preg_replace('/[^a-zA-Z0-9_]/', '', $col), $select));
    
    $where_sql = '';
    $params = [];
    if (!empty($conditions)) {
        $clauses = [];
        foreach ($conditions as $column => $value) {
            $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
            $clauses[] = "$safeColumn = :$safeColumn";
            $params[":$safeColumn"] = $value;
        }
        $where_sql = 'WHERE ' . implode(' AND ', $clauses);
    }
    
    $order_sql = '';
    if (!empty($order)) {
        $orders = [];
        foreach ($order as $col => $dir) {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
            $orders[] = "$safeCol $dir";
        }
        $order_sql = 'ORDER BY ' . implode(', ', $orders);
    }
    
    $sql = "SELECT $columns FROM $table $where_sql $order_sql LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}
?>
