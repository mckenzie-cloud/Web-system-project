<?php
    include("db.php");
    include("auth.php");

    if (!empty($_SESSION["staff_id"])) { header("Location: dashboard.php"); exit; }

    $error = "";
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $username = trim($_POST["username"] ?? "");
        $password = (string)($_POST["password"] ?? "");

        $stmt = $mysqli->prepare("SELECT id, password_hash, role FROM staff WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && password_verify($password, $row["password_hash"])) {
            session_regenerate_id(true);               // new session id after login
            $_SESSION["staff_id"] = (int)$row["id"];
            $_SESSION["role"]     = $row["role"];
            $_SESSION["username"] = $username;
            header("Location: dashboard.php");
            exit;
        }

        usleep(500000);                              // slows down password guessing
        $error = "Incorrect username or password.";
    }
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Bella Vista - Staff Login</title>
    <link rel="stylesheet" href="css/main.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-B/GM4XqrwHnWXNOWMbloTmrYXZg10cakYGmpfsR/bbzQ6JAJI4ihuyADKLnBgrCe"
          crossorigin="anonymous" />
  </head>
  <body>
    <div class="container" style="max-width: 360px; margin-top: 4rem;">
      <h1 class="fs-lg mb-3">Staff login</h1>
      <form method="post" autocomplete="off">
        <div class="mb-3">
          <label for="username" class="form-label">Username</label>
          <input type="text" class="form-control" id="username" name="username" required autofocus />
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required />
        </div>
        <?php if ($error): ?>
          <p class="text-danger" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <button type="submit" class="btn-solid theme-primary">Log in</button>
      </form>
    </div>
  </body>
</html>