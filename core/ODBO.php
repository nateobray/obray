<?php

	if (!class_exists( 'OObject' )) { die(); }

	/********************************************************************************************************************

		ODBO:	This is the database interface object built specifically for MySQL and MariaDB.

	********************************************************************************************************************/

	Class ODBO extends OObject {

        /**
         * @var PDO $dbh PDO Connection
         */
        public $dbh;
	    public $enable_system_columns = TRUE;
		protected $ignorePasswords = false;
		protected $isSeeded = false;

	    public function __construct(){

	    	$this->primary_key_column = '';
	    	$this->data_types = array();

	    	$this->enable_column_additions = TRUE;
	    	$this->enable_column_removal = TRUE;
	    	$this->enable_data_type_changes = TRUE;
	    	
	       if( !isSet($this->table) ){ $this->table = ''; }
	       if( !isSet($this->table_definition) ){ $this->table_definition = array(); }
	       if( !isSet($this->primary_key_column) ){ $this->primary_key_column = ''; }

	       if( !defined('__OBRAY_DATATYPES__') ){
				define ('__OBRAY_DATATYPES__', serialize (array (
				    'varchar'   	=>  array('sql'=>' VARCHAR(size) COLLATE utf8_general_ci ',		'my_sql_type'=>'varchar(size)',		'validation_regex'=>''),
				    'mediumtext'	=>  array('sql'=>' MEDIUMTEXT COLLATE utf8_general_ci ',		'my_sql_type'=>'mediumtext',		'validation_regex'=>''),
				    'text'      	=>  array('sql'=>' TEXT COLLATE utf8_general_ci ',				'my_sql_type'=>'text',				'validation_regex'=>''),
				    'integer'   	=>  array('sql'=>' int ',										'my_sql_type'=>'int(11)',			'validation_regex'=>'/^([+,-]?[0-9])*$/'),
				    'uninteger'		=>	array('sql'=>' int(11) unsigned NOT NULL DEFAULT \'0\'  ',	'my_sql_type'=>'int(11) unsigned',	'validation_regex'=>'/^([+,-]?[0-9])*$/'),
				    'float'     	=>  array('sql'=>' float ',										'my_sql_type'=>'float',				'validation_regex'=>'/[0-9\.]*/'),
				    'boolean'   	=>  array('sql'=>' tinyint(1) ',								'my_sql_type'=>'tinyint(1)',		'validation_regex'=>''),
				    'datetime'  	=>  array('sql'=>' datetime ',									'my_sql_type'=>'datetime',			'validation_regex'=>''),
				    'password'  	=>  array('sql'=>' varchar(255) ',								'my_sql_type'=>'varchar(255)',		'validation_regex'=>'')
				)));
            }
	    }

		public function ignorePasswords(bool $ignorePasswords)
		{
			$this->ignorePasswords = $ignorePasswords;
		}

	    public function startTransaction(){
	    	$this->dbh->beginTransaction();
	    	$this->is_transaction = TRUE;
	    }

	    public function commitTransaction(){
	    	if(!$this->is_transaction){
	    		return;  //This likely means that the transaction was rolled back and should therefore not be committed. (that or there was never a transaction to begin with).
			}
	    	$this->dbh->commit();
	    	$this->is_transaction = FALSE;
	    }

	    public function rollbackTransaction(){
	    	$this->dbh->rollBack();
	    	$this->is_transaction = FALSE;
	    }

	    public function getOptions( $params=array() ){
	    	$this->data = FALSE;
	    	if( !empty($this->table_definition[$params["column"]]["options"]) ){
	    		if(isset($params['key']) && strlen(trim($params['key']))){
	    			if( !empty($this->table_definition[$params["column"]]["options"][$params["key"]]) ){
	    				$this->data = $this->table_definition[$params["column"]]["options"][$params["key"]];
	    			} else {
	    				$this->data = FALSE;
	    			}
	    		} else if( isset($params['value']) && strlen(trim($params['value'])) ){
	    			$key = array_search($params["value"], $this->table_definition[$params["column"]]["options"]);
	    			if( $key !== FALSE ){
	    				$this->data = $key;
	    			} else {
	    				$this->data = FALSE;
	    			}
	    		} else {
	    			$this->data = $this->table_definition[$params["column"]]["options"];
	    		}
	    	}
	    }

		public function setDatabaseConnection($dbh)
		{
			$this->dbh = $dbh;
			if( !isSet($this->table) || $this->table == '' ){ return; }
			if(__OBRAY_DEBUG_MODE__){ $this->scriptTable(); $this->alterTable(); }
		}
		
		public function setReaderDatabaseConnection($reader)
		{
			$this->reader = $reader;
			if( !isSet($this->table) || $this->table == '' ){ return; }
	    }

        /*************************************************************************************************************

            SCRIPTTABLE

        *************************************************************************************************************/
		public function disableConstraints($returnString=false)
		{
			$sql = "
				SET @ORIG_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
				SET FOREIGN_KEY_CHECKS = 0;
				
				SET @ORIG_UNIQUE_CHECKS = @@UNIQUE_CHECKS;
				SET UNIQUE_CHECKS = 0;
				
				SET @ORIG_TIME_ZONE = @@TIME_ZONE;
				SET TIME_ZONE = '+00:00';
				
				SET @ORIG_SQL_MODE = @@SQL_MODE;
				SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
			";
			if($returnString) return $sql;
			$this->dbh->query($sql);
		}

		public function enableConstraints($returnString=false)
		{
			$sql = "
				SET FOREIGN_KEY_CHECKS = @ORIG_FOREIGN_KEY_CHECKS;

				SET UNIQUE_CHECKS = @ORIG_UNIQUE_CHECKS;

				SET @ORIG_TIME_ZONE = @@TIME_ZONE;
				SET TIME_ZONE = @ORIG_TIME_ZONE;

				SET SQL_MODE = @ORIG_SQL_MODE;
			";
			if($returnString) return $sql;
			$this->dbh->query($sql);
		}

        public function scriptTable($params=array())
		{
			$returnString = false;
			if(!empty($params['returnString'])) $returnString = true;

			if(!$returnString) $this->disableConstraints();
			
			//if( empty($this->dbh) ){ return $this; }

			$sql = '';
			$indexes = [];
			$foreign = [];
			$keys = [];
			$data_types = unserialize(__OBRAY_DATATYPES__);
			
			forEach($this->table_definition as $name => $def){
				if( isSet($def['data_type']) && $def['data_type'] == "filter" ){ continue; }
			    if( array_key_exists('store',$def) == FALSE || (array_key_exists('store',$def) == TRUE && $def['store'] == TRUE ) ){

			        if( !empty($sql) ){ $sql .= ",\n"; }
					if( isSet($def['data_type']) ){
						$data_type = $this->getDataType($def);
						if(!empty($data_type) && $def['data_type'] != 'ENUM'){
							$sql .= "\t`" . $name . '` '. str_replace('size',str_replace(')','',$data_type['size']),$data_types[$data_type['data_type']]['sql']);
						}
						if($def['data_type'] == 'ENUM' && !empty($def['options'])){
							$sql .= "\t`" . $name . '` ENUM (\'' . implode('\',\'', $def['options']) . '\')';
						}
						
					}
					if(isSet($def['nullable']) && $def['nullable'] == false){
						$sql .= ' NOT NULL ';
					}
					if(isSet($def['default'])){
						$sql .= ' DEFAULT \'' . $def['default'] . '\' ';
					} else if($def['primary_key'] != true && empty($def['nullable']) && $def['nullable'] !== false) {
						$sql .= ' DEFAULT NULL ';
					}

					if( array_key_exists('primary_key',$def) && $def['primary_key'] === TRUE  ){
						$this->primary_key_column = $name;
						$sql .= "\t`" . $name . "` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT";
					}

					if(!empty($def['index'])){
						if(!is_array($def['index'])){
							$def['index'] = [0 => $def['index']];
						}
						forEach($def['index'] as $indexKey => $index){
							$indexData = explode(':', $index);
							$columns = '`'.$name.'`';
							if(count($indexData) == 2){
								$indexableColumns = explode('|', $indexData[1]);
								forEach($indexableColumns as $i => $ind){
									if($ind !== $name){
										$columns .= ',`'.$ind.'`';
									}
								}
							}
							if(strpos($index, 'unique') !== false){	
								$indexes[] = 'UNIQUE KEY `'.hash('sha256', $this->table.'_'.$indexKey.'_'.$name.'_uindex').'` ('.$columns.')  USING BTREE';
							} else {
								$indexes[] = 'KEY `'.hash('sha256', $this->table.'_'.$indexKey.'_'.$name.'index').'` ('.$columns.') USING BTREE';
							}
						}
					}

					if(!empty($def['foreign'])){
						$fk = explode(':', $def['foreign']);
						if(count($fk) == 2){
							$obj = $this->route($fk[1]);
							$onDelete = 'ON DELETE CASCADE';
							$onUpdate = 'ON UPDATE CASCADE';
							if(array_key_exists('onDelete', $def) && empty($def['onDelete']) ) $onDelete = 'ON DELETE SET NULL';
							if(array_key_exists('onUpdate', $def) && empty($def['onUpdate']) ) $onUpdate = 'ON UPDATE SET NULL';
							if(array_key_exists('onDelete', $def) && $def['onDelete'] == 'restrict' ) $onDelete = 'ON DELETE RESTRICT';
							if(array_key_exists('onUpdate', $def) && $def['onUpdate'] == 'restrict' ) $onUpdate = 'ON UPDATE RESTRICT';
							$key_name = hash('sha256', $this->table.'_'.$name.'_foreign');
							$foreign[] = 'CONSTRAINT `' . $key_name . '` FOREIGN KEY (`'.$name.'`) REFERENCES `'.$obj->getTable().'` (`'.$fk[0].'`) '.$onDelete.' '.$onUpdate;
							$keys[] = 'KEY `' . $key_name . '` (`'.$name.'`)';
						}
					}
			    }
			}
			$indexesAndConstraints = array_merge($indexes, $keys, $foreign);
			
			$sql = 'CREATE TABLE `' . $this->table . "` (\n\n" . $sql;
			if( $this->enable_system_columns ){ 
				$sql .= ",\n\n\t`OCDT` DATETIME DEFAULT CURRENT_TIMESTAMP,\n\t`OCU` INT(11) UNSIGNED,\n\t`OMDT` DATETIME DEFAULT CURRENT_TIMESTAMP,\n\t`OMU` INT(11) UNSIGNED"; 
			}
			if( !empty($this->primary_key_column) ){ 
				$sql .= ",\n\nPRIMARY KEY (`" . $this->primary_key_column . '`) '; 
			}
			if( !empty($indexesAndConstraints) ){
				$sql .= ",\n";
				$sql .= implode(",\n", $indexesAndConstraints);
			}
			$sql .= "\n\n) ENGINE=".__OBRAY_DATABASE_ENGINE__.' DEFAULT CHARSET='.__OBRAY_DATABASE_CHARACTER_SET__.'; ';
			
			if(!empty($params['debug'])) $this->console($sql);
			$this->sql = $sql;
			
			if($returnString) return $this->data = $this->sql;
			
			$this->console($sql. "\n");
			$this->statement = $this->dbh->query($sql);
			
			if($this->statement === false) throw new \Exception("Script " . $this->table . "failed\n");

			$this->run('LOCK TABLES `'.$this->table.'` WRITE;');
			$this->run('UNLOCK TABLES;');
			$this->enableConstraints();

        }

		public function getConstraints()
		{
			/****
			$sql = "SELECT tc.constraint_schema,tc.constraint_name,tc.table_name,tc.constraint_type,kcu.table_name,kcu.column_name,kcu.referenced_table_name,kcu.referenced_column_name,rc.update_rule,rc.delete_rule
					  FROM information_schema.table_constraints tc
				INNER JOIN information_schema.key_column_usage kcu ON tc.constraint_catalog = kcu.constraint_catalog
																  AND tc.constraint_schema = kcu.constraint_schema
																  AND tc.constraint_name = kcu.constraint_name
																  AND tc.table_name = kcu.table_name
				 LEFT JOIN information_schema.referential_constraints rc ON tc.constraint_catalog = rc.constraint_catalog
																		AND tc.constraint_schema = rc.constraint_schema
																		AND tc.constraint_name = rc.constraint_name
																		AND tc.table_name = rc.table_name
				     WHERE tc.constraint_schema = '" . __OBRAY_DATABASE_NAME__ . "' AND tc.table_name = '".$this->table."'";
			 */
			//$this->console($sql);
			$sql = "SELECT * 
			          FROM information_schema.table_constraints tc
					  JOIN information_schema.key_column_usage kcu ON tc.constraint_catalog = kcu.constraint_catalog
																  AND tc.constraint_schema = kcu.constraint_schema
																  AND tc.constraint_name = kcu.constraint_name
																  AND tc.table_name = kcu.table_name
					 WHERE tc.constraint_schema = '".__OBRAY_DATABASE_NAME__."' AND tc.table_name = '".$this->table."';";
			$statement = $this->dbh->prepare($sql);
        	$statement->execute();

			$statement->setFetchMode(\PDO::FETCH_OBJ);
        	$constraints = $statement->fetchAll();
			$this->data = $constraints;


		}

        /*************************************************************************************************************

            ALTERTABLE

        *************************************************************************************************************/

        public function alterTable(){
        	if( empty($this->dbh) ){ return $this; }

        	$sql = 'DESCRIBE '.$this->table.';';
        	$statement = $this->dbh->prepare($sql);
        	$statement->execute();

        	$statement->setFetchMode(\PDO::FETCH_OBJ);
        	$data = $statement->fetchAll();

        	$temp_def = $this->table_definition;
        	$obray_fields = array(3=>'OCDT',4=>'OCU',5=>'OMDT',6=>'OMU');
        	forEach( $obray_fields as $of ){ unset($this->table_definition[$of]); }

        	$data_types = unserialize(__OBRAY_DATATYPES__);



        	forEach($data as $def){
        		if($temp_def[$def->Field]){
					$this->console("%s", "Found " . $def->Field . " checking column properties...\n", "GreenBold");
					// if not a primary key, check that the data types, nullableness, and default value matches the definition (and if not change it)
					if(empty($temp_def[$def->Field]['primary_key'])){
						$alter = false;
						// checking type
						$this->console("%s", "\tChecking type............", "YellowBold");
						if( false === $dataType = $this->alterCheckType($def->Type, $temp_def[$def->Field]['data_type'])){
							$dataType = $defType;
						} else { $alter = true;}
						// checking nullable
						$this->console("%s", "\tChecking nullable........", "YellowBold");
						if( false === $nullable = $this->alterCheckNullable($def->Null, $temp_def[$def->Field]['nullable']??true)){
							$nullable = ($def->Null=='YES')?'NULL':'NOT NULL';
						} else { $alter = true;}
						// checking default value
						$this->console("%s", "\tChecking default value...", "YellowBold");
						if( false === $default = $this->alterCheckDefaultValue($def->Default, $temp_def[$def->Field]['default']??null)){
							$default = 'default ' . (($def->Default==null)?'NULL':"'".$def->Default."'");
						} else { $alter = true;}

						if($alter === true) $this->alterTableColumn($def->Field, $dataType, $nullable, $default);
					}
					if(!empty($temp_def[$def->Field]['delete'])){
						$this->console("%s", "Deleting column...\n", "GreenBold");
					}
					if(!empty($temp_def[$def->Field]['rename'])){
						$this->console("%s", "Renaming column...\n", "GreenBold");
					}
				} else {
					$this->console("%s","Field " . $def->Field . " does not exist.  Consider adding to the table definition and specifying delete = true\n", "RedBold");
				}

        	}

        }

		private function AlterTableColumn($column, $dataType, $nullable, $default)
		{
			$sql = "ALTER TABLE " . $this->table . " ALTER COLUMN `" . $column . "` " . $dataType . " " . $nullable . " " . $default . ";";
			$this->console($sql . "\n");
			//$statement = $this->dbh->prepare($sql);
        	//$statement->execute();
		}

		private function alterCheckType($existingType, $definedType)
		{
			$data_types = unserialize(__OBRAY_DATATYPES__);
			$definedTypeSQL = $data_types[$definedType]["my_sql_type"];
			if(empty($definedTypeSQL)) $definedTypeSQL = $definedType;
			if($existingType !== $definedTypeSQL){
				$this->console("%s", " error: The existing type '" . $existingType . "' does not match defined type '" . $definedTypeSQL . "'\n", "RedBold");
				return $definedTypeSQL;
			}
			$this->console("%s", " ✔\n", "GreenBold");
			return false;
		}

		private function alterCheckNullable($existingNullable, $definedNullable)
		{
			if($existingNullable === false) $definedNullable = 'NO';
			if($existingNullable === true) $definedNullable = 'YES';
			if($definedNullable != $definedNullable){
				$this->console("%s", " error: The existing nullable '" . $existingNullable . "' does not match the defined nullable '" . $definedNullable . "'\n", "RedBold");
				if($definedNullable == 'YES') return 'NULL';
				return 'NOT NULL';
			}
			$this->console("%s", " ✔\n", "GreenBold");
			return false;
		}

		private function alterCheckDefaultValue($existingDefault, $definedDefault)
		{
			if($existingDefault != $definedDefault){
				$this->console("%s", " error: The existing defualt value '" . $existingDefault . "' does not match the defined default value '" . $definedDefault . "'\n", "RedBold");
				return "default " . (($definedDefault==null)?'NULL':"'".$definedDefault."'");
			}
			$this->console("%s", " ✔\n", "GreenBold");
			return false;
		}

		/********************************************************************

            GETTABLEDEFINITION

        ********************************************************************/

        public function getTableDefinition(){ return $this->data = $this->table_definition; }
		public function getTable(){ return $this->table; }
		public function getPublicDef()
		{
			$publicDef = (object)[
				'primary_key' => null,
				'name' => $this->name??null,
				'columns' => []
			];
			forEach($this->table_definition as $key => $def){
				$options = null;
				if($def['primary_key']){
					$publicDef->primary_key = $key;
				}
				if($def['type']){
					if(!empty($def['options'])){
						$options = $this->route($def['options'])->data;
					}
					$publicDef->columns[$key] = (object)[
						'type' => $def['type'],
						'label' => $def['label']??null,
						'required' => $def['required']??false,
						'placehoder' => $def['required']??'',
						'options' => $options??[]
					];
					if(!empty($def['form_label'])) $publicDef->columns[$key]->{'form_label'} = $def['form_label'];
					
				}
			}
			return $publicDef;
		}
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
					//if( !empty($def["options"]) ){
					//	$options = array_change_key_case($def["options"],CASE_LOWER);
					//	if( !empty($options[strtolower($param)]) && !is_array($options[strtolower($param)]) ){ $data[$key] = $options[strtolower($param)]; $option_is_set = TRUE; } else { $data[$key] = $param; }
					//} else {
						$data[$key] = $param;
					//}
					$data_type = $this->getDataType($def);

					if( isSet($this->required[$key]) ){ unset($this->required[$key]); }
					if( isSet($def['data_type']) && !empty($this->data_types[$data_type['data_type']]['validation_regex']) && !preg_match($this->data_types[$data_type['data_type']]['validation_regex'],$params[$key]) && $params[$key] == NULL ){
					   $this->throwError((isSet($def['error_message'])?$def['error_message']:isSet($def['label']))?$def['label'].' is invalid.':$key.' is invalid.','500',$key);
					}

					if( isSet($def['data_type']) && $def['data_type'] == 'password' && $this->ignorePasswords === false ){ $salt = '$2a$12$'.$this->generateToken(); $data[$key] = crypt($params[$key],$salt); }

					if( isSet($params[$key]) ){
					   if( !empty($sql) ){ $sql .= ','; $sql_values .= ','; }
					   $sql .= $key; $sql_values .= ':'.$key;
					}
				}
			}

        	if( !empty($this->required) ){
	        	forEach($this->required as $key => $value){
	        		$def = $this->table_definition[$key];
		        	$this->throwError((isSet($def['error_message'])?$def['error_message']:isSet($def['label']))?$key.' is required.':$key.' is required.','500',$key);
	        	}
        	}

        	if( $this->isError() ){ $this->throwError(isSet($this->general_error)?$this->general_error:'There was an error on this form, please make sure the below fields were completed correclty: '); return $this; }

        	if( $this->enable_system_columns ){
        		if( isSet($_SESSION['ouser']->ouser_id) ){ $ocu = $_SESSION['ouser']->ouser_id; } else { $ocu = 0; }
        		$system_columns = ", OCDT, OCU ";
        		$system_values = ', \''.date('Y-m-d H:i:s').'\', '.$ocu;
        	} else {
        		$system_columns = "";
        		$system_values = "";
        	}

        	$this->sql  = ' INSERT INTO '.$this->table.' ( '.$sql.$system_columns.' ) values ( '.$sql_values.$system_values.' ) ';
        	$statement = $this->dbh->prepare($this->sql);
        	forEach( $data as $key => $dati ){
        		if( $dati === 'NULL' ){
        			$statement->bindValue($key, null, PDO::PARAM_NULL);
        		} else {
        			$statement->bindValue($key, $dati);
        		}
        	}
        	$this->script = $statement->execute();
        	if( empty($this->is_transaction) ){
				$get_params = array( $this->primary_key_column => $this->dbh->lastInsertId() );
				if( !empty($option_is_set) ){ $get_params["with"] = "options"; }
				$this->get( $get_params );
			}

        }

        /********************************************************************
            UPDATE function
        ********************************************************************/

        public function update($params=array()){

        	if( empty($this->dbh) ){ return $this; }

        	$sql = ''; $sql_values = ''; $data = array();
        	$this->data_types = unserialize(__OBRAY_DATATYPES__);

			$this->getWorkingDef();

			/*
			if( isSet($this->slug_key_column) && isSet($this->slug_value_column) && isSet($params[$this->slug_key_column]) ){
				if( isSet($this->parent_column) && isSet($params[$this->parent_column]) ){ $parent = $params[$this->parent_column];  } else { $parent = null; }
				$params[$this->slug_value_column] = $this->getSlug($params[$this->slug_key_column],$this->slug_value_column,$parent);
			}
			*/

        	forEach( $params as $key => $param ){

	        	if( isSet($this->table_definition[$key]) ){

					$def = $this->table_definition[$key];
					//if( !empty($def["options"]) ){
					//	$options = array_change_key_case($def["options"],CASE_LOWER);
					//	if( !empty($options[strtolower($param)]) && !is_array($options[strtolower($param)]) ){ $data[$key] = $options[strtolower($param)]; $option_is_set = TRUE; } else { $data[$key] = $param; }
					//} else {
						$data[$key] = $param;
					//}
		        	$data_type = $this->getDataType($def);

					if( isSet($def['required']) && $def['required'] === TRUE && (!isSet($params[$key]) || $params[$key] === NULL || $params[$key] === '') ){
						$this->throwError((isSet($def['error_message'])?$def['error_message']:isSet($def['label']))?$def['label'].' is required.':$key.' is required.',500,$key);
					}

					if( (isSet($def['data_type']) && !empty($this->data_types[$data_type['data_type']]['validation_regex']) && !preg_match($this->data_types[$data_type['data_type']]['validation_regex'],$params[$key])) && $params[$key] == NULL ){
						$this->throwError((isSet($def['error_message'])?$def['error_message']:isSet($def['label']))?$def['label'].' is invalid.':$key.' is invalid.',500,$key);
					}

					if( isSet($def['data_type']) && $def['data_type'] == 'password' && $this->ignorePasswords === false ){ $salt = '$2a$12$'.$this->generateToken(); $data[$key] = crypt($params[$key],$salt); }

					if( !empty($sql) ){ $sql .= ','; $sql_values .= ','; }
					$sql .= $key . ' = :'.$key.' ';

	        	}
        	}

        	if( empty($this->primary_key_column) ){ $this->throwError('Please specify a primary key.','primary_key','500'); }
        	if( !isSet( $params[$this->primary_key_column] ) ){ $this->throwError('Please specify a value for the primary key.','500',$this->primary_key_column); }
        	if( $this->isError() ){ return $this; }



        	if( $this->enable_system_columns ){
        		if( isSet($_SESSION['ouser']->ouser_id) && !empty($_SESSION['ouser']->ouser_id) ){ $omu = $_SESSION['ouser']->ouser_id; } else { $omu = 0; }
        		$system_columns = ', OMDT = \''.date('Y-m-d H:i:s').'\', OMU = '.$omu;

        	} else {
        		$system_columns = "";
        	}

        	$this->sql  = ' UPDATE '.$this->table.' SET '.$sql.$system_columns.' WHERE '.$this->primary_key_column.' = :'.$this->primary_key_column.' ';
        	$statement = $this->dbh->prepare($this->sql);
        	forEach( $data as $key => $dati ){
        		if( $dati == 'NULL' ){
        			$statement->bindValue($key, null, PDO::PARAM_NULL);
        		} else {
        			$statement->bindValue($key, $dati);
        		}
        	}
        	$this->script = $statement->execute();

        	if( empty($this->is_transaction) ){
				$get_params = array($this->primary_key_column=>$params[$this->primary_key_column]);
				if( !empty($option_is_set) ){ $get_params["with"] = "options"; }
				$this->get( $get_params );
			}
			

        }

		public function onDuplicateKeyUpdate()
		{
			
		}

        /********************************************************************

            DELETE function

        ********************************************************************/

        public function delete($params=array()){

        	if( empty($this->dbh) ){ return $this; }
        	$original_params = $params;

        	$this->where = $this->getWhere($params,$values);

			if( empty( $this->where ) ){ $this->throwError('Please provide a filter for this delete statement',500); }
			if( !empty( $this->errors ) ){ return $this; }

        	$this->sql  = ' DELETE FROM ' . $this->table . $this->where;
        	$statement = $this->dbh->prepare($this->sql);
        	forEach($values as $value){ if( is_integer($value) ){ $statement->bindValue($value['key'], trim($value['value']), PDO::PARAM_INT); } else { $statement->bindValue($value['key'], trim((string)$value['value']), PDO::PARAM_STR); } }
        	$this->script = $statement->execute();


        }

        /********************************************************************

            GET function

        ********************************************************************/

        public function get($params=array()){

        	$original_params = $params;

        	if( !empty($this->enable_system_columns) ){
				$this->table_definition['OCDT'] = array('data_type'=>'datetime');
				$this->table_definition['OMDT'] = array('data_type'=>'datetime');
				$this->table_definition['OCU'] = array('data_type'=>'integer');
				$this->table_definition['OMU'] = array('data_type'=>'integer');
			}

        	$limit = ''; $order_by = ''; $filter = TRUE;
        	if( isSet($params['start']) && isSet($params['rows']) ){ $limit = ' LIMIT ' . $params['start'] . ',' . $params['rows'] . ''; unset($params['start']); unset($params['rows']); unset($original_params['start']); unset($original_params['rows']); }
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
	        $withs_to_pass = array();
	        $filter_columns = array();
	        
	        forEach($this->table_definition as $column => $def){
	        	if( isSet($def['data_type']) && $def['data_type'] == "filter" ){ $filter_columns[] = $columns; continue; }
	        	if( isSet($def['data_type']) && $def['data_type'] == 'password' && isSet($params[$column]) ){ $password_column = $column; $password_value = $params[$column]; unset($params[$column]); }
	        	$columns[] = $this->table.'.'.$column;
	        	if( array_key_exists('primary_key',$def) ){ $primary_key = $column; }

				// HANDLE OPTIONS
				if(!empty($params[$column]) && !empty($def["options"]) ){
					$options = $def["options"];
					$options = array_change_key_case($options, CASE_LOWER);
					if( !empty($options[ strtolower($params[$column]) ]) ){ $params[$column] = $options[ strtolower($params[$column]) ]; }
				}

	        	forEach( $withs as $i => &$with ){
	        		if( !is_array($with) && array_key_exists($with,$def) ){
	        			unset( $original_withs[$i] );
	        			$name = $with;
						if( !is_array($def[$with]) ){
			        		$with = explode(':',$def[$with]);
			        		$with[] = $column;
			        		$with[] = $name;
						} else {
							$with = array();
							$with[] = $column;
							$with[] = $name;
						}
	        		}
	        	}
	        }


	        $filter_join = "";
	        forEach( $withs as $i => $w ){ if( !is_array($w) ){ $withs_to_pass[] = $w; unset($withs[(int)$i]);  } }
	        $withs = array_values($withs);
	        $withs_to_pass = http_build_query(array('with'=>implode('|',$withs_to_pass)));
	        forEach( $withs as &$with ){
	        	if( strpos($with[1],'with') === FALSE ){
		        	if( strpos($with[1],'?') === FALSE ){ $with[1] .= '?' . $withs_to_pass; } else { $with[1] .= '&' . $withs_to_pass; }
		        }
	        }

	        if( isSet($original_params['with']) ){ $original_params['with'] = implode('|',$original_withs); }
	        $values = array();
	        $where_str = $this->getWhere($params,$values,$original_params);

	        $this->sql = 'SELECT '.implode(',',$columns).' FROM '.$this->table . $this->getJoin() . $filter_join .$where_str . $order_by . $limit;
	        $statement = (!empty($this->reader))?$this->reader->prepare($this->sql):$this->dbh->prepare($this->sql);
	        forEach($values as $value){ if( is_integer($value) ){ $statement->bindValue($value['key'], trim($value['value']), PDO::PARAM_INT); } else { $statement->bindValue($value['key'], trim((string)$value['value']), PDO::PARAM_STR); } }
			$statement->execute();
			$statement->setFetchMode(\PDO::FETCH_NUM);
			$data = $statement->fetchAll(\PDO::FETCH_OBJ);
			
	        $this->data = $data;

	        if( !empty($withs) && !empty($this->data) ){

		        forEach( $withs as &$with ){

					// HANDLES OPTIONS
					if( strpos($with[1],"options?with") !== FALSE  ){
						if( !empty($this->table_definition[$with[0]]["options"]) ){
							$column = $with[0];
							$options = $this->table_definition[$with[0]]["options"];
							forEach( $this->data as $key => $data ){
								$option = array_search( $data->$column,$options );
								if( $option !== FALSE ){
									$this->data[$key]->$column = $option;
								}
							}
						}
						continue;
					}




		        	$ids_to_index = array();
		        	if( !is_array($with) ){ break; }
		        	$with_key = $with[0]; $with_column = $with[2]; $with_name = $with[3]; $with_components = parse_url($with[1]); $sub_params = array();
		        	forEach( $this->data as $i => $data ){ if( !isSet($ids_to_index[$data->$with_column]) ){ $ids_to_index[$data->$with_column] = array(); } $ids_to_index[$data->$with_column][] = (int)$i; }
		        	$ids = array();
	        		forEach( $this->data as $row ){ $ids[] = $row->$with_column; }
		        	$ids = implode('|',$ids);
		        	if( !empty($with_components['query']) ){ parse_str($with_components['query'],$sub_params); }
		        	if( $ids !== '' ){ $with[0] = $with[0].'='.$ids; } else { $with[0] = $with[0].'='; }
		        	if( isSet($original_params['with']) && empty($original_params['with']) ){ unset($original_params['with']); }
		        	if( !empty($original_params['with']) && !empty($sub_params['with']) ){
		        		$original_params['with'] = array_unique(array_merge( explode('|',$sub_params['with']), explode('|',$original_params['with']) ));
		        		$original_params['with'] = implode('|',$original_params['with']);
		        	}
		        	$sub_params = array_replace($sub_params,$original_params);
		        	$new_params = array(); parse_str($with[0],$new_params);
		        	$sub_params = array_replace($sub_params,$new_params);

		        	if( !empty($this->data) && !empty($withs[0]) && in_array('children',$withs[0]) ){ $sub_params['with'] = 'children'; }
			        $with = $this->route($with_components['path'].'get/',$sub_params)->data;
					if(!empty($with)){
						forEach( $with as &$w ){
							if( isSet($ids_to_index[$w->$with_key]) ){
								forEach( $ids_to_index[$w->$with_key] as $index ){
									if( !isSet($this->data[$index]->$with_name) ){ $this->data[$index]->$with_name = array(); }
									array_push($this->data[$index]->$with_name,$w);
								}
							}
						}
					}
			        if($filter){ forEach( $this->data as $i => $data ){ if( empty($data->$with_name) ){ unset($this->data[$i]); } } $this->data = array_values((array)$this->data); }

		        }

	        }

	        if( $this->table == 'ousers' || (isset($this->user_session) && $this->table == $this->user_session) ){
	        	forEach( $this->data as $i => &$data ){
		        	if( isSet($password_column) && strcmp($data->$password_column,crypt($password_value,$data->$password_column)) != 0 ){ unset($this->data[$i]); }
		        	unset($data->ouser_password);
	        	}
        	}
			
			//Restructure the result set to be keyed by the column name provided
			if(!empty($original_params['keyed']) && !empty($this->data[0]->{$original_params['keyed']}))
			{
				$keyed_data = array();
				foreach($this->data as $key => $data)
				{
					if(isset($data->{$original_params['keyed']}))
						$keyed_data[strtolower($data->{$original_params['keyed']})] = $data;
				}
				
				if(count($keyed_data))
					$this->data = $keyed_data;
			}
        	$this->filter = $filter; $this->recordcount = count($this->data);

	        return $this;

        }

        private function getJoin(){

        	if( !empty($this->join) ){
	        	$obj = $this->route($this->join);
	        	forEach( $obj->table_definition as $key => $def ){
	        		if( !empty($def["primary_key"]) && $def["primary_key"] === TRUE ){ $primary_key = $key; }
	        	}
	        	forEach( $this->table_definition as $key => $def ){
	        		if( !empty($def["primary_key"]) && $def["primary_key"] === TRUE ){ $this->primary_key_column = $key; }
	        	}
	        	return ' INNER JOIN '.strtolower($obj->table).' ON '.strtolower($obj->table).'.'.$primary_key.' = '.strtolower($this->table).'.'.$this->primary_key_column.' ';
        	} else {
        		return '';
        	}

        }

        /********************************************************************

            GETWHERE

        ********************************************************************/

		private function getWhere( &$params=array(),&$values=array(),&$original_params=array() ){

			if( !empty($this->enable_system_columns) ){
				$this->table_definition['OCDT'] = array('data_type'=>'datetime');
				$this->table_definition['OMDT'] = array('data_type'=>'datetime');
			}

	        $where = array(); $count = 0; $p = array();
	        forEach( $params as $key => &$param ){
				$original_key = $key;
	        	$operator = '=';
	        	switch(substr($key,-1)){
		        	case '!': case '<': case '>':
		        		$operator = substr($key,-1).'=';
		        		//$p[str_replace(substr($key,-1),'',$key)] = $params[$key];
		        		$key = str_replace(substr($key,-1),'',$key);
		        	default:
		        		if( empty($params[$key]) ){
		        			$array = explode('~',$key);
		        			if( count($array) === 2 ){ $param = $array[1]; $key = $array[0]; unset($params[$key]); $operator = 'LIKE'; }
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
		        		if( $operator == '=' && count($ors) > 1 ){

			        			$value_keys = array();
			        			forEach( $ors as $v ){
				        			++$count; $values[] = array('key'=>':'.$key.'_'.$count,'value'=>$v);
				        			$value_keys[] = ':'.$key.'_'.$count;
			        			}

			        			$where[] = array('join'=>'','key'=>$key,'value'=>'('.implode(',',$value_keys).')','operator'=>'IN');


			        	} else {

				        	$or_key = '';

				        	forEach( $ors as $v ){

								if( $v !== 'NULL' ){
					        		if( $operator == 'LIKE' ){ $v = '%'.$v.'%'; }
					        		++$count;
					        		$values[] = array('key'=>':'.$key.'_'.$count,'value'=>$v);
						        	$where[] = array('join'=>$or_key,'key'=>$key,'value'=>':'.$key.'_'.$count,'operator'=>$operator);
						        	$or_key = 'OR';
								} else {
									$where[] = array('join'=>$or_key,'key'=>$key,'value'=>' IS NULL ','operator'=>'');
								}

					        }

				        }
				        $where[] = array('join'=>')','key'=>'','value'=>'','operator'=>'');
		        	}
		        }

				if( !empty($original_params) && $key == 'OMDT' ){ unset($original_params[$original_key]); }
				if( !empty($original_params) && $key == 'OCDT' ){ unset($original_params[$original_key]); }

	        }

	        $where_str = '';
	        if( !empty($where) ){
		        $where_str = ' WHERE ';
		        forEach( $where as $key => $value ){

		        	$val = array();
		        	forEach( $values as $i => $v ){
		        		//if( !empty($v["value"]) && $v["value"] == 'NULL' ){ $val = &$values[$i]; break; }
		        	}

		        	if( !empty($val) && $val["value"] == 'NULL' ){

		        		if( $value['operator'] === '=' ){
		        			$where_str .= ' ' . $value['join'] . ' ' . $value['key'] . ' IS NULL ';
		        		} else if ( $value['operator'] === '!=' ){
		        			$where_str .= ' ' . $value['join'] . ' ' . $value['key'] . ' IS NOT NULL ';
		        		}
		        	} else {
			        	$where_str .= ' ' . $value['join'] . ' ' . $value['key'] . ' ' . $value['operator'] . ' ' . $value['value'] . ' ';
			    	}
			        //if( $value['operator'] == '!=' ){ $where_str .= ' OR '.$value['key'].' IS NULL '; }
		        }
	        }
	        return $where_str;

        }

        /********************************************************************

            DUMP

        ********************************************************************/

        public function dump($params=array()){

	        //exec('mysqldump --user='.__OBRAY_DATABASE_USERNAME__.' --password='.__OBRAY_DATABASE_PASSWORD__.' --host='.__OBRAY_DATABASE_HOST__.' '.__OBRAY_DATABASE_NAME__.' '.$this->table.' | gzip > '.dirname(__FILE__).'backups/'.$this->table.'-'.time().'.sql.gz');

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

			SORT

        ********************************************************************/

        public function sort($column,$order='asc',$with=null,$query=''){

	        parse_str($query,$this->params);
	        $this->column = $column;
	        $this->order = $order;
	        if( empty($with) ){
		        $this->with = array();
	        } else {
		        $this->with = explode('|',$with);
	        }

		    usort($this->data,array($this,'sortCallback'));

		   	return $this;
        }

        private function sortCallback($a,$b){

	        $column = $this->column;
	        $filters = array();

	        $with_array = $this->with;
	        if( !empty($this->with) ){

	        	$with = array_shift($with_array);

	        	if( empty($a->$with) || empty($b->$with) ){ return FALSE; }
	        	$filters_a = $a->$with;
	        	$filters_b = $b->$with;

	        	$final_a = new stdClass();
	        	forEach( $filters_a as $a ){
		        	forEach( $this->with as $i => $with ){
		        		if( !empty($a->$with) ){
			        		forEach( $a->$with as $a_item ){
			        			forEach( $this->params as $key => $value ){
			        				if( !empty($a_item->$key) && $a_item->$key == $value ){
			        					$final_a = $a_item;
			        				}
			        			}
			        		}
		        		}
		        	}
	        	}

	        	$final_b = new stdClass();
	        	forEach( $filters_b as $b ){
		        	forEach( $this->with as $i => $with ){
		        		if( !empty($b->$with) ){
			        		forEach( $b->$with as $b_item ){
			        			forEach( $this->params as $key => $value ){
			        				if( !empty($b_item->$key) && $b_item->$key == $value ){
			        					$final_b = $b_item;
			        				}
			        			}
			        		}
		        		}
		        	}
	        	}

	        }

	        if( empty($final_a->$column) ){ return FALSE; }
	        if( empty($final_b->$column) ){ return TRUE	; }

	    	$a = $final_a->$column;
			$b = $final_b->$column;

	        switch( $this->order ){
		        case 'asc': case 'ASC':
		        	if( $a > $b ){ return TRUE; } else { return FALSE; }
		        	break;
		        case 'desc': case 'DESC':
		        	if( $a < $b ){ return TRUE; } else { return FALSE; }
		        	break;
	        }

        }

        /********************************************************************

			GETFIRST

        ********************************************************************/

        public function getFirst(){
			if( empty($this->errors) ){
				if( !isSet($this->data) || !is_array($this->data) ){ $this->data = array(); }
				forEach( $this->data as $i => $data ){ $v = &$this->data[$i]; return $v; }
				return reset($this->data);
			} else {
				return 0;
			}
        }

        /********************************************************************

			RUN

        ********************************************************************/

        public function run( $sql, $bind=array() )
        {
            if (is_array($sql)) {
                $sql = $sql["sql"];
            }
            try {
				$isSelect = false;
				if (preg_match("/^select/i", $sql)) $isSelect = true;

                $statement = ($isSelect && !empty($this->reader))?$this->reader->prepare($sql):$this->dbh->prepare($sql);
                $result = $statement->execute($bind);
                $this->data = [];
                if ($isSelect) {
                    $statement->setFetchMode(\PDO::FETCH_OBJ);
                    while ($row = $statement->fetch()) {
                        $this->data[] = $row;
                    }
                }
                else {
                    $this->data = $result;
                }
            }
            catch (Exception $e) {
                if (isset($this->is_transaction) && $this->is_transaction) {
                    $this->rollbackTransaction();
                }
                $this->throwError($e);
                $this->logError(oCoreProjectEnum::ODBO, $e);
            }
            return $this;
        }
		
		public function explain( $sql ){

			$this->console( 'EXPLAIN ' . $sql );
			
            try {
				
				$result = $this->dbh->query('EXPLAIN ' . $sql);
				forEach( $result as $r ){
					$this->console($r);
				}
                
            }
            catch (Exception $e) {
                if (isset($this->is_transaction) && $this->is_transaction) {
                    $this->rollbackTransaction();
                }
                $this->throwError($e);
                $this->logError(oCoreProjectEnum::ODBO, $e);
            }

            return $this;
		}

        /********************************************************************

        runStoredProc

         ********************************************************************/

        public function runStoredProc($proc, $params=array()) {
            $this->data = [];
            $paramString = "";
            $paramCount = 0;
            foreach ($params as $paramName => $paramValue) {
                if ($paramCount > 0) {
                    $paramString .= ",";
                }
                $paramString .= ":" . $paramName;
                $paramCount++;
            }

            $procString = "CALL " . $proc . "(" . $paramString . ")";
            $statement = $this->dbh->prepare($procString);
            if ($paramCount > 0) {
                foreach ($params as $paramName => $paramValue) {
                    $statement->bindValue(':' . $paramName, $paramValue);
                }
            }

            try {
                $statement->execute();
                $statement->setFetchMode(\PDO::FETCH_OBJ);
                $this->data = $statement->fetchAll();
            } catch (Exception $e) {
            	if(isset($this->is_transaction) && $this->is_transaction){
            		$this->rollbackTransaction();
				}
                $this->throwError($e);
                $this->logError(oCoreProjectEnum::ODBO, $e);
            }
            return $this->data;
        }

        /********************************************************************

			COUNT

        ********************************************************************/

        public function count( $params=array() ){

			$values = array();
			$where_str = $this->getWhere($params,$values);
			$this->sql = 'SELECT COUNT(*) as count FROM '.$this->table.' '.$where_str;
	        $statement = $this->dbh->prepare($this->sql);
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
	        $statement->setFetchMode(\PDO::FETCH_NUM);
	        $this->data = $statement->fetchAll(\PDO::FETCH_OBJ);
	        return $this;

        }

        /********************************************************************

			MATH FUNCTIONS

        ********************************************************************/

        public function sum( $params=array() ){  $this->math('SUM','sum',$params); }
        public function average( $params=array() ){  $this->math('AVG','average',$params); }
        public function maximum( $params=array() ){  $this->math('MAX','maximum',$params); }
        public function minimum( $params=array() ){  $this->math('MIN','minimum',$params); }
        public function truncate(){
	        $statement = $this->dbh->prepare('TRUNCATE TABLE '.$this->table);
	        $statement->execute();
	   }

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

	        if(__OBRAY_DEBUG_MODE__){
	        	$sql = 'CREATE TABLE IF NOT EXISTS ologs ( olog_id INT UNSIGNED NOT NULL AUTO_INCREMENT,olog_label VARCHAR(255),olog_data TEXT,OCDT DATETIME,OCU INT UNSIGNED, PRIMARY KEY (olog_id) ) ENGINE='.__OBRAY_DATABASE_ENGINE__.' DEFAULT CHARSET='.__OBRAY_DATABASE_CHARACTER_SET__.'; ';
				$statement = $this->dbh->prepare($sql); $statement->execute();
	        }

		    $sql = 'INSERT INTO ologs(olog_label,olog_data,OCDT,OCU) VALUES(:olog_label,:olog_data,:OCDT,:OCU);';
		    $statement = $this->dbh->prepare($sql);
		    $statement->bindValue('olog_label',$label, PDO::PARAM_STR);
		    $statement->bindValue('olog_data',json_encode($object,JSON_PRETTY_PRINT),PDO::PARAM_STR);
		    $statement->bindValue('OCDT',date('Y-m-d H:i:s'), PDO::PARAM_STR);
		    $statement->bindValue('OCU',isSet($_SESSION['ouser']->ouser_id)?$_SESSION['ouser']->ouser_id:0, PDO::PARAM_INT);
		    $statement->execute();

        }

         /********************************************************************

			GENERATETOKEN

        ********************************************************************/

        private function generateToken(){
			$safe = FALSE;
			return hash('sha512',base64_encode(openssl_random_pseudo_bytes(128,$safe)));
		}

}?>
