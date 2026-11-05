<?php
    include("db.php");
    include("rules.php");
    include("auth.php");
    header("Content-Type: application/json");
    requirePermission("report");

    $rows = $mysqli->query(
        "SELECT table_id, name, guests, reservation_start_time, reservation_end_time,
                payment_status, (cancelled_at IS NOT NULL) AS cancelled
        FROM reservations
        WHERE reservation_date = CURDATE()
        ORDER BY reservation_start_time, table_id"
    )->fetch_all(MYSQLI_ASSOC);

    $byCategory = ["STANDARD" => 0, "FAMILY" => 0, "VIP" => 0];
    $list = [];
    $cancelled = 0;

    foreach ($rows as $r) {
        if ($r["cancelled"]) { $cancelled++; continue; }
        $byCategory[tableCategory($r["table_id"])]++;
        $list[] = [
            "table_id"       => $r["table_id"],
            "name"           => $r["name"],
            "guests"         => (int)$r["guests"],
            "start_time"     => (int)$r["reservation_start_time"],
            "end_time"       => (int)$r["reservation_end_time"],
            "payment_status" => $r["payment_status"],
        ];
    }

    $sales = $mysqli->query(
        "SELECT COUNT(*) AS n, COALESCE(SUM(o.total), 0) AS total
        FROM orders o
        LEFT JOIN reservations r ON r.id = o.reservation_id
        WHERE o.status = 'served'
            AND (o.reservation_id IS NULL OR r.cancelled_at IS NULL)
            AND ( r.reservation_date = CURDATE()
                OR (o.reservation_id IS NULL
                    AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY) )"
    )->fetch_assoc();

    echo json_encode([
        "today"                  => date("Y-m-d"),
        "reservations_active"    => count($list),
        "reservations_cancelled" => $cancelled,
        "by_category"            => $byCategory,
        "orders_served"          => (int)$sales["n"],
        "food_sales"             => (float)$sales["total"],
        "reservations"           => $list,
    ]);
?>