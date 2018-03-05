if (typeof(ss) === 'undefined' || typeof(ss.i18n) === 'undefined') {
  if (typeof(console) !== 'undefined') { // eslint-disable-line no-console
    console.error('Class ss.i18n not defined');  // eslint-disable-line no-console
  }
} else {
  ss.i18n.addDictionary('en', {
      "PageSections.GridField.AddAChild": "Add a child",
      "PageSections.GridField.Delete": "Delete",
      "PageSections.GridField.DeleteAChild": "Finally delete",
      "PageSections.GridField.RemoveAChild": "Remove",
  });
}
