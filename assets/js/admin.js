jQuery(function ($) {
	let latestSeenId = Number(window.sessionStorage.getItem("wbp_latest_seen_id") || 0);

	function playNotificationSound() {
		try {
			const AudioContextClass = window.AudioContext || window.webkitAudioContext;
			if (!AudioContextClass) {
				return;
			}
			const ctx = new AudioContextClass();
			const osc = ctx.createOscillator();
			const gain = ctx.createGain();
			osc.type = "sine";
			osc.frequency.setValueAtTime(880, ctx.currentTime);
			gain.gain.setValueAtTime(0.001, ctx.currentTime);
			gain.gain.exponentialRampToValueAtTime(0.08, ctx.currentTime + 0.02);
			gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
			osc.connect(gain);
			gain.connect(ctx.destination);
			osc.start();
			osc.stop(ctx.currentTime + 0.28);
		} catch (error) {
			// Ignore audio failures silently.
		}
	}

	function notifyNewOffer(pendingCount) {
		playNotificationSound();
		if (!("Notification" in window)) {
			return;
		}
		if (Notification.permission === "granted") {
			new Notification("پیشنهاد جدید ثبت شد", {
				body: "یک درخواست جدید برای بررسی دارید. در انتظار: " + pendingCount
			});
			return;
		}
		if (Notification.permission !== "denied") {
			Notification.requestPermission();
		}
	}

	function pollAdminOffers() {
		if (typeof wbpAdmin === "undefined") {
			return;
		}

		$.post(wbpAdmin.ajaxUrl, {
			action: "wbp_admin_poll",
			nonce: wbpAdmin.nonce
		}).done(function (res) {
			if (!res.success || !res.data?.latest_id) {
				return;
			}

			const latestId = Number(res.data.latest_id);
			if (!latestSeenId) {
				latestSeenId = latestId;
				window.sessionStorage.setItem("wbp_latest_seen_id", String(latestSeenId));
				return;
			}

			if (latestId > latestSeenId) {
				latestSeenId = latestId;
				window.sessionStorage.setItem("wbp_latest_seen_id", String(latestSeenId));
				notifyNewOffer(Number(res.data.pending_count || 0));
			}
		});
	}

	$(document).on("click", ".wbp-panel .button", function () {
		$(this).addClass("is-busy");
	});

	pollAdminOffers();
	window.setInterval(pollAdminOffers, Number(wbpAdmin?.pollInterval || 10) * 1000);
});
