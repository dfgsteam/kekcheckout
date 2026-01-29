if (!window.ADMIN_TOKEN_KEY) {
  window.ADMIN_TOKEN_KEY = "kekcounter.adminToken";
}

const menuAdminContent = document.getElementById("adminContent");
const categoryName = document.getElementById("categoryName");
const categoryParent = document.getElementById("categoryParent");
const categoryActive = document.getElementById("categoryActive");
const categoryAdd = document.getElementById("categoryAdd");
const categoryStatus = document.getElementById("categoryStatus");

const itemCategory = document.getElementById("itemCategory");
const itemName = document.getElementById("itemName");
const itemPrice = document.getElementById("itemPrice");
const itemIngredients = document.getElementById("itemIngredients");
const itemTags = document.getElementById("itemTags");
const itemPreparation = document.getElementById("itemPreparation");
const itemActive = document.getElementById("itemActive");
const itemAdd = document.getElementById("itemAdd");
const itemStatus = document.getElementById("itemStatus");

const menuList = document.getElementById("menuList");
const menuStatus = document.getElementById("menuStatus");
const menuErrorModal = document.getElementById("menuErrorModal");
const menuErrorMessage = document.getElementById("menuErrorMessage");

function getAdminToken() {
  try {
    return localStorage.getItem(window.ADMIN_TOKEN_KEY) || "";
  } catch (error) {
    return "";
  }
}

function showErrorModal(message) {
  if (!menuErrorModal || !window.bootstrap?.Modal) {
    return;
  }
  if (menuErrorMessage) {
    menuErrorMessage.textContent = message || "Ein Fehler ist aufgetreten.";
  }
  const modal = bootstrap.Modal.getOrCreateInstance(menuErrorModal);
  modal.show();
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

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

async function menuRequest(action, payload) {
  const token = getAdminToken();
  if (!token) {
    const message = "Admin-Token fehlt. Bitte erneut anmelden.";
    showErrorModal(message);
    throw new Error(message);
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
    showErrorModal(message);
    throw new Error(message);
  }
  return data;
}

async function fetchMenu() {
  const token = getAdminToken();
  const response = await fetch("menu.php?action=get_menu", {
    method: "POST",
    cache: "no-store",
    headers: {
      "X-Requested-With": "fetch",
      "X-Admin-Token": token || "",
    },
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const message = data?.error || "Request failed";
    const error = new Error(message);
    error.status = response.status;
    throw error;
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

  // Sort categories hierarchically
  const sortedCategories = [];
  const addedIds = new Set();

  function addBranch(parentId, level) {
    categories
      .filter((c) => (c.parent_id || "") === parentId)
      .forEach((c) => {
        if (addedIds.has(c.id)) return;
        addedIds.add(c.id);
        sortedCategories.push({ ...c, level });
        addBranch(c.id, level + 1);
      });
  }

  addBranch("", 0);
  // Add orphaned categories (if any)
  categories.forEach((c) => {
    if (!addedIds.has(c.id)) {
      sortedCategories.push({ ...c, level: 0 });
      addedIds.add(c.id);
    }
  });

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

  function isCategoryEffectivelyActive(catId, categories) {
    const catMap = new Map();
    categories.forEach(c => catMap.set(c.id, c));

    let currentId = catId;
    const visited = new Set();
    while (currentId) {
      if (visited.has(currentId)) return false;
      visited.add(currentId);

      const cat = catMap.get(currentId);
      if (!cat || !cat.active) return false;
      currentId = cat.parent_id || "";
    }
    return true;
  }

  sortedCategories.forEach((category) => {
    const isCatEffectivelyActive = isCategoryEffectivelyActive(category.id, categories);
    const card = document.createElement("div");
    card.className = "card border-0 shadow-sm";
    card.dataset.categoryId = category.id || "";
    if (category.level > 0) {
      card.style.marginLeft = `${category.level * 2}rem`;
      card.classList.add("border-start", "border-primary", "border-4");
    }

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

    const parentWrap = document.createElement("div");
    parentWrap.className = "flex-grow-1";
    const parentLabel = document.createElement("label");
    parentLabel.className = "form-label small text-secondary";
    parentLabel.textContent = "Uebergeordnet";
    const parentSelect = document.createElement("select");
    parentSelect.className = "form-select form-select-sm js-category-parent";
    const noneOpt = document.createElement("option");
    noneOpt.value = "";
    noneOpt.textContent = "Keine";
    parentSelect.appendChild(noneOpt);
    categories.forEach((c) => {
      if (c.id === category.id) return;
      const opt = document.createElement("option");
      opt.value = c.id;
      opt.textContent = c.name;
      opt.selected = c.id === category.parent_id;
      parentSelect.appendChild(opt);
    });
    parentWrap.appendChild(parentLabel);
    parentWrap.appendChild(parentSelect);

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
    const deleteBtn = document.createElement("button");
    deleteBtn.type = "button";
    deleteBtn.className = "btn btn-outline-danger btn-sm";
    deleteBtn.dataset.action = "delete-category";
    deleteBtn.title = "Kategorie loeschen";
    const deleteIcon = document.createElement("i");
    deleteIcon.className = "bi bi-trash";
    deleteBtn.appendChild(deleteIcon);
    actions.appendChild(activeWrap);
    actions.appendChild(deleteBtn);

    header.appendChild(titleWrap);
    header.appendChild(parentWrap);
    header.appendChild(actions);

    const badge = document.createElement("span");
    badge.className = `badge js-category-badge ${isCatEffectivelyActive ? "text-bg-success" : "text-bg-secondary"} align-self-start`;
    badge.textContent = isCatEffectivelyActive ? "Aktiv" : "Inaktiv";
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
        colName.className = "col-12 col-md-4";
        const nameLabel = document.createElement("label");
        nameLabel.className = "form-label small text-secondary";
        nameLabel.textContent = "Artikel";
        const nameInput = document.createElement("input");
        nameInput.className = "form-control form-control-sm js-item-name";
        nameInput.value = item.name || "";
        colName.appendChild(nameLabel);
        colName.appendChild(nameInput);

        const colPrice = document.createElement("div");
        colPrice.className = "col-12 col-md-2";
        const priceLabel = document.createElement("label");
        priceLabel.className = "form-label small text-secondary";
        priceLabel.textContent = "Preis";
        const priceInput = document.createElement("input");
        priceInput.className = "form-control form-control-sm js-item-price";
        priceInput.value = item.price || "0.00";
        colPrice.appendChild(priceLabel);
        colPrice.appendChild(priceInput);

        const colIngredients = document.createElement("div");
        colIngredients.className = "col-12 col-md-6";
        const ingredientsLabel = document.createElement("label");
        ingredientsLabel.className = "form-label small text-secondary";
        ingredientsLabel.textContent = "Zutaten";
        const ingredientsInput = document.createElement("input");
        ingredientsInput.className = "form-control form-control-sm js-item-ingredients";
        const ingredients = Array.isArray(item.ingredients) ? item.ingredients.join(", ") : "";
        ingredientsInput.value = ingredients;
        colIngredients.appendChild(ingredientsLabel);
        colIngredients.appendChild(ingredientsInput);

        const colTags = document.createElement("div");
        colTags.className = "col-12 col-md-4";
        const tagsLabel = document.createElement("label");
        tagsLabel.className = "form-label small text-secondary";
        tagsLabel.textContent = "Tags";
        const tagsInput = document.createElement("input");
        tagsInput.className = "form-control form-control-sm js-item-tags";
        const tags = Array.isArray(item.tags) ? item.tags.join(", ") : "";
        tagsInput.value = tags;
        colTags.appendChild(tagsLabel);
        colTags.appendChild(tagsInput);

        const colPreparation = document.createElement("div");
        colPreparation.className = "col-12 col-md-8";
        const preparationLabel = document.createElement("label");
        preparationLabel.className = "form-label small text-secondary";
        preparationLabel.textContent = "Zubereitung";
        const preparationInput = document.createElement("input");
        preparationInput.className = "form-control form-control-sm js-item-preparation";
        preparationInput.value = item.preparation || "";
        colPreparation.appendChild(preparationLabel);
        colPreparation.appendChild(preparationInput);

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
        const itemDelete = document.createElement("button");
        itemDelete.type = "button";
        itemDelete.className = "btn btn-outline-danger btn-sm";
        itemDelete.dataset.action = "delete-item";
        itemDelete.title = "Artikel loeschen";
        const itemDeleteIcon = document.createElement("i");
        itemDeleteIcon.className = "bi bi-trash";
        itemDelete.appendChild(itemDeleteIcon);
        colActions.appendChild(itemActiveWrap);
        colActions.appendChild(itemDelete);
        const itemBadge = document.createElement("span");
        const isActuallyActive = item.active && isCatEffectivelyActive;
        itemBadge.className = `badge js-item-badge ${isActuallyActive ? "text-bg-success" : "text-bg-secondary"}`;
        itemBadge.textContent = isActuallyActive ? "Aktiv" : "Inaktiv";
        colActions.appendChild(itemBadge);

        rowGrid.appendChild(colName);
        rowGrid.appendChild(colPrice);
        rowGrid.appendChild(colIngredients);
        rowGrid.appendChild(colTags);
        rowGrid.appendChild(colPreparation);
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
  const selects = [itemCategory, categoryParent];
  const categories = Array.isArray(menu?.categories) ? menu.categories : [];

  selects.forEach((select) => {
    if (!select) return;
    const currentVal = select.value;
    select.innerHTML = "";

    if (select === categoryParent) {
      const option = document.createElement("option");
      option.value = "";
      option.textContent = "Keine (Hauptkategorie)";
      select.appendChild(option);
    }

    categories.forEach((category) => {
      const option = document.createElement("option");
      option.value = category.id || "";
      option.textContent = category.name || "Kategorie";
      select.appendChild(option);
    });

    if (currentVal) {
      select.value = currentVal;
    }
  });
}

function updateBadge(badge, isActive) {
  if (!badge) {
    return;
  }
  badge.classList.toggle("text-bg-success", isActive);
  badge.classList.toggle("text-bg-secondary", !isActive);
  badge.textContent = isActive ? "Aktiv" : "Inaktiv";
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
    const status = error?.status || 0;
    if (status === 403 && menuList && menuList.children.length > 0) {
      setStatus(menuStatus, "Menue angezeigt.");
      return;
    }
    setStatus(menuStatus, error?.message || "Menue konnte nicht geladen werden.", true);
  }
}

async function saveCategory(card) {
  if (!card) return;
  const nameInput = card.querySelector(".js-category-name");
  const parentSelect = card.querySelector(".js-category-parent");
  const activeInput = card.querySelector(".js-category-active");
  const badge = card.querySelector(".js-category-badge");
  const id = card.dataset.categoryId || "";
  const name = nameInput ? nameInput.value.trim() : "";
  const parentId = parentSelect ? parentSelect.value : "";
  const active = activeInput ? activeInput.checked : false;
  if (!id || !name) {
    return;
  }
  try {
    const response = await menuRequest("update_category", { id, name, active, parentId });
    setStatus(menuStatus, "Kategorie gespeichert.");
    if (response?.menu) {
      // If something changed that affects effective activity, we might need to update badges elsewhere.
      // Easiest (but maybe flickering) is to re-render, but we want to avoid focus loss.
      // Let's update effective activity status for all categories and items.
      const cats = Array.isArray(response.menu.categories) ? response.menu.categories : [];
      const itemMap = new Map();
      if (Array.isArray(response.menu.items)) {
        response.menu.items.forEach(i => itemMap.set(i.id, i));
      }

      const cards = menuList.querySelectorAll("[data-category-id]");
      cards.forEach(c => {
        const cId = c.dataset.categoryId;
        const cBadge = c.querySelector(".js-category-badge");
        const effectivelyActive = isCategoryEffectivelyActive(cId, cats);
        updateBadge(cBadge, effectivelyActive);

        const itemRows = c.querySelectorAll("[data-item-id]");
        itemRows.forEach(row => {
          const iId = row.dataset.itemId;
          const item = itemMap.get(iId);
          const iBadge = row.querySelector(".js-item-badge");
          const iActive = row.querySelector(".js-item-active")?.checked ?? (item?.active || false);
          const actuallyActive = effectivelyActive && iActive;
          updateBadge(iBadge, actuallyActive);
        });
      });

      populateCategorySelect(response.menu);
    } else {
      updateBadge(badge, active);
    }
  } catch (error) {
    console.error(error);
  }
}

const debouncedSaveCategory = debounce(saveCategory, 500);

async function saveItem(row) {
  if (!row) return;
  const card = row.closest("[data-category-id]");
  const catId = card?.dataset.categoryId || "";
  
  // We need the full menu to check effective activity correctly
  let categories = [];
  try {
    const menu = await fetchMenu();
    categories = Array.isArray(menu?.categories) ? menu.categories : [];
  } catch (e) {
    console.error("Could not fetch menu for activity check", e);
  }

  const effectivelyActive = catId ? isCategoryEffectivelyActive(catId, categories) : true;

  const nameInput = row.querySelector(".js-item-name");
  const priceInput = row.querySelector(".js-item-price");
  const ingredientsInput = row.querySelector(".js-item-ingredients");
  const tagsInput = row.querySelector(".js-item-tags");
  const preparationInput = row.querySelector(".js-item-preparation");
  const activeInput = row.querySelector(".js-item-active");
  const badge = row.querySelector(".js-item-badge");
  const id = row.dataset.itemId || "";
  const name = nameInput ? nameInput.value.trim() : "";
  const price = priceInput ? priceInput.value.trim() : "";
  const ingredients = ingredientsInput ? ingredientsInput.value.trim() : "";
  const tags = tagsInput ? tagsInput.value.trim() : "";
  const preparation = preparationInput ? preparationInput.value.trim() : "";
  const active = activeInput ? activeInput.checked : false;
  if (!id || !name) {
    return;
  }
  try {
    const response = await menuRequest("update_item", {
      id,
      name,
      price,
      ingredients,
      tags,
      preparation,
      active,
    });
    setStatus(menuStatus, "Artikel gespeichert.");
    if (badge) {
      const actuallyActive = active && categoryActive;
      badge.classList.toggle("text-bg-success", actuallyActive);
      badge.classList.toggle("text-bg-secondary", !actuallyActive);
      badge.textContent = actuallyActive ? "Aktiv" : "Inaktiv";
    }
  } catch (error) {
    console.error(error);
  }
}

const debouncedSaveItem = debounce(saveItem, 500);

async function addCategory() {
  if (!categoryName || !categoryActive || !categoryAdd) {
    return;
  }
  const name = categoryName.value.trim();
  const parentId = categoryParent ? categoryParent.value : "";
  if (!name) {
    setStatus(categoryStatus, "Bitte Namen eingeben.", true);
    return;
  }
  setButtonDisabled(categoryAdd, true);
  try {
    const response = await menuRequest("add_category", {
      name,
      active: categoryActive.checked,
      parentId,
    });
    categoryName.value = "";
    if (categoryParent) categoryParent.value = "";
    categoryActive.checked = true;
    setStatus(categoryStatus, "Kategorie gespeichert.");
    if (response?.menu) {
      renderMenu(response.menu);
      populateCategorySelect(response.menu);
      setStatus(menuStatus, "Menue aktualisiert.");
    } else {
      await loadMenu();
    }
  } catch (error) {
    showErrorModal(error.message || "Speichern fehlgeschlagen.");
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
  const ingredients = itemIngredients ? itemIngredients.value.trim() : "";
  const tags = itemTags ? itemTags.value.trim() : "";
  const preparation = itemPreparation ? itemPreparation.value.trim() : "";
  if (!categoryId || !name) {
    setStatus(itemStatus, "Bitte Kategorie und Namen angeben.", true);
    return;
  }
  setButtonDisabled(itemAdd, true);
  try {
    const response = await menuRequest("add_item", {
      categoryId,
      name,
      price,
      ingredients,
      tags,
      preparation,
      active: itemActive.checked,
    });
    itemName.value = "";
    itemPrice.value = "";
    if (itemIngredients) {
      itemIngredients.value = "";
    }
    if (itemTags) {
      itemTags.value = "";
    }
    if (itemPreparation) {
      itemPreparation.value = "";
    }
    itemActive.checked = true;
    setStatus(itemStatus, "Artikel gespeichert.");
    if (response?.menu) {
      renderMenu(response.menu);
      populateCategorySelect(response.menu);
      setStatus(menuStatus, "Menue aktualisiert.");
    } else {
      await loadMenu();
    }
  } catch (error) {
    showErrorModal(error.message || "Speichern fehlgeschlagen.");
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

if (menuAdminContent) {
  const observer = new MutationObserver(() => {
    if (!menuAdminContent.classList.contains("d-none")) {
      loadMenu();
    }
  });
  observer.observe(menuAdminContent, { attributes: true, attributeFilter: ["class"] });
  if (!menuAdminContent.classList.contains("d-none")) {
    loadMenu();
  }
}

if (menuList) {
  menuList.addEventListener("input", (event) => {
    const target = event.target;
    if (target.classList.contains("js-category-name")) {
      debouncedSaveCategory(target.closest("[data-category-id]"));
    } else if (
      target.classList.contains("js-item-name") ||
      target.classList.contains("js-item-price") ||
      target.classList.contains("js-item-ingredients") ||
      target.classList.contains("js-item-tags") ||
      target.classList.contains("js-item-preparation")
    ) {
      debouncedSaveItem(target.closest("[data-item-id]"));
    }
  });

  menuList.addEventListener("change", (event) => {
    const target = event.target;
    if (target.classList.contains("js-category-active") || target.classList.contains("js-category-parent")) {
      saveCategory(target.closest("[data-category-id]"));
    } else if (target.classList.contains("js-item-active")) {
      saveItem(target.closest("[data-item-id]"));
    }
  });

  menuList.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) {
      return;
    }
    const action = button.dataset.action;
    if (action === "delete-category") {
      const card = button.closest("[data-category-id]");
      const id = card?.dataset.categoryId || "";
      if (!id) return;
      if (!confirm("Kategorie und alle enthaltenen Artikel wirklich loeschen?")) {
        return;
      }
      setButtonDisabled(button, true);
      try {
        const response = await menuRequest("delete_category", { id });
        setStatus(menuStatus, "Kategorie geloescht.");
        if (response?.menu) {
          renderMenu(response.menu);
          populateCategorySelect(response.menu);
        } else {
          await loadMenu();
        }
      } catch (error) {
        showErrorModal(error.message || "Loeschen fehlgeschlagen.");
        setStatus(menuStatus, error.message || "Loeschen fehlgeschlagen.", true);
      } finally {
        setButtonDisabled(button, false);
      }
    }

    if (action === "delete-item") {
      const row = button.closest("[data-item-id]");
      const id = row?.dataset.itemId || "";
      if (!id) return;
      if (!confirm("Artikel wirklich loeschen?")) {
        return;
      }
      setButtonDisabled(button, true);
      try {
        const response = await menuRequest("delete_item", { id });
        setStatus(menuStatus, "Artikel geloescht.");
        if (response?.menu) {
          renderMenu(response.menu);
          populateCategorySelect(response.menu);
        } else {
          await loadMenu();
        }
      } catch (error) {
        showErrorModal(error.message || "Loeschen fehlgeschlagen.");
        setStatus(menuStatus, error.message || "Loeschen fehlgeschlagen.", true);
      } finally {
        setButtonDisabled(button, false);
      }
    }
  });
}
