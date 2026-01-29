(() => {
  const TYPE_VOUCHER = "Gutschein";
  const TYPE_FREE = "Gratis";
  const TYPE_SELL = "Verkauft";

  let currentType = TYPE_SELL;
  let resetTimer = null;

  const voucherBtn = document.getElementById("typeVoucherButton");
  const freeBtn = document.getElementById("typeFreeButton");
  const stornoBtn = document.getElementById("stornoButton");
  const bookButtons = document.querySelectorAll(".js-tablet-book-btn");

  const settings = window.kekTabletSettings || { typeResetSeconds: 30 };

  function updateActiveState() {
    if (voucherBtn) {
      voucherBtn.classList.toggle("active", currentType === TYPE_VOUCHER);
    }
    if (freeBtn) {
      freeBtn.classList.toggle("active", currentType === TYPE_FREE);
    }

    // Update icons and classes on book buttons
    bookButtons.forEach((btn) => {
      const icon = btn.querySelector(".js-btn-icon");
      const categoryColorClass = btn.getAttribute("data-category-color-class") || "";
      
      // Remove possible status classes
      btn.classList.remove("btn-primary", "tablet-btn-voucher", "tablet-btn-free");
      
      // Always ensure category color is present
      if (categoryColorClass) {
        btn.classList.add(categoryColorClass);
      } else {
        btn.classList.add("btn-primary");
      }

      btn.setAttribute("data-book-type", currentType);

      if (currentType === TYPE_VOUCHER) {
        btn.classList.add("tablet-btn-voucher");
        if (icon) {
          icon.className = "bi bi-ticket-perforated fs-2 js-btn-icon";
          icon.style.color = "#ff8800";
        }
      } else if (currentType === TYPE_FREE) {
        btn.classList.add("tablet-btn-free");
        if (icon) {
          icon.className = "bi bi-gift fs-2 js-btn-icon";
          icon.style.color = "#00ff88";
        }
      } else {
        if (icon) {
          icon.style.color = "";
          icon.className = "bi bi-cart-plus fs-2 js-btn-icon";
        }
      }
    });
  }

  function setType(type) {
    if (currentType === type) {
      currentType = TYPE_SELL; // Toggle off
    } else {
      currentType = type;
    }
    updateActiveState();
    startResetTimer();
  }

  function startResetTimer() {
    if (resetTimer) {
      clearTimeout(resetTimer);
      resetTimer = null;
    }

    if (currentType !== TYPE_SELL && settings.typeResetSeconds > 0) {
      resetTimer = setTimeout(() => {
        currentType = TYPE_SELL;
        updateActiveState();
      }, settings.typeResetSeconds * 1000);
    }
  }

  if (voucherBtn) {
    voucherBtn.addEventListener("click", () => setType(TYPE_VOUCHER));
  }
  if (freeBtn) {
    freeBtn.addEventListener("click", () => setType(TYPE_FREE));
  }
  if (stornoBtn) {
    stornoBtn.addEventListener("click", () => {
      currentType = TYPE_SELL;
      updateActiveState();
      if (resetTimer) {
        clearTimeout(resetTimer);
        resetTimer = null;
      }
    });
  }

  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".js-tablet-book-btn");
    if (btn && currentType !== TYPE_SELL) {
       // Reset after a small delay to ensure pos.js got the right type from the attribute
       setTimeout(() => {
           if (settings.typeResetSeconds > 0) {
               currentType = TYPE_SELL;
               updateActiveState();
               if (resetTimer) {
                   clearTimeout(resetTimer);
                   resetTimer = null;
               }
           }
       }, 500);
    }
  });

  // Initial state
  updateActiveState();
})();
