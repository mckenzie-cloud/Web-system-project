<?php
    include("db.php");
    include("auth.php");
    header("Content-Type: application/json");
    requirePermission("orders");

    // today's orders, skipping any whose booking was cancelled or marked a no-show
    $rows = $mysqli->query(
        "SELECT o.id, o.table_id, o.status, o.total, o.payment_method, o.paid_at,
                t.status AS table_status,
                r.name, r.amount_paid, r.guests,
                r.reservation_start_time, r.reservation_end_time,
                m.name AS item_name, oi.quantity
        FROM orders o
        JOIN tables t ON t.table_id = o.table_id
        LEFT JOIN reservations r ON r.id = o.reservation_id
        JOIN order_items oi ON oi.order_id = o.id
        JOIN menu_items m ON m.id = oi.menu_item_id
        WHERE (o.reservation_id IS NULL OR r.cancelled_at IS NULL)
            AND ( r.reservation_date = CURDATE()
            OR (o.reservation_id IS NULL
            AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY) )
        ORDER BY o.created_at, o.id"
    )->fetch_all(MYSQLI_ASSOC);

    $orders = [];
    foreach ($rows as $row) {
        $id = (int)$row["id"];
        if (!isset($orders[$id])) {
            $paid = $row["paid_at"] !== null;
            $orders[$id] = [
                "id"             => $id,
                "table_id"       => $row["table_id"],
                "status"         => $row["status"],
                "table_status"   => $row["table_status"],
                "name"           => $row["name"],                 // null for walk-ins
                "guests"         => $row["guests"] !== null ? (int)$row["guests"] : null,
                "start_time"     => $row["reservation_start_time"] !== null ? (int)$row["reservation_start_time"] : null,
                "end_time"       => $row["reservation_end_time"] !== null ? (int)$row["reservation_end_time"] : null,
                "total"          => (float)$row["total"],
                "paid"           => $paid,
                "payment_method" => $row["payment_method"],
                "balance"        => $paid ? 0 : max(0, (float)$row["total"] - (float)($row["amount_paid"] ?? 0)),
                "items"          => [],
            ];
        }
        $orders[$id]["items"][] = ["name" => $row["item_name"], "quantity" => (int)$row["quantity"]];
    }

    echo json_encode(["orders" => array_values($orders)]);

?>