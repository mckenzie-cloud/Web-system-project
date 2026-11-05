<?php
    include("db.php");
    include("rules.php");
    include("auth.php");
    header("Content-Type: application/json");
    requirePermission("tables");

    $data = json_decode(file_get_contents("php://input"), true);
    $code = strtoupper(trim($data["reference_code"] ?? ""));

    $stmt = $mysqli->prepare(
        "SELECT id, table_id, guests, reservation_start_time, amount_paid, payment_status
        FROM reservations
        WHERE reference_code = ? AND reservation_date = CURDATE() AND cancelled_at IS NULL"
    );
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if (!$r) fail(404, "Booking not found.");

    // only after the 15-minute hold has ended (server clock)
    $nowHour = (int)date("G") + (int)date("i") / 60;
    if ($nowHour < (int)$r["reservation_start_time"] + 0.25) fail(409, "The 15-minute hold hasn't ended yet.");

    // same-day cancellation: VIP and Family keep the reservation fee, food payments are refunded
    $paid = (float)$r["amount_paid"];
    $kept = 0.0;
    if (tableCategory($r["table_id"]) !== "STANDARD") {
        $kept = min($paid, reservationFee($r["table_id"], (int)$r["guests"]) ?? 0);
    }
    $refund = $paid - $kept;
    $status = $paid > 0 ? ($kept > 0 ? "forfeited" : "refunded") : $r["payment_status"];

    $id  = (int)$r["id"];
    $upd = $mysqli->prepare(
        "UPDATE reservations SET cancelled_at = NOW(), payment_status = ? WHERE id = ? AND cancelled_at IS NULL"
    );
    $upd->bind_param("si", $status, $id);
    $upd->execute();

    echo json_encode(["success" => true, "kept" => $kept, "refund" => $refund]);
    ?>