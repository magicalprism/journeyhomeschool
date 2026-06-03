(function () {
	'use strict';

	function getElementHeight(selector) {
		var element = document.querySelector(selector);

		if (!element) {
			return 0;
		}

		return element.getBoundingClientRect().height;
	}

	function updateTemplateMinHeightVars() {
		var headerHeight = getElementHeight('#top-header') + getElementHeight('#main-header');
		var footerHeight = getElementHeight('#main-footer');

		document.documentElement.style.setProperty('--jha-site-header-height', headerHeight + 'px');
		document.documentElement.style.setProperty('--jha-site-footer-height', footerHeight + 'px');
	}

	function scheduleTemplateMinHeightUpdate(delay) {
		window.setTimeout(function () {
			window.requestAnimationFrame(updateTemplateMinHeightVars);
		}, delay || 0);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var toggles = document.querySelectorAll('.jha-course-submenu-toggle');
		var mobileMenuToggle = document.querySelector('.jha-course-mobile-menu-toggle');
		var mobileMenu = document.getElementById('jha-course-lessons-panel');
		var progressRefreshTimeout;

		function collapseMenuItem(menuItem) {
			menuItem.classList.remove('is-expanded');

			Array.prototype.slice.call(menuItem.querySelectorAll('.jha-course-menu-item.is-expanded')).forEach(function (childItem) {
				childItem.classList.remove('is-expanded');
			});

			Array.prototype.slice.call(menuItem.querySelectorAll('.jha-course-submenu-toggle')).forEach(function (childToggle) {
				childToggle.setAttribute('aria-expanded', 'false');
			});
		}

		function closeOtherMenuItems(activeMenuItem) {
			Array.prototype.slice.call(document.querySelectorAll('.jha-course-menu-item.is-expanded')).forEach(function (menuItem) {
				if (menuItem === activeMenuItem || menuItem.contains(activeMenuItem)) {
					return;
				}

				collapseMenuItem(menuItem);
			});
		}

		function initializeMenuAccordion() {
			var activeItem = document.querySelector('.jha-course-menu-item.is-active');
			var keepOpen = [];

			if (activeItem) {
				keepOpen.push(activeItem);

				while (activeItem.parentElement) {
					activeItem = activeItem.parentElement.closest('.jha-course-menu-item');

					if (!activeItem) {
						break;
					}

					keepOpen.push(activeItem);
				}
			}

			Array.prototype.slice.call(document.querySelectorAll('.jha-course-menu-item.is-expanded')).forEach(function (menuItem) {
				if (keepOpen.indexOf(menuItem) === -1) {
					collapseMenuItem(menuItem);
				}
			});
		}

		function refreshCourseProgress() {
			var progressTracker = document.querySelector('.jha-course-progress-tracker[data-course-id]');
			var requestBody;

			if (!progressTracker || !window.jhaCourseMenu || !window.jhaCourseMenu.ajaxUrl) {
				return;
			}

			requestBody = new window.FormData();
			requestBody.append('action', 'jha_get_course_progress_tracker');
			requestBody.append('nonce', window.jhaCourseMenu.progressNonce || '');
			requestBody.append('courseId', progressTracker.getAttribute('data-course-id'));

			progressTracker.setAttribute('aria-busy', 'true');

			window.fetch(window.jhaCourseMenu.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: requestBody
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (response) {
					if (!response || !response.success || !response.data || typeof response.data.html !== 'string') {
						return;
					}

					progressTracker.innerHTML = response.data.html;
				})
				.catch(function () {
					// Progress is decorative; leave the existing value in place if refresh fails.
				})
				.finally(function () {
					progressTracker.removeAttribute('aria-busy');
				});
		}

		function scheduleCourseProgressRefresh(delay) {
			window.clearTimeout(progressRefreshTimeout);
			progressRefreshTimeout = window.setTimeout(refreshCourseProgress, delay || 900);
		}

		function scheduleProgressAllyRefreshes() {
			scheduleCourseProgressRefresh(600);
			window.setTimeout(refreshCourseProgress, 1600);
			window.setTimeout(refreshCourseProgress, 3200);
		}

		scheduleTemplateMinHeightUpdate();
		window.addEventListener('load', function () {
			scheduleTemplateMinHeightUpdate();
			scheduleTemplateMinHeightUpdate(250);
		});
		window.addEventListener('resize', function () {
			scheduleTemplateMinHeightUpdate();
		});
		window.addEventListener('pageshow', function () {
			scheduleTemplateMinHeightUpdate();
		});

		if (mobileMenuToggle && mobileMenu) {
			mobileMenuToggle.addEventListener('click', function () {
				var isExpanded = mobileMenuToggle.getAttribute('aria-expanded') === 'true';

				mobileMenuToggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
				mobileMenu.classList.toggle('is-mobile-expanded', !isExpanded);
			});
		}

		initializeMenuAccordion();

		toggles.forEach(function (toggle) {
			toggle.addEventListener('click', function () {
				var menuItem = toggle.closest('.jha-course-menu-item');
				var isExpanded = toggle.getAttribute('aria-expanded') === 'true';

				if (!menuItem) {
					return;
				}

				if (!isExpanded) {
					closeOtherMenuItems(menuItem);
				}

				toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
				menuItem.classList.toggle('is-expanded', !isExpanded);
			});
		});

		document.addEventListener('change', function (event) {
			if (event.target.closest('.jha-course-content [class*="progressally"], .jha-course-content [class*="accessally"]')) {
				scheduleProgressAllyRefreshes();
			}
		});

		document.addEventListener('click', function (event) {
			if (event.target.closest('.jha-course-content [class*="progressally"] button, .jha-course-content [class*="progressally"] a, .jha-course-content [class*="accessally"] button, .jha-course-content [class*="accessally"] a')) {
				scheduleProgressAllyRefreshes();
			}
		});
	});
})();
