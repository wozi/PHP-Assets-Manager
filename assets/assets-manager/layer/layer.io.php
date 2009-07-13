<?php
/**
 *	Class io
 *	Abstraction layer for IO accesses (local or distant)
 *	Provides read/write accesses, + cache and versionning capabilities
 *	@layer
 *	@author Nicolas Wozniak
 *	Takes care of all IO actions
 */

/**
 *	CACHE_DIR
 *	Must be set to your CACHE directory. Make sure it is writeable.
 *	@note Must be set using the io::setCachePath ($path) function!
 */
//define ("CACHE_DIR", "cache/");

/**
 *	DISTANT_CACHE_EXCEED
 *	Set how many minutes we keep a cache for a distant access
 */
define ("DISTANT_CACHE_EXCEED", 30);

abstract class io {
	
	/**
	 *	distant_timeout
	 *	The timeout for when we create a distant connection. In seconds.
	 */
	var $distant_timeout = 10;

	
	/**
	 *	read ()
	 *	Read a file
	 */
	public function read ($path) {
		if (!file_exists ($path)) return NULL;
		
		/**
		 *	Check if $path is an URL, in which case we will use readDistant instead.
		 */
		if (preg_match ('@^(?:http://)@i', $path))
			return self::readDistant ($path);
		
		$fr = fopen ($path, 'r');
		$data = fread ($fr, filesize ($path));
		fclose ($fr);
		return $data;
	}
		
	/**
	 *	getDistantContent ()
	 *	Get the content of a distant file. Proxy is implemented.
	 *	@note Never use directly this function within a controller, lib, etc but use getContent () instead.
	 *	@param {String} path : the path to the file.
	 */
	private function readDistant ($path, $proxy_address='', $proxy_port='', $proxy_user='', $proxy_pass='') {
		preg_match ('@^(?:http://)?([^/]+)@i', $path, $matches);
		$host = $matches [1];
		
		//Check if we use a proxy within the config file
		if ($proxy_address != '') {
			echo 'caching file with proxy, host is '.$host.' <br>';
			
			$fp = fsockopen ($proxy_address, $proxy_port, $errno, $errstr, $this->distant_timeout);
			if (!$fp) return false;
			
			$get = $path;
					
			$out = "GET ".$get." HTTP/1.0\r\n";
			$out .= "Host: ".$proxy_address."\r\n";
			if (!empty ($proxy_user) && !empty ($proxy_pass))		//Gere l'autentification
				$out .= "Proxy-Authorization: Basic ". base64_encode ($proxy_user.":".$proxy_pass)."\r\n"; 
					
			if (function_exists ('gzinflate')) 
				$out .= "Accept-Encoding: gzip,deflate\r\n";
			$out .= "Connection: Close\r\n\r\n";
				
		/**
 		 *	No proxy
 		 */
		} else {	 
			/**
			 *	get the file's name
			 */
       	$uri = strtr (strval ($path), array ("http://" => "", "https://" => "ssl://", "ssl://" => "ssl://", "\\" => "/", "//" => "/"));
        	if (($protocol = stripos ($uri, "://")) !== FALSE) {
         	if (($domain_pos = stripos ($uri, "/", ($protocol + 3))) !== FALSE)
              	$file = substr ($uri, $domain_pos);
         	else
              	$file = "/";
         } else {
         	if (($domain_pos = stripos ($uri, "/")) !== FALSE)
              	$file = substr ($uri, $domain_pos);
          	else 
              	$file = "/";
       	}
			$fp = fsockopen ($host, 80, $errno, $errstr, $this->distant_timeout);
			if (!$fp) return false;
			
			$out = "GET ".$file." HTTP/1.0\r\n";
			$out .= "Host: ".$host."\r\n";

			//if (function_exists ('gzinflate')) 
			//	$out .= "Accept-Encoding: gzip,deflate\r\n";
			$out .= "Connection: Close\r\n\r\n";
		}
		fwrite ($fp, $out);	
		$response = "";
		$info = stream_get_meta_data ($fp);
		
      while (!feof ($fp)) {
      	$response .= fgets ($fp, 128);
      	$info = stream_get_meta_data ($fp);
      }
      fclose ($fp);	
			
		/**
		 *	data
		 *	Will contain the data we're interested in, without the HTTP headers
		 */
		$data = '';
		if (!$info ['timed_out']) {			
         if (stripos ($response, "\r\n\r\n") !== FALSE) {
         	$hc = explode ("\r\n\r\n", $response);
         	/**
         	 *	Do we need to uncompress the data if it has been zipped?
         	 *	Maybe there is a header that says this is compressed:
         	 *	if (stripos (hc [0], 'gzip')) {
         	 *		if (substr ($data, 0, 3) == "\x1f\x8b\x08")		//Check seen on http://fr3.php.net/gzinflate
         	 *			$data = gzinflate ($data);
         	 *	}
         	 */
            $data = $hc [sizeof ($hc)-1];
            
			} else if (stripos ($response, "\r\n") !== FALSE) {
      		$hc = explode ("\r\n",  $response);
         	$data = $hc [sizeof ($hc)-1];;
         	
			} else
				$data = $response;
		}
		return $data;
	}
	
	/**
	 *	write ()
	 *	Write a file
	 */
	public function write ($filename, $content) {
		$fw = fopen ($filename, "w+");
		fwrite ($fw, $content, strlen ($content));
		fclose ($fw);
		
		return true;
	}
	
	/**
	 * is_writable ()
	 * Check if a dir is writable
	 * @param $path
	 * @return {Boolean}
	 */
	public function is_writable ($path) {
		return is_writable ($path);
	}
	
	/**
	 * cache_is_writable ()
	 * Check that the cache dir is writable
	 * @return {Boolean}
	 */
	public function cache_is_writable () {
		return is_writable (CACHE_DIR);
	}
	
	/**
	 *	mod_time ()
	 *	Get the last mod time of a file
	 *	@param {String} path
	 *	@return {String} the date, NULL if the file is not found
	 */
	public function mod_time ($path) {
		if (file_exists ($path))
			return filemtime ($path);
		else
			return NULL;
	}

	/**
 	 *	copy ()
 	 *	Copy a file to another location
 	 *	@param {String} source : the source path
 	 *	@param {String} dest : the dest path. If this is a dir, we'll use the same filename.
 	 *	@return {Boolean} if success or failed
 	 */
	public function copy ($source, $dest) {
		if (!file_exists ($source)) return false;
		
		// Get the file's content
		if (($file_content = self::read ($source)) === NULL) {
			echo "Copy: Failed reading source file content<br>";
			return false;
		}
		
		/**
 		 *	Now get the type of copy. If we have a filename for dest file, we'll use it. If not, we'll use the same name
 		 */
 		if (is_dir ($dest)) {
 			$dest .= basename ($source);
 		}
 		
 		/**
 		 *	Now write the file to this folder
 		 */
 		if (!self::write ($dest, $file_content)) return false;
 		
 		return true;
	}
	
	/**
	 *	CACHE *****************************************************
	 */
	
	public function setCachePath ($path) {
		define ("CACHE_DIR", $path);
	}
	
	/**
	 *	cache ()
	 *	Write a file into the cache.
	 *	It can be use to cache a file by doing a copy, or to cache some content so a new file will be created
	 *	@param {String} path : the path of the file to be cached
	 *	@param {String} file_content [] : if set, this content will be written into a file named with the given path
	 *	@return {Boolean} true if success, false if failed caching
	 *	
	 *	@ToDo : on devrait checker si la version en cache (si il y a) n'est pas dï¿½jï¿½ la derniï¿½re version
	 */
	public function cache ($path, $file_content=NULL) {
		//	Create the cache filename
		$cached_path = self::createCachedPath ($path);	

		// If file_content is not set, we'll make an exact copy of the given file in cache
		if (!$file_content) {
			if (!file_exists ($path)) return false;
			
			// Copy the file
			return self::copy ($path, $cached_path);
		
		//	If this is a content copy, we'll create a new file
		} else {
			return self::write ($cached_path, $file_content);
		}
	}	
	
	/**
	 *	createCachedPath ()
	 *	Create a valid cache path
	 */
	public function createCachedPath ($path) {
		return CACHE_DIR.basename ($path).".cache";
	}
	
	/**
	 *	cacheUpToDate ()
	 *	Check if a file is up to date in the cache.
	 *	This will check the mod_time of the original file and the mod_time of the file we have
	 *	in our db
	 */	
	public function cacheUpToDate ($path) {		
		if (!file_exists (self::createCachedPath ($path))) {
			return false;
		}
		if (self::mod_time ($path) < self::getCacheTime ($path)) return true;
		else return false;
	}
	
	/**
	 *	getCachePath ()
	 *	get the path in cache of a file that have been cached, from its original path
	 */
	public function getCachePath ($path) {
		return self::createCachedPath ($path);
	}
	
	/**
	 *	readCache ()
	 *	Get the content of a file that has been cached, from its original filename
	 */
	public function readCache ($path) {
		return self::read (self::createCachedPath ($path)); 
	}
	
	/**
	 *	getCacheTime ()
	 *	Get the time when a file has been cached, from its original path
	 */
	public function getCacheTime ($path) {
		return self::mod_time (self::createCachedPath ($path));
	}
	
	/**
	 *	getFileExtension ()
	 *	Get the extention of a file
	 */
	public function getFileExtension ($filename) {
		$extension = array ();
		preg_match ('/^.[.]+$/', $filename, $extension);
		return $extension [0];
	}
}
?>