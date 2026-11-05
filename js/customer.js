import * as bootstrap from "https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/js/bootstrap.bundle.min.js";

// -------------------- constants --------------------
const timeSlotBlock = 2;
const img_width = 1536;  // 1536 px
const img_height = 1024;  // 1024 px

const boxes = [
    ["S1", 203, 143, 308, 248], // S1
    ["S2", 203, 294, 308, 400], // S2
    ["S3", 203, 440, 308, 548], // S3
    ["S4", 203, 588, 308, 695], // S4
    ["F1", 438, 100, 566, 295], // F1
    ["F2", 624, 100, 762, 295], // F2
    ["F3", 562, 404, 797, 540], // F3
    ["VIP1", 884, 98, 1076, 274], // VIP-1
    ["VIP2", 1169, 98, 1361, 274], // VIP-2
];

const guestLimits = {
    S: { min: 2, max: 4, help: "2-4 guests" },
    F: { min: 5, max: 8, help: "5-8 guests" },
    VIP: { min: 1, max: 20, help: "Up to 15 guests included, ₱300 per guest above 15 (max 20)" },
};

// Mirrors rules.php. This only drives the preview; the server recalculates everything.
const RULES = {
    S: { fee: 0, minOrder: 1000 },
    F: { fee: 500, minOrder: 0 },
    VIP: { fee: 2500, minOrder: 0, freePax: 15, extraPaxFee: 300 },
};

// -------------------- helpers --------------------
const peso = (n) => "₱" + Number(n).toLocaleString("en-PH", { maximumFractionDigits: 2 });
const hour12 = (h) => `${h % 12 || 12}${h < 12 ? "am" : "pm"}`;
const esc = (s) =>
    String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

// -------------------- elements --------------------
const alertPlaceholder = document.getElementById("liveAlertPlaceholder");
const dateInputElement = document.getElementById("datepicker2");
const selectTableCategoryElement = document.getElementById("fieldSelectTableCategory");
const selectTimeSlotElement = document.getElementById("fieldSelectTimeSlot");
const buttonElements = document.querySelectorAll(".floor-plan button");
const dialogElement = document.getElementById("seatDialog");
const seatDialog = new bootstrap.Dialog(dialogElement, { modal: false });
const seatFormElement = document.getElementById("seatForm");
const guestsInputElement = document.getElementById("guests");
const guestsHelpElement = document.getElementById("guestsHelp");
const backBtn = document.getElementById("backBtn");
const nextBtn = document.getElementById("nextBtn");
const cancelBtn = document.getElementById("cancelBtn");
const stepLabel = document.getElementById("stepLabel");

// -------------------- state --------------------
let dateSelected;
let tableCategoryIdSelected;
let timeSlotSelected;

let reservations = [];          // bookings for the selected date
let reservationRequestId = 0;   // ignores out-of-order responses

let step = 1;
let menu = [];                  // [{ id, name, items: [{ id, name, description, price }] }]
let itemsById = {};
let cart = {};                  // { menuItemId: quantity }

// -------------------- alerts --------------------
const alertMessageCallback = (type, message) => {
    const wrapper = document.createElement("div");
    wrapper.innerHTML = [
        `<div class="alert theme-${type} m-5" role="alert">`,
        `   <p></p>`,
        '   <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>',
        "</div>",
    ].join("");
    wrapper.querySelector("p").textContent = message;   // plain text, never HTML

    alertPlaceholder.append(wrapper);
    setTimeout(() => wrapper.remove(), 5000);
};

// -------------------- floor plan --------------------
function set_button_positions_label() {
    boxes.forEach(([id, x1, y1, x2, y2]) => {
        const button = document.querySelector(`.button${id}`);
        button.style.cssText = `left:${x1 / img_width * 100}%;
                                top:${y1 / img_height * 100}%;
                                width:${(x2 - x1) / img_width * 100}%;
                                height:${(y2 - y1) / img_height * 100}%`;
        button.style.display = "none";
    });
}

set_button_positions_label();

function hide_button_label_display() {
    for (let i = 0; i < boxes.length; i++) {
        document.querySelector(`.button${boxes[i][0]}`).style.display = "none";
    }
}

function isTableTaken(tableId, date, start, end) {
    return reservations.some(
        (r) =>
            r.table_id === tableId &&
            r.reserved_date === date &&
            start < r.reserved_time_end &&   // new slot starts before existing one ends
            end > r.reserved_time_start,     // new slot ends after existing one starts
    );
}

function display_selected_table() {
    hide_button_label_display();
    if (
        dateSelected === undefined ||
        tableCategoryIdSelected === undefined ||
        timeSlotSelected === undefined
    )
        return;

    const startTimeSlot = Number(timeSlotSelected);
    const endTimeSlot = startTimeSlot + timeSlotBlock;

    boxes
        .filter((box) => box[0].startsWith(tableCategoryIdSelected))
        .forEach((box) => {
            const button = document.querySelector(`.button${box[0]}`);
            button.style.display = "inline-block";

            if (isTableTaken(box[0], dateSelected, startTimeSlot, endTimeSlot)) {
                button.style.setProperty("background-color", "rgba(255, 0, 0, 0.35)", "important");
                button.style.cursor = "not-allowed";
                button.disabled = true;
            } else {
                button.style.setProperty("background-color", "rgba(0, 255, 0, 0.35)", "important");
                button.style.cursor = "pointer";
                button.disabled = false;
            }
        });
}

async function loadReservations(date) {
    if (!date) {
        reservations = [];
        display_selected_table();
        return;
    }

    const myId = ++reservationRequestId;
    try {
        const res = await fetch(`get_reservations.php?date=${encodeURIComponent(date)}`);
        if (!res.ok) throw new Error("load failed");
        const rows = await res.json();
        if (myId !== reservationRequestId) return;   // a newer request replaced this one

        reservations = rows.map((r) => ({
            ...r,
            reserved_time_start: Number(r.reserved_time_start),
            reserved_time_end: Number(r.reserved_time_end),
        }));
    } catch {
        if (myId !== reservationRequestId) return;
        reservations = [];
        alertMessageCallback("danger", "Could not load table availability.");
    }
    display_selected_table();
}

function updateTimeSlots() {
    const now = new Date();
    const isToday = dateSelected === now.toLocaleDateString("en-CA");

    [...selectTimeSlotElement.options].forEach((opt) => {
        if (!opt.value) return;   // placeholder (needs value="" in the HTML)
        opt.disabled = isToday && now.getHours() >= Number(opt.value);
    });

    if (selectTimeSlotElement.selectedOptions[0]?.disabled) {
        selectTimeSlotElement.selectedIndex = 0;
        timeSlotSelected = undefined;
    }
}

// -------------------- filters --------------------
if (dateInputElement.value === "") selectTimeSlotElement.disabled = true;

if (dateInputElement) {
    const datepicker = new bootstrap.Datepicker(dateInputElement, {
        vcpOptions: {
            disableDatesPast: true,
            disableWeekdays: [1],   // closed on Mondays
        },
    });
}

dateInputElement.addEventListener("change.bs.datepicker", async (event) => {
    dateSelected = event.dates[0];
    selectTimeSlotElement.disabled = false;
    updateTimeSlots();
    await loadReservations(dateSelected);
});

selectTableCategoryElement.addEventListener("change", (event) => {
    switch (event.target.value) {
        case "Standard": tableCategoryIdSelected = "S"; break;
        case "Family": tableCategoryIdSelected = "F"; break;
        case "VIP": tableCategoryIdSelected = "VIP"; break;
        default: return;
    }
    display_selected_table();
});

selectTimeSlotElement.addEventListener("change", (event) => {
    timeSlotSelected = event.target.value;
    display_selected_table();
});

// -------------------- opening the dialog --------------------
buttonElements.forEach((button) => {
    button.addEventListener("click", () => {
        seatFormElement.reset();   // reset first, then set values below
        resetMenu();

        const id = button.dataset.id;
        dialogElement.dataset.type = tableCategoryIdSelected;

        const { min, max, help } = guestLimits[tableCategoryIdSelected];
        guestsInputElement.min = min;
        guestsInputElement.max = max;
        guestsInputElement.value = "";
        guestsHelpElement.textContent = help;

        document.getElementById("seatId").value = id;
        document.getElementById("seatDialogTitle").textContent = `Reserve seat ${id}`;
        document.getElementById("dateSelected").textContent = dateSelected;
        document.getElementById("timeSlotSelected").textContent =
            `(${selectTimeSlotElement.selectedOptions[0].text})`;

        showStep(1);
        seatDialog.show();
    });
});

// Clamp to the allowed range while typing or pasting
guestsInputElement.addEventListener("input", () => {
    const min = Number(guestsInputElement.min);
    const max = Number(guestsInputElement.max);
    const value = guestsInputElement.value;

    if (value !== "" && (Number(value) < min || Number(value) <= 0)) guestsInputElement.value = min;
    if (value !== "" && Number(value) > max) guestsInputElement.value = max;
});

// the form is only submitted by submitBooking(), never by the browser
seatFormElement.addEventListener("submit", (e) => e.preventDefault());

// -------------------- steps --------------------
function showStep(n) {
    step = n;
    dialogElement.dataset.step = n;   // CSS uses this to widen the dialog on step 2

    document.querySelectorAll(".booking-step").forEach((el) => {
        el.hidden = Number(el.dataset.step) !== n;
    });

    stepLabel.textContent = n < 4 ? `Step ${n} of 3` : "Booking confirmed";
    backBtn.hidden = n === 1 || n === 4;
    cancelBtn.hidden = n === 4;
    nextBtn.textContent = n === 3 ? "Confirm booking" : n === 4 ? "Done" : "Next";
    updateNextState();
}

function updateNextState() {
  if (step === 2) {
    nextBtn.disabled = cartTotal() < RULES[tableCategoryIdSelected].minOrder;
  } else if (step === 3) {
    nextBtn.disabled = !document.getElementById("agreeTerms").checked;
  } else {
    nextBtn.disabled = false;
  }
}

document.getElementById("agreeTerms").addEventListener("change", updateNextState);

function validateStep(n) {
    if (n === 1) {
        return [...document.querySelectorAll('.booking-step[data-step="1"] input')]
            .every((el) => el.reportValidity());
    }
    if (n === 3) {
        if (!document.querySelector('input[name="payment"]:checked')) {
            alertMessageCallback("danger", "Please choose a payment option.");
            return false;
        }
        if (!document.getElementById("agreeTerms").checked) {
            alertMessageCallback("danger", "Please accept the terms and conditions.");
            return false;
        }
    }
    return true;
}

nextBtn.addEventListener("click", async () => {
    if (step === 4) { await seatDialog.hide(); return; }
    if (!validateStep(step)) return;

    if (step === 1) {
        try {
            await loadMenu();
        } catch {
            alertMessageCallback("danger", "Could not load the menu. Please try again.");
            return;
        }
        renderMenu();
        showStep(2);
        renderCart();
    } else if (step === 2) {
        renderCheckout();
        showStep(3);
    } else if (step === 3) {
        await submitBooking();
    }
});

document.getElementById("agreeTerms").addEventListener("change", updateNextState);
backBtn.addEventListener("click", () => showStep(step - 1));

// -------------------- step 2: menu and cart --------------------
async function loadMenu() {
    if (menu.length) return;   // already loaded, reuse it
    const res = await fetch("get_menu.php");
    if (!res.ok) throw new Error("menu");

    menu = (await res.json())
        .map((c) => ({
            ...c,
            items: c.items.map((i) => ({ ...i, id: Number(i.id), price: Number(i.price) })),
        }))
        .filter((c) => c.items.length);   // skip categories with nothing available

    itemsById = {};
    menu.forEach((c) => c.items.forEach((i) => (itemsById[i.id] = i)));
}

function renderMenu() {
    document.getElementById("menuList").innerHTML = menu
        .map((c) => `
        <h2 class="fs-md">${esc(c.name)}</h2>
        ${c.items.map((i) => `
          <div class="menu-item" data-id="${i.id}">
            <div>
              <strong>${esc(i.name)}</strong>
              ${i.description ? `<div class="form-text">${esc(i.description)}</div>` : ""}
              <div>${peso(i.price)}</div>
            </div>
            <div class="qty">
              <button type="button" data-action="dec" aria-label="Remove one">−</button>
              <span class="qty-value">${cart[i.id] ?? 0}</span>
              <button type="button" data-action="inc" aria-label="Add one">+</button>
            </div>
          </div>`).join("")}`)
        .join("");
}

function resetMenu() {
    cart = {};
    if (menu.length) renderMenu();   // redraws every quantity as 0
}

function cartTotal() {
    return Object.entries(cart).reduce((sum, [id, qty]) => sum + itemsById[id].price * qty, 0);
}

function renderCart() {
    const entries = Object.entries(cart);
    document.getElementById("cartList").innerHTML = entries.length
        ? entries.map(([id, qty]) => `
          <li class="d-flex justify-content-between">
            <span>${qty} × ${esc(itemsById[id].name)}</span>
            <span>${peso(itemsById[id].price * qty)}</span>
          </li>`).join("")
        : `<li class="form-text">Your cart is empty.</li>`;

    const total = cartTotal();
    const min = RULES[tableCategoryIdSelected].minOrder;
    document.getElementById("cartTotal").textContent = peso(total);
    document.getElementById("minOrderNote").textContent = min
        ? total < min
            ? `Add ${peso(min - total)} more to reach the ${peso(min)} minimum order.`
            : "Minimum order reached."
        : "Pre-ordering is optional for this table.";
    updateNextState();
}

document.getElementById("menuList").addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-action]");
    if (!btn) return;

    const row = btn.closest(".menu-item");
    const id = row.dataset.id;
    const qty = (cart[id] ?? 0) + (btn.dataset.action === "inc" ? 1 : -1);

    if (qty <= 0) delete cart[id];
    else cart[id] = Math.min(qty, 20);

    row.querySelector(".qty-value").textContent = cart[id] ?? 0;
    renderCart();
});

// -------------------- step 3: checkout --------------------
function computeAmounts() {
    const r = RULES[tableCategoryIdSelected];
    const guests = Number(guestsInputElement.value);
    const extra = Math.max(0, guests - (r.freePax ?? guests)) * (r.extraPaxFee ?? 0);
    const fee = r.fee + extra;
    const food = cartTotal();
    const total = Math.max(fee, food);
    const down = Math.max(fee, Math.round((total / 2) * 100) / 100);
    return { fee, food, total, down };
}

function renderCheckout() {
    const a = computeAmounts();
    const seat = document.getElementById("seatId").value;
    const slot = selectTimeSlotElement.selectedOptions[0].text;

    document.getElementById("checkoutSummary").innerHTML = `
      <p class="mb-2">Table ${esc(seat)} · ${esc(dateSelected)} · ${esc(slot)} · ${esc(guestsInputElement.value)} guests</p>
      ${a.fee ? `<div class="d-flex justify-content-between"><span>Reservation fee</span><span>${peso(a.fee)}</span></div>` : ""}
      <div class="d-flex justify-content-between"><span>Food pre-order</span><span>${peso(a.food)}</span></div>
      <div class="d-flex justify-content-between mb-1"><strong>Total</strong><strong>${peso(a.total)}</strong></div>
      ${a.fee ? `<p class="form-text">The reservation fee counts toward your food bill, so the total is whichever is higher.</p>` : ""}`;

    const prev = document.querySelector('input[name="payment"]:checked')?.value;
    document.getElementById("paymentOptions").innerHTML = `
      <label class="d-block"><input type="radio" name="payment" value="online-downpayment"> Online downpayment (${peso(a.down)} now)</label>
      <label class="d-block"><input type="radio" name="payment" value="online-full"> Online full payment (${peso(a.total)} now)</label>
      <label class="d-block"><input type="radio" name="payment" value="onsite"> Pay onsite, cash or card (${peso(a.total)} at the restaurant)</label>`;
    if (prev) document.querySelector(`input[name="payment"][value="${prev}"]`).checked = true;
}

async function submitBooking() {
    nextBtn.disabled = true;
    const fd = new FormData(seatFormElement);
    const [method, option] = document.querySelector('input[name="payment"]:checked').value.split("-");

    try {
        const response = await fetch("reserve.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                name: fd.get("name"),
                phoneNumber: fd.get("phoneNumber"),
                email: fd.get("email"),
                guests: Number(fd.get("guests")),
                seat: document.getElementById("seatId").value,
                date: dateSelected,
                start_time: Number(timeSlotSelected),
                end_time: Number(timeSlotSelected) + timeSlotBlock,
                payment_method: method,
                payment_option: option ?? null,
                agreed: document.getElementById("agreeTerms").checked,
                items: Object.entries(cart).map(([id, qty]) => ({ id: Number(id), qty })),
            }),
        });
        const result = await response.json();

        if (response.ok) {
            renderReceipt(result);
            showStep(4);
            resetMenu();
        } else {
            alertMessageCallback("danger", result.error ?? "Could not complete the booking.");
            if (response.status === 409) await seatDialog.hide();   // table was just taken
        }
    } catch {
        alertMessageCallback("danger", "Could not reach the server. Please try again.");
    } finally {
        updateNextState();
        await loadReservations(dateSelected);   // refresh the floor plan
    }
}

// -------------------- step 4: receipt --------------------
function renderReceipt(r) {
    const rows = r.items
        .map((i) => `<tr><td>${i.qty} × ${esc(i.name)}</td><td class="text-end">${peso(i.unit_price * i.qty)}</td></tr>`)
        .join("");

    document.getElementById("receipt").innerHTML = `
      <div class="text-center mb-3">
        <p class="form-text m-0">Your reference code</p>
        <p class="fs-lg m-0"><strong>${esc(r.reference_code)}</strong></p>
        <p class="form-text">Keep this code. You need it to cancel.</p>
      </div>
      <p>${esc(r.name)} · Table ${esc(r.seat)} · ${r.guests} guests<br>
         ${esc(r.date)} · ${hour12(r.start_time)} - ${hour12(r.end_time)}</p>
      <table class="w-100 mb-2">
        ${rows || `<tr><td class="form-text">No food pre-ordered</td><td></td></tr>`}
        <tr><td>Reservation fee</td><td class="text-end">${peso(r.fee)}</td></tr>
        <tr><td><strong>Total</strong></td><td class="text-end"><strong>${peso(r.total_amount)}</strong></td></tr>
        <tr><td>Paid</td><td class="text-end">${peso(r.amount_paid)}</td></tr>
        <tr><td><strong>Balance at the restaurant</strong></td><td class="text-end"><strong>${peso(r.balance)}</strong></td></tr>
      </table>
      <p class="form-text">Your table is held for 15 minutes past your start time. Cancellations must be made at least 24 hours ahead.</p>`;
}

// -------------------- cancel a reservation --------------------
const cancelDialogElement = document.getElementById("cancelDialog");
const cancelDialog = new bootstrap.Dialog(cancelDialogElement, { modal: false });
const cancelFormElement = document.getElementById("cancelForm");
const cancelMessageElement = document.getElementById("cancelMessage");

document.getElementById("openCancelBtn").addEventListener("click", () => {
  cancelFormElement.reset();
  cancelMessageElement.textContent = "";
  cancelDialog.show();
});

cancelFormElement.addEventListener("submit", async (e) => {
  e.preventDefault();
  const btn = document.getElementById("cancelSubmitBtn");
  btn.disabled = true;
  cancelMessageElement.textContent = "";

  try {
    const response = await fetch("cancel_reservation.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        reference_code: document.getElementById("cancelCode").value,
        phone: document.getElementById("cancelPhone").value,
      }),
    });
    const result = await response.json();

    if (response.ok) {
      await cancelDialog.hide();
      alertMessageCallback(
        "success",
        result.refund > 0
          ? `Reservation cancelled. ${peso(result.refund)} will be refunded.`
          : "Reservation cancelled."
      );
      await loadReservations(dateSelected);   // the table becomes available again
    } else {
      cancelMessageElement.textContent = result.error ?? "Could not cancel the reservation.";
    }
  } catch {
    cancelMessageElement.textContent = "Could not reach the server. Please try again.";
  } finally {
    btn.disabled = false;
  }
});