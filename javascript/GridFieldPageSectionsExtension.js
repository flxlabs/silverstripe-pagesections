(function($) {
	$.entwine("ss", function($) {
		/**
		 * Orderable rows
		 */

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
				event.preventDefault();

				$target = $(event.target);

				var grid = this.getGridField();
				var id = grid.data("id");
				var rowId = $target.parents(".ss-gridfield-item").data("id");
				var $treeNav = $target.parents(".col-treenav").first();
				var parentId = null;
				if ($treeNav.data("level") > 0) {
					parentId = $treeNav.parents(".ss-gridfield-item").prev().data("id");
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
				$menu.append("<li class='header'>--------------------</li>");
				$menu.append("<li data-type='__REMOVE__'>Remove</li>");
				$menu.append("<li data-type='__DELETE__'>Delete</li>");
				$menu.show();
			},
			rebuildSort: function() {
				var grid = this.getGridField();

				// Get lowest sort value in this list (respects pagination)
				var minSort = null;
				grid.getItems().each(function() {
					var sort = $(this).find(".col-reorder").data("sort");

					if (minSort === null && sort > 0) {
						minSort = sort;
					} else if (sort > 0) {
						minSort = Math.min(minSort, sort);
					}
				});
				minSort = Math.max(1, minSort);

				// With the min sort found, loop through all records and re-arrange
				var sort = minSort;
				grid.getItems().each(function() {
					$(this).find(".col-reorder").data("sort", sort);
					sort++;
				});
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
				var self = this;

				var helper = function(e, row) {
					return row.clone()
								.addClass("ss-gridfield-orderhelper")
								.width("auto")
								.find(".col-buttons")
								.remove()
								.end();
				};

				var update = function(event, ui) {
					// Rebuild all sort data fields
					self.rebuildSort();

					// If the item being dragged is unsaved, don't do anything
					if ((ui != undefined) && ui.item.hasClass('ss-gridfield-inline-new')) {
						return;
					}

					// Check if we are allowed to postback
					var grid = self.getGridField();
					grid.reload({
						url: grid.data("url-reorder")
					});
				};

				this.sortable({
					handle: ".handle",
					helper: helper,
					opacity: 0.7,
					update: update,
					start: function(event, ui) {
						console.log(ui.placeholder);
					},
					over: function(event, ui) {
						var prev = ui.placeholder.prev().find(".col-treenav");
						var allowedElements = Object.keys(prev.data("allowed-elements"));
						console.log(allowedElements);

						var helper = ui.helper.find(".col-treenav");
						console.log(allowedElements[helper.data("class")]);
					}
				});
			},
			onremove: function() {
				if (this.data('sortable')) {
					this.sortable("destroy");
				}
			}
		});

		/*
		$(".ss-gridfield-orderable .ss-gridfield-previouspage, .ss-gridfield-orderable .ss-gridfield-nextpage").entwine({
			onadd: function() {
				var grid = this.getGridField();

				if(this.is(":disabled")) {
					return false;
				}

				var drop = function(e, ui) {
					var page;

					if($(this).hasClass("ss-gridfield-previouspage")) {
						page = "prev";
					} else {
						page = "next";
					}

					grid.find("tbody").sortable("cancel");
					grid.reload({
						url: grid.data("url-movetopage"),
						data: [
							{ name: "move[id]", value: ui.draggable.data("id") },
							{ name: "move[page]", value: page }
						]
					});
				};

				this.droppable({
					accept: ".ss-gridfield-item",
					activeClass: "ui-droppable-active ui-state-highlight",
					disabled: this.prop("disabled"),
					drop: drop,
					tolerance: "pointer"
				});
			},
			onremove: function() {
				if(this.hasClass("ui-droppable")) this.droppable("destroy");
			}
		});*/
	});
})(jQuery);
