<?php
    include("db.php");
    header("Content-Type: application/json");

    function fail(int $code, string $msg) {
        http_response_code($code);
        echo json_encode(["error" => $msg]);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) fail(400, "Invalid request.");

    $code  = strtoupper(trim($data["reference_code"] ?? ""));
    $phone = preg_replace('/\D/', '', $data["phone"] ?? "");   // digits only
    $notFound = "We couldn't find an active reservation with that code and contact number.";

    if (!preg_match('/^[0-9A-F]{8}$/', $code) || $phone === "") fail(404, $notFound);

    // find the booking by its code, then check the phone number
    $stmt = $mysqli->prepare(
        "SELECT id, phone, reservation_date, reservation_start_time, reserved_at, payment_status, amount_paid
        FROM reservations
        WHERE reference_code = ? AND cancelled_at IS NULL"
    );
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();

    // one message for both mistakes, so nobody can probe which codes exist
    if (!$r || preg_replace('/\D/', '', $r["phone"]) !== $phone) fail(404, $notFound);

    // ---- 24-hour rule
    $startTs = strtotime(sprintf("%s %02d:00:00", $r["reservation_date"], (int)$r["reservation_start_time"]));
    if ($startTs - time() < 24 * 3600) {
        fail(403, "Cancellations must be made at least 24 hours before the reservation. Please contact the restaurant.");
    }

    // ---- cancel, and refund anything paid online (simulated)
    $id        = (int)$r["id"];
    $paid      = (float)$r["amount_paid"];
    $newStatus = $paid > 0 ? "refunded" : $r["payment_status"];

    $upd = $mysqli->prepare(
        "UPDATE reservations SET cancelled_at = NOW(), payment_status = ?
        WHERE id = ? AND cancelled_at IS NULL"
    );
    $upd->bind_param("si", $newStatus, $id);
    $upd->execute();
    if ($upd->affected_rows === 0) fail(404, $notFound);   // cancelled a moment ago

    echo json_encode(["success" => true, "refund" => $paid]);

?>