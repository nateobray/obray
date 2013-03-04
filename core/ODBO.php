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
	    private $primary_key;
	    private $data_types;
	    
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
	       
	       $this->dbh = $dbh; 
	       if(isSet($_REQUEST["refresh"]) && __DebugMode__){ $this->scriptTable(array()); }
	       
	    }
	    
        /*************************************************************************************************************
            
            Script Table
            
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
            
        *************************************************************************************************************/
        
        public function alterTable(){
        	
        }
        
        /********************************************************************
            
            ADD function
            
        ********************************************************************/
        
        public function add($params=array()){
        	
        	// generate prepared statement
        	$sql = "";
        	$sql_values = "";
        	$data = array();
        	$this->data_types = unserialize(__DATATYPES__);
        	
        	forEach($this->table_definition as $name => $def){
        	
        	   // validate
        	   $data_type = $this->getDataType($def);
        	   if( isSet($def["required"]) && $def["required"] === TRUE && (!isSet($params[$name]) || $params[$name] === NULL || $params[$name] === "") ){ $this->throwError('500',isSet($def["error_message"])?$def["error_message"]:isSet($def['label'])?$def['label']." is required.":$name." is required.",$name); }
        	   
        	   if( isSet($params[$name]) ){
        	   
	        	   if( isSet($def["data_type"]) && !empty($this->data_types[$data_type["data_type"]]["validation_regex"]) && !preg_match($this->data_types[$data_type["data_type"]]["validation_regex"],$params[$name]) ){
	        	       $this->throwError('500',isSet($def["error_message"])?$def["error_message"]:isSet($def['label'])?$def['label']." is invalid.":$name." is invalid.",$name);
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
        	
        	if( $this->isError() ){ return $this; }
        	
        	$this->reorder(1,$this->order_key,$order_value);
        	
        	if( !isSet($params["parent_id"]) ){ $params["parent_id"] = 0; }
        	if( isSet($params[$slug_column]) ){ $params["slug"] = $this->getSlug($params[$slug_column],$slug_column); } else { $params["slug"] = ""; }
        	$this->sql  = " insert into $this->table ( ".$sql.", slug, order_variable, parent_id, OCDT, OCU ) values ( ".$sql_values.", :slug, 1, :parent_id, NOW(), 0 ) ";
        	$statement = $this->dbh->prepare($this->sql);
        	
        	$this->script = $statement->execute($params);
        	
        }
        
        /********************************************************************
            
            UPDATE function
            
        ********************************************************************/
        
        public function update($params=array()){
        	
        	// generate prepared statement
        	$sql = "";
        	$sql_values = "";
        	$data = array();
        	$this->data_types = unserialize(__DATATYPES__);
        	
        	forEach($this->table_definition as $name => $def){
        		
				if( array_key_exists("primary_key",$def) && $def["primary_key"] === TRUE  ){                           // write SQL for key column if it exists
						$this->primary_key_column = $name;                                                            // set the key column variable
				} else {	
				
					// validate
					$data_type = $this->getDataType($def);
					if( isSet($def["required"]) && $def["required"] === TRUE && (!isSet($params[$name]) || $params[$name] === NULL || $params[$name] === "") ){ 
							$this->throwError('500',isSet($def["error_message"])?$def["error_message"]:isSet($def['label'])?$def['label']." is required.":$name." is required.",$name); 
					}
					
					if( isSet($params[$name]) ){
					
						if( isSet($def["data_type"]) && !empty($this->data_types[$data_type["data_type"]]["validation_regex"]) && !preg_match($this->data_types[$data_type["data_type"]]["validation_regex"],$params[$name]) ){
							$this->throwError('500',isSet($def["error_message"])?$def["error_message"]:isSet($def['label'])?$def['label']." is invalid.":$name." is invalid.",$name);
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
        	
        	if( empty($this->primary_key_column) ){ $this->throwError('500','Please specify a primary key.','primary_key'); }
        	if( !isSet( $params[$this->primary_key_column] ) ){ $this->throwError('500','Please specify a value for the primary key.',$this->primary_key_column); }
        	
        	if( $this->isError() ){ return $this; }
        	
        	$this->reorder(1,$this->order_key,$order_value);
        	
        	if( isSet($params["parent_id"]) ){ $sql .= " ,parent_id = :parent_id"; }
        	if( isSet($params[$slug_column]) ){ $params["slug"] = $this->getSlug($params[$slug_column],$slug_column); $sql .= " ,slug = :slug"; }
        	$this->sql  = " UPDATE $this->table SET $sql WHERE $this->primary_key_column = :$this->primary_key_column ";
        	$statement = $this->dbh->prepare($this->sql);
        	
        	$this->script = $statement->execute($params);
        	
        }
        
        /********************************************************************
            
            DELETE function
            
        ********************************************************************/
        
        public function delete($params=array()){
        	
        	if( empty($this->primary_key_column) ){
	        	forEach($this->table_definition as $name => $def){
					if( array_key_exists("primary_key",$def) && $def["primary_key"] === TRUE  ){                          // write SQL for key column if it exists
							$this->primary_key_column = $name;                                                            // set the key column variable	
					}
				}
        	}
        	
        	if( empty($this->primary_key_column) ){ $this->throwError('500','Please specify a primary key.','primary_key'); }
        	if( !isSet( $params[$this->primary_key_column] ) ){ $this->throwError('500','Please specify a value for the primary key.',$this->primary_key_column); }
        	
        	$this->sql  = " DELETE FROM $this->table WHERE  $this->primary_key_column = :$this->primary_key_column ";
        	$statement = $this->dbh->prepare($this->sql);
        	$this->script = $statement->execute($params);
        	
        	
        }
        
        /********************************************************************
            
            GET function
            
        ********************************************************************/
        
        public function get($params=array()){
        	
        	$columns = '';
        	forEach($this->table_definition as $name => $def){
        		if( !empty($columns) ){ $columns .= ' ,'; }
        		$columns .= " $name ";
        	}
        	
        	// create where clause
        	$where = '';
        	forEach($params as $key => $value){
	        	
	        	// define ORs within a where clause
	        	$value = explode('|',$value);
	        	$or = '(';
	        	forEach($value as $k => $v){
	        	    if( $or != '(' ){ $or .= ' OR '; }
		        	$or .= " $key = :$key"."_".$k;
		        	unset($params[$key]);
		        	$params[$key."_".$k] = $v;
	        	}
	        	$or .= ')';
	        	
	        	// write where clause
	        	if( !empty($where) ){ $where .= " AND $or"; } else { $where .= " $or ";	 }
	        	
        	}
        	
        	$this->sql = " SELECT $columns FROM $this->table ";
        	if( !empty($where) ){ $this->sql .= " WHERE $where "; }
        	$statement = $this->dbh->prepare($this->sql);
        	$statement->execute($params);
        	$statement->setFetchMode(PDO::FETCH_OBJ);
        	$this->data = $statement->fetchAll();
        	
        }
        
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
        
        private function reorder($order,$order_key,$order_value){
            $params = array("order"=>$order,"order_value"=>$order_value);
            $sql = " UPDATE $this->table SET order_variable = (order_variable+1) WHERE order_variable >= :order ";
            if( isSet($order_key) && isSet($order_value) ){ $sql .= " AND $order_key = :order_value"; }
            $statement = $this->dbh->prepare($sql);
            $statement->execute($params);
        }
        
        public function cleanUp(){
	        
	        if( __DebugMode__ === FALSE ){ unset($this->sql); }
	        if( empty($this->table_definition) || __DebugMode__ === FALSE ){ unset($this->table_definition); }
	        if( empty($this->primary_key_column) ){ unset($this->primary_key_column); }
	        if( empty($this->error_message) ){ unset($this->error_message); }
	        if( empty($this->error_message_array) ){ unset($this->error_message_array); }
	        
        }
		
	}
	