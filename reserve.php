<?php
    include("db.php");
    include("rules.php");
    header("Content-Type: application/json");

    function fail(int $code, string $msg) {
        http_response_code($code);
        echo json_encode(["error" => $msg]);
        exit;
    }

    // ---------- read the request ----------
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) fail(400, "Invalid request.");

    $name    = trim($data["name"] ?? "");
    $phone   = trim($data["phoneNumber"] ?? "");
    $email   = trim($data["email"] ?? "");
    $tableId = (string)($data["seat"] ?? "");
    $guests  = (int)($data["guests"] ?? 0);
    $date    = (string)($data["date"] ?? "");
    $start   = (int)($data["start_time"] ?? -1);
    $end     = (int)($data["end_time"] ?? -1);
    $method  = $data["payment_method"] ?? "";
    $option  = $data["payment_option"] ?? "";
    $items   = $data["items"] ?? [];

    // ---------- basic validation ----------
    if (empty($data["agreed"]))                          fail(400, "Please accept the terms and conditions.");
    if ($name === "" || strlen($name) > 100)             fail(400, "Please enter your name.");
    if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone))     fail(400, "Please enter a valid contact number.");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      fail(400, "Please enter a valid email address.");

    // ---------- date and time slot ----------
    $d = DateTime::createFromFormat("Y-m-d", $date);
    if (!$d || $d->format("Y-m-d") !== $date)            fail(400, "Invalid date.");
    if ($date < date("Y-m-d"))                           fail(400, "That date has passed.");
    if ((int)date("N", strtotime($date)) === 1)          fail(400, "We are closed on Mondays.");
    if ($start < 10 || $end > 22 || $end - $start !== 2) fail(400, "Invalid time slot.");
    if ($date === date("Y-m-d") && (int)date("G") >= $start) fail(400, "That time slot has already started.");

    // ---------- guests and reservation fee ----------
    $fee = reservationFee($tableId, $guests);
    if ($fee === null) fail(400, "Invalid number of guests for this table.");
    $rule = $RULES[tableCategory($tableId)];

    // ---------- pre-order: prices always come from the database ----------
    $lines = [];
    $food  = 0.0;
    if (!is_array($items) || count($items) > 30) fail(400, "Invalid order.");

    $priceStmt = $mysqli->prepare(
        "SELECT id, name, price FROM menu_items WHERE id = ? AND is_available = 1"
    );

    foreach ($items as $it) {
        $qty    = (int)($it["qty"] ?? 0);
        $itemId = (int)($it["id"] ?? 0);
        if ($qty < 1 || $qty > 20) fail(400, "Invalid quantity.");

        $priceStmt->bind_param("i", $itemId);
        $priceStmt->execute();
        $row = $priceStmt->get_result()->fetch_assoc();
        if (!$row) fail(400, "A menu item is no longer available.");

        $lines[] = ["id" => (int)$row["id"], "name" => $row["name"], "qty" => $qty, "price" => (float)$row["price"]];
        $food += $row["price"] * $qty;
    }
    if ($food < $rule["min_order"]) {
        fail(400, "This table needs a pre-order of at least ₱" . number_format($rule["min_order"]) . ".");
    }

    // ---------- payment (simulated) ----------
    if (!in_array($method, ["online", "onsite"], true)) fail(400, "Please choose a payment method.");

    $total  = max($fee, $food);   // the fee counts toward the food bill
    $paid   = 0.0;
    $status = "unpaid";
    if ($method === "online") {
        if ($option === "full")            { $paid = $total; }
        elseif ($option === "downpayment") { $paid = max($fee, round($total / 2, 2)); }
        else fail(400, "Please choose downpayment or full payment.");
        $status = $paid >= $total ? "paid" : "downpayment";
    }

    // ---------- save everything in one transaction ----------
    $code = strtoupper(bin2hex(random_bytes(4)));   // generate random reference code for cancellation e.g. 9F3A1C7E

    try {
        $mysqli->begin_transaction();

        // lock the table row so two bookings can't pass the overlap check together
        $lock = $mysqli->prepare("SELECT table_id FROM tables WHERE table_id = ? FOR UPDATE");
        $lock->bind_param("s", $tableId);
        $lock->execute();
        if (!$lock->get_result()->fetch_row()) { $mysqli->rollback(); fail(400, "Unknown table."); }

        $check = $mysqli->prepare(
            "SELECT COUNT(*) FROM reservations
            WHERE table_id = ? AND reservation_date = ? AND cancelled_at IS NULL
            AND ? < reservation_end_time AND ? > reservation_start_time"
        );

        $check->bind_param("ssii", $tableId, $date, $start, $end);
        $check->execute();
        if ($check->get_result()->fetch_row()[0] > 0) {
            $mysqli->rollback();
            fail(409, "Sorry, that table was just taken.");
        }

        $ins = $mysqli->prepare(
            "INSERT INTO reservations
            (name, phone, email, table_id, guests, reservation_date,
                reservation_start_time, reservation_end_time, reference_code,
                payment_method, payment_status, amount_paid, total_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param("ssssisiisssdd",
            $name, $phone, $email, $tableId, $guests, $date,
            $start, $end, $code, $method, $status, $paid, $total);
        $ins->execute();
        $reservationId = $mysqli->insert_id;

        if ($lines) {
            // a fully paid booking means the food is paid too
            $orderMethod = $status === "paid" ? "online" : null;
            $orderPaidAt = $status === "paid" ? date("Y-m-d H:i:s") : null;
            $ord = $mysqli->prepare("INSERT INTO orders (table_id, reservation_id, total, payment_method, paid_at) 
                                     VALUES (?, ?, ?, ?, ?)");
            $ord->bind_param("sidss", $tableId, $reservationId, $food, $orderMethod, $orderPaidAt);
            $ord->execute();
            $orderId = $mysqli->insert_id;

            $line = $mysqli->prepare(
                "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)"
            );
            
            foreach ($lines as $l) {
                $line->bind_param("iiid", $orderId, $l["id"], $l["qty"], $l["price"]);
                $line->execute();
            }
        }

        // commit transaction
        $mysqli->commit();

    } catch (Throwable $e) {
        $mysqli->rollback();
        error_log($e->getMessage());
        fail(500, "Something went wrong. Please try again.");
    }

    // ---------- the receipt data ----------
    echo json_encode([
        "success"        => true,
        "reference_code" => $code,
        "name"           => $name,
        "seat"           => $tableId,
        "date"           => $date,
        "start_time"     => $start,
        "end_time"       => $end,
        "guests"         => $guests,
        "items"          => array_map(
            fn($l) => ["name" => $l["name"], "qty" => $l["qty"], "unit_price" => $l["price"]],
            $lines
        ),
        "fee"            => $fee,
        "food_total"     => $food,
        "total_amount"   => $total,
        "amount_paid"    => $paid,
        "balance"        => max(0, $total - $paid),
        "payment_method" => $method,
        "payment_status" => $status,
    ]);

?>