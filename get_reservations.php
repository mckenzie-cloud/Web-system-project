<?php
    include ("db.php");
    header("Content-Type: application/json");

    $date = $_GET["date"] ?? "";
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode([]);
        exit;
    }

    $stmt = $mysqli->prepare(
        "SELECT table_id,
                reservation_date       AS reserved_date,
                reservation_start_time AS reserved_time_start,
                reservation_end_time   AS reserved_time_end
        FROM reservations
        WHERE reservation_date = ? AND cancelled_at IS NULL"
    );
    $stmt->bind_param("s", $date);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
?>