if (typeof(ss) === 'undefined' || typeof(ss.i18n) === 'undefined') {
	if (typeof(console) !== 'undefined') { // eslint-disable-line no-console
		console.error('Class ss.i18n not defined');  // eslint-disable-line no-console
	}
} else {
	ss.i18n.addDictionary('de', {
		"PageSections.GridField.FindExisting": "Bestehendes Element suchen",
		"PageSections.GridField.AddAChild": "Unterelement hinzufügen",
		"PageSections.GridField.Delete": "Löschen",
		"PageSections.GridField.DeleteAChild": "Endgültig löschen",
		"PageSections.GridField.RemoveAChild": "Entfernen",
	});
}
