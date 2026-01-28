const ADMIN_TOKEN_KEY = "kekcounter.adminToken";

const adminContent = document.getElementById("adminContent");
const categoryName = document.getElementById("categoryName");
const categoryActive = document.getElementById("categoryActive");
const categoryAdd = document.getElementById("categoryAdd");
const categoryStatus = document.getElementById("categoryStatus");

const itemCategory = document.getElementById("itemCategory");
const itemName = document.getElementById("itemName");
const itemPrice = document.getElementById("itemPrice");
const itemActive = document.getElementById("itemActive");
const itemAdd = document.getElementById("itemAdd");
const itemStatus = document.getElementById("itemStatus");

const menuList = document.getElementById("menuList");
const menuStatus = document.getElementById("menuStatus");

function getAdminToken() {
  try {
    return localStorage.getItem(ADMIN_TOKEN_KEY) || "";
  } catch (error) {
    return "";
  }
}

function setStatus(el, message, isError = false) {
  if (!el) {
    return;
  }
  el.textContent = message;
  el.classList.toggle("text-danger", isError);
  el.classList.toggle("text-secondary", !isError);
}

function setButtonDisabled(button, disabled) {
  if (!button) {
    return;
  }
  button.disabled = disabled;
  button.setAttribute("aria-busy", disabled ? "true" : "false");
}

async function apiRequest(action, payload) {
  const token = getAdminToken();
  if (!token) {
    throw new Error("Admin token missing");
  }
  const response = await fetch(`menu.php?action=${action}`, {
    method: "POST",
    cache: "no-store",
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "fetch",
      "X-Admin-Token": token,
    },
    body: JSON.stringify(payload || {}),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const message = data?.error || "Request failed";
    throw new Error(message);
  }
  return data;
}

async function fetchMenu() {
  const response = await fetch("menu.php?action=get_menu", {
    method: "POST",
    cache: "no-store",
    headers: {
      "X-Requested-With": "fetch",
    },
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const message = data?.error || "Request failed";
    throw new Error(message);
  }
  return data;
}

function renderMenu(menu) {
  if (!menuList) {
    return;
  }
  menuList.innerHTML = "";
  const categories = Array.isArray(menu?.categories) ? menu.categories : [];
  const items = Array.isArray(menu?.items) ? menu.items : [];
  const itemsByCategory = new Map();
  items.forEach((item) => {
    const categoryId = item.category_id || "";
    if (!itemsByCategory.has(categoryId)) {
      itemsByCategory.set(categoryId, []);
    }
    itemsByCategory.get(categoryId).push(item);
  });
  if (!categories.length) {
    const empty = document.createElement("div");
    empty.className = "text-secondary small";
    empty.textContent = "Noch keine Kategorien angelegt.";
    menuList.appendChild(empty);
    return;
  }
  categories.forEach((category) => {
    const card = document.createElement("div");
    card.className = "card border-0 shadow-sm";
    card.dataset.categoryId = category.id || "";
    const body = document.createElement("div");
    body.className = "card-body";

    const header = document.createElement("div");
    header.className = "d-flex flex-column flex-md-row align-items-start align-items-md-center gap-2 mb-3";
    const titleWrap = document.createElement("div");
    titleWrap.className = "flex-grow-1";
    const titleLabel = document.createElement("label");
    titleLabel.className = "form-label small text-secondary";
    titleLabel.textContent = "Kategorie";
    const title = document.createElement("input");
    title.className = "form-control form-control-sm js-category-name";
    title.value = category.name || "";
    titleWrap.appendChild(titleLabel);
    titleWrap.appendChild(title);
    const actions = document.createElement("div");
    actions.className = "d-flex align-items-center gap-2";
    const activeWrap = document.createElement("div");
    activeWrap.className = "form-check";
    const activeInput = document.createElement("input");
    activeInput.className = "form-check-input js-category-active";
    activeInput.type = "checkbox";
    activeInput.checked = Boolean(category.active);
    const activeLabel = document.createElement("label");
    activeLabel.className = "form-check-label";
    activeLabel.textContent = "Aktiv";
    activeWrap.appendChild(activeInput);
    activeWrap.appendChild(activeLabel);
    const saveBtn = document.createElement("button");
    saveBtn.type = "button";
    saveBtn.className = "btn btn-outline-primary btn-sm";
    saveBtn.dataset.action = "save-category";
    saveBtn.textContent = "Speichern";
    actions.appendChild(activeWrap);
    actions.appendChild(saveBtn);
    header.appendChild(titleWrap);
    header.appendChild(actions);
    const badge = document.createElement("span");
    badge.className = `badge ${category.active ? "text-bg-success" : "text-bg-secondary"} align-self-start`;
    badge.textContent = category.active ? "Aktiv" : "Inaktiv";
    header.appendChild(badge);
    body.appendChild(header);

    const categoryItems = itemsByCategory.get(category.id || "") || [];
    if (!categoryItems.length) {
      const empty = document.createElement("div");
      empty.className = "text-secondary small";
      empty.textContent = "Keine Artikel.";
      body.appendChild(empty);
    } else {
      const list = document.createElement("div");
      list.className = "d-flex flex-column gap-2";
      categoryItems.forEach((item) => {
        const row = document.createElement("div");
        row.className = "border rounded px-3 py-2 bg-light";
        row.dataset.itemId = item.id || "";
        const rowGrid = document.createElement("div");
        rowGrid.className = "row g-2 align-items-center";

        const colName = document.createElement("div");
        colName.className = "col-12 col-md-5";
        const nameLabel = document.createElement("label");
        nameLabel.className = "form-label small text-secondary";
        nameLabel.textContent = "Artikel";
        const nameInput = document.createElement("input");
        nameInput.className = "form-control form-control-sm js-item-name";
        nameInput.value = item.name || "";
        colName.appendChild(nameLabel);
        colName.appendChild(nameInput);

        const colPrice = document.createElement("div");
        colPrice.className = "col-12 col-md-3";
        const priceLabel = document.createElement("label");
        priceLabel.className = "form-label small text-secondary";
        priceLabel.textContent = "Preis";
        const priceInput = document.createElement("input");
        priceInput.className = "form-control form-control-sm js-item-price";
        priceInput.value = item.price || "0.00";
        colPrice.appendChild(priceLabel);
        colPrice.appendChild(priceInput);

        const colActions = document.createElement("div");
        colActions.className = "col-12 col-md-4 d-flex flex-wrap align-items-center gap-2";
        const itemActiveWrap = document.createElement("div");
        itemActiveWrap.className = "form-check";
        const itemActive = document.createElement("input");
        itemActive.className = "form-check-input js-item-active";
        itemActive.type = "checkbox";
        itemActive.checked = Boolean(item.active);
        const itemActiveLabel = document.createElement("label");
        itemActiveLabel.className = "form-check-label";
        itemActiveLabel.textContent = "Aktiv";
        itemActiveWrap.appendChild(itemActive);
        itemActiveWrap.appendChild(itemActiveLabel);
        const itemSave = document.createElement("button");
        itemSave.type = "button";
        itemSave.className = "btn btn-outline-primary btn-sm";
        itemSave.dataset.action = "save-item";
        itemSave.textContent = "Speichern";
        colActions.appendChild(itemActiveWrap);
        colActions.appendChild(itemSave);

        rowGrid.appendChild(colName);
        rowGrid.appendChild(colPrice);
        rowGrid.appendChild(colActions);
        row.appendChild(rowGrid);
        list.appendChild(row);
      });
      body.appendChild(list);
    }

    card.appendChild(body);
    menuList.appendChild(card);
  });
}

function populateCategorySelect(menu) {
  if (!itemCategory) {
    return;
  }
  itemCategory.innerHTML = "";
  const categories = Array.isArray(menu?.categories) ? menu.categories : [];
  categories.forEach((category) => {
    const option = document.createElement("option");
    option.value = category.id || "";
    option.textContent = category.name || "Kategorie";
    itemCategory.appendChild(option);
  });
}

async function loadMenu() {
  if (!menuStatus) {
    return;
  }
  setStatus(menuStatus, "Lade Menue...");
  try {
    const menu = await fetchMenu();
    renderMenu(menu);
    populateCategorySelect(menu);
    setStatus(menuStatus, "Menue geladen.");
  } catch (error) {
    setStatus(menuStatus, error.message || "Menue konnte nicht geladen werden.", true);
  }
}

async function addCategory() {
  if (!categoryName || !categoryActive || !categoryAdd) {
    return;
  }
  const name = categoryName.value.trim();
  if (!name) {
    setStatus(categoryStatus, "Bitte Namen eingeben.", true);
    return;
  }
  setButtonDisabled(categoryAdd, true);
  try {
    await apiRequest("add_category", {
      name,
      active: categoryActive.checked,
    });
    categoryName.value = "";
    categoryActive.checked = true;
    setStatus(categoryStatus, "Kategorie gespeichert.");
    await loadMenu();
  } catch (error) {
    setStatus(categoryStatus, error.message || "Speichern fehlgeschlagen.", true);
  } finally {
    setButtonDisabled(categoryAdd, false);
  }
}

async function addItem() {
  if (!itemCategory || !itemName || !itemPrice || !itemActive || !itemAdd) {
    return;
  }
  const categoryId = itemCategory.value;
  const name = itemName.value.trim();
  const price = itemPrice.value.trim();
  if (!categoryId || !name) {
    setStatus(itemStatus, "Bitte Kategorie und Namen angeben.", true);
    return;
  }
  setButtonDisabled(itemAdd, true);
  try {
    await apiRequest("add_item", {
      categoryId,
      name,
      price,
      active: itemActive.checked,
    });
    itemName.value = "";
    itemPrice.value = "";
    itemActive.checked = true;
    setStatus(itemStatus, "Artikel gespeichert.");
    await loadMenu();
  } catch (error) {
    setStatus(itemStatus, error.message || "Speichern fehlgeschlagen.", true);
  } finally {
    setButtonDisabled(itemAdd, false);
  }
}

if (categoryAdd) {
  categoryAdd.addEventListener("click", () => {
    addCategory();
  });
}

if (itemAdd) {
  itemAdd.addEventListener("click", () => {
    addItem();
  });
}

if (adminContent) {
  const observer = new MutationObserver(() => {
    if (!adminContent.classList.contains("d-none")) {
      loadMenu();
    }
  });
  observer.observe(adminContent, { attributes: true, attributeFilter: ["class"] });
  if (!adminContent.classList.contains("d-none")) {
    loadMenu();
  }
}

if (menuList) {
  menuList.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) {
      return;
    }
    const action = button.dataset.action;
    if (action === "save-category") {
      const card = button.closest("[data-category-id]");
      if (!card) {
        return;
      }
      const nameInput = card.querySelector(".js-category-name");
      const activeInput = card.querySelector(".js-category-active");
      const id = card.dataset.categoryId || "";
      const name = nameInput ? nameInput.value.trim() : "";
      const active = activeInput ? activeInput.checked : false;
      if (!id || !name) {
        setStatus(menuStatus, "Bitte Kategorie-Namen angeben.", true);
        return;
      }
      setButtonDisabled(button, true);
      try {
        await apiRequest("update_category", { id, name, active });
        setStatus(menuStatus, "Kategorie gespeichert.");
        await loadMenu();
      } catch (error) {
        setStatus(menuStatus, error.message || "Speichern fehlgeschlagen.", true);
      } finally {
        setButtonDisabled(button, false);
      }
    }

    if (action === "save-item") {
      const row = button.closest("[data-item-id]");
      if (!row) {
        return;
      }
      const nameInput = row.querySelector(".js-item-name");
      const priceInput = row.querySelector(".js-item-price");
      const activeInput = row.querySelector(".js-item-active");
      const id = row.dataset.itemId || "";
      const name = nameInput ? nameInput.value.trim() : "";
      const price = priceInput ? priceInput.value.trim() : "";
      const active = activeInput ? activeInput.checked : false;
      if (!id || !name) {
        setStatus(menuStatus, "Bitte Artikel-Namen angeben.", true);
        return;
      }
      setButtonDisabled(button, true);
      try {
        await apiRequest("update_item", { id, name, price, active });
        setStatus(menuStatus, "Artikel gespeichert.");
        await loadMenu();
      } catch (error) {
        setStatus(menuStatus, error.message || "Speichern fehlgeschlagen.", true);
      } finally {
        setButtonDisabled(button, false);
      }
    }
  });
}
