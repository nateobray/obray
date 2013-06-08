<?php

	/***********************************************************************
	
	Obray - Super lightweight framework.  Write a little, do a lot, fast.
    Copyright (C) 2013  Nathan A Obray

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    ***********************************************************************/

	if (!class_exists( 'OObject' )) { die(); }
	
	/********************************************************************************************************************
		
		ODB:	This is the database interface object built specifically for MySQL and MariaDB.  It is also designed to
		        to aid in quickly generating HTML forms the table definition.  The goal here is easily mange the data
		        from retrieval to input on a client application all while maintaining flexibility.
		
		
		Table Definition:
		
		      DATABASE ATTRIBUTES
		          
		          name:               (sring) - column name (THIS IS THE ARRAY KEY)
		          data_type:          (integer|float|varchar(length)|boolean|text|timestamp) - column data type
		          primary_key:        (TRUE|FALSE) - true if the column is a primary key; this will setup the column up
		                              as an identity column with autoincrement.  Data type must be an integer.                    
		                              
		      
		      FORM ATTRIBUTES
		          
		          label:              (string) - the text used for <label>
		          field_type:         (textbox|textarea|datetime|checkbox|radio|select) - the type of HTML input field to generate
		          labels:             (array) - applies to (select|radio|checkbox) and is an array containing the labels.
		          values:             (array) - applies to (select|radio|checkbox) and is an array containing the values that correspond to the labels above
		          enable_icon:        (icon css class) - allows you to add an icon to your forms HTML
		          
		          
		      VALIDATION ATRIBUTES    
		          
		          required:           (TRUE|FALSE) - if the field must contain a value when submitted.  If not it will cause an error
		          required_error:     (string) - a message to display that overrides the default error message displayed when a required field is left blank
		          format:             (regex) - contains a regex to test the data agains.  If it does not match an error is shown
		          format_error:       (string) - message to be shown when the value does not match the regex format
		          tooltip:            (string) - the message to be displayed in the tooltip, helpful when wanting to explain about the field
		          
		      RELATIONSHIP ATTRIBUTES
		      
		          foriegn_key:        (integer) - specify the id parameter for another route (must also specify the foriegn_route)
		          foriegn_route:      (string - must be a valid route) - specify the route to cally passing in the foriegn_key.  It will be called like: '/cmd/your/path/object/get/?[primary_key]={foriegn_key} 
		          children:           (array of children fields) - must specify the children to report his value to when the value changes (done via ajax)
		          
		      
		      Example Table Definition:
		      
		      $this->table_definition["id"] = array(
		          "primary_key":TRUE,
		          "data_type":"integer"
		      )
		      $this->table_definition["first_name"] = array(
		          "data_type"=>"varchar(150)",
		          "label"=>"First Name:",
		          "required"=>TRUE,
		          
		      );
		      
	********************************************************************************************************************/
	
	Class ODBO extends OObject {
	
	    private $dbh;
	    private $primary_key_column;
	    private $data_types;
	    private $enable_column_additions = TRUE;
	    private $enable_column_removal = TRUE;
	    private $enable_data_type_changes = TRUE;
			    
	    public function __construct(){
	    
	       if( !isSet($this->table) ){ $this->table = ""; } 
	       if( !isSet($this->table_definition) ){ $this->table_definition = array(); } 
	       if( !isSet($this->primary_key_column) ){ $this->primary_key_column = ""; }
	       
	       if( !defined("__DATATYPES__") ){
	       
    	       define ("__DATATYPES__", serialize (array (
                    "varchar"   =>  array("sql"=>" VARCHAR(size) COLLATE utf8_general_ci ","validation_regex"=>""),
                    "text"      =>  array("sql"=>" TEXT COLLATE utf8_general_ci ","validation_regex"=>""),
                    "integer"   =>  array("sql"=>" INT ","validation_regex"=>""),
                    "float"     =>  array("sql"=>" FLOAT ","validation_regex"=>""),
                    "boolean"   =>  array("sql"=>" BOOLEAN ","validation_regex"=>""),
                    "datetime"  =>  array("sql"=>" DATETIME ","validation_regex"=>""),
                    "password"  =>  array("sql"=>" VARCHAR(255) ","validation_regex"=>"")
                )));
                
            }
            
	    }
	    
	    public function setDatabaseConnection($dbh){ 
	       
	       if( !isSet($this->table) || $this->table == '' ){ return; }
	       
	       $this->dbh = $dbh; 
	       if(isSet($_REQUEST["refresh"]) && __DebugMode__){ $this->scriptTable(array()); }
	       if( __DebugMode__ == TRUE && isSet($_REQUEST["refresh"]) ){ $this->alterTable(); }
	       
	    }
	    
        /*************************************************************************************************************
            
            Script Table
            
            	What this does:
            	
            		1.  Script a table new based on the table definition ($_RQUEST["refresh"] must be set )
            		
            	What this doesn't do
            	
            		1.	Will only script datatypes set in the __DATATYPES__ constant
            
        *************************************************************************************************************/
        
        public function scriptTable($params){
           
           if( empty($this->dbh) ){ return $this; }
           
           /*************************************************************************************************************
				
				WRITE SQL FROM TABLE DEFINITION
				
				Loop through the table definition array	and write the approriate SQL.  If the "store" parameter exists 
				in a definition	then skip and move on to the next definition.
				
           **************************************************************************************************************/
           
           $sql = "";
           $data_types = unserialize(__DATATYPES__);
           
           forEach($this->table_definition as $name => $def){
                if( array_key_exists("store",$def) == FALSE || (array_key_exists("store",$def) == TRUE && $def["store"] == TRUE ) ){
                    
                    if( !empty($sql) ){ $sql .= ","; }                                                                     // add comma                                                                               // column name
					if( isSet($def["data_type"]) ){                                                                        // if no data type is found don't use it
    					$data_type = $this->getDataType($def);
    					$sql .= $name . str_replace('size',str_replace(')','',$data_type["size"]),$data_types[$data_type["data_type"]]["sql"]);  // generate SQL
					} 
					
					if( array_key_exists("primary_key",$def) && $def["primary_key"] === TRUE  ){                           // write SQL for key column if it exists
						$this->primary_key_column = $name;                                                                 // set the key column variable
						$sql .= $name . " INT UNSIGNED NOT NULL AUTO_INCREMENT ";                                          // write SQL
					}
                }
           }
           
           $sql = "CREATE TABLE IF NOT EXISTS " . $this->table . " ( " . $sql;                                             // prepend CREATE logic
           
           /*************************************************************************************************************
           	
           	    ADD CREATE AND MODIFY TRACKING FIELDS
				
					slug 				- used to create a unique identifier the is still "human readable" but reliable for identification (must be unique)
					order_variable		- used to keep track of ordering
					parent_id			- used to track the parent_id of the row (useful for creating trees and relationships)
					OCDT				- tracks the create date of a specific record
					OCU					- tracks the user that created the record.  If no one is logged in this should be 0
					OMDT				- tracks the modify date of a specific record
					OMU					- tracks the user that modified the record.  If no one is logged in this should be 0
					
			*************************************************************************************************************/
			
			$sql .= ", slug VARCHAR(255), order_variable INT UNSIGNED, parent_id INT UNSIGNED, OCDT DATETIME, OCU INT UNSIGNED, OMDT DATETIME, OMU INT UNSIGNED ";
			
            /*************************************************************************************************************
				ASSIGN PRIMARY KEY AND DEFUALT CHARSET (utf8 for multilangual support)
			*************************************************************************************************************/

			if( !empty($this->primary_key_column) ){ $sql .= ", PRIMARY KEY (" . $this->primary_key_column . ") ) ENGINE=".__DBEngine__." DEFAULT CHARSET=".__DBCharSet__."; "; }
			
			/*************************************************************************************************************
				RUN SQL
			*************************************************************************************************************/
			$this->sql = $sql;
			$statement = $this->dbh->prepare($sql);  
			$this->script = $statement->execute();
            
        }
        
        /*************************************************************************************************************
            
            Alter Table
            
            	What it does:
            		
            		1.	Update field data type if table is different from table definition.
            		2.	Remove columns that don't exist in the table definition if enableDrop
			        	is set.
			        3.	Add fields if they don't exist in the table but do in the table definition
            		
            	What it does not do:
            	
            		1.	Will not change a column name (WARNING: if enableDrop is set it will drop the old column and 
            			add the new	which may result in data loss)
            		
            	WARNING
            	
            		DATA LOSS IS POSSIBLE: 	Certain combinations like setting a size smaller than existing data will
            								result in data loss.  As a result this will attempt to dump the table
            								to _SELF_/backups/ if there are sufficient priveldeges to this path.
            
        *************************************************************************************************************/
        
        public function alterTable(){
        	
        	if( empty($this->dbh) ){ return $this; }
        	
        	$this->dump();
        	
        	$sql = "DESCRIBE $this->table;";
        	$statement = $this->dbh->prepare($sql);
        	$statement->execute();
        	
        	$statement->setFetchMode(PDO::FETCH_OBJ);
        	$data = $statement->fetchAll();
        	
        	$temp_def = $this->table_definition;
        	$obray_fields = array(0=>"slug",1=>"order_variable",2=>"parent_id",3=>"OCDT",4=>"OCU",5=>"OMDT",6=>"OMU");
        	forEach( $obray_fields as $of ){ unset($this->table_definition[$of]); }
        	
        	$data_types = unserialize(__DATATYPES__);
        	
        	forEach($data as $def){
        	
        		if( array_key_exists("store",$def) == FALSE || (array_key_exists("store",$def) == TRUE && $def["store"] == TRUE ) ){
        	
	        	if( array_search($def->Field,$obray_fields) === FALSE ){
		        	if( isSet($this->table_definition[$def->Field]) ){
			        	
			        	
			        	/*********************************************************************************
			        		
			        		1.	Update field data type if table is different from table definition.
			        		
			        	*********************************************************************************/
			        	
			        	if( $this->enable_data_type_changes && isSet($this->table_definition[$def->Field]["data_type"]) ){
			        		$data_type = $this->getDataType($this->table_definition[$def->Field]);
			        		
			        		if( str_replace('size',$data_type["size"],$data_types[$data_type["data_type"]]["my_sql_type"]) != $def->Type ){
				        		if( !isSet($this->table_alterations) ){ $this->table_alterations = array(); }
				        		$sql = "ALTER TABLE $this->table MODIFY COLUMN ".$def->Field." ".str_replace('size',$data_type["size"],$data_types[$data_type["data_type"]]["sql"]);
				        		$statement = $this->dbh->prepare($sql);
				        		$this->table_alterations[] = $statement->execute();
				        		
			        		} 
				        	
			        	}
			        	
			        	unset( $this->table_definition[$def->Field] );
			        	
			        	/*********************************************************************************
			        		
			        		2.	Remove columns that don't exist in the table definition if enableDrop
			        			is set.
			        		
			        	*********************************************************************************/
			        	
		        	} else {
			        	if( $this->enable_column_removal && isSet($_REQUEST["enableDrop"]) ){ 
			        		if( !isSet($this->table_alterations) ){ $this->table_alterations = array(); }
    						$sql = "ALTER TABLE $this->table DROP COLUMN $def->Field";
			        		$statement = $this->dbh->prepare($sql);
			        		$this->table_alterations[] = $statement->execute();
			        	}
		        	}
	        	}
	        	
	        	}
        	}
        	
        	/*********************************************************************************
        		
        		3.	Add fields if they don't exist in the table but do in the table definition
        		
        	*********************************************************************************/
        	
        	if( $this->enable_column_additions ){
	        	forEach($this->table_definition as $key => $def){
	        		if( array_key_exists("store",$def) == FALSE || (array_key_exists("store",$def) == TRUE && $def["store"] == TRUE ) ){
		        		if( !isSet($this->table_alterations) ){ $this->table_alterations = array(); }
		        		$data_type = $this->getDataType($def);
			        	$sql = "ALTER TABLE $this->table ADD ($key ".str_replace('size',$data_type["size"],$data_types[$data_type["data_type"]]["sql"]).")";
		        		$statement = $this->dbh->prepare($sql);
		        		$this->table_alterations[] = $statement->execute();
	        		}
	        	}
        	}
        	
        	$this->table_definition = $temp_def;
        	
        }
        
        public function getTableDefinition(){
	        
	        $this->data = $this->table_definition;
	        
        }
        
        /********************************************************************
            
            ADD function
            
        ********************************************************************/
        
        public function add($params=array()){
        
        	if( empty($this->dbh) ){ return $this; }
        	// generate prepared statement
        	$sql = "";
        	$sql_values = "";
        	$data = array();
        	$this->data_types = unserialize(__DATATYPES__);
        	
        	forEach($this->table_definition as $name => $def){
        		
        		if( array_key_exists("primary_key",$def) && $def["primary_key"] === TRUE  ){
					$this->primary_key_column = $name;
				}
        		
				// validate
				$data_type = $this->getDataType($def);
				if( isSet($def["required"]) && $def["required"] === TRUE && (!isSet($params[$name]) || $params[$name] === NULL || $params[$name] === "") ){ $this->throwError(isSet($def["error_message"])?$def["error_message"]:isSet($def['label'])?$def['label']." is required.":$name." is required.",'500',$name); }
        	   
				if( isSet($params[$name]) ){
				
					if( isSet($def["data_type"]) && !empty($this->data_types[$data_type["data_type"]]["validation_regex"]) && !preg_match($this->data_types[$data_type["data_type"]]["validation_regex"],$params[$name]) ){
					   $this->throwError(isSet($def["error_message"])?$def["error_message"]:isSet($def['label'])?$def['label']." is invalid.":$name." is invalid.",'500',$name);
					}
					
					if( isSet($def["data_type"]) && $def["data_type"] == "password" ){ 
							$salt = "$2a$12$".$this->route('/core/OUtilities/generateToken/')->token;
							$params[$name] = crypt($params[$name],$salt); 
					}
					
					// is slug
					if( isSet($def["slug"]) && $def["slug"] === TRUE ){ $slug_column = $name; }
					
					// is order_key
					if( isSet($def["order_key"]) && $def["order_key"] == TRUE ){ $this->order_key = $name; $order_value = $params["order_key"]; } else { $this->order_key = "parent_id"; $order_value = isSet($params["parent_id"])?$params["parent_id"]:0; }
					
					if( isSet($params[$name]) ){
					   if( !empty($sql) ){ $sql .= ","; $sql_values .= ","; }
					   $sql .= $name; $sql_values .= ":$name";
					}
				}
        	}
        	
        	if( $this->isError() ){ $this->throwError(isSet($this->general_error)?$this->general_error:"There was an error on this form, please make sure the below fields were completed correclty: "); return $this; }
        	
        	$this->reorder(1,$this->order_key,$order_value);
        	
        	if( !isSet($params["parent_id"]) ){ $params["parent_id"] = 0; }
        	if( isSet($slug_column) && isSet($params[$slug_column]) ){ $params["slug"] = $this->getSlug($params[$slug_column],$slug_column); } else { $params["slug"] = ""; }
        	$this->sql  = " insert into $this->table ( ".$sql.", slug, order_variable, parent_id, OCDT, OCU ) values ( ".$sql_values.", :slug, 1, :parent_id, NOW(), 0 ) ";
        	$statement = $this->dbh->prepare($this->sql);
        	
        	unset($params["refresh"]);
        	
        	$this->script = $statement->execute($params);
			
			$this->route('/get/?where=('.$this->primary_key_column.'='.$this->dbh->lastInsertId().')');
        	
        }
        
        /********************************************************************
            
            UPDATE function
            
        ********************************************************************/
        
        public function update($params=array()){
        	
        	if( empty($this->dbh) ){ return $this; }
        	// generate prepared statement
        	$sql = "";
        	$sql_values = "";
        	$data = array();
        	$this->data_types = unserialize(__DATATYPES__);
        	
        	if( !isSet($this->data) ){
	        	forEach($this->table_definition as $name => $def){
	        		if( array_key_exists("primary_key",$def) && $def["primary_key"] === TRUE  ){  if(isSet($params[$name])){ $this->get(array($name=>$params[$name])); }  }
	        		if(isSet( $params[$name] )){ $this->data[0]->$name = $params[$name]; }
	        	}
        	}
        	
        	forEach( $params as $key => $value ){ if(!array_key_exists($key, $this->table_definition)){ unset($params[$key]); } }
        	
        	forEach($this->data as $i => $row){
        	
	        	forEach($this->table_definition as $name => $def){
	        		
					if( array_key_exists("primary_key",$def) && $def["primary_key"] === TRUE  ){
							$this->primary_key_column = $name;
							$params[$name] = $row->$name;
					} else {	
					
						if( isSet($params[$name]) ){
							// validate
							$data_type = $this->getDataType($def);
							if( isSet($def["required"]) && $def["required"] === TRUE && (!isSet($params[$name]) || $params[$name] === NULL || $params[$name] === "") ){ 
									$this->throwError(isSet($def["error_message"])?$def["error_message"]:isSet($def['label'])?$def['label']." is required.":$name." is required.",500,$name); 
							}
							
							if( isSet($params[$name]) ){
							
								if( isSet($def["data_type"]) && !empty($this->data_types[$data_type["data_type"]]["validation_regex"]) && !preg_match($this->data_types[$data_type["data_type"]]["validation_regex"],$params[$name]) ){
									$this->throwError(isSet($def["error_message"])?$def["error_message"]:isSet($def['label'])?$def['label']." is invalid.":$name." is invalid.",500,$name);
								}
								
								// is slug
								if( isSet($def["slug"]) && $def["slug"] === TRUE ){ $slug_column = $name; }
								
								// is order_key
								if( isSet($def["order_key"]) && $def["order_key"] == TRUE ){ $this->order_key = $name; $order_value = $params["order_key"]; } else { $this->order_key = "parent_id"; $order_value = isSet($params["parent_id"])?$params["parent_id"]:0; }
								
								if( isSet($params[$name]) ){
									if( !empty($sql) ){ $sql .= ","; $sql_values .= ","; }
									$sql .= $name . " = :$name ";
								}
							}
						}
					}
	        	}
	        	
	        	
	        	if( empty($this->primary_key_column) ){ $this->throwError('Please specify a primary key.','primary_key','500'); }
	        	if( !isSet( $params[$this->primary_key_column] ) ){ $this->throwError('Please specify a value for the primary key.','500',$this->primary_key_column); }
	        	
	        	if( $this->isError() ){ return $this; }
	        	
	        	$this->reorder(1,$this->order_key,$order_value);
	        	
	        	if( isSet($params["parent_id"]) ){ $sql .= " ,parent_id = :parent_id"; }
	        	if( isSet($slug_column) && isSet($params[$slug_column]) ){ $params["slug"] = $this->getSlug($params[$slug_column],$slug_column); $sql .= " ,slug = :slug"; }
	        	$this->sql  = " UPDATE $this->table SET $sql WHERE $this->primary_key_column = :$this->primary_key_column ";
	        	
	        	$statement = $this->dbh->prepare($this->sql);
	        	
	        	$this->script = $statement->execute($params);
	        	
        	}
        	
        	$this->params = $params;
        	
        }
        
        /********************************************************************
            
            DELETE function
            
        ********************************************************************/
        
        public function delete($params=array()){
        	
        	if( empty($this->dbh) ){ return $this; }
        	if( empty($this->primary_key_column) ){
	        	forEach($this->table_definition as $name => $def){
					if( array_key_exists("primary_key",$def) && $def["primary_key"] === TRUE  ){                          // write SQL for key column if it exists
							$this->primary_key_column = $name;                                                            // set the key column variable	
					}
				}
        	}
        	
        	if( empty($this->primary_key_column) ){ $this->throwError('Please specify a primary key.','500','primary_key'); }
        	if( !isSet( $params[$this->primary_key_column] ) ){ $this->throwError('Please specify a value for the primary key.','500',$this->primary_key_column); }
        	
        	$this->sql  = " DELETE FROM $this->table WHERE  $this->primary_key_column = :$this->primary_key_column ";
        	$statement = $this->dbh->prepare($this->sql);
        	$this->script = $statement->execute($params);
        	
        	
        }
        
        /********************************************************************
            
            GET function
            
        ********************************************************************/
        
        public function get($params){
        	
        	$original_params = $params;
        	if( !isSet($params["where"]) ){ $params["where"] = array("="=>$params); }
        	if( isSet($params["with"]) ){ $with = explode('|',$params["with"]); unset($params["with"]); } else { $with = array(); }
        	
        	if( empty($this->dbh) ){ return $this; }
        	$where = "";
        	
        	if( isSet($params["where"]["="]["parent_id"]) ){ $where .= " " . $this->table . '.parent_id = :parent_id '; $parent_id = $params["where"]["="]["parent_id"]; unset($params["parent_id"]); }
        	if( isSet($params["where"]["="]["slug"]) ){ if(!empty($where)){ $where .= " AND "; } $where .= " " . $this->table . '.slug = :slug '; $slug = $params["where"]["="]["slug"]; unset($params["slug"]); }
        	
        	$components = $this->getQueryComponents($params);
        	
        	$params = $components->params;
        	if( isSet($parent_id) ){ $params["parent_id"] = $parent_id; }
        	if( isSet($slug) ){ $params["slug"] = $slug; }
        	
        	$components->columns .= ', '.$this->table.'.parent_id';
        	$this->sql = ' SELECT ' .$components->columns . ' FROM ' . $this->table . $components->from . ' ';
        	if( !empty($components->where) ){ $this->sql .= ' WHERE ' . $components->where; }
        	if( !empty($where) && empty($components->where) ){ $this->sql .= ' WHERE ' . $where; } else if( !empty($where) ) { $this->sql .= ' AND ' . $where; }
        	
        	$statement = $this->dbh->prepare($this->sql);
        	$statement->execute($params);
        	$statement->setFetchMode(PDO::FETCH_OBJ);
        	$this->data = $statement->fetchAll();
        	
        	$this->params = $params;
        	
        	forEach( $this->data as $i => $row ){
        		forEach($this->table_definition as $key => $def){
	        		if( isSet($def['foriegn_objects'])  ){
		        		forEach($def['foriegn_objects'] as $object => $details){
		        			if( array_search($object,$with) !== FALSE ){
		        				//echo $query_string;exit();
		        				if( isSet($original_params["parent_id"]) ){ unset($original_params["parent_id"]); }
		        				if( isSet($original_params["slug"]) ){ unset($original_params["slug"]); }
		        				$query_string = http_build_query($original_params);
			        			$this->data[$i]->$object = $this->route($details["path"].'?'.$details["column"].'='.$row->$key.'&'.$query_string)->data;
			        			if( count($this->data[$i]->$object) === 0 ){ unset($this->data[$i]); }
			        		}
		        		}
	        		}
        		}
        	}
        	
        	if( isSet($components->password) ){
	        	for( $i=0;$i<count($this->data);++$i ){
	        		$key = $components->password_key;
	        		if( strcmp($this->data[$i]->$key,crypt($components->password,$this->data[$i]->$key)) !== 0 ){ unset($this->data[$i]); } 
	        	}
        	}
        	
        }
        
        private function mergeParams($eq,$neq,$gte,$lte,$gt,$lt,$like){ return array("="=>$eq,"!="=>$neq,">="=>$gte,"<="=>$lte,">"=>$gt,"<"=>$lt,"like"=>$like); }
        
        /********************************************************************
            
            GENERATE QUERY COMPONENTS (SELECT, FROM, WHERE)
            
        ********************************************************************/
        
        public function getQueryComponents($params){
	        
	        $obj = new stdClass;
	        $obj->columns = "";
	        $obj->from = "";
	        $obj->table = $this->table;
	        $obj->joins = array();
	        $obj->primary_key = $this->primary_key_column;
	        $obj->where = '';
	        $obj->params = $params;
	        
	        
	        /**************************************************************
	        	collect columns
	        **************************************************************/
	        
	        forEach($this->table_definition as $name => $def){
        		
        		if( array_key_exists("primary_key",$def) && $def["primary_key"] === TRUE  ){
					$this->primary_key_column = $name;
				}
			
        		if( isSet($def["innerjoin"]) ){
        			//$join = $this->route($def["innerjoin"]);
        			//$join = $join->getQueryComponents($obj->params);
        			//$join->local_column = $name;
        			//$join->column = $def["on"];
        			//$obj->params = $join->params;
        			//$obj->where = $join->where;
        			
        		}
        		
        		// if( isSet($def["data_type"]) ){ unset($this->table_definition[$name]); }
        		
		        if( !empty($obj->columns) ){ $obj->columns .= ' ,'; }
		        $obj->columns .= " " . $this->table ."." . $name;
		        if( isSet($join) ){
		        	$obj->columns .= ', ' . $join->columns;
					$obj->joins[] = $join;
		        }
	        	unset($join);
        	}
        	
        	/**************************************************************
	        	Generate From
	        **************************************************************/
	        
        	forEach( $obj->joins as $join ){
        		
	        	$obj->from .= ' INNER JOIN ' . $join->table . ' ON ' . $obj->table . '.' .$join->local_column . ' = ' . $join->table . '.' . $join->column . ' ' . $join->from;
        	}
        	
        	/**************************************************************
	        	Generate Where
	        **************************************************************/
        	
        	if( isSet($obj->params["where"]) ){ $tmp = $this->buildWhereClause($obj->params["where"]); $obj->where .= $tmp->where; $obj->params = $tmp->params; if( isSet($tmp->password) ){ $obj->password = $tmp->password; $obj->password_key = $tmp->password_key; } }
        	        	
        	if( isSet($this->operators) ){ forEach( $this->operators as $operator ){ unset($obj->params[$operator]); } }
	        return $obj;
	        
        }
        
        private function buildWhereClause($params){
	        
	        $where = "";
	        $param_array = array();
			
	        forEach( $params as $operator => $pair ){
	        	
	        	forEach($pair as $key => $value){
	        		
	        		if( is_array($value) ){ $obj = $this->buildWhereClause($value); $where .= $obj->where; $param_array = array_merge($param_array,$obj->params); }
	        		
	        		if(array_key_exists($key, $this->table_definition) || $key === 'slug' || $key === 'parent_id'){
						
						
						if( isSet($this->table_definition[$key]["data_type"]) && $this->table_definition[$key]["data_type"] == "password" ){ $password_key = $key; $password = $v; } else {
						
				        	// define ORs within a where clause
				        	$value = explode('|',$value);
				        	$or = '(';
				        	forEach($value as $k => $v){
				        	
					        	if( $or != '(' ){ $or .= ' OR '; }
					        	   
						        $or .= " ".$this->table.".$key " . $operator . " :$key"."_".count($param_array);
						        //unset($params[$key]);
						        	
						        $param_array[$key."_".count($param_array)] = $v;
					        	
				        	}
				        	$or .= ' )';
				        	
				        	// write where clause
							if( !empty($where) && isSet($or) ){ $where .= " AND $or"; } else if( isSet($or) ) { $where .= " $or ";	 }
						
						}
		        	
		        	}
		        	
		        	
		        	
	        	}
	        }
	        
	        
	        
	        $obj = new stdClass; $obj->where = $where; $obj->params = $param_array;
	        if( isSet($password) ){ $obj->password = $password; $obj->password_key = $password_key; }
	        
	        return $obj;
	        
        }
        
        public function expandJoins($from,$join){
	        return $from;
        }
        
        /********************************************************************
            
            DUMP
            
            	What this does:
            	
            		1.	This does a mysqldump of the current table
            
        ********************************************************************/
        
        public function dump($params=array()){
	        
	        exec('mysqldump --user='.__DBUserName__.' --password='.__DBPassword__.' --host='.__DBHost__.' '.__DB__.' '.$this->table.' | gzip > '.__SELF__.'backups/'.$this->table.'-'.time().'.sql.gz');
	        
        }
        
        /********************************************************************
            
            
            
        ********************************************************************/
        
        private function getDataType($def){
            if( !isSet($def["data_type"]) ){ return FALSE; }												   // make sure datatype is set
            $data_type = explode("(",$def["data_type"]);                                                       // explode datatypes that contain a size i.e. varchar(255)
            if( !isSet($data_type[1]) ){ $data_type[1] = ''; }                                                 // if size is used then extract it
            $data_type[1] = str_replace(')','',$data_type[1]);												   // remove extra ')' and extract data type
            return array("data_type"=>$data_type[0],"size"=>$data_type[1]);									   // return datatype with size
        }
        
        /********************************************************************
            
            
            
        ********************************************************************/
        
        private function getSlug($slug,$column){
            $count = 1; $i = 0;
            while($count > 0){
                $new_slug = $slug;
                if( $i == 0 ){ $appendage = ""; } else { $appendage = " $i"; }
                $params = array("slug"=>removeSpecialChars(str_replace("-".($i-1),'',$new_slug).$appendage,'-','and'));
                $sql = " SELECT $column FROM $this->table WHERE slug = :slug";
                $statement = $this->dbh->prepare($sql);
                $statement->execute($params);
                $count = count($statement->fetchAll());
                ++$i;
            }
            return $params["slug"];
            
        }
        
        /********************************************************************
            
            
            
        ********************************************************************/
        
        private function reorder($order,$order_key,$order_value){
            $params = array("order"=>$order,"order_value"=>$order_value);
            $sql = " UPDATE $this->table SET order_variable = (order_variable+1) WHERE order_variable >= :order ";
            if( isSet($order_key) && isSet($order_value) ){ $sql .= " AND $order_key = :order_value"; }
            $statement = $this->dbh->prepare($sql);
            $statement->execute($params);
        }
        
	}
	