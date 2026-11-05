import * as bootstrap from "https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/js/bootstrap.bundle.min.js";

// ================= constants =================
const timeSlotBlock = 2;
const GRACE_MINUTES = 15;
const STATUS_LABEL = { available: "Available", reserved: "Reserved", occupied: "Occupied", cleanup: "Clean-up" };

const img_width = 1536;  // 1536 px
const img_height = 1024;  // 1024 px
const boxes = [
    ["S1", 203, 143, 308, 248],
    ["S2", 203, 294, 308, 400],
    ["S3", 203, 440, 308, 548],
    ["S4", 203, 588, 308, 695],
    ["F1", 438, 100, 566, 295],
    ["F2", 624, 100, 762, 295],
    ["F3", 562, 404, 797, 540],
    ["VIP1", 884, 98, 1076, 274],
    ["VIP2", 1169, 98, 1361, 274],
];

// ================= helpers =================
const $ = (id) => document.getElementById(id);
const peso = (n) => "₱" + Number(n).toLocaleString("en-PH", { maximumFractionDigits: 2 });
const hour12 = (h) => `${h % 12 || 12}${h < 12 ? "am" : "pm"}`;
const esc = (s) =>
    String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const longDate = (iso) =>
    new Date(iso + "T00:00:00").toLocaleDateString("en-PH", { weekday: "long", year: "numeric", month: "long", day: "numeric" });

// one place for fetch: sends the login back to login.php, turns errors into exceptions
async function api(url, body) {
    const res = await fetch(url, body
        ? { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) }
        : undefined);
    if (res.status === 401) { location.href = "login.php"; throw new Error("Please log in."); }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error ?? "Request failed.");
    return data;
}

function alertMessageCallback(type, message) {
    const wrapper = document.createElement("div");
    wrapper.innerHTML = [
        `<div class="alert theme-${type}" role="alert">`,
        `   <p></p>`,
        '   <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>',
        "</div>",
    ].join("");
    wrapper.querySelector("p").textContent = message;
    $("liveAlertPlaceholder").append(wrapper);
    setTimeout(() => wrapper.remove(), 5000);
}

// ================= tabs and refresh =================
let activeTab = document.querySelector(".dash-tabs button")?.dataset.tab ?? "tables";

function showTab() {
    document.querySelectorAll(".dash-tab").forEach((s) => (s.hidden = s.dataset.tab !== activeTab));
    document.querySelectorAll(".dash-tabs button").forEach((b) =>
        b.classList.toggle("active", b.dataset.tab === activeTab));
}

document.querySelectorAll(".dash-tabs button").forEach((b) =>
    b.addEventListener("click", () => {
        activeTab = b.dataset.tab;
        showTab();
        refresh();
    }));

async function refresh() {
    try {
        if (activeTab === "tables") await loadTables();
        else if (activeTab === "orders") await loadOrders();
        else await loadReport();
        $("syncNote").textContent = "";
    } catch {
        $("syncNote").textContent = "Could not refresh. Retrying…";
    }
}

// ================= tab: tables =================
const selectTimeSlotElement = $("fieldSelectTimeSlot");
const tableDialog = new bootstrap.Dialog($("tableDialog"), { modal: false });

let closed = false;
let tables = [];            // [{ table_id, status }]
let reservations = [];      // today's active bookings
let serverHour = 0;         // e.g. 14.5 = 2:30 pm, from the server's clock
let userPickedSlot = false;
let dialogTableId = null;

boxes.forEach(([id, x1, y1, x2, y2]) => {
    const b = document.querySelector(`.button${id}`);
    b.style.cssText = `left:${x1 / img_width * 100}%;
                        top:${y1 / img_height * 100}%;
                        width:${(x2 - x1) / img_width * 100}%;
                        height:${(y2 - y1) / img_height * 100}%`;
});

function currentSlot() {
    const h = Math.floor(serverHour);
    if (h < 10) return 10;                        // before opening: first slot
    if (h >= 22) return 20;                       // after closing: last slot
    return 10 + Math.floor((h - 10) / 2) * 2;
}
const selectedSlot = () => Number(selectTimeSlotElement.value);
const isLive = () => selectedSlot() === currentSlot();

function bookingFor(tableId, slot) {
    return reservations.find(
        (r) => r.table_id === tableId &&
            slot < r.reservation_end_time &&
            slot + timeSlotBlock > r.reservation_start_time);
}

function displayStatus(table, slot) {
    const booking = bookingFor(table.table_id, slot);
    if (slot === currentSlot()) {
        if (table.status !== "available") return table.status;       // what staff set wins
        if (booking && serverHour < booking.reservation_start_time + GRACE_MINUTES / 60) return "reserved";
        return "available";                                           // no booking, or hold expired
    }
    return booking ? "reserved" : "available";                      // other slots: bookings only
}

function renderTables() {
    const slot = selectedSlot();
    tables.forEach((t) => {
        const btn = document.querySelector(`.button${t.table_id}`);
        if (!btn) return;
        const status = displayStatus(t, slot);
        btn.dataset.status = status;
        btn.title = btn.ariaLabel = `Table ${t.table_id}: ${STATUS_LABEL[status]}`;
    });
}

async function loadTables() {
    const data = await api("get_dashboard.php");

    closed = !!data.closed;
    $("closedBanner").hidden = !closed;

    tables = data.tables;
    serverHour = Number(data.hour);
    reservations = data.reservations.map((r) => ({
        ...r,
        guests: Number(r.guests),
        reservation_start_time: Number(r.reservation_start_time),
        reservation_end_time: Number(r.reservation_end_time),
        amount_paid: Number(r.amount_paid),
        total_amount: Number(r.total_amount),
    }));
    $("todayLabel").textContent = longDate(data.today);
    if (!userPickedSlot) selectTimeSlotElement.value = String(currentSlot());   // follow the clock
    renderTables();
}

selectTimeSlotElement.addEventListener("change", () => { userPickedSlot = true; renderTables(); });
$("nowBtn").addEventListener("click", () => {
    userPickedSlot = false;
    selectTimeSlotElement.value = String(currentSlot());
    renderTables();
});

function bookingHtml(b) {
    if (!b) return `<p class="form-text">No reservation in this time slot.</p>`;
    const balance = b.payment_status === "paid" ? 0 : Math.max(0, b.total_amount - b.amount_paid);
    return `
          <div class="mb-3">
            <strong>${esc(b.name)}</strong> · ${b.guests} guests<br>
            ${esc(b.phone)}<br>
            Code ${esc(b.reference_code)} · ${hour12(b.reservation_start_time)} - ${hour12(b.reservation_end_time)}<br>
            Payment: ${esc(b.payment_status)} · Balance ${peso(balance)}
          </div>`;
}

document.querySelectorAll(".floor-plan button").forEach((btn) => {
    btn.addEventListener("click", () => {
        const t = tables.find((x) => x.table_id === btn.dataset.id);
        if (!t) return;

        const slot = selectedSlot();
        const booking = bookingFor(t.table_id, slot);
        const live = isLive();
        const holdExpired = !!booking && serverHour >= booking.reservation_start_time + GRACE_MINUTES / 60;
        dialogTableId = t.table_id;

        $("tableDialogTitle").textContent = `Table ${t.table_id}`;
        $("tableStatusLabel").textContent = STATUS_LABEL[displayStatus(t, slot)];
        $("tableBooking").innerHTML = bookingHtml(booking);

        document.querySelectorAll("#statusButtons button").forEach((b) => {
            b.disabled = !live || closed || b.dataset.status === t.status;
        });

        $("noShowBtn").hidden = !(live && holdExpired);
        $("noShowBtn").dataset.code = booking?.reference_code ?? "";

        $("statusNote").textContent = closed
            ? "Closed on Mondays: table statuses can't be changed."
            : !live
                ? "Select the current time slot to change a table's status."
                : holdExpired
                    ? "The 15-minute hold has ended. If the guests arrive, you can still seat them."
                    : "";

        tableDialog.show();
    });
});

$("statusButtons").addEventListener("click", async (e) => {
    const btn = e.target.closest("button[data-status]");
    if (!btn || btn.disabled) return;
    try {
        await api("update_table_status.php", { table_id: dialogTableId, status: btn.dataset.status });
        await tableDialog.hide();
        await loadTables();
    } catch (err) {
        alertMessageCallback("danger", err.message);
    }
});

$("noShowBtn").addEventListener("click", async (e) => {
    const code = e.currentTarget.dataset.code;
    if (!code || !confirm(
        "Mark this booking as a no-show? Only do this if the guests did not arrive. " +
        "It is treated as a same-day cancellation: VIP and Family reservation fees are not refunded."
    )) return;

    try {
        const result = await api("mark_no_show.php", { reference_code: code });
        await tableDialog.hide();

        const parts = [];
        if (result.kept > 0) parts.push(`${peso(result.kept)} reservation fee kept`);
        if (result.refund > 0) parts.push(`${peso(result.refund)} to refund`);
        alertMessageCallback("success", "No-show recorded." + (parts.length ? " " + parts.join(", ") + "." : ""));
        await loadTables();
    } catch (err) {
        alertMessageCallback("danger", err.message);
    }
});

// ================= tab: orders =================
let orders = [];
let ordersView = "table";     // "table": one row per table, "status": Pending / Preparing / Served columns
const ORDER_STATUS_LABEL = { pending: "Pending", preparing: "Preparing", served: "Served" };

const isWaiting = (o) => o.status === "pending" && o.table_status !== "occupied";
const itemsHtml = (o) => o.items.map((i) => `<li>${i.quantity} × ${esc(i.name)}</li>`).join("");
const paymentText = (o) => (o.paid ? `Paid (${esc(o.payment_method ?? "")})` : `Balance due ${peso(o.balance)}`);
const reservationLine = (o) => o.start_time != null ? `${hour12(o.start_time)} - ${hour12(o.end_time)} · ${o.guests} guests` : "";

function orderActions(o) {
    if (o.status === "pending") {
        return `<button type="button" class="btn-solid theme-primary" data-action="status" data-next="preparing" ${isWaiting(o) ? "disabled" : ""}>Start preparing</button>`;
    }
    if (o.status === "preparing") {
        return `<button type="button" class="btn-solid theme-primary" data-action="status" data-next="served">Mark served</button>`;
    }
    if (!o.paid) {
        return `
      <button type="button" class="btn-solid theme-primary" data-action="settle" data-method="cash">Paid cash</button>
      <button type="button" class="btn-solid theme-secondary" data-action="settle" data-method="card">Paid card</button>`;
    }
    return "";
}

// ---- by status: cards in three columns
function orderCard(o) {
    return `
    <article class="order-card" data-id="${o.id}">
      <div class="d-flex justify-content-between">
        <strong>Table ${esc(o.table_id)}</strong>
        <span>${esc(o.name ?? "Walk-in")}</span>
      </div>
      <p class="form-text m-0">${reservationLine(o)}</p>
      <ul>${itemsHtml(o)}</ul>
      <div class="d-flex justify-content-between"><span>Total</span><strong>${peso(o.total)}</strong></div>
      <p class="form-text m-0">${paymentText(o)}</p>
      ${isWaiting(o) ? `<p class="form-text m-0">Waiting for the guests to be seated.</p>` : ""}
      <div class="order-actions">${orderActions(o)}</div>
    </article>`;
}

// ---- by table: one row per order, in floor-plan order
function renderOrdersByTable() {
    if (!orders.length) {
        $("ordersByTable").innerHTML = `<p class="form-text">No orders today.</p>`;
        return;
    }

    const tableOrder = boxes.map((b) => b[0]);                      // S1..S4, F1..F3, VIP1, VIP2
    const sorted = [...orders].sort(
        (a, b) => tableOrder.indexOf(a.table_id) - tableOrder.indexOf(b.table_id) || a.id - b.id);

    $("ordersByTable").innerHTML = `
    <div class="table-scroll">
      <table class="orders-table">
        <thead>
          <tr><th>Table</th><th>Reservation</th><th>Food ordered</th><th>Total</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          ${sorted.map((o) => `
            <tr data-id="${o.id}">
              <td><strong>${esc(o.table_id)}</strong></td>
              <td>${esc(o.name ?? "Walk-in")}<br><span class="form-text">${reservationLine(o)}</span></td>
              <td><ul>${itemsHtml(o)}</ul></td>
              <td>${peso(o.total)}<br><span class="form-text">${paymentText(o)}</span></td>
              <td>${ORDER_STATUS_LABEL[o.status]}</td>
              <td>${isWaiting(o) ? `<span class="form-text">Waiting for guests</span><br>` : ""}${orderActions(o)}</td>
            </tr>`).join("")}
        </tbody>
      </table>
    </div>`;
}

function renderOrders() {
    $("ordersBoard").hidden = ordersView !== "status";
    $("ordersByTable").hidden = ordersView !== "table";

    if (ordersView === "table") { renderOrdersByTable(); return; }

    const cols = { pending: [], preparing: [], served: [] };
    orders.forEach((o) => cols[o.status].push(orderCard(o)));
    Object.keys(cols).forEach((s) => {
        $(`col-${s}`).innerHTML = cols[s].join("") || `<p class="form-text">No orders.</p>`;
        $(`count-${s}`).textContent = `(${cols[s].length})`;
    });
}

async function loadOrders() {
    orders = (await api("get_orders.php")).orders;
    renderOrders();
}

// buttons in either view
async function handleOrderAction(e) {
    const btn = e.target.closest("button[data-action]");
    if (!btn || btn.disabled) return;

    const orderId = Number(btn.closest("[data-id]").dataset.id);
    btn.disabled = true;
    try {
        if (btn.dataset.action === "status") {
            await api("update_order_status.php", { order_id: orderId, status: btn.dataset.next });
        } else {
            await api("settle_order.php", { order_id: orderId, method: btn.dataset.method });
        }
    } catch (err) {
        alertMessageCallback("danger", err.message);
    }
    await loadOrders().catch(() => { });
}
$("ordersBoard").addEventListener("click", handleOrderAction);
$("ordersByTable").addEventListener("click", handleOrderAction);

// the view switch
document.querySelectorAll(".view-switch button").forEach((b) =>
    b.addEventListener("click", () => {
        ordersView = b.dataset.view;
        document.querySelectorAll(".view-switch button").forEach((x) => x.classList.toggle("active", x === b));
        renderOrders();
    }));

// ================= tab: daily report =================
async function loadReport() {
    const d = await api("get_report.php");
    $("reportDate").textContent = longDate(d.today);

    $("reportStats").innerHTML = [
        ["Reservations today", d.reservations_active],
        ["Standard / Family / VIP", `${d.by_category.STANDARD} / ${d.by_category.FAMILY} / ${d.by_category.VIP}`],
        ["Cancelled / no-show", d.reservations_cancelled],
        ["Orders served", d.orders_served],
        ["Total food sales", peso(d.food_sales)],
    ].map(([label, value]) =>
        `<div class="stat"><span class="form-text">${esc(label)}</span><strong>${esc(value)}</strong></div>`
    ).join("");

    $("reportRows").innerHTML = d.reservations.length
        ? d.reservations.map((r) => `
              <tr>
                <td>${hour12(r.start_time)} - ${hour12(r.end_time)}</td>
                <td>${esc(r.table_id)}</td>
                <td>${esc(r.name)}</td>
                <td>${r.guests}</td>
                <td>${esc(r.payment_status)}</td>
              </tr>`).join("")
        : `<tr><td colspan="5" class="form-text">No reservations today.</td></tr>`;
}

// ================= go =================
showTab();
await refresh();
setInterval(refresh, 5000);          // real-time: refresh the open tab every 5 seconds