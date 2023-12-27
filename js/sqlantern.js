/*
This file is part of SQLantern Database Manager
Copyright (C) 2022, 2023 Svitlana Militovska
License: GNU General Public License v3.0
https://github.com/nekto-kotik/sqlantern
https://sqlantern.com/

SQLantern is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
*/

let config = {
	language: 'en',
	default_auto_resize: true,
	default_open_query: true,
	auto_color: true,
	prefix: 'sqlantern',
	styles: '',
	auto_save_session: true,
	default_extra_hints: true,
	handy_queries: ['UPDATE {table} SET ? = ? WHERE ', 'DELETE FROM {table} WHERE ', 'SELECT * FROM {table} WHERE '],
	export_break_rows: 100,
	backend: 'php/',
	default_profiler_time: 1000,
	distinct: 'SELECT COUNT(*) AS quantity, {table}.{field} FROM {table} GROUP BY {table}.{field} ORDER BY COUNT(*) DESC',
};

let app = {
	connections: [],
	tabs: [],
	scroll: [],
	default_full_texts: null,
	translations: {},
	version: '',
	matrix: {state: false, value: ''},
	id: '',
	start_config: {},
	interval: null,
};

function Tab(drag, obj) {
	this.tab = null;
	this.drag = drag;
	this.parent = drag.parent;
	this.connection = obj.connection;
	this.database = obj.database;
	this.table = obj.table;
	this.newConnect = obj.newConnect;
	this.newDB = obj.newDB;
	this.prevTab = obj.prevTab;
	this.color = obj.color;
	this.duplicate = obj.duplicate;
	this.sql = `SELECT * FROM ${this.table}`;
	this.history = [this.sql];
	this.columns = [];
	this.keepAliveInterval = null;
	this.intoView = false;
	this.createTab();
}

Tab.prototype.request = function(obj) {
	const self = this;
	obj.body.language = config.language;
	let init = {
		method: 'POST',
		headers: {'Content-type': 'application/json'},
		body: JSON.stringify(obj.body),
	};
	if (obj.form) {
		init.body = obj.body;
		delete init.headers;
	};
	
	if (obj.forError && obj.forError.querySelector('.error')) {
		obj.forError.querySelector('.error').remove();
	}

	let errorText;
	self.processing(true);
	fetch(config.backend, init)
		.then(res => res.text())
		.then(text => {
			errorText = text;
			return JSON.parse(text);
		})
		.then(res => {
			if (res.version) {
				const title = document.querySelector('title').textContent.split('(');
				document.querySelector('title').textContent = `${title[0]} (${res.version})`;
				app.version = res.version;
			}
			if (obj.callback && self.tab) {
				obj.callback(res);
			}
		})
		.catch(err => {
			if (!self.tab) return;
			if (obj.catchCallback) {
				obj.catchCallback(errorText);
				return;
			}
			if (obj.forError) {
				self.throwError(errorText, obj.forError);
			}
		})
		.finally(() => {
			if (!self.tab) return;
			self.processing();
			if (obj.end) {
				obj.end();
			}
		});
}

Tab.prototype.throwError = function(errorText, elem) {
	const self = this;
	const tmp = document.querySelector('.templates .error').cloneNode(true);
	if (errorText) {
		tmp.querySelector('.error-text').innerHTML = errorText;
	} else {
		tmp.querySelector('.error-text').dataset.text = 'error-network';
		tmp.querySelector('.error-text').textContent =  app.translations['error-network'];
	}
	if (typeof elem == 'object') {
		elem.append(tmp);
	} else {
		self.tab.querySelector(elem).append(tmp);
	}
}

Tab.prototype.requestListTables = function() {
	const self = this;
	return {
		list_tables: true,
		connection_name: self.connection,
		database_name: self.database,
	};
}

Tab.prototype.requestQueryTiming = function(name) {
	const self = this;
	const obj = {
		connection_name: self.connection,
		database_name: self.database,
		query_timing: name,
	};
	const init = {
		method: 'POST',
		headers: {'Content-type': 'application/json'},
		body: JSON.stringify(obj)
	};
	return fetch(config.backend, init);
}

Tab.prototype.runQuery = function(page) {
	const self = this;
	//let sql = self.tab.querySelector('textarea').value.replace(/\\/g, '\\\\');
	let sql = self.tab.querySelector('textarea').value;
	if (!sql) return;

	if (!page) {
		self.tab.querySelector('.cur-query').value = sql;
	} else {
		sql = self.tab.querySelector('.cur-query').value;
	}
	
	let obj = {
		body: {
			query: sql,
			connection_name: self.connection,
			database_name: self.database,
			full_texts: self.tab.querySelector('.full-text input').checked ? 'true' : 'false',
		},
		callback: res => {
			self.fillQueryResult(res);
			self.checkTableWidth();
			if (!self.tab.classList.contains('width-auto')) {
				self.checkScrollX();
			}
		},
		end: () => {
			const run = self.tab.querySelector('.run-block .stop-run');
			if (run) {
				const time = +self.tab.querySelector('.run-block input').value * 1000;
				const bar = self.tab.querySelector('.query-block .bar');
				bar.style.transitionDuration = `${time}ms`;
				bar.style.width = '100%';
			}
			self.tab.classList.add('executed');
			setTimeout(() => self.tab.classList.remove('executed'), 1000);
		},
		forError: self.tab.querySelector('.query-block'),
	};
	if (page) {
		obj.body.page = page;
	}
	self.request(obj);
}

Tab.prototype.showHistory = function() {
	const self = this;
	const holder = self.tab.querySelector('.blocks-list .history');
	holder.innerHTML = '';
	for (let i = 0; i < self.history.length; i++) {
		const tmp = document.querySelector('.templates .tmp-line').cloneNode(true);
		//tmp.querySelector('.query-name').textContent = self.history[i].split('\n')[0];
		tmp.querySelector('.name').title = self.history[i];
		tmp.querySelector('.name').textContent = self.history[i];
		tmp.querySelector('.name').addEventListener('click', function() {
			self.tab.querySelector('textarea').value = self.history[i];
			self.tab.querySelector('.block-name.query').click();
			self.tab.querySelector('textarea').focus();
			self.autoResize();
		});
		tmp.querySelector('.delete').addEventListener('click', function() {
			self.history.splice(i, 1);
			tmp.remove();
		});
		holder.append(tmp);
	}
}

Tab.prototype.savedQueries = function() {
	const self = this;
	const item = localStorage.getItem(`${config.prefix}.queries`);
 	if (!item) return;
	const obj = JSON.parse(item);
	const arr = Object.entries(obj).sort((a, b) => a[1].name > b[1].name ? 1 : -1);
	const holder = self.tab.querySelector('.blocks-list .saved-queries');
	holder.innerHTML = '';
	for (let i = 0; i < arr.length; i++) {
		const tmp = document.querySelector('.templates .tmp-line').cloneNode(true);
		tmp.querySelector('.key').value = arr[i][0];
		tmp.querySelector('.name').textContent = arr[i][1].name;
		tmp.querySelector('.query').textContent = arr[i][1].query;
		tmp.querySelector('.name').addEventListener('click', function() {
			self.tab.querySelector('textarea').value = arr[i][1].query;
			self.tab.querySelector('.block-name.query').click();
			self.tab.querySelector('textarea').focus();
			self.autoResize();
		});
		tmp.querySelector('.delete').addEventListener('click', function() {
			const idx = tmp.querySelector('.key').value;
			delete obj[idx];
			localStorage.setItem(`${config.prefix}.queries`, JSON.stringify(obj));
			tmp.remove();
		});
		holder.append(tmp);
	}
}

Tab.prototype.addQuery = function() {
	const self = this;
	const popUp = document.querySelector('.templates .pop-up-save-query').cloneNode(true);
	const query = self.tab.querySelector('textarea').value;
	popUp.querySelector('input').placeholder = query;
	popUp.querySelector('.btn-save').addEventListener('click', () => {
		if (!query) return;
		const queryName = popUp.querySelector('input').value || query;
		const itemName = `${config.prefix}.queries`;
		const item = JSON.parse(localStorage.getItem(itemName)) || {};
		let keys = Object.keys(item);
		let idx = keys.length ? Math.max(...keys) : 0;
		item[idx + 1] = {name: queryName, query: query};
		localStorage.setItem(itemName, JSON.stringify(item));
		
		popUp.querySelector('.btn-cancel').click();
	});
	popUp.querySelector('input').addEventListener('keyup', function(e) {
		if (e.key == 'Enter') {
			popUp.querySelector('.btn-save').click();
		}
		if (e.key == 'Escape') {
			popUp.querySelector('.btn-cancel').click();
		}
	});
	popUp.querySelector('.btn-cancel').addEventListener('click', () => {
		document.body.classList.remove('pop-up-active');
		popUp.remove();
	});
	
	document.body.append(popUp);
	document.body.classList.add('pop-up-active');
	popUp.querySelector('input').focus();
}

Tab.prototype.getWidth = function(el) {
	return Math.ceil(el.getBoundingClientRect().width);
}

Tab.prototype.setWidth = function() {
	const self = this;
	self.tab.classList.add('no-overflow');
	self.tab.style.width = self.getWidth(self.tab) + 'px';
	self.tab.classList.remove('no-overflow');
	self.tab.querySelectorAll('.table.structure, .table.indexes').forEach(el => el.classList.add('close'));
	new SimpleBar(self.tab.querySelector('.content'));
}

Tab.prototype.checkTableWidth = function() {
	const self = this;
	const cover = self.tab.querySelector('.table.rows');
	const table = cover.querySelector('table');
	const num = table.querySelectorAll('thead td').length;
	self.tab.classList.add('start');
	cover.style.width = 200 * num + 'px';
	const width = self.getWidth(table);
	cover.style.width = 'unset';
	self.tab.classList.remove('start');
	table.style.width = width + 'px';
	self.tab.style.width = Math.max(width + 30, self.minWidth) + 'px';
}

Tab.prototype.createTable = function(rows) {
	const self = this;
	const table = document.querySelector('.templates .table').cloneNode(true);
	const thead = table.querySelector('thead');
	const tbody = table.querySelector('tbody');
	if (rows && rows.length) {
		for (let i = 0; i < rows.length; i++) {
			let tr = document.createElement('tr');
			for (let j in rows[i]) {
				let td = document.createElement('td');
				let div = document.createElement('div');
				div.textContent = j;
				td.appendChild(div);
				tr.appendChild(td);
			}
			thead.appendChild(tr);
			break;
		}
		for (let i = 0; i < rows.length; i++) {
			let tr = document.createElement('tr');
			for (let j in rows[i]) {
				let td = document.createElement('td');
				td.textContent = rows[i][j];
				if (rows[i][j] === null) {
					td.classList.add('null');
				}
				else if (typeof rows[i][j] == 'object') {
					td.classList.add('object');
					td.textContent = '';
				}
				if (['Rows', 'Size'].indexOf(j) > -1) {
					td.classList.add(j.toLowerCase());
				}
				tr.appendChild(td);
			}
			tbody.appendChild(tr);
		}
	} else {
		table.classList.add('empty');
	}
	return table;
}

Tab.prototype.closeColumn = function(table) {
	const self = this;
	table.querySelector('.line').append(document.querySelector('.templates .columns-list').cloneNode(true));
	const elems = table.querySelectorAll('thead div');
	const columnsList = table.querySelector('.for-columns');
	
	const closeColumn = (i) => {
		table.querySelectorAll(`tr td:nth-child(${i + 1})`).forEach(td => {
			td.classList.add('close');
		});
		table.classList.add('columns');
	};

	const createLine = (name, i) => {
		const line = document.querySelector('.templates .one-line.one').cloneNode(true);
		line.querySelector('.name').textContent = name;
		
		line.querySelector('input').addEventListener('change', () => {
			table.querySelectorAll(`tr td:nth-child(${i + 1})`).forEach(td => {
				td.classList.remove('close');
			});
			self.columns.forEach((el, e) => {
				el == name ? self.columns.splice(e, 1) : '';
			});
			if (!self.columns.length) {
				table.classList.remove('columns', 'show-columns');
			}
			line.remove();
			self.checkTableWidth();
			if (!self.tab.classList.contains('width-auto')) {
				self.checkScrollX();
			}
		});
		
		columnsList.append(line);
	};
	
	elems.forEach((el, i) => {
		const name = el.textContent;
		const span = document.createElement('span');
		span.textContent = '✕';
		if (self.columns.indexOf(name) != -1) {
			closeColumn(i);
			createLine(name, i);
		}
		el.append(span);
	});
	
	table.querySelectorAll('thead span').forEach((el, i) => {
		el.addEventListener('click', () => {
			const name = el.closest('div').textContent.slice(0, -1);
			closeColumn(i);
			createLine(name, i);
			self.columns.unshift(name);
			self.checkTableWidth();
			self.checkScrollX();
		});
	});
	
	columnsList.querySelector('.one-line.all input').addEventListener('change', function() {
		columnsList.querySelectorAll('.one-line.one').forEach(line => line.remove());
		table.querySelectorAll(`tr td`).forEach(td => {
			td.classList.remove('close');
			table.classList.remove('columns', 'show-columns');
			self.columns = [];
		});
		this.checked = true;
		self.checkTableWidth();
		if (!self.tab.classList.contains('width-auto')) {
			self.checkScrollX();
		}
	});
	table.querySelector('.open-columns').addEventListener('click', function() {
		table.classList.toggle('show-columns');
	});
}

Tab.prototype.clickBlockName = function() {
	const self = this;	
	self.tab.querySelectorAll('.table-line .block-name').forEach((elem, i) => {
		elem.addEventListener('click', () => {
			const names = self.tab.querySelectorAll('.table-line .block-name');
			const tables = self.tab.querySelectorAll('.table.structure, .table.indexes');
			elem.classList.toggle('active');
			tables[i].classList.toggle('close');
			names.forEach(el => el != elem ? el.classList.remove('active') : '');
			tables.forEach(el => el != tables[i] ? el.classList.add('close') : '');
		});
	});
}

Tab.prototype.checkScrollX = function() {
	const self = this;
	const bar = self.tab.querySelector('.scroll-wrapper .bar');
	const table = self.tab.querySelector('.table.rows table');
	const tableWidth = table.offsetWidth;
	const overallWidth = self.tab.querySelector('.table.rows').offsetWidth;
	if (tableWidth > overallWidth) {
		const barWidth = overallWidth / (tableWidth / overallWidth);
		let barShift = window.getComputedStyle(bar).left.slice(0, -2);
		self.bit = (tableWidth - overallWidth) / (overallWidth - barWidth);
		bar.style.width = barWidth + 'px';
		
		const barRight = bar.getBoundingClientRect().right;
		const tableRight = self.tab.querySelector('.table.rows').getBoundingClientRect().right;
		if (barRight > tableRight) {
			barShift = overallWidth - barWidth;
			bar.style.left = barShift + 'px';
		}
		if (self.tab.querySelector('.page-block')) {
			bar.parentNode.classList.add('height');
		} else {
			bar.parentNode.classList.remove('height');
		}
		table.style.left = -(barShift * self.bit) + 'px';
		self.tab.classList.add('show-scroll');
	} else {
		self.tab.classList.remove('show-scroll');
		bar.style.left = '0';
		table.style.left = '0';
	}
};

Tab.prototype.createScrollX = function() {
	const self = this;
	const tmp = document.querySelector('.templates .scroll-wrapper').cloneNode(true);
	const table = self.tab.querySelector('.table.rows table');
	const bar = tmp.querySelector('.bar');
	const events = ['mousemove', 'touchmove']
	const events2 = ['mouseup', 'touchend', 'mouseleave', 'touchcancel'];
	let left = 0;
	
	function onMouseMove(e) {
		e.preventDefault();
		if (e.type == 'touchmove') {
			e.pageX = e.touches[0].pageX;
		}
		let shift = e.pageX - left;
		const right = shift + bar.offsetWidth;
		if (shift < 0) {
			shift = 0;
		}
		if (right > tmp.offsetWidth) {
			shift = tmp.offsetWidth - bar.offsetWidth;
		}
		bar.style.left = shift + 'px';
		table.style.left = -(shift * self.bit) + 'px';
	}
	
	function onMouseUp() {
		events.forEach(evt => {
			self.tab.removeEventListener(evt, onMouseMove);
		});
		events2.forEach(evt => {
			document.body.removeEventListener(evt, onMouseUp);
		});
	}
	
	['mousedown', 'touchstart'].forEach(event => {
		bar.addEventListener(event, function(e) {
			e.preventDefault();
			if (e.type == 'touchstart') {
				e.pageX = e.touches[0].pageX;
			}
			left = e.pageX - (bar.getBoundingClientRect().left - tmp.getBoundingClientRect().left);
			
			events.forEach(evt => {
				self.tab.addEventListener(evt, onMouseMove);
			});
			events2.forEach(evt => {
				document.body.addEventListener(evt, onMouseUp);
			});
		});
	});

	tmp.addEventListener('click', function(e) {
		if (!e.target.classList.contains('scroll-wrapper')) return;
		const right = bar.getBoundingClientRect().right;
		const left = bar.getBoundingClientRect().left;
		const tableLeft = Math.abs(window.getComputedStyle(table).left.slice(0, -2));
		const tableWidth = self.tab.querySelector('.table.rows table').offsetWidth;
		const overallWidth = self.tab.querySelector('.table.rows').offsetWidth;
		const parentRight = tableWidth - overallWidth;
		
		let shift;
		if (e.pageX > right) {
			shift = tableLeft + overallWidth;
			shift = shift > parentRight ? parentRight : shift;
		}
		if (e.pageX < left) {
			shift = tableLeft - overallWidth;
			shift = shift < 0 ? 0 : shift;
		}
		table.style.left = -shift + 'px';
		bar.style.left = (shift / self.bit) + 'px';
	});
	
	self.tab.querySelector('.table.rows table').after(tmp);
};

Tab.prototype.fillQueryResult = function(res) {
	const self = this;
	const curTable = self.tab.querySelector('.table.rows');
	curTable ? curTable.remove() : null;
	
	//const sql = self.tab.querySelector('textarea').value.replace(/\\/g, '\\\\');
	const sql = self.tab.querySelector('textarea').value;
	if (self.history.indexOf(sql) == -1) {
		self.history.unshift(sql);
	}
	
	const table = self.createTable(res.rows);
	const span = document.createElement('span');
	span.classList.add('num-rows');
	span.textContent = `(${res.num_rows})`;
	table.classList.add('rows');
	table.querySelector('.line').append(span);
	table.querySelector('.block-name').textContent = app.translations['rows'];
	table.querySelector('.block-name').dataset.text = 'rows';
	self.closeColumn(table);
	self.tab.querySelector('.query-block').after(table);
	
	if (res.num_rows == null) {
		table.querySelector('.block-name').classList.add('close');
	}
	
	if (res.num_pages) {
		self.tab.querySelector('.page-block') ? self.tab.querySelector('.page-block').remove() : '';
		const numPages = +res.num_pages.match(/\d/g).join('');
		if (numPages > 1) {
			const tmp = document.querySelector('.templates .page-block').cloneNode(true);
			const curPage = tmp.querySelector('.cur-page');
			
			function changePage(elem) {
				let page = +curPage.value;
				if (elem.classList.contains('arrow-left')) {
					page -= 1;
				}
				if (elem.classList.contains('arrow-right')) {
					page += 1;
				}
				if (page > 0 && page <= numPages) {
					curPage.value = page;
					self.runQuery(page);
				}
			}
			tmp.querySelectorAll('.arrow').forEach(elem => elem.addEventListener('click', function() {
				changePage(this);
			}));
			curPage.addEventListener('keyup', function(e) {
				if (e.key == 'Enter') {
					changePage(this);
				}
			});
			curPage.addEventListener('click', function() {
				this.setSelectionRange(0, this.value.length);
			});
			table.append(tmp);
			self.tab.querySelector('.cur-page').value = res.cur_page;
			self.tab.querySelector('.num-pages span').textContent = res.num_pages;
		}
	}
	self.createScrollX();
}

Tab.prototype.addChooseField = function(table) {
	const self = this;
	table.append(document.querySelector('.templates .fields-list').cloneNode(true));
	
	const tr = table.querySelectorAll('tr');
	for (let i = 0; i < tr.length; i++) {
		const td = document.createElement('td');
		const box = document.querySelector('.templates .box').cloneNode(true);
		box.querySelector('input').checked = false;
		box.querySelector('input').addEventListener('change', function() {
			const list = self.tab.querySelector('.cover-fields');
			const checked = self.tab.querySelector('input:checked');
			if (checked) {
				self.tab.classList.add('clipboard');
			}
			
			let fields = [];
			tr.forEach(el => {
				const input = el.querySelector('input:checked');
				input ? fields.push(input.closest('tr').querySelector('td:nth-child(2)').textContent) : '';
			});
			const str = fields.join(', ');
			list.innerText = str;
			
			if (!list.innerText.length) {
				self.tab.classList.remove('clipboard');
			}
		})
		td.append(box);
		tr[i].querySelector('td').before(td);
	}
	table.querySelector('.fields-list .copy').addEventListener('click', () => {
		const str = table.querySelector('.cover-fields').textContent;
		try {
			navigator.clipboard.writeText(str);
		}
		catch (e) {
			try {
				var input = document.createElement('textarea');
				input.value = str;
				input.style.position = 'absolute';
				input.style.opacity = 0;
				document.body.append(input);
				input.select();
				document.execCommand('copy');
				input.remove();
			}
			catch (e) {
				console.log('Clipboard failed');
			}
		}
	});
}

Tab.prototype.addCondition = function(table) {
	const self = this;
	table.querySelectorAll('tr').forEach((tr, i) => {
		const td = document.createElement('td');
		const box = document.querySelector('.templates .box-input').cloneNode(true);
		box.querySelector('.field').value = `${self.table}.${tr.querySelector('td:nth-child(2)').textContent}`;
		box.querySelector('.input-text').addEventListener('keyup', function(e) {
			if (e.ctrlKey && e.key == 'Enter') self.tab.querySelector('.run-block .btn-run').click();
			let arr = [];
			table.querySelectorAll('.input-text').forEach(input => {
				const box = input.closest('.box-input');
				const inputValue = box.querySelector('.input-text').value;
				const fieldValue = box.querySelector('.field').value;
				if (inputValue) {
					const str = fieldValue + ' ' + inputValue;
					box.querySelector('.string').value = str;
					arr.push(str);
				}
			});
			const condition = arr.join(' AND ');
			self.tab.querySelector('.textarea-value').value = condition;
			self.tab.querySelector('textarea').value = `SELECT * FROM ${self.table} WHERE ${condition}`;
		});
		box.querySelector('.delete').addEventListener('click', function() {
			const str = box.closest('.box-input').querySelector('.string').value;
			const arr = self.tab.querySelector('.textarea-value').value.split(' AND ');
			const idx = arr.indexOf(str);
			if (idx >= 0) {
				arr.splice(idx, 1);
				const condition = arr.join(' AND ');
				self.tab.querySelector('textarea').value = 
					arr.length ? `SELECT * FROM ${self.table} WHERE ${condition}` : `SELECT * FROM ${self.table}`;
				self.tab.querySelector('.query-block .textarea-value').value = condition;
			}
			box.querySelector('.input-text').value = '';
		});
		td.append(box);
		tr.querySelector('td:last-child').after(td);
	});
	table.querySelector('tr td:last-child label').innerHTML = 'SELECT * WHERE<div class="delete">✕</div>';
	table.querySelector('tr td:last-child .delete').addEventListener('click', () => {
		table.querySelectorAll('.box-input .input-text').forEach(input => input.value = '');
		self.tab.querySelector('textarea').value = `SELECT * FROM ${self.table}`;
	});
}

Tab.prototype.addDistinct = function(table) {
	const self = this;
	table.querySelectorAll('tr').forEach((tr, i) => {
		const td = document.createElement('td');
		td.textContent = 'DISTINCT';
		td.addEventListener('click', () => {
			if (i == 0) return;
			const field = tr.querySelector('td:nth-child(2)').textContent;
			let newQuery = config.distinct.replaceAll('{table}', self.table);
			self.tab.querySelector('textarea').value = newQuery.replaceAll('{field}', field);
			self.tab.querySelector('.query-block .btn-run').click();
		});
		tr.querySelector('td:last-child').before(td);
	});
}

Tab.prototype.addCustomName = function() {
	const self = this;
	const tmp = document.querySelector('.templates .custom-name').cloneNode(true);
	tmp.querySelector('.edit').addEventListener('click', () => {
		tmp.querySelector('input').value = tmp.querySelector('span').textContent;
		tmp.classList.toggle('active');
		tmp.querySelector('input').focus();
	});
	tmp.querySelector('.confirm').addEventListener('click', () => {
		tmp.querySelector('span').textContent = tmp.querySelector('input').value;
		tmp.classList.toggle('active');
	});
	tmp.querySelector('input').addEventListener('keyup', (e) => {
		if (e.key == 'Enter') {
			tmp.querySelector('.confirm').click();
		}
		if (e.key == 'Escape') {
			tmp.classList.remove('active');
		}
	});
	self.showHint(tmp);
	self.tab.querySelector('.table-line').append(tmp);
}

Tab.prototype.autoResize = function() {
	const self = this;
	const autoResize = self.tab.querySelector('.auto-resize input').checked;
	if (autoResize) {
		const textarea = self.tab.querySelector('textarea');
		if (textarea.offsetHeight != textarea.scrollHeight) {
			const tmp = textarea.cloneNode();
			tmp.style.height = '';
			tmp.style.opacity = 0;
			self.tab.querySelector('.content').append(tmp);
			const textareaHeight = tmp.scrollHeight;
			tmp.remove();
			const height = textareaHeight < 100 ? 100 : textareaHeight;
			textarea.style.height = height + 'px';
		}
	}
}

Tab.prototype.addQueryBlock = function(res) {
	const self = this;
	const tmp = document.querySelector('.templates .query-block').cloneNode(true);
	const bar = tmp.querySelector('.bar');
	const autoResize = tmp.querySelector('.auto-resize input');
	const textarea = tmp.querySelector('textarea');
	textarea.value = self.sql;
	autoResize.checked = config.default_auto_resize;
	config.default_auto_resize ? self.tab.classList.add('resize') : '';
	tmp.querySelector('.cur-query').value = self.sql;
	tmp.querySelector('.full-text input').checked = app.default_full_texts;
	tmp.querySelector('.time-block input').value = '1';

	if (!config.default_open_query) {
		tmp.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
	}
	
	bar.addEventListener('transitionend', () => {
		bar.style.transitionDuration = 'unset';
		bar.style.width = '0';
		self.runQuery();
	});
	tmp.querySelector('.btn-run').addEventListener('click', function() {
		if (this.classList.contains('stop-run')) {
			bar.style.transitionDuration = 'unset';
			bar.style.width = '0';
		}
		if (self.tab.classList.contains('with-time')) {
			this.classList.toggle('stop-run');
		}
		self.runQuery();
	});
	tmp.querySelector('.run-block .arrow').addEventListener('click', () => {
		self.tab.classList.toggle('with-time');
		tmp.querySelector('.run-block input').focus();
	});
	textarea.addEventListener('keydown', (e) => {
		const start = textarea.selectionStart;
		const end = textarea.selectionEnd;
		const selection = start != end ? true : false;
		if (e.key == 'Tab' && !selection && e.shiftKey) {
			e.preventDefault();
			return;
		}
		if (e.ctrlKey && e.key == 'Enter') {
			tmp.querySelector('.btn-run').click();
		}
		if (e.key == 'Tab' && !selection) { // add tab
			e.preventDefault();
			textarea.setRangeText(
				'\u0009',
				textarea.selectionStart,
				textarea.selectionStart,
				'end'
			)
		}
		if (e.key == 'Tab' && selection) {
			e.preventDefault();
			let fromLine = textarea.value.slice(0, start).split('\n').length - 1;
			let toLine = textarea.value.slice(0, end).split('\n').length - 1;
			let curStr = textarea.value.split('\n');
			let obj = {};
			for (let i = fromLine; i <= toLine; i++) {
				if (e.shiftKey && curStr[i][0] == '\t') {
					obj[i] = 1;
					curStr[i] = curStr[i].slice(1);
				}
				if (!e.shiftKey) {
					obj[i] = 1;
					curStr[i] = '\t' + curStr[i];
				}
			}
			textarea.value = curStr.join('\n');
			
			let newStart = (obj[fromLine] && start > 0) ? start - 1 : start;
			let newEnd = end - Object.keys(obj).length;
			if (!e.shiftKey) {
				newStart = obj[fromLine] ? start + 1 : start;
				newEnd = end + Object.keys(obj).length;
			}
			textarea.setSelectionRange(newStart, newEnd);
		}
	});
	textarea.addEventListener('keyup', () => self.autoResize());
	textarea.addEventListener('paste', () => {
		setTimeout(function() {
			let keyup = new Event('keyup');
			textarea.dispatchEvent(keyup);
		}, 100);
	});
	autoResize.addEventListener('change', () => {
		if (autoResize.checked) {
			self.autoResize();
			self.tab.classList.add('resize');
		} else {
			textarea.removeAttribute('style');
			self.tab.classList.remove('resize');
		}
	});
	tmp.querySelector('.btn-add-query').addEventListener('click', () => self.addQuery());
	const funcs = [() => textarea.focus(), () => self.showHistory(), () => self.savedQueries()];
	tmp.querySelectorAll('.block-name').forEach((elem, idx) => {
		elem.addEventListener('click', () => {
			elem.classList.toggle('active');
			tmp.querySelectorAll('.active').forEach(el => {
				if (el != elem) {
					el.classList.remove('active');
				}
			});
			if (elem.classList.contains('active')) {
				tmp.querySelectorAll('.blocks-list > div')[idx].classList.add('active');
			} else {
				tmp.querySelectorAll('.blocks-list > div')[idx].classList.remove('active');
			}
			funcs[idx]();
		});
	});
	tmp.querySelector('.tmp-queries').addEventListener('click', function() {
		this.classList.toggle('open');
		let queries = config.handy_queries.map(el => el.replace('{table}', self.table));
		if (this.classList.contains('open')) {
			const block = document.createElement('div');
			block.className = 'block-tmp-queries';
			for (let i = 0; i < queries.length; i++) {
				const div = document.createElement('div');
				div.textContent = queries[i];
				div.addEventListener('click', () => {
					textarea.value = queries[i];
					tmp.querySelector('.block-tmp-queries').remove();
					this.classList.remove('open');
					textarea.focus();
				});
				block.append(div);
			}
			tmp.append(block);
		} else {
			tmp.querySelector('.block-tmp-queries').remove();
		}
	});
	tmp.querySelectorAll('.tmp-queries, label[data-hint]').forEach(elem => self.showHint(elem));
	
	self.tab.querySelector('.content').append(tmp);
}

Tab.prototype.fillTables = function(res) {
	const self = this;
	self.tab.querySelector('.db-name').textContent = self.database;
	self.tab.querySelector('.tb-name').textContent = self.table;
	self.tab.classList.add('query');

	if (res.structure) {
		const table = self.createTable(res.structure);
		table.classList.add('structure')
		table.querySelector('.block-name').textContent = app.translations['structure-heading'];
		table.querySelector('.block-name').dataset.text = 'structure-heading';
		table.querySelector('.line').classList.add('table-line');
		self.tab.querySelector('.content').append(table.querySelector('.line'));
		
		self.addChooseField(table);
		self.addCondition(table);
		self.addDistinct(table);
		self.addCustomName();
		self.tab.querySelector('.content').append(table);
	}
	if (res.indexes) {
		const indexes = res.indexes;
		for (let i = 0; i < indexes.length; i++) {
			indexes[i].columns = indexes[i].columns.split('\n').join('<br>');
		}
		const table = self.createTable(indexes);
		table.classList.add('indexes');
		table.querySelector('.block-name').textContent = app.translations['indexes-heading'];
		table.querySelector('.block-name').dataset.text = 'indexes-heading';
		self.tab.querySelector('.table-line').append(table.querySelector('.block-name'));
		self.tab.querySelector('.content').append(table);
	}
	self.minWidth = self.getWidth(self.tab);
	
	self.clickBlockName();
	self.addQueryBlock(res);
	self.fillQueryResult(res);
	
	const table = self.tab.querySelector('.table.rows table');
	self.tab.classList.add('start');
	table.style.width = self.getWidth(table) + 'px';
	self.tab.classList.remove('start');
	self.setWidth();
}

Tab.prototype.appendTab = function() {
	const self = this;
	const tabs = self.parent.querySelectorAll('.one-tab');
	let idx;
	
	if (self.prevTab) {
		const from = Array.from(tabs).indexOf(self.prevTab);
		for (let i = from; i < tabs.length; i++) {
			const elem = tabs[i].querySelector('.db-name');
			const database = elem ? elem.textContent : '';
			const connection = tabs[i].querySelector('.connection').value;
			if (!self.newDB && (database != self.database || connection != self.connection)) {
				idx = i;
				break;
			}
			if (self.newDB && connection != self.connection) {
				idx = i;
				break;
			}
		}
	}

	if (self.duplicate) {
		self.prevTab.after(self.tab);
	} else if (idx) {
		tabs[idx].before(self.tab);
	} else {
		self.parent.append(self.tab);
	}
	
	if (self.newDB && config.auto_color) {
		const length = document.querySelectorAll('.templates .palette > div').length;
		let color = self.tab.previousElementSibling ? +self.tab.previousElementSibling.dataset.color : 0;
		let arr = [];
		for (let i = 1; i < length; i++) {
			if (i != color) {
				arr.push(i);
			}
		}
		const idx = Math.floor(Math.random() * arr.length);
		self.tab.dataset.color = arr[idx];
	}
}

Tab.prototype.createProfiler = function() {
	const self = this;
	const tmp = document.querySelector('.templates .profiler').cloneNode(true);
	const obj = {
		parent: tmp.querySelector('.fields'),
		dragSelector: '.sql-time',
	};
	const drag = new Drag(obj);
	tmp.querySelector('.btn-add').addEventListener('click', () => {
		const block = document.querySelector('.templates .sql-time').cloneNode(true);
		block.querySelector('.delete').addEventListener('click', () => {
			if (!tmp.querySelector('.btn-run.stop')) return; 
			block.remove();
		});
		block.querySelectorAll('div[data-hint]').forEach((el) => self.showHint(el));
		block.querySelector('.time').value = config.default_profiler_time;
		tmp.querySelector('.fields').append(block);
		drag.addEvent(block.querySelector('.drag-icon'));
	});
	tmp.querySelector('.btn-run').addEventListener('click', function() {
		const blocks = tmp.querySelectorAll('.sql-time');
		const elems = Array.from(blocks).filter(el => !el.querySelector('label input').checked);
		const allSkip = Array.from(blocks).every(el => el.querySelector('label input').checked);
		const emptyQueries = elems.every(el => !el.querySelector('textarea').value.trim());
		
		if (!blocks.length || emptyQueries || allSkip) {
			return;
		}
		this.classList.toggle('stop');
		
		function runTimeQuery(i, n) {
			if (tmp.querySelector('.btn-run.stop')) {
				const active = tmp.querySelector('.sql-time.active');
				active ? active.classList.remove('active') : '';
				return;
			}
			if (!blocks[i]) {
				i = 0;
				n++;
			}
			
			const skip = blocks[i].querySelector('label input:checked');
			const query = blocks[i].querySelector('textarea').value;
			const time = blocks[i].querySelector('.time').value;
			
			if (skip || !query) {
				runTimeQuery(i + 1, n);
				return;
			}
			
			const active = tmp.querySelector('.sql-time.active');
			active ? active.classList.remove('active') : '';
			blocks[i].classList.add('active');
			
			let errorText;
			if (self.tab.querySelector('.sql-time .error')) {
				self.tab.querySelector('.sql-time .error').remove();
			}
			
			self.requestQueryTiming(query)
				.then(res => res.text())
				.then(text => {
					errorText = text;
					return JSON.parse(text);
				})
				.then(json => {
					const tmpLine = document.querySelector('.templates > .sql-time-line').cloneNode(true);
					tmpLine.querySelector('.num').textContent = `${n}`;
					tmpLine.querySelector('.ms').textContent = json.time;
					blocks[i].querySelector('.list').append(tmpLine);
					
					blocks[i].querySelector('.bar').style.width = '100%';
					blocks[i].querySelector('.bar').style.transitionDuration = `${time}ms`;
					
					setTimeout(function() {
						blocks[i].querySelector('.bar').style.width = '0';
						blocks[i].querySelector('.bar').style.transitionDuration = 'unset';
						runTimeQuery(i + 1, n);
					}, time);
				})
				.catch(err => {
					self.throwError(errorText, tmp.querySelector('.sql-time.active'));
					tmp.querySelector('.btn-run').click();
				});
		}
		if (!this.classList.contains('stop')) {
			blocks.forEach(block => block.querySelector('.list').innerHTML = '');
			runTimeQuery(0, 1);
		}
	});
	self.showInfo(tmp.querySelector('div[data-info]'));
	
	self.tab.querySelector('.content .table').after(tmp);
	tmp.querySelector('.btn-add').click();
}

Tab.prototype.keepAlive = function() {
	const self = this;
	const elem = self.tab.querySelector('.line .keep-alive');
	self.tab.classList.toggle('keep-alive');
	elem.classList.remove('not-alive');
	if (self.tab.classList.contains('keep-alive')) {
		self.keepAliveInterval = setInterval(() => {
			const obj = {
				body: self.requestListTables(),
				catchCallback: (err) => {
					elem.classList.add('not-alive');
					self.tab.classList.remove('keep-alive');
					clearInterval(self.keepAliveInterval);
				},
			};
			if (self.tab) {
				self.request(obj);
			} else {
				clearInterval(self.keepAliveInterval);
			}
		}, 30000);
	} else {
		clearInterval(self.keepAliveInterval);
	}
}

Tab.prototype.rightPanel = function() {
	const self = this;
	const tmp = document.querySelector('.templates .right-panel').cloneNode(true);
	tmp.querySelector('.open-profiler').addEventListener('click', () => self.tab.classList.toggle('profiler'));
	tmp.querySelector('.keep-alive').addEventListener('click', () => self.keepAlive());
	tmp.querySelector('.search-icon').addEventListener('click', () => {
		tmp.classList.add('open');
		tmp.querySelector('input').focus();
	});
	tmp.querySelector('.close-search').addEventListener('click', () => {
		tmp.classList.remove('open');
		const names = self.tab.querySelectorAll('tbody td:first-child');
		names.forEach(name => name.parentNode.classList.remove('close'));
		tmp.querySelector('input').value = '';
	});
	tmp.querySelector('input').addEventListener('keyup', function() {
		const str = this.value.toLowerCase();
		const names = self.tab.querySelectorAll('tbody td:first-child');
		names.forEach(name => name.parentNode.classList.remove('close'));
		names.forEach(name => {
			const str2 = name.textContent.toLowerCase();
			if (str2.indexOf(str) == -1) {
				name.parentNode.classList.add('close');
			}
		});
	});
	tmp.querySelectorAll('.icon[data-hint]').forEach(elem => self.showHint(elem));
	self.tab.querySelector('.table .line').appendChild(tmp);
}

Tab.prototype.clickTable = function(name, arg) {
	const self = this;
	const autoResize = self.tab.querySelector('.auto-resize input');
	const obj = {
		connection: self.connection,
		database: self.database,
		prevTab: self.tab,
		table: name,
		color: self.tab.dataset.color,
	};
	const newTab = new Tab(self.drag, obj);
	const obj2 = {
		body: {
			describe_table: true,
			table_name: newTab.table,
			query: newTab.sql,
			connection_name: newTab.connection,
			database_name: newTab.database,
			full_texts: app.default_full_texts ? 'true' : 'false',
		},
		callback: (res) => newTab.fillTables(res),
		forError: newTab.tab.querySelector('.content'),
	};
	newTab.request(obj2);
}

Tab.prototype.clickDB = function(name, arg) {
	const self = this;
	const obj = {
		connection: self.connection,
		database: name,
		prevTab: self.tab,
		newDB: true,
	};
	const newTab = new Tab(self.drag, obj);
	const obj2 = {
		body: newTab.requestListTables(),
		callback: (res) => newTab.listTables(res),
		forError: newTab.tab.querySelector('.content'),
	};
	newTab.request(obj2);
}

Tab.prototype.listTables = function(res) {
	const self = this;
	const table = self.createTable(res.tables);
	self.tab.querySelector('.db-name').textContent = self.database;
	table.querySelectorAll('tbody td:first-child').forEach(td => td.addEventListener('click', function() {
		self.clickTable(this.textContent);
	}));
	if (res.export_import) {
		self.import_limits = res.import_limits;
		self.tab.classList.add('open-export');
	}
	if (res.driver) {
		self.tab.classList.add(`driver-${res.driver}`);
	}
	if (res.views) {
		table.querySelectorAll('tbody td:first-child').forEach(td => {
			if (res.views.indexOf(td.textContent) != -1) {
				td.classList.add('view');
			}
		});
	}
	self.tab.classList.add('list', 'list-tb');
	self.tab.querySelector('.content').append(table);
	self.createProfiler();
	self.rightPanel();
	self.setWidth();
	self.scrollInto();
}

Tab.prototype.listDB = function(rows) {
	const self = this;
	self.tab.querySelector('.names').innerHTML = self.connection;
	const table = self.createTable(rows);
	table.querySelector('.block-name').textContent = app.translations['db-heading'];
	table.querySelector('.block-name').dataset.text = 'db-heading';
	table.querySelectorAll('tbody td:first-child').forEach(td => td.addEventListener('click', function() {
		self.clickDB(this.textContent);
	}));
	self.tab.classList.remove('connects', 'add-connection');
	self.tab.classList.add('list', 'list-db');
	
	if (self.tab.querySelector('.connect-block')) {
		self.tab.querySelector('.connect-block').remove();
	}
	if (self.tab.querySelector('.simplebar-content')) {
		self.tab.querySelector('.simplebar-content').append(table);
	} else {
		self.tab.querySelector('.content').append(table);
		new SimpleBar(self.tab.querySelector('.content'));
	}
}

Tab.prototype.getConnection = function(name) {
	const self = this;
	self.connection = name;
	self.tab.querySelector('.connection').value = name;
	const obj = {
		body: {connection_name: self.connection, list_db: true},
		callback: res => self.listDB(res.databases),
		forError: self.tab.querySelector('.connections'),
	};
	self.request(obj);
}

Tab.prototype.clickSubmit = function(e) {
	e.preventDefault();
	const self = this;
	const login = self.tab.querySelector('.login').value;
	const password = self.tab.querySelector('.password').value;
	const obj = {
		body: {
			add_connection: true,
			list_connections: true,
			login: login,
			password: password,
		},
		callback: (json) => {
			const name = json.connections[json.connections.length - 1];
			self.getConnection(name);
		},
		forError: self.tab.querySelector('.connect-block .form'),
	};
	self.request(obj);
}

Tab.prototype.fillConnections = function(res) {
	const self = this;
	self.tab.classList.add('connects');
	self.tab.querySelector('.content').append(document.querySelector('.templates .connect-block').cloneNode(true));
	self.tab.querySelector('.form').addEventListener('submit', (e) => self.clickSubmit(e));
	self.tab.querySelector('.new-connection').addEventListener('click', () => self.tab.classList.toggle('add-connection'));
	
	app.default_full_texts = res.default_full_texts;

	if (!res.connections.length) {
		self.tab.classList.add('add-connection');
	} else {
		self.tab.querySelector('.connections').classList.remove('close');
	}
	
	for (let i = 0; i < res.connections.length; i++) {
		const name = res.connections[i];
		const tmp = document.querySelector('.templates .tmp-line').cloneNode(true);
		tmp.querySelector('.name').textContent = name;
		tmp.querySelector('.name').addEventListener('click', () => self.getConnection(name));
		tmp.querySelector('.delete').addEventListener('click', function() {
			const obj = {
				body: {forget_connection: name},
				callback: res => {
					if (res.result == 'success') {
						tmp.remove();
						if (!self.tab.querySelector('.connections-list').children.length) {
							self.tab.classList.add('add-connection');
							self.tab.querySelector('.connections').classList.add('close');
						}
					}
				},
				forError: self.tab.querySelector('.connections'),
			};
			self.request(obj);
		});
		self.tab.querySelector('.connections-list').append(tmp);
	}
	
	self.tab.querySelectorAll('div[data-info]').forEach(elem => self.showInfo(elem));
	new SimpleBar(self.tab.querySelector('.content'));
	self.scrollInto();
}

Tab.prototype.processing = function(arg) {
	const self = this;
	if (arg) {
		self.tab.classList.add('processing');
		
		if (!document.body.classList.contains('flicker')) {
			document.body.classList.add('flicker');
			const favicons = ['favicon/sqlantern_1_favicon.png', 'favicon/sqlantern_1_favicon_frame_1.png'];
			
			let num = 0;
			app.interval = setInterval(() => {
				const favicon = document.querySelector('#favicon');
				favicon.href = favicons[num];
				num = num == 0 ? 1 : 0;
				if (!document.querySelector('.one-tab.processing')) {
					clearInterval(app.interval);
					document.body.classList.remove('flicker');
					document.querySelector('#favicon').href = favicons[0];
				}
			}, 500);
		}
	}
	else {
		self.tab.classList.remove('processing');
	}
}

Tab.prototype.scrollInto = function() {
	const self = this;
	const rect = self.tab.getBoundingClientRect();
	const scrollParent = self.tab.closest('.one-list');
	const scrollLeft = scrollParent.scrollLeft;
	if (rect.right > window.innerWidth) {
		const offset = scrollLeft + (rect.right - window.innerWidth);
		scrollParent.scrollLeft = offset + 10;
	}
}

Tab.prototype.duplicateTab = function() {
	const self = this;
	document.body.classList.add('restore');
	
	let obj = {};
	const obj2 = {
		connection: self.connection,
		database: self.database,
		table: self.table,
		color: self.tab.dataset.color,
		prevTab: self.tab,
		duplicate: true,
	};
	
	state.common(obj, self.tab);
	const newTab = new Tab(self.drag, obj2);
	if (self.tab.classList.contains('list-tb')) {
		state.listTb(obj, self.tab);
		restore.listTb(obj, newTab);
	}
	if (self.tab.classList.contains('query')) {
		state.listQuery(obj, self.tab);
		restore.listQuery(obj, newTab);
	}
	restore.common(obj, newTab);
	newTab.scrollInto();
	document.body.classList.remove('restore');
}

Tab.prototype.importCallback = function(res) {
	const self = this;
	const value = self.tab.querySelector('.import textarea').value;
	const formData = new FormData(self.tab.querySelector('.import'));
	formData.append('import_id', res.import_id);
	self.tab.querySelector('.import textarea').value = '';
	self.tab.querySelector('.import').classList.remove('show-textarea');

	const obj = {
		form: true,
		body: formData,
		forError: self.tab.querySelector('.import .for-error'),
	};
	self.request(obj);	
		
	const importTimeout = function(id) {
		setTimeout(() => {
			const error = self.tab.querySelector('.import .for-error .error');
			if (!self.tab || error) return;
			const obj = {
				body: {connection_name: self.connection, import_progress: true, import_id: res.import_id},
				callback: res => {
					self.tab.querySelector('.import .progress span').textContent = res.state;
					if (res.finished) {
						self.tab.querySelector('.import textarea').value = '';
						self.tab.querySelector('.import').classList.remove('show-textarea');
						self.tab.querySelector('.import .progress').classList.add('done');
					} else {
						importTimeout();
					}
				},
				forError: self.tab.querySelector('.import .for-error'),
			};
			self.request(obj);
		}, 2000);
	};
	importTimeout();
}

Tab.prototype.export = function() {
	const self = this;
	self.tab.classList.toggle('export');
	const block = document.querySelector('.templates .export-block');
	const width = +window.getComputedStyle(block).width.slice(0, -2)
	
	if (self.tab.classList.contains('export')) {
		const tmp = block.cloneNode(true);
		const importFile = tmp.querySelector('.import-file');
		tmp.querySelector('.import .limits').innerHTML = self.import_limits;
		tmp.querySelector('.export .rows input').value = config.export_break_rows;
		tmp.querySelectorAll('input[name="database_name"]').forEach(el => el.value = self.database);
		tmp.querySelectorAll('input[name="connection_name"]').forEach(el => el.value = self.connection);
		
		tmp.querySelectorAll('.switch-line > div').forEach((el, idx) => {
			el.addEventListener('click', () => {
				if (el.classList.contains('active')) return;
				tmp.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
				tmp.querySelectorAll('form')[idx].classList.add('active');
				el.classList.add('active');
			});
		});
		
		importFile.querySelector('input').addEventListener('change', function() {
			if (this.value) {
				const path = this.value.split('\\').pop().split('/');
				const size = this.files[0].size / (1024 * 1024);
				importFile.classList.add('open');
				importFile.querySelector('input[type="radio"]').checked = true;
				importFile.querySelector('.filename .name').textContent = `${path[path.length - 1]}, `;
				importFile.querySelector('.filename .size').textContent = size.toFixed(1) + 'M';
				tmp.querySelector('.import').classList.remove('show-textarea');
			} else {
				importFile.classList.remove('open');
				importFile.querySelector('input[type="radio"]').checked = false;
				importFile.querySelectorAll('.filename span').forEach(el => el.textContent = '');
			}
		});
		tmp.querySelector('.import-text input').addEventListener('change', () => {
			importFile.classList.remove('open');
			importFile.querySelector('input').value = '';
			tmp.querySelector('.import').classList.add('show-textarea');
			importFile.querySelectorAll('.filename span').forEach(el => el.textContent = '');
		});
		
		tmp.querySelector('.choose-tables input').addEventListener('change', () => {
			self.tab.querySelectorAll('table tr').forEach(tr => {
				const td = document.createElement('td');
				const box = document.querySelector('.templates .box').cloneNode(true);
				box.querySelector('input').checked = false;
				box.querySelector('input').name = 'tables[]';
				box.querySelector('input').value = tr.querySelector('td').textContent;
				td.append(box);
				tr.querySelector('td').after(td);
			});
			self.tab.querySelector('table thead .box input').addEventListener('change', function() {
				if (this.checked) {
					self.tab.querySelectorAll('table tbody tr:not(.close) .box input').forEach(el => el.checked = true);
				} else {
					self.tab.querySelectorAll('table tbody tr .box input').forEach(el => el.checked = false);
				}
			});
		});
		
		tmp.querySelector('.all-tables input').addEventListener('change', () => {
			self.tab.querySelectorAll('td .box').forEach(el => el.parentNode.remove());
		});
		
		tmp.querySelector('.btn-import').addEventListener('click', () => {
			self.tab.querySelector('.import .progress span').textContent = '';
			self.tab.querySelector('.import .progress').classList.remove('done');
			const obj = {
				body: {import_get_id: true, connection_name: self.connection},
				callback: res => self.importCallback(res),
				forError: tmp.querySelector('.import .for-error'),
			}
			self.request(obj);
		});
		
		tmp.querySelector('.btn-export').addEventListener('click', () => {
			tmp.querySelector('.export textarea').value = '';
			tmp.querySelector('.export').classList.remove('show-textarea');
			const form = tmp.querySelector('.export').cloneNode(true);
			const isTables = tmp.querySelector('.choose-tables input').checked;
			if (isTables) {
				let arr = [];
				self.tab.querySelectorAll('tbody td .box input:checked').forEach(el => {
					arr.push(el.closest('tr').querySelector('td').textContent);
				});
				form.append(document.querySelector('.templates .input-hidden').cloneNode());
				form.querySelector('.input-hidden').value = JSON.stringify(arr);
			}
			
			if (tmp.querySelector('.export .export-file input').checked) {
				form.action = config.backend;
				form.style.display = 'none';
				self.tab.append(form);
				form.submit();
				setTimeout(() => form.remove(), 1000);
			} else {
				const obj = {
					form: true,
					body: new FormData(form),
					catchCallback: res => {
						self.tab.querySelector('form.export').classList.add('show-textarea');
						self.tab.querySelector('form.export textarea').value = res;
					},
				};
				self.request(obj);
			}
		});
		
		self.tab.style.width = self.tab.offsetWidth + width + 'px';
		self.tab.querySelector('.cover').append(tmp);
	} else {
		self.tab.querySelector('.export-block').remove();
		self.tab.style.width = self.tab.offsetWidth - width + 'px';
		self.tab.querySelectorAll('td .box').forEach(el => el.parentNode.remove());
	}
}

Tab.prototype.showHint = function(elem) {
	let obj = {};
	elem.addEventListener('mouseenter', () => {
		elem.classList.add('hover');
		obj[elem] = setTimeout(() => {
			if (elem.classList.contains('hover')) {
				const div = document.createElement('div');
				div.classList.add('data-hint');
				div.innerHTML = app.hint[elem.dataset.hint];
				div.style.top = elem.offsetHeight + 10 + 'px';
				elem.append(div);
			}
		}, 1500);
	});
	['click', 'mouseleave'].forEach(event => {
		elem.addEventListener(event, () => {
			const hint = elem.querySelector('.data-hint');
			hint ? hint.remove() : '';
			obj[elem] ? clearTimeout(obj[elem]) : '';
			elem.classList.remove('hover');
		});
	});
}

Tab.prototype.showInfo = function(elem) {
	elem.querySelector('.info').addEventListener('mouseenter', () => {
		const div = document.createElement('div');
		div.classList.add('data-info');
		div.innerHTML = app.translations[elem.dataset.info];
		elem.append(div);
	});
	elem.querySelector('.info').addEventListener('mouseleave', () => {
		elem.querySelector('.data-info').remove();
	});
}

Tab.prototype.createTab = function() {
	let self = this;
	self.tab = document.querySelector('.templates .one-tab').cloneNode(true);
	app.tabs.push(self);
	self.tab.dataset.color = self.color || 0;
	self.tab.querySelector('.connection').value = self.connection;
	self.tab.querySelector('.duplicate').addEventListener('click', () => self.duplicateTab());
	self.tab.querySelector('.export').addEventListener('click', () => self.export());
	self.tab.querySelector('.set-width').addEventListener('click', function() { this.classList.toggle('active'); });
	self.tab.querySelector('.small-tab').addEventListener('click', () => {
		if (self.tab.classList.contains('query')) {
			const name = self.tab.querySelector('.custom-name span').textContent;
			self.tab.querySelector('.tb-name').dataset.name = name;
		}
		self.tab.classList.toggle('small');
	});
	self.tab.querySelector('.delete-tab').addEventListener('click', () => {
		const idx = app.tabs.findIndex(el => el.tab == self.tab);
		app.tabs.splice(idx, 1);
		self.tab.remove();
		
		if (!self.parent.querySelectorAll('.one-tab').length) {
			document.querySelector('.add-tab').click();
		}
		
		for (let key in self) {
			delete self[key];
		}
	});
	self.tab.querySelectorAll('.icon.set-width .width-block > div').forEach(el => {
		el.addEventListener('click', () => {
			if (!self.tab.classList.contains(el.className)) {
				self.tab.classList.remove('width-auto', 'width-25', 'width-50', 'width-100');
				self.tab.classList.add(el.className);
			}
		});
	});
	self.tab.querySelector('.icon.lock').addEventListener('click', () => {
		self.tab.classList.toggle('lock-tab');
		if (self.tab.classList.contains('lock-tab')) {
			self.tab.querySelector('.query-block textarea').disabled = true;
		} else {
			self.tab.querySelector('.query-block textarea').disabled = false;
		}
	});
	self.tab.querySelector('.icon.color').addEventListener('click', function() {
		this.classList.toggle('active');
		if (this.classList.contains('active')) {
			const tmp = document.querySelector('.templates .palette').cloneNode(true);
			const color = self.tab.getAttribute('data-color');
			tmp.querySelector(`.color-${color}`).style.order = '-1';
			tmp.querySelectorAll('div').forEach((el, idx) => {
				el.addEventListener('click', () => self.tab.dataset.color = idx);
			});
			self.tab.querySelector('.icon.color').append(tmp);
		} else {
			self.tab.querySelector('.palette').remove();
		}
	});
	self.tab.addEventListener('transitionend', (evt) => {
		if (evt.target.classList.contains('one-tab')) {
			self.scrollInto();
			const class1 = self.tab.classList.contains('query');
			const class2 = self.tab.classList.contains('small');
			if (class1 && !class2) {
				self.autoResize();
				self.checkScrollX();
			}
		}
	});
	self.tab.querySelectorAll('div[data-hint]').forEach(elem => self.showHint(elem));
	self.drag.addEvent(self.tab.querySelector('.drag-icon'));

	if (self.newConnect) {
		const obj = {
			body: {list_connections: true},
			callback: res => self.fillConnections(res),
			forError: self.tab.querySelector('.content'),
		}
		self.request(obj);
	}
	self.appendTab();
}

const panel = {
	init() {
		clearInputs();
		updateHeight();
	
		app.start_config = JSON.stringify(config);
		const itemName = `${config.prefix}.settings`;
		const obj = JSON.parse(localStorage.getItem(itemName)) || {};
		for (let key in obj) {
			config[key] = obj[key];
		}
		if (!config.default_extra_hints) {
			document.body.classList.add('no-hints');
		}
		
		panel.translation();
		
		if (config.styles) {
			config.styles.split(' ').forEach(name => {
				const link = document.createElement('link');
				link.href = `css/${name}.css`;
				link.rel = 'stylesheet';
				document.querySelector('head').append(link);
			});
		}
		document.querySelector('.logo').addEventListener('click', panel.logo);
		document.querySelector('.add-tab').addEventListener('click', panel.addTab);
		document.querySelector('.notepad').addEventListener('click', panel.notepad);
		document.querySelector('.settings').addEventListener('click', panel.settings);
		document.querySelector('.hide-tabs').addEventListener('click', panel.hideTabs);
		document.querySelector('.add-screen').addEventListener('click', panel.addScreen);
		document.querySelector('.scroll-into').addEventListener('click', panel.scrollInto);
		document.querySelector('.panel .states').addEventListener('click', panel.states);
		document.querySelector('.change-screen').addEventListener('click', panel.changeScreen);
		document.querySelector('.panel .up').addEventListener('click', panel.screenUp);
		document.querySelector('.panel .down').addEventListener('click', panel.screenDown);
		panel.addScreen();
	},
	
	translation() {
		const translation = `translations/${config.language}.json`;
		fetch(translation)
			.then(res => res.json())
			.then(res => {
				Object.assign(res['data-text'], res.js);
				app.translations = res.js;
				app.hint = res['data-hint'];
				document.querySelectorAll('[data-text]').forEach(el => {
					el.innerHTML = res['data-text'][el.dataset.text];
				});
				document.querySelectorAll('.panel > div[data-hint]').forEach(el => Tab.prototype.showHint(el));
				
				autoSave.restore();
			});
	},
	
	logo() {
		document.querySelector('.panel .logo').classList.toggle('active');
		if (!document.querySelector('.panel .logo.active')) {
			document.querySelector('.panel .tmp-common').remove();
			return;
		}
		document.querySelectorAll('.panel > div.active:not(.logo)').forEach(el => el.click());
		
		const tmp = document.querySelector('.templates .tmp-common').cloneNode(true);
		tmp.querySelector('.version').textContent = app.version;
		document.querySelector('.panel').append(tmp);
	},
	
	settings() {
		document.querySelector('.panel .settings').classList.toggle('active');
		if (!document.querySelector('.panel .settings.active')) {
			document.querySelector('.panel .tmp-settings').remove();
			return;
		}
		document.querySelectorAll('.panel > div.active:not(.settings)').forEach(el => el.click());
		
		const tmp = document.querySelector('.templates .tmp-settings').cloneNode(true);
		const itemName = `${config.prefix}.settings`;
		const elems = tmp.querySelectorAll('input:not([type="radio"]), .distinct textarea');
		for (let i = 0; i < elems.length; i++) {
			const elem = elems[i];
			const key = elem.name;
			elem.type == 'checkbox' ? elem.checked = config[key] : elem.value = config[key];
		}
		tmp.querySelector(`.lang input[value="${config.language}"]`).checked = true;
		
		const obj = {
			parent: tmp.querySelector('.handy-queries .list'),
			dragSelector: '.tmp-textarea',
			scrollParent: tmp,
		};
		const drag = new Drag(obj);
		
		const createLine = (query) => {
			const line = document.querySelector('.templates .tmp-textarea').cloneNode(true);
			const textarea = line.querySelector('textarea');
			line.querySelector('textarea').value = query || '';
			line.querySelectorAll('.edit, .confirm').forEach(elem => {
				elem.addEventListener('click', () => {
					line.classList.toggle('edit-line');
					if (line.classList.contains('edit-line')) {
						textarea.disabled = false;
						textarea.focus();
					} else {
						textarea.disabled = true;
						textarea.scrollTop = 0;
						textarea.style.removeProperty('height');
					}
				});
			});
			line.querySelector('.delete').addEventListener('click', () => line.remove());
			drag.addEvent(line.querySelector('.drag'));
			tmp.querySelector('.handy-queries .list').append(line);
			
			if (!query) {
				line.querySelector('.edit').click();
			}
		};
		
		config.handy_queries.forEach(el => createLine(el));
		
		tmp.querySelector('.btn-save').addEventListener('click', () => {
			const obj = JSON.parse(localStorage.getItem(itemName)) || {};
			const elems = tmp.querySelectorAll('input:not([type="radio"]), .lang input:checked, .distinct textarea');
			for (let i = 0; i < elems.length; i++) {
				const elem = elems[i];
				const key = elem.name;
				const value = elem.type == 'checkbox' ? elem.checked : elem.value;
				obj[key] = value;
				config[key] = value;
			}
			config.handy_queries.length = 0;
			tmp.querySelectorAll('.handy-queries textarea').forEach(el => {
				if (el.value) {
					config.handy_queries.push(el.value);
				}
			});
			obj.handy_queries = config.handy_queries;
			localStorage.setItem(itemName, JSON.stringify(obj));
			
			if (config.default_extra_hints) {
				document.body.classList.remove('no-hints');
			} else {
				document.body.classList.add('no-hints');
			}
			panel.translation();
			document.querySelector('.panel .settings').click();
		});
		
		tmp.querySelectorAll('.handy-queries .block, .distinct .block').forEach(el => {
			el.addEventListener('click', function(evt) {
				if (evt.target.classList.contains('btn-add')) return;
				el.closest('.block').classList.toggle('open');
			});
		});
		tmp.querySelector('.btn-add').addEventListener('click', () => createLine());
		tmp.querySelector('.btn-reset').addEventListener('click', () => {
			localStorage.removeItem(itemName);
			config = JSON.parse(app.start_config);
			document.querySelector('.panel .settings').click();
			panel.translation();
		});
		tmp.querySelectorAll('div[data-info]').forEach(elem => Tab.prototype.showInfo(elem));
		
		document.querySelector('.panel').append(tmp);
		new SimpleBar(tmp.querySelector('.cover'));
	},
	
	states() {
		document.querySelector('.panel .states').classList.toggle('active');
		if (!document.querySelector('.panel .states.active')) {
			document.querySelector('.panel .tmp-state').remove();
			return;
		} 
		document.querySelectorAll('.panel > div.active:not(.states)').forEach(el => el.click());
		
		let chosenState;
		const tmp = document.querySelector('.templates .tmp-state').cloneNode(true);
		const itemName = `${config.prefix}.states`;
		
		function list() {
			let arr = JSON.parse(localStorage.getItem(itemName));
			if (!arr) return;
			
			arr.forEach((el, i) => el.n = i);
			arr = arr.sort((a, b) => a.name > b.name ? 1 : -1);
			
			tmp.querySelectorAll('.list').forEach(el => el.innerHTML = '');
			for (let i = 0; i < arr.length; i++) {
				const date = new Date(arr[i].date).toLocaleDateString();
				const time = new Date(arr[i].date).toLocaleTimeString();
				const line = document.querySelector('.templates .tmp-line').cloneNode(true);
				line.querySelector('.key').value = arr[i].n;
				line.querySelector('.date').textContent = date;
				line.querySelector('.date').title = date + ' ' + time;
				line.querySelector('.name').textContent = arr[i].name;
				
				line.addEventListener('click', (e) => {
					if (e.target.closest('.delete') || line.classList.contains('active')) return;
					const length = document.querySelectorAll('.container .one-list').length;
					const length2 = document.querySelectorAll('.container .one-list .one-tab').length;
					const elem = document.querySelector('.container .one-list .one-tab.connects');
					if (length == 1 && length2 == 1 && elem) {
						restore.init(arr[i]);
						document.querySelector('.panel .states').click();
					} else {
						tmp.classList.add('confirm-restore');
						line.classList.add('active');
						chosenState = arr[i];
					}
				});
				line.querySelector('.delete').addEventListener('click', () => {
					tmp.classList.add('confirm-delete');
					line.classList.add('active');
				});
				
				const line2 = line.cloneNode(true);
				line2.querySelector('.name').addEventListener('click', () => {
					tmp.classList.add('confirm-rewrite');
					line2.classList.add('active');
				});
				
				tmp.querySelector('.restore .list').append(line);
				tmp.querySelector('.save .list').append(line2);
			}
		}
		
		function saveState(name, idx) {
			const newState = state.save(name);
			const states = JSON.parse(localStorage.getItem(itemName)) || [];
			if (idx >= 0) {
				newState.name = states[idx].name;
				states[idx] = newState;
			} else {
				states.unshift(newState);
			}
			localStorage.setItem(itemName, JSON.stringify(states));
		};
		
		tmp.querySelector('.confirm-1 .btn-yes').addEventListener('click', () => {
			restore.init(chosenState);
			tmp.querySelector('.confirm-1 .btn-no').click();
			document.querySelector('.panel .states').click();
		});
		tmp.querySelector('.confirm-1 .btn-no').addEventListener('click', () => {
			tmp.classList.remove('confirm-restore');
			tmp.querySelector('.tmp-line.active').classList.remove('active');
		});
		
		tmp.querySelector('.confirm-2 .btn-yes').addEventListener('click', () => {
			const idx = +tmp.querySelector('.restore .tmp-line.active .key').value;
			let arr = JSON.parse(localStorage.getItem(itemName));
			arr.splice(idx, 1);
			localStorage.setItem(itemName, JSON.stringify(arr));
			tmp.querySelector('.confirm-2 .btn-no').click();
			list();
		});
		tmp.querySelector('.confirm-2 .btn-no').addEventListener('click', () => {
			tmp.classList.remove('confirm-delete');
			tmp.querySelector('.tmp-line.active').classList.remove('active');
		});
		
		tmp.querySelector('.confirm-3 .btn-yes').addEventListener('click', () => {
			const idx = +tmp.querySelector('.save .tmp-line.active .key').value;
			saveState(name, idx);
			document.querySelector('.panel .states').click();
			//tmp.querySelector('.confirm-3 .btn-no').click();
			//list();
		});
		tmp.querySelector('.confirm-3 .btn-no').addEventListener('click', () => {
			tmp.classList.remove('confirm-rewrite');
			tmp.querySelector('.tmp-line.active').classList.remove('active');
		});
		
		tmp.querySelector('.confirm-4 .btn-ok').addEventListener('click', () => {
			tmp.classList.remove('confirm-warning');
		});

		tmp.querySelector('.save input').addEventListener('keyup', function() {
			const str = this.value.toLowerCase();
			tmp.querySelectorAll('.tmp-line.close').forEach(el => el.classList.remove('close'));
			tmp.querySelectorAll('.tmp-line').forEach(el => {
				const str2 = el.querySelector('.name').textContent.toLowerCase();
				if (str2.indexOf(str) == -1) {
					el.classList.add('close');
				}
			});
		});
		tmp.querySelectorAll('.switch-line > div').forEach((el, i) => {
			el.addEventListener('click', () => {
				if (el.classList.contains('active')) return;
				tmp.querySelectorAll('.active').forEach(el => el.classList.remove('active'));
				el.classList.add('active');
				tmp.querySelectorAll('.simplebar-content > div')[i].classList.add('active');
				tmp.querySelector('.input-save input').focus();
			});
		});
		tmp.querySelector('.btn-save').addEventListener('click', () => {
			const input = tmp.querySelector('.input-save input');
			const lines = tmp.querySelectorAll('.save .tmp-line:not(.close) .name');
			for (let i = 0; i < lines.length; i++) {
				if (lines[i].textContent == input.value) {
					lines[i].click();
					return;
				}
			};
			if (input.value.length) {
				saveState(input.value);
				input.value = '';
				document.querySelector('.panel .states').click();
				//list();
			} else {
				tmp.classList.add('confirm-warning');
			}
		});
		tmp.querySelector('.input-save input').addEventListener('keyup', (e) => {
			if (e.key == 'Enter') {
				tmp.querySelector('.btn-save').click();
			}
		});
		
		list();
		document.querySelector('.panel').append(tmp);
		new SimpleBar(tmp.querySelector('.cover'));
	},
	
	addTab() {
		const idx = app.curScreen;
		document.querySelectorAll('.main .one-list')[idx].querySelector('.add-new').click();
	},
	
	listEvents(tmp, drag) {
		tmp.addEventListener('scroll', function(e) {
			const idx = app.curScreen;
			if (app.scroll[idx][0] == true) {
				app.scroll[idx][1] = tmp.scrollLeft;
				document.querySelectorAll('.main .one-list')[idx].classList.remove('scroll');
			}
		});
		tmp.querySelector('.add-new').addEventListener('click', () => {
			new Tab(drag, {newConnect: true});
			app.scroll.push([]);
		});
	},
	
	addScreen() {
		const container = document.querySelector('.main .container');
		const tmp = document.querySelector('.templates .one-list').cloneNode(true);
		app.curScreen = container.children.length;
		const obj = {
			parent: tmp.querySelector('.tabs-list'),
			dragSelector: '.one-tab',
			scrollParent: tmp,
		};
		const drag = new Drag(obj);
		panel.listEvents(tmp, drag);
		
		tmp.querySelector('.add-new').click();
		container.append(tmp);
		document.querySelector('html').scrollTop = window.innerHeight * app.curScreen;
		panel.checkActive();
	},
	
	checkActive() {
		document.body.classList.remove('inactive-down', 'inactive-up');
		const n = document.querySelectorAll('.main .container .one-list').length - 1;
		if (app.curScreen == n) {
			document.body.classList.add('inactive-down');
		}
		if (app.curScreen == 0) {
			document.body.classList.add('inactive-up');
		}
	},
	
	screenUp() {
		app.curScreen = app.curScreen > 0 ? app.curScreen - 1 : 0;
		document.querySelector('html').scrollTop = window.innerHeight * app.curScreen;
		panel.checkActive();
	},
	
	screenDown() {
		const n = document.querySelectorAll('.main .container .one-list').length - 1;
		app.curScreen = app.curScreen < n ? app.curScreen + 1 : app.curScreen;
		document.querySelector('html').scrollTop = window.innerHeight * app.curScreen;
		panel.checkActive();
	},
	
	changeScreen() {
		const popup = document.querySelector('.templates .pop-up-screens').cloneNode(true);
		const icon = document.querySelector('.icon.delete-tab').cloneNode('true');
		const tmp = document.querySelector('.main .container').cloneNode(true);
		tmp.classList.add('drag-parent');
		popup.append(icon);
		popup.querySelector('.cover').append(tmp);
		document.body.append(popup);
		panel.calcScale();
		
		popup.querySelectorAll('.one-list').forEach((list, idx) => {
			const panel = document.querySelector('.templates .side-panel').cloneNode(true);
			panel.querySelectorAll('.icon').forEach(el => Tab.prototype.showHint(el));
			list.append(panel);
		});

		popup.querySelectorAll('.one-tab').forEach((elem, idx) => elem.dataset.idx = idx);
		panel.screenEvents(popup);
		
		const obj = {
			parent: popup.querySelector('.container'),
			dragSelector: '.one-tab',
			dragSelectorClick: '.one-tab .top-line',
			callback: (res) => panel.dragCallback(res),
		};
		new Drag(obj);
		document.body.classList.add('pop-up-active');
		app.matrix.state = true;
	},
	
	calcScale() {
		const tmp = document.querySelector('body > .pop-up-screens .container');
		const style = document.querySelector(':root').style;
		style.setProperty('--matrix-scale', 1);
		const widthRatio = window.innerWidth / tmp.offsetWidth;
		const heightRatio = window.innerHeight / tmp.offsetHeight;
		const min = Math.min(widthRatio, heightRatio) * 0.97;
		style.setProperty('--matrix-scale', min);
		app.matrix.value = min;
	},
	
	dragCallback(res) {
		const elems = document.querySelectorAll('.main .one-tab');
		const next = res.elem.nextElementSibling;
		const prev = res.elem.previousElementSibling;
		if (!next && !prev) return;
		if (next) {
			const idx = next.dataset.idx;
			elems[idx].before(elems[res.from]);
		} else {
			const idx = prev.dataset.idx;
			elems[idx].after(elems[res.from]);
		}
		const icon = elems[res.from].querySelector('.drag-icon');
		const cloneIcon = icon.cloneNode(true);
		const parent = elems[res.from].closest('.tabs-list');
		const from = app.tabs.findIndex(tab => tab.parent == parent);
		const to = app.tabs.findIndex(elem => elem.tab == elems[res.from]);
		app.tabs[to].drag = app.tabs[from].drag;
		app.tabs[to].parent = app.tabs[from].parent;
		app.tabs[to].drag.addEvent(cloneIcon);
		icon.remove();
		elems[res.from].querySelector('.top-line .delete-tab').before(cloneIcon);
		document.querySelectorAll('body > .pop-up-screens .one-tab').forEach((elem, idx) => elem.dataset.idx = idx);
		
		setTimeout(() => {
			const simpleBar = elems[res.from].querySelector('.content');
			simpleBar ? new SimpleBar(simpleBar) : '';
		}, 0);
	},
	
	screenEvents(popup) {
		const findIdx = (el) => {
			const list = el.closest('.one-list');
			const i = Array.from(popup.querySelectorAll('.one-list')).findIndex(elem => elem == list);
			return i;
		};
		const closeIcon = popup.querySelector('.cover + .icon')
		popup.addEventListener('mouseup', function(e) {
			if (popup.querySelector('.one-tab.current')) return;
			const classes = ['pop-up', 'container', 'cover'];
			const isClass = classes.find(el => e.target.classList.contains(el));
			if (isClass) {
				closeIcon.click();
			}
		});
		closeIcon.addEventListener('click', function(e) {
			app.matrix.state = false;
			document.body.classList.remove('pop-up-active');
			popup.remove();
			
			const arr = Array.from(document.querySelectorAll('.main .container .one-list'));
			app.curScreen = arr.findIndex(el => el.getBoundingClientRect().top >= 0);
			panel.checkActive();
		});
		popup.querySelectorAll('.side-panel').forEach(elem => {
			elem.querySelector('.delete-tab').addEventListener('click', function() {
				const list = this.closest('.one-list');
				const tmp = document.querySelector('.templates .pop-up-confirm').cloneNode(true);
				tmp.querySelector('.btn-confirm').addEventListener('click', () => {
					const idx = findIdx(elem);
					const list2 = document.querySelectorAll('.main .one-list')[idx];
					list2.querySelectorAll('.one-tab').forEach(tab => {
						const i = app.tabs.findIndex(el => el.tab == tab);
						for (let key in app.tabs[i]) {
							delete app.tabs[i][key];
						}
						app.tabs.splice(i, 1);
					});
					list.remove();
					list2.remove();
					if (popup.querySelectorAll('.one-list').length) {
						panel.calcScale();
					} else {
						closeIcon.click();
						document.querySelector('.add-screen').click();
					}
				});
				tmp.querySelector('.btn-cancel').addEventListener('click', () => tmp.remove());
				list.append(tmp);
			});
			elem.querySelector('.icon.color .color-box').addEventListener('click', function() {
				const cur = this;
				cur.classList.toggle('active');
				const list = cur.closest('.one-list');
				if (cur.classList.contains('active')) {
					const tmp = document.querySelector('.templates .palette').cloneNode(true);
					tmp.querySelectorAll('.color-5, .color-6').forEach(el => el.remove());
					const color = list.getAttribute('data-color');
					const idx = findIdx(elem);
					tmp.querySelector(`.color-${color}`).style.order = '-1';
					tmp.querySelectorAll('div').forEach((el, i) => {
						el.addEventListener('click', function() {
							list.dataset.color = i;
							document.querySelectorAll('.one-list')[idx].dataset.color = i;
							cur.classList.remove('active');
							tmp.remove();
						});
					});
					list.querySelector('.side-panel .icon.color').append(tmp);
				} else {
					list.querySelector('.side-panel .palette').remove();
				}
			});
		});
		popup.querySelectorAll('.one-list').forEach(list => {
			list.addEventListener('mouseup', function(e) {
				if (popup.querySelector('.one-tab.current')) return;
				const isClass = e.target.classList.contains('tabs-list') || e.target.classList.contains('one-list');
				if (isClass) {
					const idx = findIdx(list);
					document.querySelector('html').scrollTop = window.innerHeight * idx;
					closeIcon.click();
				}
			});
		});
		popup.querySelectorAll('.one-tab .processing').forEach((el, i) => {
			el.addEventListener('click', () => {
				const idx = findIdx(el);
				const arr = Array.from(popup.querySelectorAll('.one-list')[idx].querySelectorAll('.one-tab'));
				const tabIdx = arr.findIndex(elem => elem == el.closest('.one-tab'));
				const tab = document.querySelectorAll('.main .one-list')[idx].querySelectorAll('.one-tab')[tabIdx];
				const parent = tab.closest('.one-list');
				parent.scrollLeft = tab.offsetLeft;
				document.querySelector('html').scrollTop = window.innerHeight * idx;
				closeIcon.click();
			});
		});
	},
	
	hideTabs() {
		const idx = app.curScreen;
		document.querySelectorAll('.main .one-list')[idx].querySelectorAll('.one-tab:not(.connects)').forEach(tab => tab.classList.add('small'));
	},
	
	scrollInto() {
		const idx = app.curScreen;
		const parent = document.querySelectorAll('.main .one-list')[idx];
		parent.classList.toggle('scroll');
		if (parent.classList.contains('scroll')) {
			const listRight = parent.querySelector('.one-tab:last-child').getBoundingClientRect().right
			const scrollLeft = parent.scrollLeft;
			if (listRight > window.innerWidth) {
				app.scroll[idx][1] = parent.scrollLeft;
				const diff = listRight - window.innerWidth;
				app.scroll[idx][0] = false;
				parent.scrollLeft = scrollLeft + diff + 10;
				setTimeout(() => {
					app.scroll[idx][0] = true;
				}, 500);
			}
		} else {
			parent.scrollLeft = app.scroll[idx][1];
		}
	},
	
	notepad() {
		document.querySelector('.panel .notepad').classList.toggle('open');
		if (!document.querySelector('.panel .notepad.open')) {
			document.querySelector('body > .notepad-block').remove();
			return;
		}
		
		const tmp = document.querySelector('.templates .notepad-block').cloneNode(true);
		const itemName = `${config.prefix}.notepad`;
		const text = localStorage.getItem(itemName);
		tmp.querySelector('textarea').value = text;
		let top;
		let left;
		tmp.addEventListener('mousedown', function(e) {
			if (e.target.classList.contains('textarea')) return;
			let elTop = tmp.getBoundingClientRect().top;
			let elLeft = tmp.getBoundingClientRect().left;
			top = e.pageY - elTop;
			left = e.pageX - elLeft;
		});
		document.body.addEventListener('mousemove', function(e) {
			if (!top) return;
			let topShift = e.pageY - top;
			let leftShift = e.pageX - left;
			tmp.style.top = topShift + 'px';
			tmp.style.left = leftShift + 'px';
		});
		document.body.addEventListener('mouseup', function(e) {
			if (!top) return;
			let elLeft = tmp.getBoundingClientRect().left;
			let elTop = tmp.getBoundingClientRect().top;
			tmp.style.top = elTop + 'px';
			tmp.style.left = elLeft + 'px';
			top = 0;
			left = 0;
		});
		tmp.querySelector('textarea').addEventListener('keydown', (e) => {
			if (e.key == 'Tab') {
				e.preventDefault();
				tmp.querySelector('textarea').setRangeText(
					'\u0009',
					tmp.querySelector('textarea').selectionStart,
					tmp.querySelector('textarea').selectionStart,
					'end'
				)
			}
		});
		tmp.querySelector('.btn-close').addEventListener('click', panel.notepad);
		tmp.querySelector('.btn-save').addEventListener('click', () => {
			const text = tmp.querySelector('textarea').value;
			localStorage.setItem(itemName, text);
		});
		tmp.querySelector('.btn-save-close').addEventListener('click', () => {
			tmp.querySelector('.btn-save').click();
			tmp.querySelector('.btn-close').click();
		});
		document.body.append(tmp);
		tmp.querySelector('textarea').focus();
	},
};

const state = {
	save(name, idx) {
		let main = {};
		main.data = [];
		document.querySelectorAll('.main .container .one-list').forEach(list => {
			let tabs = [];
			list.querySelectorAll('.one-tab').forEach(tab => {
				let obj = {};
				state.common(obj, tab);
				if (tab.classList.contains('connects')) {
					state.listCn(obj, tab);
				}
				
				if (tab.classList.contains('list-db')) {
					state.listDB(obj, tab);
				}
				
				if (tab.classList.contains('list-tb')) {
					state.listTb(obj, tab);
				}
				
				if (tab.classList.contains('query')) {
					state.listQuery(obj, tab);
				}
				tabs.push(obj);
			});
			main.data.push(tabs);
		});
		main.name = name;
		main.date = new Date().getTime();
		main.idx = app.curScreen;
		main.version = '1.9';
		main.scroll_left = [];
		main.color = [];
		document.querySelectorAll('.main .container .one-list').forEach(el => main.scroll_left.push(el.scrollLeft));
		document.querySelectorAll('.main .container .one-list').forEach(el => main.color.push(el.dataset.color));
		return main;
	},
	
	common(obj, tab) {
		obj.connection = tab.querySelector('.connection').value;
		obj.color = tab.getAttribute('data-color');
		obj.width_class = tab.className.split(' ').filter(e => e.substr(0, 5) == 'width')[0];
		tab.classList.contains('small') ? obj.small_class = true : '';
		tab.querySelector('.icon.color.active') ? obj.color_active_class = true : '';
		tab.querySelector('.icon.set-width.active') ? obj.width_active_class = true : '';

		if (tab.querySelector('.simplebar-content-wrapper')) {
			obj.scroll_top = tab.querySelector('.simplebar-content-wrapper').scrollTop;
		}
		
		if (tab.querySelector('.error')) {
			obj.errors = [];
			tab.querySelectorAll('.error').forEach(el => {
				let tem = {};
				tem.text = el.querySelector('.error-text').innerHTML;
				tem.selector = '.' + el.parentNode.className.split(' ').join('.');
				obj.errors.push(tem);
			});
		}
	},
	
	listCn(obj, tab) {
		obj.connections = [];
		tab.querySelectorAll('.tmp-line .name').forEach(el => obj.connections.push(el.textContent));
		tab.classList.contains('add-connection') ? obj.add_connection_class = true : '';
	},
	
	listDB(obj, tab) {
		obj.databases = [];
		tab.querySelectorAll('table tbody tr').forEach(tr => {
			line = {};
			tr.querySelectorAll('td').forEach((td, e) => {
				const name = tab.querySelector(`table thead td:nth-child(${e + 1})`).textContent;
				line[name] = td.textContent;
			});
			obj.databases.push(line);
		});
	},
	
	listTb(obj, tab) {
		obj.db_name = tab.querySelector('.db-name').textContent;
		tab.classList.contains('profiler') ? obj.profiler_class = true : '';
		tab.classList.contains('keep-alive') ? obj.keep_alive_class = true : '';
		tab.querySelector('.keep-alive.not-alive') ? obj.keep_not_alive_class = true : '';
		
		const driver_class = tab.className.split(' ').filter(e => e.substr(0, 6) == 'driver')[0];
		driver_class ? obj.driver_class = driver_class : '';
		
		if (tab.classList.contains('open-export')) {
			obj.open_export_class = true;
			const idx = app.tabs.findIndex(el => el.tab == tab);
			obj.import_limits = app.tabs[idx].import_limits;
		}
		
		obj.tables = [];
		tab.querySelectorAll('.table tbody tr').forEach(tr => {
			line = {};
			tr.querySelectorAll('td').forEach((td, e) => {
				const name = tab.querySelector(`.table thead td:nth-child(${e + 1})`).textContent;
				line[name] = td.textContent;
			});
			obj.tables.push(line);
		});
		
		obj.profiler_fields = [];
		tab.querySelectorAll('.sql-time').forEach(el => {
			let field = {};
			field.time = el.querySelector('.time').value;
			field.skip = el.querySelector('.icons-group input').checked;
			field.query = el.querySelector('textarea').value;
			field.time_line = [];
			el.querySelectorAll('.list > .sql-time-line').forEach(line => {
				field.time_line.push(line.querySelector('.ms').textContent);
			});
			el.classList.contains('active') ? field.active = true : '';
			obj.profiler_fields.push(field);
		});
		
		if (tab.querySelector('.right-panel').classList.contains('open')) {
			obj.search = tab.querySelector('.search input').value;
		}
		
		if (tab.classList.contains('export')) {
			obj.export_class = true;
			tab.querySelector('form.export.active') ? obj.export_active_class = true : '';
			tab.querySelector('form.export.show-textarea') ? obj.export_show_textarea_class = true : '';
			tab.querySelector('form.import.show-textarea') ? obj.import_show_textarea_class = true : '';
			
			obj.export_fields = {
				what: tab.querySelector('form.export input[name="what"]:checked').value,
				format: tab.querySelector('form.export input[name="format"]:checked').value,
				transaction: tab.querySelector('form.export input[name="transaction"]:checked').value,
				rows: tab.querySelector('form.export .rows input').value,
				textarea: tab.querySelector('form.export textarea').value,
			};
			if (tab.querySelector('form.export .choose-tables input').checked) {
				obj.export_fields.tables = [];
				tab.querySelectorAll('.table .box input:checked').forEach(input => {
					obj.export_fields.tables.push(input.value);
				});
			}
			
			const textValue = tab.querySelector('form.import textarea').value;
			textValue ? obj.import_textarea = textValue : '';
			
			const progressText = tab.querySelector('form.import .progress span').textContent;
			progressText ? obj.import_progress = progressText : '';
			
			tab.querySelector('form.import .progress.done') ? obj.import_finished_class = true : '';
			tab.querySelector('form.import .import-text input').checked ? obj.import_text = true : '';
		}
	},
	
	listQuery(obj, tab) {
		obj.db_name = tab.querySelector('.db-name').textContent;
		obj.tb_name = tab.querySelector('.tb-name').textContent;
		obj.run_time = tab.querySelector('.query-block .time').value;
		obj.sql_query = tab.querySelector('.query-block textarea').value;
		obj.custom_name = tab.querySelector('.custom-name span').textContent;
		obj.custom_name_input = tab.querySelector('.custom-name input').value;
		obj.num_rows = tab.querySelector('.table.rows .num-rows').textContent;
		
		tab.querySelector('.full-text input').checked ? obj.full_text = true : '';
		tab.classList.contains('with-time') ? obj.with_time_class = true : '';
		tab.classList.contains('lock-tab') ? obj.lock_class = true : '';
		tab.querySelector('.table.rows.close') ? obj.rows_class = true : '';
		tab.querySelector('.table.indexes.close') ? obj.indexes_class = true : '';
		tab.querySelector('.table.structure.close') ? obj.structure_class = true : '';
		tab.querySelector('.icon.tmp-queries.open') ? obj.tmp_queries_class = true : '';
		tab.querySelector('.custom-name.active') ? obj.custom_name_class = true : '';
		tab.querySelector('.table.rows .block-name.close') ? obj.block_rows_class = true : '';
		
		if (tab.querySelector('.auto-resize input').checked) {
			obj.auto_height = true;
		} else {
			obj.auto_height = false;
			obj.textarea_scroll_top = document.querySelector('.query-block textarea').scrollTop;
		}

		tab.querySelectorAll('.query-block .block-name').forEach((el, i) => {
			if (el.classList.contains('active')) {
				obj.block_class = i + 1;
			}
		});
		
		if (tab.classList.contains('show-scroll')) {
			obj.scroll_bar = true;
			obj.bar_shift = tab.querySelector('.table.rows .bar').style.left.slice(0, -2);
			obj.table_shift = tab.querySelector('.table.rows table').style.left.slice(0, -2);
		}
	
		obj.structure = [];		
		tab.querySelectorAll('.table.structure tbody tr').forEach(tr => {
			line = {};
			tr.querySelectorAll('td:not(:first-child):not(:nth-last-child(-n+2))').forEach((td, e) => {
				const name = tab.querySelector(`.table.structure thead td:nth-child(${e + 2})`).textContent;
				line[name] = td.textContent;
				if (td.classList.contains('null')) {
					line[name] = null;
				}
			});
			obj.structure.push(line);
		});
		
		if (tab.querySelectorAll('.table.indexes tbody tr').length) {
			obj.indexes = [];
			tab.querySelectorAll('.table.indexes tbody tr').forEach(tr => {
				line = {};
				tr.querySelectorAll('td').forEach((td, e) => {
					const name = tab.querySelector(`.table.indexes thead td:nth-child(${e + 1})`).textContent;
					line[name] = td.textContent;
				});
				obj.indexes.push(line);
			});
		}
		
		if (tab.querySelectorAll('.table.rows tbody tr').length) {
			obj.rows = [];
			tab.querySelectorAll('.table.rows tbody tr').forEach(tr => {
				line = {};
				tr.querySelectorAll('td').forEach((td, e) => {
					const name = tab.querySelector(`.table.rows thead td:nth-child(${e + 1})`).textContent.slice(0, -1);
					line[name] = td.textContent;
					if (td.classList.contains('null')) {
						line[name] = null;
					}
					if (td.classList.contains('object')) {
						line[name] = {type: 'blob'};
					}
				});
				obj.rows.push(line);
			});
		}
		
		obj.structure_checked = [];
		obj.structure_conditions = {};
		tab.querySelectorAll('.table.structure tbody tr').forEach((tr, e) => {
			const value = tr.querySelector('td:last-child .input-text').value;
			if (value) {
				obj.structure_conditions[e + 1] = value;
			}
			if (tr.querySelector('.box input:checked')) {
				obj.structure_checked.push(e + 1);
			}
		});
		
		const idx = app.tabs.findIndex(el => el.tab == tab);
		obj.history = [...app.tabs[idx].history];
		
		if (tab.querySelector('.table.rows').classList.contains('columns')) {
			obj.columns = [...app.tabs[idx].columns];
			tab.querySelector('.table.rows.show-columns') ? obj.columns_class = true : '';
		}

		if (tab.querySelector('.cur-page')) {
			obj.cur_page = tab.querySelector('.cur-page').value;
			obj.num_pages = tab.querySelector('.num-pages span').textContent;
		}
	},
};

const restore = {
	init(res) {
		document.body.classList.add('restore');
		const arr = res.data;
		app.tabs.forEach(tab => Object.keys(tab).forEach(prop => delete tab[prop]));
		app.tabs.length = 0;
		app.scroll.length = 0;
		document.querySelector('.main .container').innerHTML = '';
		for (let i = 0; i < arr.length; i++) {
			const tmp = document.querySelector('.templates .one-list').cloneNode(true);
			document.querySelector('.main .container').append(tmp);
			const obj = {
				parent: tmp.querySelector('.tabs-list'),
				dragSelector: '.one-tab',
				scrollParent: tmp,
			};
			const drag = new Drag(obj);
			const tabs = arr[i];
			panel.listEvents(tmp, drag);
			app.scroll.push([]);
			
			for (let j = 0; j < tabs.length; j++) {
				const obj2 = {
					connection: tabs[j].connection,
					database: tabs[j].db_name,
					table: tabs[j].tb_name,
					color: tabs[j].color,
				}
				const newTab = new Tab(drag, obj2);
				if (tabs[j].connections) {
					restore.listCn(tabs[j], newTab);
				}
				
				if (tabs[j].databases) {
					newTab.listDB(tabs[j].databases);
				}
				
				if (tabs[j].tables) {
					restore.listTb(tabs[j], newTab);
				}
				
				if (tabs[j].structure) {
					restore.listQuery(tabs[j], newTab);
				}
				restore.common(tabs[j], newTab);
			}
		}
		app.curScreen = res.idx;
		document.querySelector('html').scrollTop = window.innerHeight * res.idx;
		document.querySelectorAll('.main .container .one-list').forEach((el, i) => el.scrollLeft = res.scroll_left[i]);
		document.querySelectorAll('.main .container .one-list').forEach((el, i) => el.dataset.color = res.color[i]);
		document.body.classList.remove('restore');
		panel.checkActive();
	},
	
	common(obj, newTab) {
		newTab.tab.classList.remove('width-auto');
		newTab.tab.classList.add(obj.width_class);
	
		if (newTab.tab.querySelector('.simplebar-content-wrapper')) {
			newTab.tab.querySelector('.simplebar-content-wrapper').scrollTop = obj.scroll_top;
		}
		if (obj.errors) {
			obj.errors.forEach(el => newTab.throwError(el.text, el.selector));
		}
		if (obj.color_active_class) {
			newTab.tab.querySelector('.icon.color').click();
		}
		if (obj.width_active_class) {
			newTab.tab.querySelector('.icon.set-width').classList.add('active');
		}
		if (obj.small_class) {
			newTab.tab.classList.add('small');
			if (newTab.tab.classList.contains('query')) {
				newTab.tab.querySelector('.tb-name').dataset.name = obj.custom_name;
			}
		}
		if (newTab.tab.querySelector('.table.rows.columns')) {
			newTab.checkTableWidth();
		}
		if (obj.scroll_bar) {
			newTab.checkScrollX();
			newTab.tab.querySelector('.table.rows .bar').style.left = obj.bar_shift + 'px';
			newTab.tab.querySelector('.table.rows table').style.left = obj.table_shift + 'px';
		}
	},
	
	listCn(obj, newTab) {
		newTab.fillConnections(obj);
		if (obj.add_connection_class) {
			newTab.tab.classList.add('add-connection');
		}
	},
	
	listTb(obj, newTab) {
		if (obj.open_export_class) {
			newTab.tab.classList.add('open-export');
			newTab.import_limits = obj.import_limits;
		}
		newTab.listTables(obj);
		
		if (obj.keep_alive_class) {
			newTab.tab.querySelector('.icon.keep-alive').click();
		}
		if (obj.keep_not_alive_class) {
			newTab.tab.querySelector('.icon.keep-alive').classList.add('not-alive');
		}
		if (obj.profiler_class) {
			newTab.tab.classList.add('profiler');
		}
		if (obj.driver_class) {
			newTab.tab.classList.add(obj.driver_class);
		}
		if (obj.search != undefined) {
			newTab.tab.querySelector('.right-panel').classList.add('open');
			newTab.tab.querySelector('.search input').value = obj.search;
			newTab.tab.querySelector('.search input').dispatchEvent(new Event('keyup'));
		}
		
		if (obj.export_class) {
			newTab.export();
			if (obj.export_active_class) {
				newTab.tab.querySelector('.export-block .switch-line div').click();
			} else {
				newTab.tab.querySelector('.export-block .switch-line div:last-child').click();
			}
			
			if (obj.export_show_textarea_class) {
				newTab.tab.querySelector('form.export').classList.add('show-textarea');
			}
			
			const block = newTab.tab.querySelector('.export-block .export');
			block.querySelector(`input[name="what"][value="${obj.export_fields.what}"]`).checked = true;
			block.querySelector(`input[name="format"][value="${obj.export_fields.format}"]`).checked = true;
			block.querySelector(`input[name="transaction"][value="${obj.export_fields.transaction}"]`).checked = true;
			block.querySelector('.rows input').value = obj.export_fields.rows;
			block.querySelector('textarea').value = obj.export_fields.textarea;
			
			if (obj.export_fields.tables) {
				block.querySelector('.choose-tables input').checked = true;
				block.querySelector('.choose-tables input').dispatchEvent(new Event('change'));
				obj.export_fields.tables.forEach(name => {
					newTab.tab.querySelector(`table .box input[value="${name}"]`).checked = true;
				});
			}
			
			if (obj.import_show_textarea_class) {
				newTab.tab.querySelector('form.import').classList.add('show-textarea');
			}
			if (obj.import_text) {
				newTab.tab.querySelector('form.import .import-text input').checked = true;
			}
			if (obj.import_textarea) {
				newTab.tab.querySelector('form.import textarea').value = obj.import_textarea;
			}
			if (obj.import_finished_class) {
				newTab.tab.querySelector('form.import .progress').classList.add('done');
			}
			if (obj.import_progress) {
				newTab.tab.querySelector('form.import .progress span').textContent = obj.import_progress;
			}
		}
		
		newTab.tab.querySelector('.profiler .fields').innerHTML = '';
		obj.profiler_fields.forEach(field => {
			newTab.tab.querySelector('.profiler .btn-add').click();
			const elem = newTab.tab.querySelector('.profiler .sql-time:last-child');
			elem.querySelector('.time').value = field.time;
			elem.querySelector('textarea').value = field.query;
			elem.querySelector('.icons-group input').checked = field.skip;
			if (field.active) {
				elem.classList.add('active');
			}
			field.time_line.forEach((time, idx) => {
				const tmpLine = document.querySelector('.templates > .sql-time-line').cloneNode(true);
				tmpLine.querySelector('.num').textContent = idx + 1;
				tmpLine.querySelector('.ms').textContent = time;
				elem.querySelector('.list').append(tmpLine);
			});
		});
	},
	
	listQuery(obj, newTab) {
		newTab.table = obj.tb_name;
		newTab.columns = obj.columns || [];
		newTab.fillTables(obj);
		newTab.history = obj.history;
		
		newTab.tab.querySelector('.table.rows .num-rows').textContent = obj.num_rows;
		newTab.tab.querySelector('.full-text input').checked = obj.full_text;
		newTab.tab.querySelector('.custom-name span').textContent = obj.custom_name;
		newTab.tab.querySelector('.custom-name input').value = obj.custom_name_input;
		newTab.tab.querySelector('.query-block .time').value = obj.run_time;
		newTab.tab.querySelector('.query-block textarea').value = obj.sql_query;
		
		newTab.tab.querySelector('.auto-resize input').checked = obj.auto_height;
		newTab.tab.querySelector('.auto-resize input').dispatchEvent(new Event('change'));
		newTab.tab.querySelector('.query-block textarea').scrollTop = obj.textarea_scroll_top;
		newTab.tab.querySelectorAll('.query-block .active').forEach(el => el.classList.remove('active'));

		if (obj.with_time_class) {
			newTab.tab.classList.add('with-time');
		}
		if (obj.columns_class) {
			newTab.tab.querySelector('.table.rows').classList.add('show-columns');
		}
		if (obj.tmp_queries_class) {
			newTab.tab.querySelector('.icon.tmp-queries').click();
		}
		if (obj.custom_name_class) {
			newTab.tab.querySelector('.custom-name').classList.add('active');
		}
		if (obj.lock_class) {
			newTab.tab.querySelector('.icon.lock').click();
		}
		if (obj.block_rows_class) {
			newTab.tab.querySelector('.table.rows .block-name').classList.add('close');
		}
		if (obj.rows_class) {
			newTab.tab.querySelector('.table.rows').classList.add('close');
		}
		if (obj.block_class) {
			newTab.tab.querySelector(`.query-block .block-name:nth-child(${obj.block_class})`).click();
		}
		
		if (!obj.structure_class) {
			newTab.tab.querySelector('.table.structure').classList.remove('close');
			newTab.tab.querySelector('.table-line [data-text="structure-heading"]').classList.add('active');
		}
		
		if (newTab.tab.querySelector('.table.indexes')) {
			if (!obj.indexes_class) {
				newTab.tab.querySelector('.table.indexes').classList.remove('close');
				newTab.tab.querySelector('.table-line [data-text="indexes-heading"]').classList.add('active');
			}
		}
		
		obj.structure_checked.forEach(i => {
			const elem = newTab.tab.querySelector(`.table.structure tbody tr:nth-child(${i}) .box input`);
			elem.checked = true;
			elem.dispatchEvent(new Event('change'));
		});

		for (let key in obj.structure_conditions) {
			newTab.tab.querySelector(`.table.structure tr:nth-child(${key}) td:last-child .input-text`).value = obj.structure_conditions[key];
		}
	},
};

const autoSave = {
	check() {
		if (!config.auto_save_session) return;
		if (document.visibilityState == 'hidden') {
			setTimeout(() => {
				if ((document.visibilityState == 'hidden') && (location.hash != app.id)) {
					location.hash = app.id;
					const obj = state.save();
					sessionStorage.setItem(`${config.prefix}.auto_save`, JSON.stringify(obj));
				}
			}, 3000);
		}
		if (document.visibilityState == 'visible') {
			autoSave.clearHash();
		}
	},
	
	restore() {
		if (app.id) return;
		if (location.hash) {
			app.id = location.hash.substr(1);
			const obj = JSON.parse(sessionStorage.getItem(`${config.prefix}.auto_save`));
			if (obj) {
				restore.init(obj);
			}
			autoSave.clearHash();
		} else {
			const randomID = '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c => (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
			app.id = randomID;
		}
	},
	
	clearHash() {
		history.replaceState('', '', location.pathname);
		sessionStorage.removeItem(`${config.prefix}.auto_save`);
	},
};

function clearInputs() {
	const fields = document.querySelectorAll('.templates input[type="text"], .templates input[type="checkbox"], .templates input[data-checked], .templates textarea');
	fields.forEach(el => {
		if (el.dataset.checked) {
			el.checked = (el.dataset.checked == 'true');
		} else {
			el.type == 'checkbox' ? el.checked = false : el.value = '';
		}
	});
}

function updateHeight() {
	const height = window.innerHeight;
	document.documentElement.style.setProperty('--view-height', `${height}px`);
}

window.addEventListener('resize', (e) => {
	updateHeight();
	document.querySelector('html').scrollTop = window.innerHeight * app.curScreen;
	if (document.querySelector('body > .pop-up-screens')) {
		panel.calcScale();
	}
});

window.addEventListener('load', panel.init);
window.onbeforeunload = () => { return true; };
document.addEventListener('visibilitychange', autoSave.check);