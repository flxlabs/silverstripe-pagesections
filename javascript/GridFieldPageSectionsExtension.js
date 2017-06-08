(function($) {
	$.entwine("ss", function($) {
		/**
		 * Orderable rows
		 */

		$(".ss-gridfield-pagesections tbody").entwine({
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
						alert("hi");
					},
					over: function(event, ui) {
						var prev = ui.placeholder.prev().find(".col-treenav");
						var allowedElements = prev.data("allowed-elements").split(",");
						
						var helper = ui.helper.find(".col-treenav");
						console.log(allowedElements.indexOf(helper.data("class")) !== false);
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
