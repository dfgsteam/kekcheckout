(() => {
  const ADMIN_TOKEN_KEY = "kekcounter.adminToken";
  const t = (key, vars) => (typeof window.t === "function" ? window.t(key, vars) : key);

  const adminAuthCard = document.getElementById("adminAuthCard");
  const adminContent = document.getElementById("adminContent");
  const adminTokenInput = document.getElementById("adminToken");
  const adminSave = document.getElementById("adminSave");
  const adminClear = document.getElementById("adminClear");
  const adminStatus = document.getElementById("adminStatus");

  let adminAuthenticated = false;

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
      loadAnalysisData();
    }
  }

  async function loadAnalysisData() {
    const token = getAdminToken();
    if (!token) return;

    try {
      const response = await fetch("analysis.php?action=get_data", {
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
      renderAnalysis(data);
    } catch (e) {
      console.error(e);
    }
  }

  function renderAnalysis(data) {
    const stats = data.stats;
    const statsContainer = document.getElementById("analysisStats");
    if (statsContainer && stats) {
      statsContainer.querySelector(".revenue-val").textContent = "€ " + stats.revenue;
      statsContainer.querySelector(".count-val").textContent = stats.count;
      statsContainer.querySelector(".average-val").textContent = "€ " + stats.average;
    }

    const bucketMins = data.charts?.bucketMinutes || 15;
    document.querySelectorAll(".js-bucket-text").forEach(el => {
      if (!el.dataset.initialized) {
        el.textContent += " je " + bucketMins + " Minuten.";
        el.dataset.initialized = "true";
      }
    });

    if (window.renderKekCharts && data.charts) {
        window.renderKekCharts(data.charts);
    }

    renderLog(data.log);
  }

  function renderLog(log) {
    const container = document.getElementById("logContainer");
    if (!container || !log) return;

    if (!log.hasFile) {
        container.innerHTML = '<p class="text-secondary mb-0">Kein Buchungslog gefunden. Datei fehlt.</p>';
        return;
    }
    if (!log.rows || log.rows.length === 0) {
        container.innerHTML = '<p class="text-secondary mb-0">Noch keine Buchungen vorhanden.</p>';
        return;
    }

    let html = "";
    if (log.truncated) {
        html += `<div class="alert alert-info py-2 px-3 small mb-3">Anzeige auf die letzten ${log.limit} Buchungen begrenzt.</div>`;
    }

    html += '<div class="table-responsive analysis-table"><table class="table table-sm table-hover align-middle mb-0"><thead><tr>';
    log.headers.forEach(h => {
        html += `<th scope="col">${h.charAt(0).toUpperCase() + h.slice(1)}</th>`;
    });
    html += '</tr></thead><tbody>';
    log.rows.forEach(row => {
        html += '<tr>';
        row.forEach(cell => {
            html += `<td>${cell}</td>`;
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
  }

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
  }

  if (adminClear) {
    adminClear.addEventListener("click", () => {
      localStorage.removeItem(ADMIN_TOKEN_KEY);
      setAdminAuthState(false);
    });
  }
})();