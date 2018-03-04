(function($) {
	$.entwine("ss", function($) {

		// Recursively hide a data-grid row and it's children
		var hideRow = function($row) {
			var id = $row.data("id");
			$("tr.ss-gridfield-item > .col-treenav[data-parent=" + id + "]").each(function() {
				hideRow($(this).parent());
			});
			$row.hide();
		};

		// Hide our custom context menu when not needed
		$(document).on("mousedown", function (event) {
			$parents = $(event.target).parents(".treenav-menu");
			if ($parents.length == 0) {
				$(".treenav-menu").remove();
			}
		});

		// Context menu click
		$(document).on("click", ".treenav-menu li", function(event) {
			var $this = $(this);
			var $menu = $this.parents(".treenav-menu");
			var $gridfield = $(".ss-gridfield-pagesections[data-id='" + $menu.data("grid-id") + "']").find("tbody");
			var newType = $this.data("type");

			// If we don't have a type then the user clicked a header or some random thing
			if (!newType) return;

			if (newType === "__REMOVE__") {
				$gridfield.removeElement($menu.data("row-id"), $menu.data("parent-id"));
			} else if (newType === "__DELETE__") {
				if (!confirm("Are you sure you want to remove this element? All children will be orphans!"))
					return;

				$gridfield.deleteElement($menu.data("row-id"));
			} else {
				$gridfield.addElement($menu.data("row-id"), newType);
			}

			$this.parents(".treenav-menu").hide();
		});

		// Show context menu
		$(".ss-gridfield-pagesections tbody").entwine({
			oncontextmenu: function(event) {
				$target = $(event.target);

				var grid = this.getGridField();
				var id = grid.data("id");
				var rowId = $target.parents(".ss-gridfield-item").data("id");
				var $treeNav = $target.hasClass("col-treenav") ? $target :
					$target.parents(".col-treenav").first();

				// If we don't have a col-treenav the user clicked on another column
				if ($treeNav.length <= 0) return;
				event.preventDefault();

				var parentId = null;
				var parentName = null;
				var level = $treeNav.data("level");
				if (level > 0) {
					// Go up through the rows and find the first row with lower level (=parent)
					$parent = $treeNav.parents(".ss-gridfield-item").prev();
					while ($parent.length > 0 &&
							$parent.find(".col-treenav").data("level") >= level) {
						$parent = $parent.prev();
					}
					if ($parent != null) {
						parentId = $parent.data("id");
						parentName = $parent.find(".col-treenav > span").html();
					}
				}

				var elems = $treeNav.data("allowed-elements");
				$menu = $("<ul id='treenav-menu-" + id + "' class='treenav-menu' data-id='" + id + "'></ul>");
				$menu.css({
					top: event.pageY + "px",
					left: event.pageX + "px"
				});
				$(document.body).append($menu);

				$menu.data({
					gridId: id,
					rowId: rowId,
					parentId: parentId,
				});
				$menu.append("<li class='header'>Add a child</li>");
				$.each(elems, function(key, value) {
					$menu.append("<li data-type='" + key + "'>" + value  + "</li>");
				});
				$menu.append("<li class='header options'>Options</li>");
				$menu.append("<li data-type='__REMOVE__'>Remove from " +
					(parentId ? parentName : "page") + "</li>");
				$menu.append("<li data-type='__DELETE__'>Delete</li>");
				$menu.show();
			},
			addElement: function(id, elemType) {
				var grid = this.getGridField();

				grid.reload({
					url: grid.data("url-add"),
					data: [
						{ name: "id", value: id },
						{ name: "type", value: elemType },
					]
				});
			},
			removeElement: function(id, parentId) {
				var grid = this.getGridField();

				grid.reload({
					url: grid.data("url-remove"),
					data: [
						{ name: "id", value: id },
						{ name: "parentId", value: parentId },
					]
				});
			},
			deleteElement: function(id) {
				var grid = this.getGridField();

				grid.reload({
					url: grid.data("url-delete"),
					data: [
						{ name: "id", value: id },
					]
				});
			},
			onadd: function() {
				var grid = this.getGridField();

				$("tr.ss-gridfield-item").each(function() {
					var $this = $(this);
					var icon = "<svg width='20' height='15' xmlns='http://www.w3.org/2000/svg'><path d='M10.957 10.882v2.367a1.21 1.21 0 0 1-1.905.988l-8.54-6.02a1.21 1.21 0 0 1 0-1.976l8.54-6.02a1.21 1.21 0 0 1 1.906.988v2.418h7.254c.668 0 1.21.542 1.21 1.21v4.836a1.21 1.21 0 0 1-1.21 1.209h-7.255z' fill='#4A4A4A' fill-rule='evenodd'/></svg>"

					$col = $this.find(".col-reorder");
					$col.append("<div class='before'>" + icon + "</div><div class='middle'>" + icon + "</div><div class='after'>" + icon + "</div>");
					$col.find("div").each(function() {
						$(this).droppable({
							hoverClass: "state-active",
							tolerance: "pointer",
							drop: function(event, ui) {
								$drop = $(this);

								var type = "before";
								var childOrder = 0;

								var $treenav = $this.find(".col-treenav");
								var $reorder = $this.find(".col-reorder");

								if ($drop.hasClass("middle")) {
									type = "child";
									childOrder = 1000000;
								} else if ($drop.hasClass("after")) {
									// If the current element is open, then dragging the other element to the
									// "after" slot means it becomes a child of this element, otherwise it
									// has to actually go after this element.
										if ($treenav.find("button").hasClass("is-open")) {
											type = "child";
											childOrder = -1000000;
										} else {
											type = "after";
										}
								}

								var id = ui.draggable.data("id");
								var parent = ui.draggable.find(".col-treenav").data("parent");
								var newParent = type === "child" ? $this.data("id") : $treenav.data("parent");
								var sort = type === "child" ? childOrder : $reorder.data("sort");

								// we alter the state of the published / saved buttons
								$('.cms-edit-form .Actions #Form_EditForm_action_publish').button({
									showingAlternate: true
								});

								grid.reload({
									url: grid.data("url-reorder"),
									data: [{
										name: "type",
										value: type,
									}, {
										name: "id",
										value: id,
									}, {
										name: "parent",
										value: parent,
									}, {
										name: "newParent",
										value: newParent,
									}, {
										name: "sort",
										value: sort,
									}],
								});
							},
						});
					});

					$this.draggable({
						revert: "invalid",
						cursor: "crosshair",
						cursorAt: { top: -15, left: -15 },
						activeClass: "state-active",
						hoverClass: "state-active",
						tolerance: "pointer",
						greedy: true,
						helper: function() {
							var $tr = $this.parents("tr.ss-gridfield-item");
							var $helper =  $(
								"<div class='col-treenav__draggable'>" +
								$this.find(".col-treenav__title").text() +
								"</div>"
							)
							$this.css("opacity", 0.6)

							return $helper;
						},
						start: function() {
							var element = $this.data("class");
							$(".ui-droppable").each(function() {
								var $drop = $(this);
								var $treenav = $drop.parent().siblings(".col-treenav");
								var isOpen = $treenav.find("button").hasClass("is-open");
								var $tr = $drop.parents("tr.ss-gridfield-item");

								// Check if we're allowed to drop the element on the specified drop point.
									// dont enable dropping on itself
								if ($tr.data("id") == $this.data("id")) return

								// dont enable dropping on .before of itself
								if ($drop.hasClass("before") && $tr.prev().data("id") == $this.data("id")) return
								// Depending on where we drop it (before, middle or after) we have to either
								// don't show middle if open
								if (
									$drop.hasClass("middle") &&
									isOpen
								) {
									return;
								}
								// let's handle level 0 if not open
								else if (
									$treenav.data("level") == 0 &&
									(
										$drop.hasClass("before") ||
										(
											$drop.hasClass("after") &&
											!isOpen
										)
									)
								) {
									var allowed = $treenav.data("allowed-page-elements");
									console.log(allowed, element, $treenav)
									if (!allowed[element]) return;
								}
								// check our allowed children, or the allowed children of our parent row.
								else if (
									$drop.hasClass("before") ||
									(
										$drop.hasClass("after") &&
										!isOpen
									)
								) {
									var $parent = $treenav.parent().siblings(
										"[data-id=" + $treenav.data("parent") + "]"
									).first();

									var allowed = $parent.find(".col-treenav").data("allowed-elements");
									if (allowed && !allowed[element]) return;
								} else {
									var allowed = $treenav.data("allowed-elements");
									if (!allowed[element]) return;
								}

								$(this).show();
							});
						},
						stop: function(event, ui) {
							$(".ui-droppable").hide();
							// Show the previous elements. If the user made an invalid movement then
							// we want this to show anyways. If he did something valid the grid will
							// refresh so we don't care if it's visible behind the loading icon.
							$("tr.ss-gridfield-item").css("opacity", "")
						},
					});
				});
			},
			onremove: function() {
				if (this.data('sortable')) {
					this.sortable("destroy");
				}
			}
		});
	});
})(jQuery);
