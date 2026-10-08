"use strict";
/* Admin: approve / reject / edit, and the Admin-maintained dropdown lists. Depends on app.js. */
(function () {
    const tbody = E("#admin-rows");
    const base = tbody ? tbody.dataset.base : "";

    /* ---------- filters submit as soon as they change ---------- */
    E("#filter-form")?.addEventListener("change", (e) =>
        e.target.form.submit(),
    );

    /* ---------- Review / Dropdown lists tabs ---------- */
    function showPane(name) {
        document
            .querySelectorAll("#admin-tabs [data-admin]")
            .forEach((b) =>
                b.classList.toggle("active", b.dataset.admin === name),
            );
        E("#admin-review").hidden = name !== "review";
        E("#admin-lists").hidden = name !== "lists";
        history.replaceState(
            null,
            "",
            name === "lists"
                ? "#lists"
                : window.location.pathname + window.location.search,
        );
    }
    document
        .querySelectorAll("#admin-tabs [data-admin]")
        .forEach((b) =>
            b.addEventListener("click", () => showPane(b.dataset.admin)),
        );
    if (window.location.hash === "#lists") showPane("lists");

    function done(res, fallback) {
        if (res.ok) {
            flashToast(res.data.message || fallback);
            window.location.reload();
            return true;
        }
        return false;
    }

    /* ---------- Approve / Reject / Edit ---------- */
    function reject(kind, id, trigger) {
        returnFocus = trigger;
        openModal(
            "Reject this record",
            `<span><b>Crushing</b> · ${kind === "shift" ? "Shift report" : "Moisture sample"}</span>`,
            `<p class="micro">A reason is required and will remain visible in the review log.</p>
       <label for="review-reason" class="pv-label">Reason for rejection</label>
       <textarea id="review-reason" rows="4" class="review-reason" maxlength="255" placeholder="Describe the incorrect reading"></textarea>`,
            "Reject record",
            async () => {
                const reason = E("#review-reason").value.trim();
                if (!reason) {
                    setModalError("Enter a reason for rejecting this record.");
                    E("#review-reason").focus();
                    return;
                }
                const res = await api("POST", `${base}/${kind}/${id}/reject`, {
                    review_note: reason,
                });
                if (!done(res, "Record rejected with a reason"))
                    setModalError(
                        res.data.message || "Could not reject the record.",
                    );
            },
            "Cancel",
        );
    }

    const pairs = (list) => list.map((v) => [v, v]);

    function editFields(kind, d) {
        const L = window.LISTS || { oreTypes: [], streams: [] };
        const withCurrent = (list, current) =>
            current && !list.includes(current) ? [...list, current] : list;

        if (kind === "moisture") {
            return [
                ["moisture_reading_date", "Moisture reading date", "date"],
                ["moisture_reading_time", "Moisture reading time", "time"],
                [
                    "crushed_ore_moisture_pct",
                    "Crushed ore moisture (%)",
                    "text",
                ],
                ["moisture_notes", "Moisture notes", "textarea", null, true],
            ];
        }

        const fields = [
            ["crushing_reading_date", "Crushing reading date", "date"],
            ["crushing_reading_time", "Crushing reading time", "time"],
            [
                "crushing_ore_type",
                "Crushing ore type",
                "select",
                pairs(withCurrent(L.oreTypes, d.values.crushing_ore_type)),
            ],
            ["crushing_ore_name_id", "Crushing ore name / ID", "text"],
            ["crushing_start_time", "Crushing start time", "time"],
        ];

        // End-of-shift fields are only editable once the report is Closed.
        if (d.stage === "Closed") {
            fields.push(
                [
                    "crushing_moisture_sample_id",
                    "Crushing moisture sample",
                    "select",
                    d.moisture_options.map((o) => [String(o.id), o.label]),
                    true,
                ],
                ["crusher_trucks_crushed", "Crusher trucks crushed", "text"],
                ["crushing_stop_time", "Crushing stop time", "time"],
                ["crusher_running_hours", "Crusher running hours (h)", "text"],
                ["crusher_downtime_hours", "Crusher downtime (h)", "text"],
                ["crusher_downtime_reason", "Crusher downtime reason", "text"],
                ["ore_stockpile_pct", "Ore stockpile (%)", "text"],
                [
                    "crushing_shift_notes",
                    "Crushing report note",
                    "textarea",
                    null,
                    true,
                ],
            );
        }
        return fields;
    }

    function edit(kind, id, trigger) {
        let d;
        try {
            d = JSON.parse(trigger.dataset.edit);
        } catch (e) {
            toast("Could not open the editor.");
            return;
        }
        returnFocus = trigger;

        const cells = editFields(kind, d)
            .map(([name, label, type, options, full]) => {
                const value = d.values[name] ?? "";
                let control;
                if (type === "select") {
                    control =
                        `<select id="edit-${name}" data-edit-field="${name}"><option value="">Select...</option>` +
                        options
                            .map(
                                ([v, t]) =>
                                    `<option value="${esc(v)}" ${String(v) === String(value) ? "selected" : ""}>${esc(t)}</option>`,
                            )
                            .join("") +
                        "</select>";
                } else if (type === "textarea") {
                    control = `<textarea id="edit-${name}" data-edit-field="${name}" rows="3">${esc(value)}</textarea>`;
                } else {
                    control = `<input id="edit-${name}" data-edit-field="${name}" type="${type}" value="${esc(value)}" autocomplete="off">`;
                }
                return `<div class="${full ? "full" : ""}"><label for="edit-${name}">${esc(label)}</label>${control}</div>`;
            })
            .join("");

        openModal(
            "Edit · " + (kind === "shift" ? "Shift report" : "Moisture sample"),
            "<span><b>Status stays the same</b> until you change it deliberately in review.</span>",
            `<div class="edit-grid">${cells}</div>`,
            "Save edit",
            async () => {
                const payload = {};
                document.querySelectorAll("[data-edit-field]").forEach((el) => {
                    payload[el.dataset.editField] = el.value.trim();
                });
                const res = await api("PUT", `${base}/${kind}/${id}`, payload);
                if (!done(res, "Edit saved; status unchanged"))
                    setModalError(
                        res.data.message || "Could not save the edit.",
                    );
            },
            "Cancel",
        );
    }

    tbody?.addEventListener("click", async (e) => {
        const btn = e.target.closest("[data-action]");
        if (!btn) return;
        const { action, kind, id } = btn.dataset;

        if (action === "approve") {
            btn.disabled = true;
            const res = await api("POST", `${base}/${kind}/${id}/approve`);
            if (!done(res, "Record marked Approved")) {
                btn.disabled = false;
                toast(res.data.message || "Could not approve the record.");
            }
        }
        if (action === "reject") reject(kind, id, btn);
        if (action === "edit") edit(kind, id, btn);
    });

    /* ---------- Dropdown lists ---------- */
    E("#admin-lists")?.addEventListener("click", async (e) => {
        const btn = e.target.closest("[data-master-toggle]");
        if (!btn) return;
        btn.disabled = true;
        const res = await api(
            "POST",
            `${base}/masters/${btn.dataset.masterToggle}/${btn.dataset.id}/toggle`,
        );
        if (!done(res, "Dropdown name status updated")) {
            btn.disabled = false;
            toast(res.data.message || "Could not update the list.");
        }
    });

    document.querySelectorAll("[data-master-add]").forEach((form) => {
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const name = form.elements.name.value.trim();
            if (!name) {
                toast("Enter a name to add.");
                return;
            }
            const res = await api(
                "POST",
                `${base}/masters/${form.dataset.masterAdd}`,
                { name },
            );
            if (!done(res, "Added to the dropdown list"))
                toast(res.data.message || "Could not add the value.");
        });
    });
})();
