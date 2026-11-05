<?php
    session_set_cookie_params(["httponly" => true, "samesite" => "Lax"]);
    session_start();

    // JSON endpoints: an error status plus a JSON body (no redirects)
    function fail(int $code, string $msg)
    {
        http_response_code($code);
        echo json_encode(["error" => $msg]);
        exit;
    }

    // pages: show your 403 page, with a real 403 status, at the same URL
    function forbidden()
    {
        http_response_code(403);
        require __DIR__ . "/forbidden_403.php";
        exit;
    }

    const PERMISSIONS = [
        "manager" => ["tables", "orders", "report"],
        "kitchen" => ["tables", "orders"],
    ];

    function can(string $permission): bool
    {
        return in_array($permission, PERMISSIONS[$_SESSION["role"] ?? ""] ?? [], true);
    }

    function requireStaff()
    {              // JSON endpoints
        if (empty($_SESSION["staff_id"])) fail(401, "Please log in.");
    }

    function requirePermission(string $permission)
    {
        requireStaff();                      // 401 if not logged in
        if (!can($permission)) fail(403, "You don't have access to this.");
    }

    function requireStaffPage()
    {          // pages: 403 page if not logged in
        if (empty($_SESSION["staff_id"])) forbidden();
    }

    function requirePagePermission(string $permission)
    {   // pages limited to one role
        requireStaffPage();
        if (!can($permission)) forbidden();
    }
?>