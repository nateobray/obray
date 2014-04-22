<?php

	/*****************************************************************************

	The MIT License (MIT)
	
	Copyright (c) 2014 Nathan A Obray <nathanobray@gmail.com>
	
	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the 'Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
	
	*****************************************************************************/

	if (!class_exists( 'OObject' )) { die(); }

	/********************************************************************************************************************

		ODBO:	This is the database interface object built specifically for MySQL and MariaDB.

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

	       if( !defined('__OBRAY_DATATYPES__') ){

				define ('__OBRAY_DATATYPES__', serialize (array (
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
	       if(__OBRAY_DEBUG_MODE__){ $this->scriptTable(); $this->alterTable(); }

	    }

        /*************************************************************************************************************

            SCRIPTTABLE

        *************************************************************************************************************/

        public function scriptTable($params=array()){
        
			$sql = 'CREATE DATABASE IF NOT EXISTS '.__OBRAY_DATABASE_NAME__.';'; $statement = $this->dbh->prepare($sql); $statement->execute();
        	
			if( empty($this->dbh) ){ return $this; }
			
			$sql = '';
			$data_types = unserialize(__OBRAY_DATATYPES__);
			
			forEach($this->table_definition as $name => $def){
			    if( array_key_exists('store',$def) == FALSE || (array_key_exists('store',$def) == TRUE && $def['store'] == TRUE ) ){
			
			        if( !empty($sql) ){ $sql .= ','; }
					if( isSet($def['data_type']) ){
						$data_type = $this->getDataType($def);
						$sql .= $name . str_replace('size',str_replace(')','',$data_type['size']),$data_types[$data_type['data_type']]['sql']);
					}
			
					if( array_key_exists('primary_key',$def) && $def['primary_key'] === TRUE  ){
						$this->primary_key_column = $name;
						$sql .= $name . ' INT UNSIGNED NOT NULL AUTO_INCREMENT ';
					}
			    }
			}
			
			$sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table . ' ( ' . $sql;
			$sql .= ', OCDT DATETIME, OCU INT UNSIGNED, OMDT DATETIME, OMU INT UNSIGNED ';
			if( !empty($this->primary_key_column) ){ $sql .= ', PRIMARY KEY (' . $this->primary_key_column . ') ) ENGINE='.__OBRAY_DATABASE_ENGINE__.' DEFAULT CHARSET='.__OBRAY_DATABASE_CHARACTER_SET__.'; '; }
			
			$this->sql = $sql;
			$statement = $this->dbh->prepare($sql);
			$this->script = $statement->execute();
			
        }

        /*************************************************************************************************************

            ALTERTABLE

        *************************************************************************************************************/

        public function alterTable(){
        	if( empty($this->dbh) ){ return $this; }

        	$this->dump();

        	$sql = 'DESCRIBE '.$this->table.';';
        	$statement = $this->dbh->prepare($sql);
        	$statement->execute();

        	$statement->setFetchMode(PDO::FETCH_OBJ);
        	$data = $statement->fetchAll();

        	$temp_def = $this->table_definition;
        	$obray_fields = array(3=>'OCDT',4=>'OCU',5=>'OMDT',6=>'OMU');
        	forEach( $obray_fields as $of ){ unset($this->table_definition[$of]); }

        	$data_types = unserialize(__OBRAY_DATATYPES__);
			
        	forEach($data as $def){

        		if( array_key_exists('store',$def) == FALSE || (array_key_exists('store',$def) == TRUE && $def['store'] == TRUE ) ){

	        	if( array_search($def->Field,$obray_fields) === FALSE ){
		        	if( isSet($this->table_definition[$def->Field]) ){
						
			        	if( $this->enable_data_type_changes && isSet($this->table_definition[$def->Field]['data_type']) ){
			        		$data_type = $this->getDataType($this->table_definition[$def->Field]);

			        		if( str_replace('size',$data_type['size'],$data_types[$data_type['data_type']]['my_sql_type']) != $def->Type ){
				        		if( !isSet($this->table_alterations) ){ $this->table_alterations = array(); }
				        		$sql = 'ALTER TABLE '.$this->table.' MODIFY COLUMN '.$def->Field.' '.str_replace('size',$data_type['size'],$data_types[$data_type['data_type']]['sql']);
				        		$statement = $this->dbh->prepare($sql);
				        		$this->table_alterations[] = $statement->execute();

			        		}

			        	}

			        	unset( $this->table_definition[$def->Field] );

		        	} else {
			        	if( $this->enable_column_removal && isSet($_REQUEST['enableDrop']) ){
			        		if( !isSet($this->table_alterations) ){ $this->table_alterations = array(); }
    						$sql = 'ALTER TABLE '.$this->table.' DROP COLUMN '.$def->Field.' ';
			        		$statement = $this->dbh->prepare($sql);
			        		$this->table_alterations[] = $statement->execute();
			        	}
		        	}
	        	}

	        	}
        	}
			
        	if( $this->enable_column_additions ){
	        	forEach($this->table_definition as $key => $def){
	        		if( array_key_exists('store',$def) == FALSE || (array_key_exists('store',$def) == TRUE && $def['store'] == TRUE ) ){
		        		if( !isSet($this->table_alterations) ){ $this->table_alterations = array(); }
		        		$data_type = $this->getDataType($def);
			        	$sql = 'ALTER TABLE '.$this->table.' ADD ('.$key.' '.str_replace('size',$data_type['size'],$data_types[$data_type['data_type']]['sql']).')';
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
        	
        	$sql = '';$sql_values = '';$data = array();
        	$this->data_types = unserialize(__OBRAY_DATATYPES__);
			
			$this->getWorkingDef();
			
			if( isSet($this->slug_key_column) && isSet($this->slug_value_column) && isSet($params[$this->slug_key_column]) ){ 	
				if( isSet($this->parent_column) && isSet($params[$this->parent_column]) ){ $parent = $params[$this->parent_column];  } else { $parent = null; }
				$params[$this->slug_value_column] = $this->getSlug($params[$this->slug_key_column],$this->slug_value_column,$parent);
			}
			
			forEach( $params as $key => $param ){
				
				if( isSet($this->table_definition[$key]) ){ 
					
					$def = $this->table_definition[$key];
					$data[$key] = $param;
					$data_type = $this->getDataType($def);
					
					if( isSet($this->required[$key]) ){ unset($this->required[$key]); }
					if( isSet($def['data_type']) && !empty($this->data_types[$data_type['data_type']]['validation_regex']) && !preg_match($this->data_types[$data_type['data_type']]['validation_regex'],$params[$key]) ){
					   $this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$def['label'].' is invalid.':$key.' is invalid.','500',$key);
					}
					
					if( isSet($def['data_type']) && $def['data_type'] == 'password' ){ $salt = '$2a$12$'.$this->generateToken(); $data[$key] = crypt($params[$key],$salt); }
					
					if( isSet($params[$key]) ){
					   if( !empty($sql) ){ $sql .= ','; $sql_values .= ','; }
					   $sql .= $key; $sql_values .= ':'.$key;
					}
				}
			}
			
        	if( !empty($this->required) ){
	        	forEach($this->required as $key => $value){
	        		$def = $this->table_definition[$key];
		        	$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$key.' is required.':$key.' is required.','500',$key);
	        	}
        	}
        	
        	if( $this->isError() ){ $this->throwError(isSet($this->general_error)?$this->general_error:'There was an error on this form, please make sure the below fields were completed correclty: '); return $this; }
        	
        	if( isSet($_SESSION['ouser']) ){ $ocu = $_SESSION['ouser']->ouser_id; } else { $ocu = 0; }
        	$this->sql  = ' INSERT INTO '.$this->table.' ( '.$sql.', OCDT, OCU ) values ( '.$sql_values.', NOW(), '.$ocu.' ) ';
        	$statement = $this->dbh->prepare($this->sql);
        	$this->script = $statement->execute($data);
        	
			$this->get(array( $this->primary_key_column => $this->dbh->lastInsertId() ) );

        }

        /********************************************************************

            UPDATE function

        ********************************************************************/

        public function update($params=array()){

        	if( empty($this->dbh) ){ return $this; }
        	
        	$sql = ''; $sql_values = ''; $data = array();
        	$this->data_types = unserialize(__OBRAY_DATATYPES__);
        	
			$this->getWorkingDef();
			
			if( isSet($this->slug_key_column) && isSet($this->slug_value_column) && isSet($params[$this->slug_key_column]) ){ 	
				if( isSet($this->parent_column) && isSet($params[$this->parent_column]) ){ $parent = $params[$this->parent_column];  } else { $parent = null; }
				$params[$this->slug_value_column] = $this->getSlug($params[$this->slug_key_column],$this->slug_value_column,$parent);
			}
			
        	forEach( $params as $key => $param ){
	        	
	        	if( isSet($this->table_definition[$key]) ){
	        		$def = $this->table_definition[$key];
	        		$data[$key] = $param;
		        	$data_type = $this->getDataType($def);
		        	
					if( isSet($def['required']) && $def['required'] === TRUE && (!isSet($params[$key]) || $params[$key] === NULL || $params[$key] === '') ){
							$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$def['label'].' is required.':$key.' is required.',500,$key);
					}
					
					if( isSet($def['data_type']) && !empty($this->data_types[$data_type['data_type']]['validation_regex']) && !preg_match($this->data_types[$data_type['data_type']]['validation_regex'],$params[$key]) ){
						$this->throwError(isSet($def['error_message'])?$def['error_message']:isSet($def['label'])?$def['label'].' is invalid.':$key.' is invalid.',500,$key);
					}
					
					if( isSet($def['data_type']) && $def['data_type'] == 'password' ){ $salt = '$2a$12$'.$this->generateToken(); $data[$key] = crypt($params[$key],$salt); }
					
					if( !empty($sql) ){ $sql .= ','; $sql_values .= ','; }
					$sql .= $key . ' = :'.$key.' ';
					
	        	}
        	}
        	
        	if( empty($this->primary_key_column) ){ $this->throwError('Please specify a primary key.','primary_key','500'); }
        	if( !isSet( $params[$this->primary_key_column] ) ){ $this->throwError('Please specify a value for the primary key.','500',$this->primary_key_column); }
        	if( $this->isError() ){ return $this; }
        	
        	if( isSet($_SESSION['ouser']) ){ $omu = $_SESSION['ouser']->ouser_id; } else { $omu = 0; }
        	$this->sql  = ' UPDATE '.$this->table.' SET '.$sql.', OMDT = NOW(), OMU = '.$omu.' WHERE '.$this->primary_key_column.' = :'.$this->primary_key_column.' ';
        	$statement = $this->dbh->prepare($this->sql);
        	$this->script = $statement->execute($data);
        	
        	$this->get(array($this->primary_key_column=>$params[$this->primary_key_column]));
			
        }
        
        /********************************************************************

            DELETE function

        ********************************************************************/

        public function delete($params=array()){
			
			$this->table_alias = 't'.md5(uniqid(rand(), true));
        	if( empty($this->dbh) ){ return $this; }
        	
			$this->getWorkingDef();
        	
        	$this->where = '';
        	$this->params = $this->buildWhereClause($params);
        	$this->where = str_replace($this->table_alias.'.','',$this->where);
        	
			if( empty( $this->where ) ){ $this->throwError('Please provide a filter for this delete statement',500); }			
			if( !empty( $this->errors ) ){ return $this; }
			
        	$this->sql  = ' DELETE FROM '.$this->table.' WHERE '.$this->where;
        	
        	$statement = $this->dbh->prepare($this->sql);
        	$this->script = $statement->execute($this->params);
        	
        }
        
        /********************************************************************

            GET function
            
        ********************************************************************/
                
        public function get($params=array()){
        
        	$original_params = $params;
        	
        	$limit = ''; $order_by = ''; $filter = TRUE;
        	if( isSet($params['start']) && isSet($params['rows']) ){ $limit = ' LIMIT ' . $params['start'] . ',' . $params['rows'] . ''; }
        	if( isSet($params['filter']) && ($params['filter'] == 'false' || !$filter) ){ $filter = FALSE; unset($params['filter']); }
        	if( isSet($params['order_by']) ){
	        	$order_by = explode('|',$params['order_by']); $columns = array();
	        	forEach( $order_by as $i => &$order ){
	        		$order = explode(':',$order);
	        		if( !empty($order) && array_key_exists($order[0],$this->table_definition) ){
        				$columns[] = $order[0];
						if( count($order) > 1 ){ switch($order[1]){ case 'ASC': case 'asc': $columns[count($columns)-1] .= ' ASC '; break; case 'DESC': case 'desc': $columns[count($columns)-1] .= ' DESC '; break; } }
	        		}
	        	}
	        	if( !empty($columns) ){ $order_by = ' ORDER BY ' . implode(',',$columns); } else { $order_by = ''; }
        	}
	        
	        $withs = array(); $original_withs = array();
	        if( !empty($params['with']) ){ $withs = explode('|',$params['with']); $original_withs = $withs; }
	        
	        $columns = array();
	        forEach($this->table_definition as $column => $def){
	        	if( isSet($def['data_type']) && $def['data_type'] == 'password' && isSet($params[$column]) ){ $password_column = $column; $password_value = $params[$column]; unset($params[$column]); }
	        	$columns[] = $column;
	        	if( array_key_exists('primary_key',$def) ){ $primary_key = $column; }
	        	forEach( $withs as $i => &$with ){
	        		if( !is_array($with) && array_key_exists($with,$def) ){
	        			unset( $original_withs[$i] );
	        			$name = $with;
		        		$with = explode(':',$def[$with]);
		        		$with[] = $column;
		        		$with[] = $name;
	        		}
	        	}
	        }
	        
	        if( isSet($original_params['with']) ){ $original_params['with'] = implode('|',$original_withs); }
	        $values = array();
	        $where_str = $this->getWhere($params,$values);
	        $this->sql = 'SELECT '.implode(',',$columns).',OCDT,OCU,OMDT,OMU FROM '.$this->table .$where_str . $order_by . $limit;
	        $statement = $this->dbh->prepare($this->sql);
	        forEach($values as $value){ if( is_integer($value) ){ $statement->bindValue($value['key'], trim($value['value']), PDO::PARAM_INT); } else { $statement->bindValue($value['key'], trim((string)$value['value']), PDO::PARAM_STR); } }
	        $statement->execute();
	        $statement->setFetchMode(PDO::FETCH_NUM);
	        $this->data = $statement->fetchAll(PDO::FETCH_OBJ);
	        
	        if( !empty($withs) ){
				
		        forEach( $withs as &$with ){
		        	
		        	$ids_to_index = array();
		        	if( !is_array($with) ){ break; }
		        	$with_key = $with[0]; $with_column = $with[2]; $with_name = $with[3]; $with_components = parse_url($with[1]); $sub_params = array();
		        	forEach( $this->data as $i => $data ){ if( !isSet($ids_to_index[$data->$with_column]) ){ $ids_to_index[$data->$with_column] = array(); } $ids_to_index[$data->$with_column][] = (int)$i; }
		        	$ids = array();
		        	if( count($this->data) < 1000 ){ forEach( $this->data as $row ){ $ids[] = $row->$with_column; }  }
		        	$ids = implode('|',$ids);
		        	
		        	if( !empty($with_components['query']) ){ parse_str($with_components['query'],$sub_params); }
		        	if( !empty($ids) ){ $with[0] = '?'.$with[0].'='.$ids; } else { $with[0] = ''; }
			        $with = $this->route($with_components['path'].'get/'.$with[0],array_merge($sub_params,$original_params))->data;
			        forEach( $with as &$w ){   
			        	if( isSet($ids_to_index[$w->$with_key]) ){
				        	forEach( $ids_to_index[$w->$with_key] as $index ){ 
				        		if( !isSet($this->data[$index]->$with_name) ){ $this->data[$index]->$with_name = array(); } 
				        		array_push($this->data[$index]->$with_name,$w);
				        	}
			        	}
			        }
			        
			        if($filter){ forEach( $this->data as $i => $data ){ if( empty($data->$with_name) ){ unset($this->data[$i]); } } array_values($this->data); }
			        
		        }
		        
	        }
	        	
	        if( $this->table == 'ousers' ){
	        	forEach( $this->data as $i => &$data ){
		        	if( isSet($password_column) && strcmp($data->$password_column,crypt($password_value,$data->$password_column)) != 0 ){ unset($this->data[$i]); }
		        	unset($data->ouser_password);
	        	}	        	
        	}
        	
        	$this->filter = $filter;$this->recordcount = count($this->data);
        	
	        return $this;
	        
        }
        
        /********************************************************************

            GETWHERE
            
        ********************************************************************/
        
        private function getWhere( &$params=array(),&$values=array() ){
        	
	        $where = array(); $count = 0; $p = array();
	        forEach( $params as $key => &$param ){
	        	
	        	$operator = '=';
	        	switch(substr($key,-1)){
		        	case '!': case '<': case '>':
		        		$operator = substr($key,-1).'=';
		        		//$p[str_replace(substr($key,-1),'',$key)] = $params[$key];
		        		$key = str_replace(substr($key,-1),'',$key);
		        	default:
		        		if( empty($params[$key]) ){ 
		        			$array = explode('~',$key); 
		        			if( count($array) === 2 ){ $param = '%'.urldecode($array[1]).'%'; $key = $array[0]; unset($params[$key]); $operator = 'LIKE'; }
		        			$array = explode('>',$key); 
		        			if( count($array) === 2 ){ $param = urldecode($array[1]); $key = $array[0]; unset($params[$key]); $operator = '>'; }
		        			$array = explode('<',$key); 
		        			if( count($array) === 2 ){ $param = urldecode($array[1]); $key = $array[0]; unset($params[$key]); $operator = '<'; }
		        		}	
		        	break;
	        	}
				
		        if( array_key_exists($key,$this->table_definition) ){
		        	
		        	if( !is_array($param) ){ $param = array(0=>$param); }
		        	
		        	forEach( $param as &$param_value ){
			        	if( empty($where) ){ $new_key = ''; } else { $new_key = 'AND'; }
			        	$ors = explode('|',$param_value);
			        	$where[] = array('join'=>$new_key.' (','key'=>'','value'=>'','operator'=>'');
			        	$or_key = '';
			        	forEach( $ors as $v ){
			        		++$count; $values[] = array('key'=>':'.$key.'_'.$count,'value'=>$v);
				        	$where[] = array('join'=>$or_key,'key'=>$key,'value'=>':'.$key.'_'.$count,'operator'=>$operator);
				        	$or_key = 'OR';
				        }
				        $where[] = array('join'=>')','key'=>'','value'=>'','operator'=>'');
		        	}
		        }
		        
	        }
	        
	        $where_str = '';
	        if( !empty($where) ){
		        $where_str = ' WHERE ';
		        forEach( $where as $key => $value ){
			        $where_str .= ' ' . $value['join'] . ' ' . $value['key'] . ' ' . $value['operator'] . ' ' . $value['value'] . ' ';
		        }
	        }
	        
	        return $where_str;
	        
        }
        
        /********************************************************************
			
            DUMP
					
        ********************************************************************/

        public function dump($params=array()){

	        exec('mysqldump --user='.__OBRAY_DATABASE_USERNAME__.' --password='.__OBRAY_DATABASE_PASSWORD__.' --host='.__OBRAY_DATABASE_HOST__.' '.__OBRAY_DATABASE_NAME__.' '.$this->table.' | gzip > '.dirname(__FILE__).'backups/'.$this->table.'-'.time().'.sql.gz');

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
                if( $i == 0 ){ $appendage = ''; } else { $appendage = ' '.$i; }
                $params = array('slug'=>strtolower(removeSpecialChars(str_replace('-'.($i-1),'',$new_slug).$appendage,'-','and')));
                if( !empty($parent) && isSet($this->parent_column) ){ $parent_sql = ' AND '.$this->parent_column.' = :'.$this->parent_column.' '; $params[$this->parent_column] = $parent; } else { $parent_sql = ''; }
                $sql = ' SELECT '.$column.' FROM '.$this->table.' WHERE '.$this->slug_value_column.' = :slug '.$parent_sql.' ';
                $statement = $this->dbh->prepare($sql);
                $statement->execute($params);
                $count = count($statement->fetchAll());
                ++$i;
            }
            return $params['slug'];

        }
        
        /********************************************************************

			GETFIRST
		
        ********************************************************************/

        public function getFirst(){
	        if( !isSet($this->data) || !is_array($this->data) ){ $this->data = array(); }
	        forEach( $this->data as $i => $data ){ $v = &$this->data[$i]; return $v; }
	        return reset($this->data);
        }
        
        /********************************************************************

			RUN
		
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
		
        ********************************************************************/
        
        public function count( $params=array() ){
			
			$values = array();
			$where_str = $this->getWhere($params,$values);
	        $statement = $this->dbh->prepare('SELECT COUNT(*) as count FROM '.$this->table.' '.$where_str);
	        forEach($values as $value){ if( is_integer($value) ){ $statement->bindValue($value['key'], trim($value['value']), PDO::PARAM_INT); } else { $statement->bindValue($value['key'], trim((string)$value['value']), PDO::PARAM_STR); } }
	        $statement->execute();
	        while ($row = $statement->fetch()) { $this->data[] = $row; }
	        $this->data = $this->data[0];
	        unset($this->data[0]);
	        return $this;
	        
        }
        
        /********************************************************************

			RAND
			
        ********************************************************************/
        
        public function random( $params=array() ){
			
			if( !empty($params['rows']) && is_numeric($params['rows']) ){ $rows = $params['rows']; } else { $rows = 1; }
			$values = array();
			$where_str = $this->getWhere($params,$values);
	        $statement = $this->dbh->prepare('SELECT * FROM '.$this->table.' '.$where_str.' ORDER BY RAND() LIMIT '.$rows);
	        forEach($values as $value){ if( is_integer($value) ){ $statement->bindValue($value['key'], trim($value['value']), PDO::PARAM_INT); } else { $statement->bindValue($value['key'], trim((string)$value['value']), PDO::PARAM_STR); } }
	        $statement->execute();
	        $statement->setFetchMode(PDO::FETCH_NUM);
	        $this->data = $statement->fetchAll(PDO::FETCH_OBJ);
	        return $this;
	        
        }
        
        /********************************************************************

			MATH FUNCTIONS
		
        ********************************************************************/
        
        public function sum( $params=array() ){  $this->math('SUM','sum',$params); }
        public function average( $params=array() ){  $this->math('AVG','average',$params); }
        public function maximum( $params=array() ){  $this->math('MAX','maximum',$params); }
        public function minimum( $params=array() ){  $this->math('MIN','minimum',$params); }
        
        private function math( $fn, $key, $params=array() ){
	        
	        $column = $params['column']; unset($params['column']);
			
			if( array_key_exists($column,$this->table_definition) ){
				$values = array();
				$where_str = $this->getWhere($params,$values);
		        $statement = $this->dbh->prepare('SELECT '.$fn.'('.$column.') as '.$key.' FROM '.$this->table.' '.$where_str);
		        forEach($values as $value){ if( is_integer($value) ){ $statement->bindValue($value['key'], trim($value['value']), PDO::PARAM_INT); } else { $statement->bindValue($value['key'], trim((string)$value['value']), PDO::PARAM_STR); } }
		        $statement->execute();
		        while ($row = $statement->fetch()) { $this->data[] = $row; }
		        $this->data = $this->data[0];
		        unset($this->data[0]);
		        return $this;
	        } else {
		        $this->throwError('Column does not exist.');
	        }
	        
        }
        
        /********************************************************************

			UNIQUE
		
        ********************************************************************/
        
        public function unique( $params=array() ){
			
			$column = $params['column']; unset($params['column']);
			
			if( array_key_exists($column,$this->table_definition) ){
				$values = array();
				$where_str = $this->getWhere($params,$values);
		        $statement = $this->dbh->prepare('SELECT DISTINCT '.$column.' FROM '.$this->table.' '.$where_str);
		        forEach($values as $value){ if( is_integer($value) ){ $statement->bindValue($value['key'], trim($value['value']), PDO::PARAM_INT); } else { $statement->bindValue($value['key'], trim((string)$value['value']), PDO::PARAM_STR); } }
		        $statement->execute();
		        while ($row = $statement->fetch()) { $this->data[] = $row[$column]; }
		        return $this;
	        } else {
		        $this->throwError('Column does not exist.');
	        }
	        
        }
        
         /********************************************************************

			LOG

        ********************************************************************/
        
        protected function log( $object,$label=null ){
	        
	        print_r(json_encode($object));
	        
	        if(__OBRAY_DEBUG_MODE__){ 
	        	$sql = 'CREATE TABLE IF NOT EXISTS ologs ( olog_id INT UNSIGNED,olog_label VARCHAR(255),olog_data TEXT,OCDT DATETIME,OCU INT UNSIGNED ), PRIMARY KEY (olog_id) ) ENGINE='.__OBRAY_DATABASE_ENGINE__.' DEFAULT CHARSET='.__OBRAY_DATABASE_CHARACTER_SET__.'; ';
				$statement = $this->dbh->prepare($sql); $statement->execute();
	        }
	        
		    $sql = 'INSERT INTO ologs(olog_label,olog_data,OCDT,OCU) VALUES(:olog_label,:olog_label,:OCDT,OCU);';
		    $statement->bindValue('olog_label',$label, PDO::PARAM_STR);
		    $statement->bindValue('olog_data',json_encode($obj), PDO::PARAM_STR);
		    $statement->bindValue('OCDT',date('Y-m-d H:i:s'), PDO::PARAM_STR);
		    $statement->bindValue('OCU',isSet($_SESSION['ouser']->ouser_id)?$_SESSION['ouser']->ouser_id:0, PDO::PARAM_INT);
	        
        }
        
         /********************************************************************

			GENERATETOKEN
		
        ********************************************************************/
        
        private function generateToken(){
			$safe = FALSE;
			return hash('sha512',base64_encode(openssl_random_pseudo_bytes(128,$safe)));
		}
		
}?>