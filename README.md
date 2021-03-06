Description
===========

Trout is a Tiny ROUTer in PHP that routes HTTP requests to your PHP controller code.

Features
--------

* No dependencies, no enforced conventions. 

* It's not a framework! You are free to put your views, your controllers etc ... where you want. You don't need to subclass any 'base' controller class to provide controller objects.

* Very light-weight: Only 1 file to require, less than 200 lines of code

* Very few methods to learn

* RESTful (nested or not) resource routing

* Routes use RegExp

* Nice routes output for debugging

* Tested on PHP 5+


License
-------

MIT License (see below)

Copyright (C) 2012 Thierry Passeron



Basics
======

1) Require the file and create a new Trout:
		
	require 'lib/Trout.php';
	$trout = new Trout();

2) Add routing rules:

	// Either require a php file
	$trout->get('/', function () { require_once "inc/home.php"; });

	// Or directly output the content
	$trout->get('/about', function () {
		echo "Please contact us at info@foo.bar";
	});

3) Need more ?

	// How about a route for POSTing stuff ?
	$trout->post('/events', function () { require_once "inc/create_event.php"; });

	// Or PUT ?
	$trout->put('/avatar/([^/]+)', function ($avatar_id) { require_once "inc/update_avatar.php"; });

	// Or DELETE ?
	$trout->delete('/events/([^/]+)', function ($event_id) { require_once "inc/delete_event.php"; });

	// Or a set of routes that map to a RESTful resource
		require_once "inc/CommentsController.php";
	$trout->resource('/comments', "CommentsController");

4) Let it go...

You need to call the #swim() method to let the routing start.

	if (!$trout->swim()) { require_once "inc/routing_error.php; }


Advanced
========

Routing methods
---------------

		any(<Regexp-string>, <Callback-function> [, <Hint-string>]) 	# will be triggered before any (get()/post()/put()/delete()) rule
		get(<Regexp-string>, <Callback-function> [, <Hint-string>]) 	# will be triggered if the GET request matches
		put(<Regexp-string>, <Callback-function> [, <Hint-string>]) 	# will be triggered if the PUT request matches
		post(<Regexp-string>, <Callback-function> [, <Hint-string>]) 	# will be triggered if the POST request matches
		delete(<Regexp-string>, <Callback-function> [, <Hint-string>]) 	# will be triggered if the DELETE request matches

			<Regexp-string> is a pattern string that will be used to match the current request
				No need to put ^ or $ in the rule as they are assumed, so a rule like '/products' will be considered '@^/product$@i'
				Note that because we often use slashes (/) in rules, Trout uses '@' as regexp delimiter. 
				So if you need to use '@' in your rules, you need to escape it.
			<Callback-function> is a function that will be called when the rule is matched
			<Hint-string> is optional and is used to output a human readable explanation of the rule 
				when dump() is used to dump the rules

* The order of rules declaration (within the same class of routes i.e. within 'get', or 'post' etc...) is important because Trout will try to match them in their declaration order.

* The first rule to output something (on the output buffer) is considered the last rule.

* You may need to call #lastRule() if you are only sending headers.

Example:

			$trout->get('/?', function () { require_once "main.php"; }, "The site's main page");


RESTful Resource routing
------------------------

You may declare a RESTful resource if you provide a controller class.

Example:
			
			require "inc/controllers/products.php"; # declares class ProductsController
			$trout->resource('/products', "ProductsController");

The controller class should implement any or all of the public methods expected by trout to map HTTP requests to controller actions, that is:

				public function index() { ... }
				public function form() { ... }
				public function show($id) { ... }
				public function edit($id) { ... }
				public function update($id) { ... }
				public function create() { ... }
				public function delete($id) { ... }

Note that some actions expect a parameter '$id' (name it accordingly).

Trout will only create resource routing rules for actions that are implemented. You can for example implement a controller class with only #index() public instance method, in which case only one (corresponding) routing rule will be created by Trout.

If you want to change the request to controller actions mappings you may override Trout 
to provide a custom protected $_resource_actions


Examples
========

	require 'lib/Trout.php';
	$tr = new Trout();

	// Now declare some routes...
	$tr->any('.*', function() { / * ... * / }, 'Login required');
	$tr->get('/applications/?', function() { echo "Applications list\n"; }, "List Applications");
	$tr->get('/applications/new', function() { echo "New application form\n"; }, "New Application form");
	$tr->get('/applications/([^/]+)', function($id) { echo "Get Application '$id'\n"; }, "Show Application");
	$tr->put('/applications/([^/]+)', function($id) { echo "Update Application '$id'\n"; }, "Update Application");
	$tr->post('/applications/?', function() { echo "Create an Application\n"; }, "Create an Application");
	$tr->delete('/applications/([^/]+)', function($id) { echo "Delete Application '$id'\n"; }, "Delete Application");

	$tr->dump(); // Shows the routes

	// This will output something like:
		*ANY* .*                                   Login required
		   GET /applications/?                   List Applications
		   GET /applications/new              New Application form
		   GET /applications/([^/]+)              Show Application
		  POST /applications/?                Create an Application
		   PUT /applications/([^/]+)            Update Application
		DELETE /applications/([^/]+)            Delete Application

	// Declare a controller class...
	class StoresController {
		public function index() { echo "Index\n"; }
		public function show($id) { echo "Show '$id'\n"; }
		public function form() { echo "New form\n"; }
		public function update($id) { echo "Update '$id'\n"; }
		public function create() { echo "Create\n"; }
		public function delete($i) { echo "Delete '$i'\n"; }
	}

	// REM: only implemented methods will trigger the creation of resource rules

	// And then declare the resource...
	$tr->resource('/stores', "StoresController");

	// which would generate these routes:
	   GET /stores                                 Stores list
	   GET /stores/new                         new Stores form
	   GET /stores/([^/]+)                         Stores show
	  POST /stores                               Stores create
	   PUT /stores/([^/]+)                       Stores update
	DELETE /stores/([^/]+)                       Stores delete

	// Now let's test the routes

	$verbose = false;
	$tr->swim("GET", "/applications/", $verbose);
	$tr->swim("GET", "/applications/new", $verbose);
	$tr->swim("GET", "/applications/123", $verbose);
	$tr->swim("PUT", "/applications/123", $verbose);
	$tr->swim("POST", "/applications/", $verbose);
	$tr->swim("DELETE", "/applications/123", $verbose);

	// This will output:
		Applications list
		New application form
		Get Application '123'
		Update Application '123'
		Create an Application
		Delete Application '123'
	


Web server configuration
========================

Apache configuration
--------------------

Set the "One-index-file" scheme in .htaccess 

	Options +FollowSymlinks

	RewriteEngine On
	RewriteBase /

	RewriteCond %{REQUEST_FILENAME} !^(.+)\.(css|js|jpg|gif|png|ico|html)$
	# OR
	# RewriteCond %{REQUEST_FILENAME} !-f
	# RewriteCond %{REQUEST_FILENAME} !-d

	RewriteRule ^(.*) index.php [QSA,L]


Declare an apache vhost:

	<VirtualHost *:80>
	    ServerName trout.local
	    DocumentRoot "/path/to/Trout-site"
	    <Directory /path/to/Trout-site>
	        Order allow,deny
	        Allow from all
	    </Directory>
	</VirtualHost>


Set name resolution in /etc/hosts

	[...]
	fe80::1%lo0	trout.local # REM: on OSX, it helps a lot to set the ipv6 local address!
	127.0.0.1	trout.local
	[...]


Don't forget to restart your web server!

Now you can browse http://trout.local 


Customization
=============

Subclassing Trout
-----------------

You can subclass Trout to provide a custom isLastRule() method. 
This method is used after swimming each routes to determine if we stop swimming and flush the outputs.
The default behavior is to consider any rule that outputs something as the last rule.

	class MyTrout extends Trout {
		function isLastRule() {
			[...]
			return $true_or_false;
		}
	}


Layout
------

By default the flush function just outputs contents captured during execution of the rules.
The flush() is called when a rule is the last rule.
You can set a custom flush method without needing to subclass like this:

	$tr = new Trout();
	$tr->flush(function () use ($tr) {
		require_once "layout.php"; // layout.php uses $tr to access the $tr->out_buffer (an array of outputs)
	});


Example layout.php:
-------------------

	<!DOCTYPE html>
	<html lang="en">
		<head>
	    	<meta charset="utf-8">
			<title>Trout site;)</title>
		</head>
	  
		<body>
		  	
		  	<div class="container">
		  		<?= implode("", $tr->out_buffer); ?>
		  	</div>
		</body>
	</html>


Headers
-------

Sometimes you just need to set a response header for example when redirecting to an uri. In this case you will not output anything.
But the Trout needs to be aware that the current route should be treated as the last rule. 
You use the lastRule() method to do so. Example:

	$tr = new Trout();
	function redirect_to ($uri) {
		global $tr;
		$tr->lastRule();
		header('Location: ' + $uri);
	}

Public ivars
------------
	
	$out_buffer: 	an array of rules outputs
	$method: 		the method of the current request
	$uri: 			the uri of the current request
	$error: 		contains the error message and code in case swim() did not succeed 
	
	$error = array(
		'code' => <error-code-string>, 
		'message' => <error-message-string>
	)

example usage of ivars:

	$tr->any('.*', function() use ($tr) {
		/ * Login is REQUIRED * /
		if (!isset($_SESSION['user']) && !preg_match('@^/login@i', $tr->uri)) {
			redirect_to('/login/new');
			return;
		} 
	});

Return status
-------------

The "swim loop" is triggered by the swim() method which should be written after routes declaration.
swim() returns false if an error occured or no route was found as last rule else it returns true.
If false, you may look at the $error.
I usually do things like this:

	if (!$tr->swim()) { require_once "error.php"; } // error.php displays the content of $tr->error etc...


Debugging
=========

When something is not working as expected you may:

* set swim() in verbose mode
	
	$tr->swim(null, null, true);

* test a rule with a custom-made request
	
	$tr->swim('PUT', '/product/1234', $verbose);

* output the rules to see if they are okay

	$tr->dump();

* check the swim error if any
	
	var_dump($tr->error);


TODO:
=====

* have a test-suite to test the class. (help appreciated)


Contact:
========

If you find Trout useful drop me a line ;).
If you'd like some new features implemented, feel free to ask me and feel free to fork the project on github.
If you don't like Trout, it's not a problem, try Salmon.


License terms:
==============

The MIT License

Copyright (C) 2012 Thierry Passeron

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated 
documentation files (the "Software"), to deal in the Software without restriction, including without limitation 
the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, 
and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED 
TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL 
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF 
CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER 
DEALINGS IN THE SOFTWARE.
