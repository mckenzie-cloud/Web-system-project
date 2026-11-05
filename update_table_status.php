<?php
    include("db.php");
    include("auth.php");
    include("rules.php");
    header("Content-Type: application/json");
    requirePermission("tables");

    if (isClosedDay(date("Y-m-d"))) fail(403, "The restaurant is closed on Mondays.");

    $data    = json_decode(file_get_contents("php://input"), true);
    $tableId = (string)($data["table_id"] ?? "");
    $status  = (string)($data["status"] ?? "");

    // "reserved" is never stored: it's worked out from the bookings
    if (!in_array($status, ["available", "occupied", "cleanup"], true)) fail(400, "Invalid status.");

    $chk = $mysqli->prepare("SELECT table_id FROM tables WHERE table_id = ?");
    $chk->bind_param("s", $tableId);
    $chk->execute();
    if (!$chk->get_result()->fetch_row()) fail(404, "Unknown table.");

    $upd = $mysqli->prepare("UPDATE tables SET status = ? WHERE table_id = ?");
    $upd->bind_param("ss", $status, $tableId);
    $upd->execute();

    echo json_encode(["success" => true]);
?>