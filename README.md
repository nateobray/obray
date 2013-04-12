# obray

Obray is lightweight PHP object oriented MVC framework designed to help you write less code and do more quickly.

## Introduction

Obray allows you to map PHP objects directly to URIs even from within your application!  To do this every object in Obray extends the OObject class, for example:

	
	Class MyClass1 extends OObject{
		
		public permissions = array(
			"firstFunction" => "any"
		);
	
		public function firstFunction($params){
			
			$this->result = $params["a"] + $params["b"];
			
		}
		
	}

	Class MyClass2 extends OObject{
	
		public function secondFunction(){
		
			$params = array("a"=>1,"b"=>2);
		
			$my_instance = $this->route('/lib/MyClass1/firstFunction/',$params);  // instantiate object and call firstFunction and return the object

		}
	
	}


From within an OObject class you can access any path defined as a class path in your settings file.  In this case the "lib" path and any of it's public methods.

## ORouter

ORouter's job is to handle an HTTP request by converting into a routable path and returning the object in an output format such JSON with appropriate status codes and HTTP headers.  For example if you put this in your browser address bar:


	http://www.myobrayapplication.com/lib/MyClass/firstFunction/?a=1&b=2


you will get the response (JSON is the default output format from ORouter):


	{
		"object":"MyClass",
		"result":3
	}


External HTTP requests that are handled by ORouter are restricted not only by public/private declarations, but also by the permissions array. Unless you explicitely define a functions permissions in the permissions array a request through ORouter will be blocked with 404 Not Found status code.  If an attempt is made to a URI where the permissions are not sufficient you will recieve a 403 Forbidden error.  This way unless the resource is explicity defined with permissions an object can be hidden from ORouter and completely inaccessable except from within an OObject in PHP code.

## ODBO - database access
