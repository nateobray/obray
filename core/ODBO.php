<?php

	/*****************************************************************************

	The MIT License (MIT)
	
	Copyright (c) 2013 Nathan A Obray <nathanobray@gmail.com>
	
	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the 'Software'), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
	
	*****************************************************************************/

	if (!class_exists( 'OObject' )) { die(); }

	/********************************************************************************************************************

		ODB:	This is the database interface object built specifically for MySQL and MariaDB.  It is also designed to
		        to aid in quickly generating HTML forms from the table definition.  The goal here is easily mange the 
		        data from retrieval to input on a client application all while maintaining flexibility.

	********************************************************************************************************************/

	Class ODBO extends OObject {

	    public $dbh;
	    private $primary_key_column;
	    private $data_types;
	    private $enable_column_additions = TRUE;
	    private $enable_column_removal = TRUE;
	    private $enable_data_type_changes = TRUE;

	    public function __construct(){

	       if( !isSet($this->table) ){ $this->table = ''; }
	       if( !isSet($this->table_definition) ){ $this->table_definition = array(); }
	       if( !isSet($this->primary_key_column) ){ $this->primary_key_column = ''; }

	       if( !defined('__DATATYPES__') ){

				define ('__DATATYPES__', serialize (array (
				    'varchar'   	=>  array('sql'=>' VARCHAR(size) COLLATE utf8_general_ci ',	'my_sql_type'=>'varchar(size)',		'validation_regex'=>''),
				    'mediumtext'	=>  array('sql'=>' MEDIUMTEXT COLLATE utf8_general_ci ',	'my_sql_type'=>'mediumtext',		'validation_regex'=>''),
				    'text'      	=>  array('sql'=>' TEXT COLLATE utf8_general_ci ',			'my_sql_type'=>'text',				'validation_regex'=>''),
				    'integer'   	=>  array('sql'=>' int ',									'my_sql_type'=>'int(11)',			'validation_regex'=>'/^([0-9])*$/'),
				    'float'     	=>  array('sql'=>' float ',									'my_sql_type'=>'float',				'validation_regex'=>'/[0-9\.]*/'),
				    'boolean'   	=>  array('sql'=>' boolean ',								'my_sql_type'=>'boolean',			'validation_regex'=>''),
				    'datetime'  	=>  array('sql'=>' datetime ',								'my_sql_type'=>'datetime',			'validation_regex'=>''),
				    'password'  	=>  array('sql'=>' varchar(255) ',							'my_sql_type'=>'varchar(255)',		'validation_regex'=>'')
				)));
				
            }
			
	    }

	    public function setDatabaseConnection($dbh){
			
	       if( !isSet($this->table) || $this->table == '' ){ return; }
	       $this->dbh = $dbh;
	       if(isSet($_REQUEST['refresh']) && __DebugMode__){ $this->scriptTable(); $this->alterTable(); }

	    }

        /*************************************************************************************************************

            Script Table

            	What this does:

            		1.  Script a table new based on the table definition ($_RQUEST['refresh'] must be set )

            	What this doesn't do

            		1.	Will only script datatypes set in the __DATATYPES__ constant

        *************************************************************************************************************/

        public function scriptTable($params=array()){
        	
           if( empty($this->dbh) ){ return $this; }

           /*************************************************************************************************************

				WRITE SQL FROM TABLE DEFINITION

				Loop through the table definition array	and write the approriate SQL.  If the 'store' parameter exists
				in a definition	then skip and move on to the next definition.

           **************************************************************************************************************/

           $sql = '';
           $data_types = unserialize(__DATATYPES__);

           forEach($this->table_definition as $name => $def){
                if( array_key_exists('store',$def) == FALSE || (array_key_exists('store',$def) == TRUE && $def['store'] == TRUE ) ){

                    if( !empty($sql) ){ $sql .= ','; }                                                                     // add comma                                                                               // column name
					if( isSet($def['data_type']) ){                                                                        // if no data type is found don't use it
    					$data_type = $this->getDataType($def);
    					$sql .= $name . str_replace('size',str_replace(')','',$data_type['size']),$data_types[$data_type['data_type']]['sql']);  // generate SQL
					}

					if( array_key_exists('primary_key',$def) && $def['primary_key'] === TRUE  ){                           // write SQL for key column if it exists
						$this->primary_key_column = $name;                                                                 // set the key column variable
						$sql .= $name . ' INT UNSIGNED NOT NULL AUTO_INCREMENT ';                                          // write SQL
					}
                }
           }

           $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table . ' ( ' . $sql;                                             // prepend CREATE logic
		   
           /*************************************************************************************************************

           	    ADD CREATE AND MODIFY TRACKING FIELDS

					slug 				- used to create a unique identifier the is still 'human readable' but reliable for identification (must be unique)
					order_variable		- used to keep track of ordering
					parent_id			- used to track the parent_id of the row (useful for creating trees and relationships)
					OCDT				- tracks the create date of a specific record
					OCU					- tracks the user that created the record.  If no one is logged in this should be 0
					OMDT				- tracks the modify date of a specific record
					OMU					- tracks the user that modified the record.  If no one is logged in this should be 0

			*************************************************************************************************************/

			$sql .= ', OCDT DATETIME, OCU INT UNSIGNED, OMDT DATETIME, OMU INT UNSIGNED ';

            /*************************************************************************************************************
				ASSIGN PRIMARY KEY AND DEFUALT CHARSET (utf8 for multilangual support)
			*************************************************************************************************************/

			if( !empty($this->primary_key_column) ){ $sql .= ', PRIMARY KEY (' . $this->primary_key_column . ') ) ENGINE='.__DBEngine__.' DEFAULT CHARSET='.__DBCharSet__.'; '; }

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

        	$sql = 'DESCRIBE $this->table;';
        	$statement = $this->dbh->prepare($sql);
        	$statement->execute();

        	$statement->setFetchMode(PDO::FETCH_OBJ);
        	$data = $statement->fetchAll();

        	$temp_def = $this->table_definition;
        	$obray_fields = array(3=>'OCDT',4=>'OCU',5=>'OMDT',6=>'OMU');
        	forEach( $obray_fields as $of ){ unset($this->table_definition[$of]); }

        	$data_types = unserialize(__DATATYPES__);
			
        	forEach($data as $def){

        		if( array_key_exists('store',$def) == FALSE || (array_key_exists('store',$def) == TRUE && $def['store'] == TRUE ) ){

	        	if( array_search($def->Field,$obray_fields) === FALSE ){
		        	if( isSet($this->table_definition[$def->Field]) ){
						
			        	/*********************************************************************************

			        		1.	Update field data type if table is different from table definition.

			        	*********************************************************************************/

			        	if( $this->enable_data_type_changes && isSet($this->table_definition[$def->Field]['data_type']) ){
			        		$data_type = $this->getDataType($this->table_definition[$def->Field]);

			        		if( str_replace('size',$data_type['size'],$data_types[$data_type['data_type']]['my_sql_type']) != $def->Type ){
				        		if( !isSet($this->table_alterations) ){ $this->table_alterations = array(); }
				        		$sql = 'ALTER TABLE $this->table MODIFY COLUMN '.$def->Field.' '.str_replace('size',$data_type['size'],$data_types[$data_type['data_type']]['sql']);
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
			        	if( $this->enable_column_removal && isSet($_REQUEST['enableDrop']) ){
			        		if( !isSet($this->table_alterations) ){ $this->table_alterations = array(); }
    						$sql = 'ALTER TABLE $this->table DROP COLUMN $def->Field';
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
	        		if( array_key_exists('store',$def) == FALSE || (array_key_exists('store',$def) == TRUE && $def['store'] == TRUE ) ){
		        		if( !isSet($this->table_alterations) ){ $this->table_alterations = array(); }
		        		$data_type = $this->getDataType($def);
			        	$sql = 'ALTER TABLE $this->table ADD ($key '.str_replace('size',$data_type['size'],$data_types[$data_type['data_type']]['sql']).')';
		        		$statement = $this->dbh->prepare($sql);
		        		$this->table_alterations[] = $statement->execute();
	        		}
	        	}
        	}

        	$this->table_definition = $temp_def;

        }
		
		/********************************************************************

            GETTABLEDEFINITION 

        ********************************************************************/
		
        public function getTableDefinition(){ $this->data = $this->table_definition; }
        private function getWorkingDef(){
        	$this->required = array();
	        forEach($this->table_definition as $key => $def){
	        	if( isSet($def['required']) && $def['required'] == TRUE ){ $this->required[$key] = TRUE; }
				if( isSet($def['primary_key']) ){ $this->primary_key_column = $key; }
				if( isSet($def['parent']) && $def['parent'] == TRUE ){ $this->parent_column = $key; }
	        	if( isSet($def['slug_key']) && $def['slug_key'] == TRUE ){ $this->slug_key_column = $key; }
	        	if( isSet($def['slug_value']) && $def['slug_value'] == TRUE ){ $this->slug_value_column = $key; }
	        }
        }

        /********************************************************************

            ADD function

        ********************************************************************/

        public function add($params=array()){
			
        	if( empty($this->dbh) ){ return $this; }
        	
        	// generate prepared statement
        	$sql = '';
        	$sql_values = '';
        	$data = array();
        	$this->data_types = unserialize(__DATATYPES__);
			
			// get columns fields
			$this->getWorkingDef();
			
			// generate slug if set
			if( isSet($this->slug_key_column) && isSet($this->slug_value_column) && isSet($params[$this->slug_key_column]) ){ 	
				if( isSet($this->parent_column) && isSet($params[$this->parent_column]) ){ $parent = $params[$this->parent_column];  } else { $parent = null; }
				$params[$this->slug_value_column] = $this->getSlug($params[$this->slug_key_column],$this->slug_value_column,$parent);
			}
			
			// write SQL and prepare data
			forEach( $params as $key => $param ){
				
				if( isSet($this->table_definition[$key]) ){ 
					
					// add param to data and get data_type
					$def = $this->table_definition[$key];
					$data[$key] = $param;
					$data_type = $this->getDataType($def);
					
					// validate data type
					if( isSet($this->required[$key]) ){ unset($this->required[$key]); }
					if( isSet($def['data_type']) && !empty($this->data_types[$data_type['data_type']]['validation_regex']) && !preg_match($this->data_types[$data_type['data_type']]['validation_regex'],$params[$key]) ){
					   $this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$def['label'].' is invalid.':$key.' is invalid.','500',$key);
					}
					
					// generate hashed password using crypt and generate token - will significantly add to the speed of an add (processor intensive)
					if( isSet($def['data_type']) && $def['data_type'] == 'password' ){ $salt = '$2a$12$'.$this->generateToken(); $data[$key] = crypt($params[$key],$salt); }
					
					// add param to SQL prepared statement
					if( isSet($params[$key]) ){
					   if( !empty($sql) ){ $sql .= ','; $sql_values .= ','; }
					   $sql .= $key; $sql_values .= ':$key';
					}
					
				}
				
			}
			
			// throw error if all the required fields are not accounted for
        	if( !empty($this->required) ){
	        	forEach($this->required as $key => $value){
	        		$def = $this->table_definition[$key];
		        	$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$key.' is required.':$key.' is required.','500',$key);
	        	}
        	}
        	
        	// throw general error
        	if( $this->isError() ){ $this->throwError(isSet($this->general_error)?$this->general_error:'There was an error on this form, please make sure the below fields were completed correclty: '); return $this; }
        	
        	if( isSet($_SESSION['ouser']) ){ $ocu = $_SESSION['ouser']->ouser_id; } else { $ocu = 0; }
        	$this->sql  = ' insert into $this->table ( '.$sql.', OCDT, OCU ) values ( '.$sql_values.', NOW(), '.$ocu.' ) ';
        	$statement = $this->dbh->prepare($this->sql);
        	$this->script = $statement->execute($data);
        	
        	// get inserted row
			$this->get(array( $this->primary_key_column => $this->dbh->lastInsertId() ) );

        }

        /********************************************************************

            UPDATE function

        ********************************************************************/

        public function update($params=array()){

        	if( empty($this->dbh) ){ return $this; }
        	
        	// generate prepared statement
        	$sql = '';
        	$sql_values = '';
        	$data = array();
        	$this->data_types = unserialize(__DATATYPES__);
        	
        	// get columns fields
			$this->getWorkingDef();
			
			// generate slug if set
			if( isSet($this->slug_key_column) && isSet($this->slug_value_column) && isSet($params[$this->slug_key_column]) ){ 	
				if( isSet($this->parent_column) && isSet($params[$this->parent_column]) ){ $parent = $params[$this->parent_column];  } else { $parent = null; }
				$params[$this->slug_value_column] = $this->getSlug($params[$this->slug_key_column],$this->slug_value_column,$parent);
			}
			
			// write SQL and prepare data
        	forEach( $params as $key => $param ){
	        	
	        	if( isSet($this->table_definition[$key]) ){
	        		$def = $this->table_definition[$key];
	        		$data[$key] = $param;
		        	$data_type = $this->getDataType($def);
		        	
		        	// validate required
					if( isSet($def['required']) && $def['required'] === TRUE && (!isSet($params[$key]) || $params[$key] === NULL || $params[$key] === '') ){
							$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$def['label'].' is required.':$key.' is required.',500,$key);
					}
					
					// validate data type
					if( isSet($def['data_type']) && !empty($this->data_types[$data_type['data_type']]['validation_regex']) && !preg_match($this->data_types[$data_type['data_type']]['validation_regex'],$params[$key]) ){
						$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$def['label'].' is invalid.':$key.' is invalid.',500,$key);
					}
					
					// generate hashed password using crypt and generate token - will significantly add to the speed of an add (processor intensive)
					if( isSet($def['data_type']) && $def['data_type'] == 'password' ){ $salt = '$2a$12$'.$this->generateToken(); $data[$key] = crypt($params[$key],$salt); }
					
					// write SQL
					if( !empty($sql) ){ $sql .= ','; $sql_values .= ','; }
					$sql .= $key . ' = :$key ';
					
	        	}
        	}
        	
        	// handle errors
        	if( empty($this->primary_key_column) ){ $this->throwError('Please specify a primary key.','primary_key','500'); }
        	if( !isSet( $params[$this->primary_key_column] ) ){ $this->throwError('Please specify a value for the primary key.','500',$this->primary_key_column); }
        	if( $this->isError() ){ return $this; }
        	
        	// prepare end execute SQL
        	if( isSet($_SESSION['ouser']) ){ $omu = $_SESSION['ouser']->ouser_id; } else { $omu = 0; }
        	$this->sql  = ' UPDATE $this->table SET $sql, OMDT = NOW(), OMU = '.$omu.' WHERE $this->primary_key_column = :$this->primary_key_column ';
        	$statement = $this->dbh->prepare($this->sql);
        	$this->script = $statement->execute($data);
        	
        	// get updated row
        	$this->get(array($this->primary_key_column=>$params[$this->primary_key_column]));
			
        }
        
        /********************************************************************

            DELETE function

        ********************************************************************/

        public function delete($params=array()){
			
			$this->table_alias = 't'.md5(uniqid(rand(), true));
        	if( empty($this->dbh) ){ return $this; }
        	
        	// get columns fields
			$this->getWorkingDef();
        	
        	// build where caluse
        	$this->where = '';
        	$this->params = $this->buildWhereClause($params);
        	$this->where = str_replace($this->table_alias.'.','',$this->where);
        	
        	// handle errors
			if( empty( $this->where ) ){ $this->throwError('Please provide a filter for this delete statement',500); }			
			if( !empty( $this->errors ) ){ return $this; }
			
        	$this->sql  = ' DELETE FROM $this->table WHERE ' . $this->where;
        	
        	$statement = $this->dbh->prepare($this->sql);
        	$this->script = $statement->execute($this->params);
        	
        }
        
         /********************************************************************

            GET function
            
            Writes a SELECT statement from INNER/LEFT JOINS, a WHERE clause,
            and ORDER BY.
            
            	1. Prepare Select Statement (see function)
            	2. Write SQL
            	3. Execute Query & Get Result set
            	4. Post Process Results

        ********************************************************************/
                
        public function get($params=array()){
	        
	        /**************************************************************************
	        	1. Prepare Select Statement (see function)
	        **************************************************************************/
	        
	        if( isSet( $params['start'] ) && isSet( $params['rows'] ) ){
		        $limit = ' LIMIT :start, :rows'; $start = (int)$params['start']; $rows = (int)$params['rows'];
		        unset( $params['start'] );  unset( $params['rows'] );
	        }
	        
	        $this->table_definition['OMDT'] = array('data_type'=>'datetime');
	        $this->table_definition['OCDT'] = array('data_type'=>'datetime');
	        
	        $this->where = '';
	        $with = $this->prepareSelectStatement($params);
	        	        
	        /**************************************************************************
	        	2. Write SQL
	        **************************************************************************/
	        
	        $select = ' SELECT ' . $this->table_alias . '.' . $this->columns;
	        $from = ' FROM ' . $this->table . ' as ' .$this->table_alias . ' ' . $this->from;
	        if( isSet($params['where']) ){ $params_where = str_replace($this->table,$this->table_alias,$params['where']); } else { $params_where = ''; }
	        if( !empty($this->where) ){ $where = ' WHERE ' . $params_where . ' ' . $this->where; } else if ( !empty($params_where) ) { $where = ' WHERE ' . $params_where; } else { $where = ' '; }
	        if( !empty($this->order_by) ){ $order_by = ' ORDER BY ' . implode(',',$this->order_by); } else { $order_by = ''; }
	        if( !isSet( $limit ) ){ $limit = ''; } //else { $this->params['start'] = (int)$start; $this->params['rows'] = (int)$rows; }
	        $this->sql = $select . $from . $where . $order_by . $limit;
	        
	        /**************************************************************************
	        	3. Execute Query & Get Result set
	        **************************************************************************/
			
			$this->r = array();															// store results
	        $statement = $this->dbh->prepare($this->sql);								// prepare PDO statement with written SQL
	        if( isSet($start) && isSet($rows) ){
		        $statement->bindValue(':start', (int)trim($start), PDO::PARAM_INT); 
		        $statement->bindValue(':rows', (int)trim($rows), PDO::PARAM_INT); 
	        }
	        forEach($this->params as $key => $param){ $statement->bindValue(':'.$key, trim($param), PDO::PARAM_STR); }
        	$statement->execute();														// execute statement
	        $statement->setFetchMode(PDO::FETCH_NUM);									// Fetch results numerically (important for inner joins on the same table)
	        $statement->fetchAll(PDO::FETCH_FUNC,array($this, 'getPDORow'));			// Fetch all and use callback getPDORow (see function)
	        
	        /**************************************************************************
	        	4. Post Process Results
	        **************************************************************************/
	        
	        $this->data = $this->r;
	        	
	        forEach($this->data as $key => $value){
		        // handle additional function calls
	        	$rr = $this->data[$key];
		        forEach( $this->fns as $column => $obj ){ 
		        	if( property_exists($rr,$obj->join_column) ){ 
			        	$key = $obj->join_column;
			        	
			        	$rr->$column = $this->route($obj->path,array($obj->column=>$rr->$key))->data; 
			        	
					} 
		        }
	        }
	        
	        if( isSet($this->password) && isSet($this->password_key) && count($this->data) > 0 ){
	        	forEach( $this->data as $i => $d ){
	        		$key = $this->password_key;
	        		if( strcmp($this->data[$i]->$key,crypt($this->password,$this->data[$i]->$key)) !== 0 ){ unset($this->data[$i]); }
	        	}
        	}
        	
        }
        
        /********************************************************************

            POST Process Functions
            
            	1.	GetPDORow - called for each row in a result set
            	2.	GetRowObjectRecursively - breaks objects out into
            		sub arrays bassed on with parameter

        ********************************************************************/
        
        /********************************************************************
        	1.	GetPDORow - called for each row in a result set
        ********************************************************************/
        
        public function getPDORow(){
	       
			$args = func_get_args();
			$columns = $this->columns_array;
			$obj = $this->getRowObjectRecursively($args,$this->columns_array);
			
			$this->mergeObjectsRecursively($this->r,$obj);
			
        }
        
        /********************************************************************
        	2.	GetRowObjectRecursively - breaks objects out into
            	sub arrays bassed on with parameter
        ********************************************************************/
        
        public function getRowObjectRecursively(&$args,$columns){
	        
	        $obj = new stdClass();
	        
			forEach( $columns as $index => $column ){

				if( is_array($column) ){
					
					$args = array_values($args);
					
					// only add set a args if the values are not empty
					if (array_filter($args)) { $obj->$index = array(0=>$this->getRowObjectRecursively($args,$column)); } else { $obj->$index = array(); }
					
				} else {
					
					$obj->$column = $args[$index];
					unset($args[$index]);
					
				}
								
			}
			
			return $obj;
        }
        
        /********************************************************************

            PREPARESELECTSTATEMENT function

        ********************************************************************/
        
        public function mergeObjectsRecursively(&$array,$obj){
	        
	        $found = FALSE;
	        
			forEach( $array as $i => $row ){
				
				$obj1 = (array)$row; $obj2 = (array)$obj;
				
				if( reset($obj1) == reset($obj2) ){
					$found = TRUE;
					
					forEach( $obj as $key => $value ){
						
						if( is_array($value) && !empty($value) ){
							
							$this->mergeObjectsRecursively($array[$i]->$key,$value[0]);
						}
					}
				}
			}
			
			if( !$found ){ $array[] =  $obj; }
	        
        }

        /********************************************************************

            PREPAREGETSTATEMENT function

        ********************************************************************/
        
        public function prepareSelectStatement($params,$key=null){
	        
	        $this->table_alias = 't'.md5(uniqid(rand(), true));
	        $with_array = array();
	        $with_obj = array();
	        if( isSet($params['with']) ){ $with = $params['with']; } else { $with = ''; }
	        if( isSet($with) ){ $with_array = explode('|',$with); }
	        
	        
	        if( isSet($this->direct) && $this->direct && isSet($params['where']) ){ $this->where .= str_replace('oproducts',$this->table_alias,urldecode($params['where'])); }
	        
	        $this->columns_array = array();
	        $this->fns = array();
	        
	        /**************************************************************************
	        
	        	Create Valid With Objects
	        	
	        		- Should probably find a way to reduce nested looping
	        
	        **************************************************************************/
	        $this->order_by = array();
	        if( isSet($params['order_by']) ){ $order_by_array = explode('|',$params['order_by']); } else { $order_by_array = array(); }
	        $orders = array();
	        forEach( $order_by_array as $pair ){
		        $tmp = explode(':',$pair);
		        $orders[$tmp[0]] = isSet($tmp[1])?$tmp[1]:'ASC';
	        }
	        
	        //$this->order_by[] = $this->table_alias . '.order_variable ';
	        
	        forEach($this->table_definition as $name => $def){ 
	        	
	        	if( array_key_exists($name,$orders) ){
		        	$this->order_by[] = $this->table_alias . '.' . $name . ' ' . $orders[$name];
	        	}
	        	
	        	$this->columns_array[] = $name;
	        	
	        	if( empty($key) || $key != $name ){
	        		
		        	if( array_key_exists('primary_key',$def) && $def['primary_key'] === TRUE  ){ $this->primary_key_column = $name; } 
		        	
		        	forEach( $with_array as $i => $w ){
		        		
			        	if( array_key_exists($w,$def) ){ 
			        		
		        			$obj = explode(':',$def[$w]);
		        			 
			        		if( count($obj) > 1 ){ 
			        			
			        			if( $this->isObject($obj[1]) ){
			        			
			        				$subparams = $params; unset($subparams['parent_id']); unset($subparams['slug']); unset($subparams[$this->primary_key_column]);
			        				if( $w === 'parent' ){ $subparams['with'] = str_replace('parent','',$subparams['with']); }
			        				$with_obj[$w] = new stdClass();
				        			$with_obj[$w] = $this->route($obj[1]);
				        			$with_obj[$w]->column = $obj[0];
				        			$with_obj[$w]->join_column = $name;
				        			if( isSet($obj[2]) ){ $with_obj[$w]->join_type = $obj[2]; } else { $with_obj[$w]->join_type = ''; }
				        			if( isSet($subparams['where']) ){ unset($subparams['where']); }
				        			$with_obj[$w]->prepareSelectStatement($subparams,$this->primary_key_column);
				        		
			        			} else {
			        				
			        				$this->fns[$w] = new stdClass();
				        			$this->fns[$w]->column = $obj[0];
				        			$this->fns[$w]->join_column = $name;
				        			$this->fns[$w]->path = $obj[1];
				        			
			        			}
			        		}
			        	}
		        	}
	        	}
	        }
	        
	        /**************************************************************************
	        
	        	Generate Query parts and parameters
	        
	        **************************************************************************/
	        
	        // columns for select
	        $this->columns = implode(','.$this->table_alias.'.',$this->columns_array);
	        
	        // params for where clause
	        $this->params = $this->buildWhereClause($params);
			forEach( $with_obj as $column => $obj ){ $this->params = array_merge($this->params,$obj->params); $this->columns_array[$column] = $obj->columns_array; }
			
			// from join statements
	        $this->from = '';
	        forEach($with_obj as $obj){
	        	 $this->columns .= ','.$obj->table_alias.'.'.$obj->columns;
	        	 $join = ' LEFT JOIN '; forEach( $params as $column => $param ){ if( array_key_exists($column,$obj->table_definition) ){ $join = ' INNER JOIN '; } }
	        	 if( !empty($obj->join_type) ){ switch($obj->join_type){ case 'inner': $join = ' INNER JOIN '; break; case 'right': $join = ' RIGHT JOIN '; break; default: $join = ' LEFT JOIN '; break; } }
	        	 $this->from .= $join . $obj->table . ' AS ' . $obj->table_alias . ' ON ' . $obj->table_alias . '.' . $obj->column . ' = ' . $this->table_alias . '.' . $obj->join_column; 
	        	 if( !empty($obj->where) ){ $this->from .= ' AND '; }
	        	 $this->from .= $obj->where . ' ' . $obj->from;
	        	 //if( !empty($obj->where) && !empty($this->where) ){ $this->where .= ' AND ' . $obj->where; } else if( empty($this->where) ){ $this->where = $obj->where; }
	        	 $this->order_by = array_merge($this->order_by,$obj->order_by);
	        }
	        
	        
	        // set with and return 
	        $this->with = $with_obj;
	        return $with_obj;
	        
        }
                
        /********************************************************************

            BUILDWHERECLAUSE Function

        ********************************************************************/
        
        private function buildWhereClause($params){
        	
        	/**************************************************************************
	        
	        	Setup operators for where expressions
	        
	        **************************************************************************/
        	
        	$repeat_count = 0;
        	forEach($params as $key => $value){
        		
	        	switch(substr($key,-1)){
	        		
		        	case '!': case '<': case '>':
		        		
		        		
		        		if( isSet($params[str_replace(substr($key,-1),'',$key)]) ){ 
		        			
		        			if( is_array($params[str_replace(substr($key,-1),'',$key)]) ){
		        				$operators[str_replace(substr($key,-1),'',$key)] = substr($key,-1).'=';
			        			$params[str_replace(substr($key,-1),'',$key)][] = $params[$key];
		        			} else {
		        				$operators[str_replace(substr($key,-1),'',$key)] = array(0=>$operators[str_replace(substr($key,-1),'',$key)],1=>substr($key,-1).'='); 
		        				$params[str_replace(substr($key,-1),'',$key)] = array(0=>$params[str_replace(substr($key,-1),'',$key)],1=>$params[$key]);
		        			}
		        			
		        		} else{
		        			$operators[str_replace(substr($key,-1),'',$key)] = substr($key,-1).'=';
			        		$params[str_replace(substr($key,-1),'',$key)] = $params[$key]; unset($params[$key]); 
		        		}
		        		
		        		break;
		        		
		        	default:
		        		
		        		$operators[$key] = '=';
		        		if( empty($params[$key]) ){ 
		        			
		        			$array = explode('>',$key); if( count($array) === 2 ){ $params[$array[0]] = urldecode($array[1]); unset($params[$key]); $operators[$array[0]] = '<'; }
		        			$array = explode('<',$key); if( count($array) === 2 ){ $params[$array[0]] = urldecode($array[1]); unset($params[$key]); $operators[$array[0]] = '>'; }
		        			$array = explode('~',$key); if( count($array) === 2 ){ $params[$array[0]] = '%'.urldecode($array[1]).'%'; unset($params[$key]); $operators[$array[0]] = 'LIKE'; }
		        			
		        		}
		        		break;
						
	        	}
	        	
        	}
        	
        	/**************************************************************************
	        
	        	Generate Where clause
	        
	        **************************************************************************/
        	
	        $where = '';
	        $param_array = array();
	        
	        $count = 0;
	        $this->table_definition['OCDT'] = array();
	        forEach($params as $key => $value){
	        
	    		if(array_key_exists($key, $this->table_definition) ){
	    		
					if( isSet($this->table_definition[$key]['data_type']) && $this->table_definition[$key]['data_type'] == 'password' ){ $this->password_key = $key; $this->password = $value; } else {
						
						if( is_array($value) ){
			    			
			    			$or = '(';
			    			forEach($value as $k => $v){
				        	
					        	if( $or != '(' ){ $or .= ' AND '; }
					        	   
						        $or .= ' '.$this->table_alias.'.$key ' . $operators[$key][$k] . ' :$key'.'_'.count($param_array);
						        	
						        $param_array[$key.'_'.count($param_array)] = $v;
					        	
				        	}
				        	$or .= ' )';
				        	
				        	if( !empty($where) && isSet($or) ){ $where .= ' AND $or'; } else if( isSet($or) ) { $where .= ' $or ';	 }
			    			
		    			} else {
						
				        	// define ORs within a where clause
				        	$value = explode('|',$value);
				        	$or = '(';
				        	forEach($value as $k => $v){
				        	
					        	if( $or != '(' ){ $or .= ' OR '; }
					        	   
						        $or .= ' '.$this->table_alias.'.$key ' . $operators[$key] . ' :$key'.'_'.count($param_array);
						        	
						        $param_array[$key.'_'.count($param_array)] = $v;
					        	
				        	}
				        	$or .= ' )';
				        	
				        	// write where clause
							if( !empty($where) && isSet($or) ){ $where .= ' AND $or'; } else if( isSet($or) ) { $where .= ' $or ';	 }
						
						}
						
					}
					
	        	}
				++ $count;
        	}
			if( !isSet($this->where) ){ $this->where = $where; } else { $this->where .= $where; }
	        return $param_array;
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
			
			GETDATATYPE
			
        ********************************************************************/

        private function getDataType($def){
            if( !isSet($def['data_type']) ){ return FALSE; }												   // make sure datatype is set
            $data_type = explode('(',$def['data_type']);                                                       // explode datatypes that contain a size i.e. varchar(255)
            if( !isSet($data_type[1]) ){ $data_type[1] = ''; }                                                 // if size is used then extract it
            $data_type[1] = str_replace(')','',$data_type[1]);												   // remove extra ')' and extract data type
            return array('data_type'=>$data_type[0],'size'=>$data_type[1]);									   // return datatype with size
        }

        /********************************************************************
			
			GETSLUG
			
        ********************************************************************/

        private function getSlug($slug,$column,$parent){
            $count = 1; $i = 0;
            while($count > 0){
                $new_slug = $slug;
                if( $i == 0 ){ $appendage = ''; } else { $appendage = ' $i'; }
                $params = array('slug'=>strtolower(removeSpecialChars(str_replace('-'.($i-1),'',$new_slug).$appendage,'-','and')));
                if( !empty($parent) && isSet($this->parent_column) ){ $parent_sql = ' AND $this->parent_column = :$this->parent_column '; $params[$this->parent_column] = $parent; } else { $parent_sql = ''; }
                $sql = ' SELECT $column FROM $this->table WHERE $this->slug_value_column = :slug $parent_sql ';
                $statement = $this->dbh->prepare($sql);
                $statement->execute($params);
                $count = count($statement->fetchAll());
                ++$i;
            }
            return $params['slug'];

        }
        
        /********************************************************************

			GETFIRST
			
				useful when you want to get the first or only item of a 
				result set.

        ********************************************************************/

        public function getFirst(){
	        if( !isSet($this->data) || !is_array($this->data) ){ $this->data = array(); }
	        forEach( $this->data as $i => $data ){ $v = &$this->data[$i]; return $v; }
	        return reset($this->data);
        }
        
        /********************************************************************

			RUN
			
				Used to execute very specific SQL.

        ********************************************************************/
        
        public function run( $sql ){
	        $statement = $this->dbh->prepare($sql);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_OBJ);
            $this->data = [];
	        while ($row = $statement->fetch()) { $this->data[] = $row; }
	        return $this;
        }
        
        /********************************************************************

			COUNT
			
				Very fast way to retreive a count of records in a given table

        ********************************************************************/
        
        public function count( $params=array() ){
			
        	$this->table_alias = 't'.md5(uniqid(rand(), true));
        	$params = $this->buildWhereClause($params);			
        	if( !empty($this->where) ){ $where = ' WHERE ' . $this->where; } else { $where = ''; }
        	//echo 'SELECT COUNT(*) as count FROM '.$this->table.' '.$this->table_alias . $where;
	        $statement = $this->dbh->prepare('SELECT COUNT(*) as count FROM '.$this->table.' '.$this->table_alias . $where);
	        forEach($params as $key => $param){ $statement->bindValue(':'.$key, trim($param), PDO::PARAM_STR); }
	        $statement->execute();
	        while ($row = $statement->fetch()) { $this->data[] = $row; }
	        $this->data = $this->data[0];
	        unset($this->data[0]);
	        return $this;
	        
        }
        
        /********************************************************************

			SUM
			
				Very fast way to sum a column of records in a given table

        ********************************************************************/
        
        public function sum( $params=array() ){
			
			$column = $params['column']; unset($params['column']);
			
        	$this->table_alias = 't'.md5(uniqid(rand(), true));
        	$params = $this->buildWhereClause($params);			
        	if( !empty($this->where) ){ $where = ' WHERE ' . $this->where; } else { $where = ''; }
        	//echo 'SELECT SUM('.$column.') as sum FROM '.$this->table.' '.$this->table_alias . $where;
	        $statement = $this->dbh->prepare('SELECT SUM('.$column.') as sum FROM '.$this->table.' '.$this->table_alias . $where);
	        forEach($params as $key => $param){ $statement->bindValue(':'.$key, trim($param), PDO::PARAM_STR); }
	        $statement->execute();
	        while ($row = $statement->fetch()) { $this->data[] = $row; }
	        $this->data = $this->data[0];
	        unset($this->data[0]);
	        return $this;
	        
        }
        
         /********************************************************************

			GENERATETOKEN
			
				Is used to generate a safe HASH for salt for data_type = password

        ********************************************************************/
        
        private function generateToken(){
			$safe = FALSE;
			return hash('sha512',base64_encode(openssl_random_pseudo_bytes(128,$safe)));
		}
        
        

	}?>