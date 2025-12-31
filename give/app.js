(() => {
  // =========================
  // Helpers: money + total
  // =========================
  function sanitizeMoneyString(raw) {
    if (!raw) return "";
    let v = String(raw).replace(/,/g, "").replace(/[^\d.]/g, "");
    const parts = v.split(".");
    if (parts.length > 2) v = parts[0] + "." + parts.slice(1).join("");
    return v;
  }

  function parseMoney(raw) {
    const clean = sanitizeMoneyString(raw);
    if (!clean) return 0;
    const n = Number(clean);
    return Number.isFinite(n) ? n : 0;
  }

  function updateTotal() {
    const inputs = document.querySelectorAll(".money-input");
    let sum = 0;
    inputs.forEach((inp) => (sum += parseMoney(inp.value)));

    const totalAmountEl = document.getElementById("totalAmount");
    const isInt = Math.abs(sum - Math.round(sum)) < 1e-9;
    totalAmountEl.textContent = isInt ? String(Math.round(sum)) : String(Math.round(sum * 100) / 100);
  }

  function bindMoneyInput(inputEl) {
    inputEl.addEventListener("input", () => {
      const clean = sanitizeMoneyString(inputEl.value);
      if (inputEl.value !== clean) inputEl.value = clean;
      updateTotal();
    });
  }

  // bind existing money inputs
  document.querySelectorAll(".money-input").forEach(bindMoneyInput);
  updateTotal();

  // =========================
  // Add More Fields modal
  // =========================
  const fieldsModal = document.getElementById("fieldsModal");
  const openModalBtn = document.getElementById("openModalBtn");
  const closeModalBtn = document.getElementById("closeModalBtn");
  const cancelModalBtn = document.getElementById("cancelModalBtn");
  const addSelectedBtn = document.getElementById("addSelectedBtn");
  const checklist = document.getElementById("fieldsChecklist");
  const extraFields = document.getElementById("extraFields");

  const addedFieldKeys = new Set(); // prevents duplicates

  function openFieldsModal() {
    fieldsModal.classList.add("is-open");
    fieldsModal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
    checklist.querySelector('input[type="checkbox"]')?.focus();
  }

  function closeFieldsModal() {
    fieldsModal.classList.remove("is-open");
    fieldsModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
    openModalBtn?.focus();
  }

  openModalBtn.addEventListener("click", openFieldsModal);
  closeModalBtn.addEventListener("click", closeFieldsModal);
  cancelModalBtn.addEventListener("click", closeFieldsModal);

  fieldsModal.addEventListener("click", (e) => {
    if (e.target?.dataset?.close === "true") closeFieldsModal();
  });

  // =========================
  // Dynamic field creation + Remove button
  // =========================
  function makeFieldId(label) {
    return "f_" + label.toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "");
  }

  function addField(label) {
    const key = label.trim();
    if (!key) return;
    if (addedFieldKeys.has(key)) return;

    addedFieldKeys.add(key);

    const id = makeFieldId(key);

    const wrapper = document.createElement("div");
    wrapper.className = "field";
    wrapper.dataset.dynamic = "true";

    const top = document.createElement("div");
    top.className = "field__top";

    const lab = document.createElement("label");
    lab.className = "field__label";
    lab.setAttribute("for", id);
    lab.textContent = key;

    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "field__remove";
    removeBtn.textContent = "Remove";

    removeBtn.addEventListener("click", () => {
      wrapper.remove();
      addedFieldKeys.delete(key); // allow re-adding later
      updateTotal();
    });

    top.appendChild(lab);
    top.appendChild(removeBtn);

    const input = document.createElement("input");
    input.className = "field__input money-input";
    input.id = id;
    input.name = id;
    input.type = "text";
    input.placeholder = key;
    input.inputMode = "numeric";
    input.autocomplete = "off";

    wrapper.appendChild(top);
    wrapper.appendChild(input);

    extraFields.appendChild(wrapper);

    bindMoneyInput(input);
    updateTotal();
  }

  addSelectedBtn.addEventListener("click", () => {
    const checked = checklist.querySelectorAll('input[type="checkbox"]:checked');
    checked.forEach((cb) => addField(cb.value));
    checked.forEach((cb) => (cb.checked = false));
    closeFieldsModal();
  });

  // =========================
  // Proceed Payment popup
  // =========================
  const proceedBtn = document.getElementById("proceedBtn");
  const payModal = document.getElementById("payModal");
  const payBackBtn = document.getElementById("payBackBtn");
  const payForm = document.getElementById("payForm");

  function openPayModal() {
    payModal.classList.add("is-open");
    payModal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
    document.getElementById("payerName")?.focus();
  }

  function closePayModal() {
    payModal.classList.remove("is-open");
    payModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
    proceedBtn?.focus();
  }

  proceedBtn.addEventListener("click", openPayModal);
  payBackBtn.addEventListener("click", closePayModal);

  payModal.addEventListener("click", (e) => {
    if (e.target?.dataset?.close === "true") closePayModal();
  });

  // ESC closes whichever modal is open
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (fieldsModal.classList.contains("is-open")) closeFieldsModal();
    if (payModal.classList.contains("is-open")) closePayModal();
  });

  
  //stk push 
 payForm.addEventListener("submit", async (e) => {
  e.preventDefault();

  const name = payerName.value.trim();
  const email = payerEmail.value.trim();
  const phone = payerPhone.value.trim();
  const amount = Number(document.getElementById("totalAmount").textContent);

  if (!name || !phone || amount <= 0) {
    alert("Please enter valid payment details");
    return;
  }

  try {
    const res = await fetch("https://offering.kandiafreshtz.org/give/mpesa/stkpush.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email, phone, amount })
    });

    const data = await res.json();

    if (data.ResponseCode === "0") {
      alert("STK Push sent! Please check your phone.");
      closePayModal();
    } else {
      alert(data.ResponseDescription || "Failed to initiate payment");
    }
  } catch (err) {
    console.error(err);
    alert("Error connecting to payment service");
  }
});

})();


