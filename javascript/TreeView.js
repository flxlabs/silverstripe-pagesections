(function ($) {
	$.entwine("ss", function ($) {
		// Hide our custom context menu when not needed
		$(document).on("mousedown", function (event) {
			$parents = $(event.target).parents(".treeview-menu");
			if ($parents.length == 0) {
				$(".treeview-menu").remove();
			}
		});

		// Load our search form when opening the search dialog
		$(".add-existing-search-dialog").entwine({
			loadDialog: function (deferred) {
				var dialog = this.addClass("loading").children(".ui-dialog-content").empty();

				deferred.done(function (data) {
					dialog.html(data).parent().removeClass("loading");
				});
			}
		});

		// Submit our search form to our own endpoint and show the results
		$(".add-existing-search-dialog .add-existing-search-form").entwine({
			onsubmit: function () {
				this.closest(".add-existing-search-dialog").loadDialog($.get(
					this.prop("action"), this.serialize()
				));
				return false;
			}
		});

		// Allow clicking the elements in the search form
		$('.add-existing-search-dialog .add-existing-search-items .list-group-item-action').entwine({
			onclick: function () {
				if (this.children('a').length > 0) {
					this.children('a').first().trigger('click');
				}
			}
		});

		// Trigger the "add existing" action on the selected element
		$(".add-existing-search-dialog .add-existing-search-items a").entwine({
			onclick: function () {
				var link = this.closest(".add-existing-search-items").data("add-link");
				var id = this.data("id");

				var dialog = this.closest(".add-existing-search-dialog")
					.addClass("loading")
					.children(".ui-dialog-content")
					.empty();

				dialog.data("treeview").reload({
					url: link,
					data: [{
						name: "id",
						value: id
					}]
				}, function () {
					dialog.dialog("close");
				});

				return false;
			}
		});

		// Browse search result pages
		$(".add-existing-search-dialog .add-existing-search-pagination a").entwine({
			onclick: function () {
				this.closest(".add-existing-search-dialog").loadDialog($.get(
					this.prop("href")
				));
				return false;
			}
		});

		// Attach data to our tree view
		$(".treeview-pagesections").entwine({
			addItem: function (parents, itemId, elemType) {
				var $treeView = $(this);

				$treeView.reload({
					url: $treeView.data("url") + "/add",
					data: [{
						name: "parents",
						value: parents
					}, {
						name: "itemId",
						value: itemId
					}, {
						name: "type",
						value: elemType
					}]
				});
			},
			removeItem: function (parents, itemId) {
				var $treeView = $(this);

				$treeView.reload({
					url: $treeView.data("url") + "/remove",
					data: [{
						name: "parents",
						value: parents
					}, {
						name: "itemId",
						value: itemId
					}]
				});
			},
			deleteItem: function (parents, itemId) {
				var $treeView = $(this);

				$treeView.reload({
					url: $treeView.data("url") + "/delete",
					data: [{
						name: "parents",
						value: parents
					}, {
						name: "itemId",
						value: itemId
					}]
				});
			},
			onadd: function () {
				var $treeView = $(this);
				var name = $treeView.data("name");
				var url = $treeView.data('url');

				// Setup add new button
				$treeView.find(".treeview-actions-addnew .tree-button").click(function () {
					$treeView.reload({
						url: url + "/add",
						data: [{
							name: "type",
							value: $("#AddNewClass").val()
						}]
					});
				});

				// Setup find existing button
				$treeView.find("button[name=action_FindExisting]").click(function () {
					var dialog = $("<div></div>").appendTo("body").dialog({
						modal: true,
						resizable: false,
						width: 500,
						height: 600,
						close: function () {
							$(this).dialog("destroy").remove();
						}
					});

					dialog.parent().addClass("add-existing-search-dialog").loadDialog(
						$.get($treeView.data("url") + "/find")
					);
					dialog.data("treeview", $treeView);
				});

				// Process items
				$treeView.find(".treeview-item").each(function () {
					var $item = $(this);
					var $onlyThis = $item.find("> .treeview-item-flow");
					var itemId = $item.data("id");
					var parents = $item.data("tree");

					// Open an item button
					$onlyThis.find(".treeview-item-actions .tree-button").click(function (event) {
						event.preventDefault();
						event.stopImmediatePropagation();

						$treeView.reload({
							url: url + "/tree",
							data: [{
								name: "parents",
								value: parents
							}, {
								name: "itemId",
								value: itemId
							}]
						});
					});

					// Add new item button
					$onlyThis.find(".treeview-item-actions .add-button").click(function (event) {
						event.preventDefault();
						event.stopImmediatePropagation();

						$target = $(event.target);
						var elems = $target.data("allowed-elements");

						var $menu = $(
							"<ul id='treeview-menu-" +
							name +
							"' class='treeview-menu' data-id='" +
							itemId +
							"'></ul>"
						);
						$menu.css({
							top: event.pageY + "px",
							left: event.pageX + "px"
						});
						$(document.body).append($menu);

						$menu.append(
							"<li class='header'>" +
							ss.i18n._t("PageSections.TreeView.AddAChild", "Add a child") +
							"</li>"
						);
						$.each(elems, function (key, value) {
							var $li = $("<li data-type='" + key + "'>" + value + "</li>");
							$li.click(function () {
								$treeView.addItem(parents, itemId, key);
								$menu.remove();
							});
							$menu.append($li);
						});
						$menu.show();
					});

					// Delete button action
					$onlyThis.find(".treeview-item-actions .delete-button").click(function (event) {
						event.preventDefault();
						event.stopImmediatePropagation();

						$target = $(event.target);

						var $menu = $(
							"<ul id='treeview-menu-" +
							name +
							"' class='treeview-menu' data-id='" +
							itemId +
							"'></ul>"
						);
						$menu.css({
							top: event.pageY + "px",
							left: event.pageX + "px"
						});
						$(document.body).append($menu);

						$menu.append(
							"<li class='header'>" +
							ss.i18n._t("PageSections.TreeView.Delete", "Delete") +
							"</li>"
						);

						var $li = $(
							"<li data-type='__REMOVE__'>" +
							ss.i18n._t("PageSections.TreeView.RemoveAChild", "Remove") +
							"</li>"
						);
						$li.click(function () {
							$treeView.removeItem(parents, itemId);
							$menu.remove();
						});
						$menu.append($li);
						if ($target.data("used-count") < 2) {
							var $li = $(
								"<li>" +
								ss.i18n._t(
									"PageSections.TreeView.DeleteAChild",
									"Finally delete"
								) +
								"</li>"
							);
							$li.click(function () {
								$treeView.deleteItem(parents, itemId);
								$menu.remove();
							});
							$menu.append($li);
						}

						$menu.show();
					});

					// Attach draggable events & info
					$onlyThis.draggable({
						revert: "invalid",
						cursor: "crosshair",
						cursorAt: {
							top: -15,
							left: -15
						},
						activeClass: "state-active",
						hoverClass: "state-active",
						tolerance: "pointer",
						greedy: true,
						helper: function () {
							var $helper = $(
								"<div class='treeview-item__draggable'>" +
								$item.find(".treeview-item-content__title").text() +
								"</div>"
							);
							$item.css("opacity", 0.6);

							return $helper;
						},
						start: function () {
							$(".ui-droppable").each(function () {
								var $drop = $(this);
								var $dropItem = $drop.closest(".treeview-item");
								var isOpen = $dropItem.data("is-open");

								// Dont enable dropping on itself
								if ($dropItem.data("id") == itemId &&
									$dropItem.data("tree") == parents) {
									return;
								}

								// Don't enable dropping on the middle arrow for open items
								// (they will have child elements where we can drop before or after)
								if ($drop.hasClass("middle") && isOpen) {
									return;
								}

								// Dont enable dropping on .after of itself
								if (
									$drop.hasClass("after") &&
									$dropItem.next().data("id") == itemId
								) {
									return;
								}

								// Dont enable dropping on .middle of other same id elements
								// (no recursive structures)
								if (
									$drop.hasClass("middle") &&
									$dropItem.data("id") == itemId
								) {
									return;
								}

								// Don't allow dropping elements on the root level that aren't allowed there
								if (
									$dropItem.data("tree").length == 0 &&
									($drop.hasClass("before") || $drop.hasClass("after"))
								) {
									if (!$item.data("allowed-root")) {
										return;
									}
								}

								// Don't allow dropping elements on this level if they're not an allowed child
								// Depending on the arrow we either have to check this element or the parent
								// of this element to see which children are allowed
								var clazz = $item.data("class");
								if ($drop.hasClass("before") || $drop.hasClass("after")) {
									var $parent = $dropItem.parent().closest(".treeview-item");
									var allowed = $parent.data("allowed-elements");
									if (allowed && !allowed[clazz]) {
										return;
									}
								} else {
									var allowed = $dropItem.data("allowed-elements");
									if (allowed && !allowed[clazz]) {
										return;
									}
								}

								$drop.show();
							});
						},
						stop: function (event, ui) {
							$(".ui-droppable").hide();
							// Show the previous elements. If the user made an invalid movement then
							// we want this to show anyways. If he did something valid the treeview will
							// refresh so we don't care if it's visible behind the loading icon.
							$(".treeview-item").css("opacity", "");
						}
					});

					// Dropping targets
					$onlyThis.find(".treeview-item-reorder div").each(function () {
						$(this).droppable({
							hoverClass: "state-active",
							tolerance: "pointer",
							drop: function (event, ui) {
								$drop = $(this);
								$dropItem = $drop.closest(".treeview-item");

								$oldItem = ui.draggable.closest(".treeview-item");
								var oldId = $oldItem.data("id");
								var oldParents = $oldItem.data("tree");

								var type = "child";
								var sort = 100000;

								if ($drop.hasClass("before")) {
									type = "before";
									sort = $dropItem.data("sort") - 1;
								} else if ($drop.hasClass("after")) {
									type = "after";
									sort = $dropItem.data("sort") + 1;
								}

								var newParent = type === "child" ? itemId : parents[parents.length - 1];

								$treeView.reload({
									url: url + "/move",
									data: [{
										name: "parents",
										value: oldParents
									}, {
										name: "itemId",
										value: oldId
									}, {
										name: "newParent",
										value: newParent
									}, {
										name: "sort",
										value: sort
									}]
								});
							}
						});
					});
				});
			},
			// This is copy paste from SilverStripe GridField.js, modified to work for the TreeView
			// It updates the gridfield by sending the specified request
			// and using the response as the new content for the gridfield
			reload: function (ajaxOpts, successCallback) {
				var self = this,
					form = this.closest('form'),
					focusedElName = this.find(':input:focus').attr('name'), // Save focused element for restoring after refresh
					data = form.find(':input').serializeArray();

				if (!ajaxOpts) ajaxOpts = {};
				if (!ajaxOpts.data) ajaxOpts.data = [];
				ajaxOpts.data = ajaxOpts.data.concat(data).concat([{
					name: "state",
					value: self.data("state-id"),
				}]);

				// Include any GET parameters from the current URL, as the view state might depend on it.
				// For example, a list prefiltered through external search criteria might be passed to GridField.
				if (window.location.search) {
					ajaxOpts.data = window.location.search.replace(/^\?/, '') + '&' + $.param(ajaxOpts.data);
				}

				form.addClass('loading');

				$.ajax($.extend({}, {
					headers: {
						"X-Pjax": 'CurrentField'
					},
					type: "POST",
					url: this.data('url'),
					dataType: 'html',
					success: function (data) {
						// Replace the grid field with response, not the form.
						// TODO Only replaces all its children, to avoid replacing the current scope
						// of the executing method. Means that it doesn't retrigger the onmatch() on the main container.
						self.empty().append($(data).children());

						// Refocus previously focused element. Useful e.g. for finding+adding
						// multiple relationships via keyboard.
						if (focusedElName) self.find(':input[name="' + focusedElName + '"]').focus();

						form.removeClass('loading');
						if (successCallback) successCallback.apply(this, arguments);
						// TODO: Don't know how original SilverStripe GridField magically calls
						self.onadd();
					},
					error: function (e) {
						alert(i18n._t('Admin.ERRORINTRANSACTION'));
						form.removeClass('loading');
					}
				}, ajaxOpts));
			},
		});
	});
})(jQuery);
