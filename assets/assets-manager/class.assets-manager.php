<?php
/**
 *	Class AssetsManager
 *	@author Nicolas Wozniak
 *	@version 1.0.2.1 - 13/07/2009 -> 30/12/2008
 *	@web http://github.com/wozi/php-assets-manager
 *	
 *	Manage your CSS/JS/Other files inclusion in Web pages, with compression capability, package creation (multiple files into one) and dynamic caching.
 *
 *	@usage:
 *	The call is made using <link rel="/ap/screen" media="screen" />
 *	Where /ap/ is a special directory used which will be taken care of by the AssetPackager.
 *	screen is the name of the file we need. This one is defined in the config file as
 *		 stylesheets:
 *			screen:
 *			files: ../css/screen-btn.css
 *			# Different versions of the same file
 *			versions:
 *				1.1:
 *					files: ../css/screen-btn.css
 *
 *	To use mutliple files, use an array:
 *			...
 *			screen:
 *			files: [../css/screen-btn.css, ../css/screen-login.css]
 *	
 *	At the first call, one single css will be tidied and merge into one that will be cached in the CACHE $dir.
 *	The script will then send the compacted file. The file will not move until one of the file of the package is modified. It
 *	will be repackaged then.
 *
 *	JavaScript files can be used the same way. They just have to be defined in the javascript array in the config file.
 *		 javascripts:
 *			highslide:
 *			files: ../js/highslide.js
 *			versions:
 *				1.1:
 *					files: ../js/highslide11.js
 *				1.2:
 *					files: ../js/highslide12.js
 *
 *	Versionning:
 *	You can also gives a version number to your CSS while calling it using the ?v parameter.
 *	If you have more than one version for one package, the version asked will be delivered.
 *	config.yml:
 *		 stylesheets:
 *			screen:
 *			files: ../css/screen-btn.css
 *			# Different versions of the same file
 *			versions:
 *				1.1:
 *					files: ../css/screen-btn.css
 *		  
 *		 javascripts:
 *			highslide:
 *			files: ../js/highslide.js
 *			versions:
 *				1.1:
 *					files: ../js/highslide11.js
 *				1.2:
 *					files: ../js/highslide12.js
 *	Call:
 *		<link rel="/ap/screen?1.2" media="screen" />
 *	Will deliver the up to date version.
 *
 *	CSS / JavaScript compression & Other types of files:
 *	By default, the CSS & JS files are compressed. You can disable it by specifying in the config file the new value:
 *	config.yml:
 *		compressJS: false
 *		compressCSS: false
 *		
 *	You can also use other types of files that re not CSS or JS. In that case no compression will be applied. 
 *	Just create a new section at the same level as stylesheets and javascripts in the asset_packager.yml config file.
 *	 myset:
 *		nico:
 *		files: ../js/test.txt
 *
 *	superCSS:
 *	We now support in version 1.0.2 variables in stylesheets. Variables are declared this way, in any part of your CSS file:
 *	@myVar = 12px; (or @myVar : 12px;)
 *	(just be careful to always use a ; at the end.
 *
 *	Then you can just use it anywhere by calling it:
 *	font-size: @myVar;
 *
 *	This is a primary support, more functions with variables will be added in the next version.
 */
include ('lib/spyc/class.spyc.php');
include ('lib/csstidy/class.csstidy.php');
include ('lib/jsmin/jsmin-1.1.1.php');
include ('layer/layer.io.php');
include ('layer/layer.config.php');
include ('class.superCSS.php');

// the path for caching YAML files. Different from the cache defined in config, cause this one is actually for caching config files.
define ("CONFIG_CACHE_DIR", "cache/");
 
class AssetsManager {

	/**
	 *	_CONFIG_FILE
	 *	The file containing some config information
	 */
	var $_CONFIG_FILE = NULL;
	
	// Our base configuration object
	var $conf = NULL;
	
	// the asset_packager.yml config file
	var $assets_conf = NULL;
	
	/**
	 *	package_name
	 *	The name of the package to display
	 */
	var $package_name = NULL;
	
	/**
	 *	package_version
	 *	The version of the package to display
	 */
	var $package_version = NULL;
	
	/**
	 *	CSS/JS Compression
	 *	You can set them here or you can do it in the config file
	 */
	var $compressStylesheets = true;
	var $compressJavascripts = true;
	
	/**
	 *	Constructor ()
	 *	Create an AssetPackager instance with the right config
	 */
	public function __construct ($config_file) {
		$this->_CONFIG_FILE = $config_file;
		$this->loadConfig ();
	}
	
	/**
	 *	loadConfig ()
	 *	Load the configuration
	 */
	public function loadConfig () {
		$this->conf = new config ($this->_CONFIG_FILE, CONFIG_CACHE_DIR);
		
		// Set the compression levels
		$compressCSS = $this->conf->get ('compressCSS');
		if ($compressCSS)
			$this->compressStylesheets = true;
		else if ($compressCSS === FALSE) 
			$this->compressStylesheets = false;
		
		$compressJS = $this->conf->get ('compressJS');
		if ($compressJS == TRUE)
			$this->compressJavascripts = true;
		else if ($compressJS === FALSE) 
			$this->compressJavascripts = false;
	}

	/**
	 *	get ()
	 *	Get a specific package
	 *	@param {String} package_name : the name of the package to get
	 */
	public function get ($package_name, $package_version=NULL) {
		$this->package_name = $package_name;
		$this->package_version = $package_version;
		
		/**
 		 *	Now display the correct package
 		 */
 		$this->bundle ();
	}
	
	/**
	 *	getAuto ()
	 *	Get a file by the getting the right stuff in the request URI by using a .htaccess
	 */
	public function getAuto () {
		$this->getPackageFromURI ($query);
		
		//	We've got the name & the version, now we need
		$this->bundle ();
	}
	
	/**
	 *	bundle ()
	 *	Display the package
	 *	The package is automatically cached after being compacted. The cache is the one gonna be displayed
	 */	
	private function bundle () {
		// First, we'll get the files we need from the asset_packager.yml config file, in the path defined in the conf.yml file
		$this->assets_conf = new config ($this->conf->get ("assets"), CONFIG_CACHE_DIR);
		
		// Go find the package!
		foreach ($this->assets_conf->get ("assets") as $type=>$packs) {
			$packageFiles = $this->getPackageFiles ($packs);
			
			// Now if we have the package let's pack it and display it
			if ($packageFiles) {					
				$package = $this->createPackage ($packageFiles, $type);
				$this->out ($package, $type);
			}
		}
	}
	
	/**
	 *	getPackageFiles ()
	 *	Get the correct set of files for the required package (CSS or JS)
	 *	@param {Array} pack : the selected pack (stylesheets or javascripts)
	 *	@return {String/Array} : the file(s), NULL if not found
	 */
	private function getPackageFiles ($pack) {
		if ($pack) {
			foreach ($pack as $package_name=>$package_options) {
				if ($package_name == $this->package_name) {
					
					/**
					 *	Look if the version is correct, otherwise keep looking.
					 *	If we can't find the version, we'll use the default files (outside versions)
					 *	Verify that versions are defined within the config file
					 */
					if ($this->package_version && isset ($package_options ['versions'])) {
						$versions = $package_options ['versions'];
						
						foreach ($versions as $version=>$args) {	//set version, files
							if ($version == $this->package_version) {
								$files = $args ['files'];
								return $files;
							}
						}
						/**
						 *	If we get here that means we haven't found the requested version. Let's get the default one.
						 *	@note As the system thinks we're using the version event though it doesn't exist a cache will be created
						 *	for this non existing version
						 */
						return $package_options ['files'];
						
					// If no version is specified we use the default files (outside versions)
					} else {
						$files = $package_options ['files'];			//No specific version
						return $files;
					}
				}
			}
		}	
		return NULL;
	}
	
	/**
	 *	createPackage ()
	 *	Create a package, cache it, and return it
	 *	
	 *	@note The filename of the package will be the name of the file if only one or the names of the files concatened with - if more than one
	 *	@param {String/Array} the list of file(s)
	 *	@param {String} type : the type of package (JS or CSS) in order to apply the correct compression
	 *	@return {String} the content of the cached package
	 *	@note Instead of naming the cached files filename-filename.. we could give the name of the package an dits version.type (screen-1.1.css)
	 */
	private function createPackage ($files, $type) {
		/**
		 *	Check if the files have been modified since last cache
		 *	If a single file has changed we'll rewrite the whole thing out. Btw, each file will be tidied.
		 *	We cache the complete package so it's faster to load (only one access) but each file is available individually.
		 */
		$hasChanged = false;
		
		// define the cache path for our css & js files
		io::setCachePath ($this->conf->get ('cache_dir'));
		
		// test that the cahce is writable. If not, we'll display all files within the package as raw, with no operation on it to not surcharge your server
		if (!io::cache_is_writable ())
			return $this->createPackageRaw ($files, $type);
			
			
		/**
		 *	Create the complete package filename : 
		 *		- if one file -> filename.cache
		 *		- if more than one file -> filename1-filename2.cache
		 */
		if (is_array ($files)) {
			$package_filename = '';
			foreach ($files as $file) {
				$package_filename .= basename ($file)."-";
			}
		} else {
			$package_filename = basename ($files);
		}
		

		// determine if the files have changed since last cache
		// we write all files in cache so we have a reference of when each individual file has been cached for the last time (necessary for arrays)
		if (is_array ($files)) {
			foreach ($files as $file) {
				$filename = $file;		//.$this->package_version;	-> cannot be used, different versions must have different original filenames

				// recache the file
				if (!io::cacheUpToDate ($filename)) {
					io::cache ($filename);
					$hasChanged = true;
				}
			}	
		} else {
			$filename = $files; //.$this->package_version;
			
			// recache the file
			if (!io::cacheUpToDate ($filename)) {
				io::cache ($filename);
				$hasChanged = true;
			}
		}
		
		// DEV
		$hasChanged = true;
		
		
		// Cache the files if need to be and read their content
		$package = ""; 
 		if ($hasChanged) {
 			// Cache each file and read its content for creating the big package.
 			if (is_array ($files)) {
 				
				// We'll read each file, tidy it, and add it to the pile
				foreach ($files as $file) {
					
					// Package the CSS or JS or raw
					if ($type == 'stylesheets')
						$package .= $this->cleanCSSFile ($file);
					else if ($type == 'javascripts')
						$package .= $this->cleanJSFile ($file);
					else
						$package .= $this->getRaw ($file, $type);		
				}
			} else {
				// Package the CSS or JS or raw
				if ($type == 'stylesheets')
					$package = $this->cleanCSSFile ($files);
				else if ($type == 'javascripts')
					$package = $this->cleanJSFile ($files);
				else
					$package = $this->getRaw ($files, $type);
			} 
			
			// Now cache the big package to a new file in cache (create file on the fly) so next time we won't have to cache it, only read it
			io::cache ($package_filename, $package);
				
		// if not changed, we'll read the cached file
 		} else {
			$package = io::readCache ($package_filename);
		}
		
		// Now get the cached content and return it
		return $package;
	}

	/**
	 *	createPackageRaw ()
	 *	Create the package (the whole content of mixed files) with no cache.
	 *	Used only if the cache dir is not writable so we don't do any operation on the files, in order to save resources.
	 *	If this function is sued, it's because something is not configured properly.
	 *	@param {String/Array} the list of file(s)
	 *	@param {String} type : the type of package (JS or CSS) in order to apply the correct compression
	 *	@return {String} the content of the cached package
	 */
	private function createPackageRaw ($files, $type) {
		$package = ""; 
		
 		// Get each file and read its content for creating the big package.
 		if (is_array ($files)) {
			// We'll read each file and add its content to the pile
			foreach ($files as $file) {
								
				// get the file's content in raw (cache not used, leverage the resources)
				$package .= $this->getRaw ($file, $type);		
			}
		} else {
			// get the file's content in raw (cache not used, leverage the resources)
			$package .= $this->getRaw ($files, $type);
		} 
			
		// Now get the cached content and return it
		return $package;
	}
	
	/**
	 *	cleanCSSFile ()
	 *	Read a file and clean it (tidy)
	 *	@param {String} path : the path to the file to parse
	 *	@return {String} the converted content
	 */
	private function cleanCSSFile ($path) {
		$content = file_get_contents ($path);
		
		// parse superCSS content
		$content = superCSS::parse ($content);
			
		// Use CSSTidy to clean the file
		if ($this->compressStylesheets) {
			$css = new csstidy ();
			$css->set_cfg ('remove_last_;', TRUE);
			$css->parse ($content);
			return $css->print->plain ()."\n\n";
		} else
			return $content."\n\n";			// add 2 new lines to separate the contents
	}	
	
	/**
	 *	cleanJSFile ()
	 *	Read a file and clean it  (jsmin)
	 *	@param {String} path : the path to the file to parse
	 *	@return {String} the converted content
	 */
	private function cleanJSFile ($path) {
		$content = @file_get_contents ($path);
		if (!$content) return "";
		
		// Use JSMin to compress the file
		if ($this->compressJavascripts) {
			$min = JSMin::minify ($content);
			return $min."\n\n";
		} else
			return $content."\n\n";
	}	
	
	/**
	 *	getRaw ()
	 *	Read a file with no action on it (for unknown types of packages)
	 *	@param {String} path : the path to the file to parse
	 *	@return {String} the converted content
	 */
	private function getRaw ($path, $type=NULL) {
		$content = @file_get_contents ($path);
		
		// for CSS files, will parse for superCSS content
		if ($type == 'stylesheets')
			$content = superCSS::parse ($content);
		
		if (!$content) return "";
		return $content."\n\n";
	}	
	

	/**
	 *	out ()
	 *	Display data out
	 *	@param {String} data : the raw data to put out
	 */
	private function out ($data, $type) {
		// send some headers so the browser has the right mime types
		if ($type == 'stylesheets') {
			header("Content-type: text/css");
		} else if ($type == 'javascripts') {
			header("Content-type: text/javascript");
		}
		
		print $data;
	}
	
	/**
	 *	getPackageFromURI ()
	 *	Explode the URI and only return what we need: the last element, ie the name of the file + its version if specified
	 */
	private function getPackageFromURI () {
		$package_extract = preg_match ("/[a-z?0-9.a-z]+$/i", $_SERVER ['REQUEST_URI'], $occ);
		
		// Now we need to look if a special version is set
		if (preg_match ("/[?]/i", $occ [0])) {
			$explode = explode ("?", $occ [0]);
			$this->package_name = $explode [0];
			$this->package_version = $explode [1];
		} else
			$this->package_name = $occ [0];	
	}
}
?>
