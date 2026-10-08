"use strict";
/*
 * Crushing page: three entry pages (Shift report start / Shift report end / Moisture sample),
 * each with a preview pop-up before anything is saved (PRD 8.1).
 * Depends on app.js (E, esc, api, openModal, closeModal, toast, ...).
 */
(function () {
    const openReports = (window.CRUSHING && window.CRUSHING.openReports) || [];
    const moistureShifts =
        (window.CRUSHING && window.CRUSHING.moistureShifts) || [];

    const fieldEls = (form) => [...form.querySelectorAll("[data-capture]")];

    /* ---------- plant clock (server time in the plant time zone) ---------- */
    function plantNow() {
        const c = window.PLANT_NOW;
        if (!c) return null;
        const d = new Date(
            c.epoch * 1000 + (Date.now() - c.loaded) + c.offset * 60000,
        );
        const p = (n) => String(n).padStart(2, "0");
        return {
            date: `${d.getUTCFullYear()}-${p(d.getUTCMonth() + 1)}-${p(d.getUTCDate())}`,
            time: `${p(d.getUTCHours())}:${p(d.getUTCMinutes())}`,
        };
    }

    /* ---------- validation (same rules as the mockup, plus server-side validation) ---------- */
    function clearErrors(form) {
        form.querySelectorAll(".field-error").forEach((x) => {
            x.textContent = "";
        });
        form.querySelectorAll("[aria-invalid]").forEach((x) =>
            x.removeAttribute("aria-invalid"),
        );
    }

    function showError(form, name, message) {
        const err = form.querySelector(`[data-error-for="${name}"]`);
        const input = form.querySelector(`[name="${name}"]`);
        if (err) err.textContent = message;
        if (input) input.setAttribute("aria-invalid", "true");
        return input;
    }

    function validate(form) {
        clearErrors(form);
        let first = null;

        fieldEls(form).forEach((el) => {
            const v = el.value.trim();
            const kind = el.dataset.kind;
            const label = el.dataset.label;
            let msg = "";

            if (el.dataset.required === "1" && !v)
                msg = `${label} is required.`;

            if (!msg && v && (kind === "number" || kind === "integer")) {
                if (
                    !/^-?(?:\d+\.?\d*|\.\d+)$/.test(v) ||
                    !Number.isFinite(Number(v))
                ) {
                    msg = `Enter a number for ${label} (for example 2.5).`;
                } else if (kind === "integer" && !Number.isInteger(Number(v))) {
                    msg = `${label} must be a whole number.`;
                } else {
                    const min = el.dataset.min,
                        max = el.dataset.max;
                    if (
                        (min !== undefined && Number(v) < Number(min)) ||
                        (max !== undefined && Number(v) > Number(max))
                    ) {
                        msg = `${label} is outside the possible ${min ?? "–"} to ${max ?? "–"} ${el.dataset.unit || ""} range. Check the value.`;
                    }
                }
            }

            if (msg) {
                const input = showError(form, el.name, msg);
                first = first || input;
            }
        });

        // Start of shift: the reading cannot be in the future.
        const dEl = form.querySelector('[name="crushing_reading_date"]');
        const tEl = form.querySelector('[name="crushing_reading_time"]');
        const now = plantNow();
        if (dEl && tEl && now && dEl.value && tEl.value) {
            let bad = null;
            if (dEl.value > now.date) {
                bad = showError(
                    form,
                    dEl.name,
                    "The reading date cannot be in the future.",
                );
            } else if (dEl.value === now.date && tEl.value > now.time) {
                bad = showError(
                    form,
                    tEl.name,
                    "The reading time cannot be in the future.",
                );
            }
            first = first || bad;
        }

        // Moisture: validate the selected shift date only.
        const mShift = form.querySelector('[name="crushing_shift_report_id"]');

        if (mShift && typeof plantNow === "function") {
            const shift = moistureShifts.find(
                (x) => String(x.id) === mShift.value,
            );

            const current = plantNow();

            if (shift && current && shift.op_date > current.date) {
                const bad = showError(
                    form,
                    mShift.name,
                    "The selected shift date cannot be in the future.",
                );

                first = first || bad;
            }
        }

        // End of shift: the stop time cannot be in the future.
        const stopEl = form.querySelector('[name="crushing_stop_time"]');
        const repSel = E("#end-report");
        if (
            stopEl &&
            stopEl.value &&
            repSel &&
            typeof plantNow === "function"
        ) {
            const r = openReports.find((x) => String(x.id) === repSel.value);
            const now = plantNow();
            if (r && now) {
                const d = stopDate(r, stopEl.value);
                if (
                    d > now.date ||
                    (d === now.date && stopEl.value > now.time)
                ) {
                    const bad = showError(
                        form,
                        stopEl.name,
                        "The stop time cannot be in the future.",
                    );
                    first = first || bad;
                }
            }
        }

        if (first) first.focus();
        return !first;
    }

    function collect(form) {
        const payload = {};
        fieldEls(form).forEach((el) => {
            payload[el.name] = el.value.trim();
        });
        return payload;
    }

    function showServerErrors(form, errors) {
        clearErrors(form);
        let first = null;
        Object.entries(errors || {}).forEach(([name, messages]) => {
            const input = showError(form, name, messages[0]);
            first = first || input;
        });
        first?.focus();
    }

    /* ---------- preview pop-up ---------- */
    function previewRows(form) {
        return fieldEls(form)
            .map((el) => {
                const v = el.value.trim();
                const text =
                    el.tagName === "SELECT" && v
                        ? el.options[el.selectedIndex].text
                        : v;
                const unit = el.dataset.unit || "";
                return (
                    `<div class="pv-row"><span class="pv-label">${esc(el.dataset.label)}</span>` +
                    `<span class="pv-value ${v === "" ? "blank" : ""}">${v !== "" ? esc(text) + (unit ? " " + esc(unit) : "") : "No reading"}</span></div>`
                );
            })
            .join("");
    }

    function readingMeta(team, date, time) {
        return (
            `<span><b>Section:</b> Crushing</span><span><b>Team:</b> ${esc(team)}</span>` +
            `<span><b>Reading:</b> ${esc(fmtDate(date))} · ${esc(time)}</span>` +
            `<span><b>Operating date:</b> ${esc(fmtDate(deriveOperatingDate(date, time)))}</span>` +
            `<span><b>Shift:</b> ${esc(deriveShift(time))}</span>`
        );
    }

    function updateDerived(form, dateName, timeName, targetSel) {
        const d = form.querySelector(`[name="${dateName}"]`)?.value;
        const t = form.querySelector(`[name="${timeName}"]`)?.value;
        const target = E(targetSel);
        if (!target) return;
        target.textContent =
            d && t
                ? `Operating date: ${fmtDate(deriveOperatingDate(d, t))} · ${deriveShift(t)} shift`
                : "";
    }

    /* ---------- submit ---------- */
    function handleConflict(form, data) {
        if (
            form.dataset.form === "start" &&
            data.existing_report_id &&
            data.existing_stage === "Open"
        ) {
            // "A second start-of-shift submission is refused, and the page offers to open the existing report."
            openModal(
                "Shift report already opened",
                "",
                `<p class="micro">${esc(data.message)}</p>`,
                "Open existing report",
                () => {
                    closeModal();
                    switchStage("end", data.existing_report_id);
                },
                "Cancel",
            );
            return;
        }
        closeModal();
        toast(data.message || "This record already exists.");
    }

    async function submit(form, cfg, payload) {
        const confirmBtn = E("#preview-confirm");
        confirmBtn.disabled = true;
        const res = await api(cfg.method, cfg.url, payload);
        confirmBtn.disabled = false;

        if (res.ok) {
            closeModal();
            flashToast(res.data.message || "Saved");
            const p = new URLSearchParams();
            if (res.data.operating_date) p.set("date", res.data.operating_date);
            window.location.href =
                window.location.pathname +
                (p.toString() ? "?" + p.toString() : "");
            return;
        }

        if (res.status === 422) {
            closeModal();
            showServerErrors(form, res.data.errors);
            toast(res.data.message || "Check the highlighted fields.");
            return;
        }

        if (res.status === 409) {
            handleConflict(form, res.data);
            return;
        }

        setModalError(res.data.message || "Could not save. Try again.");
    }

    function bind(form, build) {
        form.addEventListener("submit", (e) => {
            e.preventDefault();
            if (!validate(form)) return;
            const payload = collect(form);
            const cfg = build(form, payload);
            if (!cfg) return;
            returnFocus = e.submitter;
            openModal(
                cfg.title,
                cfg.meta,
                cfg.body,
                "Confirm and submit",
                () => submit(form, cfg, payload),
                "Go back and edit",
            );
        });
    }

    /* ---------- start of shift ---------- */
    const startForm = E("#form-start");
    if (startForm) {
        bind(startForm, (form, p) => ({
            method: "POST",
            url: form.dataset.url,
            title: "Shift report — start of shift · review",
            meta: readingMeta(
                "Production",
                p.crushing_reading_date,
                p.crushing_reading_time,
            ),
            body: previewRows(form),
        }));
        const upd = () =>
            updateDerived(
                startForm,
                "crushing_reading_date",
                "crushing_reading_time",
                "#derived-start",
            );
        startForm.addEventListener("input", upd);
        startForm.addEventListener("change", upd);
        startForm.addEventListener("reset", () => setTimeout(upd, 0));

        // Production sees a locked, live date and time; only Admin can change them.
        const lockedDate = startForm.querySelector(
            '[name="crushing_reading_date"][readonly]',
        );
        const lockedTime = startForm.querySelector(
            '[name="crushing_reading_time"][readonly]',
        );
        const syncClock = () => {
            const n = plantNow();
            if (!n) return;
            if (lockedDate) lockedDate.value = n.date;
            if (lockedTime) lockedTime.value = n.time;
            upd();
        };
        if (lockedDate || lockedTime) {
            syncClock();
            setInterval(syncClock, 15000);
            startForm.addEventListener("reset", () => setTimeout(syncClock, 0));
        }

        upd();
    }

    /* ---------- end of shift ---------- */
    const endForm = E("#form-end");

    function renderEnd() {
        if (!endForm) return;
        const sel = E("#end-report");
        const r = openReports.find((x) => String(x.id) === sel.value);
        const box = E("#end-summary");
        const ms = endForm.querySelector(
            '[name="crushing_moisture_sample_id"]',
        );
        const warn = E("#end-moisture-empty");

        if (!r) {
            box.innerHTML = "";
            ms.innerHTML = '<option value="">Select...</option>';
            ms.disabled = true;
            return;
        }

        box.innerHTML = r.summary
            .map(([k, v]) => `<div>${esc(k)}: <b>${esc(v)}</b></div>`)
            .join("");
        ms.disabled = false;
        ms.innerHTML =
            '<option value="">Select...</option>' +
            r.moisture_options
                .map(
                    (o) =>
                        `<option value="${esc(o.id)}">${esc(o.label)}</option>`,
                )
                .join("");
        warn.hidden = r.moisture_options.length > 0;
    }

    if (endForm) {
        E("#end-report").addEventListener("change", () => {
            clearErrors(endForm);
            renderEnd();
        });
        endForm.addEventListener("reset", () => setTimeout(renderEnd, 0));

        bind(endForm, (form) => {
            const sel = E("#end-report");
            const r = openReports.find((x) => String(x.id) === sel.value);
            if (!r) return null;

            const startRows = r.summary
                .map(
                    ([k, v]) =>
                        `<div class="pv-row"><span class="pv-label">${esc(k)}</span><span class="pv-value">${esc(v)}</span></div>`,
                )
                .join("");

            return {
                method: "PUT",
                url: form.dataset.urlTemplate.replace(
                    "__ID__",
                    encodeURIComponent(sel.value),
                ),
                title: "Shift report — end of shift · review",
                meta: `<span><b>Section:</b> Crushing</span><span><b>Team:</b> Production</span><span><b>Report:</b> ${esc(r.label)}</span><span><b>Stage after submit:</b> Closed</span>`,
                body:
                    `<div class="preview-group">Opened at start of shift · read-only</div>${startRows}` +
                    `<div class="preview-group">End of shift</div>${previewRows(form)}`,
            };
        });

        const stopEl = endForm.querySelector('[name="crushing_stop_time"]');
        stopEl.addEventListener("change", () => {
            const r = openReports.find(
                (x) => String(x.id) === E("#end-report").value,
            );
            const now = typeof plantNow === "function" ? plantNow() : null;
            if (!r || !now || !stopEl.value) return;

            const d = stopDate(r, stopEl.value);
            if (d > now.date) {
                stopEl.value = "";
                toast("That stop time has not happened yet.");
            } else if (d === now.date && stopEl.value > now.time) {
                stopEl.value = now.time;
                toast(
                    "Stop time cannot be in the future. Set to the current time.",
                );
            }
        });

        renderEnd();
    }

    /* ---------- moisture sample ---------- */
    const moistureForm = E("#form-moisture");

    function pickedShift(form) {
        const v = form.querySelector(
            '[name="crushing_shift_report_id"]',
        )?.value;
        return moistureShifts.find((x) => String(x.id) === v) || null;
    }

    if (moistureForm) {
        const oreBox = E("#f-moisture-ore");
        const derived = E("#derived-moisture");
        const timeEl = moistureForm.querySelector(
            '[name="moisture_reading_time"]',
        );

        const upd = () => {
            const s = pickedShift(moistureForm);
            if (oreBox) oreBox.value = s ? s.ore : "";
            if (!derived) return;
            derived.textContent =
                s && timeEl.value
                    ? `Sample date: ${fmtDate(sampleDate(s.op_date, timeEl.value))} · ${s.shift} shift · report ${s.stage}`
                    : "";
        };

        bind(moistureForm, (form, p) => {
            const s = pickedShift(form);
            if (!s) return null;
            const date = sampleDate(s.op_date, p.moisture_reading_time);

            return {
                method: "POST",
                url: form.dataset.url,
                title: "Moisture sample · review",
                meta:
                    `<span><b>Section:</b> Crushing</span><span><b>Team:</b> Lab</span>` +
                    `<span><b>Sample:</b> ${esc(fmtDate(date))} · ${esc(p.moisture_reading_time)}</span>` +
                    `<span><b>Shift:</b> ${esc(s.shift)}</span>`,
                body:
                    `<div class="pv-row"><span class="pv-label">Sample stream (ore being crushed)</span>` +
                    `<span class="pv-value">${esc(s.ore)}</span></div>` +
                    previewRows(form),
            };
        });

        moistureForm.addEventListener("input", upd);
        moistureForm.addEventListener("change", upd);
        moistureForm.addEventListener("reset", () => setTimeout(upd, 0));

        // Do not let a future time stay in the field.
        timeEl.addEventListener("change", () => {
            const s = pickedShift(moistureForm);
            const now = typeof plantNow === "function" ? plantNow() : null;
            if (!s || !now || !timeEl.value) return;

            const d = sampleDate(s.op_date, timeEl.value);
            if (d > now.date) {
                timeEl.value = "";
                toast("That time has not happened yet for this shift's day.");
            } else if (d === now.date && timeEl.value > now.time) {
                timeEl.value = now.time;
                toast(
                    "Moisture time cannot be in the future. Set to the current time.",
                );
            }
            upd();
        });
        upd();
    }

    /* ---------- tabs: Shift report / Moisture sample ---------- */
    document.querySelectorAll("#report-tabs [data-tab]").forEach((btn) => {
        btn.addEventListener("click", () => {
            document
                .querySelectorAll("#report-tabs [data-tab]")
                .forEach((b) => {
                    const on = b === btn;
                    b.classList.toggle("active", on);
                    b.setAttribute("aria-selected", on ? "true" : "false");
                    const panel = E("#panel-" + b.dataset.tab);
                    if (panel) panel.hidden = !on;
                });
        });
    });

    /* ---------- Start / End of shift switch ---------- */
    function switchStage(stage, reportId) {
        const start = E("#stage-start"),
            end = E("#stage-end");
        if (!start || !end) return;

        // Make sure the Shift report tab is showing.
        const shiftTab = E('#report-tabs [data-tab="shift"]');
        if (shiftTab && !shiftTab.classList.contains("active"))
            shiftTab.click();

        start.hidden = stage !== "start";
        end.hidden = stage !== "end";
        document
            .querySelectorAll("#stage-nav [data-stage]")
            .forEach((b) =>
                b.classList.toggle("active", b.dataset.stage === stage),
            );

        if (stage === "end" && reportId && E("#end-report")) {
            E("#end-report").value = String(reportId);
            renderEnd();
        }
    }
    document
        .querySelectorAll("#stage-nav [data-stage]")
        .forEach((b) =>
            b.addEventListener("click", () => switchStage(b.dataset.stage)),
        );

    /* ---------- View button in Recent readings ---------- */
    function showDetail(d, trigger) {
        returnFocus = trigger;
        const meta = Object.entries(d.meta)
            .map(([k, v]) => `<span><b>${esc(k)}:</b> ${esc(v)}</span>`)
            .join("");

        let body = "";
        d.rows.forEach((r) => {
            if (r.group) {
                body += `<div class="preview-group">${esc(r.group)}</div>`;
                return;
            }
            body +=
                `<div class="pv-row"><span class="pv-label">${esc(r.label)}</span>` +
                `<span class="pv-value ${r.value === null ? "blank" : ""}">${r.value === null ? "Not reported" : esc(r.value)}</span></div>`;
        });

        body +=
            `<div class="preview-group">Notes</div><div class="pv-row"><span class="pv-label">Notes</span>` +
            `<span class="pv-value ${d.notes ? "" : "blank"}">${d.notes ? esc(d.notes) : "No notes entered"}</span></div>`;

        if (d.review_note) {
            body += `<div class="pv-row"><span class="pv-label">Review note</span><span class="pv-value">${esc(d.review_note)}</span></div>`;
        }

        openModal(d.title, meta, body, null, null, "Close");
    }

    E("#recent-rows")?.addEventListener("click", (e) => {
        const btn = e.target.closest("[data-detail]");
        if (!btn) return;
        try {
            showDetail(JSON.parse(btn.dataset.detail), btn);
        } catch (err) {
            toast("Could not open the details.");
        }
    });
})();

/* ---------- Live note counters ---------- */
(function () {
    "use strict";

    function updateCounter(input) {
        const counter = document.getElementById(input.id + "-count");
        if (!counter) return;

        const length = input.value.length;
        const limit = input.maxLength;

        counter.textContent =
            limit >= 0 ? `${length} / ${limit}` : `${length} characters`;

        counter.classList.toggle("at-limit", limit >= 0 && length >= limit);
    }

    document.querySelectorAll("[data-note-counter]").forEach(updateCounter);

    document.addEventListener("input", function (event) {
        if (event.target.matches("[data-note-counter]")) {
            updateCounter(event.target);
        }
    });

    document.addEventListener("reset", function (event) {
        const form = event.target;

        setTimeout(function () {
            form.querySelectorAll("[data-note-counter]").forEach(updateCounter);
        }, 0);
    });
})();

/* Calendar date of a sample taken at `time` on operating day `opDate` (day runs 06:00 to 05:59). */
function sampleDate(opDate, time) {
    if (String(time).slice(0, 5) >= PLANT.dayStart) return opDate;
    const d = new Date(opDate + "T12:00:00Z");
    d.setUTCDate(d.getUTCDate() + 1);
    return d.toISOString().slice(0, 10);
}

/* Date of a stop time on a shift report: a time earlier than the start means the next day. */
function stopDate(r, time) {
    if (time >= r.start_time) return r.start_date;
    const d = new Date(r.start_date + "T12:00:00Z");
    d.setUTCDate(d.getUTCDate() + 1);
    return d.toISOString().slice(0, 10);
}
