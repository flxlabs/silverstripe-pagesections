<div
	class="treeview-item ui-draggable"
	data-id="$Item.ID"
	data-name="$Item.Name"
	data-class="$Item.ClassName"
	data-tree="$Tree"
	data-sort="$Item.SortOrder"
	data-is-open="$IsOpen"
	data-allowed-root="$AllowedRoot"
	data-allowed-elements="$AllowedElements"
>
	<div
		class="treeview-item__panel"
	>
		<div class="treeview-item-reorder">
			<div class="treeview-item-reorder__handle font-icon-drag-handle"></div>
			<% if IsFirst %>
			<div class="droppable before"><svg width='20' height='15' xmlns='http://www.w3.org/2000/svg'><path d='M10.957 10.882v2.367a1.21 1.21 0 0 1-1.905.988l-8.54-6.02a1.21 1.21 0 0 1 0-1.976l8.54-6.02a1.21 1.21 0 0 1 1.906.988v2.418h7.254c.668 0 1.21.542 1.21 1.21v4.836a1.21 1.21 0 0 1-1.21 1.209h-7.255z' fill='#4A4A4A' fill-rule='evenodd'/></svg></div>
			<% end_if %>
			<div class="droppable middle"><svg width='20' height='15' xmlns='http://www.w3.org/2000/svg'><path d='M10.957 10.882v2.367a1.21 1.21 0 0 1-1.905.988l-8.54-6.02a1.21 1.21 0 0 1 0-1.976l8.54-6.02a1.21 1.21 0 0 1 1.906.988v2.418h7.254c.668 0 1.21.542 1.21 1.21v4.836a1.21 1.21 0 0 1-1.21 1.209h-7.255z' fill='#4A4A4A' fill-rule='evenodd'/></svg></div>
			<div class="droppable after"><svg width='20' height='15' xmlns='http://www.w3.org/2000/svg'><path d='M10.957 10.882v2.367a1.21 1.21 0 0 1-1.905.988l-8.54-6.02a1.21 1.21 0 0 1 0-1.976l8.54-6.02a1.21 1.21 0 0 1 1.906.988v2.418h7.254c.668 0 1.21.542 1.21 1.21v4.836a1.21 1.21 0 0 1-1.21 1.209h-7.255z' fill='#4A4A4A' fill-rule='evenodd'/></svg></div>
		</div>
		<div class="treeview-item-flow">
			<div class="treeview-item__treeswitch">
				$TreeButton
			</div>
			<div class="treeview-item-content">
				$ButtonField
				<div class="treeview-item-content__classname">
					<strong>$Item.singular_name()</strong> ID: $Item.ID, Used: {$UsedCount}
				</div>
				<div class="treeview-item-content__title">
					$Item.Name
				</div>
			</div>
			<div class="treeview-item__preview">
				$Item.TreeViewPreview
			</div>
			<div class="treeview-item-actions treeview-item-actions--edit">
				$EditButton
				$DeleteButton
			</div>
		</div>
		<div class="treeview-item-actions treeview-item-actions--pre">
			$AddButton
		</div>
		<div class="treeview-item__children">
			$Children.RAW
		</div>
	</div>
	<div class="treeview-item__post-actions">
		$AddAfterButton
	</div>
</div>
