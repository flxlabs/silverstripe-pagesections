(function($) {
	function TreeViewContextMenu() {
		this.createDom = function(id, name) {
			this.$menu = $(
				"<ul id='treeview-menu-" +
					name +
					"' class='treeview-menu' data-id='" +
					id +
					"'></ul>"
			);
		};
		this.addLabel = function(label) {
			this.$menu.append("<li class='header'>" + label + '</li>');
		};
		this.addItem = function(type, label, onClick = function() {}) {
			var $li = $("<li data-type='" + type + "'>" + label + '</li>');
			$li.click(onClick);
			this.$menu.append($li);
		};
		this.show = function(x, y) {
			var pos = {
				top: y,
				left: x
			};

			$(document.body).append(this.$menu);
			this.$menu.css(pos);
			this.$menu.show();
			var that = this;
			window.requestAnimationFrame(function() {
				var wW = $(window).width();
				var wH = $(window).height();
				var eW = that.$menu.outerWidth(true);
				var eH = that.$menu.outerHeight(true);
				pos.left = Math.max(2, pos.left - eW * 0.5);
				pos.top = Math.max(2, pos.top - eH * 0.5);
				if (pos.left + eW > wW) {
					pos.left = wW - eW - 2;
				}
				if (pos.top + eH > wH) {
					pos.top = wH - eH - 2;
				}
				that.$menu.css(pos);
			});
		};
		this.remove = function() {
			this.$menu.remove();
		};
	}

	$.entwine('ss', function($) {
		// Hide our custom context menu when not needed
		$(document).on('mousedown', function(event) {
			$parents = $(event.target).parents('.treeview-menu');
			if ($parents.length == 0) {
				$('.treeview-menu').remove();
			}
		});

		$('.view-detail-dialog .gridfield-better-buttons-prevnext').entwine({
			onclick: function() {
				this.closest('.view-detail-dialog').loadDialog(
					$.get(this.prop('href'))
				);
				return false;
			}
		});

		$('.view-detail-dialog .action-detail').entwine({
			onclick: function() {
				const dialog = this.closest('.view-detail-dialog').loadDialog(
					$.get(this.prop('href'))
				);
				dialog.data('href', this.prop('href'));
				return false;
			}
		});

		$('.view-detail-dialog form').entwine({
			onsubmit: function() {
				var dialog = this.closest('.view-detail-dialog');

				const self = this;

				$.ajax(
					$.extend(
						{},
						{
							headers: {
								'X-Pjax': 'CurrentField'
							},
							type: 'POST',
							url: this.prop('action'),
							dataType: 'html',
							data: this.serialize(),
							success: function() {
								if (self.hasClass('view-detail-form')) {
									dialog.data('treeview').reload();
									dialog.dialog('close');
								} else {
									if (dialog.data('clickedButton') === 'action_doSaveAndQuit') {
										dialog.loadDialog($.get(dialog.data('origUrl')));
									} else {
										const url = self.prop('href')
											? self.prop('href')
											: dialog.data('href');
										dialog.loadDialog($.get(url));
									}
								}
							}
						}
					)
				);

				return false;
			}
		});

		// Show our search form after opening the search dialog
		// Show our detail form after opening the detail dialog
		$('.add-existing-search-dialog-treeview, .view-detail-dialog').entwine({
			loadDialog: function(deferred) {
				var dialog = this.addClass('loading').empty();

				deferred.done(function(data) {
					dialog.html(data).removeClass('loading');
					dialog.find('button[type=submit]').click(function() {
						dialog.data('clickedButton', this.name);
					});
				});
				return this;
			}
		});

		// Submit our search form to our own endpoint and show the results
		$('.add-existing-search-dialog-treeview .add-existing-search-form').entwine(
			{
				onsubmit: function() {
					this.closest('.add-existing-search-dialog-treeview').loadDialog(
						$.get(this.prop('action'), this.serialize())
					);
					return false;
				}
			}
		);

		// Allow clicking the elements in the search form
		$(
			'.add-existing-search-dialog-treeview .add-existing-search-items .list-group-item-action'
		).entwine({
			onclick: function() {
				if (this.children('a').length > 0) {
					this.children('a')
						.first()
						.trigger('click');
				}
			}
		});

		// Trigger the "add existing" action on the selected element
		$(
			'.add-existing-search-dialog-treeview .add-existing-search-items a'
		).entwine({
			onclick: function() {
				var link = this.closest('.add-existing-search-items').data('add-link');
				var id = this.data('id');

				var dialog = this.closest('.add-existing-search-dialog-treeview')
					.addClass('loading')
					.empty();

				dialog.data('treeview').reload(
					{
						url: link,
						data: [
							{
								name: 'id',
								value: id
							}
						]
					},
					function() {
						dialog.dialog('close');
					}
				);

				return false;
			}
		});

		// Browse search result pages
		$(
			'.add-existing-search-dialog-treeview .add-existing-search-pagination a'
		).entwine({
			onclick: function() {
				this.closest('.add-existing-search-dialog-treeview').loadDialog(
					$.get(this.prop('href'))
				);
				return false;
			}
		});

		// Save the item in the detail view
		/*$(".view-detail-dialog .view-detail-form").entwine({
			onsubmit: function () {
				var dialog = this.closest(".view-detail-dialog").children(
					".ui-dialog-content"
				);

				$.post(this.prop("action"), this.serialize(), function () {
					dialog.data("treeview").reload();
					dialog.dialog("close");
				});

				return false;
			}
		});*/

		// Attach data to our tree view
		$('.treeview-pagesections').entwine({
			addItem: function(parents, itemId, elemType, sort = 99999) {
				var $treeView = $(this);

				$treeView.reload({
					url: $treeView.data('url') + '/add',
					data: [
						{
							name: 'parents',
							value: parents
						},
						{
							name: 'itemId',
							value: itemId
						},
						{
							name: 'type',
							value: elemType
						},
						{
							name: 'sort',
							value: sort
						}
					]
				});
			},
			removeItem: function(parents, itemId) {
				var $treeView = $(this);

				$treeView.reload({
					url: $treeView.data('url') + '/remove',
					data: [
						{
							name: 'parents',
							value: parents
						},
						{
							name: 'itemId',
							value: itemId
						}
					]
				});
			},
			deleteItem: function(parents, itemId) {
				var $treeView = $(this);

				$treeView.reload({
					url: $treeView.data('url') + '/delete',
					data: [
						{
							name: 'parents',
							value: parents
						},
						{
							name: 'itemId',
							value: itemId
						}
					]
				});
			},
			onadd: function() {
				var $treeView = $(this);
				var name = $treeView.data('name');
				var url = $treeView.data('url');

				// Setup find existing button
				$treeView.find('button[name=action_FindExisting]').click(function() {
					var dialog = $('<div></div>')
						.appendTo('body')
						.dialog({
							modal: true,
							resizable: false,
							width: 500,
							height: 600,
							close: function() {
								$(this)
									.dialog('destroy')
									.remove();
							}
						});

					var url = $.get($treeView.data('url') + '/search');
					dialog
						.addClass('add-existing-search-dialog-treeview')
						.data('treeview', $treeView)
						.data('origUrl', url)
						.loadDialog(url);
				});

				// Add new button at the very top
				$treeView
					.find(
						'> .treeview-pagesections__header > .treeview-item-actions .add-button'
					)
					.click(function(event) {
						event.preventDefault();
						event.stopImmediatePropagation();

						$target = $(event.target);
						var elems = $target.data('allowed-elements');

						var menu = new TreeViewContextMenu();
						menu.createDom(null, name);
						menu.addLabel(
							ss.i18n._t('PageSections.TreeView.AddAChild', 'Add a child')
						);
						$.each(elems, function(key, value) {
							menu.addItem(key, value, function() {
								$treeView.addItem([], null, key, 1);
								menu.remove();
							});
						});
						menu.show(event.pageX, event.pageY);
					});

				// Process items
				$treeView.find('.treeview-item').each(function() {
					var $item = $(this);
					var itemId = $item.data('id');
					var parents = $item.data('tree');

					// Open an item button
					$item
						.find('> .treeview-item__panel > .treeview-item-flow .tree-button')
						.click(function(event) {
							event.preventDefault();
							event.stopImmediatePropagation();

							$treeView.reload({
								url: url + '/tree',
								data: [
									{
										name: 'parents',
										value: parents
									},
									{
										name: 'itemId',
										value: itemId
									}
								]
							});
						});

					// Edit button
					$item
						.find('> .treeview-item__panel > .treeview-item-flow .edit-button')
						.click(function() {
							var dialog = $('<div></div>')
								.appendTo('body')
								.dialog({
									modal: false,
									resizable: false,
									width: $(window).width() * 0.9,
									height: $(window).height() * 0.9,
									close: function() {
										$(this)
											.dialog('destroy')
											.remove();
									}
								});

							var url = $treeView.data('url') + '/detail?ID=' + itemId;
							dialog
								.addClass('view-detail-dialog')
								.data('treeview', $treeView)
								.data('origUrl', url)
								.loadDialog($.get(url));
						});

					// Add new item button
					$item
						.find(
							'> .treeview-item__panel > .treeview-item-actions .add-button'
						)
						.click(function(event) {
							event.preventDefault();
							event.stopImmediatePropagation();

							$target = $(event.target);
							var elems = $target.data('allowed-elements');

							var menu = new TreeViewContextMenu();
							menu.createDom(itemId, name);
							menu.addLabel(
								ss.i18n._t('PageSections.TreeView.AddAChild', 'Add a child')
							);
							$.each(elems, function(key, value) {
								menu.addItem(key, value, function() {
									$treeView.addItem(parents, itemId, key, 1);
									menu.remove();
								});
							});
							menu.show(event.pageX, event.pageY);
						});

					// Add new after item button
					$item
						.find('> .treeview-item__post-actions .add-after-button')
						.click(function(event) {
							event.preventDefault();
							event.stopImmediatePropagation();

							$target = $(event.target);
							var elems = $target.data('allowed-elements');

							var menu = new TreeViewContextMenu();
							menu.createDom(itemId, name);
							menu.addLabel(
								ss.i18n._t(
									'PageSections.TreeView.AddAfterThis',
									'Add new element'
								)
							);

							$.each(elems, function(key, value) {
								menu.addItem(key, value, function() {
									$treeView.addItem(
										parents.slice(0, parents.length - 1),
										parents[parents.length - 1],
										key,
										$item.data('sort') + 1
									);
									menu.remove();
								});
							});
							menu.show(event.pageX, event.pageY);
						});

					// Delete button action
					$item
						.find(
							'> .treeview-item__panel > .treeview-item-flow .delete-button'
						)
						.click(function(event) {
							event.preventDefault();
							event.stopImmediatePropagation();

							$target = $(event.target);
							// If we clicked the span get the parent button
							if ($target.prop('tagName') === 'SPAN') {
								$target = $target.parent();
							}

							var menu = new TreeViewContextMenu();
							menu.createDom(itemId, name);
							menu.addLabel(
								ss.i18n._t('PageSections.TreeView.Delete', 'Delete')
							);

							menu.addItem(
								'__REMOVE__',
								ss.i18n._t('PageSections.TreeView.RemoveAChild', 'Remove'),
								function() {
									$treeView.removeItem(parents, itemId);
									menu.remove();
								}
							);

							if (Number($target.data('used-count')) <= 1) {
								menu.addItem(
									'',
									ss.i18n._t(
										'PageSections.TreeView.DeleteAChild',
										'Finally delete'
									),
									function() {
										$treeView.deleteItem(parents, itemId);
										menu.remove();
									}
								);

								var $li = $(
									'<li>' +
										ss.i18n._t(
											'PageSections.TreeView.DeleteAChild',
											'Finally delete'
										) +
										'</li>',
									function() {
										$treeView.deleteItem(parents, itemId);
										menu.remove();
									}
								);
							}

							menu.show(event.pageX, event.pageY);
						});

					// Attach draggable events & info
					$item.find('.treeview-item-reorder__handle').draggable({
						//cancel: ".treeview-item",
						appendTo: 'body',
						revert: 'invalid',
						cursor: 'crosshair',
						cursorAt: {
							top: 30,
							left: 15
						},
						activeClass: 'state-active',
						hoverClass: 'state-active',
						tolerance: 'pointer',
						greedy: true,

						helper: function(event) {
							var $panel = $item.find('> .treeview-item__panel');
							var $helper = $("<div class='treeview-item__dragger'/>");
							$helper
								.append($item.clone(false))
								.find('.treeview-item__children')
								.remove();
							$helper.css({
								width: $panel.outerWidth(true),
								marginTop: -40,
								marginLeft: -4
							});
							//$panel.css("opacity", 0.6);
							//$panel.hide();
							$panel.css({
								overflow: 'hidden',
								height: 0,
								minHeight: 0,
								border: 'none'
							});
							return $helper;
						},

						start: function() {
							$('.ui-droppable').each(function() {
								var $drop = $(this);
								var $dropItem = $drop.closest('.treeview-item');
								var $parentDropItem = $dropItem
									.parent()
									.closest('.treeview-item');
								var clazz = $item.data('class');

								var isOpen = $dropItem.data('is-open');

								// Dont enable dropping on itself
								// or a same id
								if ($dropItem.data('id') == itemId) {
									return;
								}

								// Dont enable dropping on a child of itself
								// or a same id
								if (
									$dropItem.parents(".treeview-item[data-id='" + itemId + "']")
										.length
								) {
									return;
								}

								// Dont enable dropping on .before/.after
								// from neighbours with same id
								// (no same ids on same branch)
								if (
									($drop.hasClass('after') || $drop.hasClass('before')) &&
									$dropItem
										.siblings(".treeview-item[data-id='" + itemId + "']")
										.not($item).length
								) {
									return;
								}

								// Dont enable dropping on next bottom
								// of the same id
								if (
									$drop.hasClass('after') &&
									$dropItem.next().data('id') == itemId
								) {
									return;
								}

								// Don't enable dropping on the middle arrow for open items
								// (they will have child elements where we can drop before or after)
								if ($drop.hasClass('middle') && isOpen) {
									return;
								}

								// avoid adding unallowed elements on root section
								if (!$parentDropItem.length) {
									var allowed = $drop
										.closest('.treeview-pagesections')
										.data('allowed-elements');
									if (allowed && !allowed[clazz]) {
										return;
									}
								}

								// Don't allow dropping elements on this level if they're not an allowed child
								// Depending on the arrow we either have to check this element or the parent
								// of this element to see which children are allowed
								if ($drop.hasClass('before') || $drop.hasClass('after')) {
									var allowed = $parentDropItem.data('allowed-elements');
									if (allowed && !allowed[clazz]) {
										return;
									}
								} else {
									// is middle
									var allowed = $dropItem.data('allowed-elements');
									if (allowed && !allowed[clazz]) {
										return;
									}
								}

								$drop.show();
							});
						},
						stop: function(event, ui) {
							$('.ui-droppable').css('display', '');
							// Show the previous elements. If the user made an invalid movement then
							// we want this to show anyways. If he did something valid the treeview will
							// refresh so we don't care if it's visible behind the loading icon.
							//$(".treeview-item__panel").show();
							$('.treeview-item__panel').css({
								overflow: '',
								height: '',
								minHeight: '',
								border: ''
							});
						}
					});

					// Dropping targets
					$item.find('.treeview-item-reorder .droppable').each(function() {
						$(this).droppable({
							hoverClass: 'state-active',
							tolerance: 'pointer',
							drop: function(event, ui) {
								$drop = $(this);
								$dropItem = $drop.closest('.treeview-item');

								$oldItem = ui.draggable.closest('.treeview-item');
								var oldId = $oldItem.data('id');
								var oldParents = $oldItem.data('tree');

								var type = 'child';
								var sort = 100000;

								if ($drop.hasClass('before')) {
									type = 'before';
									sort = $dropItem.data('sort') - 1;
								} else if ($drop.hasClass('after')) {
									type = 'after';
									sort = $dropItem.data('sort') + 1;
								}

								var newParent =
									type === 'child'
										? itemId
										: parents.length > 0
										? parents[parents.length - 1]
										: '';

								$treeView.reload({
									url: url + '/move',
									data: [
										{
											name: 'parents',
											value: oldParents
										},
										{
											name: 'itemId',
											value: oldId
										},
										{
											name: 'newParent',
											value: newParent
										},
										{
											name: 'sort',
											value: sort
										}
									]
								});
							}
						});
					});
				});
			},
			// This is copy paste from SilverStripe GridField.js, modified to work for the TreeView
			// It updates the gridfield by sending the specified request
			// and using the response as the new content for the gridfield
			reload: function(ajaxOpts, successCallback) {
				var self = this,
					form = this.closest('form'),
					focusedElName = this.find(':input:focus').attr('name'), // Save focused element for restoring after refresh
					data = form.find(':input').serializeArray();

				if (!ajaxOpts) ajaxOpts = {};
				if (!ajaxOpts.data) ajaxOpts.data = [];
				ajaxOpts.data = ajaxOpts.data.concat(data).concat([
					{
						name: 'state',
						value: self.data('state-id')
					}
				]);

				// Include any GET parameters from the current URL, as the view state might depend on it.
				// For example, a list prefiltered through external search criteria might be passed to GridField.
				if (window.location.search) {
					ajaxOpts.data =
						window.location.search.replace(/^\?/, '') +
						'&' +
						$.param(ajaxOpts.data);
				}

				form.addClass('loading');

				$.ajax(
					$.extend(
						{},
						{
							headers: {
								'X-Pjax': 'CurrentField'
							},
							type: 'POST',
							url: this.data('url'),
							dataType: 'html',
							success: function(data) {
								// Replace the grid field with response, not the form.
								// TODO Only replaces all its children, to avoid replacing the current scope
								// of the executing method. Means that it doesn't retrigger the onmatch() on the main container.
								self.empty().append($(data).children());

								// Refocus previously focused element. Useful e.g. for finding+adding
								// multiple relationships via keyboard.
								if (focusedElName)
									self.find(':input[name="' + focusedElName + '"]').focus();

								form.removeClass('loading');
								if (successCallback) successCallback.apply(this, arguments);
								// TODO: Don't know how original SilverStripe GridField magically calls
								self.onadd();
							},
							error: function(e) {
								alert(i18n._t('Admin.ERRORINTRANSACTION'));
								form.removeClass('loading');
							}
						},
						ajaxOpts
					)
				);
			}
		});
	});
})(jQuery);
