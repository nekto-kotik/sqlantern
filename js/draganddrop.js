/*
This file is part of SQLantern Database Manager
Copyright (C) 2022, 2023 Svitlana Militovska
License: GNU General Public License v3.0
https://github.com/nekto-kotik/sqlantern
https://sqlantern.com/

SQLantern is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
*/

function Drag(obj) {
	this.cursorX = null;
	this.cursorY = null;
	this.clone = null;
	this.dragElem = null;
	this.mouseDownEvent = false;
	this.parent = obj.parent;
	this.dragSelector = obj.dragSelector;
	this.dragSelectorClick = obj.dragSelectorClick;
	this.backlight = obj.backlight;
	this.scrollParent = obj.scrollParent;
	this.callback = obj.callback;
	this.lastPoint = {x: null, y: null};
	this.init();
}

Drag.prototype.init = function() {
	const self = this;
	const elems = self.parent.querySelectorAll(`${self.dragSelectorClick}`);
	elems.forEach(elem => self.addEvent(elem));
	
	const events = ['mousemove', 'touchmove'];
	events.forEach(event => {
		self.parent.addEventListener(event, function(evt) {
			self.mouseMove(evt);
		});
	});
}

Drag.prototype.addEvent = function(elem) {
	const self = this;
	const events = ['mousedown', 'touchstart'];
	const events2 = ['mouseup', 'touchend', 'mouseleave', 'touchcancel'];
	events.forEach(event => {
		elem.addEventListener(event, function(evt) {
			self.mouseDown(this, evt);

			function onMouseUp(evt) {
				self.mouseDownEvent = false;
				const simpleBar = self.dragElem.querySelector('.simplebar-content');
				simpleBar ? new SimpleBar(self.dragElem.querySelector('.content')) : '';
				
				self.dragElem.classList.remove('current');
				self.clone.remove();
				
				if (self.callback) {
					self.callback({from: self.from, elem: self.dragElem});
				}
				if (self.dragElem.querySelector('.btn-run.stop-run')) {
					const time = +self.dragElem.querySelector('.run-block input').value * 1000;
					const bar = self.dragElem.querySelector('.query-block .bar');
					bar.style.transitionDuration = 'unset';
					bar.style.width = '0';
					window.setTimeout(() => {
						bar.style.transitionDuration = `${time}ms`;
						bar.style.width = '100%';
					}, 100);
				}
				
				events2.forEach(evt => document.body.removeEventListener(evt, onMouseUp));
			}
			events2.forEach(evt => document.body.addEventListener(evt, onMouseUp));
		});
	});
}

Drag.prototype.mouseDown = function(elem, evt) {
	const self = this;
	evt.preventDefault();
	self.mouseDownEvent = true;
	if (evt.type == 'touchstart') {
		evt.x = evt.touches[0].pageX;
		evt.y = evt.touches[0].pageY;
	}
	
	self.lastPoint.x = evt.x;
	self.lastPoint.y = evt.y;
	self.dragElem = elem.classList.contains(self.dragSelector) ? elem : elem.closest(self.dragSelector);
	self.clone = self.dragElem.cloneNode(true);
	self.dragElem.classList.add('current');
	self.clone.classList.add('draggable');
	
	var parentTop = self.parent.getBoundingClientRect().top;
	var parentLeft = self.parent.getBoundingClientRect().left;
	var elemTop = self.dragElem.getBoundingClientRect().top;
	var elemLeft = self.dragElem.getBoundingClientRect().left;
	self.cursorY = evt.y - elemTop;
	self.cursorX = evt.x - elemLeft;
	var top = elemTop - parentTop;
	var left = elemLeft - parentLeft;
	
	if (app.matrix.state) {
		self.clone.style.top = `calc(${top}px / ${app.matrix.value})`;
		self.clone.style.left =  `calc(${left}px / ${app.matrix.value})`;
	} else {
		self.clone.style.top = top + 'px';
		self.clone.style.left = left + 'px';
	}
	
	if (self.callback) {
		let elems = document.querySelectorAll('body > .pop-up-screens .one-tab');
		for (let i = 0; i < elems.length; i++) {
			if (elems[i] == self.dragElem) {
				self.from = i;
				break;
			}
		}
	}
	
	self.parent.append(self.clone);
}

Drag.prototype.mouseMove = function(evt) {
	const self = this;
	if (!self.mouseDownEvent) return;
	
	if (evt.type == 'touchmove') {
		evt.y = evt.touches[0].pageY;
		evt.x = evt.touches[0].pageX;
	}
	
	var parentRect = self.parent.getBoundingClientRect();
	var parentTop = parentRect.top;
	var parentBottom = parentRect.bottom;
	var parentLeft = parentRect.left;
	var parentWidth = parentRect.width;
	var parentHeight = parentRect.height;
	var parentRight = parentLeft + parentWidth;
	var cloneRect = self.clone.getBoundingClientRect();
	var cloneTop = cloneRect.top;
	var cloneBottom = cloneRect.bottom;
	var cloneLeft = cloneRect.left;
	var cloneWidth = cloneRect.width;
	var cloneRight = cloneLeft + cloneWidth;
	var cloneHeight = cloneRect.height;
	var elemTop = evt.y - parentTop - self.cursorY;
	var elemBottom = elemTop + cloneHeight;
	var elemLeft = evt.x - parentLeft - self.cursorX;
	var elemRight = elemLeft + cloneWidth;
	
	if (self.scrollParent) {
		var moveAgain = false;
		/*if (cloneBottom > window.innerHeight && cloneTop < 1) {
			console.log('MIDDLE');
			return;
		}
		if ((self.lastPoint.y < evt.pageY) && (cloneBottom != parentBottom) && (cloneBottom > window.innerHeight) && (parentBottom >= window.innerHeight)) {
			self.scrollParent.scrollTop = self.scrollParent.scrollTop + 20;
			elemTop += 20;
			moveAgain = true;
		}
		if (self.lastPoint.y > evt.pageY && cloneTop < 1 && parentTop < 1) {
			self.scrollParent.scrollTop -= 20;
			elemTop -= 20;
			moveAgain = true;
		}*/
		if (self.lastPoint.x < evt.x && cloneRight != parentRight && cloneRight > window.innerWidth && parentRight >= window.innerWidth) {
			self.scrollParent.scrollLeft = self.scrollParent.scrollLeft + 20;
			elemLeft += 20;
			moveAgain = true;
		}
		if (self.lastPoint.x > evt.x && cloneLeft < 1 && parentLeft < 1) {
			self.scrollParent.scrollLeft -= 20;
			elemLeft -= 20;
			moveAgain = true;
		}
		if (moveAgain) {
			window.setTimeout(() => {
				self.mouseMove(evt);
			}, 100);
		}
	}
	if (elemTop > 1 && elemBottom < parentHeight) {
		if (app.matrix.state) {
			self.clone.style.top = `calc(${elemTop}px / ${app.matrix.value})`
		} else {
			self.clone.style.top = elemTop + 'px';
		}
	}
	if (elemLeft > 1 && elemRight < parentWidth) {
		if (app.matrix.state) {
			self.clone.style.left = `calc(${elemLeft}px / ${app.matrix.value})`;
		} else {
			self.clone.style.left = elemLeft + 'px';
		}
	}
	
	var cloneX = cloneLeft + (cloneWidth/2);
	var cloneY = cloneTop + (cloneHeight/2);
	var elems = self.parent.querySelectorAll(`${self.dragSelector}:not(.draggable, .current)`);

	for (let i = 0; i < elems.length; i++) {
		var elRect = elems[i].getBoundingClientRect();
		var elTop = elRect.top;
		var elBottom = elRect.bottom;
		var elLeft = elRect.left;
		var elRight = elRect.right;
		var elX = elLeft + (elRect.width/2);
		var elY = elTop + (elRect.height/2);

		const condition1 = cloneX > elLeft && cloneX < elRight && cloneY > elTop && cloneY < elBottom;
		const condition2 = elX > cloneLeft && elX < cloneRight && elY > cloneTop && elY < cloneBottom;
		
		if (condition1 || condition2) {
			if (self.lastPoint.x > evt.x || self.lastPoint.y > evt.y) {
				elems[i].before(self.dragElem);
			}
			if (self.lastPoint.x < evt.x || self.lastPoint.y < evt.y) {
				elems[i].after(self.dragElem);
			}
		}
	}
	self.lastPoint.y = evt.y;
	self.lastPoint.x = evt.x;
}