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

					$col = $this.find(".col-reorder");
					$col.append("<div class='before'></div><div class='middle'></div><div class='after'></div>");
					$col.find("div").each(function() {
						$(this).droppable({
							tolerance: "pointer",
							drop: function(event, ui) {
								$drop = $(this);

								var type = "before";
								if ($drop.hasClass("middle"))
									type = "child";
								else if ($drop.hasClass("after"))
									type = "after";

								var id = ui.draggable.data("id");
								var parent = ui.draggable.find(".col-treenav").data("parent");
								var newParent = type === "child" ? $this.data("id") : $this.find(".col-treenav").data("parent");
								var sort = $this.find(".col-reorder").data("sort");

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
						helper: function() {
							var clone = $this.clone().css("z-index", 200).find(".ui-droppable").remove().end();
							// Timeout is needed otherwise the draggable position is messed up
							setTimeout(function() { $this.hide(); }, 1);
							return clone;
						},
						start: function() {
							$(".ui-droppable").show();
						},
						stop: function(event, ui) {
							$(".ui-droppable").hide();
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
