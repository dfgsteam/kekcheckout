(() => {
  const ADMIN_TOKEN_KEY = "kekcounter.adminToken";
  const t = (key, vars) => (typeof window.t === "function" ? window.t(key, vars) : key);

  const adminAuthCard = document.getElementById("adminAuthCard");
  const adminContent = document.getElementById("adminContent");
  const adminTokenInput = document.getElementById("adminToken");
  const adminSave = document.getElementById("adminSave");
  const adminClear = document.getElementById("adminClear");
  const adminStatus = document.getElementById("adminStatus");
  const logSearchInput = document.getElementById("logSearch");
  const logContainer = document.getElementById("logContainer");

  let adminAuthenticated = false;
  let allHeaders = [];
  let allRows = [];
  let sortColumn = null;
  let sortOrder = "asc"; // "asc" or "desc"

  function getAdminToken() {
    try {
      return localStorage.getItem(ADMIN_TOKEN_KEY) || "";
    } catch (e) {
      return "";
    }
  }

  function setAdminAuthState(authenticated) {
    adminAuthenticated = authenticated;
    if (adminAuthCard) adminAuthCard.classList.toggle("d-none", authenticated);
    if (adminContent) adminContent.classList.toggle("d-none", !authenticated);
    if (authenticated) {
      loadBookings();
    }
  }

  async function loadBookings() {
    const token = getAdminToken();
    if (!token) return;

    try {
      const response = await fetch("bookings.php?action=get_bookings", {
        method: "POST",
        headers: {
          "X-Admin-Token": token,
          "X-Requested-With": "fetch"
        }
      });

      if (!response.ok) {
        if (response.status === 403) {
          setAdminAuthState(false);
          if (adminStatus) adminStatus.textContent = t("token.adminInvalid");
        }
        return;
      }

      const data = await response.json();
      allHeaders = data.headers || [];
      allRows = data.rows || [];
      applyDefaultSort();
    } catch (e) {
      console.error(e);
      if (logContainer) logContainer.innerHTML = '<p class="text-danger">Fehler beim Laden der Daten.</p>';
    }
  }

  function renderTable(headers, rows) {
    if (!logContainer) return;

    if (rows.length === 0) {
      logContainer.innerHTML = '<p class="text-secondary mb-0">Keine Buchungen gefunden.</p>';
      return;
    }

    let html = '<div class="table-responsive bookings-table-wrapper w-100"><table class="table table-sm table-hover align-middle mb-0 w-100"><thead><tr>';
    headers.forEach((h, index) => {
      const isSorted = sortColumn === index;
      const icon = isSorted
        ? sortOrder === "asc"
          ? ' <i class="bi bi-sort-up"></i>'
          : ' <i class="bi bi-sort-down"></i>'
        : ' <i class="bi bi-sort-alpha-down text-light-emphasis opacity-50"></i>';
      const label = formatHeader(h);
      const ariaSort = isSorted ? (sortOrder === "asc" ? "ascending" : "descending") : "none";
      html += `<th scope="col" class="sortable-header" data-column="${index}" aria-sort="${ariaSort}" style="cursor: pointer; white-space: nowrap;">${label}${icon}</th>`;
    });
    html += "</tr></thead><tbody>";
    rows.forEach((row) => {
      html += "<tr>";
      row.forEach((cell) => {
        html += `<td>${escapeHtml(String(cell ?? ""))}</td>`;
      });
      html += "</tr>";
    });
    html += "</tbody></table></div>";
    logContainer.innerHTML = html;

    // Add event listeners to headers
    logContainer.querySelectorAll(".sortable-header").forEach((header) => {
      header.addEventListener("click", () => {
        const column = parseInt(header.dataset.column);
        handleSort(column);
      });
    });
  }

  function handleSort(column) {
    if (sortColumn === column) {
      sortOrder = sortOrder === "asc" ? "desc" : "asc";
    } else {
      sortColumn = column;
      sortOrder = "asc";
    }

    applyFiltersAndSort();
  }

  function applyFiltersAndSort() {
    const query = logSearchInput ? logSearchInput.value.toLowerCase().trim() : "";
    let rows = [...allRows];

    if (query) {
      rows = rows.filter(row => {
        return row.some(cell => String(cell).toLowerCase().includes(query));
      });
    }

    if (sortColumn !== null) {
      rows.sort((a, b) => compareValues(a[sortColumn], b[sortColumn]));
    }

    renderTable(allHeaders, rows);
  }

  function compareValues(a, b) {
    const order = sortOrder === "asc" ? 1 : -1;
    const strA = String(a ?? "").trim();
    const strB = String(b ?? "").trim();

    const numA = parseNumber(strA);
    const numB = parseNumber(strB);
    if (numA !== null && numB !== null) {
      if (numA < numB) return -1 * order;
      if (numA > numB) return 1 * order;
      return 0;
    }

    const dateA = parseDate(strA);
    const dateB = parseDate(strB);
    if (dateA !== null && dateB !== null) {
      if (dateA < dateB) return -1 * order;
      if (dateA > dateB) return 1 * order;
      return 0;
    }

    if (strA < strB) return -1 * order;
    if (strA > strB) return 1 * order;
    return 0;
  }

  function parseNumber(value) {
    if (!value) return null;
    const normalized = value.replace(",", ".");
    if (!/^-?\d+(\.\d+)?$/.test(normalized)) {
      return null;
    }
    const num = Number(normalized);
    return Number.isFinite(num) ? num : null;
  }

  function parseDate(value) {
    if (!value) return null;
    const timestamp = Date.parse(value);
    if (!Number.isFinite(timestamp)) {
      return null;
    }
    return timestamp;
  }

  function formatHeader(value) {
    const text = String(value ?? "");
    if (text === "") return "";
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function escapeHtml(value) {
    return value
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function applyDefaultSort() {
    if (!allHeaders.length) {
      sortColumn = null;
      return;
    }
    const timeIndex = allHeaders.findIndex((header) => {
      const key = String(header || "").toLowerCase();
      return key === "uhrzeit" || key === "zeit" || key === "time" || key === "timestamp";
    });
    sortColumn = timeIndex >= 0 ? timeIndex : 0;
    sortOrder = "desc";
    applyFiltersAndSort();
  }

  function handleSearch() {
    applyFiltersAndSort();
  }

  // Init
  const storedToken = getAdminToken();
  if (storedToken) {
    setAdminAuthState(true);
  } else {
    setAdminAuthState(false);
  }

  if (adminSave && adminTokenInput) {
    adminSave.addEventListener("click", () => {
      const token = adminTokenInput.value.trim();
      if (token) {
        localStorage.setItem(ADMIN_TOKEN_KEY, token);
        setAdminAuthState(true);
      }
    });
    adminTokenInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") adminSave.click();
    });
  }

  if (adminClear) {
    adminClear.addEventListener("click", () => {
      localStorage.removeItem(ADMIN_TOKEN_KEY);
      setAdminAuthState(false);
    });
  }

  if (logSearchInput) {
    logSearchInput.addEventListener("input", handleSearch);
  }
})();
