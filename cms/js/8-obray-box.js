
/*********************************************************************

	OBox
	
*********************************************************************/

;(function ( $, window, document, undefined ) {

    $.widget( "obray.OBox" , {

        options: {
            attr: "",
            id: null,
            html_class: "",
            header: "",
            body: "",
            footer: "",
            onClick: function(){},
            onHidden: function(){},
            onSuccess: function(){}
        },
        
        /*********************************************************************
			CREATE
		*********************************************************************/

        _create: function () {
        
        	this._init();
			
            if( $('#'+this.options.id).length === 0){
				var html = '<div id="'+this.options.id+'" class="modal hide fade '+this.options.html_class+'" '+this.options.attr+' >'
					 	 + '<div class="modal-header">'
					 	 + '<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>'
					 	 + this.options.header
					 	 + '</div>'
					 	 + '<div class="modal-body">'
					 	 + this.options.body
					 	 + '</div>'
					 	 + '<div class="modal-footer">'
					 	 + this.options.footer
					 	 + '</div>'
					 	 + '</div>';
				
				$('body').append(html);
			}
            
            $('#'+this.options.id).modal(this.options);
		
			$('#'+this.options.id).on('hidden', function(){
				this.options.onHidden();
				this.destroy();
			}.bind(this));
			
			$('#'+this.options.id).on("loaded",function(){ setTimeout(this.extractButtons.bind(this),100); }.bind(this));
			
        },
        
        _init: function(){},
        
        /*********************************************************************
			Extracts Form Buttons
		*********************************************************************/
        
        extractButtons: function(){
	        $('#'+this.options.id+' .modal-footer').empty();
			$('#'+this.options.id+' .oform .btn-footer').addClass("btn-primary");
			var control = $('#'+this.options.id+' .oform .btn-footer').parents('.control-group');
			$('#'+this.options.id+' .modal-footer').append($('#'+this.options.id+' .oform .btn-footer'));
			control.remove();
			this.options.loaded();
			
			console.log($('#'+this.options.id+' .oform .btn-footer'));
        },
		
		/*********************************************************************
			Destroy
		*********************************************************************/
		
        destroy: function () {
			
			$('#'+this.options.id).remove();
			
            $.Widget.prototype.destroy.call(this);
        }
        
    });

})( jQuery, window, document );

/*********************************************************************

	OBoxConfirm
	
*********************************************************************/

;(function ( $, window, document, undefined ) {

	$.widget( "obray.OBoxConfirm", $.obray.OBox, {
		
		//Options to be used as defaults
        options: {
            attr: "",
            id: "",
            html_class: "",
            header: "<h3>Are you sure?</h3>",
            body: "",
            footer: "",
            onClick: function(){},
            onHidden: function(){},
            onSuccess: function(){}
        },
		
		_init: function() {
			this.element.data('OBox', this.element.data('OBoxConfirm'));
		}
	});

})( jQuery, window, document );


/*********************************************************************

	EDITOR - wizzywig and code editor for HTML and CSS
	
*********************************************************************/

;(function ( $, window, document, undefined ) {

	$.widget( "obray.OBoxEditor", $.obray.OBox, {
		
		//Options to be used as defaults
        options: {
            attr: "",
            id: "OEditor-modal",
            primary_key_value: 0,
            html_class: "",
            header: "<h3><i class=\"icon-cog icon-spin \"></i> Loading...</h3>",
            body: "",
            footer: "",
            content:"",
            parent:null,
            onClick: function(){},
            onHidden: function(){},
            onSuccess: function(){},
            edit_mode: "wizzy"
            
        },
        
        /*********************************************************************
			Initialize
		*********************************************************************/
		
		_init: function() {
			
			this.element.data('OBox', this.element.data('OBoxEditor'));
			
			this.header = $('#'+this.options.id+' .modal-header');
			this.body = $('#'+this.options.id+' .modal-body');
			this.footer = $('#'+this.options.id+' .modal-footer').append('<a id="OEditor-save-btn" href="#" class="btn btn-primary">Save changes</a>');
			
			$('#OEditor-save-btn').on("click",function(){
			
				this.setValues();
				$.ajax({
			        "url":'/cms/OParts/update/',
			        "data":'&opart_content='+encodeURIComponent(this.html)+'&opart_css='+encodeURIComponent(this.css)+"&opart_id="+this.options.primary_key_value,
			        "dataType":"json",
			        "success":function(data){ 
			        	this.options.parent.reset();
			        	$('#'+this.options.id).modal('hide')
			        }.bind(this)
				});
				
			}.bind(this));
			
			$.ajax({
		        "url":'/cms/OParts/getForEditor/',
		        "data":"&opart_id="+this.options.primary_key_value,
		        "dataType":"json",
		        "success":function(response){ 
		        	
		        	this.html = (response.data.html=="null")?"":response.data.html;
		        	this.css = (response.data.css=="null")?"":response.data.css;
		        	this.toolbar = response.data.toolbar;
		        	
		        	switch(this.options.edit_mode){
						case "wizzy": this.wizzywig(); break;
						case "html":
						case "css": this.code(); break;
					}
		        	
		        	$('#'+this.options.id+' .modal-header h3').remove();
		        	this.header.append(this.toolbar);
		        	
					$('#OEditor-wizzy-btn').on("click",function(){ this.wizzywig(); }.bind(this));	        	
					$('#OEditor-html-btn').on("click",function(){ this.codeHTML(); }.bind(this));
					$('#OEditor-css-btn').on("click",function(){ this.codeCSS(); }.bind(this));
					
		        }.bind(this)
			});
			
		},
		
		/*********************************************************************
			wizzywig
		*********************************************************************/
		
		wizzywig: function(){
			
			this.setValues();
			
			this.edit_mode = "wizzy";
			this.body.empty();
		    this.body.append('<form id="OEditor-form"><textarea name="opart_content" id="wysihtml5-textarea" placeholder="Enter your text ..." autofocus>'+this.html+'</textarea></form>')
        	this.wizzywig_editor = new wysihtml5.Editor("wysihtml5-textarea", { // id of textarea element
				toolbar:      "wysihtml5-toolbar", // id of toolbar element
				useLineBreaks: false,
				parserRules:  wysihtml5ParserRules // defined in parser rules set 
			});
			
		},
		
		/*********************************************************************
			codeHTML
		*********************************************************************/
		
		codeHTML: function(){
			
			this.setValues();
			
			this.edit_mode = "html";
			this.body.empty();
			this.body.append('<div id="OEditor-code-HTML">'+this.html+'</div>')
			this.code_editor_html = ace.edit("OEditor-code-HTML");
			this.code_editor_html.setTheme("ace/theme/xcode");
			this.code_editor_html.getSession().setValue(this.html);
			this.code_editor_html.getSession().setMode("ace/mode/html");
			
		},
		
		/*********************************************************************
			codeCSS
		*********************************************************************/
		
		codeCSS: function(){
		
			this.setValues();
			
			this.edit_mode = "css";
			this.body.empty();
			this.body.append('<div id="OEditor-code-CSS">'+this.css+'</div>')
			this.code_editor_css = ace.edit("OEditor-code-CSS");
			this.code_editor_css.setTheme("ace/theme/xcode");
			this.code_editor_css.getSession().setValue(this.css);
			this.code_editor_css.getSession().setMode("ace/mode/css");
			
		},
		
		/*********************************************************************
			setValues
		*********************************************************************/
		
		setValues: function(){
			
			switch( this.edit_mode ){
				case "css": this.css = this.code_editor_css.getSession().getValue(); break;
				case "html": this.html = this.code_editor_html.getSession().getValue(); break;
				case "wizzy": this.html = $(this.wizzywig_editor.textareaElement).val(); break;
			}
			
			console.log(this.html);
			
		}
		
	});

})( jQuery, window, document );

/*********************************************************************

	OBoxConfirm
	
*********************************************************************/

;(function ( $, window, document, undefined ) {

	$.widget( "obray.OPage", $.obray.OBox, {
		
		//Options to be used as defaults
        options: {
            attr: "",
            id: "OPage",
            html_class: "",
            header: "<h3>Page</h3>",
            body: "",
            footer: "",
            remote:"/cms/OPages/form/?layout=form-horizontal&button="+encodeURIComponent("Save Page"),
            onClick: function(){},
            onHidden: function(){},
            onSuccess: function(){},
            loaded: function(){}
        },
        
        
		
		_init: function() {
		
			this.options.remote += "&opage_title="+this.options.opage_title;
		
			this.element.data('OBox', this.element.data('OBoxConfirm'));
		},
		
		
        _setOption: function ( key, value ) {
            switch (key) {
            case "page_name":
                //this.options.someValue = doSomethingWith( value );
                console.log(key);
                break;
            default:
                //this.options[ key ] = value;
                break;
            }

            $.Widget.prototype._setOption.apply( this, arguments );
            
        }
	});

})( jQuery, window, document );



