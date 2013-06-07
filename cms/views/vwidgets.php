<ul class="thumbnails">
  
	<!-- html content -->
	
	<li id="whtml" class="span2" data-owidget-name="html">
	<div class="thumbnail">
		<img src="/obray/cms/images/widget-cube.png" alt="">
		<h4>HTML Content</h4>
		<p>Use the Wizzywig built in Editor to edit content or edit straight HTML.</p>
	</div>
	</li>
	
	<script>
		$('#whtml').on("click",function(){ 
			$.ajax({
		        "url":"/cms/OParts/add/",
		        "data":"&oarea_id="+$('#OWidgets-modal').attr("data-oarea-id")+"&opart_type=html&opart_content=Please enter your content here...",
		        "dataType":"json",
		        "error":function(data){ console.log("failed to created opart"); }.bind(this),
		        "success":function(data){ 
		        	var oarea = $("#oarea-"+$('#OWidgets-modal').attr("data-oarea-id"));
		        	oarea.find(".opart-empty").before(data.html);
		        	$('#OWidgets-modal').modal("hide");
		        	$("#obray-cancel-btn").trigger("click");
		        }.bind(this),
		        "timeout":function(){ console.log("request timed out"); }
		     });
	     });
	</script>
	
	<!-- individual widget -->
	
	<?php forEach($this->data as $widget){ ?>
	
	<li id="<?php echo $widget->folder; ?>" class="span2" data-owdiget-name="<?php echo $widget->folder; ?>">
	<div class="thumbnail">
		<img src="/obray/cms/images/widget-cube.png" alt="">
		<h4><?php echo $widget->name ?></h4>
		<p>Places a login into the page.</p>
	</div>
	</li>
	
	<script>
		$('#<?php echo $widget->folder; ?>').on("click",function(){ 
			$.ajax({
		        "url":"/cms/OParts/add/",
		        "data":"&oarea_id="+$('#OWidgets-modal').attr("data-oarea-id")+"&opart_type=html&opart_content=--"+$(this).attr('data-owdiget-name')+"--",
		        "dataType":"json",
		        "error":function(data){ console.log("failed to created opart"); }.bind(this),
		        "success":function(data){ 
		        	var oarea = $("#oarea-"+$('#OWidgets-modal').attr("data-oarea-id"));
		        	oarea.find(".opart-empty").before(data.html);
		        	$('#OWidgets-modal').modal("hide");
		        	$("#obray-cancel-btn").trigger("click");
		        }.bind(this),
		        "timeout":function(){ console.log("request timed out"); }
		     });
	     });
	</script>
	<?php } ?>
  
  
</ul>
