"use strict";
/* Plant Overview: live timers, count-up numbers, and a 60-second refresh. */
(function () {
    const pad = (n) => String(n).padStart(2, "0");

    // Use the server's clock so a wrong clock on this PC does not skew the timers.
    const card = document.querySelector("[data-server-now]");
    const offset = card
        ? Number(card.dataset.serverNow) * 1000 - Date.now()
        : 0;
    const nowSec = () => (Date.now() + offset) / 1000;

    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec));
        const d = Math.floor(sec / 86400);
        const h = Math.floor((sec % 86400) / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        return (d ? d + "d " : "") + pad(h) + ":" + pad(m) + ":" + pad(s);
    }

    const timers = [...document.querySelectorAll("[data-since]")];
    const tick = () =>
        timers.forEach((el) => {
            el.textContent = fmt(nowSec() - Number(el.dataset.since));
        });
    if (timers.length) {
        tick();
        setInterval(tick, 1000);
    }

    // Count-up on first view only, so the 60-second refresh does not re-animate every minute.
    const reduce = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
    ).matches;
    let recent = false;
    try {
        recent =
            Date.now() - Number(sessionStorage.getItem("smzl-countup") || 0) <
            300000;
        if (!recent) sessionStorage.setItem("smzl-countup", String(Date.now()));
    } catch (e) {
        /* ignore */
    }

    if (!reduce && !recent) {
        document.querySelectorAll("[data-countup]").forEach((el) => {
            const target = Number(el.dataset.countup);
            if (!Number.isFinite(target) || target < 1) return;
            const t0 = performance.now(),
                dur = 900;
            const step = (now) => {
                const p = Math.min(1, (now - t0) / dur);
                el.textContent = Math.round(
                    target * (1 - Math.pow(1 - p, 3)),
                ).toLocaleString("en-US");
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        });
    }

    /* ---------- recent shift details ---------- */
    const shiftRows = document.querySelector("#overview-shift-rows");
    const shiftDialog = document.querySelector("#overview-shift-dialog");

    if (shiftRows && shiftDialog) {
        shiftRows.addEventListener("click", (event) => {
            const button = event.target.closest("[data-overview-detail]");
            if (!button) return;

            const detail = JSON.parse(button.dataset.overviewDetail);
            const list = document.querySelector("#overview-shift-details");

            document.querySelector("#overview-shift-title").textContent =
                detail.title;

            list.replaceChildren();

            Object.entries(detail.rows).forEach(([label, value]) => {
                const term = document.createElement("dt");
                const description = document.createElement("dd");

                term.textContent = label;
                description.textContent = value ?? "—";
                list.append(term, description);
            });

            const notes = detail.notes || "";

            document.querySelector("#overview-shift-notes").textContent = notes;
            document.querySelector("#overview-shift-notes-wrap").hidden =
                !notes.trim();

            shiftDialog.showModal();
        });

        shiftDialog.addEventListener("click", (event) => {
            const bounds = shiftDialog.getBoundingClientRect();

            if (
                event.target === shiftDialog &&
                (event.clientX < bounds.left ||
                    event.clientX > bounds.right ||
                    event.clientY < bounds.top ||
                    event.clientY > bounds.bottom)
            ) {
                shiftDialog.close();
            }
        });
    }

    // Keep the page live: reload every minute while the tab is visible.
    setInterval(() => {
        if (
            document.visibilityState === "visible" &&
            !document.querySelector("dialog[open]")
        ) {
            window.location.reload();
        }
    }, 60000);
})();
