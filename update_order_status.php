<?php
    include("db.php");
    include("auth.php");
    header("Content-Type: application/json");
    requirePermission("orders");

    $data = json_decode(file_get_contents("php://input"), true);
    $id   = (int)($data["order_id"] ?? 0);
    $next = (string)($data["status"] ?? "");

    $flow = ["preparing" => "pending", "served" => "preparing"];   // new status => required current status
    if (!isset($flow[$next])) fail(400, "Invalid status.");

    $stmt = $mysqli->prepare(
        "SELECT o.status, o.reservation_id, t.status AS table_status
        FROM orders o JOIN tables t ON t.table_id = o.table_id
        WHERE o.id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();

    if (!$o) fail(404, "Order not found.");
    if ($o["status"] !== $flow[$next]) fail(409, "This order was already updated.");
    if ($next === "preparing" && $o["reservation_id"] !== null && $o["table_status"] !== "occupied") {
        fail(409, "Seat the guests first: set the table to Occupied.");
    }

    $required = $flow[$next];
    $upd = $mysqli->prepare("UPDATE orders SET status = ? WHERE id = ? AND status = ?");
    $upd->bind_param("sis", $next, $id, $required);
    $upd->execute();
    if ($upd->affected_rows === 0) fail(409, "This order was already updated.");

    echo json_encode(["success" => true]);

?>