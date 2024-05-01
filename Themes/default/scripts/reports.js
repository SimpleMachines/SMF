function breakWideTable(table, pageWidth) {
	const positions = [0];
	let curPageWidth = pageWidth;
	const els = table.rows[1].children;
	const pos = els[0].offsetWidth;

	for (let i = 1; i < els.length; i++) {
		if (els[i].offsetLeft + els[i].offsetWidth > curPageWidth) {
			// Split the table at this column.
			positions.push(i - 1);
			curPageWidth += pageWidth - pos;
		}
	}

	for (let i = 0; i < positions.length; i++) {
		const newTable = table.cloneNode(true);
		table.parentNode.append(newTable);

		if (table.id) {
			newTable.dataset.id = table.id;
			newTable.removeAttribute('id');
		}

		// Remove columns either side of our page.
		for (let j = els.length - 1; j >= 1; j--) {
			if (j > positions[i + 1] || j <= positions[i]) {
				for (let x = 1; x < newTable.rows.length; x++) {
					newTable.rows[x].children[j].remove();
				}
			}
		}
	}

	table.remove();
}

window.addEventListener('load', () => {
	const el = document.body.children[0].children[0];

	if (el.tagName == 'TABLE') {
		// This is the printer-friendly version.  600px seems
		// like a good cutoff point for 8½" x 11" (US Lettter).
		breakWideTable(el, 600);
	} else {
		// Create a copy of the node list that's not "live".
		for (const table of [...document.getElementsByClassName('report_result')]) {
			breakWideTable(table, table.parentNode.clientWidth);
		}
	}
});