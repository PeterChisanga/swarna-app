"use strict";
/* Crushing page: ticking timer + a quiet 30-second status check. Never reloads on its own. */
(function () {
    const banner = document.getElementById("live-banner");
    if (!banner) return;

    const pad = (n) => String(n).padStart(2, "0");

    function fmt(sec) {
        sec = Math.max(0, Math.floor(sec));
        const d = Math.floor(sec / 86400);
        const h = Math.floor((sec % 86400) / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        return (d ? d + "d " : "") + pad(h) + ":" + pad(m) + ":" + pad(s);
    }

    // Use the server's clock so a wrong clock on this PC does not skew the timer.
    const offset = Number(banner.dataset.serverNow) * 1000 - Date.now();
    const nowSec = () => (Date.now() + offset) / 1000;

    const timers = [...banner.querySelectorAll("[data-since]")];
    const tick = () =>
        timers.forEach((el) => {
            el.textContent = fmt(nowSec() - Number(el.dataset.since));
        });
    if (timers.length) {
        tick();
        setInterval(tick, 1000);
    }

    // Tell the user when the shift changed elsewhere; never reload (it would wipe a half-filled form).
    const known = {
        state: banner.dataset.liveState,
        report: banner.dataset.liveReport || "",
        moisture: banner.dataset.liveMoisture || "",
    };

    const notice = document.getElementById("live-refresh");

    async function poll() {
        if (document.visibilityState !== "visible" || !notice || !notice.hidden)
            return;
        try {
            const res = await fetch(banner.dataset.statusUrl, {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
            });
            if (
                !res.ok ||
                !(res.headers.get("content-type") || "").includes(
                    "application/json",
                )
            )
                return;
            const d = await res.json();
            if (
                d.state !== known.state ||
                String(d.reportId ?? "") !== known.report ||
                String(d.moistureCount ?? "") !== known.moisture
            )
                notice.hidden = false;
        } catch (e) {
            /* network blip: try again next time */
        }
    }
    setInterval(poll, 30000);

    document
        .getElementById("live-reload")
        ?.addEventListener("click", () => window.location.reload());
})();
