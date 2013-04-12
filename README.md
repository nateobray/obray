# obray

Obray is lightweight PHP object oriented MVC framework designed to help you write less code and do more quickly.

## Introduction

Obray allows you to map php objects directly to URIs.  To do this every object in Obray extends the OObject class, for example:


	Class MyClass extends OObject{
	
		public permissions = array(
			"secondFunction" => "any",
			"firstFunction" => "any"
		)
	
		public function firstFunction($params){
			
			$this->result = $params["a"] + $params["b"];
			
		}
	
		public function secondFunction(){
		
			$params = array("a"=>1,"b"=>2);
		
			$my_instance = $this->route('/lib/MyClass/firstFunction/',$params);  // instantiate object and call firstFunction and return the object

		}
	
	}


From within an OObject class you can access any path defined as a class path.  In this case the "lib" path and any of it's public methods.

## ORouter

ORouters job is to handle an HTTP request by converiting into a routable path and return the object in an output format such JSON with appropriate status codes and HTTP headers.  For example if you put this in your browser address bar


	http://www.myobrayapplication.com/lib/MyClass/firstFunction/?a=1&b=2


you will get the response:


	{
		"object":"MyClass",
		"result":3
	}


External HTTP requests that are handled by ORouter are restricted not only by public/private declarations, but also by the permissions array. Unless you explicitely define a functions permissions in the permissions array a request through ORouter will be blocked with 403 Forbidden status code (more on permissions).  
