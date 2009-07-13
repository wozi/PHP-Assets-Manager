<?php
/**
 *	Class config
 *	Takes care of the configuration of Kobaia. It is accessible by the controllers, processes and libs.
 *	Based on a YAML config file.
 *	Takes care of caching the file (ioLayer is needed to enable this feature).
 *
 *	@version 0.9.7 //  07/12/2009
 */

class config {

	/**
	 *	yaml
	*	Our YAML array, containing the configuration information
	 */
	var $yaml = NULL;
	
	// the directory where we'll cache our config files
	var $cache_dir = NULL;
	
	/**
	 *	Constructor
	 *	Load a new config from the specified file
	 *	@param {String} configPath : the path to the YAML config file
	 *	@param {Stirng} $cache_dir : the cach edirectory. If NULL, no caching will be done.
	 */
	function __construct ($configPath=NULL, $cache_dir=NULL) {
		if ($configPath) $this->load ($configPath);
		$this->cache_dir = $cache_dir;
	}
	
	/**
	 *	load ()
	 *	Load a config file
	 *	@param {String} configPath : the path to the YAML config file
	 */
	public function load ($configPath) {
		if (!class_exists ("Spyc")) return NULL;
			
		// Cache the file if the ioLayer is loaded
		if (class_exists ('io') && $this->cache_dir) {
			$this->yaml = $this->cacheYaml ($configPath);
		} else {
			$this->yaml = Spyc::YAMLLoad ($configPath);
		}
	}	
	
	/** 
	 *	cacheYaml ()
	 *	Write a yaml file into a file as an array for cache.
	 *	We serialize the yaml array, and we just write it to a file.
	 *	@param {String} config_file : the path of the config file
	 *	@return {Array} the yaml array
	 */
	public function cacheYaml ($config_file) {

		// Create the cache name
		$cache_name = preg_replace ("/\//", "-", $config_file);
		$cache_name = preg_replace ("/[..\/]/", "", $config_file);
		$cached_file = $this->cache_dir.basename ($cache_name).".cache";
		
		// if the file is out dated, we'll recache it
		if (io::mod_time ($cached_file) < io::mod_time ($config_file)) {
			// Serialize the data for write
			$yaml = Spyc::YAMLLoad ($config_file);
			$content = serialize ($yaml);
			
			// Now let's write it with the cache option
			io::write ($cached_file, $content);
		}
		
		// Now read it and deserialize it.
		return unserialize (io::read ($cached_file));
	}
	
	/**
	 *	get ()
	 *	Get a value from a key
	 *	@param {String} key : the key to the value
	 *	@param {String} in : optionnal, take the value from another key element (inside/embed element)
	 *	@return {String} the value or NULL if not found
	 */
	function get ($key, $in=NULL) {
		foreach ($this->yaml as $ykey=>$yvalue) {
			// We may want to loonk for a specific value into that
			if ($in) {
				if ($ykey === $in) {
					// We've found the right key, now we'll look for a value into that
					foreach ($yvalue as $yykey=>$yyvalue) {
						if ($yykey == $key) {
							return $yyvalue;		//Got it!
						}
					}
				}
			}
			else {
				if ($ykey == $key) return $yvalue;
			}
		}
		return NULL;
	}
	
	/**
	 *	getParent ()
	 *	Get the parent value of a value
	 *	@param {String} key : the key to the value
	 *	@return {String} the value or NULL if not found
	 */
	public function getParent ($key) {
		foreach ($this->yaml as $ykey=>$value) {
			if (is_array ($value)) {
				foreach ($value as $invalue) {
					if ($invalue == $key) {
						return $ykey;		//Gotcha -> return the parent key
					}
				}
			} else {
				if ($value == $key) return $value;		//Nothing higher -> return this value
			}
		}
		return NULL;
	}
}
?>