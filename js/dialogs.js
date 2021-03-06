/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

var OpenEyes = window.OpenEyes || {};
OpenEyes.Dialog = OpenEyes.Dialog || {};

(function() {

	'use strict';

	// Set the jQuery UI Dialog default options.
	$.extend($.ui.dialog.prototype.options, {
		dialogClass: 'dialog',
		show: 'fade'
	});

	var EventEmitter = OpenEyes.Util.EventEmitter;

	/**
	 * Dialog constructor.
	 * @name Dialog
	 * @constructor
	 * @extends {Emitter}
	 * @example
	 * var dialog = new OpenEyes.Dialog({
	 *	title: 'Title here',
	 *	content: 'Here is some content.'
	 * });
	 * dialog.on('open', function() {
	 *	console.log('The dialog is now open');
	 * });
	 * dialog.open();
	 */
	function Dialog(options) {

		EventEmitter.call(this);

		this.options = $.extend(true, {}, Dialog._defaultOptions, options);

		this.create();
		this.bindEvents();

		// Load dialog content in an iframe.
		if (this.options.iframe) {
			this.loadIframeContent();
		}
		// Load dialog content via an AJAX request.
		else if (this.options.url) {
			this.loadContent();
		}
	}

	Dialog.inherits(EventEmitter);

	/**
	 * The default dialog options. Custom options will be merged with these.
	 * @name Dialog#_defaultOptions
	 * @property {mixed} [content=null] - Content to be displayed in the dialog.
	 * This option accepts multiple types, including strings, DOM elements, jQuery instances, etc.
	 * @property {string|null} [title=null] - The dialog title.
	 * @property {string|null} [iframe=null] - A URL string to load the dialog content
	 * in via an iFrame.
	 * @property {string|null} [url=null] - A URL string to load the dialog content in via an
	 * AJAX request.
	 * @property {object|null} [data=null] - Request data used when loading dialog content
	 * via an AJAX request.
	 * @property {string|null} [dialogClass=dialog] - A CSS class string to be added to
	 * the main dialog container.
	 * @property {boolean} [contrainToViewport=false] - Constrain the dialog dimensions
	 * so that it is never displayed outside of the window viewport?
	 * @property {integer|string} [width=400] - The dialog width.
	 * @property {integer|string} [height=auto] - The dialog height.
	 * @private
	 */
	Dialog._defaultOptions = {
		content: null,
		destroyOnClose: true,
		url: null,
		data: null,
		id: null,
		iframe: null,
		autoOpen: false,
		title: null,
		modal: true,
		dialogClass: 'dialog',
		resizable: false,
		draggable: false,
		constrainToViewport: false,
		width: 400,
		height: 'auto',
		minHeight: 'auto',
		show: 'fade'
	};

	/**
	 * Creates and stores the dialog container, and creates a new jQuery UI
	 * instance on the container.
	 * @name Dialog#create
	 * @method
	 * @private
	 */
	Dialog.prototype.create = function() {

		// Create the dialog content div.
		this.content = $('<div />', { id: this.options.id });

		// Add default content (if any exists)
		this.setContent(this.options.content);

		// Create the jQuery UI dialog.
		this.content.dialog(this.options);

		// Store a reference to the jQuery UI dialog instance.
		this.instance = this.content.data('ui-dialog');
	};

	/**
	 * Add content to the dialog.
	 * @name Dialog#setContent
	 * @method
	 * @public
	 */
	Dialog.prototype.setContent = function(content) {
		this.content.html(content);
	};

	/**
	 * Binds common dialog event handlers.
	 * @name Dialog#create
	 * @method
	 * @private
	 */
	Dialog.prototype.bindEvents = function() {

		// Ensure all handlers are called in the context of this object instance.
		this.bindAll(true);

		this.content.on({
			dialogclose: this.onDialogClose,
			dialogopen: this.onDialogOpen
		});
	};

	/**
	 * Gets a script template from the DOM, compiles it using Mustache, and
	 * returns the HTML.
	 * @name Dialog#compileTemplate
	 * @method
	 * @private
	 * @param {object} options - An options object container the template selector and data.
	 * @returns {string}
	 */
	Dialog.prototype.compileTemplate = function(options) {

		var template = $(options.selector).html();

		if (!template) {
			throw new Error('Unable to compile dialog template. Template not found: ' + options.selector);
		}

		return Mustache.render(template, options.data || {});
	};

	/**
	 * Sets the dialog to be in a loading state.
	 * @name Dialog#setLoadingState
	 * @method
	 * @private
	 */
	Dialog.prototype.setLoadingState = function() {
		this.content.addClass('loading');
		this.setTitle('Loading...');
	};

	/**
	 * Removes the loading state from the dialog.
	 * @name Dialog#removeLoadingState
	 * @method
	 * @private
	 */
	Dialog.prototype.removeLoadingState = function() {
		this.content.removeClass('loading');
	};

	/**
	 * Sets a 'loading' message and retrieves the dialog content via AJAX.
	 * @name Dialog#loadContent
	 * @method
	 * @private
	 */
	Dialog.prototype.loadContent = function() {

		this.setLoadingState();

		this.xhr = $.ajax({
			url: this.options.url,
			data: this.options.data
		});

		this.xhr.done(this.onContentLoadSuccess);
		this.xhr.fail(this.onContentLoadFail);
		this.xhr.always(this.onContentLoad);
	};

	/**
	 * Sets a 'loading' message and creates an iframe with the appropriate src attribute.
	 * @name Dialog#loadIframeContent
	 * @method
	 * @private
	 */
	Dialog.prototype.loadIframeContent = function() {

		this.setLoadingState();

		this.iframe = $('<iframe />', {
			width: '100%',
			height: '99%',
			frameborder: 0
		}).hide();

		// We're intentionally setting the load handler before setting the src.
		this.iframe.on('load', this.onIframeLoad);
		this.iframe.attr({
			src: this.options.iframe,
		});

		// Add the iframe to the DOM.
		this.setContent(this.iframe);
	};

	/**
	 * Sets the dialog title.
	 * @name Dialog#setTitle
	 * @method
	 * @public
	 */
	Dialog.prototype.setTitle = function(title) {
		this.instance.option('title', title);
	};

	/**
	 * Repositions the dialog in the center of the page.
	 * @name Dialog#reposition
	 * @method
	 * @public
	 */
	Dialog.prototype.reposition = function() {
		this.instance._position(this.instance._position());
	};

	/**
	 * Calculates the dialog dimensions. If Dialog#options.constrainToViewport is
	 * set, then the dimensions will be calculated so that the dialog will not be
	 * displayed outside of the browser viewport.
	 * @name Dialog#getDimensions
	 * @method
	 * @private
	 */
	Dialog.prototype.getDimensions = function() {

		var dimensions = {
			width: this.options.width,
			height: this.options.height
		};

		if (this.options.constrainToViewport) {

			var margin = 40;
			var width = parseInt(dimensions.width, 10);
			var height = parseInt(dimensions.height, 10);

			if (!isNaN(width)) {
				dimensions.width = Math.min(width, $(window).width() - margin);
			}
			if (!isNaN(height)) {
				dimensions.height = Math.min(height, $(window).height() - margin);
			}
		}

		return dimensions;
	};

	/**
	 * Calculates and sets the dialog dimensions.
	 * @name Dialog#setDimensions
	 * @method
	 * @private
	 */
	Dialog.prototype.setDimensions = function() {
		var dimensions = this.getDimensions();
		this.instance.option('width', dimensions.width);
		this.instance.option('height', dimensions.height);
	};

	/**
	 * Opens (shows) the dialog.
	 * @name Dialog#open
	 * @method
	 * @public
	 */
	Dialog.prototype.open = function() {
		this.setDimensions();
		this.instance.open();
	};

	/**
	 * Closes (hides) the dialog.
	 * @name Dialog#close
	 * @method
	 * @public
	 */
	Dialog.prototype.close = function() {
		this.instance.close();
	};

	/**
	 * Destroys the dialog. Removes all elements from the DOM and detaches all
	 * event handlers.
	 * @name Dialog#destroy
	 * @method
	 * @public
	 */
	Dialog.prototype.destroy = function() {

		if (this.xhr) {
			this.xhr.abort();
		}
		if (this.iframe) {
			this.iframe.remove();
		}

		this.instance.destroy();
		this.content.remove();

		this.emit('destroy');
	};

	/** Event handlers */

	/**
	 * Emit the 'open' event after the dialog has opened.
	 * @name Dialog#onDialogOpen
	 * @method
	 * @private
	 */
	Dialog.prototype.onDialogOpen = function() {
		this.emit('open');
	};

	/**
	 * Emit the 'close' event after the dialog has closed, and optionally destroy
	 * the dialog.
	 * @name Dialog#onDialogClose
	 * @method
	 * @private
	 */
	Dialog.prototype.onDialogClose = function() {
		this.emit('close');
		if (this.options.destroyOnClose) {
			this.destroy();
		}
	};

	/**
	 * Content load handler. This method is always executed *after* the content
	 * request completes (whether there was an error or not), and is executed after
	 * any success or fail handlers. This method removes the loading state of the
	 * dialog, and repositions it in the center of the screen.
	 * @name Dialog#onContentLoad
	 * @method
	 * @private
	 */
	Dialog.prototype.onContentLoad = function() {
		this.removeLoadingState();
		this.setDimensions();
		this.reposition();
	};

	/**
	 * Content load success handler. Sets the dialog content to be the response of
	 * the content request.
	 * @name Dialog#onContentLoadSuccess
	 * @method
	 * @private
	 */
	Dialog.prototype.onContentLoadSuccess = function(response) {
		this.setTitle(this.options.title);
		this.setContent(response);
	};

	/**
	 * Content load fail handler. This method is executed if the content request
	 * fails, and shows an error message.
	 * @name Dialog#onContentLoadFail
	 * @method
	 * @private
	 */
	Dialog.prototype.onContentLoadFail = function() {
		this.setTitle('Error');
		this.setContent('Sorry, there was an error retrieving the content. Please try again.');
	};

	/**
	 * iFrame load handler. This method is always executed after the iFrame
	 * source is loaded. This method removes the loading state of the
	 * dialog, and repositions it in the center of the screen.
	 * @name Dialog#onIframeLoad
	 * @method
	 * @private
	 */
	Dialog.prototype.onIframeLoad = function() {
		this.setTitle(this.options.title);
		this.iframe.show();
		this.onContentLoad();
	};

	OpenEyes.Dialog = Dialog;

}());

(function() {

	'use strict';

	var Dialog = OpenEyes.Dialog;

	/**
	 * AlertDialog constructor. The AlertDialog extends the base Dialog and provides
	 * an 'Ok' button for the user to click on.
	 * @name AlertDialog
	 * @constructor
	 * @extends {Dialog}
	 * @example
	 * var alert = new OpenEyes.Dialog.Alert({
	 *	 content: 'Here is some content.'
	 * });
	 * alert.open();
	 */
	function AlertDialog(options) {

		options = $.extend(true, {}, AlertDialog._defaultOptions, options);
		options.content = this.getContent(options);

		Dialog.call(this, options);
	}

	AlertDialog.inherits(Dialog);

	/**
	 * The default alert dialog options. These options will be merged into the
	 * default dialog options.
	 * @name AlertDialog#_defaultOptions
	 * @private
	 */
	AlertDialog._defaultOptions = {
		modal: true,
		width: 400,
		minHeight: 'auto',
		title: 'Alert',
		dialogClass: 'dialog alert'
	};

	/**
	 * Get the dialog content. Do some basic content formatting, then compile
	 * and return the alert dialog template.
	 * @name AlertDialog#getContent
	 * @method
	 * @private
	 * @param {string} content - The main alert dialog content to display.
	 * @returns {string}
	 */
	AlertDialog.prototype.getContent = function(options) {

		// Replace new line characters with html breaks
		options.content = (options.content || '').replace(/\n/g, '<br/>');

		// Compile the template, get the HTML
		return this.compileTemplate({
			selector: '#dialog-alert-template',
			data: {
				content: options.content
			}
		});
	};

	/**
	 * Bind events
	 * @name AlertDialog#bindEvents
	 * @method
	 * @private
	 */
	AlertDialog.prototype.bindEvents = function() {
		Dialog.prototype.bindEvents.apply(this, arguments);
		this.content.on('click', '.ok', this.onButtonClick);
	};

	/** Event handlers */

	/**
	 * 'OK' button click handler. Simply close the dialog on click.
	 * @name AlertDialog#onButtonClick
	 * @method
	 * @private
	 */
	AlertDialog.prototype.onButtonClick = function() {
		this.close();
	};

	OpenEyes.Dialog.Alert = AlertDialog;

}());

(function() {

	'use strict';

	var Dialog = OpenEyes.Dialog;

	/**
	 * ConfirmDialog constructor. The ConfirmDialog extends the base Dialog and provides
	 * an 'Ok' and 'Cancel' button for the user to click on.
	 * @name ConfirmDialog
	 * @constructor
	 * @extends {Dialog}
	 * @example
	 * var alert = new OpenEyes.Dialog.Confirm({
	 *	 content: 'Here is some content.'
	 * });
	 * alert.open();
	 */
	function ConfirmDialog(options) {

		options = $.extend(true, {}, ConfirmDialog._defaultOptions, options);
		options.content = !options.url ? this.getContent(options) : '';

		Dialog.call(this, options);
	}

	ConfirmDialog.inherits(Dialog);

	/**
	 * The default confirm dialog options. These options will be merged into the
	 * default dialog options.
	 * @name ConfirmDialog#_defaultOptions
	 * @property {object} _defaultOptions - The default options.
	 * @private
	 */
	ConfirmDialog._defaultOptions = {
		modal: true,
		width: 400,
		minHeight: 'auto',
		title: 'Confirm',
		dialogClass: 'dialog confirm',
		okButton: 'OK',
		cancelButton: 'Cancel'
	};

	/**
	 * Get the dialog content. Do some basic content formatting, then compile
	 * and return the alert dialog template.
	 * @name ConfirmDialog#getContent
	 * @method
	 * @private
	 * @param {string} content - The main alert dialog content to display.
	 * @returns {string}
	 */
	ConfirmDialog.prototype.getContent = function(options) {
		// Compile the template, get the HTML
		return this.compileTemplate({
			selector: '#dialog-confirm-template',
			data: {
				content: options.content,
				okButton: options.okButton,
				cancelButton: options.cancelButton
			}
		});
	};

	/**
	 * Bind events
	 * @name ConfirmDialog#bindEvents
	 * @method
	 * @private
	 */
	ConfirmDialog.prototype.bindEvents = function() {
		Dialog.prototype.bindEvents.apply(this, arguments);
		this.content.on('click', '.ok', this.onOKButtonClick);
		this.content.on('click', '.cancel', this.onCancelButtonClick);
	};

	/** Event handlers */

	/**
	 * 'OK' button click handler. Simply close the dialog on click.
	 * @name ConfirmDialog#onButtonClick
	 * @method
	 * @private
	 */
	ConfirmDialog.prototype.onOKButtonClick = function() {
		this.close();
		this.emit('ok');
	};

	/**
	 * 'Cancel' button click handler. Simply close the dialog on click.
	 * @name ConfirmDialog#onButtonClick
	 * @method
	 * @private
	 */
	ConfirmDialog.prototype.onCancelButtonClick = function() {
		this.close();
		this.emit('cancel');
	};

	/**
	 * Content load success handler. Sets the dialog content to be the response of
	 * the content request.
	 * @name ConfirmDialog#onContentLoadSuccess
	 * @method
	 * @private
	 */
	ConfirmDialog.prototype.onContentLoadSuccess = function(response) {
		this.options.content = response;
		Dialog.prototype.onContentLoadSuccess.call(this, this.getContent(this.options));
	};

	OpenEyes.Dialog.Confirm = ConfirmDialog;
}());