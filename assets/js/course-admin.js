(function ($) {
	'use strict';

	$(function () {
		var $list = $('#jha-course-child-order-list');
		var $input = $('#jha-course-child-order');
		var $trashInput = $('#jha-course-child-trash');
		var $hiddenInput = $('#jha-course-hidden-lessons');
		var $newPagesInput = $('#jha-course-new-child-pages');
		var $newTitleInput = $('#jha-course-new-child-title');
		var $newMenuLabelInput = $('#jha-course-new-child-menu-label');
		var $newCreatePageInput = $('#jha-course-new-child-create-page');
		var $addButton = $('#jha-course-add-child-page');
		var $blockGapInput = $('#jha-course-block-gap');
		var $blockGapOutput = $blockGapInput.next('output');
		var $searchInput = $('#jha-course-lesson-search');
		var $searchStatus = $('#jha-course-lesson-search-status');
		var $courseSettingsBox = $('#jha-course-settings');
		var courseTemplateSlug = (window.jhaCourseAdmin && jhaCourseAdmin.courseTemplateSlug)
			? jhaCourseAdmin.courseTemplateSlug
			: 'page-templates/course-template.php';
		var isInCourseTemplateSystem = Boolean(
			window.jhaCourseAdmin && jhaCourseAdmin.isInCourseTemplateSystem
		);
		var currentTemplate = '';
		var renameObserver;
		var renameTimeout;
		var newPages = {};
		var newPageCounter = 0;
		var lessonManagerReloadPollTimer = null;
		var lessonSaveReloadArmed = false;
		var wasSavingPost = false;
		var isAddingLesson = false;
		var lessonOrderDirty = false;
		var lessonManagerSaveSyncArmed = false;
		var saveButtonSelector = [
			'.editor-post-publish-button',
			'.editor-post-publish-button__button',
			'.editor-post-save-draft',
			'.editor-post-save-draft__button',
			'button.editor-post-publish-button',
			'button.editor-post-save-draft',
			'.edit-post-header__settings-save',
			'#publish',
			'#save-post'
		].join(', ');

		function getEditorPostId() {
			if (window.jhaCourseAdmin && jhaCourseAdmin.postId) {
				return jhaCourseAdmin.postId;
			}

			if (window.wp && wp.data && wp.data.select) {
				var editor = wp.data.select('core/editor');

				if (editor && editor.getCurrentPostId) {
					return editor.getCurrentPostId() || 0;
				}
			}

			return 0;
		}

		function hasPendingLessonManagerChanges() {
			return Object.keys(newPages).length > 0 ||
				$list.find('.jha-course-child-order-item.is-new-lesson').length > 0;
		}

		function isAutosavingEditor() {
			if (!window.wp || !wp.data || !wp.data.select) {
				return false;
			}

			var editor = wp.data.select('core/editor');

			return Boolean(editor && editor.isAutosavingPost && editor.isAutosavingPost());
		}

		function isSavingEditor() {
			if (!window.wp || !wp.data || !wp.data.select) {
				return false;
			}

			var editor = wp.data.select('core/editor');

			return Boolean(editor && editor.isSavingPost && editor.isSavingPost());
		}

		function clearLessonManagerReloadPoll() {
			if (lessonManagerReloadPollTimer) {
				window.clearTimeout(lessonManagerReloadPollTimer);
				lessonManagerReloadPollTimer = null;
			}
		}

		function reloadLessonManagerEditor() {
			clearLessonManagerReloadPoll();
			window.location.reload();
		}

		function pollLessonManagerReloadFromServer(attempt) {
			var nextAttempt = attempt || 0;
			var postId = getEditorPostId();

			if (!postId || !window.jhaCourseAdmin) {
				clearLessonManagerReloadPoll();
				return;
			}

			if (isSavingEditor()) {
				lessonManagerReloadPollTimer = window.setTimeout(function () {
					pollLessonManagerReloadFromServer(nextAttempt);
				}, 300);
				return;
			}

			$.post(
				jhaCourseAdmin.ajaxUrl,
				{
					action: 'jha_course_lesson_manager_needs_reload',
					nonce: jhaCourseAdmin.nonce,
					postId: postId
				}
			)
				.done(function (response) {
					if (response && response.success && response.data && response.data.reload) {
						reloadLessonManagerEditor();
						return;
					}

					if (lessonSaveReloadArmed && nextAttempt < 40) {
						lessonManagerReloadPollTimer = window.setTimeout(function () {
							pollLessonManagerReloadFromServer(nextAttempt + 1);
						}, 400);
						return;
					}

					clearLessonManagerReloadPoll();
				})
				.fail(function () {
					if (lessonSaveReloadArmed && nextAttempt < 40) {
						lessonManagerReloadPollTimer = window.setTimeout(function () {
							pollLessonManagerReloadFromServer(nextAttempt + 1);
						}, 400);
						return;
					}

					clearLessonManagerReloadPoll();
				});
		}

		function startLessonManagerReloadPoll() {
			if (!lessonSaveReloadArmed && !hasPendingLessonManagerChanges()) {
				return;
			}

			clearLessonManagerReloadPoll();
			lessonManagerReloadPollTimer = window.setTimeout(function () {
				pollLessonManagerReloadFromServer(0);
			}, 500);
		}

		function armLessonManagerReloadAfterSave() {
			if (!hasPendingLessonManagerChanges()) {
				return;
			}

			lessonSaveReloadArmed = true;
			startLessonManagerReloadPoll();
		}

		if ($blockGapInput.length && $blockGapOutput.length) {
			$blockGapInput.on('input', function () {
				$blockGapOutput.text($blockGapInput.val() + 'rem');
			});
		}

		var $showProgressInput = $('#jha-course-show-progress');

		function syncCourseProgressMeta() {
			if (!$showProgressInput.length) {
				return;
			}

			if (!window.wp || !wp.data || !wp.data.dispatch) {
				return;
			}

			wp.data.dispatch('core/editor').editPost({
				meta: {
					_jha_course_show_progress: $showProgressInput.is(':checked') ? '1' : '0',
				},
			});
		}

		if ($showProgressInput.length) {
			$showProgressInput.on('change', syncCourseProgressMeta);
		}

		function getSelectedPageTemplate() {
			var $classicTemplateSelect = $('#page_template, select[name="page_template"]').first();
			var editor;
			var template;

			if ($classicTemplateSelect.length) {
				return String($classicTemplateSelect.val() || 'default');
			}

			if (window.wp && wp.data && wp.data.select) {
				editor = wp.data.select('core/editor');

				if (editor && editor.getEditedPostAttribute) {
					template = editor.getEditedPostAttribute('template');

					return String(template || 'default');
				}
			}

			return 'default';
		}

		function isCourseTemplateSelected() {
			return getSelectedPageTemplate() === courseTemplateSlug;
		}

		function shouldShowCourseSettings() {
			return isInCourseTemplateSystem || isCourseTemplateSelected();
		}

		function keepCourseSettingsOpen() {
			if (!$courseSettingsBox.length || !shouldShowCourseSettings()) {
				return;
			}

			$courseSettingsBox.removeClass('closed');
			$courseSettingsBox.children('.inside').show();
			$courseSettingsBox.find('.handlediv').attr('aria-expanded', 'true');
		}

		function updateCourseSettingsVisibility() {
			var shouldShow = shouldShowCourseSettings();

			if (!$courseSettingsBox.length) {
				return;
			}

			$courseSettingsBox.toggle(shouldShow);

			if (shouldShow) {
				keepCourseSettingsOpen();
			}
		}

		function renameCourseSettingsAccordion() {
			var walker;
			var node;

			if (!$courseSettingsBox.length) {
				return;
			}

			walker = document.createTreeWalker(document.body, window.NodeFilter.SHOW_TEXT);

			while ((node = walker.nextNode())) {
				if (/^\s*meta boxes\s*$/i.test(node.nodeValue)) {
					node.nodeValue = node.nodeValue.replace(/meta boxes/i, 'Course Settings');
				}
			}

			$('[aria-label], [title]').each(function () {
				var $element = $(this);
				var ariaLabel = $element.attr('aria-label');
				var title = $element.attr('title');

				if (ariaLabel && /meta boxes/i.test(ariaLabel)) {
					$element.attr('aria-label', ariaLabel.replace(/meta boxes/ig, 'Course Settings'));
				}

				if (title && /meta boxes/i.test(title)) {
					$element.attr('title', title.replace(/meta boxes/ig, 'Course Settings'));
				}
			});
		}

		function scheduleCourseSettingsAccordionRename() {
			window.clearTimeout(renameTimeout);
			renameTimeout = window.setTimeout(renameCourseSettingsAccordion, 50);
		}

		function promptForMenuLabel($button, onSaved) {
			var currentLabel = String($button.attr('data-current-label') || '');
			var defaultLabel = String($button.attr('data-default-label') || '');
			var promptText = (window.jhaCourseAdmin && jhaCourseAdmin.i18n && jhaCourseAdmin.i18n.menuLabelPrompt)
				? jhaCourseAdmin.i18n.menuLabelPrompt
				: 'Enter the course menu label override. Leave blank to use the page title.';
			var newLabel = window.prompt(promptText, currentLabel);

			if (newLabel === null || !window.jhaCourseAdmin) {
				return;
			}

			$button.prop('disabled', true);

			$.post(
				jhaCourseAdmin.ajaxUrl,
				{
					action: 'jha_update_course_menu_label',
					nonce: jhaCourseAdmin.nonce,
					postId: $button.attr('data-page-id'),
					label: newLabel
				}
			)
				.done(function (response) {
					if (!response || !response.success || !response.data) {
						window.alert(jhaCourseAdmin.i18n.saveFailed);
						return;
					}

					$button.attr({
						'data-current-label': response.data.rawLabel || '',
						'data-default-label': response.data.default || defaultLabel
					});

					if (typeof onSaved === 'function') {
						onSaved(response.data);
					}
				})
				.fail(function () {
					window.alert(jhaCourseAdmin.i18n.saveFailed);
				})
				.always(function () {
					$button.prop('disabled', false);
				});
		}

		updateCourseSettingsVisibility();
		renameCourseSettingsAccordion();
		window.setTimeout(renameCourseSettingsAccordion, 250);
		window.setTimeout(renameCourseSettingsAccordion, 1000);

		if (window.MutationObserver && $courseSettingsBox.length) {
			renameObserver = new window.MutationObserver(scheduleCourseSettingsAccordionRename);
			renameObserver.observe(document.body, {
				childList: true,
				subtree: true
			});
		}
		$(document).on('change', '#page_template, select[name="page_template"]', updateCourseSettingsVisibility);

		if (window.wp && wp.data && wp.data.subscribe) {
			currentTemplate = getSelectedPageTemplate();
			wp.data.subscribe(function () {
				var nextTemplate = getSelectedPageTemplate();

				if (nextTemplate === currentTemplate) {
					return;
				}

				currentTemplate = nextTemplate;
				updateCourseSettingsVisibility();
				renameCourseSettingsAccordion();
			});
		}

		$(document).on('click', '.jha-course-list-label-override', function () {
			promptForMenuLabel($(this));
		});

		if (!$list.length) {
			return;
		}

		if (window.jhaCourseLessonManagerInitialized) {
			return;
		}

		window.jhaCourseLessonManagerInitialized = true;

		function getItemToken($item) {
			return String($item.attr('data-page-token') || $item.attr('data-page-id') || '');
		}

		function serializeList($currentList) {
			return $currentList
				.children('.jha-course-child-order-item:not(.is-marked-for-trash)')
				.map(function () {
					var $item = $(this);
					var $children = $item.children('.jha-course-child-order-sublist').first();
					var node = {
						token: getItemToken($item),
						children: serializeList($children)
					};

					if ($item.attr('data-node-type') === 'menu') {
						node.type = 'menu';
						node.title = String($item.attr('data-menu-title') || '');
					}

					return node;
				})
				.get();
		}

		function updateOrderInput() {
			if (!$input.length) {
				return;
			}

			$input.val(JSON.stringify(serializeList($list)));
		}

		function updateTrashInput() {
			if (!$trashInput.length) {
				return;
			}

			var pageIds = $list
				.find('.jha-course-child-order-item.is-marked-for-trash')
				.map(function () {
					return String($(this).attr('data-page-id') || '');
				})
				.get()
				.filter(Boolean);

			$trashInput.val(pageIds.join(','));
		}

		function updateHiddenInput() {
			if (!$hiddenInput.length) {
				return;
			}

			var pageIds = $list
				.find('.jha-course-child-order-item.is-hidden-from-menu')
				.map(function () {
					return String($(this).attr('data-page-id') || '');
				})
				.get()
				.filter(Boolean);

			$hiddenInput.val(pageIds.join(','));
		}

		function updateNewPagesInput() {
			if (!$newPagesInput.length) {
				return;
			}

			$newPagesInput.val(JSON.stringify(newPages));
		}

		function normalizeSearchValue(value) {
			return $.trim(String(value || '')).toLowerCase();
		}

		function getLessonSearchText($item) {
			var $row = $item.children('.jha-course-child-order-row').first();

			return normalizeSearchValue(
				$row.children('.jha-course-child-order-title').text() + ' ' +
					$row.children('.jha-course-child-order-post-title').text()
			);
		}

		function filterLessonItems($items, query) {
			var visibleCount = 0;

			$items.each(function () {
				var $item = $(this);
				var childVisibleCount = filterLessonItems(
					$item.children('.jha-course-child-order-sublist').children('.jha-course-child-order-item'),
					query
				);
				var isMatch = !query || getLessonSearchText($item).indexOf(query) !== -1;
				var isVisible = isMatch || childVisibleCount > 0;

				$item.toggleClass('is-search-hidden', !isVisible);

				if (isVisible) {
					visibleCount += 1;
				}
			});

			return visibleCount;
		}

		function updateLessonSearch() {
			if (!$searchInput.length) {
				return;
			}

			var query = normalizeSearchValue($searchInput.val());
			var visibleCount = filterLessonItems($list.children('.jha-course-child-order-item'), query);

			if (!$searchStatus.length) {
				return;
			}

			if (!query) {
				$searchStatus.text('');
			} else if (visibleCount) {
				$searchStatus.text('Showing matching lessons. Clear search to return to the full sortable list.');
			} else {
				$searchStatus.text('No lessons match this search.');
			}
		}

		function getTrashFieldValue() {
			if ($trashInput.length) {
				return $trashInput.val() || '';
			}

			if (!$list.length) {
				return '';
			}

			return $list
				.find('.jha-course-child-order-item.is-marked-for-trash')
				.map(function () {
					return String($(this).attr('data-page-id') || '');
				})
				.get()
				.filter(Boolean)
				.join(',');
		}

		function getHiddenFieldValue() {
			if ($hiddenInput.length) {
				return $hiddenInput.val() || '';
			}

			if (!$list.length) {
				return '';
			}

			return $list
				.find('.jha-course-child-order-item.is-hidden-from-menu')
				.map(function () {
					return String($(this).attr('data-page-id') || '');
				})
				.get()
				.filter(Boolean)
				.join(',');
		}

		function shouldSyncLessonManagerToEditor() {
			return hasPendingLessonManagerChanges() || lessonOrderDirty;
		}

		function updateLessonManagerHiddenFields() {
			if ($list.length) {
				updateOrderInput();
				updateTrashInput();
				updateHiddenInput();
			}

			updateNewPagesInput();
		}

		function syncLessonManagerFields(forceSync) {
			updateLessonManagerHiddenFields();

			if (!shouldShowCourseSettings() || !$list.length) {
				return;
			}

			if (!forceSync && !shouldSyncLessonManagerToEditor()) {
				return;
			}

			if (!forceSync && isAutosavingEditor()) {
				return;
			}

			if (forceSync && lessonManagerSaveSyncArmed) {
				return;
			}

			if (!window.wp || !wp.data || !wp.data.dispatch) {
				return;
			}

			var editor = wp.data.select('core/editor');

			if (!editor || !editor.getCurrentPostId) {
				return;
			}

			if (forceSync) {
				lessonManagerSaveSyncArmed = true;
			}

			wp.data.dispatch('core/editor').editPost({
				meta: {
					_jha_course_lesson_tree: JSON.stringify(serializeList($list)),
					_jha_course_new_child_pages: JSON.stringify(newPages),
					_jha_course_child_trash: getTrashFieldValue(),
					_jha_course_hidden_lessons: getHiddenFieldValue()
				}
			});
		}

		function resetLessonManagerSaveSyncArm() {
			lessonManagerSaveSyncArmed = false;
		}

		function escapeHtml(value) {
			return $('<div>').text(value).html();
		}

		function setIconButton($button, iconClass, label) {
			$button
				.attr({
					'aria-label': label,
					title: label
				})
				.find('.dashicons')
				.attr('class', 'dashicons ' + iconClass);

			$button.find('.screen-reader-text').text(label);
		}

		function markLessonManagerDirty() {
			lessonOrderDirty = true;
		}

		function addPendingLesson(title, menuLabel, createPage) {
			if (isAddingLesson) {
				return;
			}

			isAddingLesson = true;

			var token = (createPage ? 'new-' : 'menu-') + Date.now() + '-' + newPageCounter;
			var displayLabel = menuLabel || title;
			var safeTitle = escapeHtml(title);
			var safeDisplayLabel = escapeHtml(displayLabel);
			var $item;

			newPageCounter += 1;

			if (createPage) {
				newPages[token] = {
					title: title,
					menuLabel: menuLabel || ''
				};
			}

			$item = $(
				'<li class="jha-course-child-order-item is-new-lesson' + (createPage ? '' : ' is-menu-only') + '" data-page-token="' + token + '"' + (createPage ? '' : ' data-node-type="menu" data-menu-title="' + safeDisplayLabel + '"') + '>' +
					'<div class="jha-course-child-order-row">' +
						'<span class="dashicons dashicons-menu" aria-hidden="true"></span>' +
						'<span class="jha-course-child-order-title">' + safeDisplayLabel + '</span>' +
						'<span class="jha-course-child-order-post-title">' + (createPage ? 'New page title: ' + safeTitle : 'Menu-only parent, no page created') + '</span>' +
						'<span class="jha-course-child-order-hidden-label">Hidden from menu</span>' +
						'<button type="button" class="button-link-delete jha-course-child-action jha-course-child-delete" aria-label="Remove new unsaved lesson" title="Remove new unsaved lesson">' +
							'<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
							'<span class="screen-reader-text">Remove new unsaved lesson</span>' +
						'</button>' +
					'</div>' +
					'<ul class="jha-course-child-order-list jha-course-child-order-sublist"></ul>' +
				'</li>'
			);

			$list.append($item);
			initSortable($item);
			syncLessonManagerFields(true);
			updateLessonSearch();

			window.setTimeout(function () {
				isAddingLesson = false;
			}, 0);
		}

		function initSortable($scope) {
			if (typeof $.fn.sortable !== 'function') {
				return;
			}

			$scope
				.find('.jha-course-child-order-list')
				.addBack('.jha-course-child-order-list')
				.each(function () {
					var $sortableList = $(this);

					if ($sortableList.data('jha-sortable-ready')) {
						return;
					}

					$sortableList
						.data('jha-sortable-ready', true)
						.sortable({
							connectWith: '.jha-course-child-order-list',
							cursor: 'move',
							handle: '.dashicons-menu',
							items: '> .jha-course-child-order-item:not(.is-marked-for-trash)',
							placeholder: 'jha-course-child-order-placeholder',
							tolerance: 'pointer',
							update: function () {
								markLessonManagerDirty();
								syncLessonManagerFields(true);
							},
							receive: function () {
								markLessonManagerDirty();
								syncLessonManagerFields(true);
							},
							stop: function () {
								markLessonManagerDirty();
								syncLessonManagerFields(true);
							}
						});
				});
		}

		$list.on('click', '.jha-course-child-delete', function () {
			var $button = $(this);
			var $item = $button.closest('.jha-course-child-order-item');
			var token = getItemToken($item);
			var isMarked = $item.hasClass('is-marked-for-trash');

			if ($item.hasClass('is-new-lesson')) {
				delete newPages[token];
				$item.remove();
				syncLessonManagerFields(true);
				updateLessonSearch();
				return;
			}

			if ($item.hasClass('is-menu-only')) {
				$item.remove();
				markLessonManagerDirty();
				syncLessonManagerFields(true);
				updateLessonSearch();
				return;
			}

			if (!isMarked && !window.confirm('Are you sure you want to move this page to the WordPress Trash? This removes the page from the site. Use the eye icon instead if you only want to hide it from the course menu.')) {
				return;
			}

			isMarked = $item.toggleClass('is-marked-for-trash').hasClass('is-marked-for-trash');
			setIconButton(
				$button,
				isMarked ? 'dashicons-undo' : 'dashicons-trash',
				isMarked ? 'Undo move to Trash' : 'Move page to Trash'
			);
			syncLessonManagerFields(true);
			updateLessonSearch();
		});

		$list.on('click', '.jha-course-child-hide', function () {
			var $button = $(this);
			var $item = $button.closest('.jha-course-child-order-item');
			var isHidden = $item.toggleClass('is-hidden-from-menu').hasClass('is-hidden-from-menu');

			setIconButton(
				$button,
				isHidden ? 'dashicons-visibility' : 'dashicons-hidden',
				isHidden ? 'Show in course menu' : 'Hide from course menu'
			);
			syncLessonManagerFields(true);
			updateLessonSearch();
		});

		$list.on('click', '.jha-course-child-label-override', function () {
			var $button = $(this);
			var $item = $button.closest('.jha-course-child-order-item');

			promptForMenuLabel($button, function (data) {
				$item.children('.jha-course-child-order-row').first().children('.jha-course-child-order-title').text(data.label);
				updateLessonSearch();
			});
		});

		$addButton.on('click', function () {
			var title = $.trim($newTitleInput.val());
			var menuLabel = $.trim($newMenuLabelInput.val());
			var createPage = !$newCreatePageInput.length || $newCreatePageInput.is(':checked');

			if (!title || isAddingLesson) {
				return;
			}

			addPendingLesson(title, menuLabel, createPage);
			$newTitleInput.val('').trigger('focus');
			$newMenuLabelInput.val('');
		});

		$newTitleInput.add($newMenuLabelInput).on('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				$addButton.trigger('click');
			}
		});

		$searchInput.on('input', updateLessonSearch);

		initSortable($(document));
		keepCourseSettingsOpen();
		updateLessonManagerHiddenFields();
		updateLessonSearch();

		$(document).on('click', '#jha-course-settings .postbox-header, #jha-course-settings .hndle', function () {
			window.setTimeout(keepCourseSettingsOpen, 0);
		});

		$('form#post').on('submit', function () {
			if (shouldSyncLessonManagerToEditor()) {
				syncLessonManagerFields(true);
				armLessonManagerReloadAfterSave();
			}
		});

		$(document).on('click', saveButtonSelector, function () {
			if (shouldSyncLessonManagerToEditor()) {
				syncLessonManagerFields(true);
				armLessonManagerReloadAfterSave();
			}
		});

		if (window.wp && wp.data && wp.data.subscribe) {
			wp.data.subscribe(function () {
				var saving = isSavingEditor();
				var autosaving = isAutosavingEditor();

				if (!wasSavingPost && saving && !autosaving && hasPendingLessonManagerChanges()) {
					lessonSaveReloadArmed = true;
				}

				if (wasSavingPost && !saving && !autosaving && lessonSaveReloadArmed) {
					lessonSaveReloadArmed = false;
					resetLessonManagerSaveSyncArm();
					reloadLessonManagerEditor();
					return;
				}

				if (wasSavingPost && !saving && !autosaving) {
					resetLessonManagerSaveSyncArm();
				}

				wasSavingPost = saving;
			});
		}

		function registerCourseSaveHooks(hookName) {
			if (!window.wp || !wp.hooks || !wp.hooks.addAction) {
				return;
			}

			wp.hooks.addAction(
				hookName,
				'jha/course-lesson-sync',
				function () {
					if (isAutosavingEditor() || !shouldSyncLessonManagerToEditor()) {
						return;
					}

					syncLessonManagerFields(true);

					if (hasPendingLessonManagerChanges()) {
						armLessonManagerReloadAfterSave();
					}
				},
				99
			);
		}

		registerCourseSaveHooks('editor.savePost');
		registerCourseSaveHooks('core/editor/savePost');
	});
})(jQuery);
