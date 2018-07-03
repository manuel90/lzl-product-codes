Object.defineProperty(window, 'mlzl_admin_close_alert', {
    get: function() { 
        return function() {
			jQuery(document.body).trigger('mlzl_admin_close_alert');
			if(document.getElementById('mlzl_bg_modal')) {
				jQuery('#mlzl_bg_modal').remove();
				jQuery('#mlzl_modal').remove();
			}
        };
    }
});
Object.defineProperty(window, 'lzlExecFail', {
    get: function() { 
        return function(error,hrxr) {
			var id_panel = 'lfail_bg_mzra_panel2093';

			console.log(error);
			console.log(error.status);
			console.log(error.statusText);
			console.log(hrxr);

			var options = {
				seconds: 10,
				text: 'Connection is lost. Try connect in {t} Message: '+error.statusText,
			};

			var args = {};

			for(var i in options) {
				if( !args[i] ) {
					args[i] = options[i];
				}
			}

			if( document.getElementById(id_panel) ) {
				return;
			}

			var element = document.createElement('div');
			element.setAttribute('id', id_panel);
			element.style.display = 'block';
			element.style.position = 'fixed';
			element.style.left = '0';
			element.style.top = '0';
			element.style.width = '100%';
			element.style.height = '100%';
			element.style.background = 'rgba(255,255,255,0.5)';
			element.style.zIndex = '9999';
			element.innerHTML = '<span style="color: #fff;position: absolute;left: 50%;top: 50px;transform: translate(-50%,-50%);font-style: italic;font-size: 18px;font-weight: bold;background: rgba(0,0,0,0.9);border-radius:20px;padding: 20px 40px 20px 30px;"><span id="text-lost_1014">'+args.text.replace("{t}",args.seconds)+'</span><i id="dots_1014" style="text-decoration: none;position: absolute;"></i></span>';

			document.body.appendChild(element);

			document.getElementById('text-lost_1014').data_time = args.seconds;

			window.dotsTAnimation = window.setInterval(function(){
				var e = document.getElementById('text-lost_1014');
				var t = parseInt(e.data_time);
				if(t < 0) {
					return;
				}
				document.getElementById('text-lost_1014').innerHTML = args.text.replace(/{t}/g,t);
				t--;
				document.getElementById('text-lost_1014').data_time = t;
				if(t < 0 && window.dotsTAnimation) {
					clearInterval(window.dotsTAnimation);
					window.location.reload();
				}
			},1000);
        };
    }
});
Object.defineProperty(window, 'lzlCodesShowMessage', {
    get: function() { 
        return function(type,message) {
			var panel = arguments[2] ? arguments[2] : 'panel-view-codes-messages';

			jQuery(document.getElementById(panel)).attr('class','lzl-panel-view-messages')
				.addClass(type)
				.html('<span>'+message+'</span><b class="cl dashicons dashicons-dismiss" onclick="document.getElementById(\''+panel+'\').style.display = \'none\';"></b>')
				.show();
        };
    }
});
Object.defineProperty(window, 'mlzl_admin_alert', {
    get: function() { 
        return function(messages) {
			var content = '';
			var textButton = arguments[1] ? arguments[1] : '<span class="dashicons dashicons-yes"></span>';
			if(jQuery.isArray(messages)) {
				content = '<ul>';
			for (var i in messages) {
				content += '<li>'+messages[i]+'</li>';
			}
				content += '</ul>';
			} else {
				content = '<div>'+messages+'</div>';
			}


			if(!document.getElementById('mlzl_bg_modal')) {
				jQuery('<div id="mlzl_bg_modal" style="position: fixed;z-index: 100000;width: 100%;height: 100%;left: 0;top: 0;background-color: #000;opacity: 0.5;filter: Alpha(opacity=50);">').appendTo(jQuery(document.body));
				jQuery('<div id="mlzl_modal" style="max-width: 650px;max-height: 800px;overflow-y: auto;position: fixed;top: 50%;left: 50%;transform: translate(-50%,-50%);border: 1px solid #eee;background-color: #fff;box-shadow: 2px 2px 2px #000;z-index: 100001;">')
				.append(jQuery('<div class="body-modal" style="padding: 20px;box-sizing: border-box;">').append('<div class="content" style="display: block;"></div><button onclick="mlzl_admin_close_alert();" class="button button-primary button-close" style="margin: 15px auto 0 auto;display: table;">'+textButton+'</button>'))
				.appendTo(jQuery(document.body));
			}
			jQuery('#mlzl_modal').find('.content').html(content);
        };
    }
});
//---------------------------------------------


Object.defineProperty(window, 'lzlAttachEventSend', {
    get: function() { 
        return function() {
			jQuery('.lzl-btn-send-email').on('click',function(e){
				e.preventDefault();
				var _this = jQuery(this);

				if( _this.hasClass('sending') ) {
					return;
				}
				jQuery('#lzl-modal-messages').hide();
				_this.addClass('sending').attr('disabled',true);

				var c = _this.data('code');

				var ipt = jQuery('input[name="lzl_sendeto'+c+'"]').first();
				ipt.attr('disabled',true);

				jQuery.ajax({
					url: ajaxurl,
					type: 'post',
					dataType: 'json',
					data: {
						action: 'lzlsemailu5',
						email: jQuery.trim( ipt.val() ),
						code: c,
						product: _this.data('product'),
					}
				}).done(function(result){
					_this.removeClass('sending').attr('disabled',false);
					ipt.attr('disabled',false);
					if( result.success ) {
						_this.parent().html(result.data);
						return;
					}
					lzlCodesShowMessage('error',result.data,'lzl-modal-messages');
				}).fail(lzlExecFail);

			});
        };
    }
});


jQuery(document).ready(function(){

	

	

	var btnAddCodes = document.getElementById('btn-add-form-codes');
	if( btnAddCodes ) {
		jQuery(btnAddCodes).on('click',function(e){
			e.preventDefault();

			var _this = jQuery(this);
			var listing = _this.data('listing');

			if( !jQuery.isArray(listing) ) {
				listing = [];
			}

			jQuery('#panel-view-codes-messages').hide();

			var code = jQuery.trim( jQuery('#ipt-code-pcodes').val() );

			if( code.length == 0 ) {
				lzlCodesShowMessage('error',_this.data('msg-empty-code'));
				return;
			}

			if( jQuery.inArray(code,listing) != -1 ) {
				lzlCodesShowMessage('error',_this.data('msg-code-exists'));
				return;
			}

			var addCode = function() {
				var html_code = '<li>'+
					'<b>'+code+'</b>'+
					'<input type="hidden" name="lzl_codes[]" value="'+code+'" />'+
					'<b class="cl dashicons dashicons-dismiss" onclick="jQuery(this).parent().remove();var l = jQuery(document.getElementById(\'btn-add-form-codes\')).data(\'listing\');jQuery(document.getElementById(\'btn-add-form-codes\')).data(\'listing\',l.splice(\''+code+'\'));"></b>'+
					'</li>';

				jQuery('#panel-view-codes-added').prepend(html_code);
				listing.push(code);
				_this.data('listing',listing);
				
			};

			if( _this.hasClass('checking') ) {
				return;
			}
			_this.addClass('checking').attr('disabled',true);
			jQuery.ajax({
				type: 'post',
				url: ajaxurl,
				dataType: 'json',
				data: {
					action: 'lzlapcode2',
					code: code,
				}
			}).done(function(result){
				_this.removeClass('checking').attr('disabled',false);
				if( result.success ) {
					addCode();
					return;
				}
				lzlCodesShowMessage('error',result.data);
			}).fail(lzlExecFail);

		});
	}

	var btnSeeCodes = document.getElementById('btn-view-codes-product');
	if( btnSeeCodes ) {
		jQuery(btnSeeCodes).on('click',function(e){
			e.preventDefault();

			var _this = jQuery(this);

			if( _this.hasClass('loading') ) {
				return;
			}

			_this.addClass('loading').attr('disabled',true);

			var content = ''
			jQuery.ajax({
				type: 'post',
				url: ajaxurl,
				dataType: 'json',
				data: {
					action: 'lzlspcode7',
					product: _this.data('product'),
				}
			}).done(function(result){
				_this.removeClass('loading').attr('disabled',false);
				if( result.success ) {
					mlzl_admin_alert(result.data);
					lzlAttachEventSend();
					return;
				}
				lzlCodesShowMessage('error',result.data);
			}).fail(lzlExecFail);
		});
	}
});