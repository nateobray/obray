<html>
    <head>
        <title>WDictionary</title>
        
        <?php $this->route('/cmd/lib/ORC/concatenate/?extension=css'); ?>
        <?php $this->route('/cmd/lib/ORC/concatenate/?extension=js'); ?>
        <!--[if IE 7]>
        <link rel="stylesheet" href="assets/fonts/font-awesome-ie7.min.css">
        <![endif]-->
        
        
    </head>
    <body>
        <div class="container">
            <h2>Dictionary</h2>
            <div class="row">
                <div class="span2 offset10"></div>
            </div>
            <table class="table table-striped table-hover table-bordered">
                <colgroup>
                    <col class="span1">
                    <col class="span7">
                    <col class="span1">
                </colgroup>
                <thead>
                    <tr>
                        <th class="span2">Word</th>
                        <th class="span7">Definition</th>
                        <th class="span1"><a id="add-btn" class="btn btn-block"> <i class="icon-plus"></i></a></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 0; ?>
                    <?php forEach($params as $word => $def){ ?>
                    <tr>
                        <td class="span2"><?php echo $word; ?></td>
                        <td class="span9"><?php echo $def; ?></td>
                        <td class="span1" style="text-align:center"><a id="btn-trash-<?php echo ++$i;?>" class="btn btn-danger"><i class="icon-trash"></i></a></td>
                    </tr>
                    <script>$('#btn-trash-<?php echo $i;?>').bind('click',function(){ $.ajax({'url':'<?php echo $this->delegate; ?>delete/?word=<?php echo urlencode($word)?>','complete':function(){ window.location.reload(true); }}) })</script>
                    <?php } ?>
                </tbody>
            </table>
                        
            
        </div>
        <script>
            $('#add-btn').bind('click',function(){
                $.ajax({'url':'<?php echo $this->delegate; ?>add/','complete':function(){ window.location.reload(true); }})
            });
        </script>
    </body>
</html>