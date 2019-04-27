<?php

/**
 * Implements the XBRL_Global class.
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright (C) 2018 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * This a singleton class used to provide a common context for all XBRL class instances.
 * A taxonomy schema is often comprised of many sub-taxonomies.  When a taxonomy is being
 * loaded it's usually necessary to keep track of all the related schemsa, files and link
 * bases.  This class acts as common repository of these artifacts of a taxonomy.
 * @author Bill Seddon
 */
class XBRL_Global
{

	/**
	 * A reference to this singleton instance
	 * @var Singleton
	 */
	private static $instance;

	// Taxonomy variables

	/**
	 * An array containing the set of taxonmies
	 * @var array[XBRL]
	 */
	public $importedSchemas = array();
	/**
	 * The array is a map relating the namespace of a taxonomy to the schema file in which it is implemented
	 * @var array[string]
	 */
	public $schemaFileToNamespace = array();
	/**
	 * The current locale
	 * @var string
	 */
	public $locale = 'en_GB';

	// Taxonomy caching variables

	/**
	 * A flag indicating whether a cache of taxonomy files will be used.
	 * If true taxonomies will be accessed from the cache where possible and new taxonomies will be stored there
	 * @var boolean
	 */
	public $useCache = false;
	/**
	 * A path to the location for the cache
	 * @var string
	 */
	public $cacheLocation = null;

	/**
	 * The set of accumulated types used by XBRL taxonomies
	 * @var XBRL_Types
	 */
	public $types = null;

	/**
	 * Formula names MUST be unique across the DTS so this array provides a reference to each name
	 * @var array $formulaNames
	 */
	public $formulaNames = array();

	/**
	 * As linkbases are processed they are recorded here so attempts
	 * to reprocess them in other schemas can be avoided
	 * @var array $processedLinkbases
	 */
	public $processedLinkbases = array();

	/**
	 * A list of the XML and XBRL core taxonomies that are taken as read so do not need to be processed
	 * @var array
	 */
	public static $taxonomiesToIgnore = array(
		'http://www.xbrl.org/2003/xbrl-linkbase-2003-12-31.xsd' => '',	// These are the four core XBRL schemas
		'http://www.xbrl.org/2003/xl-2003-12-31.xsd' => '',				// |
		'http://www.xbrl.org/2003/xlink-2003-12-31.xsd' => '',			// |
		'http://www.xbrl.org/2003/xbrl-instance-2003-12-31.xsd' => '',	// |
		'http://www.xbrl.org/2005/xbrldt-2005.xsd' => '',				// Dimensional schema taxonomy
		'http://www.xbrl.org/2006/ref-2006-02-27.xsd' => '',			// | These define elements for reference information
		'http://www.xbrl.org/2004/ref-2004-08-10.xsd' => '',			// |
	);

	/**
	 * Get an instance of the global singleton
	 * @return XBRL_Global
	 */
	public static function getInstance()
	{
		if ( is_null( self::$instance ) )
		{
			self::$instance = new self();
			self::$instance->types = XBRL_Types::getInstance();
		}
		return self::$instance;
	}

	/**
	 * Reset the global context. This is necessary when compiling multiple taxonomies.
	 * Without it the globals accumulate and get stored in each successive compiled file.
	 */
	public static function reset()
	{
		if ( version_compare( PHP_VERSION, "5.4", ">=" ) && false )
		{
			libxml_set_external_entity_loader( null );
		}
		self::$instance = null;
	}

	// Linkbases

	/**
	 * A list of role refs from the schema
	 * @var array $presentationRoleRefs
	 */
	public $presentationRoleRefs = array();

	/**
	 * A list of essence alias arcs
	 * @var array
	 */
	public $essenceAlias = array();

	/**
	 * A list of role refs from the schema
	 * @var array $generalSpecialRoleRefs
	 */
	public $generalSpecialRoleRefs	= array();

	/**
	 * A list of role refs from the schema
	 * @var array $calculationRoleRefs
	 */
	public $calculationRoleRefs		= array();

	/**
	 * A list of role refs from the schema
	 * @var array $nonDimensionalRoleRefs
	 */
	public $nonDimensionalRoleRefs	= array();

	/**
	 * A list of require element arcs
	 * @var array
	 */
	public $requireElements = array();

	/**
	 * A store for the taxonomy labels
	 * @var array $labels
	 */
	public $labels 				= array();
	/**
	 * A list of label link role refs from the schema.  In all likelihood there will be one: XBRL_Constants::$defaultLinkRole
	 * @var array $labelLinkRoleRefs
	 */
	public $labelLinkRoleRefs	= array();
	/**
	 * A list of label role refs from the schema. There will be at least one: XBRL_Constants::$labelRoleLabel
	 * @var array $labelRoleRefs
	 */
	public $labelRoleRefs		= array();
	/**
	 * The source of the current label set
	 * @var string $labelSource
	 */
	public $labelSource			= 'taxonomy';

	/**
	 * Used to record any missing labels to aid debugging taxonomy
	 * @var array $missingLabels
	 */
	public $missingLabels		= array();

	/**
	 * Dimension defaults are global so for validation this variable is in the global context but is not persisted
	 * @var array $dimensionDefaults
	 */
	public $dimensionDefaults	= array();

	/**
	 * Records the xsd associated with the first use of the default link role
	 * @var string $defaultLinkHref
	 */
	public $defaultLinkHref = null;

	/**
	 * A cache of all the extended link roles in the DTS so far
	 *
	 * @var null|array $definitionLinkRolesCache
	 */
	public $definitionLinkRolesCache = null;

	/**
	 * A cache of primary items for performance
	 * @var array $primaryItemsCache
	 */
	private $primaryItemsCache = null;

	/**
	 * True or false if the taxonomy set has been validated or null if not tested.
	 * @var null|bool $validTaxonomySet
	 */
	public $validTaxonomySet = null;

	/**
	 * True if this instance represents an instance taxonomy
	 * @var bool $isExtensionTaxonomy
	 */
	private $isExtensionTaxonomy				= false;

	/**
	 * Return true if the current instance is an extension taxonomy
	 * @return bool
	 */
	public function isExtensionTaxonomy()
	{
		return $this->isExtensionTaxonomy;
	}

	/**
	 * Allows a caller to declare the instance is an extension taxonomy
	 * @return void
	 */
	public function setIsExtensionTaxonomy()
	{
		$this->isExtensionTaxonomy = true;
	}


	/**
	 * Default constructor
	 */
	public function __construct()
	{
		$this->labels[ XBRL_Constants::$defaultLinkRole ] = array();
	}

	/**
	 * Find a file in the cache if it exists.  If it exists return the path or return false.
	 * @param string $url
	 * @return false|string
	 */
	public function findCachedFile( $url )
	{
		if ( ! $this->useCache ) return false;

		$path = str_replace( '//', '/', XBRL_Global::createPathFromUrl( $url, $this->cacheLocation ) );

		return file_exists( $path )
			? $path
			: false;
	}

	/**
	 * Get the taxonomy schema document instance for a given href.
	 * @param $href string|array The href is likely to come from a locator and can be the string or an array produced by parse_url.
	 * @returns XBRL An instance of XBRL
	 */
	public function getTaxonomyForXSD( $href )
	{
		if ( is_string( $href ) && \XBRL::endsWith( $href, '.xsd' ) && \XBRL::startsWith( $href, 'http' ) )
		{
			if ( ! isset( $this->schemaFileToNamespace[ $href ] ) )
				return null;

			$xsd = $href;
		}
		else
		{
			$parts = is_array( $href ) ? $href : parse_url( $href );
			if ( ! isset( $parts['path'] ) ) return false;
			$xsd = pathinfo( $parts['path'], PATHINFO_BASENAME );
			if ( ! isset( $this->schemaFileToNamespace[ $xsd ] ) )
			{
				return false;
			}
		}

		$namespace = $this->schemaFileToNamespace[ $xsd ];
		if ( ! isset( $this->importedSchemas[ $namespace ] ) ) return false;

		return $this->importedSchemas[ $namespace ];
	}

	/**
	 * Return a taxonomy that is associated with a prefix
	 * @param string $prefix
	 * @return boolean|XBRL
	 */
	public function getTaxonomyWithPrefix( $prefix )
	{
		if ( ! $prefix ) return false;

		foreach ( $this->importedSchemas as $taxonomy )
		{
			if ( $taxonomy->getPrefix() == $prefix )
			{
				return $taxonomy;
			}
		}

		return false;
	}

	/**
	 * Returns an array of any taxonomies where the id is one for an arcrole type
	 * @param string $id
	 * @return XBRL[]
	 */
	public function getTaxonomiesWithArcRoleTypeId( $id )
	{
		return array_filter( $this->importedSchemas, function( /** @var XBRL $taxonomy */ $taxonomy ) use( $id )
		{
			return $taxonomy->hasArcRoleTypeId( $id ); }
		);
	}

	/**
	 * Create a path to the parallel folder for a url
	 * @param string $url
	 * @param string $cacheLocation
	 * @return string
	 */
	public static function createPathFromUrl( $url, $cacheLocation )
	{
		$parts = parse_url( $url );

		$path = str_replace( '\\', '/', rtrim( $cacheLocation, '\\/' ) );

		if ( isset( $parts['scheme'] ) ) $path .= "/{$parts['scheme']}";
		if ( isset( $parts['host'] ) ) $path .= "/{$parts['host']}";
		if ( isset( $parts['path'] ) ) $path .= "/{$parts['path']}";

		return $path;
	}

	/**
	 * Save a file that has a normal URL by creating a parallel folder structure.
	 * Will return true if the file is saved of false if:
	 *
	 *   caching is not enabled;
	 *   a folder cannot be created;
	 *   the file cannot be written
	 *
	 * @param string $url
	 * @param string $content
	 * @return boolean
	 */
	public function saveCacheFile( $url, $content )
	{
		if ( ! $this->useCache ) return false;

		if ( XBRL::endsWith( $url, "/" ) )
		{
			return false;
		}

		$path = XBRL_Global::createPathFromUrl( $url, $this->cacheLocation );

		$dir = pathinfo( $path, PATHINFO_DIRNAME );
		if ( ! file_exists( $dir ) )
		{
			if ( ! mkdir( $dir, 0777 , true ) )
			{
				$this->log()->err( "Unable to create the path '$dir'" );
				return false;
			}
		}

		if ( ! file_put_contents( $path, $content ) )
		{
			$this->log()->err( "Unable to write contents to '$path'" );
			return false;
		}

		return true;
	}

	/**
	 * Initializes the cache.  Reads the cache if available or creates one.
	 * If there are problems, such as the cache location not being writeable the cache is disabled.
	 * @return void
	 */
	public function initializeCache()
	{
		if ( $this->cacheLocation === null )
		{
			$this->cacheLocation = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xbrl_taxonomy_cache' . DIRECTORY_SEPARATOR;
		}

		$this->cacheLocation = rtrim( $this->cacheLocation, '/' ) . '/';

		// $this->log()->info( "    Taxonomy cache location: " . $this->cacheLocation );

		// The cache location must exist or be creatable
		if ( ! file_exists( $this->cacheLocation ) )
		{
			// Attempt to create the directory
			if ( mkdir( $this->cacheLocation ) === false )
			{
				$this->useCache = false;
				$this->log()->err( "Failed to create the XBRL taxonomy cache. Caching will be disabled." );
				return;
			}
		}
	}

	/**
	 * Removes any cached file and directories
	 * @return boolean True if the directory exists and has been deleted
	 */
	public function removeCache()
	{
		if ( ! $this->useCache || ! $this->cacheLocation ) return false;

		if ( ! is_dir( $this->cacheLocation ) ) return false;

		$rmrf = function ($dir) use ( &$rmrf )
		{
		    foreach ( glob( $dir ) as $file )
		    {
		        if ( is_dir( $file ) )
		        {
		            $rmrf( "$file/*" );
		            rmdir( $file );
		        }
		        else
		        {
		            unlink( $file );
		        }
		    }
		};

		$rmrf( $this->cacheLocation );

		return true;
	}

	/**
	 * Reset the cache of primary items
	 * @return void
	 */
	public function resetPrimaryItemsCache()
	{
		$this->primaryItemsCache = null;
	}

	/**
	 * Returns a reference to the current primary items cache
	 * @return array
	 */
	public function &getPrimaryItemsCache()
	{
		return $this->primaryItemsCache;
	}

	/**
	 * Returns true if there are cached primary items
	 * @return boolean
	 */
	public function hasPrimaryItemsCache()
	{
		return ! is_null( $this->primaryItemsCache );
	}

	/**
	 * Set the cache of primary items
	 * @param array An array of primary items to store in the cache
	 * @return void
	 */
	public function setPrimaryItemsCache( $cache )
	{
		$this->primaryItemsCache = $cache;
	}

	/**
	 * Adds locators, arcs, labels and labelsByHref collections to the respective collections in XBRL_Global.
	 * This is called by the processLabelLinkbase and fromExtensionTaxonomy functions
	 * @param array $locators
	 * @param array $arcs
	 * @param array $labels
	 * @param array $labelsByHref
	 * @param string $extendedLinkRole (optional)
	 */
	public function addLabels( $locators, $arcs, $labels, $labelsByHref, $extendedLinkRole = null )
	{
		if ( is_null( $extendedLinkRole ) ) $extendedLinkRole = XBRL_Constants::$defaultLinkRole;
		$link = &$this->labels[ $extendedLinkRole ];

		$link['locators'] = isset( $link['locators'] ) && count( $link['locators'] )
			? array_merge( $link['locators'], $locators )
			: $locators;

		if ( isset( $link['arcs'] ) && count( $link['arcs'] ) )
		{
			$link['arcs'] = array_merge_recursive( $link['arcs'], $arcs );
			/*
			// Remove duplicates generated by the recursive merge function
			foreach ( $link['arcs'] as $label => &$labelLabels )
			{
				foreach ( $labelLabels as $labelLabel => $items )
				{
					if ( count( $items ) < 2 ) continue;

					$keep = array( $items[0] );
					for ( $i = 1; $i < Count( $items ); $i++ )
					{
						if (
								$items[0]['label'] === $items[ $i ]['label'] &&
								$items[0]['priority'] === $items[ $i ]['priority'] &&
								$items[0]['use'] === $items[ $i ]['use']
						   )
						{
							continue;
						}

						$keep[] = $items[ $i ];
					}

					$labelLabels[ $labelLabel ] = $keep;
				}
			}
			unset( $labelLabels );
			*/
		}
		else
		{
			$link['arcs'] = $arcs;
		}

		$link['labels'] = isset( $link['labels'] ) && count( $link['labels'] )
			? array_merge_recursive( $link['labels'], $labels )
			: $labels;

		$link['labelshref'] = isset( $link['labelshref'] ) && count( $link['labelshref'] )
			? array_merge_recursive( $link['labelshref'], $labelsByHref )
			: $labelsByHref;

		unset( $link );
	}

	/**
	 * Display a default label for this class which is the taxonomy file name
	 * @return string
	 */
	public function __toString()
	{
		$schemaCount = count( $this->importedSchemas );
		return "$schemaCount taxonomy schemas";
	}

	/**
	 * The schema file that is the root of the libxml loading
	 * @var string
	 */
	private $schemaRoot;

	/**
	 * Enable the entity loader and pass a root as the basis for the discovery of non-http files
	 *
	 * @param string $schemaRoot The root path to the taxonomy files
	 */
	public function setEntityLoader( $schemaRoot )
	{
		$this->schemaRoot = $schemaRoot;

		$useEntityLoader = true; // For debug convenience
		if ( version_compare( PHP_VERSION, "5.4", ">=" ) && $useEntityLoader )
		{
			libxml_set_external_entity_loader( array( $this, 'entity_loader' ) );
		}
	}

	/**
	 * Unload an entity loader callback
	 */
	public function resetEntityLoader()
	{
		if ( version_compare( PHP_VERSION, "5.4", ">=" ) && false )
		{
			libxml_set_external_entity_loader( null );
		}
	}

	/**
	 * An entity loader that can access schema and linkbase files in
	 * $cacheLocation
	 *
	 * @param string $public
	 * @param string $system
	 * @param string $info
	 * @return null|string
	 */
	private function entity_loader( $public, $system, $info )
	{
		// $this->log()->err( "$system" );

		$parts = parse_url( $system );
		if ( ! isset( $parts['host'] ) || ( isset( $parts['scheme'] ) && $parts['scheme'] == 'file' ) )
		{
			if ( file_exists( $system ) )
			{
				return $system;
			}
			else if ( ! isset( $parts['host'] ) && ! isset( $parts['scheme'] ) )
			{
				$location = $this->schemaRoot . "/" . $system;
				if ( file_exists( $location ) )
				{
					return $location;
				}
				return null;
			}
			else
			{
				$cacheLocation = rtrim( str_replace( "\\", "/", $this->cacheLocation ), '/' ) . '/';

				if ( strpos( $system, $cacheLocation ) !== 0 ) return null;

				$path = str_replace( $cacheLocation, '', $system );
				// Re-create a url from the path
				$parts = explode( "/", $path );
				$system = "{$parts[0]}://{$parts[1]}/" . implode( "/", array_slice( $parts, 2 ) );
			}
		}
		else
		{
			// Maybe its already cached
			$path = $this->findCachedFile( $system );
			if ( $path !== false) return $path;
		}

		// Get and cache the document
		$xml = XBRL::getXmlInner( $system, $this );

		// Return the path to the cached file
		return $this->findCachedFile( $system );
	}

	/**
	 * Get the log instance
	 * @return XBRL_Log
	 */
	protected function log()
	{
		return XBRL_Log::getInstance();
	}
}
