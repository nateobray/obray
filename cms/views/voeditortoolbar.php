<div id="wysihtml5-toolbar" style="padding:5px 0px;">
	<div class="btn-group">
		<a data-wysihtml5-command="bold" class="btn btn-icon"><i class="icon-bold"></i></a>
		<a data-wysihtml5-command="italic" class="btn btn-icon"><i class="icon-italic"></i></a>
		<a data-wysihtml5-command="createLink" class="btn btn-icon"><i class="icon-link"></i></a>
	</div>
			  		
	<div class="btn-group">
		<a class="btn dropdown-toggle" data-toggle="dropdown" href="#">Format <span class="caret"></span></a>
		<ul class="dropdown-menu">
			<li><a data-wysihtml5-command="formatBlock" data-wysihtml5-command-value="p">Paragraph</a></li>
			<li><a data-wysihtml5-command="formatBlock" data-wysihtml5-command-value="h1">heading 1</a></li>
			<li><a data-wysihtml5-command="formatBlock" data-wysihtml5-command-value="h2">heading 2</a></li>
			<li><a data-wysihtml5-command="formatBlock" data-wysihtml5-command-value="h3">heading 3</a></li>
			<li><a data-wysihtml5-command="formatBlock" data-wysihtml5-command-value="h4">heading 4</a></li>
			<li><a data-wysihtml5-command="formatBlock" data-wysihtml5-command-value="blockquote">blockquote</a></li>
		</ul>
	</div>
			  		
	<div class="btn-group">
		<a data-wysihtml5-command="insertOrderedList" class="btn btn-icon"><i class="icon-list-ol"></i></a>
		<a data-wysihtml5-command="insertUnorderedList" class="btn btn-icon"><i class="icon-list-ul"></i></a>
	</div>
			  		
	<div class="btn-group">
		<a data-wysihtml5-command="justifyLeft" class="btn btn-icon"><i class="icon-align-left"></i></a>
		<a data-wysihtml5-command="justifyCenter" class="btn btn-icon"><i class="icon-align-center"></i></a>
		<a data-wysihtml5-command="justifyRight" class="btn btn-icon"><i class="icon-align-right"></i></a>
	</div>
			  		
	<div class="btn-group">
		<a id="OEditor-wizzy-btn" class="btn btn-text">Wzy</a>
		<a id="OEditor-html-btn" class="btn btn-text">HTML</a>
		<a id="OEditor-css-btn" class="btn btn-text">CSS</a>
	</div>
	
	<div class="btn-group">
		<a class="btn dropdown-toggle" data-toggle="dropdown" href="#"><i class="icon-cogs"></i> <span class="caret"></span></a>
		<ul class="dropdown-menu">
			<?php forEach($this->data as $widget){ ?>
			<li><a data-wysihtml5-command="insertHTML" data-wysihtml5-command-value="--<?php echo $widget->folder; ?>--"><?php echo $widget->name; ?></a></li>
			<?php } ?>
		</ul>
	</div>
			  		
</div>