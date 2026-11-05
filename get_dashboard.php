<?php
    include("db.php");
    include("auth.php");
    include("rules.php");
    
    header("Content-Type: application/json");
    requirePermission("tables");

    $tables = $mysqli->query("SELECT table_id, status FROM tables")->fetch_all(MYSQLI_ASSOC);

    $reservations = $mysqli->query(
    "SELECT table_id, name, phone, guests, reference_code,
                reservation_start_time, reservation_end_time,
                payment_status, amount_paid, total_amount
        FROM reservations
        WHERE reservation_date = CURDATE() AND cancelled_at IS NULL"
    )->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "today"        => date("Y-m-d"),
        "closed"       => isClosedDay(date("Y-m-d")),   // new
        "hour"         => (int)date("G") + (int)date("i") / 60,
        "tables"       => $tables,
        "reservations" => $reservations,
    ]);
?>