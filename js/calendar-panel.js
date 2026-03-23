(function () {
  const PANEL_ID = "calendar-panel";
  const HEADER_SELECTOR = ".panel-header";

  // ——— Public API ———
  window.openCalendarPanel = function openCalendarPanel() {
    let panel = document.getElementById(PANEL_ID);

    if (!panel) {
      panel = document.createElement("div");
      panel.id = PANEL_ID;
      panel.className = "overlay-panel";
      panel.style.position = "fixed";
      panel.style.top = "8vh";
      panel.style.left = "8vw";
      panel.style.width = "60vw";
      panel.style.height = "70vh";
      panel.style.zIndex = "2004";

      panel.innerHTML = `
        <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;user-select:none;cursor:move;">
          <span class="panel-title">Calendar</span>
          <div>
            <button type="button" class="btn-close" onclick="closePanel('${PANEL_ID}')" aria-label="Close" title="Close">?</button>
            <button type="button" class="btn-min"   onclick="minimizePanel('${PANEL_ID}')" aria-label="Minimize" title="Minimize">–</button>
            <button type="button" class="btn-max"   onclick="maximizePanel('${PANEL_ID}')" aria-label="Maximize / Restore" title="Maximize / Restore">?</button>
          </div>
        </div>
        <iframe src="panels/calendar-panel.html" style="border:0;width:100%;height:calc(100% - 40px);" loading="lazy" referrerpolicy="no-referrer"></iframe>
      `;

      document.body.appendChild(panel);
      makeDraggable(panel);
      adjustIframeHeight(panel);
    } else {
      panel.style.display = "block";
      adjustIframeHeight(panel);
    }
  };

  // ——— Global helpers (define only if missing to avoid conflicts) ———
  if (typeof window.closePanel !== "function") {
    window.closePanel = function closePanel(id) {
      const panel = document.getElementById(id);
      if (panel) panel.remove();
    };
  }

  if (typeof window.minimizePanel !== "function") {
    window.minimizePanel = function minimizePanel(id) {
      const panel = document.getElementById(id);
      if (!panel) return;

      const header = panel.querySelector(HEADER_SELECTOR);
      const iframe = panel.querySelector("iframe");

      if (!panel.dataset.prevHeight) {
        panel.dataset.prevHeight = panel.style.height || `${panel.offsetHeight}px`;
      }

      const headerHeight = header ? header.offsetHeight || 40 : 40;
      panel.style.height = `${headerHeight}px`;
      if (iframe) iframe.style.display = "none";
    };
  }

  if (typeof window.maximizePanel !== "function") {
    window.maximizePanel = function maximizePanel(id) {
      const panel = document.getElementById(id);
      if (!panel) return;

      const isMax = panel.dataset.maximized === "true";

      if (!isMax) {
        // Save previous geometry
        panel.dataset.prevTop = panel.style.top || `${panel.offsetTop}px`;
        panel.dataset.prevLeft = panel.style.left || `${panel.offsetLeft}px`;
        panel.dataset.prevWidth = panel.style.width || `${panel.offsetWidth}px`;
        panel.dataset.prevHeight = panel.style.height || `${panel.offsetHeight}px`;

        // Maximize to viewport with margins
        panel.style.top = "2vh";
        panel.style.left = "2vw";
        panel.style.width = "96vw";
        panel.style.height = "96vh";
        panel.dataset.maximized = "true";

        const iframe = panel.querySelector("iframe");
        if (iframe) {
          iframe.style.display = "block";
          adjustIframeHeight(panel);
        }
      } else {
        // Restore
        if (panel.dataset.prevTop) panel.style.top = panel.dataset.prevTop;
        if (panel.dataset.prevLeft) panel.style.left = panel.dataset.prevLeft;
        if (panel.dataset.prevWidth) panel.style.width = panel.dataset.prevWidth;
        if (panel.dataset.prevHeight) panel.style.height = panel.dataset.prevHeight;

        panel.dataset.maximized = "false";
        const iframe = panel.querySelector("iframe");
        if (iframe) {
          iframe.style.display = "block";
          adjustIframeHeight(panel);
        }
      }
    };
  }

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
      if (el.dataset.maximized === "true") return; // no drag while maximized

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
    window.addEventListener("resize", () => adjustIframeHeight(el));
    adjustIframeHeight(el);
  }
})();
