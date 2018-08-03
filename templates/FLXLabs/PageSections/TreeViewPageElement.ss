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
	<div class="treeview-item-flow">
		<div class="treeview-item-reorder">
			<% if IsFirst %>
				<div class="before"><svg width='20' height='15' xmlns='http://www.w3.org/2000/svg'><path d='M10.957 10.882v2.367a1.21 1.21 0 0 1-1.905.988l-8.54-6.02a1.21 1.21 0 0 1 0-1.976l8.54-6.02a1.21 1.21 0 0 1 1.906.988v2.418h7.254c.668 0 1.21.542 1.21 1.21v4.836a1.21 1.21 0 0 1-1.21 1.209h-7.255z' fill='#4A4A4A' fill-rule='evenodd'/></svg></div>
			<% end_if %>
			<div class="middle"><svg width='20' height='15' xmlns='http://www.w3.org/2000/svg'><path d='M10.957 10.882v2.367a1.21 1.21 0 0 1-1.905.988l-8.54-6.02a1.21 1.21 0 0 1 0-1.976l8.54-6.02a1.21 1.21 0 0 1 1.906.988v2.418h7.254c.668 0 1.21.542 1.21 1.21v4.836a1.21 1.21 0 0 1-1.21 1.209h-7.255z' fill='#4A4A4A' fill-rule='evenodd'/></svg></div>
			<div class="after"><svg width='20' height='15' xmlns='http://www.w3.org/2000/svg'><path d='M10.957 10.882v2.367a1.21 1.21 0 0 1-1.905.988l-8.54-6.02a1.21 1.21 0 0 1 0-1.976l8.54-6.02a1.21 1.21 0 0 1 1.906.988v2.418h7.254c.668 0 1.21.542 1.21 1.21v4.836a1.21 1.21 0 0 1-1.21 1.209h-7.255z' fill='#4A4A4A' fill-rule='evenodd'/></svg></div>
		</div>
		<div>
			$TreeButton
		</div>
		<div class="treeview-item-content">
			$ButtonField
			<div class="treeview-item-content__text">
				<div class="treeview-item-content__classname">
					$Item.ClassName (ID: $Item.ID, {$UsedCount}x)
				</div>
				<div class="treeview-item-content__title">
					$Item.Name
				</div>
			</div>
		</div>
		<div class="treeview-item-preview">
			$Item.TreeViewPreview
		</div>
		<div class="treeview-item-fill"></div>
	</div>
	<div class="treeview-item-children">
		$Children.RAW
	</div>
	<div class="treeview-item-actions">
		$AddButton
		$AddAfterButton
		$EditButton
		$DeleteButton
	</div>
</div>
