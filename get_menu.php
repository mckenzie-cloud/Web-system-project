<?php
    include("db.php");

    $cats = $mysqli
    ->query("SELECT id, name FROM menu_categories ORDER BY id")
    ->fetch_all(MYSQLI_ASSOC);

    $items = $mysqli
    ->query("SELECT id, category_id, name, description, price
            FROM menu_items WHERE is_available = 1 ORDER BY id")
    ->fetch_all(MYSQLI_ASSOC);

    foreach ($cats as &$c) {
    $c["items"] = array_values(array_filter($items, fn($i) => $i["category_id"] == $c["id"]));
    }
    unset($c);

    header("Content-Type: application/json");
    echo json_encode($cats);
?>