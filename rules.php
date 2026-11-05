<?php
    $RULES = [
        "STANDARD" => ["fee" => 0,    "min_pax" => 2, "max_pax" => 4,  "min_order" => 1000],
        "FAMILY"   => ["fee" => 500,  "min_pax" => 5, "max_pax" => 8,  "min_order" => 0],
        "VIP"      => ["fee" => 2500, "min_pax" => 1, "max_pax" => 20, "min_order" => 0,
                        "free_pax" => 15, "extra_pax_fee" => 300],
    ];

    function isClosedDay(string $date): bool {
        return (int)date("N", strtotime($date)) === 1;   // 1 = Monday
    }

    function tableCategory(string $id): string {
        return str_starts_with($id, "VIP") ? "VIP"
            : (str_starts_with($id, "F") ? "FAMILY" : "STANDARD");
    }

    function reservationFee(string $tableId, int $guests): ?float {
        global $RULES;
        $r = $RULES[tableCategory($tableId)];
        if ($guests < $r["min_pax"] || $guests > $r["max_pax"]) return null;
        $extra = max(0, $guests - ($r["free_pax"] ?? $guests)) * ($r["extra_pax_fee"] ?? 0);
        return (float)($r["fee"] + $extra);
    }
?>