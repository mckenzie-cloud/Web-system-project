<?php
    include("db.php");
    include("auth.php");
    header("Content-Type: application/json");
    requirePermission("orders");

    $data   = json_decode(file_get_contents("php://input"), true);
    $id     = (int)($data["order_id"] ?? 0);
    $method = (string)($data["method"] ?? "");
    if (!in_array($method, ["cash", "card"], true)) fail(400, "Choose cash or card.");

    $stmt = $mysqli->prepare("SELECT status, paid_at FROM orders WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();

    if (!$o) fail(404, "Order not found.");
    if ($o["status"] !== "served") fail(409, "Mark the order as served first.");
    if ($o["paid_at"] !== null) fail(409, "This order is already paid.");

    $upd = $mysqli->prepare("UPDATE orders SET payment_method = ?, paid_at = NOW() WHERE id = ? AND paid_at IS NULL");
    $upd->bind_param("si", $method, $id);
    $upd->execute();

    // the booking's own payment is now settled too
    $mysqli->query(
        "UPDATE reservations r JOIN orders o ON o.reservation_id = r.id
        SET r.payment_status = 'paid'
        WHERE o.id = " . $id . " AND r.payment_status IN ('unpaid', 'downpayment')"
    );

    echo json_encode(["success" => true]);
?>