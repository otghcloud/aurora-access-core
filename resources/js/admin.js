import './bootstrap';

import '@tabler/core/js/tabler';

import '@fortawesome/fontawesome-free/js/fontawesome';
import '@fortawesome/fontawesome-free/js/regular';
import '@fortawesome/fontawesome-free/js/solid';

document.addEventListener('click', function (event) {
	const deleteLink = event.target.closest('[data-action="delete-modal"]');

	if (!deleteLink) {
		return;
	}

	event.preventDefault();

	if (!window.confirm('Are you sure you want to delete this item?')) {
		return;
	}

	const form = document.createElement('form');
	form.method = 'POST';
	form.action = deleteLink.href;
	form.hidden = true;

	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
	const fields = {
		_token: csrfToken ?? '',
		_method: 'DELETE',
	};

	Object.entries(fields).forEach(([name, value]) => {
		const input = document.createElement('input');
		input.name = name;
		input.value = value;
		form.appendChild(input);
	});

	document.body.appendChild(form);
	form.submit();
});


import $ from 'jquery';
window.$ = $;
window.jQuery = $;

import 'jquery-migrate';
import initDataTables from './admin/modules/datatables';

initDataTables();