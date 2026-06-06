jQuery(function ($) {
	const storageKey = "wbp_offer_tokens";
	const pollers = new Map();
	const countdowns = new Map();

	function readStore() {
		try {
			return JSON.parse(window.localStorage.getItem(storageKey) || "{}");
		} catch (error) {
			return {};
		}
	}

	function writeStore(store) {
		window.localStorage.setItem(storageKey, JSON.stringify(store));
	}

	function setToken(productId, token) {
		const store = readStore();
		store[String(productId)] = token;
		writeStore(store);
	}

	function getToken(productId) {
		const store = readStore();
		return store[String(productId)] || "";
	}

	function clearPoller(productId) {
		const timer = pollers.get(String(productId));
		if (timer) {
			window.clearInterval(timer);
			pollers.delete(String(productId));
		}
	}

	function clearCountdown(productId) {
		const timer = countdowns.get(String(productId));
		if (timer) {
			window.clearInterval(timer);
			countdowns.delete(String(productId));
		}
	}

	function openModal($modal) {
		$modal.addClass("is-open").attr("aria-hidden", "false");
		$("body").addClass("wbp-modal-open");
		const $entry = $modal.closest(".wbp-entry");
		const productId = $entry.data("product-id");
		syncOfferState($entry, true);
		startPolling(productId, $entry);
		const $input = $modal.find('input[name="offered_price"]');
		if ($input.length) {
			window.setTimeout(function () {
				$input.trigger("focus");
			}, 120);
		}
	}

	function closeModal($modal) {
		const $entry = $modal.closest(".wbp-entry");
		const productId = $entry.data("product-id");
		clearPoller(productId);
		clearCountdown(productId);
		$modal.removeClass("is-open").attr("aria-hidden", "true");
		if (!$(".wbp-modal.is-open").length) {
			$("body").removeClass("wbp-modal-open");
		}
	}

	function formatRemaining(targetUnix) {
		const now = Math.floor(Date.now() / 1000);
		const diff = Math.max(0, targetUnix - now);
		const minutes = Math.floor(diff / 60);
		const seconds = diff % 60;
		return minutes + ":" + String(seconds).padStart(2, "0");
	}

	function renderTimer($entry, offer) {
		const $timer = $entry.find("[data-wbp-timer]");
		const productId = $entry.data("product-id");
		clearCountdown(productId);

		if (!offer) {
			$timer.text("");
			return;
		}

		let mode = "wait";
		let target = Number(offer.created_at_unix || 0) + Number(wbpPublic.waitMinutes || 10) * 60;
		let prefix = "حداقل زمان انتظار";

		if (["accepted", "countered", "customer_accepted_counter"].includes(offer.status) && offer.checkout_url && Number(offer.expires_at_unix || 0) > 0) {
			mode = "checkout";
			target = Number(offer.expires_at_unix);
			prefix = "مهلت افزودن به سبد";
		}

		function timerMarkup(diff) {
			const minutes = Math.floor(diff / 60);
			const seconds = diff % 60;
			return (
				'<span class="wbp-timer-label">' + prefix + "</span>" +
				'<span class="wbp-timer-box"><strong>' + String(minutes).padStart(2, "0") + '</strong><small>دقیقه</small></span>' +
				'<span class="wbp-timer-sep">:</span>' +
				'<span class="wbp-timer-box"><strong>' + String(seconds).padStart(2, "0") + '</strong><small>ثانیه</small></span>'
			);
		}

		function tick() {
			const now = Math.floor(Date.now() / 1000);
			if (target <= now) {
				$timer.text(mode === "checkout" ? "مهلت خرید رو به پایان است." : "زمان انتظار اولیه تمام شده.");
				clearCountdown(productId);
				return;
			}
			$timer.html(timerMarkup(target - now));
		}

		tick();
		countdowns.set(String(productId), window.setInterval(tick, 1000));
	}

	function activateTab($entry, tabName) {
		$entry.find(".wbp-tab").removeClass("is-active");
		$entry.find('.wbp-tab[data-wbp-tab="' + tabName + '"]').addClass("is-active");
		$entry.find(".wbp-tab-panel").removeClass("is-active");
		$entry.find('.wbp-tab-panel[data-wbp-panel="' + tabName + '"]').addClass("is-active");
	}

	function renderOffer($entry, offer) {
		const $statusBox = $entry.find(".wbp-offer-status");
		const $summary = $entry.find("[data-wbp-offer-summary]");
		const $link = $entry.find("[data-wbp-offer-link]");
		const $thread = $entry.find("[data-wbp-thread]");
		const $badge = $entry.find("[data-wbp-status-badge]");
		const $threadForm = $entry.find(".wbp-thread-form");

		if (!offer) {
			$statusBox.prop("hidden", true);
			renderTimer($entry, null);
			return;
		}

		$statusBox.prop("hidden", false);
		$badge.attr("class", "wbp-badge status-" + offer.status).text(offer.status_label);
		renderTimer($entry, offer);
		$summary.html(
			'<div class="wbp-offer-timeline">' +
				(offer.records || []).map(function (record) {
					return (
						'<div class="wbp-offer-record type-' + record.type + '">' +
							'<div class="wbp-offer-record-dot"></div>' +
							'<div class="wbp-offer-record-body">' +
								"<strong>" + record.label + "</strong>" +
								'<div class="wbp-offer-record-meta"><span>' + record.value + "</span><time>" + record.time + "</time></div>" +
							"</div>" +
						"</div>"
					);
				}).join("") +
			"</div>"
		);

		if (offer.status === "rejected") {
			$link.html('<div class="wbp-status-note is-rejected">این پیشنهاد رد شده است. اگر خواستی پیام جدید بگذار یا پیشنهاد تازه ثبت کن.</div>');
		} else if (["accepted", "countered", "customer_accepted_counter"].includes(offer.status) && offer.checkout_url) {
			$link.html('<a class="wbp-deal-link" href="' + offer.checkout_url + '">رفتن به خرید با قیمت توافقی</a>');
		} else if (offer.status === "pending") {
			$link.html('<div class="wbp-status-note is-pending">درخواست شما در حال بررسی است. حدود ' + wbpPublic.waitMinutes + " دقیقه منتظر بمان.</div>");
		} else {
			$link.empty();
		}

		if (offer.messages && offer.messages.length) {
			$thread.html(
				offer.messages
					.map(function (message) {
						return (
							'<div class="wbp-thread-item role-' + message.sender_type + '">' +
								"<strong>" + message.label + "</strong>" +
								"<p>" + message.message + "</p>" +
								"<time>" + message.created_at + "</time>" +
							"</div>"
						);
					})
					.join("")
			);
		} else {
			$thread.html('<div class="wbp-thread-empty">هنوز پیامی بین شما و فروشنده رد و بدل نشده است.</div>');
		}

		$threadForm.find('input[name="offer_id"]').val(offer.id);
		activateTab($entry, "negotiation");
	}

	function syncOfferState($entry, silent) {
		const productId = $entry.data("product-id");
		const token = getToken(productId);
		if (!token) {
			return;
		}

		$.post(wbpPublic.ajaxUrl, {
			action: "wbp_offer_status",
			nonce: wbpPublic.nonce,
			product_id: productId,
			token: token
		}).done(function (res) {
			if (!res.success) {
				if (!silent) {
					$entry.find(".wbp-response").addClass("is-error").text(res.data?.message || "خطا در دریافت وضعیت");
				}
				return;
			}

			renderOffer($entry, res.data.offer);
		});
	}

	function startPolling(productId, $entry) {
		clearPoller(productId);
		const token = getToken(productId);
		if (!token) {
			return;
		}

		const timer = window.setInterval(function () {
			if (!$entry.find(".wbp-modal").hasClass("is-open")) {
				clearPoller(productId);
				return;
			}
			syncOfferState($entry, true);
		}, Number(wbpPublic.pollIntervalSeconds) * 1000);

		pollers.set(String(productId), timer);
	}

	$(document).on("click", "[data-wbp-open]", function () {
		const modalId = $(this).attr("data-wbp-open");
		const $modal = $("#" + modalId);
		if ($modal.length) {
			openModal($modal);
		}
	});

	$(document).on("click", "[data-wbp-close]", function () {
		closeModal($(this).closest(".wbp-modal"));
	});

	$(document).on("click", ".wbp-tab", function () {
		const $entry = $(this).closest(".wbp-entry");
		activateTab($entry, $(this).attr("data-wbp-tab"));
	});

	$(document).on("keydown", function (event) {
		if (event.key === "Escape") {
			closeModal($(".wbp-modal.is-open"));
		}
	});

	$(document).on("submit", ".wbp-offer-form", function (event) {
		event.preventDefault();
		const $form = $(this);
		const $entry = $form.closest(".wbp-entry");
		const productId = $entry.data("product-id");
		const $response = $form.find(".wbp-response");
		const data = $form.serialize() + "&action=wbp_submit_offer";

		$response.removeClass("is-error is-success").text("در حال ثبت پیشنهاد...");

		$.post(wbpPublic.ajaxUrl, data)
			.done(function (res) {
				if (!res.success) {
					$response.addClass("is-error").text(res.data?.message || "خطا در ثبت پیشنهاد");
					return;
				}

				setToken(productId, res.data.token);
				$response.addClass("is-success").text(res.data.message);
				renderOffer($entry, res.data.offer);
				startPolling(productId, $entry);
			})
			.fail(function () {
				$response.addClass("is-error").text("ارتباط با سرور برقرار نشد.");
			});
	});

	$(document).on("submit", ".wbp-thread-form", function (event) {
		event.preventDefault();
		const $form = $(this);
		const offerId = $form.find('input[name="offer_id"]').val();
		const message = $form.find('textarea[name="message"]').val().trim();
		const $entry = $form.closest(".wbp-entry");
		if (!offerId || !message) {
			return;
		}

		$.post(wbpPublic.ajaxUrl, {
			action: "wbp_add_message",
			nonce: wbpPublic.nonce,
			offer_id: offerId,
			message: message
		}).done(function (res) {
			if (!res.success) {
				$entry.find(".wbp-response").addClass("is-error").text(res.data?.message || "ارسال پیام انجام نشد.");
				return;
			}

			$form.trigger("reset");
			syncOfferState($entry, false);
		});
	});

	$(".wbp-entry").each(function () {
		syncOfferState($(this), true);
	});
});
