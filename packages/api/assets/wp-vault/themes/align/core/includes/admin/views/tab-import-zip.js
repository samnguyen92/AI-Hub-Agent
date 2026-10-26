document.addEventListener("DOMContentLoaded", function() {
	const accordionToggle = document.getElementById("align-zip-accordion-toggle");
	const accordionBody   = document.getElementById("align-zip-accordion-body");
	const accordionIcon   = document.getElementById("align-zip-accordion-icon");
	const accordionLabel  = document.getElementById("align-zip-accordion-label");

	if (accordionToggle && accordionBody) {
		accordionToggle.addEventListener("click", () => {
			const isHidden = accordionBody.style.display === "none";
			accordionBody.style.display = isHidden ? "block" : "none";
			if (accordionIcon) accordionIcon.className = isHidden ? "dashicons dashicons-arrow-down-alt2" : "dashicons dashicons-arrow-right-alt2";
			if (accordionLabel) accordionLabel.textContent = isHidden ? "Hide Guide" : "View Guide";
		});
	}

	const dropzone = document.getElementById("align-zip-dropzone"), fileInput = document.getElementById("align-zip-file-input"), filenameSpan = document.getElementById("align-zip-filename"), selectedInfo = document.getElementById("align-zip-selected-info"), startBtn = document.getElementById("align-start-import-btn"), spinner = document.getElementById("align-import-spinner"), progressWrap = document.getElementById("align-import-progress-wrap"), progressBarFill = document.getElementById("align-progress-bar-fill"), progressPctBadge = document.getElementById("align-progress-pct-badge"), progressStageText = document.getElementById("align-progress-stage-text"), progressDetailText = document.getElementById("align-progress-detail-text"), progressTimer = document.getElementById("align-progress-timer"), pillAi = document.getElementById("align-step-pill-ai"), arrowAi = document.getElementById("align-step-arrow-ai"), pillMediaCount = document.getElementById("align-pill-media-count"), pillPagesCount = document.getElementById("align-pill-pages-count"), resultsCont = document.getElementById("align-import-results-container"), summaryMsg = document.getElementById("align-import-summary-msg"), pagesTbody = document.getElementById("align-import-pages-tbody"), toggleLogsBtn = document.getElementById("align-toggle-logs-btn"), logContainer = document.getElementById("align-import-log-container"), logContent = document.getElementById("align-import-log-content");

	const permBanner = document.getElementById("align-perm-banner"), permIcon = document.getElementById("align-perm-icon"), permMsg = document.getElementById("align-perm-msg"), permFixBtn = document.getElementById("align-perm-fix-btn"), permModal = document.getElementById("align-perm-modal-backdrop"), permModalClose = document.getElementById("align-perm-modal-close"), permModalCancel = document.getElementById("align-perm-modal-cancel"), permRecheckBtn = document.getElementById("align-perm-recheck-btn"), permIssuesList = document.getElementById("align-perm-issues-list"), permCommandsBox = document.getElementById("align-perm-commands-box");

	let selectedZipFile = null, systemPerms = null, importTimer = null, timerStartTime = 0;

	const stepPills = {
		scan: document.getElementById("align-step-pill-scan"),
		ai: document.getElementById("align-step-pill-ai"),
		reset: document.getElementById("align-step-pill-reset"),
		media: document.getElementById("align-step-pill-media"),
		parts: document.getElementById("align-step-pill-parts"),
		pages: document.getElementById("align-step-pill-pages"),
		finalize: document.getElementById("align-step-pill-finalize")
	};
	const stepOrder = ["scan", "ai", "reset", "media", "parts", "pages", "finalize"];

	function setStepActive(activeKey) {
		let isPassed = true;
		stepOrder.forEach(key => {
			const el = stepPills[key];
			if (!el) return;
			el.classList.remove("is-active", "is-done");
			if (key === activeKey) {
				el.classList.add("is-active");
				isPassed = false;
			} else if (isPassed) {
				el.classList.add("is-done");
			}
		});
	}

	function markAllStepsDone() {
		stepOrder.forEach(key => {
			const el = stepPills[key];
			if (!el) return;
			el.classList.remove("is-active");
			el.classList.add("is-done");
		});
	}

	function startImportTimer() {
		if (importTimer) clearInterval(importTimer);
		timerStartTime = Date.now();
		if (progressTimer) progressTimer.textContent = "00:00";
		importTimer = setInterval(() => {
			const sec = Math.floor((Date.now() - timerStartTime) / 1000);
			const mins = String(Math.floor(sec / 60)).padStart(2, "0");
			const secs = String(sec % 60).padStart(2, "0");
			if (progressTimer) progressTimer.textContent = `${mins}:${secs}`;
		}, 1000);
	}

	function stopImportTimer() {
		if (importTimer) {
			clearInterval(importTimer);
			importTimer = null;
		}
	}

	function checkPermissions(callback) {
		const formData = new FormData();
		formData.append("action", "align_check_importer_permissions");
		formData.append("nonce", getNonce());

		fetch(ajaxurl, { method: "POST", body: formData })
		.then(r => r.json())
		.then(data => {
			if (data.success && data.data) {
				systemPerms = data.data;
				updatePermissionUI(systemPerms);
			}
			if (typeof callback === "function") callback(systemPerms);
		})
		.catch(() => { if (typeof callback === "function") callback(null); });
	}

	function updatePermissionUI(perms) {
		if (!perms || !permBanner) return;
		permBanner.style.display = "flex";
		if (perms.uploads_writable && perms.theme_writable) {
			permBanner.style.background = "#f0fdf4"; permBanner.style.borderColor = "#bbf7d0"; permBanner.style.color = "#15803d";
			permIcon.className = "dashicons dashicons-yes-alt"; permIcon.style.color = "#16a34a";
			permMsg.textContent = "Server Permissions OK: wp-content/uploads and themes are writable."; permFixBtn.style.display = "none";
		} else if (perms.uploads_writable) {
			permBanner.style.background = "#fffbeb"; permBanner.style.borderColor = "#fef3c7"; permBanner.style.color = "#b45309";
			permIcon.className = "dashicons dashicons-info"; permIcon.style.color = "#d97706";
			permMsg.textContent = "Theme is read-only. Assets fallback to wp-content/uploads/align-assets."; permFixBtn.style.display = "inline-block";
		} else {
			permBanner.style.background = "#fef2f2"; permBanner.style.borderColor = "#fecaca"; permBanner.style.color = "#991b1b";
			permIcon.className = "dashicons dashicons-warning"; permIcon.style.color = "#dc2626";
			permMsg.textContent = "Write permission denied on wp-content/uploads. Media & assets cannot be saved."; permFixBtn.style.display = "inline-block";
		}
		if (permIssuesList) permIssuesList.innerHTML = (perms.issues && perms.issues.length) ? perms.issues.map(i => "<div>• " + i + "</div>").join("") : "<div>• All writable.</div>";
		if (permCommandsBox && perms.commands) permCommandsBox.textContent = perms.commands.join("\n");
	}

	function openPermModal() { if (permModal) permModal.style.display = "flex"; }
	function closePermModal() { if (permModal) permModal.style.display = "none"; }

	if (permFixBtn) permFixBtn.addEventListener("click", openPermModal);
	if (permModalClose) permModalClose.addEventListener("click", closePermModal);
	if (permModalCancel) permModalCancel.addEventListener("click", closePermModal);
	if (permRecheckBtn) {
		permRecheckBtn.addEventListener("click", () => {
			permRecheckBtn.setAttribute("disabled", "disabled");
			checkPermissions(p => { permRecheckBtn.removeAttribute("disabled"); if (p?.uploads_writable) { closePermModal(); alert("Server permissions OK!"); } else alert("Uploads still not writable."); });
		});
	}

	checkPermissions();
	if (!dropzone || !fileInput) return;

	if (toggleLogsBtn && logContainer) {
		toggleLogsBtn.addEventListener("click", () => {
			const isOpen = logContainer.style.display === "block";
			logContainer.style.display = isOpen ? "none" : "block";
			const caret = document.getElementById("align-logs-caret");
			if (caret) caret.className = isOpen ? "dashicons dashicons-arrow-down-alt2" : "dashicons dashicons-arrow-up-alt2";
		});
	}

	dropzone.addEventListener("click", e => { if (e.target !== fileInput) fileInput.click(); });
	["dragenter", "dragover"].forEach(n => dropzone.addEventListener(n, e => { e.preventDefault(); dropzone.style.borderColor = "#0284c7"; dropzone.style.background = "#f0f9ff"; }, false));
	["dragleave", "drop"].forEach(n => dropzone.addEventListener(n, e => { e.preventDefault(); dropzone.style.borderColor = "#cbd5e1"; dropzone.style.background = "#f8fafc"; }, false));
	dropzone.addEventListener("drop", e => { if (e.dataTransfer?.files?.length) handleFileSelection(e.dataTransfer.files[0]); });
	fileInput.addEventListener("change", function() { if (this.files?.length) handleFileSelection(this.files[0]); });

	function handleFileSelection(file) {
		if (!file.name.toLowerCase().endsWith(".zip")) {
			alert("Please select a valid .zip project archive.");
			return;
		}
		selectedZipFile = file;
		filenameSpan.textContent = file.name + " (" + (file.size / (1024 * 1024)).toFixed(2) + " MB)";
		selectedInfo.style.display = "inline-flex";
		startBtn.removeAttribute("disabled");
	}

	function appendLog(msg, color) {
		const line = document.createElement("div");
		if (color) line.style.color = color;
		line.textContent = msg;
		logContent.appendChild(line);
		logContent.scrollTop = logContent.scrollHeight;
	}

	function setProgress(pct, stageText, detailText) {
		progressBarFill.style.width = pct + "%";
		progressPctBadge.textContent = pct + "%";
		if (stageText && progressStageText) progressStageText.textContent = stageText;
		if (detailText && progressDetailText) progressDetailText.textContent = detailText;
	}

	function getNonce() {
		return document.getElementById("align_settings_nonce")?.value || document.querySelector('input[name="align_settings_nonce"]')?.value || "";
	}

	async function postWithRetry(formData, retries = 3) {
		for (let i = 0; i < retries; i++) {
			try {
				const res = await fetch(ajaxurl, { method: "POST", body: formData });
				let data;
				try {
					data = await res.json();
				} catch (e) {
					if (!res.ok) throw new Error("Server error HTTP " + res.status);
				}
				if (!res.ok || (data && data.success === false)) {
					const msg = data?.data?.message || data?.message || ("HTTP " + res.status);
					throw new Error(msg);
				}
				return data;
			} catch (err) {
				if (i === retries - 1) throw err;
				await new Promise(r => setTimeout(r, 1000));
			}
		}
	}

	// AI Settings UI Toggle
	const aiOptCheckbox = document.getElementById("align-import-opt-ai-refactor");
	const aiSettingsBox = document.getElementById("align-ai-provider-settings");

	if (aiOptCheckbox && aiSettingsBox) {
		aiOptCheckbox.addEventListener("change", function() {
			aiSettingsBox.style.display = this.checked ? "block" : "none";
		});
	}

	startBtn.addEventListener("click", async function() {
		if (!selectedZipFile) return;
		if (systemPerms && !systemPerms.uploads_writable) { openPermModal(); return; }

		startBtn.setAttribute("disabled", "disabled");
		spinner.classList.add("is-active");
		progressWrap.style.display = "block";
		resultsCont.style.display = "none";
		logContent.innerHTML = "";
		progressBarFill.style.background = "#0284c7";

		const nonce = getNonce();
		const optOverwrite = document.getElementById("align-import-opt-overwrite")?.checked ? 1 : 0;
		const optWebp = document.getElementById("align-import-opt-webp")?.checked ? 1 : 0;
		const optMinify = document.getElementById("align-import-opt-minify")?.checked ? 1 : 0;
		const optAiRefactor = document.getElementById("align-import-opt-ai-refactor")?.checked ? 1 : 0;

		if (pillAi && arrowAi) {
			pillAi.style.display = optAiRefactor ? "inline-flex" : "none";
			arrowAi.style.display = optAiRefactor ? "inline-block" : "none";
		}
		startImportTimer();

		try {
			// Step 1: Init session & scan
			setStepActive("scan");
			setProgress(8, "Step 1/6: Uploading & Scanning Project...", "Extracting .zip archive and indexing files...");
			appendLog("[1/6] Uploading & scanning " + selectedZipFile.name + "...", "#38bdf8");
			const fd1 = new FormData();
			fd1.append("action", "align_import_init");
			fd1.append("nonce", nonce);
			fd1.append("zip_file", selectedZipFile);

			const initRes = await postWithRetry(fd1);
			if (!initRes || !initRes.success) throw new Error(initRes?.data?.message || "Failed to initialize session");

			const sid = initRes.data.session_id;
			let totalMedia = initRes.data.total_media || 0;
			let totalPages = initRes.data.total_pages || 0;
			if (pillMediaCount) pillMediaCount.textContent = `(0/${totalMedia})`;
			if (pillPagesCount) pillPagesCount.textContent = `(0/${totalPages})`;
			appendLog(`Found ${totalPages} pages and ${totalMedia} media files.`, "#94a3b8");

			// Step 1.5: Optional AI Refactor (OpenRouter Cloud API)
			if (optAiRefactor) {
				const aiApiKey = document.getElementById("align-ai-openrouter-key")?.value.trim() || "";
				const aiModel = document.getElementById("align-ai-openrouter-model")?.value.trim() || "google/gemini-2.5-flash";
				const aiEndpoint = "https://openrouter.ai/api/v1/chat/completions";

				if (!aiApiKey) {
					appendLog("[AI Refactor] OpenRouter API Key not configured. Continuing standard import.", "#eab308");
				} else {
					try {
						setStepActive("ai");
						setProgress(16, "Step 2/7: AI Semantic Refactor...", `Decomposing layout via OpenRouter (${aiModel})...`);
						appendLog(`[AI Refactor] Connecting to OpenRouter Cloud API (Model: ${aiModel})...`, "#a855f7");

						const fdAi = new FormData();
						fdAi.append("action", "align_import_ai_refactor");
						fdAi.append("nonce", nonce);
						fdAi.append("session_id", sid);
						fdAi.append("ai_endpoint", aiEndpoint);
						fdAi.append("ai_api_key", aiApiKey);
						fdAi.append("ai_model", aiModel);

						const aiRes = await postWithRetry(fdAi);
						if (aiRes && aiRes.success) {
							appendLog(`✨ [AI Refactor] ${aiRes.data.message}`, "#c084fc");
							if (aiRes.data.total_pages !== undefined) totalPages = parseInt(aiRes.data.total_pages, 10);
							if (aiRes.data.total_media !== undefined) totalMedia = parseInt(aiRes.data.total_media, 10);
							if (pillMediaCount) pillMediaCount.textContent = `(0/${totalMedia})`;
							if (pillPagesCount) pillPagesCount.textContent = `(0/${totalPages})`;
						} else {
							appendLog(`⚠️ [AI Refactor] Notice: ${aiRes?.data?.message || "Skipped AI refactor, continuing standard import."}`, "#eab308");
						}
					} catch (aiErr) {
						appendLog(`⚠️ [AI Refactor] Notice: ${aiErr.message}. Continuing standard import.`, "#eab308");
					}
				}
			}

			// Step 2: Reset site data & global assets
			setStepActive("reset");
			setProgress(22, "Step 3/7: Resetting Site & Registering Assets...", "Purging previous templates and registering global styles & scripts...");
			appendLog("[3/7] Wiping old site data & registering global assets...", "#38bdf8");
			const fd2 = new FormData();
			fd2.append("action", "align_import_reset");
			fd2.append("nonce", nonce);
			fd2.append("session_id", sid);
			fd2.append("overwrite", optOverwrite);
			await postWithRetry(fd2);

			// Step 3: Sideload Media Batches (10 per batch)
			setStepActive("media");
			let mediaOffset = 0;
			let totalUploaded = 0;
			let totalConverted = 0;
			const totalMediaBatches = Math.max(1, Math.ceil(totalMedia / 10));

			if (totalMedia === 0) {
				setProgress(55, "Step 4/7: Sideloading Media Assets...", "No media files detected in project archive.");
			}

			while (mediaOffset < totalMedia) {
				const limit = Math.min(10, totalMedia - mediaOffset);
				const currentBatchIdx = Math.floor(mediaOffset / 10) + 1;
				const curProcessed = mediaOffset + limit;
				const pct = 24 + Math.round((mediaOffset / Math.max(1, totalMedia)) * 36);

				setProgress(
					pct,
					`Step 4/7: Sideloading Media (${curProcessed}/${totalMedia} images)...`,
					`Processing batch ${currentBatchIdx}/${totalMediaBatches} (${limit} files) • WebP Cloud API Active`
				);
				if (pillMediaCount) pillMediaCount.textContent = `(${curProcessed}/${totalMedia})`;

				const fdM = new FormData();
				fdM.append("action", "align_import_media_batch");
				fdM.append("nonce", nonce);
				fdM.append("session_id", sid);
				fdM.append("offset", mediaOffset);
				fdM.append("limit", limit);
				fdM.append("convert_webp", optWebp);

				const mRes = await postWithRetry(fdM);
				if (mRes && mRes.data) {
					totalUploaded += (mRes.data.uploaded || limit);
					totalConverted += (mRes.data.converted || 0);
				}
				mediaOffset += limit;
				appendLog(`[Media ${mediaOffset}/${totalMedia}] Sideloaded batch ${currentBatchIdx}/${totalMediaBatches} (${totalUploaded} total, ${totalConverted} WebP)`, "#34d399");
			}

			// Step 4: Assemble Parts & Reusable Blocks
			setStepActive("parts");
			setProgress(64, "Step 5/7: Assembling Header, Footer & Patterns...", "Generating Template Parts (parts/*.html) and Gutenberg Synced Patterns...");
			appendLog("[5/7] Assembling Global Header, Footer & Reusable Blocks...", "#38bdf8");
			const fdP = new FormData();
			fdP.append("action", "align_import_parts");
			fdP.append("nonce", nonce);
			fdP.append("session_id", sid);
			fdP.append("minify", optMinify);
			fdP.append("overwrite", optOverwrite);
			await postWithRetry(fdP);

			// Step 5: Assemble Pages Batches (5 per batch)
			setStepActive("pages");
			let pageOffset = 0;
			const totalPageBatches = Math.max(1, Math.ceil(totalPages / 5));

			while (pageOffset < totalPages) {
				const limit = Math.min(5, totalPages - pageOffset);
				const currentBatchIdx = Math.floor(pageOffset / 5) + 1;
				const curProcessed = pageOffset + limit;
				const pct = 68 + Math.round((pageOffset / Math.max(1, totalPages)) * 26);

				setProgress(
					pct,
					`Step 6/7: Generating Pages (${curProcessed}/${totalPages} pages)...`,
					`Assembling batch ${currentBatchIdx}/${totalPageBatches} (${limit} pages) • Scoped CSS/JS isolation active`
				);
				if (pillPagesCount) pillPagesCount.textContent = `(${curProcessed}/${totalPages})`;

				const fdPg = new FormData();
				fdPg.append("action", "align_import_pages_batch");
				fdPg.append("nonce", nonce);
				fdPg.append("session_id", sid);
				fdPg.append("offset", pageOffset);
				fdPg.append("limit", limit);
				fdPg.append("minify", optMinify);
				fdPg.append("overwrite", optOverwrite);

				await postWithRetry(fdPg);
				pageOffset += limit;
				appendLog(`[Pages ${pageOffset}/${totalPages}] Assembled batch ${currentBatchIdx}/${totalPageBatches}`, "#4ade80");
			}

			// Step 6: Finalize
			setStepActive("finalize");
			setProgress(96, "Step 7/7: Rewriting Internal Links & Finalizing...", "Mapping internal anchor hrefs to WordPress permalinks and cleaning temporary files...");
			appendLog("[7/7] Finalizing links and cleaning temporary session...", "#38bdf8");
			const fdF = new FormData();
			fdF.append("action", "align_import_finalize");
			fdF.append("nonce", nonce);
			fdF.append("session_id", sid);

			const finalRes = await postWithRetry(fdF);
			if (!finalRes || !finalRes.success) throw new Error(finalRes?.data?.message || "Finalize failed");

			const res = finalRes.data;
			stopImportTimer();
			markAllStepsDone();
			setProgress(100, "Import Completed Successfully!", `All ${res.pages.length} components created & ${totalMedia} media files imported.`);
			progressBarFill.style.background = "#10b981";
			appendLog(res.message, "#4ade80");
			summaryMsg.textContent = res.message;
			pagesTbody.innerHTML = "";

			res.pages.forEach(p => {
				const tr = document.createElement("tr");
				let badge = p.is_part ? '<span style="background:#fdf2f8;color:#db2777;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px;">Global Part</span>' : (p.is_block ? '<span style="background:#faf5ff;color:#9333ea;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px;">Reusable Block</span>' : (p.is_home ? '<span style="background:#e0f2fe;color:#0284c7;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px;">Home</span>' : ''));
				let slugCol = p.is_part ? `<code style="font-size:12px;color:#db2777;">parts/${p.slug}.html</code>` : (p.is_block ? `<code style="font-size:12px;color:#9333ea;font-weight:600;">${p.shortcode}</code>` : `<code style="font-size:12px;color:#475569;">/${p.slug}/</code>`);
				let btnHtml = p.is_part ? `<a href="${p.editor_url}" target="_blank" class="button button-small" style="color:#db2777;">Site Editor ↗</a>` : `<a href="${p.editor_url}" target="_blank" class="button button-small" style="color:#0284c7;">Builder</a>` + (!p.is_block ? ` <a href="${p.url}" target="_blank" class="button button-small">View ↗</a>` : "");
				tr.innerHTML = `<td><div style="font-weight:600;display:flex;align-items:center;gap:6px;"><span>${p.title}</span>${badge}</div></td><td>${slugCol}</td><td><span style="font-weight:600;color:#64748b;font-size:12px;">#${p.post_id || "Global"}</span></td><td style="text-align:right;">${btnHtml}</td>`;
				pagesTbody.appendChild(tr);
			});
			resultsCont.style.display = "block";
		} catch (err) {
			stopImportTimer();
			progressBarFill.style.background = "#ef4444";
			if (progressStageText) progressStageText.textContent = "Import Failed: " + err.message;
			if (progressDetailText) progressDetailText.textContent = "An error occurred during execution. Check terminal logs below.";
			appendLog("Error: " + err.message, "#f87171");
			alert("Import failed: " + err.message);
		} finally {
			spinner.classList.remove("is-active");
			startBtn.removeAttribute("disabled");
		}
	});

	// Factory Reset Modal
	const quickResetBtn = document.getElementById("align-quick-reset-btn"), modalBackdrop = document.getElementById("align-reset-modal-backdrop"), modalCloseBtn = document.getElementById("align-reset-modal-close"), modalCancelBtn = document.getElementById("align-reset-modal-cancel"), confirmResetBtn = document.getElementById("align-reset-modal-confirm"), confirmInput = document.getElementById("align-reset-confirm-input"), confirmBtnText = document.getElementById("align-reset-btn-text");
	let countdownTimer = null, timerDone = false;

	function closeModal() {
		if (countdownTimer) clearInterval(countdownTimer);
		modalBackdrop.style.display = "none"; confirmInput.value = ""; timerDone = false;
	}

	function validateConfirmBtn() {
		const isTextMatch = (confirmInput.value.trim() === "DELETE");
		if (timerDone && isTextMatch) {
			confirmResetBtn.removeAttribute("disabled"); confirmResetBtn.style.cursor = "pointer"; confirmResetBtn.style.opacity = "1";
		} else {
			confirmResetBtn.setAttribute("disabled", "disabled"); confirmResetBtn.style.cursor = "not-allowed"; confirmResetBtn.style.opacity = "0.6";
		}
	}

	if (confirmInput) confirmInput.addEventListener("input", validateConfirmBtn);
	if (modalCloseBtn) modalCloseBtn.addEventListener("click", closeModal);
	if (modalCancelBtn) modalCancelBtn.addEventListener("click", closeModal);

	if (quickResetBtn) {
		quickResetBtn.addEventListener("click", () => {
			modalBackdrop.style.display = "flex"; confirmInput.value = ""; timerDone = false;
			confirmResetBtn.setAttribute("disabled", "disabled"); confirmResetBtn.style.cursor = "not-allowed"; confirmResetBtn.style.opacity = "0.6";
			let rem = 5; confirmBtnText.textContent = "Confirm Reset (" + rem + "s)";
			if (countdownTimer) clearInterval(countdownTimer);
			countdownTimer = setInterval(() => {
				rem--;
				if (rem > 0) confirmBtnText.textContent = "Confirm Reset (" + rem + "s)";
				else { clearInterval(countdownTimer); timerDone = true; confirmBtnText.textContent = "Confirm Factory Reset"; validateConfirmBtn(); }
			}, 1000);
		});
	}

	if (confirmResetBtn) {
		confirmResetBtn.addEventListener("click", () => {
			closeModal(); quickResetBtn.setAttribute("disabled", "disabled"); spinner.classList.add("is-active"); progressWrap.style.display = "block"; resultsCont.style.display = "none"; logContent.innerHTML = ""; progressBarFill.style.background = "#0284c7"; setProgress(40, "Executing factory reset...");
			const fd = new FormData(); fd.append("action", "align_reset_site_data"); fd.append("nonce", getNonce());
			fetch(ajaxurl, { method: "POST", body: fd }).then(r => r.json()).then(data => {
				spinner.classList.remove("is-active"); quickResetBtn.removeAttribute("disabled");
				if (data.success && data.data) { setProgress(100, "Site data purged!"); progressBarFill.style.background = "#10b981"; alert(data.data.message); }
				else { progressBarFill.style.background = "#ef4444"; progressStageText.textContent = "Reset failed"; alert(data.data?.message || "Error"); }
			}).catch(() => { spinner.classList.remove("is-active"); quickResetBtn.removeAttribute("disabled"); progressBarFill.style.background = "#ef4444"; });
		});
	}
});
