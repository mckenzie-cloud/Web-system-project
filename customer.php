<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Bella Vista - Table Status</title>
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/customer.css" />
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-B/GM4XqrwHnWXNOWMbloTmrYXZg10cakYGmpfsR/bbzQ6JAJI4ihuyADKLnBgrCe"
    crossorigin="anonymous" />
</head>

<body>
  <div class="container divide-y">
    <!-- Alert message -->
    <div class="d-flex justify-content-center align-items-center">
      <div id="liveAlertPlaceholder"></div>
    </div>
    <div class="field-row">

      <div class="form-field my-2">
        <label for="datepicker2" class="form-label">Select Date</label>
        <input
          type="text"
          class="form-control"
          id="datepicker2"
          data-bs-toggle="datepicker"
          autocomplete="off"
          placeholder="Choose date…" />
      </div>

      <div class="form-field my-2">
        <label for="fieldSelectTableCategory" class="form-label">Table Category</label>
        <select
          class="form-control"
          id="fieldSelectTableCategory"
          autocomplete="off">
          <option value="" selected disabled>Choose table…</option>
          <option value="Standard">Standard Table</option>
          <option value="Family">Family Table</option>
          <option value="VIP">VIP Room</option>
        </select>
      </div>

      <div class="form-field my-2">
        <label for="fieldSelectTimeSlot" class="form-label">Time Slot (10am-10pm)</label>
        <select
          class="form-control"
          id="fieldSelectTimeSlot"
          autocomplete="off">
          <option value="" selected disabled>Choose slot…</option>
          <option value="10">10am - 12pm</option>
          <option value="12">12pm - 2pm</option>
          <option value="14">2pm - 4pm</option>
          <option value="16">4pm - 6pm</option>
          <option value="18">6pm - 8pm</option>
          <option value="20">8pm - 10pm</option>
        </select>
      </div>

    </div>
    <div class="gap-3">
      <div class="d-flex justify-content-center align-items-center gap-4 my-2 flex-wrap">
        <span><i class="dot" data-status="available"></i> Available</span>
        <span><i class="dot" data-status="reserved"></i> Reserved</span>
      </div>
      <div class="d-flex justify-content-center my-2">
        <button type="button" class="btn-solid theme-secondary" id="openCancelBtn">
          Cancel a reservation
        </button>
      </div>
    </div>
    <div>
      <p class="text-center my-2">Click an <strong>available</strong> (green) table or seat on the floor plan below to reserve.</p>
    </div>
    <div class="d-flex justify-content-center align-items-center">
      <div class="floor-plan my-2" id="floor-plan">
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
  </div>

  <!-- Cancellation Dialog -->
  <dialog class="dialog" id="cancelDialog" data-bs-theme="light" aria-labelledby="cancelDialogTitle">
    <form id="cancelForm">
      <div class="dialog-header">
        <h1 class="dialog-title" id="cancelDialogTitle">Cancel a reservation</h1>
        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="Close"></button>
      </div>

      <div class="dialog-body">
        <div class="mb-3">
          <label for="cancelCode" class="form-label">Reference code</label>
          <input type="text" class="form-control" id="cancelCode"
            maxlength="8" autocomplete="off" placeholder="e.g. 9F3A1C7E" required />
        </div>
        <div class="mb-3">
          <label for="cancelPhone" class="form-label">Contact number used when booking</label>
          <input type="text" class="form-control" id="cancelPhone" required />
        </div>
        <p class="form-text">
          Cancellations must be made at least 24 hours before your reservation time.
        </p>
        <p class="text-danger" id="cancelMessage" role="alert"></p>
      </div>

      <div class="dialog-footer">
        <button type="button" class="btn-solid theme-secondary" data-bs-dismiss="dialog">Keep booking</button>
        <button type="submit" class="btn-solid theme-primary" id="cancelSubmitBtn">Cancel reservation</button>
      </div>
    </form>
  </dialog>

  <dialog class="dialog w-fit" id="seatDialog" data-bs-theme="light" data-step="1" aria-labelledby="seatDialogTitle">
    <form id="seatForm">
      <div class="dialog-header">
        <div>
          <h1 class="dialog-title" id="seatDialogTitle">Reserve seat</h1>
          <p class="form-text m-0" id="stepLabel">Step 1 of 3</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="Close"></button>
      </div>

      <div class="dialog-body">

        <!-- STEP 1: details (your existing fields, unchanged) -->
        <section class="booking-step" data-step="1">
          <input type="hidden" name="seat_id" id="seatId" />

          <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" name="name" class="form-control" id="name" required />
          </div>
          <div class="mb-3">
            <label for="phoneNumber" class="form-label">Contact Number</label>
            <input
              type="text"
              name="phoneNumber"
              class="form-control"
              id="phoneNumber"
              placeholder="e.g. 09669711929"
              required />
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" name="email" class="form-control" id="email" required />
          </div>
          <div class="mb-3">
            <label for="guests" class="form-label">Number of guests</label>
            <input
              type="number"
              name="guests"
              class="form-control"
              id="guests"
              min="1"
              step="1"
              inputmode="numeric"
              required />
            <div class="form-text" id="guestsHelp"></div>
          </div>
          <div class="mb-3 d-flex justify-content-center align-items-center">Reserved Date:</div>
          <div class="mb-3 d-flex justify-content-center align-items-center gap-3">
            <p class="fs-md text" id="dateSelected"></p>
            <p class="fs-md text" id="timeSlotSelected"></p>
          </div>
        </section>

        <!-- STEP 2: menu and cart -->
        <section class="booking-step" data-step="2" hidden>
          <div class="menu-layout">
            <div class="menu-panel" id="menuList"></div>

            <aside class="cart-panel">
              <h2 class="fs-md">Your order</h2>
              <ul id="cartList"></ul>
              <div class="d-flex justify-content-between">
                <span>Total</span>
                <strong id="cartTotal">₱0</strong>
              </div>
              <p class="form-text" id="minOrderNote"></p>
            </aside>
          </div>
        </section>

        <!-- STEP 3: checkout -->
        <section class="booking-step" data-step="3" hidden>
          <div class="mb-3" id="checkoutSummary"></div>

          <div class="mb-3">
            <label class="form-label">Payment</label>
            <div id="paymentOptions"></div>
          </div>

          <div class="terms-box mb-2">
            <p><strong>1. Reservation &amp; grace period.</strong> Reserved tables are held for a maximum of 15 minutes past the scheduled time, then released to walk-in guests.</p>
            <p><strong>2. Cancellation.</strong> Cancellations must be made at least 24 hours prior. Reservation fees for VIP and Family tables are non-refundable if cancelled on the day itself.</p>
            <p><strong>3. Outside food.</strong> Bringing outside food and beverages is strictly prohibited inside the dining premises.</p>
            <p><strong>4. Billing &amp; payment.</strong> Pay online (downpayment or full payment) or onsite by cash or card after your meal.</p>
          </div>

          <label>
            <input type="checkbox" id="agreeTerms">
            I agree to the terms and conditions
          </label>
        </section>

        <!-- STEP 4: receipt -->
        <section class="booking-step" data-step="4" hidden>
          <div id="receipt"></div>
        </section>

      </div>

      <div class="dialog-footer">
        <button type="button" class="btn-solid theme-secondary" id="cancelBtn" data-bs-dismiss="dialog">Cancel</button>
        <button type="button" class="btn-solid theme-secondary" id="backBtn" hidden>Back</button>
        <button type="button" class="btn-solid theme-primary" id="nextBtn">Next</button>
      </div>
    </form>
  </dialog>
  <script type="module" src="js/customer.js"></script>
</body>

</html>