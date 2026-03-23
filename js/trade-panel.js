(function () {
  const PANEL_ID = "trade-panel";
  const HEADER_SELECTOR = ".panel-header";

  // ——— Public API ———
  window.openTradePanel = function openTradePanel() {
    let panel = document.getElementById(PANEL_ID);

    if (!panel) {
      panel = document.createElement("div");
      panel.id = PANEL_ID;
      panel.className = "overlay-panel";
      panel.style.position = "fixed";
      panel.style.top = "16vh";
      panel.style.left = "16vw";
      panel.style.width = "60vw";
      panel.style.height = "70vh";
      panel.style.zIndex = "2003";

      panel.innerHTML = `
        <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;user-select:none;cursor:move;">
          <span class="panel-title">Trade</span>
          <div>
            <button type="button" class="btn-close" onclick="closePanel('${PANEL_ID}')" aria-label="Close" title="Close">?</button>
            <button type="button" class="btn-min"   onclick="minimizePanel('${PANEL_ID}')" aria-label="Minimize" title="Minimize">–</button>
            <button type="button" class="btn-max"   onclick="maximizePanel('${PANEL_ID}')" aria-label="Maximize / Restore" title="Maximize / Restore">?</button>
          </div>
        </div>
        <iframe src="panels/trade-panel.html" style="border:0;width:100%;height:calc(100% - 40px);" loading="lazy" referrerpolicy="no-referrer"></iframe>
      `;

      document.body.appendChild(panel);
      makeDraggable(panel);
      adjustIframeHeight(panel);
    } else {
      panel.style.display = "block";
      adjustIframeHeight(panel);
    }
  };

  // ——— Helpers ———
  function adjustIframeHeight(panel) {
    const header = panel.querySelector(HEADER_SELECTOR);
    const iframe = panel.querySelector("iframe");
    if (!iframe) return;
    const headerH = header ? (header.offsetHeight || 40) : 40;
    iframe.style.height = `calc(100% - ${headerH}px)`;
  }

  function makeDraggable(el) {
    const header = el.querySelector(HEADER_SELECTOR);
    if (!header) return;

    let startX = 0, startY = 0, startTop = 0, startLeft = 0, dragging = false;

    const onMouseDown = (e) => {
      // Prevent dragging while maximized
      if (el.dataset.maximized === "true") return;

      dragging = true;
      const rect = el.getBoundingClientRect();
      startX = e.clientX;
      startY = e.clientY;
      startTop = rect.top;
      startLeft = rect.left;

      document.addEventListener("mousemove", onMouseMove);
      document.addEventListener("mouseup", onMouseUp);
    };

    const onMouseMove = (e) => {
      if (!dragging) return;

      const dx = e.clientX - startX;
      const dy = e.clientY - startY;

      let newLeft = startLeft + dx;
      let newTop = startTop + dy;

      // Constrain to viewport
      const vpW = window.innerWidth;
      const vpH = window.innerHeight;
      const rect = el.getBoundingClientRect();

      const maxLeft = vpW - rect.width;
      const maxTop = vpH - rect.height;

      newLeft = Math.max(0, Math.min(newLeft, Math.max(0, maxLeft)));
      newTop = Math.max(0, Math.min(newTop, Math.max(0, maxTop)));

      el.style.left = `${Math.round(newLeft)}px`;
      el.style.top = `${Math.round(newTop)}px`;
    };

    const onMouseUp = () => {
      dragging = false;
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
    };

    header.addEventListener("mousedown", onMouseDown);

    // Keep iframe height correct on resize
    window.addEventListener("resize", () => adjustIframeHeight(el));

    // Initial sizing
    adjustIframeHeight(el);
  }
})();
