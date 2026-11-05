<?php
include("auth.php");
requireStaffPage();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Bella Vista - Staff Dashboard</title>
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/admin_dashboard.css" />
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-B/GM4XqrwHnWXNOWMbloTmrYXZg10cakYGmpfsR/bbzQ6JAJI4ihuyADKLnBgrCe"
    crossorigin="anonymous" />
</head>

<body>
  <div class="container">

    <header class="d-flex justify-content-between align-items-center py-3">
      <h1 class="fs-lg m-0">Bella Vista · Staff</h1>
      <div class="d-flex align-items-center gap-3">
        <?php $roleLabel = ["manager" => "Manager", "kitchen" => "Kitchen staff"][$_SESSION["role"] ?? ""] ?? ""; ?>
        <span><?= htmlspecialchars($_SESSION["username"] ?? "") ?> · <?= htmlspecialchars($roleLabel) ?></span>
        <a href="logout.php" class="btn-solid theme-secondary">Log out</a>
      </div>
    </header>

    <nav class="dash-tabs">
      <?php if (can("tables")): ?><button type="button" data-tab="tables">Tables</button><?php endif; ?>
      <?php if (can("orders")): ?><button type="button" data-tab="orders">Orders</button><?php endif; ?>
      <?php if (can("report")): ?><button type="button" data-tab="report">Daily report</button><?php endif; ?>
    </nav>

    <p class="closed-banner" id="closedBanner" role="status" hidden>
      The restaurant is closed on Mondays. Table status changes are disabled today.
    </p>

    <div class="d-flex justify-content-center">
      <div id="liveAlertPlaceholder"></div>
    </div>
    <p class="form-text text-center" id="syncNote"></p>

    <!-- ===== TAB: tables ===== -->
    <section class="dash-tab" data-tab="tables">
      <div class="field-row">
        <div class="form-field">
          <div class="form-label">Today</div>
          <strong id="todayLabel"></strong>
        </div>
        <div class="form-field">
          <label for="fieldSelectTimeSlot" class="form-label">Time Slot</label>
          <select class="form-control" id="fieldSelectTimeSlot">
            <option value="10">10am - 12pm</option>
            <option value="12">12pm - 2pm</option>
            <option value="14">2pm - 4pm</option>
            <option value="16">4pm - 6pm</option>
            <option value="18">6pm - 8pm</option>
            <option value="20">8pm - 10pm</option>
          </select>
        </div>
        <button type="button" class="btn-solid theme-secondary" id="nowBtn">Now</button>
      </div>

      <div class="d-flex justify-content-center align-items-center gap-4 m-3 flex-wrap">
        <span><i class="dot" data-status="available"></i> Available</span>
        <span><i class="dot" data-status="reserved"></i> Reserved</span>
        <span><i class="dot" data-status="occupied"></i> Occupied</span>
        <span><i class="dot" data-status="cleanup"></i> Clean-up</span>
      </div>

      <div class="d-flex justify-content-center">
        <div class="floor-plan" id="floor-plan">
          <img src="images/Floor Plan.png" alt="" />
          <button class="buttonS1" data-id="S1"></button>
          <button class="buttonS2" data-id="S2"></button>
          <button class="buttonS3" data-id="S3"></button>
          <button class="buttonS4" data-id="S4"></button>
          <button class="buttonF1" data-id="F1"></button>
          <button class="buttonF2" data-id="F2"></button>
          <button class="buttonF3" data-id="F3"></button>
          <button class="buttonVIP1" data-id="VIP1"></button>
          <button class="buttonVIP2" data-id="VIP2"></button>
        </div>
      </div>
    </section>

    <!-- ===== TAB: orders ===== -->
    <section class="dash-tab" data-tab="orders" hidden>
      <div class="view-switch">
        <button type="button" data-view="table" class="active">By table</button>
        <button type="button" data-view="status">By status</button>
      </div>

      <div id="ordersByTable"></div>

      <div class="orders-board" id="ordersBoard" hidden>
        <div class="orders-col">
          <h2 class="fs-md">Pending <span id="count-pending"></span></h2>
          <div id="col-pending"></div>
        </div>
        <div class="orders-col">
          <h2 class="fs-md">Preparing <span id="count-preparing"></span></h2>
          <div id="col-preparing"></div>
        </div>
        <div class="orders-col">
          <h2 class="fs-md">Served <span id="count-served"></span></h2>
          <div id="col-served"></div>
        </div>
      </div>
    </section>

    <!-- ===== TAB: daily report ===== -->
    <section class="dash-tab" data-tab="report" hidden>
      <h2 class="fs-md" id="reportDate"></h2>
      <div class="stat-grid" id="reportStats"></div>

      <h3 class="fs-md">Today's reservations</h3>
      <div style="overflow-x: auto;">
        <table class="w-100" id="reportTable">
          <thead>
            <tr>
              <th>Time</th>
              <th>Table</th>
              <th>Name</th>
              <th>Guests</th>
              <th>Payment</th>
            </tr>
          </thead>
          <tbody id="reportRows"></tbody>
        </table>
      </div>
    </section>

  </div>

  <!-- table status dialog -->
  <dialog class="dialog" id="tableDialog" data-bs-theme="light" aria-labelledby="tableDialogTitle">
    <div class="dialog-header">
      <h1 class="dialog-title" id="tableDialogTitle">Table</h1>
      <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="Close"></button>
    </div>
    <div class="dialog-body">
      <p class="mb-2">Status: <strong id="tableStatusLabel"></strong></p>
      <div id="tableBooking"></div>

      <div class="d-flex flex-wrap gap-2" id="statusButtons">
        <button type="button" class="btn-solid theme-secondary" data-status="available">Available</button>
        <button type="button" class="btn-solid theme-secondary" data-status="occupied">Occupied</button>
        <button type="button" class="btn-solid theme-secondary" data-status="cleanup">Clean-up</button>
      </div>

      <button type="button" class="btn-solid theme-secondary mt-3" id="noShowBtn" hidden>Mark as no-show</button>
      <p class="form-text mt-2" id="statusNote"></p>
    </div>
  </dialog>

  <script type="module" src="js/admin_dashboard_script.js">

  </script>
</body>

</html>