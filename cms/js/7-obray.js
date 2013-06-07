
/*********************************************************************

	CONFIRM BOX
	
*********************************************************************/

(function( $ ) {
	$.fn.OConfirmBox = function(options) {
		
		var id = (options && options["id"])?options["id"]:"";
		var html_class = (options && options["class"])?options["class"]:"";
		var header = (options && options["header"])?options["header"]:"<h3>Are you sure?</h3>";
		var body = (options && options["body"])?options["body"]:"";
		var footer = (options && options["footer"])?options["footer"]:'<a id="modal-cancel-btn" href="#" class="btn">Cancel</a>';
		var onClick = (options && options["onClick"])?options["onClick"]:function(){};
		var onHidden = (options && options["onHidden"])?options["onHidden"]:function(){};
		var attr = (options && options["attr"])?options["attr"]:"";
		
		if( $('#'+id).length === 0){
			var html = '<div id="'+id+'" class="modal hide fade '+html_class+'" '+attr+' >'
				 	 + '<div class="modal-header">'
				 	 + '<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>'
				 	 + header
				 	 + '</div>'
				 	 + '<div class="modal-body">'
				 	 + body
				 	 + '</div>'
				 	 + '<div class="modal-footer">'
				 	 + footer
				 	 + '</div>'
				 	 + '</div>';
			
			$(this).append(html);
		}
		
		$('#'+id).modal(options);
		
		$('#'+id).on('hidden', function(){
			onHidden();
		}.bind(this));
	    
	};
	
})( jQuery );

/*********************************************************************

	OFORM
	
*********************************************************************/

(function( $ ) {
	$.fn.OForm = function(base_url) {
		
		var options = {};
		var btn = $(this).find(".submit-btn");
		
		btn.on("click",function(){
			
			$(this).submit();
		}.bind(this));
		
		id = this.attr("id")
		
		/*********************************************************
			Handle File Uploads
		*********************************************************/
				
		$(this).find(':file').on("change",function(){
			
			console.log("file found.");
			
			console.log(id);
			
			var name = $(this).attr("id");
			$("#"+id+" #"+name+"-progress").toggleClass("hide");
			$("#"+id+" #"+name).toggleClass("hide");
			$("#"+id+" #"+name+"-btn").toggleClass("hide");
			$("#"+id+" #"+name+"-name").toggleClass("hide");
			var fd = new FormData();
			
	        fd.append(name, document.getElementById(name).files[0]);
	        var xhr = new XMLHttpRequest();
	        
	        // handle upload progress
	        xhr.upload.addEventListener("progress", function(data){
		        $("#"+id+" #"+name+"-progress .bar").width((data.loaded/data.total*100).toPrecision(1)+'%');
	        }, false);
	        
	        // handle finished loading
	        xhr.addEventListener("load", function(data){
		        
		        data = jQuery.parseJSON(data.srcElement.response).data;
		        
		        $("#"+id+" #"+name.replace("-file","")).val(data[0]["omedia_id"]);
		        $("#"+id+" #"+name+"-progress .bar").width('0%');
		        $("#"+id+" #"+name+"-progress").toggleClass("hide");
				$("#"+id+" #"+name).toggleClass("hide");
				$("#"+id+" #"+name+"-btn").toggleClass("hide");
				$("#"+id+" #"+name+"-name").toggleClass("hide");
				$(this).parents(".control-group").addClass("success");
				$("#"+id+" #"+name+"-name").html(data[0]["omedia_file"]);
				
	        }.bind(this), false);
	        
	        // handle error
	        xhr.addEventListener("error", function(data){
		        
		        console.log(data);
		        
	        }, false);
	        
	        // handle abort
	        xhr.addEventListener("abort", function(data){
		        
		        console.log(data);
		        
	        }, false);
	        
	        xhr.open("POST", "/cms/OMedia/upload/");
	        xhr.send(fd);
			
		});
		
		/*********************************************************
			Handle Submit
		*********************************************************/
	
		this.on("submit",function() {            
	    	
		    $.ajax({
		        "url":base_url,
		        "data":$(this).serialize(),
		        "dataType":"json",
		        "error":function(data){
		        
		        	data = $.parseJSON(data.responseText);
		        	var html = '<div class="alert alert-error">';
					html +='<button type="button" class="close" data-dismiss="alert">&times;</button>';  		
					html += '<strong>Error:</strong> '+data.errors["general"]+'<br/><br/><ul>';
					delete data.errors['general'];
					for( var field in data.errors ){ 
						html += "<li>" + data.errors[field] + "</li>"; 
						$('#'+field.replace("_","-")).parents(".control-group").toggleClass("error");
					}
					html += "</ul>";
					html +='</div>';
					$(this).prepend(html);
			        
			        
		        }.bind(this),
		        "success":function(data){
		        
		        	$(this).find(".control-group").removeClass("error").removeClass("warning").removeClass("info").addClass("success");
		        
		        	var html = '<div class="alert alert-success">'
					  		 + '<button type="button" class="close" data-dismiss="alert">&times;</button>'
					  		 + '<strong>Page Saved!</strong> The page has been saved successfully.'
					  		 + '</div>';
		            $(this).prepend(html);
		            
		            this.trigger("success");
			        
		        }.bind(this),
		        "timeout":function(){
		        	
		        	$(this).find(".control-group").removeClass("error").removeClass("info").removeClass("success").addClass("warning");
					
			        var html = '<div class="alert">'
					  		 + '<button type="button" class="close" data-dismiss="alert">&times;</button>'
					  		 + '<strong>Time Out</strong> The form timed out and was unable to be processed.'
					  		 + '</div>';
		            $(this).prepend(html);
			        
		        }
		    });
		    
		    return false;
		}.bind(this));
	    
	};
	
})( jQuery );


(function( $ ) {
	$.fn.addMenuItem = function(class_name,item,fn) {
		
		var menu = $(this).find(".btn-group .dropdown-menu");
		menu.prepend('<li class="divider"></li>');
		menu.prepend('<li><a class="'+class_name+'" href="#">'+item+'</a></li>');
		
		$('.'+class_name).on("click",fn);
	    
	};
	
})( jQuery );

(function( $ ) {
	$.fn.reset = function(class_name,item,fn) {
		
		var id = $(this).attr("data-opart-id");
		
		$.ajax({
		    "url":'/cms/OParts/out/',
		    "data":"&opart_id="+id,
		    "dataType":"json",
		    "success":function(data){ 
		    	$(this).replaceWith(data.html);
		    	$("#obray-cancel-btn").trigger("click");
		    }.bind(this)
		});
	    
	    
	};
	
})( jQuery );

/*********************************************************************

	OBRAY
	
*********************************************************************/

(function( $ ) {
	$.fn.Obray = function() {
		
		$("#obray-cancel-btn").detach();
		$("#obray-edit-btn").detach();
		$(this).append('<button id="obray-edit-btn" type="submit" class="btn oedit-btn" ><i class="icon-edit"></i></button>');
		
		$("#obray-edit-btn").on("click",function(){
				
			$(".oarea").append('<button type="submit" class="btn obray-active oarea-add-btn" ><i class="icon-plus"></i></button>');
			$(".opart").append('<button type="submit" class="btn obray-active opart-edit-btn" ><i class="icon-edit"></i></button>');
			$(".opart").append('<div class="btn-group opart-cog-btn obray-active"><a class="btn dropdown-toggle" data-toggle="dropdown" href="#"><i class="icon-cogs"></i></a><ul class="dropdown-menu">'
							  +'<li><a class="opart-resize-btn" href="#"><i class="icon-resize-horizontal"></i> Resize</a></li>'
							  +'<li><a class="opart-margin-btn" href="#"><i class="icon-pencil"></i> Margins & Padding</a></li>'
							  +'<li class="divider"></li>'
							  +'<li><a class="opart-delete-btn" href="#"><i class="icon-remove-sign">&nbsp; Delete</a></li>'
							  +'</ul></div>');
			
			/*********************************************************
				Handle Widgets
			*********************************************************/
			
			var widgets = $('.widget');
			
			for( var i=0;i<widgets.length;++i ){
				
				if( $(widgets[i]).parents(".opart")[$(widgets[i]).attr("data-widget")] ){
					$(widgets[i]).parents(".opart")[$(widgets[i]).attr("data-widget")]();
				}
			}
			
			
			$(".omedia-links:empty").each(function(i){
				var width = $(this).attr("data-omedia-width");
				var height = $(this).attr("data-omedia-height");
				var name = $(this).attr("data-link-name");
				$(this).append('<div class="omedia-empty obray-active" style="width:'+width+'px;height:'+height+'px;"><i class="icon-picture"></i></div>');
			});
			
			$(".omedia-links").append('<div class="btn-group omedia-cog-btn obray-active"><a class="btn dropdown-toggle" data-toggle="dropdown" href="#"><i class="icon-cogs"></i></a><ul class="dropdown-menu">'
							  +'<li><a class="omedia-upload-btn" href="#"><i class="icon-upload"></i> Upload</a></li>'
							  +'<li class="divider"></li>'
							  +'<li><a class="omedia-delete-btn" href="#"><i class="icon-remove-sign">&nbsp; Delete</a></li>'
							  +'</ul></div>');
			
			$(".omedia-upload-btn").on('click',function(){ 
				
				$("#obray-cancel-btn").trigger("click");
				
				$("body").OBox({
					"id":"OWidgets-modal",
					"header":'<h3><i class="icon-picture muted"></i> Upload New Image</h3>',
					"remote":"/cms/OMediaLinks/form/?layout=form-horizontal&omedia_link_name="+$(this).parents(".omedia-links").attr("data-link-name")+"&button="+encodeURIComponent('<i class="icon-save"></i>&nbsp; Save Image'),
					"onHidden":function(){
						
					}
				});
				
			});
			
			$(".omedia-delete-btn").on('click',function(){ 
				
				var id = $(this).parents(".omedia-links").attr("data-link-id");
				$("body").OBoxConfirm({"id":"omedia-delete-confirmation",
									   "body":"<p>Are you sure you want to permenantly delete this image?</p>",
									   "footer":'<a id="omedia-confirm-cancel-btn" class="btn" >Cancel</a><a id="omedia-confirm-delete-btn" class="btn btn-danger" href="#"><i class="icon-trash"></i> Delete</a>',
									   });
				
				$("#omedia-confirm-delete-btn").on("click",function(){
					$.ajax({
				        "url":'/cms/OMediaLinks/delete/',
				        "data":"&omedia_link_id="+id,
				        "dataType":"json",
				        "success":function(data){ 
				        	$("#omedia-delete-confirmation").modal("hide");
				        	$(this).parents(".omedia-links").detach(); 
				        }.bind(this)
					});
				}.bind(this));
				
				$("#opart-confirm-cancel-btn").on("click",function(){ $("#opart-delete-confirmation").modal("hide"); });
				
			});
			
			$(".oarea").append('<div class="opart-empty obray-active"><i class="icon-edit"></i></div>');
			
			/*********************************************
				OArea Add Button
			*********************************************/
			
			$(".oarea-add-btn").on("click",function(){
				var id = $(this).parent(".oarea").attr("data-oarea-id");
				$('body').OBox({"id":"OWidgets-modal","header":"<h3>Widgets</h3>","body":'<p>Select a widget below to add to this content area.</p>',"footer":'',"remote":'/cms/OWidgets/out/',"attr":' data-oarea-id="'+id+'" '});
			});
			
			/*********************************************
				OPart edit Button
			*********************************************/
			
			$(".opart-edit-btn").on("click",function(){
				
				var id = $(this).parent(".opart").attr("data-opart-id");
				var original = $(this).parent(".opart")
				var parent = original.clone();
				parent.find(".obray-active").detach();
				$("#obray-cancel-btn").trigger("click");
				var widget = parent.find(".widget").attr("data-widget");
				parent.find(".widget").replaceWith("--"+widget+"--");
				var content = parent.html();
				
				$.obray.OBoxEditor({"primary_key_value":id,"content":content,"parent":original});
				
			});
			
			/*********************************************
				OPart resize Button
			*********************************************/
			
			$(".opart-resize-btn").on("click",function(){
				
				var id = $(this).parents(".opart").attr("data-opart-id");
				
				if( this.is_resizable ){
					this.is_resizable = false;
					$(this).parents(".opart").resizable("destroy");
					$(this).html('<i class="icon-resize-horizontal"></i> Resize');
				} else {
				
					this.is_resizable = true;
					$(this).parents(".opart").resizable({
						"handles": "se, e",
						"stop": function( event, ui ) {
							
							min_width = Math.round((86/ui.element.parent().width())*100);
							percent = Math.round((ui.element.width()/ui.element.parent().width())*100);
							if(percent > 100){ percent = 100; } else if( percent < min_width ){ percent = min_width; }
							ui.element.css({"width":percent+'%',"height":"auto"});
							
							$.ajax({
						        "url":'/cms/OParts/update/',
						        "data":"&opart_id="+id+"&opart_width="+(percent),
						        "dataType":"json",
						        "success":function(data){ 
						        	
						        }.bind(this)
							});
						}
					});
					
					$(this).html('<i class="icon-resize-horizontal"></i> Cancel Resize');
				
				}
				
			});
			
			/*********************************************
				OPart margin Button
			*********************************************/
			
			$(".opart-margin-btn").on("click",function(){
				
			});
			
			/*********************************************
				OPart delete Button
			*********************************************/
			
			$(".opart-delete-btn").on("click",function(){
				
				var id = $(this).parents(".opart").attr("data-opart-id");
				$("body").OBoxConfirm({"id":"opart-delete-confirmation",
									   "body":"<p>Are you sure you want to permenantly delete this content?</p>",
									   "footer":'<a id="opart-confirm-cancel-btn" class="btn" >Cancel</a><a id="opart-confirm-delete-btn" class="btn btn-danger" href="#"><i class="icon-trash"></i> Delete</a>',
									   });
				
				$("#opart-confirm-delete-btn").on("click",function(){
					$.ajax({
				        "url":'/cms/OParts/delete/',
				        "data":"&opart_id="+id,
				        "dataType":"json",
				        "success":function(data){ 
				        	$("#opart-delete-confirmation").modal("hide");
				        	$(this).parents(".opart").detach(); 
				        }.bind(this)
					});
				}.bind(this));
				
				$("#opart-confirm-cancel-btn").on("click",function(){ $("#opart-delete-confirmation").modal("hide"); })
				
			});
			
			$('body').ObrayCancel();
			
			
			
		}.bind(this));
		
	    
	};
	
})( jQuery );

/*********************************************************************

	OBRAY
	
*********************************************************************/

(function( $ ) {
	$.fn.ObrayCancel = function() {
			
		$("#obray-cancel-btn").detach();
		$("#obray-edit-btn").detach();
		$(this).append('<button id="obray-cancel-btn" type="submit" class="btn oedit-btn" ><i class="icon-remove"></i></button>');
		
		$("#obray-cancel-btn").on("click",function(){
			
			$('body').Obray();
			$(".obray-active").detach();
			
		}.bind(this));
		
	    
	};
	
})( jQuery );








