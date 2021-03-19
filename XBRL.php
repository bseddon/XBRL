<?php

/**
 * Main XBRL taxonomy instance
 *
 * Almost all the XBRL 2.1 specification is implemented and support for XBRL DT 1.0.
 * The omissions from the XBRL 2.1 specification are:
 *
 * Support has not been added for reference linkbases.  The focus of this code is to
 * support internal reporting and it is unlikely that documentation of internal systems
 * will be accomplished using information in a reference linkbase.
 *
 * The arc roles general-special and similar-tuples are not supported
 *
 * The use of XPointer sytax in locator href values is accommodated but only to extract
 * the XPointer value. To complete this support it would be necessary to create an XPath
 * query from the value.
 *
 * elements with notAll has-hypercube roles are detected and the hypercubes are recorded
 * but the they are not applied to negate dimensions, domains and members
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
 *
 */

use XBRL\Formulas\Formulas;
use lyquidity\xml\QName;
use lyquidity\xml\schema\SchemaTypes;
use XBRL\Formulas\Resources\Filters\ConceptName;
use XBRL\Formulas\Resources\Variables\VariableSet;

/**
 * Main XBRL control class
 * @author Bill Seddon
 */
class XBRL {

	// Static variables
	/**
	 * A map of the schema namespaces to class
	 * @var array
	 */
	private static $namespace_to_class_map		= array();

	/**
	 * A map of the entry point namespaces to class
	 * @var array
	 */
	private static $entrypoints_to_class_map	= array();

	/**
	 * A map of the compiled taxonomy file to use for each requested taxonomy
	 * @var array
	 */
	private static $xsd_to_compiled_map			= array();

	/**
	 * When set this value will be returned by getDefaultLanguage
	 * @var string
	 */
	public static $specificLanguage				= null;

	// Instance variables
	/**
	 * Reference to a global shared class that holds indexed references all taxonomy documents
	 * @var XBRL_Global $context
	 */
	public $context								= null;

	/**
	 * A list of the schemas directly imported by this schema
	 * @var array
	 */	private $indirectNamespaces				= array();

	/**
	 * An array of schema files imported by this taxonomy
	 */
	public function getIndirectNamespaces()
	{
		return $this->indirectNamespaces;
	}

	/**
	 * An array of the schema namespaces that have used this schema
	 * @var array
	 */	private $usedByNamespaces				= array();

	/**
	 *
	 * @param XBRL $taxonomy
	 */
	public function AddUserNamespace( $taxonomy )
	{
		if ( ! ( $taxonomy instanceof XBRL ) ) return;
		$this->usedByNamespaces[] = $taxonomy->getNamespace();
	}

	/**
	 * A list of the schemas that have used this schema
	 */
	public function getUsedByNamespaces()
	{
		return $this->usedByNamespaces;
	}

	/**
	 * The XML of the texonomy schema document
	 * @var SimpleXMLElement $xbrlDocument
	 */
	protected $xbrlDocument						= null;

	/**
	 * Seggested by tim-vandecasteele to allow the same information in
	 * linkbases shared by more than one taxonomy to be loaded into all taxonomies
	 * @var array
	 * @see https://github.com/tim-vandecasteele/xbrl-experiment/commit/3610466123ffe936fd45b5a0299fa97baa4699ac
	 */
	private $processedLinkbases					= array();

	/**
	 * The elements in the taxonomy
	 * @var array $elementIndex
	 */
	private $elementIndex						= array();

	/**
	 * The elements in the taxonomy that are tuples
	 * @var array $tupleMembersIndex
	 */
	private $tupleMembersIndex					= array();

	/**
	 * The elements in the taxonomy that are hypercubes
	 * @var array $elementHypercubes
	 */
	private $elementHypercubes					= array();

	/**
	 * The elements in the taxonomy that are hypercube dimensions
	 * @var array $elementDimensions
	 */
	private $elementDimensions					= array();

	/**
	 * A list of any custom linkbase link element names
	 * @var array
	 */
	private $elementLinkTypes					= array();

	/**
	 * A list of any custim linkbase arc element name
	 * @var array
	 */
	private $elementArcTypes					= array();

	/**
	 * A list of any custom link indexed by roleUri
	 * @var array
	 */
	private $customRoles						= array();

	/**
	 * A list of any generic link indexed by roleUri
	 * @var array
	 */
	protected $genericRoles						= array();

	/**
	 * The file name of the taxonomy
	 * @var string $schemaLocation
	 */
	private $schemaLocation						= "";

	/**
	 * The namespace of the taxonomy
	 * @var string $namespace
	 */
	private $namespace							= "";

	/**
	 * The prefix used for the target namespace
	 * @var string
	 */
	private $prefix = null;

	/**
	 * Has the taxonomy been loaded
	 * @var boolean $loadSuccess
	 */
	private $loadSuccess						= false;

	/**
	 * A list of the complex types from the taxonomy
	 * @var array $complexTypes
	 */
	private $complexTypes						= array();

	/**
	 * A list of arcrole types from the schema
	 * @var array $arcroleTypes
	 */
	private $arcroleTypes						= array();

	/**
	 * A list of arcrole type ids from the schema
	 * @var array $arcroleTypeIds The array is indexed by id with the value being a path that identifies the specific arcroleType
	 */
	private $arcroleTypeIds						= array();

	/**
	 * A list of role types from the schema
	 * @var array $roleTypes
	 */
	private $roleTypes							= array();

	/**
	 * A list of role type ids from the schema
	 * @var array $roleTypeIds The array is indexed by id with the value being a path that identifies the specific roleType
	 */
	private $roleTypeIds						= array();

	/**
	 * A list of linkbase types from the schema
	 * @var array $linkbaseTypes
	 */
	private $linkbaseTypes						= array();

	/**
	 * A list of role refs from the schema
	 * @var array $definitionRoleRefs
	 */
	private $definitionRoleRefs					= array();

	/**
	 * A list of role ref details for roles that are maintained in another taxonomy
	 * These willl be saved with a compiled taxonomy or removed.
	 * @var array
	 */
	protected $foreignDefinitionRoleRefs		= array();

	/**
	 * A list of role refs from the schema
	 * @var array $referenceRoleRefs
	 */
	private $referenceRoleRefs					= array();

	/**
	 * Have the string for the taxonomy been loaded
	 * @var boolean $stringsLoaded
	 */
	private $stringsLoaded						= false;

	/**
	 * Caches the prefixes after the first time they are accessed
	 * @var array
	 */
	private $documentPrefixes					= null;

	/**
	 * The name of the XSD of the base taxonomy when an extension taxonomy is being used
	 * @var string
	 */
	private $baseTaxonomy						= null;

	/**
	 * An array containing alternatives to the default number of places to which displayed numeric
	 * values (such as monetaryItemType but not percentItemItem) will be displayed.
	 * By default, the @decimals attribute value will be used if provided.  So, by default, an
	 * instance @decimals value of -3 will be divided by 1000 before it's displayed
	 *
	 * 0 = no rounding
	 * 2 = hundreds
	 * 3 = thousands
	 * 6 = millions
	 *
	 * @var integer $displayRounding
	 */
	private $displayRoundings					= array();

	/**
	 * Flag holding the current validation state
	 *
	 * @var bool
	 */
	private static $validating					= false;

	/**
	 * A list of the schema files imported by this schema
	 * @var array $schemaFiles
	 */
	private $importedFiles						= array();

	/**
	 * An array of schema files imported by this taxonomy
	 */
	public function getImportedFiles()
	{
		return is_array( $this->importedFiles ) ? $this->importedFiles : array();
	}

	/**
	 * A list of the schema files included by this schema
	 * @var array $schemaFiles
	 */
	private $includedFiles						= array();

	/**
	 * Flag set after linkbases have been processed so repeated processing can be avoided
	 * @var bool $linkbasesProcessed
	 */
	private $linkbasesProcessed					= false;

	/**
	 * A temporary variable to hold role types by linkbase file name and is used as
	 * part of the XDT validation processes
	 * This is temporary in the sense that it will not be recorded in the JSON store.
	 * @var array $linkbaseRoleTypes
	 */
	private $linkbaseRoleTypes					= array();

	/**
	 * A variable to hold role target roles by linkbase file name and is
	 * used as part of the XDT validation processes
	 * This is temporary in the sense that it will not be recorded in the JSON store.
	 * @var array $xdtTargetRoles
	 */
	private $xdtTargetRoles						= array();

	/**
	 * A list of 'extra' elements added when compiling an extension taxonomy
	 * @var array $extraElements
	 */
	private $extraElements						= array();

	/**
	 * A temp array used to hold a list of the roles added to custom or generic extended link and arcs
	 * @var array $customArcsAdded
	 */
	private $customArcsAdded					= array();

	/**
	 * arc names MUST be unique within a variable-set so this array maintains
	 * a list of discovered names and how locate the respective arc
	 * @var array $variableSetNames
	 */
	private $variableSetNames					= array();

	/**
	 * An array of linkbases in this document and the element ids they contain
	 * @var array
	 */
	private $linkbaseIds						= array();

	/**
	 * Flag indicating whether there are formulas in the taxonomy
	 * @var bool $hasFormulas
	 */
	private $hasFormulas						= false;

	/**
	 * A list of the linkbases processed and information about them
	 * @var array $linkbases
	 */
	private $linkbases							= array();

	/**
	 * A temporary collection of discovered enumeration concept ids
	 * @var array
	 */
	private $enumerations						= array();

	/**
	 * True if the instance has been loaded from a JSON file
	 * @var string
	 */
	private $loadedFromJSON						= false;

	/**
	 * A changeable function to allow a third party to control the behavior of beforeDimensionalPruned()
	 * @var callable
	 */
	public static $beforeDimensionalPrunedDelegate = null;

	/**
	 * A changeable function to allow a third party to control the behavior of getBeginEndPreferredLabelPairs()
	 * @var callable
	 */
	public static $beginEndPreferredLabelPairsDelegate = null;

	/**
	 * Variable for public functions getBeginEndPreferredLabelPairs
	 * @var array An array of array pairs wherre each member of the pair is a preferred label role
	 */
	public static $beginEndPreferredLabelPairs = array();

	/**
	 * Static constructor
	 */
	public static function constructor()
	{
		self::$beginEndPreferredLabelPairs = array(
			array(
				XBRL_Constants::$labelRolePeriodStartLabel,
				XBRL_Constants::$labelRolePeriodEndLabel
			)
		);

		// Apply a default delegate
		self::$beforeDimensionalPrunedDelegate = function( XBRL $taxonomy, array $dimensionalNode, array &$parentNode )
		{
			return $taxonomy->beforeDimensionalPruned( $dimensionalNode, $parentNode );
		};

		// Apply a default delegate
		self::$beginEndPreferredLabelPairsDelegate = function()
		{
			return self::$beginEndPreferredLabelPairs;
		};

	}

	/**
	 * Reset the static arrays
	 */
	public static function reset()
	{
		XBRL::$namespace_to_class_map = array();
		XBRL::$entrypoints_to_class_map	= array();
		XBRL::$xsd_to_compiled_map = array();
	}

	/**
	 * Return the valiation state
	 * @return boolean
	 */
	public static function isValidating()
	{
		// If the class is set to validate then validate it is
		return XBRL::$validating;
	}

	/**
	 * Sets the flag indicating whether or not the taxonomy should be validated as it is loaded from a schema file
	 *
	 * @param string $state
	 * @return bool The previous state
	 */
	public static function setValidationState( $state = true)
	{
		$previousState = XBRL::$validating;
		XBRL::$validating = $state;
		return $previousState;
	}

	/**
	 * Called to allow a class file to register xsd to class mapping
	 * @param array $map_entries Array of maps
	 * @param string $classname The name of the taxonomy class with which the $xsd_entries are associated
	 * @return void
	 */
	public static function add_namespace_to_class_map_entries( $map_entries, $classname )
	{
		if ( is_string( $map_entries ) && ! empty( $map_entries ) )
		{
			$map_entries = array( $map_entries );
		}

		if ( ! is_array( $map_entries ) || count( $map_entries ) === 0 ) return;

		XBRL::$namespace_to_class_map = array_merge( XBRL::$namespace_to_class_map, array_fill_keys( $map_entries, $classname ) );
	}

	/**
	 * Called to allow a class file to register taxonomy entry point to class mapping
	 * @param array $map_entries Array of maps
	 * @param string $classname The name of the taxonomy class with which the $xsd_entries are associated
	 * @return void
	 */
	public static function add_entry_namespace_to_class_map_entries( $map_entries, $classname  )
	{
		if ( ! is_array( $map_entries ) || count( $map_entries ) === 0 ) return;

		XBRL::$entrypoints_to_class_map = array_merge( XBRL::$entrypoints_to_class_map, array_fill_keys( $map_entries, $classname ) );
	}

	/**
	 * Called to allow a class file to register xsd to class mapping
	 * @param Array $xsd_entries Array of maps
	 * @param string $compiled_taxonomy_name The name of the compiled taxonomy with which the $xsd_entries are associated
	 * @return void
	 */
	public static function add_xsd_to_compiled_map_entries( $xsd_entries, $compiled_taxonomy_name )
	{
		if ( ! is_array( $xsd_entries ) || count( $xsd_entries ) === 0 ) return;

		global $compiled_taxonomy_name_prefix;
		if ( strpos( $compiled_taxonomy_name_prefix, '\\') !== false ) $compiled_taxonomy_name_prefix = str_replace( '\\', '/', $compiled_taxonomy_name_prefix );
		if ( strpos( $compiled_taxonomy_name_prefix, './') ) $compiled_taxonomy_name_prefix = XBRL::normalizePath( $compiled_taxonomy_name_prefix );
		XBRL::$xsd_to_compiled_map = array_merge( XBRL::$xsd_to_compiled_map, array_fill_keys( $xsd_entries, $compiled_taxonomy_name_prefix . $compiled_taxonomy_name ) );
	}

	/**
	 * This function returns the name of the class to use to process XBRL taxonomies
	 * @param string $namespace
	 * @return string The class to be used for the namespace
	 */
	public static function class_from_namespace( $namespace )
	{
		return isset( XBRL::$namespace_to_class_map[ $namespace ] )
			? XBRL::$namespace_to_class_map[ $namespace ]
			: ( isset( XBRL::$namespace_to_class_map[ rtrim( $namespace, '/' ) ] )
				? XBRL::$namespace_to_class_map[ rtrim( $namespace, '/' ) ]
				: ( isset( XBRL::$namespace_to_class_map[ "$namespace/" ] )
					? XBRL::$namespace_to_class_map[ "$namespace/" ]
					: "XBRL"
				  )
			  );
	}

	/**
	 * This function returns the name of the class to use to process XBRL taxonomies based on the supported taxonomy entry points
	 * @param string $namespace
	 * @return string The class to be used for the namespace
	 */
	public static function class_from_entries_map( $namespace )
	{
		return isset( XBRL::$entrypoints_to_class_map[ $namespace ] ) ? XBRL::$entrypoints_to_class_map[ $namespace ] : false;
	}

	/**
	 * This function returns the name of the compiled taxonomy to use in place of the XSD
	 * @param string $xsd  The name of the XSD to be loaded
	 * @return string The name of the corresponding compiled taxonomy
	 */
	public static function compiled_taxonomy_for_xsd( $xsd )
	{
		return isset( XBRL::$xsd_to_compiled_map[ $xsd ] ) ? XBRL::$xsd_to_compiled_map[ $xsd ] : null;
	}

	/**
	 * Loads a taxonomy from a file
	 * @param string[]|string $file A string containing a single filename of .json .zip or .xsd or an array of .xsd files
	 * @param bool $compiling True if the function is being called from the compile function.  Defaults to  false.
	 * @return false|XBRL
	 */
	public static function load_taxonomy( $file = null, $compiling = false )
	{
		if ( $file === null )
		{
			XBRL_Log::getInstance()->warning( "A file (.json, .zip or .xsd) must be provided" );
			return false;
		}

		if ( is_array( $file ) )
		{
			XBRL_Log::getInstance()->info( "    Files: " . implode( "\n\t", array_map( function( $file ) { return basename( $file ); }, $file ) ) );
		}
		else
		{
			XBRL_Log::getInstance()->info( "    File: $file" );
			// Convert the file to an array
			$file = array( $file );
		}

		if ( ! count( $file ) )
		{
			XBRL_Log::getInstance()->warning( "There are no files provided" );
			return false;
		}

		$xbrl = null;

		// Modify the array so the scheme and extension are available
		$files = array_map( function( $file ) {
			$scheme = parse_url( $file, PHP_URL_SCHEME );
			$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			return array(
				'file' => $file,
				'scheme' => $scheme,
				'extension' => $extension,
			);
		}, $file );

		$allXsd = function( $files ) {
			$xsdFiles = array_filter( $files, function( $file ) { return $file['extension'] === "xsd"; } );
			return count( $xsdFiles ) == count( $files );
		};

		if ( $compiling && ! $allXsd( $files ) )
		{
			XBRL_Log::getInstance()->warning( "When compiling all the files provided MUST be .xsd" );
		}

		if ( count( $files ) > 1 && ! $allXsd( $files ) )
		{
			XBRL_Log::getInstance()->warning( "If more than one file is provided then BOTH files MUST be .xsd" );
			return false;
		}

		foreach ( $files as &$file )
		{
			if ( ! $compiling && $file['extension'] === STANDARD_PREFIX_SCHEMA_ALTERNATIVE )
			{
				// Check to see if there is a pre-defined compiled file
				$xsd = strtolower( pathinfo( $file['file'], PATHINFO_BASENAME ) );
				$compiled_taxonomy_file = XBRL::compiled_taxonomy_for_xsd( $xsd );
				if ( $compiled_taxonomy_file !== null )
				{
					$file['extension'] = "json";
					$file['file'] = "$compiled_taxonomy_file.{$file['extension']}";
					$file['scheme'] = "";
				}
				else
				{
					// Check to see if there is a compiled file of the same name
					if ( file_exists( str_replace( ".xsd", ".json", $file['file'] ) ) )
					{
						$file['extension'] = "json";
						$file['file'] = str_replace( ".xsd", ".json", $file['file'] );
					}
				}
			}

			if ( ! in_array( $file['scheme'], array( 'http', 'https' ) ) )
			{
				if ( ! file_exists( $file['file'] ) )
				{
					// First try the other sort of file
					if ( strpos( $file['file'], '.json' ) )
					{
						$file['extension'] = 'zip';
						$file['file'] = str_replace( '.json', '.zip', $file['file'] );
					}
					else
					{
						$file['extension'] = 'json';
						$file['file'] = str_replace( '.zip', '.json', $file['file'] );
					}

					if ( ! file_exists( $file['file'] ) )
					{
						XBRL_Log::getInstance()->warning( "The requested file ({$file['file']}) does not exist." );
						return false;
					}
				}
			}

			unset( $file );
		}

		if ( $allXsd( $files ) )
		{
			// Convert the array
			$files = array_map( function( $file ) { return $file['file']; }, $files );

			$xbrl = XBRL::withTaxonomy( $files, true );
			if ( $xbrl === null || ! $xbrl->loadSuccess )
			{
				XBRL_Log::getInstance()->taxonomy_validation( "5.1", "The taxonomy could not be instantiated.",
					array(
						'count' => count( $files ),
						'file(s)' => "'" . implode( "', '", $files ) . "'",
					)
				);
				$xbrl = null;
			}

			return $xbrl;
		}

		// There should be only one file
		$countFiles = count( $files );
		if ( $countFiles > 1 )
		{
			XBRL_Log::getInstance()->warning( "There should be only one none .xsd file. $countFiles provided." );
			return false;
		}

		$json = null;
		$file = $files[0]['file'];
		$extension = $files[0]['extension'];

		if ( $extension === 'json' )
		{
			$json = file_get_contents( $file );
			if ( ! $json )
			{
				XBRL_Log::getInstance()->warning( "Failed to open JSON store" );
				return false;
			}
		}
		else if ( $extension === 'zip' )
		{
			$zip = new ZipArchive();
			if ( $zip->open( $file ) === true )
			{
				$json = $zip->getFromName( pathinfo( $file, PATHINFO_FILENAME ) . '.json' );
				$zip->close();
			} else
			{
				XBRL_Log::getInstance()->err( 'Failed to open zip file $file' );
				return false;
			}
		}
		else
		{
			XBRL_Log::getInstance()->err( "The requested file type ($extension) is not supported" );
			return false;
		}

		if ( empty( $json ) )
		{
			XBRL_Log::getInstance()->err( "The required json in $file does not exist." );
			return false;
		}

		$xbrl = XBRL::fromJSON( $json, dirname( $file ) );

		if ( $xbrl === false )
		{
			XBRL_Log::getInstance()->err( "The taxonomy DTS contained in the file could not be created" );
			return false;
		}

		$xbrl->afterMainTaxonomy();

		return $xbrl;

	}

	/**
	 * This is a special case constructor for the 'main' instance so
	 * so additional processing can be done *after* the schemas have
	 * been loaded
	 * @param string[]|string $taxonomy_xsd The file containing the taxonomy xsd or an array containing a list of file to load
	 * @param boolean $useCache True if the the cache should be used
	 * @param string $cacheLocation The location of the cache.  Null or not provide will use the default
	 */
	public static function withTaxonomy( $taxonomy_xsd, $useCache = false, $cacheLocation = null )
	{
		$context = XBRL_Global::getInstance();

		if ( $useCache && ! $context->useCache )
		{
			$context->useCache = true;
			$context->cacheLocation = $cacheLocation;
			$context->initializeCache();
		}

		$taxonomy = XBRL::preProcessSchemaFile( $taxonomy_xsd );
		if ( ! $taxonomy ) return $taxonomy;

		XBRL::postProcessSchemaFile( $taxonomy_xsd );
		return $taxonomy;
	}

	/**
	 * Process a set of schema files
	 *
	 * @param string|string[] $taxonomy_xsd
	 * @param number $depth
	 * @return NULL|XBRL
	 */
	private static function preProcessSchemaFile( $taxonomy_xsd, $depth = 0 )
	{
		$context = XBRL_Global::getInstance();

		if ( ! is_array( $taxonomy_xsd ) )
		{
			// Make sure any fragments are removed
			$parts = explode( "#", $taxonomy_xsd );
			$taxonomy_xsd = $parts[0];

			if ( empty( $taxonomy_xsd ) )
			{
				XBRL_Log::getInstance()->warning( "The taxonomy file name supplied is empty" );
				return null;
			}

			$taxonomy_xsd = array( $taxonomy_xsd );
		}

		$processXsd = function( $depth = 0 ) use( &$processXsd, &$taxonomy_xsd, &$context )
		{
			if ( ! count( $taxonomy_xsd ) ) return false;

			$taxonomyXsdFile = array_shift( $taxonomy_xsd );

			$xbrlDocument = XBRL::getXml( $taxonomyXsdFile, $context );
			if ( ! $xbrlDocument instanceof SimpleXMLElement)
			{
				// XBRL_Log::getInstance()->warning( "Unable to load taxonomy: $taxonomyXsdFile" );
				XBRL_Log::getInstance()->instance_validation('4.2', "The schema file cannot be located relative to the instance document", array(
					'name' => $taxonomyXsdFile
				) );
				return null;
			}

			if ( $xbrlDocument->getName() != "schema" )
			{
				XBRL_Log::getInstance()->taxonomy_validation( "5.1", "The file is not a schema file because the root element is not 'schema'.",
					array(
						'root' => $xbrlDocument->getName(),
						'file' => $taxonomyXsdFile,
					)
				);
				return null;
			}

			$namespace = (string) $xbrlDocument['targetNamespace'];
			if ( isset( $context->importedSchemas[ $namespace ] ) )
			{
				$taxonomy = $context->importedSchemas[ $namespace ];

				if ( ! property_exists( $taxonomy, 'xbrlDocument' ) || ! $taxonomy->xbrlDocument )
				{
					// $taxonomy->xbrlDocument = $xbrlDocument;
				}
				if ( ! isset( $context->schemaFileToNamespace[ $taxonomyXsdFile ] ) )
				{
					$context->schemaFileToNamespace[ $taxonomyXsdFile ] = $namespace;
					$context->schemaFileToNamespace[ basename( $taxonomyXsdFile ) ] = $namespace;
				}
				return $taxonomy;
			}

			/**
			 * @var XBRL $classname
			 */
			$classname = XBRL::class_from_namespace( $namespace );

			/**
			 * @var XBRL $taxonomy_instance
			 */
			$taxonomy_instance = new $classname();
			$taxonomy_instance->context =& $context;
			if ( ! $taxonomy_instance->loadSchema( $taxonomyXsdFile, $xbrlDocument, $namespace, $depth + 1, $processXsd ) )
			{
				return null;
			}

			return $taxonomy_instance;
		};

		$taxonomy_instance = $processXsd( $depth );

		return $taxonomy_instance;
	}

	/**
	 * Process a set of schema files
	 *
	 * @param string|string[] $taxonomy_xsd
	 * @param number $depth
	 * @return NULL|XBRL
	 */
	private static function postProcessSchemaFile( $taxonomy_xsd, $depth = 0 )
	{
		$context = XBRL_Global::getInstance();

		if ( ! is_array( $taxonomy_xsd ) )
		{
			if ( empty( $taxonomy_xsd ) )
			{
				XBRL_Log::getInstance()->warning( "The taxonomy file name supplied is empty" );
				return null;
			}

			$taxonomy_xsd = array( $taxonomy_xsd );
		}

		$processXsd = function( $depth = 0 ) use( &$processXsd, &$taxonomy_xsd, &$context )
		{
			if ( ! count( $taxonomy_xsd ) ) return false;

			$taxonomyXsdFile = array_shift( $taxonomy_xsd );

			// Get the existing taxonomy for the file
			/**
			 * @var XBRL $taxonomy
			 */
			$taxonomy = $context->getTaxonomyForXSD( $taxonomyXsdFile );
			if ( ! $taxonomy )
			{
				XBRL_Log::getInstance()->warning( "The taxonomy for '$taxonomyXsdFile' cannot be found." );
				return;
			}

			// If there is no xbrl document then the taxonomy has been loaded from a compiled taxonomy so linkbases will be loaded
			if ( $taxonomy->xbrlDocument )
			{
				$taxonomy->loadLinkbases( $depth + 1, $processXsd );
			}

			return $taxonomy;
		};

		/**
		 * @var \XBRL $taxonomy
		 */
		$taxonomy = $processXsd( $depth );

		if ( $taxonomy )
		{
			if ( $taxonomy->xbrlDocument && \XBRL::isValidating() )
			{
				// Look for circular references in each of the extended links
				foreach ( $taxonomy->context->presentationRoleRefs as $role => $roleRef )
				{
					// If there is no hierarchy there can be no circular references
					if ( ! isset( $roleRef['hierarchy'] ) ) continue;

					$taxonomy->validateDirectedCycles( 'presentation', $role, $roleRef['hierarchy'], array(),
						function( $role, $result, $linkbase ) {
							XBRL_Log::getInstance()->taxonomy_validation( "5.2.4.2", "The linkbase contains circular references which are not permitted",
								array(
									'role' => "'$role'",
									'node' => "'$result'",
									'linkbase' => "'$linkbase'",
									'error' => 'xbrldte:DRSDirectedCycleError',
								)
							);
						}
					);
				}
			}

			// Look at the linkbases to determine if there are any definition additions that need processing
			// if ( $taxonomy->linkbaseRefExists( XBRL_Constants::$DefinitionLinkbaseRef ) )
			{
				$taxonomy->validateDimensions( true );
			}
			// Look at the linkbases to determine if there are any definition additions that need processing
			if ( $taxonomy->linkbaseRefExists( XBRL_Constants::$DefinitionLinkbaseRef ) ||
				 $taxonomy->linkbaseRefExists( XBRL_Constants::$DefinitionLinkbaseRef ) )
			{
				$taxonomy->fixupPresentationHypercubes();
			}
			$taxonomy->afterMainTaxonomy();
		}

		return $taxonomy;
	}

	/**
	 * Returns true if a linkbase ref exists with $linkbaseRefType for the taxonomy
	 * @param string $linkbaseRefType One of the standard linkbaseRef constants such as XBRL_Constants::$DefinitionLinkbaseRef
	 */
	private function linkbaseRefExists( $linkbaseRefType )
	{
		return isset( $this->linkbaseTypes[ $linkbaseRefType ] ) &&
			   count( $this->linkbaseTypes[ $linkbaseRefType ] );
	}

	/**
	 * Provides an opportunity for a descendant class implemenentation to take action after the main taxonomy is loaded
	 */
	public function afterMainTaxonomy()
	{
		// Do nothing
	}

	/**
	 * Provides an opportunity for a descendant class implemenentation to take action after each taxonomy is loaded
	 * @param string $taxonomy_schema
	 */
	protected function afterLoadTaxonomy( $taxonomy_schema )
	{
		// Do nothing
	}

	/**
	 * Provides an opportunity for a descendant class implemenentation to take action before each taxonomy is loaded
	 * @param string $taxonomy_schema
	 */
	protected function beforeLoadTaxonomy( $taxonomy_schema )
	{
		// Do nothing
	}

	/**
	 * Load a taxonomy from a store created from a .json file of an extension taxonomy (has a valid 'baseTaxonomy' element).
	 * @param array $store
	 * @param string $compiledFolder (optional)
	 * @return boolean|XBRL The resulting taxonomy instance or false if one cannot be created
	 */
	private static function fromExtensionTaxonomy( $store, $compiledFolder = null )
	{
		$baseTaxonomy = $store['baseTaxonomy']; // e.g.	us-gaap-2015-01-31.xsd
		$compiledBaseTaxonomy = XBRL::compiled_taxonomy_for_xsd( $baseTaxonomy );

		if ( $compiledBaseTaxonomy === null )
		{
			XBRL_Log::getInstance()->err( "A base taxonomy element exist but the value is empty" );
			return false;
		}

		if ( $compiledFolder && ! XBRL::endsWith( $compiledFolder, "/" ) )
		{
			$compiledFolder .= "/";
		}

		$xbrl = XBRL::load_taxonomy( "$compiledFolder$compiledBaseTaxonomy.json" );

		if ( $xbrl === false )
		{
			XBRL_Log::getInstance()->err( "Compiled taxonomy '$compiledBaseTaxonomy' failed to load" );
			return false;
		}

		$classname = get_class( $xbrl );

		// Grab the base labels
		$labels =& $store['context']['labels'][ XBRL_Constants::$defaultLinkRole ];

		$labelSet = array(
			'labels' => $store['context']['labels'],
			'labelLinkRoleRefs' => $store['context']['labelLinkRoleRefs'],
			'labelRoleRefs' => $store['context']['labelRoleRefs'],
			'labelSource' => 'extension',
		);

		$xbrl->swapLabels( $labelSet );

		// BMS 2018-07-17
		// $xbrl->swapTypes( $store['context']['types'] );
		$xbrl->context->types->mergeTypes( $store['context']['types'] );

		// Add the supplied taxonomy as a
		$namespace = $store['mainNamespace'];
		XBRL::add_namespace_to_class_map_entries( array( $namespace ), $classname );

		/**
		 *
		 * @var XBRL $taxonomy
		 */
		$taxonomy = new $classname();
		$taxonomy->context =& $xbrl->context;

		$data = $store['schemas'][ $namespace ];
		$taxonomy->fromStore( $data );

		// Fixup the context when the taxonomy extends one or more base taxonomies
		$context =& $taxonomy->context;
		if ( ! empty( $store['context']['calculationRoleRefs'] ) )
		{
			$context->calculationRoleRefs	 = $context->calculationRoleRefs + $store['context']['calculationRoleRefs'];
		}
		if ( ! empty( $store['context']['generalSpecialRoleRefs'] ) )
		{
			$context->generalSpecialRoleRefs = $context->generalSpecialRoleRefs + $store['context']['generalSpecialRoleRefs'];
		}
		if ( ! empty( $store['context']['presentationRoleRefs'] ) )
		{
			$context->presentationRoleRefs	 = $context->presentationRoleRefs + $store['context']['presentationRoleRefs'];
		}
		if ( ! empty( $store['context']['essenceAlias'] ) )
		{
			$context->essenceAlias			 = $context->essenceAlias + $store['context']['essenceAlias'];
		}
		if ( ! empty( $store['context']['requireElements'] ) )
		{
			$context->requireElements		 = $context->requireElements + $store['context']['requireElements'];
		}
		if ( ! empty( $store['context']['nonDimensionalRoleRefs'] ) )
		{
			$context->nonDimensionalRoleRefs = $context->nonDimensionalRoleRefs + $store['context']['nonDimensionalRoleRefs'];
		}
		if ( ! empty( $store['context']['dimensionDefaults'] ) )
		{
			$context->dimensionDefaults =& $store['context']['dimensionDefaults'];
		}
		if ( ! empty( $store['context']['formulaNames'] ) )
		{
			$context->formulaNames =& $store['context']['formulaNames'];
		}
		if ( ! empty( $store['validTaxonomySet'] ) )
		{
			$context->validTaxonomySet = $store['validTaxonomySet'];
		}
		if ( ! empty( $store['isExtensionTaxonomy'] ) )
		{
			if ( filter_var( $store['isExtensionTaxonomy'], FILTER_VALIDATE_BOOLEAN ) )
			{
				$context->setIsExtensionTaxonomy();
			}
		}

		$taxonomy->setBaseTaxonomy( $baseTaxonomy );

		// BMS 2020-05-20 The previously imported namespaces are captured so actions
		//                that should affect extension schemas, such as getting all
		//                all elements, can skip skip operating on base taxonomy schemas
		$context->previouslyImportedSchemaNamespaces = array_keys( $context->importedSchemas );
		$context->importedSchemas[ $namespace ] =& $taxonomy;
		$context->schemaFileToNamespace[ $taxonomy->getSchemaLocation() ] = $namespace;
		$context->schemaFileToNamespace[ $taxonomy->getTaxonomyXSD() ] = $namespace;

		foreach ( $store['schemas'] as $schemaNamespace => $data )
		{
			if ( $schemaNamespace === $namespace ) continue;

			$classname = XBRL::class_from_namespace( $schemaNamespace );

			/**
			 * @var XBRL $xbrl
			 */
			$xbrl = new $classname();
			$xbrl->context =& $context;
			$xbrl->fromStore( $data );
			$context->importedSchemas[ $schemaNamespace ] = $xbrl;
			$context->schemaFileToNamespace[ $xbrl->getSchemaLocation() ] = $schemaNamespace;
			$context->schemaFileToNamespace[ $xbrl->getTaxonomyXSD() ] = $schemaNamespace;
		}

		XBRL::fixupForeignDefinitionsFromStore( $store['schemas'] );

		$xbrl->rebuildLabelsByHRef();

		// Double-check
		$taxonomy = $xbrl->getTaxonomyForNamespace( $taxonomy->getNamespace() );
		if ( $taxonomy === false )
		{
			XBRL_Log::getInstance()->err( "Extension taxonomy failed to load" );
			return false;
		}

		return $taxonomy;
	}

	/**
	 * This is a complementary one for SEC extension taxonomies.  It first loads the US_GAAP_2015
	 * compiled taxonomy then adds the extension taxonomy given as parameter one
	 *
	 * Compiles a taxonomy into an a collection of arrays then convert to JSON and save to a file.
	 *
	 * @param string $taxonomy_file The name of the taxonomy file (xsd) to load
	 * @param string $className The name of the XBRL taxonomy class to load
	 * @param string $namespace The namespace of the extension taxonomy.
	 * @param string $output_basename A name to use as the base for output files. 'xxx' will result in 'xxx.zip' and 'xxx.json' output files. If a name is not supplied, the basename of the schema file will be used.
	 * @param string $compiledPath
	 * @param bool $prettyPrint
	 * @return boolean|XBRL <false, XBRL>
	 */
	public static function compileExtensionXSD( $taxonomy_file, $className, $namespace = null, $output_basename = null, $compiledPath = null, $prettyPrint = false )
	{
		if ( ! filter_var( $taxonomy_file, FILTER_VALIDATE_URL ) ) $taxonomy_file = str_replace( '\\', '/', $taxonomy_file );

		$taxonomy = XBRL::loadExtensionXSD( $taxonomy_file, $className, $namespace, $compiledPath );
		if ( ! $taxonomy ) return false;

		$namespace = $taxonomy->getNamespace();

		// This is the folder in which the generated zip file will be saved.
		$pathinfo = pathinfo( $taxonomy_file );
		$output_path = is_null( $compiledPath )
			? ( isset( $pathinfo['dirname'] ) ? $pathinfo['dirname'] : "." )
			: $compiledPath;

		// Get the basename from the taxonomy if one is not supplied but if one is, make sure only the basename is used even if a full path has been specified.
		$output_basename = $output_basename === null ? $pathinfo['filename'] : basename( $output_basename );

		$labelTaxonomies = array_filter( $taxonomy->context->importedSchemas, function( $tax )
		{
			return isset( $tax->labels[ XBRL_Constants::$defaultLinkRole ] );
		} );

		// Now remove the existng labels, arcs and locators so the saved file only contains the extension components
		if ( count( $labelTaxonomies ) == 0 )
		{
			XBRL_Log::getInstance()->err( "There are no labels in the extension taxonomy" );
			return false;
		}

		// Grab the extension taxonomy labels
		// Normally the labels are held within the context but when an extension
		// taxonomy is being processed, a copy of the extension taxonomy labels
		// is held in the XBRL instance prepresenting the extension taxonomy.
		// See the end of the processLabelLinkbaseXml() function in class XBRL.
		$taxonomy->context->labels = array();

		// Put them into the context.  This is so just the labels unique to the extension
		// taxonomy will be saved.  The full set of labels will be reconstructed when the
		// the extension taxonomy is load *on top of* the relevant base taxonomy
		foreach ( $labelTaxonomies as $namespace => $tax )
		{
			foreach ( $tax->labels as $roleRefsKey => $labelDetail )
			{
				if ( count( $taxonomy->context->labels ) )
				{
					// Now there is a set of locators, arcs, labels and $labelsByHref to store in the context
					$taxonomy->context->addLabels( $labelDetail['locators'], $labelDetail['arcs'], $labelDetail['labels'], $labelDetail['labelsByHref'], $roleRefsKey );

				}
				else
				{
					$taxonomy->context->labels[ $roleRefsKey ] =& $labelDetail;
				}
			}
		}

		unset( $tax );

		// Temporarily delete the other schemas
		$typesJSON = null;
		$previouslyImportedTaxonomies = $taxonomy->removePreviousTaxonomies( $typesJSON );

		$types = $taxonomy->context->types;

		foreach( $taxonomy->previouslyImportedSchemas as $namespace => $tax )
		{
			$count = $types->removeElementsTaxonomy( $tax );
			// echo "$count elements removed for {$tax->getPrefix()}\n";
		}

		// Create and save the JSON
		$json = $taxonomy->toJSON( $taxonomy->baseTaxonomy, $prettyPrint );

		// Now the JSON store has been created restore the imported schemas 
		// and types or the taxonomy returned will not be complete
		foreach( $previouslyImportedTaxonomies as $namespace => $previousTaxonomy )
		{
			$taxonomy->context->importedSchemas[ $namespace ] = $previousTaxonomy;
		}

		$types->mergeTypes( json_decode( $typesJSON, true ) );

		// Make sure this exists
		$taxonomy->context->previouslyImportedSchemaNamespaces = array_keys( $previouslyImportedTaxonomies );

		file_put_contents( "$output_path/$output_basename.json", $json );
		$zip = new ZipArchive();
		$zip->open( "$output_path/$output_basename.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFile( "$output_path/$output_basename.json", "$output_basename.json" );

		if ( $zip->close() === false )
		{
			XBRL_Log::getInstance()->err( "Error closing zip file" );
			XBRL_Log::getInstance()->err( $zip->getStatusString() );
		}

		return $taxonomy;
	}

	/**
	 * Removes the effect of previous taxonomies by removing the taxonomies from
	 * the list of imported taxonomies and their types from the context
	 * @param string $typesJSON
	 * @return \XBRL[] An array of the removed taxonomies indexed by namespaces just like the context->importedSchemas array
	 */
	function removePreviousTaxonomies( &$typesJSON )
	{
		/** @var XBRL[] */
		$previouslyImportedTaxonomies = array_filter( $this->context->importedSchemas, function( $importedTaxonomy ) 
		{
			return isset( $this->previouslyImportedSchemas[ $importedTaxonomy->getNamespace() ] );
		} );
		$this->context->importedSchemas = array_diff_key( $this->context->importedSchemas, $this->previouslyImportedSchemas );

		// Remove types belonging to previouslyImportedSchemas but make a persistent copy first
		$types = $this->context->types;
		$typesJSON = json_encode( $types->toArray() );

		foreach( $this->previouslyImportedSchemas as $namespace => $tax )
		{
			$count = $types->removeElementsTaxonomy( $tax );
			// echo "$count elements removed for {$tax->getPrefix()}\n";
		}

		return $previouslyImportedTaxonomies;
	}

	/**
	 * Load a specific taxonomy file.  Ideally the taxonomy will be compiled then used via loadExtensionTaxonomy
	 * This function will be called when the caller *knows* the XSD is US GAAP or some other extension taxonomy.
	 *
	 * This call will be used when the caller knows there is a base taxonomy that is compiled separately.
	 *
	 * @param string $taxonomy_file The name of the taxonomy file (xsd) to load
	 * @param string $className The name of the XBRL taxonomy class to load
	 * @param string $namespace The namespace of the extension taxonomy
	 * @param string $compiledPath The location of compiled taxonomies
	 * @return boolean|XBRL The loaded taxonomy or or false
	 */
	public static function loadExtensionXSD( $taxonomy_file, $className, $namespace = null, $compiledPath = null )
	{
		// Create an instance to load the PHP
		$class = new $className();

		// Check the taxonomy location
		$context = XBRL_Global::getInstance();
		// BMS 2020-05-22 Don't remember why this section of code did not use XBRL::getXml()
		//                Changing the $taxonomy_file to point to a cached file causes other
		//                that do not occur when using getXml.  Anyway, it uses getXml now.
		// if ( $context->useCache )
		// {
		// 	if ( ( $path = $context->findCachedFile( $taxonomy_file ) ) !== false )
		// 	{
		// 		$taxonomy_file = $path;
		// 	}
		// }
        //
		// if ( filter_var( $taxonomy_file, FILTER_VALIDATE_URL ) === false )
		// {
		// 	// If the taxonomy file is not a url make sure the file exists
		// 	if ( ! file_exists( $taxonomy_file ) )
		// 	{
		// 		XBRL_Log::getInstance()->err( "The supplied extension taxonomy file does not exist." );
		// 		return false;
		// 	}
		// }
        //
		// $xbrlDocument = simplexml_load_file( $taxonomy_file );
		$xbrlDocument = XBRL::getXml( $taxonomy_file, $context );
		if ( ! $xbrlDocument )
		{
			XBRL_Log::getInstance()->err( "The supplied extension taxonomy file does not exist." );
			return false;
		}

		if ( $namespace === null )
		{
			if ( ! isset( $xbrlDocument['targetNamespace'] ) )
			{
				XBRL_Log::getInstance()->err( "A namespace cannot be found in the extension taxonomy schema file provided" );
				return false;
			}
			$namespace = (string) $xbrlDocument['targetNamespace'];
		}

		$getExtensionBaseTaxonomy = function ( &$xbrlDocument )
		{
			$imports = $xbrlDocument->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] )->import;
			/**
			 * @var SimpleXMLElement $import
			 */
			foreach ( $imports as $element => $import )
			{
				$attributes = $import->attributes();
				// echo (string) $attributes['schemaLocation'] . " (" . (string)$attributes['namespace'] . ")\n";
				if ( XBRL::class_from_entries_map( (string) $attributes['namespace'] ) === false ) continue;
				$path = parse_url( trim( (string) $attributes['schemaLocation'], PHP_URL_PATH ) );
				if ( ! $path )
				{
					XBRL_Log::getInstance()->err( "The schema of the base taxonomy cannot be located" );
					return false;
				}

				return pathinfo( $path['path'], PATHINFO_BASENAME );
			}

			return false;
		};

		$baseTaxonomy = $getExtensionBaseTaxonomy( $xbrlDocument );

		if ( ! $baseTaxonomy )
		{
			XBRL_Log::getInstance()->err( "Failed to find a valid base taxonomy among the imported schemas" );
			return false;
		}

		$compiledBaseTaxonomy = XBRL::compiled_taxonomy_for_xsd( $baseTaxonomy );

		if ( ! is_null( $compiledPath ) )
		{
			$compiledBaseTaxonomy = $compiledPath . $compiledBaseTaxonomy;
		}

		// Add the supplied taxonomy as a
		XBRL::add_namespace_to_class_map_entries( array( $namespace ), $className );

		/**
		 * @var XBRL_US_GAAP_2015
		 */
		$xbrl = XBRL::load_taxonomy( "$compiledBaseTaxonomy.json" );
		if ( $xbrl === false )
		{
			XBRL_Log::getInstance()->err( "Compiled US GAAP 2015 taxonomy failed to load" );
			return false;
		}

		// LabelsByHRef is only needed to read the labels from an XSD
		$xbrl->rebuildLabelsByHRef();

		// $xbrl->importSchema( $taxonomy_file, 0, true );

		$context->setIsExtensionTaxonomy();
		// BMS 2018-07-17 Don't remember why elements are being cleared.  Maybe to save space?
		//				  The existing elements are needed by at least some extension taxonomies.
		// $context->types->clearElements();
		$importedSchemas = $context->importedSchemas;

		$taxonomy = XBRL::withTaxonomy( $taxonomy_file );
		if ( $taxonomy === false )
		{
			XBRL_Log::getInstance()->err( "Extension taxonomy failed to load" );
			return false;
		}

		$taxonomy->previouslyImportedSchemas = $importedSchemas;

		// $taxonomy->loadLinkbases();

		$taxonomy->baseTaxonomy = $baseTaxonomy;

		return $taxonomy;
	}

	/**
	 * Swap the existing labels with the set in $labelSet
	 * @param array $labelSet
	 * @return void
	 */
	private function swapLabels( $labelSet )
	{
		$this->context->labelsBackup = array(
			'labels' => &$this->context->labels,
			'labelLinkRoleRefs' => &$this->context->labelLinkRoleRefs,
			'labelRoleRefs' => &$this->context->labelRoleRefs,
			'labelSource' => $this->context->labelSource,
		);

		$this->context->labels = &$labelSet['labels'];
		$this->context->labelLinkRoleRefs = &$labelSet['labelLinkRoleRefs'];
		$this->context->labelRoleRefs = &$labelSet['labelRoleRefs'];
		$this->context->labelSource = &$labelSet['labelSource'];
	}

	/**
	 * Swap the existing types with the set in $typesSet
	 * @param array $typesSet An array of type information from a persistent store
	 * @return void
	 */
	private function swapTypes( $typesSet )
	{
		$this->context->typesBackup = &$this->context->types;

		$this->context->types = new XBRL_Types();
		$this->context->types->fromArray( $typesSet );
	}

	/**
	 * Replace the current labels with the ones in backup
	 * @return boolean
	 */
	protected function swapTypesFromBackup()
	{
		if ( ! property_exists( $this->context, 'typesBackup' ) ) return false;
		$this->context->types = $this->context->typesBackup;
		return true;
	}

	/**
	 * Replace the current labels with the ones in backup
	 * @return boolean
	 */
	protected function swapLabelsFromBackup()
	{
		if ( ! property_exists( $this->context, 'labelsBackup' ) ) return false;
		$this->swapLabels( $this->context->labelsBackup );
		return true;
	}

	/**
	 * Gets an array containing a list of extra features supported usually by descendent implementation
	 * @param string $feature (optional) If supplied just the array for the feature is returned or all
	 * 									 features.  If supplied and not found an empty array is returned
	 * @return array By default there are no additional features so the array is empty
	 */
	public function supportedFeatures( $feature = null)
	{
		return array();
	}

	/**
	 * This is a special case constructor for the 'main' instance so
	 * so additional processing can be done *after* the schemas have
	 * been loaded
	 *
	 * @param string $json
	 * @param string $compiledFolder (optional)
	 * @return boolean|NULL|XBRL
	 */
	public static function fromJSON( $json, $compiledFolder = null )
	{
		$store = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE )
		{
			$error = XBRL::json_last_error_msg();
			XBRL_Log::getInstance()->err( "$error" );
			return false;
		}

		// Check to see if there is a valid base taxonomy reference $store
		// If there is, this is an extension taxonomy so load it as one.
		if ( isset( $store['baseTaxonomy'] ) && $store['baseTaxonomy'] )
		{
			return XBRL::fromExtensionTaxonomy( $store, $compiledFolder );
		}

		$context = XBRL_Global::getInstance();

		$context->labels				 =& $store['context']['labels'];
		$context->labelLinkRoleRefs		 =& $store['context']['labelLinkRoleRefs'];
		$context->labelRoleRefs			 =& $store['context']['labelRoleRefs'];

		if ( ! empty( $store['context']['calculationRoleRefs'] ) )
		{
			$context->calculationRoleRefs	 =& $store['context']['calculationRoleRefs'];
		}
		if ( ! empty( $store['context']['generalSpecialRoleRefs'] ) )
		{
			$context->generalSpecialRoleRefs =& $store['context']['generalSpecialRoleRefs'];
		}
		if ( ! empty( $store['context']['presentationRoleRefs'] ) )
		{
			$context->presentationRoleRefs	 =& $store['context']['presentationRoleRefs'];
		}
		if ( ! empty( $store['context']['essenceAlias'] ) )
		{
			$context->essenceAlias			 =& $store['context']['essenceAlias'];
		}
		if ( ! empty( $store['context']['requireElements'] ) )
		{
			$context->requireElements		 =& $store['context']['requireElements'];
		}
		if ( ! empty( $store['context']['nonDimensionalRoleRefs'] ) )
		{
			$context->nonDimensionalRoleRefs =& $store['context']['nonDimensionalRoleRefs'];
		}
		if ( ! empty( $store['context']['primaryItems'] ) )
		{
			$context->setPrimaryItemsCache( $store['context']['primaryItems'] );
		}
		if ( ! empty( $store['context']['dimensionDefaults'] ) )
		{
			$context->dimensionDefaults =& $store['context']['dimensionDefaults'];
		}
		if ( ! empty( $store['context']['formulaNames'] ) )
		{
			$context->formulaNames =& $store['context']['formulaNames'];
		}
		if ( ! empty( $store['validTaxonomySet'] ) )
		{
			$context->validTaxonomySet = $store['validTaxonomySet'];
		}
		if ( ! empty( $store['isExtensionTaxonomy'] ) )
		{
			if ( filter_var( $store['isExtensionTaxonomy'], FILTER_VALIDATE_BOOLEAN ) )
			{
				$context->setIsExtensionTaxonomy();
			}
		}

		$instance = null;

		foreach ( $store['schemas'] as $namespace => $data )
		{
			$classname = XBRL::class_from_namespace( $namespace );

			/**
			 * @var XBRL $xbrl
			 */
			$xbrl = new $classname();
			$xbrl->context =& $context;
			$xbrl->fromStore( $data );
			// $xbrl->context->importedSchemas[ $namespace ] = $xbrl;
			// $xbrl->context->schemaFileToNamespace[ $xbrl->getSchemaLocation() ] = $namespace;
			// $xbrl->context->schemaFileToNamespace[ $xbrl->getTaxonomyXSD() ] = $namespace;

			if ( $namespace === $store['mainNamespace'] ) $instance = $xbrl;
		}

		XBRL::fixupForeignDefinitionsFromStore( $store['schemas'] );

		if ( isset( $store['context']['types'] ) )
		{
			// $context->types->fromArray( $store['context']['types'] );
			$context->types->mergeTypes( $store['context']['types'] );
		}

		return $instance;
	}

	/**
	 * The
	 * @param array $schemas
	 */
	private static function fixupForeignDefinitionsFromStore( $schemas )
	{
		$context = XBRL_Global::getInstance();
		$mergedRoles = array();

		foreach ( $schemas as $namespace => $data )
		{
			if ( ! isset( $data['foreignDefinitionRoleRefs'] ) || ! $data['foreignDefinitionRoleRefs'] ) continue;

			$schemaTaxonomy = $context->getTaxonomyForXSD( $data['schemaLocation'] );
			foreach( $data['foreignDefinitionRoleRefs'] as $definitionRoleRefKey => $definitionRoleRef )
			{
				$home_taxonomy = $context->getTaxonomyForXSD( $definitionRoleRef['href'] );
				if ( ! $home_taxonomy )
				{
					$ex = new \Exception("Unable to locate taxonomy instance for '{$definitionRoleRef['href']}'");
					// error_log($ex->getTraceAsString());
					throw $ex;
				}
				$roleRef =& $home_taxonomy->getDefinitionRoleRef( $definitionRoleRef['roleUri'] );
				$roleRef = $schemaTaxonomy->mergeExtendedRoles( $roleRef, $definitionRoleRef, $mergedRoles, false );
				$context->setPrimaryItemsCache( null );
			}
		}
	}

	/**
	 * See if there are compiled files for the base name
	 * @param string $compiledDir
	 * @param string $basename
	 * @return false|string
	 */
	public static function isCompiled( $compiledDir, $basename )
	{
		$extensions = array( 'json', 'zip' );
		// Make sure the dir ends in /
		$compiledDir = rtrim( $compiledDir, '/' ) . "/";

		foreach ( $extensions as $extension )
		{
			$compiledFile = "$compiledDir$basename.$extension";
			if ( ! file_exists( $compiledFile ) ) continue;
			return $compiledFile;
		}

		return false;
	}

	/**
	 * Compile a taxonomy into an a collection of arrays then convert to JSON and save to a file.
	 *
	 * @param string $taxonomy_file The name of the taxonomy file (xsd) to load
	 * @param string $namespace The namespace of the taxonomy imported by the one being loaded that is to be returned.
	 * @param string $output_basename A name to use as the base for output files. 'xxx' will result in 'xxx.zip' and 'xxx.json' output files
	 * @param bool $prettyPrint (Default: false)
	 * @return false|XBRL <false, XBRL>
	 */
	public static function compile( $taxonomy_file, $namespace = null, $output_basename = null, $prettyPrint = false )
	{
		$xbrl = XBRL::load_taxonomy( $taxonomy_file, true );
		if ( $xbrl === false )
		{
			XBRL_Log::getInstance()->err( "Taxonomy failed to load" );
			return false;
		}

		$taxonomy = $namespace === null ? $xbrl : $xbrl->getTaxonomyForNamespace( $namespace );

		$xbrl->saveTaxonomy( $namespace, $output_basename, $prettyPrint );

		return $taxonomy;
	}

	/**
	 *
	 * @param XBRL $taxonomy
	 * @param string $namespace
	 * @param string $output_basename
	 * @param bool $prettyPrint
	 */
	public function saveTaxonomy( $namespace = null, $output_basename = null, $prettyPrint = false  )
	{
		if ( $output_basename === null )
		{
			$output_basename = $this->compiled_taxonomy_for_xsd( $this->getTaxonomyXSD() );
		}

		if ( ! $output_basename )
		{
			XBRL_Log::getInstance()->info( "An output file base name has not been provided." );
		}
		else
		{
			// Attempt to free memory
			gc_collect_cycles();

			$json = $this->toJSON( null, $prettyPrint );

			file_put_contents( "$output_basename.json", $json );
			$zip = new ZipArchive();
			$zip->open( "$output_basename.zip", ZipArchive::CREATE );
			$date = date( "Y-m-d" );
			$zip->addFile( "$output_basename.json", basename( "$output_basename.json" ) );

			if ( $zip->close() === false )
			{
				XBRL_Log::getInstance()->err( "Error closing zip file" );
				XBRL_Log::getInstance()->err( $zip->getStatusString() );
			}
			else
			{
				copy( "$output_basename.zip", "{$output_basename}_{$date}.zip" );
			}
		}

	}

	/**
	 * Tests whether the node represents a tuple
	 * @param array $taxonomy_element An XBRL element node
	 * @param XBRL_Types $types (optional) an instance of the XBRL_types class
	 * @return boolean True|False
	 */
	public static function isTuple( $taxonomy_element, $types = null )
	{
		if ( $types == null ) $types = XBRL_Types::getInstance();

		return isset( $taxonomy_element['substitutionGroup'] ) &&
		$types->resolveToSubstitutionGroup( $taxonomy_element['substitutionGroup'], array( XBRL_Constants::$xbrliTuple ) );
	}

	/**
	 * Return false if the node should not be displayed.  May delegate to the taxonomy instance (default: true)
	 * @param array $node
	 * @return bool
	 */
	public function displayNode( $node )
	{
		return true;
	}

	/**
	 * Returns the value of $elemment formatted according to the type defined in the taxonomy
	 * @param array $element A representation of an element from an instance document
	 * @param XBRL_Instance $instance An instance of an instance class related to the $element
	 * @param bool $includeCurrency True if the returned monetary value should include a currency symbol
	 * @return mixed
	 */
	public function formattedValue( $element, $instance = null, $includeCurrency = true )
	{
		$value = $element['value'];
		$type = isset( $element['taxonomy_element'] ) ? $element['taxonomy_element']['type'] : "";

		switch ( $type )
		{

			case 'xbrli:durationItemType':
				// This will be an ISO8601 formatted value.  If this begins with a number then is a date. If it starts with a P then its a duration
				if ( is_numeric( $value[0] ) )
				{
					return $value;
				}

				$interval = new XBRL_DateInterval( $value );
				return $interval->formatWithoutZeroes( '%y year(s)', '%m month(s)', '%d day(s)', '%h hour(s)', '%i minute(s)', '%s second(s)' );
				break;

			case 'xbrli:integerItemType':
				$formatter = new NumberFormatter( $this->context->locale, NumberFormatter::DEFAULT_STYLE );

				if ( ! isset( $element['decimals'] ) || $element['decimals'] === 'INF' || $element['decimals'] <= 0 )
				{
					$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, 0 );
				}
				else
				{
					$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, $element['decimals'] );
				}

				if ( isset( $element['decimals'] ) && $element['decimals'] < 0 )
				{
					$value = round( $value, $element['decimals'] );
					$x = str_pad( "", abs( $element['decimals'] ), "0" );
					$value = str_replace( $x , "", $value );
				}

				return $formatter->format( $value );
				break;

			case 'num:percentItemType':

				/*
				if ( $instance !== null && isset( $element['unitRef'] ) && ($unitRef = $instance->getUnit( $element['unitRef'] ) ) !== null )
				{
					$parts = explode( ':', $unitRef );
					if ( count( $parts ) > 1 )
					{
						$namespace = $instance->getNamespaceForPrefix( $parts[0] );
						if ( $namespace === XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )
						{
							switch ( $parts[1] )
							{
								case 'pure':
									return "$value%";
							}
						}
					}
				}
				*/

				$formatter = new NumberFormatter( $this->context->locale, NumberFormatter::PERCENT );
				$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, 2 );
				return $formatter->format( $value );

			case 'xbrli:dateItemType':
			case 'xbrli:stringItemType':
				return $value;

			case 'xbrli:sharesItemType':
				$formatter = new NumberFormatter( $this->context->locale, NumberFormatter::DEFAULT_STYLE );

				if ( ! isset( $element['decimals'] ) || $element['decimals'] === 'INF' || $element['decimals'] <= 0 )
				{
					$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, 0 );
				}
				else
				{
					$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, $element['decimals'] );
				}

				if ( isset( $element['decimals'] ) && $element['decimals'] < 0 )
				{
					$value = round( $value, $element['decimals'] );
					$x = str_pad( "", abs( $element['decimals'] ), "0" );
					$value = str_replace( $x , "", $value );
				}

				return $formatter->format( $value );

			case 'xbrli:booleanItemType':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? "True" : "False";

			case 'num:perShareItemType':
				$formatter = new NumberFormatter( $this->context->locale, $includeCurrency ? NumberFormatter::CURRENCY : NumberFormatter::DECIMAL );
				if ( ! isset( $element['decimals'] ) || $element['decimals'] === 'INF' )
				{
					$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, strlen( $value ) -2 );
				}
				else
				{
					$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, $element['decimals'] );
				}
				return $includeCurrency
					? $formatter->formatCurrency( $value, $instance->getDefaultCurrency() )
					: $formatter->format( $value );

			case XBRL_Constants::$xbrliMonetaryItemType:
				if ( $instance !== null && isset( $element['unitRef'] ) && ($unitRef = $instance->getUnit( $element['unitRef'] ) ) !== null )
				{
					try
					{
						if ( empty( $value ) )
						{
							$value = 0;
						}
						else
						{
							$locale = locale_get_default();
							setlocale( LC_ALL, str_replace('_', '-', $this->context->locale ) );
							$thousandsSep = localeconv()['thousands_sep'];
							setlocale( LC_ALL, str_replace('_', '-', $locale ) );

							if ( $thousandsSep && strpos( $value, $thousandsSep ) !== false )
							{
								$value = str_replace( $thousandsSep, '', $value );
							}
						}
						// Lookup the format
						$parts = isset( $element['format'] ) ? array_filter( explode( ':', $element['format'] ) ) : array();
						if ( count( $parts ) > 1 )
						{
							$namespace = $instance->getNamespaceForPrefix( $parts[0] );
							if ( $namespace === 'http://www.xbrl.org/inlineXBRL/transformation/2011-07-31' )
							{
								switch ( $parts[1] )
								{
									case 'zerodash':
										if ( $value === '-' ) $value = "0";
										break;
								}
							}
						}

						if ( isset( $element['sign'] ) && $element['sign'] === '-' )
						{
							$value *= -1;
						}

						$decimal = 0;
						$divisionFactor = 1;
						$suffix = '';

						// The number of decimal places gives an indication about how the number should be formatted
						if ( isset( $element['decimals'] ) && $element['decimals'] !== 'INF' )
						{
							$divisionFactor = $this->getDisplayRoundingFactor( $element['decimals'] );

							if ( $element['decimals'] >= 0 )
							{
								$decimal = $element['decimals'];
							}
							else
							{
								$divisionFactorLen = $divisionFactor['power'];
								$suffix = $divisionFactor['label'];

								// If the decimal accuracy is not as great as the rounding then there are decimal places
								if ( $divisionFactorLen > abs( $element['decimals'] ) )
								{
									$decimal = $divisionFactorLen - abs( $element['decimals'] );
								}
							}
						}

						$formatter = new NumberFormatter( $this->context->locale, $includeCurrency ? NumberFormatter::CURRENCY : NumberFormatter::DECIMAL  );
						$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, $decimal );
						if ( $divisionFactor > 1 )
						{
							// $value = round( $value, $element['decimals'] );
							$value = round( $value / $divisionFactor['divisor'], $decimal );
							// $value = substr( $value, 0, $element['decimals'] );
						}

						// Lookup the unit ref
						$parts = explode( ':', $unitRef );
						$currencyCode = count( $parts ) === 2 && $parts[0] === STANDARD_PREFIX_ISO4217 ? $parts[1] : $instance->getDefaultCurrency();
						$result = $includeCurrency
							? $formatter->formatCurrency( $value, $currencyCode ) . $suffix
							: $formatter->format( $value );
						return $result;
					}
					catch(Exception $ex)
					{
						print_r( $element );
						exit;
					}
				}
				else
				{
					$formatter = new NumberFormatter( $this->context->locale, NumberFormatter::DECIMAL );
					return $formatter->format( $value );
				}

			case 'xbrli:decimalItemType':
				$formatter = new NumberFormatter( $this->context->locale, NumberFormatter::DECIMAL );
				return $formatter->format( $value );

			default:
				return $value;

		}
	}

	/**
	 * cache for an instance
	 * @var NumberFormatter $numberFormatter
	 */
	private $numberFormatter = null;

	/**
	 * Return the value of the element after removing any formatting.
	 * This base implementation will return the naked value.  It is expected
	 * descendants will provide specific implementations.
	 * @param array $element
	 * @return float
	 */
	public function removeNumberValueFormatting( $element )
	{
		if ( empty( $element['value'] ) ) $element['value'];

		if ( is_null( $this->numberFormatter ) )
		{
			$this->numberFormatter = new NumberFormatter( $this->getLocale(), NumberFormatter::DECIMAL );
		}

		return $this->numberFormatter->parse( $element['value'], NumberFormatter::TYPE_DOUBLE );
	}

	/**
	 * An array of callables with parameters $type
	 * @var array
	 * @return false|string
	 */
	private static $valueAlignmentForTypeRegistry = array();

	/**
	 * Add a callable to process value alignments
	 * @param callable $valueAlignmentForTypeeFunction
	 */
	public static function registerValueAlignmentForType( callable $valueAlignmentForTypeFunction )
	{
		self::$valueAlignmentForTypeRegistry[] = $valueAlignmentForTypeFunction;
	}

	/**
	 * Gets the alignment for the element based on the type
	 * @param string $namespace
	 * @param string $name
	 * @return string The alignment to use
	 */
	public function valueAlignmentForNamespace( $namespace, $name )
	{
		$prefix = "";

		// Normalize the prefix
		switch ( $namespace )
		{
			// case "http://www.xbrl.org/dtr/type/numeric":
			case XBRL_Constants::$dtrNumeric:
				$prefix = "num";
				break;

			// case "http://www.xbrl.org/dtr/type/non-numeric":
			case XBRL_Constants::$dtrNonnum:
				$prefix = "nonnum";
				break;

			case XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ]:
				$prefix = STANDARD_PREFIX_XBRLI;
				break;

			default:
				$tax = $this->getTaxonomyForNamespace( $namespace );
				if ( $tax )
				{
					$prefix = $tax->getPrefix();
				}
				else
				{
					$this->log()->warning( "valueAlignmentForNamespace: Need to handle '$namespace'" );
				}
				break;
		}

		$type = "$prefix:$name";

		// Give a value registry entry a chance
		foreach ( self::$valueAlignmentForTypeRegistry as $callback )
		{
			if ( ! is_callable( $callback ) ) continue;
			$result = $callback( $type );
			if ( $result ) return $result;
		}

		// Otherwise use an alignment based on type
		return $this->context->types->resolvesToBaseType( $type, array( 'xs:decimal' ) )
			? 'right'
			: 'left';
	}

	/**
	 * Gets the alignment for the element based on the type
	 * @param string $type The type of the element
	 * @param XBRL_Instance $instance
	 * @return string The alignment to use
	 */
	public function valueAlignment( $type, $instance )
	{
		$parts = explode( ':', $type );
		if ( count( $parts ) < 2 )
		{
			return $this->valueAlignmentForNamespace( null, null, $type );
		}

		$namespace = $instance->getInstanceTaxonomy()->getNamespaceForPrefix( $parts[0] );
		if ( ! $namespace ) $namespace = $instance->getNamespaceForPrefix( $parts[0] );

		return $this->valueAlignmentForNamespace( $namespace, $parts[1] );
	}

	/**
	 * Return the details of a linkbase if it exists or null
	 * @param string $linkbaseName
	 * @return NULL|array
	 */
	public function getLinkbase( $linkbaseName )
	{
		return isset( $this->linkbases[ basename( $linkbaseName ) ] )
			? $this->linkbases[ basename( $linkbaseName ) ]
			: null;
	}

	/**
	 * Allow a caller to access the namespaces in the taxonomy document
	 * @return array
	 */
	public function getDocumentNamespaces()
	{
		if ( ! $this->documentPrefixes )
		{
			if ( ! property_exists( $this, 'xbrlDocument' ) ) return null;
			$this->documentPrefixes = $this->xbrlDocument->getDocNamespaces( true );
		}

		return $this->documentPrefixes;
	}

	/**
	 * Get the instance namespace for a specific prefix
	 * @param string $prefix
	 * @return string The corresponding namespace or null
	 */
	public function getNamespaceForPrefix( $prefix )
	{
		// Initialize $this->documentPrefixes
		$this->getDocumentNamespaces();

		if ( ! isset( $this->documentPrefixes[ $prefix ] ) )
		{
			// Look for prefix in imported taxonmies
			$taxonomies = array_filter( $this->context->importedSchemas, function( $xbrl ) use ( $prefix ) { return $xbrl->getPrefix() == $prefix; } );
			return $taxonomies ? key( $taxonomies ): null;
		}
		return $this->documentPrefixes[ $prefix ];
	}

	/**
	 * Returns true if the element value with the $key is defined as one to display as text
	 * Can be overridden in a descendent.
	 * @param string $key The key to lookup to determine whether the row should be treated as text
	 * @param string $type The type of the element
	 * @return boolean Defaults to false
	 */
	public function treatAsText( $key, $type )
	{
		return false;
	}

	/**
	 * Returns true if the element value with the $key is defined as one that should be used as a label - usually in tuple
	 * Can be overridden in a descendent.
	 * @param string $key The key to lookup to determine whether the row should be treated as a label
	 * @return boolean Defaults to false
	 */
	public function treatAsLabel( $key )
	{
		return false;
	}

	/**
	 * Returns true if instance documents associated with the taxonomy normally provide opening balances.
	 * If they do not, then a user of the taxonomy knows to compute an opening balance from available information.
	 * Override in a descendent implementation.
	 * @return boolean
	 */
	public function openingBalancesSupplied()
	{
		return false;
	}

	/**
	 * Provides a mechanism to sort the presentation refs.  Can be overridden in descendent classes.
	 * @param array $roleRefs
	 * @return void
	 */
	public function sortRoleRefs( &$roleRefs )
	{
		uasort( $roleRefs, function( $a, $b ) { return strcmp( $a['text'], $b['text'] ); } );
	}

	/**
	 * Public accessor for the element arc type defintions
	 */
	public function getElementArcTypes()
	{
		return $this->elementArcTypes;
	}

	/**
	 * Get the division factor for an @decimals value
	 * @param int $decimal An index into the $displayRoundings array
	 * @return int The division factor to use
	 */
	public function getDisplayRoundingFactor( $decimal )
	{
		// Simple chcck
		if ( ! is_numeric( $decimal ) || $decimal == 0 ) return 1;

		// Note: using '==' so the array value is coerced to zero
		if ( ! isset( $this->displayRoundings[ $decimal ] ) || $this->displayRoundings[ $decimal ] == 0 )
		{
			$this->setDisplayRoundingFactor( $decimal, abs( $decimal ) );
		}

		return $this->displayRoundings[ $decimal ];
	}

	/**
	 * Add or change an entry in the $displayRoundings array
	 * @param int $decimal
	 * @param int $power
	 * @param string $label
	 */
	public function setDisplayRoundingFactor( $decimal, $power, $label = '' )
	{
		$this->displayRoundings[ $decimal ] = array(
			'divisor' => $decimal >= 0 ? 1 : pow( 10, $power ),
			'power' => $power,
			'label' => $label,
		);
	}

	/**
	 * Get the taxonomy prefix
	 * @return string
	 */
	public function getPrefix()
	{
		return $this->prefix;
	}

	/**
	 * Default contructor
	 */
	public function __construct() {}

	function __destruct()
	{
		unset( $this->context );
		unset( $this->xbrlDocument );
		unset( $this->elementIndex );
		unset( $this->tupleMembersIndex );
		unset( $this->elementHypercubes );
		unset( $this->elementDimensions );
		unset( $this->elementLinkTypes );
		unset( $this->elementArcTypes );
		unset( $this->customRoles );
		unset( $this->genericRoles );
		unset( $this->complexTypes );
		unset( $this->arcroleTypes );
		unset( $this->arcroleTypeIds );
		unset( $this->roleTypes );
		unset( $this->roleTypeIds );
		unset( $this->linkbaseTypes );
		unset( $this->definitionRoleRefs );
		unset( $this->foreignDefinitionRoleRefs );
		unset( $this->referenceRoleRefs );
		unset( $this->documentPrefixes );
		unset( $this->baseTaxonomy );
		unset( $this->displayRoundings );
		unset( $this->importedFiles );
		unset( $this->includedFiles );
		unset( $this->linkbaseRoleTypes );
		unset( $this->xdtTargetRoles );
		unset( $this->extraElements );
		unset( $this->customArcsAdded );
		unset( $this->variableSetNames );
		unset( $this->linkbaseIds );
		unset( $this->linkbases );
		unset( $this->enumerations );
	}

	/**
	 * Find the node in $nodes that has the path $path.  If the node is located call $callback
	 *
	 * @param array $nodes
	 * @param string $path
	 * @param Closure $callback
	 * @return boolean
	 */
	public function processNode( &$nodes, $path, $callback = null )
	{
		// This is working variable to hold the 'current node'
		if ( isset( $pathNode ) ) unset( $pathNode );

		$pathsParts = explode( '/', $path );
		for ( $i = 0; $i < count( $pathsParts ); $i++ )
		{
			// Should do error checking here.  There's lots to go wrong.
			// Working down the hierarchy using the path components.
			$pathNode =& $nodes[ $pathsParts[ $i ] ];

			// Get the text for the node if not already retrieved
			// if ( ! isset( $pathNode['text'] ) )
			// {
				// $element_xsd = parse_url( $pathNode['label'], PHP_URL_PATH );
				// $key = "$element_xsd#{$pathNode['taxonomy_element']['id']}";

				// $taxonomy = $this->getTaxonomyForXSD( $pathNode['label'] );
				// $key = $pathNode['label'];

				// $pathNode['text'] = $this->getTaxonomyDescriptionForIdWithDefaults(
				// 	$key,
				// 	isset( $pathNode['preferredLabel'] ) ? $pathNode['preferredLabel'] : null,
				// 	$this->getDefaultLanguage()
				// );

				// if ( ! $pathNode['text'] )
				// {
				// 	$pathNode['text'] = $pathNode['label'];
				// }
			// }

			$nodes =& $pathNode['children'];
		}

		// $pathnode is now the node to which to add the values
		if ( ! $pathNode ) return false; // Something went wrong

		if ( ! empty( $callback ) )
			$callback( $pathNode );

		return true;
	}

	/**
	 * Return a default for the language code. Can be overridden.
	 */
	public function getDefaultLanguage()
	{
		if ( self::$specificLanguage ) return self::$specificLanguage;
		return 'en-US';
	}

	/**
	 * Caches the language labels array once created
	 */
	private $languageLabelsCache = null;

	/**
	 * Returns a list of the languages in the set of labels
	 */
	public function getLabelLanguages()
	{
		if ( ! is_null( $this->languageLabelsCache ) )
		{
			return $this->languageLabelsCache;
		}

		$languages = array();
		foreach ( $this->context->labels as $linkRole => $linkLabels )
		{
			foreach ( $linkLabels['labels'] ?? array() as $labelRole => $languageLabels )
			{
				$languages = array_unique( array_merge( $languages, array_keys( $languageLabels ) ) );
			}
		}

		$this->languageLabelsCache = $languages;

		return $languages;
	}

	/**
	 * Return an array of the preferred labels used.
	 * @param string $extendedLinkRole
	 * @return string[]
	 */
	public function getPreferredLabelRoles( $extendedLinkRole = null )
	{
		if ( $extendedLinkRole )
		{
			if ( ! isset( $this->context->labels[ $extendedLinkRole ] ) ) return array();
			return array_keys( $this->context->labels[ $extendedLinkRole ]['labels'] );
		}

		$roles = array();

		foreach ( $this->context->labels as $linkRole => $linkLabels )
		{
			$roles = array_merge( $roles, array_keys( $linkLabels['labels'] ) );
		}

		return array_unique( $roles );
	}

	public function hasLanguage( $lang )
	{
		return in_array( $lang, $this->getLabelLanguages() );
	}

	public function array_any( &$array, $callback )
	{
		foreach ( $array as $key => $item )
		{
			if ( $callback( $key, $item ) ) return true;
		}

		return false;
	}

	/**
	 * Return true if there exists a label for the language, preferred role and extended link role.
	 * @param string $href
	 * @param string $lang
	 * @param string $role (optional) Defaults to XBRL_Constants::$labelRoleLabel
	 * @param string $extendedLinkRole (optional) Defaults to XBRL_Constants::$defaultLinkRole
	 * @return bool
	 */
	public function conceptHasLanguageRole( $href, $lang, $role, $extendedLinkRole = null )
	{
		if ( is_null( $extendedLinkRole ) ) $extendedLinkRole = XBRL_Constants::$defaultLinkRole;
		if ( is_null( $role ) ) $role = XBRL_Constants::$labelRoleLabel;

		if ( ! isset( $this->context->labels[ $extendedLinkRole ]['arcs'][ $href ] ) ) return false;
		return $this->array_any( $this->context->labels[ $extendedLinkRole ]['arcs'][ $href ], function( $label, $labels ) use ( $role )
		{
			return $this->array_any( $labels, function( $key, $details ) use( $role ) { return $details['role'] == $role; } );
		} );
	}

	/**
	 *
	 * @param string $href
	 * @param string $extendedLinkRole (optional) Defaults to XBRL_Constants::$defaultLinkRole
	 * @return string[]
	 */
	public function getConceptLabelRoles( $href, $extendedLinkRole = null )
	{
		if ( is_null( $extendedLinkRole ) ) $extendedLinkRole = XBRL_Constants::$defaultLinkRole;
		if ( ! isset( $this->context->labels[ $extendedLinkRole ]['arcs'][ $href ] ) ) return false;

		return array_reduce( $this->context->labels[ $extendedLinkRole ]['arcs'][ $href ], function( $carry, $labels )
		{
			$roles = array_merge( $carry, array_reduce( $labels, function( $carry, $details )
			{
				$carry[] = $details['role'];
				return $carry;
			}, array() ) );
			return $roles;
		}, array() );
	}

	/**
	 * Provide access to the linkbaseTypes array
	 */
	public function getLinkbaseTypes()
	{
		return $this->linkbaseTypes;
	}

	/**
	 * Provide access to the linkbaseRoleTypes array
	 */
	public function getLinkbaseRoleTypes()
	{
		return $this->linkbaseRoleTypes;
	}

	/**
	 * Provide access to the roleTypes array
	 * @param $href string|array The href is likely to come from a locator and can be the string or an array produced by parse_url.
	 * @return array An array of roleTypes corresponding to the taxonomy implied by the $href
	 */
	public function getRoleTypes( $href = null )
	{
		if ( $href === null )
			return $this->roleTypes;
		else
		{
			$taxonomy = $this->getTaxonomyForXSD( $href );
			return ( $taxonomy === false )
				? false
				: $taxonomy->getRoleTypes();
		}
	}

	/**
	 * Provide access to the arcroleTypes array
	 * @param $href string|array The href is likely to come from a locator and can be the string or an array produced by parse_url.
	 * @return array An array of arcroleTypes corresponding to the taxonomy implied by the $href
	 */
	public function getArcroleTypes( $href = null )
	{
		if ( $href === null )
			return $this->arcroleTypes;
		else
		{
			$taxonomy = $this->getTaxonomyForXSD( $href );
			return ( $taxonomy === false )
				? false
				: $taxonomy->getArcroleTypes();
		}
	}

	/**
	 * Get the Hypercubes for a presentation role
	 * @param string $roleKey Find hypercubes for a role with this key
	 */
	public function getPresentationRoleHypercubes( $roleKey )
	{
		/**
		 * @var array $refs
		 */
		$refs = $this->context->presentationRoleRefs;

		if ( ! isset( $refs[ $roleKey ] ) ) return array();
		return isset( $refs[ $roleKey ]['hypercubes'] )
			? $refs[ $roleKey ]['hypercubes']
			: array();
	}

	/**
	 * Provide access to the private presentationRoleRefs array
	 * @param array[string]|null $filter
	 * @param boolean $sort
	 * @param string $lang a locale to use when returning the text. Defaults to null to use the default.
	 * @return array
	 */
	public function &getPresentationRoleRefs( $filter = array(), $sort = true, $lang = null )
	{
		// Make sure the filter is initialized and contains lowercase values
		if ( ! is_array( $filter ) ) $filter = array();
		$filter = array_flip( array_map( 'strtolower', $filter ) );

		/**
		 * @var array $refs
		 */
		$refs = $this->context->presentationRoleRefs;
		/**
		 * @var array $result
		 */
		$result = array();

		foreach ( $refs as $refKey => $ref )
		{
			// Apply the filter if one is provided
			if ( count( $filter ) && ! isset( $filter[ strtolower( $refKey ) ] ) ) continue;

			// The presentation hierarchy may be implemented in another taxonomy
			if ( isset( $ref['hierarchy'] ) )
			{
				$result[ $refKey ]  = $ref;
			}
			else
			{
				$result[ $refKey ] = $this->context->presentationRoleRefs[ $refKey ];
			}

			$result[ $refKey ]['text'] = $this->getPresentationLinkRoleDescription( $ref, $lang );
		}

		if ( $sort )
		{
			$this->sortRoleRefs( $result );
		}

		return $result;
	}

	/**
	 * Return a list of all roles with their taxonomy xsd using a cache if available
	 * @param array $rebuildCache
	 * @return array A list of roles
	 */
	public function getAllDefinitionRoles( $rebuildCache = false )
	{
		if ( $rebuildCache || is_null( $this->context->definitionLinkRolesCache ) )
		{
			$definitionLinkRoles = array();

			// Time to build the cache
			foreach ( $this->context->importedSchemas as $id => $taxonomy )
			{
				$roles = $taxonomy->getDefinitionRoleRefs();

				foreach ( $roles as $roleUri => $role )
				{
					if ( count( $role ) <= 3 || isset( $definitionLinkRoles[ $roleUri ] ) ) continue;
					$definitionLinkRoles[ $roleUri ] = $taxonomy->getSchemaLocation();
				}
			}

			$this->context->definitionLinkRolesCache = $definitionLinkRoles;
		}

		return $this->context->definitionLinkRolesCache;

	}

	/**
	 * Provide access to the definitionRole References array
	 * @return array
	 */
	public function &getDefinitionRoleRefs()
	{
		if ( ! isset( $this->definitionRoleRefs ) )
			return false;

		return $this->definitionRoleRefs;
	}

	/**
	 * Returns an extended link array for a role.  If the extended link $this taxonomy
	 * is only reference to underlying extended link then the role is resolved and the
	 * underlying one is returned.
	 *
	 * @param string $roleUri
	 * @return array An array containing the resolved extended link for the role
	 */
	public function &getDefinitionRoleRef( $roleUri )
	{
		$defaultResult = array();

		if ( isset( $this->definitionRoleRefs[ $roleUri ] ) && count( $this->definitionRoleRefs[ $roleUri ] ) > 3 )
		{
			return $this->definitionRoleRefs[ $roleUri ];
		}

		$roles = $this->getAllDefinitionRoles();
		if ( ! isset( $roles[ $roleUri ] ) )
		{
			return $defaultResult;
		}

		$taxonomy = $this->getTaxonomyForXSD( $roles[ $roleUri ] );
		return $taxonomy->getDefinitionRoleRef( $roleUri );


		if ( ! isset( $this->definitionRoleRefs[ $roleUri ]['href'] ) )
		{
			// If the request is for the default link then look to see if this is defined
			// If the request is not for the default link role then exit
			if ( $roleUri == XBRL_Constants::$defaultLinkRole )
			{
				// If the default link role is not defined then exit
				if ( ! isset( $this->context->defaultLinkHref ) ) return $defaultResult;

				$taxonomy = $this->getTaxonomyForXSD( $this->context->defaultLinkHref );
				if ( ! $taxonomy ) return $defaultResult;
				return $taxonomy->getDefinitionRoleRef( $$roleUrirole );
			}
			else
			{
				// Look for the role in the cache
				if ( ! isset( $this->context->definitionLinkRolesCache[ $roleUri ] ) ) return $defaultResult;

				$taxonomy = $this->getTaxonomyForXSD( $this->context->definitionLinkRolesCache[ $roleUri ] );
				if ( ! $taxonomy ) return $defaultResult;
				return $taxonomy->getDefinitionRoleRef( $roleUri );
			}
		}

		// Use a regular expression to look for the current taxonomy at the end of the href but before a fragment if one exists
		if ( preg_match( '/.*(' . $this->getTaxonomyXSD() . ')($|#.*$)/', $this->definitionRoleRefs[ $roleUri ]['href'] ) )
		{
			return $this->definitionRoleRefs[ $roleUri ];
		}

		$taxonomy = $this->getTaxonomyForXSD( $this->definitionRoleRefs[ $roleUri ]['href'] );
		return $taxonomy->getDefinitionRoleRef( $roleUri );
	}

	/**
	 * Set the definitionRole references array
	 * @param array $refs The array with which to update the references
	 */
	public function setDefinitionRoleRefs( $refs )
	{
		$this->definitionRoleRefs = $refs;
	}

	/**
	 * A cache of hypercubes for performance
	 * @var array $hypercubesCache
	 */
	private $hypercubesCache = null;

	/**
	 * Reset the cache of hypercube items
	 * @return void
	 */
	public function ResetHypercubesCache()
	{
		$this->hypercubesCache = null;
	}

	/**
	 * Provide access to the definitionRole Hypercubes array
	 * @return array
	 */
	public function getDefinitionHypercubes()
	{
		if ( $this->hypercubesCache !== null ) return $this->hypercubesCache;

		$hypercubes = array();

		foreach ( $this->getDefinitionRoleRefs() as $roleRefsKey => $role )
		{
			$hypercubes += $this->getDefinitionRoleHypercubes( $roleRefsKey );
		}

		$this->hypercubesCache = $hypercubes;
		return $hypercubes;
	}

	/**
	 * Return the hypercube references for the $role passed
	 * @param string $roleRefsKey The role for which hypercubes should be retrieved
	 * @return array An array of hypercube references
	 */
	public function getDefinitionRoleHypercubes( $roleRefsKey )
	{
		$roles = $this->getAllDefinitionRoles();
		if ( ! isset( $roles[ $roleRefsKey ] ) )
		{
			return array();
		}

		$taxonomy = $this->getTaxonomyForXSD( $roles[ $roleRefsKey ] );
		$role = $taxonomy->getDefinitionRoleRef( $roleRefsKey );

		return isset( $role['hypercubes'] )
			? $role['hypercubes']
			: array();

		/*
		$role = $this->getDefinitionRoleRef( $roleRefsKey );

		if ( ! isset( $role['hypercubes'] ) )
		{
			if ( strpos( $role['href'], $this->getTaxonomyXSD() ) === false )
			{
				return $this->getTaxonomyForXSD( $role['href'] )->getDefinitionRoleHypercubes( $roleRefsKey );
			}
		}
		else
		{
			return  $role['hypercubes'];
		}

		return array();
		*/
	}

	/**
	 * Get the fragment from the value for a specific locator in a role
	 * @param string $roleRefsKey
	 * @param string $locator
	 */
	public function getDefinitionRoleLocatorFragment( $roleRefsKey, $locator )
	{
		$result = $this->getDefinitionRoleLocator( $roleRefsKey, $locator );
		return $result ? parse_url( $result, PHP_URL_FRAGMENT ) : false;
	}

	/**
	 * Get the schema from the value for a specific locator in a role
	 * @param string $roleRefsKey
	 * @param string $locator
	 */
	public function getDefinitionRoleLocatorSchema( $roleRefsKey, $locator )
	{
		$result = $this->getDefinitionRoleLocator( $roleRefsKey, $locator );
		return $result ? parse_url( $result, PHP_URL_PATH ) : false;
	}

	/**
	 * Get the value for a specific locator in a role
	 * @param string $roleRefsKey
	 * @param string $locator
	 */
	public function getDefinitionRoleLocator( $roleRefsKey, $locator )
	{
		$locators = $this->getDefinitionRoleLocators( $roleRefsKey );
		return $locators && isset( $locators[ $locator ] ) ? $locators[ $locator ] : false;
	}

	/**
	 * Get the locators associated with this role
	 * @param string $roleRefsKey
	 */
	public function getDefinitionRoleLocators( $roleRefsKey )
	{
		if ( ! isset( $this->definitionRoleRefs[ $roleRefsKey ] ) ) return array();
		$role = $this->definitionRoleRefs[ $roleRefsKey ];
		if ( strpos( $role['href'], $this->getTaxonomyXSD() ) === false )
		{
			return $this->getTaxonomyForXSD( $role['href'] )->getDefinitionRoleLocators( $roleRefsKey );
		}
		return isset( $role['locators'] ) ? $role['locators'] : array();
	}

	/**
	 * Get the xsd of the base taxonomy for the extension taxonomy loaded.  Will be null if the taxonomy is not an extension one.
	 */
	public function getBaseTaxonomy()
	{
		return $this->baseTaxonomy;
	}

	/**
	 * Set the base taxonomy for an extension taxonomy
	 * @param string $xsd
	 */
	public function setBaseTaxonomy( $xsd )
	{
		$this->baseTaxonomy = $xsd;
	}

	/**
	 * A cache of dimensions for performance
	 * @var array $dimensionsCache
	 */
	private $dimensionsCache = null;

	/**
	 * Reset the cache of dimension items
	 * @return void
	 */
	public function ResetDimensionsCache()
	{
		$this->dimensionsCache = null;
	}

	/**
	 * Provide access to the definitionRole dimensions array
	 */
	public function getDefinitionDimensions()
	{
		if ( $this->dimensionsCache !== null ) return $this->dimensionsCache;

		$dimensions = array();
		$taxonomy_base_name = $this->getTaxonomyXSD();

		foreach ( $this->getDefinitionRoleRefs() as $roleRefsKey => $role )
		{
			$dimensions += $this->getDefinitionRoleDimensions( $roleRefsKey );
		}

		$this->dimensionsCache = $dimensions;
		return $dimensions;
	}

	/**
	 * Return the dimension references for the $role passed
	 * @param string $roleRefsKey The role for which dimension should be retrieved
	 * @return array An array of dimensions references
	 */
	public function getDefinitionRoleDimensions( $roleRefsKey )
	{
		$roles = $this->getAllDefinitionRoles();
		if ( ! isset( $roles[ $roleRefsKey ] ) )
		{
			return array();
		}

		$taxonomy = $this->getTaxonomyForXSD( $roles[ $roleRefsKey ] );

		$role = $taxonomy->getDefinitionRoleRef( $roleRefsKey );

		if ( ! isset( $role['dimensions'] ) )
		{
			return array();
		}

		$result = array();

		foreach ( $role['dimensions'] as $dimensionKey => $href )
		{
			if ( ! isset( $result[ $dimensionKey ] ) )
			{
				$result[ $dimensionKey ] = array( 'href' => $href, 'roles' => array( $roleRefsKey ) );
				continue;
			}

			// Add unique roleRefsKey
			if ( in_array( $roleRefsKey, $result[ $dimensionKey ]['roles'] ) ) continue;
			$result[ $dimensionKey ]['roles'][] = $roleRefsKey;
		}

		return $result;

	}

	/**
	 * Provide access to the definitionRole PrimaryItems array
	 * The list returned will include concepts that can be
	 * primary items because of the application of target roles.
	 * But the list of hypercubes represents the total possible
	 * list which may overstate the correct position as these
	 * lists are NOT moderated by the requirements of the XDT
	 * specification (2.4) with respect to target roles.
	 * @param bool $rebuildCache
	 * @return array
	 */
	public function getDefinitionPrimaryItems( $rebuildCache = false )
	{
		if ( ! $rebuildCache && $this->context->hasPrimaryItemsCache() )
		{
			return $this->context->getPrimaryItemsCache();
		}

		$primaryItems = array();

		$roles = $this->getAllDefinitionRoles( true );

		foreach ( $roles as $roleUri => $href )
		{
			$taxonomy = $this->getSchemaLocation() != $href
				? $this
				: $this->getTaxonomyForXSD( $href );

			$rolePrimaryItems = $taxonomy->getDefinitionRolePrimaryItems( $roleUri );

			foreach ( $rolePrimaryItems as $primaryItemKey => $primaryItem )
			{
				$primaryItems[ $primaryItemKey ][ $roleUri ] = $primaryItem;

				// Add unique roleRefsKey
				$primaryItems[ $primaryItemKey ]['roles'][] = $roleUri;
			}
		}

		$this->context->setPrimaryItemsCache( $primaryItems );
		return $primaryItems;
	}

	/**
	 * Variable to hold the transient history of navigated target roles to
	 * prevent recursion when target roles in ELRs reference each other
	 *
	 * @var array
	 */
	private $navigatedTargetRoles = array();

	/**
	 * Provide access to the definitionRole primary items array
	 * @param string $roleRefsKey The name of the role
	 * @param bool $shortcut If true (default: false) To shortcut means to return the raw primary items array
	 * @param array $priorRoles Use to detect recursive lookup of primary items
	 * @return array A list of primary items
	 */
	public function getDefinitionRolePrimaryItems( $roleRefsKey, $shortcut = false, $priorRoles = array() )
	{
		// ! isset( $this->definitionRoleRefs[ $roleRefsKey ] ) ||
		if ( in_array( $roleRefsKey, $priorRoles ) )
		{
			return array();
		}

		$rolePrimaryItems = array();
		$role =& $this->getDefinitionRoleRef( $roleRefsKey );

		if ( ! isset( $role['primaryitems'] ) )
		{
			return array();
		}
		else
		{
			if ( ! $shortcut )
			{
				// Look for target role references
				foreach ( $role['primaryitems'] as $primaryItemId => $primaryItem )
				{
					if ( ! isset( $primaryItem['parents'] ) ) continue;

					foreach ( $primaryItem['parents'] as $parentPrimaryItemId => $parentPrimaryItem )
					{
						if ( ! isset( $parentPrimaryItem['targetRole'] ) )
						{
							continue;
						}

						$targetRole = $this->getDefinitionRoleRef( $parentPrimaryItem['targetRole'] );
						if ( $targetRole )
						{
							// Before merging members make sure the target role members are not really primary items in the main role
							$membersToPromote = array_keys( array_intersect_key( $targetRole['members'], $role['primaryitems'] ) );
							if ( $membersToPromote )
							{
								$this->promoteMembersByRole( $role, $targetRole, $membersToPromote );
							}
						}

						$targetPrimaryItems = $this->getDefinitionRolePrimaryItems( $parentPrimaryItem['targetRole'], $shortcut, array_merge( $priorRoles, array( $roleRefsKey ) ) );

						foreach( $targetPrimaryItems as $targetPrimaryItemId => $targetPrimaryItem )
						{
							if ( $targetPrimaryItemId == $parentPrimaryItemId )
							{
								continue;
							}

							if ( isset( $role['primaryitems'][ $targetPrimaryItemId ] ) )
							{
								// May need to add new parents
								if ( isset( $targetPrimaryItem['parents'] ) )
								{
									if ( isset( $role['primaryitems'][ $targetPrimaryItemId ]['parents'] ) )
									{
										$role['primaryitems'][ $targetPrimaryItemId ]['parents'] = array_merge( $role['primaryitems'][ $targetPrimaryItemId ]['parents'], $targetPrimaryItem['parents'] );
									}
									else
									{
										$role['primaryitems'][ $targetPrimaryItemId ]['parents'] = $targetPrimaryItem['parents'];
									}
								}
								continue;
							}

							// Maybe need here to take only those primary items that are
							// $primaryItemId or have $primaryItemId as a parent

							if ( ! isset( $targetPrimaryItem['parents'][ $primaryItemId ] ) )
							{
								continue;
							}

							$role['primaryitems'][ $targetPrimaryItemId ] = $targetPrimaryItem;

							// $childTargetPrimaryItems = array_filter( $targetRole['primaryitems'], function( $targetPrimaryItem ) use( $targetPrimaryItemId )
							// {
							// 	return isset( $targetPrimaryItem['parents'][ $targetPrimaryItemId ] ) ;
							// } );
                            //
							// foreach ( $childTargetPrimaryItems as $childTargetPrimaryItemId => $childTargetPrimaryItem )
							// {
							// 	if ( isset( $role['primaryitems'][ $childTargetPrimaryItemId ] ) ) continue;
							// 	array_walk( $childTargetPrimaryItem['parents'], function( &$parent, $parentId ) use( $parentPrimaryItem, $targetPrimaryItemId )
							// 	{
							// 		if ( $parentId != $targetPrimaryItemId ) return;
							// 		$parent['targetRole'] = $parentPrimaryItem['targetRole'];
							// 	} );
                            //
							// 	$role['primaryitems'][ $childTargetPrimaryItemId ] = $childTargetPrimaryItem;
							// }

							// continue;

							// Find child nodes and locate descendants recursively
							// Should find multi-level nested primary items
							$getDescendentPrimaryItems = function ( &$primaryItem, $primaryItemId, $targetRoleUri, &$targetRole ) use( &$getDescendentPrimaryItems )
							{
								$childPrimaryItems = array_filter( $targetRole['primaryitems'], function( $primaryItem ) use( $primaryItemId )
								{
									return isset( $primaryItem['parents'][ $primaryItemId ] ) ;
								} );

								$result =& $childPrimaryItems;

								// Still need to handle ones that are in a different target role
								foreach ( $childPrimaryItems as $childPrimaryItemId => $childPrimaryItem )
								{
									array_walk( $childPrimaryItem['parents'], function( &$parent, $parentId ) use( $targetRoleUri, $primaryItemId )
									{
										if ( $parentId != $primaryItemId ) return;
										$parent['targetRole'] = $targetRoleUri;
									} );

									$result = array_merge( $result, $getDescendentPrimaryItems( $childPrimaryItem, $childPrimaryItemId, $targetRoleUri, $targetRole ) );
								}

								return $result;
							};

							$childTargetPrimaryItems = $getDescendentPrimaryItems( $targetPrimaryItem, $targetPrimaryItemId, $parentPrimaryItem['targetRole'], $targetRole );

							foreach ( $childTargetPrimaryItems as $childTargetPrimaryItemId => $childTargetPrimaryItem )
							{
								if ( isset( $role['primaryitems'][ $childTargetPrimaryItemId ] ) ) continue;
								$role['primaryitems'][ $childTargetPrimaryItemId ] = $childTargetPrimaryItem;
							}
						}
					}
				}
			}

			$rolePrimaryItems = $role['primaryitems'];
		}

		if ( $shortcut )
		{
			return $rolePrimaryItems;
		}

		/**
		 * Function to add hypercubes to a primary item taking into account any target roles
		 *
		 * Look at XDT test suite test cases 203- v-39, v-106, v196 which provide complex
		 * examples covering a wide range of examples including recursive target roles
		 *
		 * @param string $id
		 * @param array &$hypercubes
		 * @param string|false $ELR
		 */
		$collectHypercubes = function( $id, &$hypercubes, $ELR = false ) use( &$collectHypercubes, &$rolePrimaryItems, $roleRefsKey, $role )
		{
			// Add any hypercubes
			if ( isset( $rolePrimaryItems[ $id ]['localhypercubes'] ) )
			{
				foreach ( $rolePrimaryItems[ $id ]['localhypercubes'] as $hypercube )
				{
					// Don't override a hypercube added by a descendent
					if ( isset( $hypercubes[ $id ][ $hypercube ] ) )
					{
						continue;
					}

					$closed = isset( $role['hypercubes'][ $hypercube ]['parents'][ $id ]['closed'] )
						? $role['hypercubes'][ $hypercube ]['parents'][ $id ]['closed']
						: false;

					$hypercubes[ $id ][ $hypercube ] = $closed;
				}
			}

			if ( isset( $rolePrimaryItems[ $id ]['parents'] ) )
			{
				$toELR = $ELR ? $ELR : $roleRefsKey;

				foreach( $rolePrimaryItems[ $id ]['parents'] as $parentId => $parent )
				{
					$targetRole = isset( $parent['targetRole'] ) ? $parent['targetRole'] : false;

					// Flag to indicate whether further hypercubes should be accumulated
					$collect = $targetRole
						? ( $ELR
							? ! isset( $rolePrimaryItems[ $id ]['roleUri'] ) || ( /* Target is in same ELR */ $toELR == $targetRole )
							: true )
						: true;

					if ( ! $collect ) // P5 (e.g. 203 v-39)
					{
						continue;
					}

					if ( $ELR !== false )
					{
						if ( $targetRole && $toELR != $roleRefsKey ) // P3 (e.g. 203 v-39)
						{
							if ( isset( $rolePrimaryItems[ $id ]['localhypercubes'] ) && count( $rolePrimaryItems[ $id ]['localhypercubes'] ) )
							{
								foreach ( $rolePrimaryItems[ $id ]['localhypercubes'] as $hypercube )
								{
									if ( isset( $hypercubes[ $id ][ $hypercube ] ) )
									{
										unset( $hypercubes[ $id ][ $hypercube ] );
									}
								}

								if ( ! count( $hypercubes[ $id ] ) )
								{
									unset( $hypercubes[ $id ] );
								}

								// $hypercubes[ $id ] = array_diff( $hypercubes[ $id ], $rolePrimaryItems[ $id ]['localhypercubes'] );
								// if ( ! count( $hypercubes[ $id ] ) )
								// {
								// 	unset( $hypercubes[ $id ] );
								// }
							}
						}
					}

					$collectHypercubes(
						$parentId,
						$hypercubes,
						isset( $rolePrimaryItems[ $id ]['roleUri'] )
							? $rolePrimaryItems[ $id ]['roleUri']
							: $roleRefsKey
					);

				}
			}

		};

		$result = array();

		foreach ( $rolePrimaryItems as $primaryItemId => $primaryItem )
		{
			// Make sure the primary item is populated with the hypercubes of it's parent taking into account
			// any target roles.  If the parent has a target role then any of it's hypercubes are ignored.

			// initialize the list
			$hypercubes = array();

			$collectHypercubes( $primaryItemId, $hypercubes );

			if ( $hypercubes )
			{
				// Make sure these hypercubes have this primary item as a parent
				foreach ( $hypercubes as $hypercubePrimaryItemId => $primaryItemHypercubes )
				{
					foreach( $primaryItemHypercubes as $hypercube => $closed )
					{
						// Check to see if the hypercube has a relationship with this primary item
						if ( ! isset( $role['hypercubes'][ $hypercube ]['parents'][ $primaryItemId ]  ) )
						{
							// Add the hypercube relationship
							$parent = $role['hypercubes'][ $hypercube ]['parents'][ $hypercubePrimaryItemId ];
							$role['hypercubes'][ $hypercube ]['parents'][ $primaryItemId ] = $parent;
						}

						$role['hypercubes'][ $hypercube ]['parents'][ $primaryItemId ]['closed'] = $closed;
					}
				}

				// Reduce the accumulated list back to a simple list of hypercubes
				$hypercubes = array_reduce( $hypercubes, function( $carry, $hypercubes ) {
					$carry = array_unique( array_merge( $carry, array_keys( $hypercubes ) ) );
					return $carry;
				}, array() );

			}

			// Record the role with the hypercube. The role is need to make sure the correct hypercube is
			// selected when creating the DRS. A hypercube of the same name may appear in different
			// extended links and have very different structures in those different extended links. A
			// taxonomy will probably not be implemented this way but its not prohibited.
			// $hypercubes = array( $roleRefsKey => $primaryItem['hypercubes'] );

			if ( isset( $primaryItems[ $primaryItemId ]['href'] ) )
			{
				// Add any additional hypercubes to the primary item for a different role
				// $result[ $primaryItemKey ]['href']['hypercubes'] += $hypercubes;
				$result[ $primaryItemId ]['href']['hypercubes'] += $primaryItem['hypercubes'];
			}
			else
			{
				// Add a primary item using a slightly different structure
				$result[ $primaryItemId ] = array(
					'label' => $primaryItemId,
					'hypercubes' => $hypercubes, // $primaryItem['hypercubes'],
					'roleUri' => $roleRefsKey,
					'parents' => isset( $primaryItem['parents'] ) ? $primaryItem['parents'] : array(),
				);
			}
		}

		return $result;
	}

	/**
	 * A cache of dimension members for performance
	 * @var array $dimensionMembersCache
	 */
	private $dimensionMembersCache = null;

	/**
	 * Reset the cache of dimension members
	 * @return void
	 */
	public function ResetDimensionMembersCache()
	{
		$this->dimensionMembersCache = null;
	}

	/**
	 * Reset the primary, dimension, dimension members and hypercube caches
	 * @return void
	 */
	public function ResetAllItemCaches()
	{
		$this->ResetDimensionsCache();
		$this->ResetDimensionMembersCache();
		$this->ResetHypercubesCache();
	}

	/**
	 * Provide access to the definitionRole dimension members array
	 */
	public function getDefinitionDimensionMembers()
	{
		if ( $this->dimensionMembersCache !== null ) return $this->dimensionMembersCache;

		$dimensionMembers = array();

		foreach ( $this->getDefinitionRoleRefs() as $roleRefsKey => $role )
		{
			$dimensionMembers += $this->getDefinitionRoleDimensionMembers( $roleRefsKey );
		}

		$this->dimensionMembersCache = $dimensionMembers;
		return $dimensionMembers;
	}

	/**
	 * Provide access to the definitionRole dimension members array for a specific role
	 * @param string $roleRefsKey The name of the role
	 * @return array A list of dimension members
	 */
	public function getDefinitionRoleDimensionMembers( $roleRefsKey )
	{
		$roles = $this->getAllDefinitionRoles();

		if ( ! isset( $roles[ $roleRefsKey ] ) )
		{
			return array();
		}

		$taxonomy = $this->getTaxonomyForXSD( $roles[ $roleRefsKey ] );
		$role = $taxonomy->getDefinitionRoleRef( $roleRefsKey );

		return isset( $role['members'] )
			? $role['members']
			: array();

		/*
		$roleMembers = array();
		if ( ! isset( $this->definitionRoleRefs[ $roleRefsKey ] ) ) return array();

		$role = $this->definitionRoleRefs[ $roleRefsKey ];

		if ( isset( $role['members'] ) && count( $role['members'] ) )
		{
			$roleMembers = $role['members'];
		}
		else if ( isset( $role['hypercubes'] ) && count( $role['hypercubes'] ) )
		{
			foreach ( $role['hypercubes'] as $hypercubeLabel => $hypercube )
			{
				foreach ( $hypercube['dimensions'] as $dimLabel => $dimension )
				{
					if ( ! isset( $dimension['targetRole'] ) ) continue;
					$taxonomy = $this->getTaxonomyForNamespace( $dimension['member_namespace'] );
					if ( ! $taxonomy )
					{
						$this->log()->dimension_validation( "dimensions", "A taxonomy cannot be found corresponding to a namespace", array(
							'dimension' => "'$dimLabel'",
							'namespace' => $dimension['member_namespace'] ? "'{$dimension['member_namespace']}'" : "Undefined",
						) );
						continue;
					}
					$roleMembers += $taxonomy->getDefinitionRoleDimensionMembers( $dimension['targetRole'] );
				}
			}

			return $roleMembers;
		}
		else if ( strpos( $role['href'], $this->getTaxonomyXSD() ) === false )
		{
			$roleMembers = $this->getTaxonomyForXSD( $role['href'] )->getDefinitionRoleDimensionMembers( $roleRefsKey );
			return $roleMembers;
		}

		foreach ( $roleMembers as $memberKey => $member )
		{
			if ( ! isset( $roleMembers[ $memberKey ]['roles'] ) )
			{
				$roleMembers[ $memberKey ]['roles'] = $roleRefsKey;
			}
			else
			{
				// Add unique roleRefsKey
				if ( in_array( $roleRefsKey, $roleMembers[ $memberKey ]['roles'] ) ) continue;
				$roleMembers[ $memberKey ]['roles'][] = $roleRefsKey;
			}
		}

		return $roleMembers;

		$result = array();

		foreach ( $roleMembers as $memberKey => $member )
		{
			if ( ! isset( $result[ $memberKey ] ) )
			{
				$result[ $memberKey ] = array( 'href' => $member, 'roles' => array( $roleRefsKey ) );
				continue;
			}

			// Add unique roleRefsKey
			if ( in_array( $roleRefsKey, $result[ $memberKey ]['roles'] ) ) continue;
			$result[ $memberKey ]['roles'][] = $roleRefsKey;
		}

		return $result;
		*/
	}

	/**
	 * Provide access to the definitionRole dimension hierarchy for a specific role
	 * @param string $role The name of the role
	 * @return array A list of primary items
	 */
	public function getDefinitionRoleDimensionMemberHierarchy( $role )
	{
		return ! isset( $this->definitionRoleRefs[ $role ]['hierarchy'] ) ||
				 count( $this->definitionRoleRefs[ $role ]['hierarchy'] ) === 0
			? array()
			: $this->definitionRoleRefs[ $role ]['hierarchy'];
	}

	/**
	 * Provide access to the definitionRole dimension member paths for a specific role
	 * @param string $role The name of the role
	 * @return array A list of primary items
	 */
	public function getDefinitionRoleDimensionMemberPaths( $role )
	{
		return ! isset( $this->definitionRoleRefs[ $role ]['paths'] ) ||
				 count( $this->definitionRoleRefs[ $role ]['paths'] ) === 0
			? array()
			: $this->definitionRoleRefs[ $role ]['paths'];
	}

	/**
	 * Get the default currency
	 */
	public function getDefaultCurrency()
	{
		return "USD";
	}

	/**
	 * Returns True if the $key is for a row that should be excluded.
	 * Can be overridden in a descendent to hide rows.
	 * @param string $key The key to lookup to determine whether the row should be excluded
	 * @param string $type The type of the item being tested (defaults to null)
	 * @return boolean
	 */
	public function excludeFromOutput( $key, $type = null )
	{
		return false;
	}

	/**
	 * Returns an array of locator(s) and corresponding presentation arc(s) that will be substituted for the $from in the $role
	 *
	 * The presentation link base may not suit the data being reported exactly and this mechanism allows an implementer the
	 * ability to modify the presentation link base nodes. For example, the UK GAAP taxonomy allows reporters to specify
	 * addresses for their registered office or third party agents or head office, etc. The different types of address are
	 * idendtified by their respective context definition.  However, although the presentation linkbase has locations for an
	 * address it is not possible to associate a specific address with a node in the hierarchy.  This is because, for some
	 * reason, the link base nodes that identify addresses do not reference primary items that a relevant dimension or
	 * dimension member.
	 *
	 * The array returned by this function is used by the processPresentationLinkbase function to modify the hierarchy
	 * generated so that members of the hierarchy can be used by consumers to present, for example, address information
	 * contained in instance documents in ways not normally possible using the stock presentation link base.
	 *
	 * The returned array will have the following structure:
	 *
	 * locators 	=> Analogous to a <loc> element of a linkbase this is an array of hrefs indexed by label
	 * addarcs  	=> Analogous to a <arc> element it is an array of 'from', 'to', 'order' and 'use' values and the
	 * 				   array contents will be used to create new nodes in the presentation hierarchy.
	 * deletenodes	=> An array of labels that will be used to identify nodes to delete from the presentation hierarchy.
	 * 				   Nodes will only be deleted if they are orphans.
	 * removearcs	=> An array of 'from' and 'to' values to identify an arc that is to be removed.  After removing arcs,
	 * 				   reference children (the to  value) will be orphans
	 *
	 * These elements will be applied in a stricto order:
	 *
	 *	locatators		New locators are added first
	 *	removearcs		Existing arcs are removed to create orphan nodes
	 *	deletenodes		Orphan nodes can be deleted (only orphan nodes can be deleted)
	 *	addarcs			New nodes are added and arcs are created possibly resassing nodes made orphan by the removearcs step
	 *
	 * Can be overridden in a descendent to modify the presentation link base hierarchy.
	 *
	 * @param string $roleUri A role Uri to identify the base presentation link base being modified.
	 * @return array An array of locators and links
	 */
	public function getProxyPresentationNodes( $roleUri )
	{
		return false;
	}

	/**
	 * Creates a summary of a collection of nodes that are arganized in to a hierarchy such as presentation and definition link bases.
	 * The requirements of the collection is that it is an associative array, that there is an element which is the label and one which
	 * is a nested collection of nodes.  This function is called recursively in a depth first manner to traverse the hierarchy.
	 * @param array $nodes The array of nodes to summarize.
	 * @param array $locators An array of locators for the role
	 * @param array options An array of options with the following optional elements:
	 * 	int 'indent' The indentation level for this iteration.
	 * 	string		'labelName'			The name of the element of each node that represents the label to include in the summary
	 * 	string		'collectionName'	The name of the element which references a nested collection of nodes if the node is not a leaf.
	 * 	bool		'description'		If not present or true, include a node decription in the output
	 *	function	'callback'			A function to call for each node.  The function will be passed the node and the taxonomy of the node
	 * 							 		It will return a string that will be added to the description generated.
	 * @return array A hierarchy summarizing the node hierarchy passed in
	 */
	public function summarizeNodes( $nodes, $locators, $options = array() )
	{
		$result = array();

		if ( ! $options || ! is_array( $options ) ) $options = array();
		if ( ! isset( $options['collectionName'] ) ) $options['collectionName'] = 'children';
		if ( ! isset( $options['labelName'] ) ) $options['labelName'] = 'label';
		if ( ! isset( $options['description'] ) ) $options['description'] = 'true';
		$options['indent'] = isset( $options['indent'] ) ? $options['indent'] + 1 : 0;

		$taxonomy_base_name = $this->getTaxonomyXSD();

		foreach ( $nodes as $nodeKey => $node )
		{
			$index = $node[ $options['labelName'] ];
			if ( isset( $node['arcrole'] ) )	$index .= " ({$node['arcrole']})";
			if ( isset( $node['order'] ) )		$index .= " ({$node['order']})";
			if ( isset( $node['targetRole'] ) ) $index .= " ->({$node['targetRole']})";

			//	$this->log()->info(  isset( $locators[ $node[ $labelName ] ] )
			//		? "Locator found {$locators[ $node[ $labelName ] ]}\n"
			//		: "Locator not found" );

			// $taxonomy = strpos( $locators[ $node[ $options['labelName'] ] ], $taxonomy_base_name ) === 0
			//	? $this
			//	: $this->getTaxonomyForXSD( $locators[ $node[ $options['labelName'] ] ] );

			if ( $options['description'] )
			{
				$description = $this->getTaxonomyDescriptionForIdWithDefaults( $node[ $options['labelName'] ], null, $this->getDefaultLanguage() );
				if ( $description !== false )
				{
					$index .= " '$description'";
				}
			}

			if ( isset( $node['nodeclass'] ) )
				$index .= " ({$node['nodeclass']})";

			if ( isset( $options['callback'] ) )
			{
				if ( $callback_string = call_user_func( array( $this, $options['callback'] ), $node, $this ) )
				{
					$index .= " [$callback_string]";
				}
			}

			$leaf = "Leaf";

			$result[ $index ] = $leaf;
			if ( ! isset( $node[ $options['collectionName'] ] ) ) continue;
			$result[ $index ] = $this->summarizeNodes( $node[ $options['collectionName'] ], $locators, $options );
		}

		return $result;
	}

	/**
	 * Creates a summary array containing only the labels of the presentation nodes of just one role or all roles.
	 * Useful to pass through json_encode() to be able to visualize the hierarchy.
	 * @param string|array $roleUri A roleUri to select the specific role hierarch(y|ise) to summarize.  If no argument is passed all role hierarchies will be summarized.
	 * @return array An array of labels still organized into a hierarchy
	 */
	public function getPresentationSummary( $roleUri = null )
	{
		$result = array();

		foreach ( $this->getPresentationRoleRefs() as $refUri => $ref )
		{
			if ( $roleUri !== null && ! ( is_array( $roleUri ) ? in_array( $refUri, $roleUri ) : $roleUri === $refUri ) ) continue;
			if ( ! isset( $ref['hierarchy'] ) ) continue;

			$result[ $refUri ] = $this->summarizeNodes( $ref['hierarchy'], $ref['locators'] );
		}

		return $result;
	}

	/**
	 * Creates a summary array containing only the labels of the definition nodes of just one role or all roles.
	 * Useful to pass through json_encode() to be able to visualize the hierarchy.
	 * @param string|array $roleUri A roleUri to select the specific role hierarch(y|ise) to summarize. If no argument is passed all role hierarchies will be summarized.
	 * @return array An array of labels still organized into a hierarchy
	 */
	public function getDefinitionSummary( $roleUri = null )
	{
		$result = array();

		foreach ( $this->getDefinitionRoleRefs() as $refUri => $ref )
		{
			if ( $roleUri !== null && ! ( is_array( $roleUri ) ? in_array( $refUri, $roleUri ) : $roleUri === $refUri ) ) continue;
			if ( ! isset( $ref['hierarchy'] ) ) continue;

			$result[ $refUri ] = $this->summarizeNodes( $ref['hierarchy'], $ref['locators'] );
		}

		return $result;
	}

	/**
	 * Returns true if the id is one for an arcrole type
	 * @param mixed $id
	 * @return  bool
	 */
	public function hasArcRoleTypeId( $id )
	{
		return isset( $this->arcroleTypeIds[ $id ] );
	}

	/**
	 * Return the arcrole type string or false
	 * @param string $id
	 * @return boolean|mixed
	 */
	public function getArcRoleTypeForId( $id )
	{
		if ( ! $this->hasArcRoleTypeId( $id ) ) return false;
		return $this->arcroleTypeIds[ $id ];
	}

	/**
	 * Returns true if the $root contains an element with a has-hypercube arcrole
	 * @param array $root An array representing a node to be tested
	 */
	private function hasHypercube( $root )
	{
		// Can't have a has-hypercube role if there are no children
		if ( ! isset( $root['children'] ) ) return false;
		foreach ( $root['children'] as $nodeKey => $node )
		{
			if ( ! isset( $node['arcrole'] ) ) continue;
			if ( $node['arcrole'] === 'all' || $node['arcrole'] === 'notAll' ) return true;
		}
		return false;
	}

	/**
	 * Populate an instance from stored data
	 * @param array $data
	 */
	protected function fromStore( $data )
	{
		// Fix up the schema location
		if ( strpos( $data['schemaLocation'], '\\') !== false ) $data['schemaLocation'] = str_replace( '\\', '/', $data['schemaLocation'] );
		if ( strpos( $data['schemaLocation'], './') ) $data['schemaLocation'] = XBRL::normalizePath( $data['schemaLocation'] );

		$this->loadedFromJSON = true;

		$this->elementIndex			=& $data['elementIndex'];
		$this->elementHypercubes	=& $data['elementHypercubes'];
		$this->elementDimensions	=& $data['elementDimensions'];
		$this->schemaLocation		=& $data['schemaLocation'];
		$this->namespace			=& $data['namespace'];

		$this->context->importedSchemas[ $this->getNamespace() ] = $this;
		$this->context->schemaFileToNamespace[ $this->getSchemaLocation() ] = $this->getNamespace();
		$this->context->schemaFileToNamespace[ $this->getTaxonomyXSD() ] = $this->getNamespace();

		$this->roleTypes			=& $data['roleTypes'];
		if ( isset( $data['roleTypeIds'] ) )
		{
			$this->roleTypeIds			=& $data['roleTypeIds'];
		}
		$this->arcroleTypes			=& $data['arcroleTypes'];
		if ( isset( $data['arcroleTypeIds'] ) )
		{
			$this->arcroleTypeIds		=& $data['arcroleTypeIds'];
		}
		$this->linkbaseTypes		=& $data['linkbaseTypes'];
		$this->definitionRoleRefs	=& $data['definitionRoleRefs'];
		$this->referenceRoleRefs	=& $data['referenceRoleRefs'];
		$this->stringsLoaded		=  $data['stringsLoaded'];
		$this->documentPrefixes		=  $data['documentPrefixes'];
		$this->xdtTargetRoles		=& $data['xdtTargetRoles'];
		$this->genericRoles			=& $data['genericRoles'];
		$this->variableSetNames		=& $data['variableSetNames'];
		$this->linkbaseIds			=& $data['linkbaseIds'];
		$this->hasFormulas			=& $data['hasFormulas'];
		$this->linkbases			=& $data['linkbases'];
		$this->importedFiles		=& $data['importedFiles'];
		$this->indirectNamespaces	=& $data['indirectNamespaces'];
		$this->usedByNamespaces		=& $data['usedByNamespaces'];

		if ( ( $key = array_search( $this->namespace, $this->documentPrefixes ) ) !== false )
		{
			$this->prefix = $key;
		}
		else
		{
			$this->log()->warning("A prefix cannot be found among the document prefixes for the namespace '{$this->namespace}'");
		}

		if ( isset( $data['extraElements'] ) )
		{
			foreach ( $data['extraElements'] as $newElementLabel => $existingElementLabel )
			{
				$existingElementTaxonomy = $this->getTaxonomyForXSD( $existingElementLabel );
				$element = $existingElementTaxonomy->getElementById( $existingElementLabel );

				$newElementTaxonomy = $this->getTaxonomyForXSD( $newElementLabel );
				$elements =& $newElementTaxonomy->getElements();
				$elements[ substr( strstr( $newElementLabel, '#' ), 1 ) ] = $elements[ $element['id'] ];
			}
		}

		// BMS 2018-09-09 Moved to $this->fixupForeignDefinitionsFromStore($schemas)
		// if ( isset( $data['foreignDefinitionRoleRefs'] ) && $data['foreignDefinitionRoleRefs'] )
		// {
		// 	foreach( $data['foreignDefinitionRoleRefs'] as $definitionRoleRefKey => $definitionRoleRef )
		// 	{
		// 		$home_taxonomy = $this->getTaxonomyForXSD( $definitionRoleRef['href'] );
		// 		if ( ! $home_taxonomy )
		// 		{
		// 			$ex = new \Exception("Unable to locate taxonomy instance for '{$definitionRoleRef['href']}'");
		// 			// error_log($ex->getTraceAsString());
		// 			throw $ex;
		// 		}
		// 		$roleRef =& $home_taxonomy->getDefinitionRoleRef( $definitionRoleRef['roleUri'] );
		// 		$roleRef = $this->mergeExtendedRoles( $roleRef, $definitionRoleRef, $mergedRoles, false );
		// 		$this->context->setPrimaryItemsCache( null );
		// 	}
		// }
	}

	/**
	 * A function to record the static parts of an XBRL class and load the individual XBRL instances
	 * @param string $baseTaxonomy (optional) Provided if an extension taxonomy needs to record the taxonomy it is extending.
	 * @param boolean $prettyPrint
	 * @return string
	 */
	public function toJSON( $baseTaxonomy = null, $prettyPrint = false )
	{
		$schemas = array();

		/**
		 * @var XBRL $schema
		 */
		foreach ( $this->context->importedSchemas as $schemaKey => $schema )
		{
			$schemas[ $schemaKey ] = $schema->toStore();
			$filename = str_replace( '/', '-', str_replace( 'http://', '', $schemaKey ) );
		}

		if ( isset( $this->context->labels[ XBRL_Constants::$defaultLinkRole ]['labelshref'] ) )
		{
			unset( $this->context->labels[ XBRL_Constants::$defaultLinkRole ]['labelshref'] );
		}

		$context = array(
			'labels' => $this->context->labels,
			'labelLinkRoleRefs' => $this->context->labelLinkRoleRefs,
			'labelRoleRefs' => $this->context->labelRoleRefs,
			'types' => $this->context->types->toArray(),
		);

		$removeAttributes = function( $roleRefs, $collection )
		{
			foreach ( $roleRefs as $roleUri => $role )
			{
				if ( ! isset( $role[ $collection ] ) ) continue;

				foreach ( $role[ $collection ] as $fromId => $to )
				{
					foreach ( $to as $toId => $arc )
					{
						if ( ! isset( $arc['attributes'] ) ) continue;
						unset( $roleRefs[ $roleUri ][ $collection ][ $fromId ][ $toId ]['attributes'] );
					}
				}
			}

			return $roleRefs;
		};

		if ( ! empty( $this->context->calculationRoleRefs ) )
		{
			// Need to do this on all collections on which arc equivalence has been used
			$context['calculationRoleRefs'] = $removeAttributes( $this->context->calculationRoleRefs, "calculations" );
		}
		if ( ! empty( $this->context->generalSpecialRoleRefs ) )
		{
			$context['generalSpecialRoleRefs'] = $this->context->generalSpecialRoleRefs;
		}
		if ( ! empty( $this->context->presentationRoleRefs ) )
		{
			$context['presentationRoleRefs'] = $this->context->presentationRoleRefs;
		}
		if ( ! empty( $this->context->essenceAlias ) )
		{
			$context['essenceAlias'] = $this->context->essenceAlias;
		}
		if ( ! empty( $this->context->requireElements ) )
		{
			$context['requireElements'] = $this->context->requireElements;
		}
		if ( ! empty( $this->context->nonDimensionalRoleRefs ) )
		{
			$context['nonDimensionalRoleRefs'] = $this->context->nonDimensionalRoleRefs;
		}
		if ( $this->context->hasPrimaryItemsCache() )
		{
			$context['primaryItems'] = $this->getDefinitionPrimaryItems();
		}
		if ( $this->context->dimensionDefaults )
		{
			$context['dimensionDefaults'] =& $this->context->dimensionDefaults;
		}
		if ( $this->context->formulaNames )
		{
			$context['formulaNames'] =& $this->context->formulaNames;
		}

		return json_encode(
			array(
				'schemas' => &$schemas,
				'context' => &$context,
				'mainNamespace' => $this->namespace,
				'baseTaxonomy' => $baseTaxonomy,
				'validTaxonomySet' => XBRL::isValidating()
					? ! XBRL_Log::getInstance()->hasConformanceIssueWarning()
					: null,
				'isExtensionTaxonomy' => $this->context->isExtensionTaxonomy(),

			), $prettyPrint ? JSON_PRETTY_PRINT : null
		);
	}

	/**
	 * A function to record the relevant parts of an XBRL instance
	 * @param boolean $encode True if the result is to be encoded
	 * @param boolean $prettyPrint
	 * @return array|string
	 */
	public function toStore( $encode = false, $prettyPrint = false )
	{
		$store = array(
			'elementIndex'				=> &$this->elementIndex,
			'elementHypercubes' 		=> &$this->elementHypercubes,
			'elementDimensions' 		=> &$this->elementDimensions,
			'schemaLocation'			=> &$this->schemaLocation,
			'namespace'					=> &$this->namespace,
			'roleTypes'					=> &$this->roleTypes,
			'roleTypeIds'				=> &$this->roleTypeIds,
			'arcroleTypes'				=> &$this->arcroleTypes,
			'arcroleTypeIds'			=> &$this->arcroleTypeIds,
			'linkbaseTypes'				=> &$this->linkbaseTypes,
			'definitionRoleRefs'		=> &$this->definitionRoleRefs,
			'referenceRoleRefs'			=> &$this->referenceRoleRefs,
			'xdtTargetRoles'			=> &$this->xdtTargetRoles,
			'stringsLoaded'				=> $this->stringsAreLoaded(),
			'documentPrefixes'			=> $this->documentPrefixes,
			'genericRoles'				=> $this->genericRoles,
			'variableSetNames'			=> $this->variableSetNames,
			'linkbaseIds'				=> $this->linkbaseIds,
			'hasFormulas'				=> $this->hasFormulas,
			'linkbases'					=> $this->linkbases,
			'foreignDefinitionRoleRefs'	=> &$this->foreignDefinitionRoleRefs,
			'importedFiles'				=> &$this->importedFiles,
			'indirectNamespaces'		=> &$this->indirectNamespaces,
			'usedByNamespaces'			=> &$this->usedByNamespaces,
		);

		if ( $this->context->isExtensionTaxonomy() && $this->extraElements )
		{
			$store['extraElements'] = $this->extraElements;
		}

		return $encode
			? json_encode( $store, $prettyPrint ? JSON_PRETTY_PRINT : null )
			: $store;
	}

	/**
	 * Initializes a specific schema
	 * @param string $taxonomy_schema The file containing the taxonomy xsd
	 * @param SimpleXMLElement $xbrlDocument An instance of SimpleXMLElement representing the schema file XML content
	 * @param string $targetNamespace The namespace of the taxonomy being loaded
	 * @param int $depth (Optional) The nesting depth at which this taxonomy is being loaded
	 * @param Closure $callback (Optional) A callback to process additional schema files
	 * @return XBRL The newly created taxonomy instance
	 */
	public function loadSchema( $taxonomy_schema, $xbrlDocument, $targetNamespace, $depth = 0, $callback = null )
	{
		if ( empty( $targetNamespace ) )
		{
			if ( XBRL::isValidating() )
				$this->log()->taxonomy_validation( "5.1", "The 'targetNamespace attribute cannot be missing or empty.  This schema will not be loaded.",
					array(
						'schema' => $taxonomy_schema,
					)
				);

				return false;
		}

		$this->beforeLoadTaxonomy( $taxonomy_schema );

		/* ------------------------------------------------------------------
		 * Begin reading the content such as elements, attributed and roles
		 * ------------------------------------------------------------------ */
		if ( $this->including )
		{
			$originalSchemaLocation = $this->schemaLocation;
			$originalXbrlDocument = $this->xbrlDocument;
		}
		else
		{
			$this->context->importedSchemas[ $targetNamespace ] =& $this;
			$this->namespace = $targetNamespace;
		}

		// If the location is a file location normalize the path to remove dots
		$this->schemaLocation = filter_var( $taxonomy_schema, FILTER_VALIDATE_URL ) ? $taxonomy_schema : XBRL::normalizePath( str_replace( '\\', '/', $taxonomy_schema ) );
		$this->xbrlDocument = $xbrlDocument;

		if ( is_null( $this->documentPrefixes ) ) $this->documentPrefixes = array();
		$this->documentPrefixes += $xbrlDocument->getDocNamespaces( true );

		if ( isset( $this->documentPrefixes[''] ) )
		{
			$defaultNamespace = $this->documentPrefixes[''];
			if ( isset( XBRL_Constants::$standardNamespaces[ $defaultNamespace ] ) )
			{
				$default = XBRL_Constants::$standardNamespaces[ $defaultNamespace ];
				$this->documentPrefixes[ $default ] = $defaultNamespace;
			}
		}

		foreach ( $this->documentPrefixes as $prefix => $namespace )
		{
			if ( $namespace != $targetNamespace ) continue;
			$this->prefix = $prefix;
			break;
		}

		if ( is_null( $this->prefix ) )
		{
			// BMS 2018-04-20
			// $this->prefix = '';
			$this->prefix = substr( str_shuffle( "abcdefghijklmnopqrstuvwxyz" ), 0, 8 );
			// BMS 2019-07-06 Adding the prefix/namespace pair is needed for when a compiled taxonomy is used and this information is used in fromStore()
			$this->documentPrefixes[ $this->prefix ] = $this->namespace;
		}

		$this->importSchemas( $depth );

		/* ------------------------------------------------------------------
		 * Next import additional and included schema then any sibling schemas
		 * ------------------------------------------------------------------ */
		// The document may include an 'include' tag defining another document that should be included in this one
		$includedSchema = $this->includeSchemas( $taxonomy_schema, $xbrlDocument, $targetNamespace, $depth );
		// if ( $includedSchema !== false )
		// BMS 2018-04-01 Moved higher up (see the note with the same time)
		$this->indexElements( $includedSchema );

		$this->createArcRoleTypesList();
		$this->createRoleTypesList();
		$this->createLinkbaseRefList();

		if ( $this->including)
		{
			// If a schema has been included then the included schema XML will be set as the XBRL document so update it
			// $this->schemaLocation = $taxonomy_schema;
			// $this->xbrlDocument = $xbrlDocument;
			$this->schemaLocation = $originalSchemaLocation;
			$this->xbrlDocument = $originalXbrlDocument;
		}

		// BMS 2018-04-01 Moved higher up (see the note with the same time)
		// $this->indexElements( $includedSchema );
		// BMS 2018-03-31 Moved higher up (see the note with the same time)
		// $this->context->importedSchemas[ $targetNamespace ] =& $this;
		$this->context->schemaFileToNamespace[ $this->getSchemaLocation() ] = $targetNamespace;
		$this->context->schemaFileToNamespace[ $this->getTaxonomyXSD() ] = $targetNamespace;

		// A callback will be used if the caller needs this function
		// to process multiple schema documents such as multiple
		// schemeRef elements in an instance document
		if ( $callback )
		{
			$taxonomy = $callback( $depth );
		}

		return $this;
	}

	/**
	 * Initializes a specific schema
	 * @param int $depth (Optional) The nesting depth at which this taxonomy is being loaded
	 * @param Closure $callback (Optional) A callback to process additional schema files
	 * @return XBRL The newly created taxonomy instance
	 */
	public function loadLinkbases( $depth = 0, $callback = null )
	{
		if ( $this->linkbasesProcessed ) return $this;

		if ( $this->importedFiles )
			foreach ( $this->importedFiles as $importedFile )
			{
				// echo "$importedFile\n";
				$taxonomy = $this->getTaxonomyForXSD( $importedFile );
				if ( ! $taxonomy )
				{
					XBRL_Log::getInstance()->warning( "The taxonomy for '$importedFile' cannot be found." );
				}
				$taxonomy->loadLinkbases( $depth + 1 );
			}

		$xsd = $this->getTaxonomyXSD();
		// echo strftime('%b %d %H:%M:%S ') . "Processing linkbases: $xsd\n";
		/*
		 *  Included schemas do not need to be processed because their roleTypes, arcroleTypes
		 * and linkbase definitions are included in the including schema and, so, these
		 * components will be processed when the including schema linkbases are processed.
		 */
		$this->processLinkbases();

		// $this->fixupPresentationHypercubes();
		$this->fixupDefinitionRoles(); // Mainly adds a 'paths' index to the 'hierarchy' element of each role.

		$this->linkbasesProcessed = true;
		$this->linkbasesProcessInProgress = false;

		// A callback will be used if the caller needs this function
		// to process multiple schema documents such as multiple
		// schemeRef elements in an instance document
		if ( $callback )
		{
			$taxonomy = $callback( $depth );
		}

		$this->validateTaxonomy21();
		// Look at the linkbases to determine if there are any definition additions that need processing
		// if ( $this->linkbaseRefExists( XBRL_Constants::$DefinitionLinkbaseRef ) )
		{
			$this->validateDimensions();
		}
		$this->validateCustom();

		$this->loadSuccess = true;

		$this->afterLoadTaxonomy( $this->getSchemaLocation() );

		return $this;
	}

	/**
	 * Provides access to the elements array
	 * @return array
	 */
	public function &getElements()
	{
		return $this->elementIndex;
	}

	/**
	 * Returns an array of elements across all base taxonomy taxonomies
	 * @return array
	 */
	public function getAllBaseTaxonomyElements()
	{
		return $this->getAllElements( true, false );
	}

	/**
	 * Returns an array of elements across all taxonomies.  The arguments are only relevant
	 * when the main taxonomy is an extension taxonomy.
	 * @param boolean $includeBaseTaxonomyElements (default: false)
	 * @param boolean $includeExtensionTaxonomyElements (default: true)
	 * @return array
	 */
	public function getAllElements( $includeBaseTaxonomyElements = false, $includeExtensionTaxonomyElements = true )
	{
		$filterUsingArgs =	$this->context->isExtensionTaxonomy() &&
							property_exists( $this->context, 'previouslyImportedSchemaNamespaces' ) &&
							is_array( $this->context->previouslyImportedSchemaNamespaces );

		$result = array();
		if ( $filterUsingArgs && ! $includeBaseTaxonomyElements && ! $includeExtensionTaxonomyElements ) return $result;

		foreach ( $this->context->importedSchemas as $namespace => $taxonomy )
		{
			if ( $filterUsingArgs )
			{
				// If excluding base taxonomy elements and the previouslyImportedSchemaNamespaces array contains the namespace then ignore
				if ( ! $includeBaseTaxonomyElements && array_search( $namespace, $this->context->previouslyImportedSchemaNamespaces ) !== false ) continue;
				// If excluding extension taxonomy elements and the previouslyImportedSchemaNamespaces array does not contain the namespace then ignore
				if ( ! $includeExtensionTaxonomyElements && array_search( $namespace, $this->context->previouslyImportedSchemaNamespaces ) === false ) continue;
			}

			$elements = $taxonomy->getElements();
			if ( ! $elements ) continue; // Don't attempt to merge if there are no element as it take time
			// Add the taxonomy prefix to each element
			$prefix = $taxonomy->getPrefix();
			$elements = array_map( function( $item ) use ( $prefix ) { $item['prefix'] = $prefix; return $item; }, $elements );
			$result = array_merge( $result, $elements );
		}
		return $result;
	}

	/**
	 * Returns an array of preferred label pairs.  In the base XBRL instance is only the PeriodStart/PeriodEnd pair.
	 * @return string[][]
	 */
	public function getBeginEndPreferredLabelPairs()
	{
		return is_callable( XBRL::$beginEndPreferredLabelPairsDelegate )
			? call_user_func( XBRL::$beginEndPreferredLabelPairsDelegate )
			: XBRL::$beginEndPreferredLabelPairs;
	}

	/**
	 * Set a preferred label pair
	 * @param array $pair An array with two items that are both preferredLabel roles
	 */
	public function setBeginEndPreferredLabelPair( $pair )
	{
		$this->beginEndPreferredLabelPairs[] = $pair;
	}

	/**
	 * Returns an array of a preferred label pair if $preferredLabel is among the supported pairs
	 * @param array $preferredLabel
	 * return string[]
	 */
	public function getBeginEndPreferredLabelPair( $preferredLabel )
	{
		if ( ! $preferredLabel ) return false;

		return array_filter( $this->getBeginEndPreferredLabelPairs(), function( $pair ) use( $preferredLabel )
		{
			return in_array( $preferredLabel, $pair );
		} );
	}

	/**
	 * Returns the flag indicating whether or not the taxonomy includes formulas
	 * @param bool $checkAllSchemas (optional: default = false) Forces the test to look at all taxonomies and return true if any one taxonomy has formulas
	 * @return boolean
	 */
	public function getHasFormulas( $checkAllSchemas = false )
	{
		if ( ! $checkAllSchemas )
		{
			return $this->hasFormulas;
		}

		// Check to see if any of the schemas
		foreach ( $this->context->importedSchemas as $namespace => $taxonomy )
		{
			if ( $taxonomy->getHasFormulas() ) return true;
		}

		return false;
	}

	/**
	 * A cache of tuple elements for performance
	 * @var array $tupleElements
	 */
	private $tupleElements = null;

	/**
	 * Get a list of the elements that are tuples
	 * @return array
	 */
	public function getTupleElements()
	{
		if ( $this->tupleElements === null )
		{
			$this->tupleElements = array_filter( $this->elementIndex, function( $item ) { return $item['substitutionGroup'] === XBRL_Constants::$xbrliTuple; } );
		}

		return $this->tupleElements;
	}

	/**
	 * Provides access the the element hypercubes array
	 * @return array
	 */
	public function getElementHypercubes()
	{
		return $this->elementHypercubes;
	}

	/**
	 * Provides access the the element dimensions array
	 * @return array
	 */
	public function getElementDimensions()
	{
		return $this->elementDimensions;
	}

	/**
	 * Return an element from the types class for the $id
	 *
	 * @param string $id Id of the element type to retrieve
	 * @param XBRL_Types $types (optional) An instance of the XBRL_Types class
	 * @return boolean|array
	 */
	public function getTypeElementById( $id, $types = null )
	{
		$fragment = parse_url( $id, PHP_URL_FRAGMENT );
		$fragment = $fragment ? $fragment : $id;

		$taxonomy = $this->getTaxonomyForXSD( $id );
		if ( ! $taxonomy )
		{
			$this->log()->warning( "Unable to locate taxonomy for id '$id'" );
			return false;
		}

		if ( is_null( $types ) ) $types = XBRL_Types::getInstance();
		return $types->getElement( $fragment, $taxonomy->getPrefix() );
	}

	/**
	 * Gets an element based on its id
	 * @param string $id The id of the element to return
	 * @return Array An element array or false
	 */
	public function &getElementById( $id )
	{
		$id = urldecode( $id );

		$fragment = parse_url( $id, PHP_URL_FRAGMENT );
		$fragment = $fragment ? $fragment : $id;

		if ( ! isset( $this->elementIndex[ $fragment ] ) )
		{
			$false = false;
			return $false;
		}
		$element = &$this->elementIndex[ $fragment ];
		return $element;
	}

	/**
	 * Gets an element based on its name
	 * @param string $name The name of the element to return
	 * @return array An element array or false
	 */
	public function &getElementByName( $name )
	{
		$defaultResult = array();

		if ( ! property_exists( $this, 'elements_by_name' ) )
		{
			$this->elements_by_name = array();

			foreach ( $this->elementIndex as $elementsKey => &$element )
			{
				$this->elements_by_name[ $element['name'] ]  = &$element;
			}
		}

		if ( isset( $this->elements_by_name[ $name ] ) )
		{
			return $this->elements_by_name[ $name ];
		}
		return $defaultResult;

		return isset( $this->elements_by_name[ $name ] ) ? $this->elements_by_name[ $name ] : $defaultResult;
	}

	/**
	 * Get the current locale
	 * @return string
	 */
	public function getLocale()
	{
		return $this->context->locale;
	}

	/**
	 * Set the current locale
	 * @param string $locale The locale to use
	 */
	public function setLocale( $locale )
	{
		$this->context->locale = $locale;
	}

	/**
	 * Returns the node with ID that corresponds to the href.
	 * @param $href string|array The href is likely to come from a locator and can be the string or an array produced by parse_url.
	 * @param string $link A 'usedOn' value to select one of the roleTypes
	 * @return string|boolean
	 */
	public function resolveId( $href, $link = null )
	{
		// Check the $href provided is valid
		$parts = is_array( $href ) ? $href : parse_url( $href );
		if ( ! isset( $parts['path'] ) || ! isset( $parts['fragment'] ) ) return false;

		$id = $parts['fragment'];
		$taxonomy = $this->getTaxonomyForXSD( $href );
		if ( $taxonomy === false ) return false;

		// The ID may belong to one of the elements
		$elements =& $taxonomy->getElements();
		return isset( $elements[ $id ] )
			? $elements[ $id ]
			: false;
	}

	/**
	 * Get the taxonomy for the $namespace
	 * @param string $namespace The namespace of the taxonomy to retrieve
	 * @return XBRL An XBRL instance or false
	 */
	public function getTaxonomyForNamespace( $namespace )
	{
		if ( ! isset( $this->context->importedSchemas[ $namespace ] ) )
		{
			if ( XBRL::endsWith( $namespace, '/' ) )
			{
				$namespace = substr( $namespace, 0, strlen( $namespace ) - 1 );
			}
			else if ( ! XBRL::endsWith( $namespace, '/' ) )

			{
				$namespace .= '/';
			}
			if ( ! isset( $this->context->importedSchemas[ $namespace ] ) )
			{
				return false;
			}
		}

		return $this->context->importedSchemas[ $namespace ];
	}

	/**
	 * Get the taxonomy that has the prefix used in the QName
	 * @param string|QName $prefix
	 * @return XBRL
	 */
	public function getTaxonomyForQName( $qname )
	{
		$prefix = $qname instanceof \lyquidity\xml\QName
			? $qname->localName
			: strstr( $qname, ":", true );
		return $this->getTaxonomyForPrefix( $prefix );
	}

	/**
	 * Get the taxonomy for the prefix.  Taxonomies have unique prefixes.
	 * @param string $prefix
	 * @return XBRL
	 */
	public function getTaxonomyForPrefix( $prefix )
	{
		if ( ! $prefix ) return null;

		$taxonomies = array_filter(
			$this->getImportedSchemas(),
			function( $taxonomy ) use( $prefix ) { return $taxonomy->getPrefix() == $prefix; }
		);
		if ( ! $taxonomies ) return null;
		return reset( $taxonomies );
	}

	/**
	 * Get a list of imported schemas
	 * @return XBRL[] an array of all loaded schemas
	 */
	public function getImportedSchemas()
	{
		return $this->context->importedSchemas;
	}

	/**
	 * Convvert a QName of a element (concept, dimension, member, etc.) in its ID
	 * @param QName|string $qname
	 * @return string
	 */
	public function getIdForQName( $qname )
	{
		if ( ! $qname instanceof QName )
		{
			$qname = qname( $qname, $this->getDocumentNamespaces() );
		}

		if ( ! $qname )
		{
			throw new Exception("Unable to resolve the QName '$qname'");
		}

		$taxonomy = $this->getTaxonomyForNamespace( $qname->namespaceURI );
		$taxonomy_element = $taxonomy->getElementByName( $qname->localName );
		return "{$taxonomy->getTaxonomyXSD()}#{$taxonomy_element['id']}";
	}

	/**
	 * Get the namespace of the taxonony
	 * @return string The namespace
	 */
	public function getNamespace()
	{
		return $this->namespace;
	}

	/**
	 * Return the physical location of the taxonomy schema file
	 * @return string
	 */
	public function getSchemaLocation()
	{
		return $this->schemaLocation;
	}

	/**
	 * The base name of the taxonomy schema location
	 * @var string
	 */
	private $schemaXSD = null;

	/**
	 * Return the XSD filename from the location
	 * $return string
	 */
	public function getTaxonomyXSD()
	{
		if ( $this->schemaXSD === null ) $this->schemaXSD = pathinfo( $this->schemaLocation, PATHINFO_BASENAME );
		return $this->schemaXSD;
	}

	/**
	 * Get the taxonomy schema document instance for a given href.
	 * @param string|array $href The href is likely to come from a locator and can be the string or an array produced by parse_url.
	 * @param bool $allowLinkbaseLookup (default: true) When true and if the $href is not .xsd it will be checked to see if a schema can be found for the linkbase
	 * @return XBRL An instance of XBRL
	 */
	public function getTaxonomyForXSD( $href, $allowLinkbaseLookup = true )
	{
		// If the path ends in .xml then look in all schema documents to see if it is a reference to a linkbase.
		// If it is, return the 'owning' taxonomy
		// if ( XBRL::endsWith( basename( parse_url( $href, PHP_URL_PATH ) ), ".xsd" ) )
		if ( is_array( $href ) )
		{
			return $this->context->getTaxonomyForXSD( $href );
		}

		$href = strpos( $href, '#' ) === false ? $href : strstr( $href, '#', true );
		if ( XBRL::endsWith( $href, '.xsd' )  )
		{
			return $this->context->getTaxonomyForXSD( $href );
		}

		$href = basename( $href );

		if ( $allowLinkbaseLookup )
		{
			foreach ( $this->context->importedSchemas as $path => $taxonomy )
			{
				foreach ( $taxonomy->linkbaseTypes as $roleUri => $linkbaseRefs )
				{
					foreach ( $linkbaseRefs as $key => $linkbase )
					{
						if ( strcasecmp( basename( $linkbase['href'] ), $href ) != 0 )
						{
							continue;
						}

						return $taxonomy;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Return an array of labelLinkRefs.  If a role is not provided it will default to XBRL_Constants::$defaultLinkRole.
	 * @param string $role The role
	 * @param array $labelLinkRoleRefs A list of the refs for the $role
	 * @return array
	 */
	public function &getLabelLinkRoleRefs( $role = null, &$labelLinkRoleRefs = null )
	{
		if ( $role === null ) $role = XBRL_Constants::$defaultLinkRole;
		$labelLinkRoleRefs = isset ( $this->context->labelLinkRoleRefs[ $role ] )
			? $this->context->labelLinkRoleRefs[ $role ]
			: false;

		return $labelLinkRoleRefs;
	}

	/**
	 * Returns the load state of the strings for the taxonomy and all roles
	 * @return boolean
	 */
	public function stringsAreLoaded()
	{
		return $this->stringsLoaded;
	}

	/**
	 * Returns a description for an element identified by href and optionally a role and language
	 * If a label cannot be found using the options and the options are not already the defaults, try the defaults.
	 * @param string $href  The id of the element for which a description is to be retrieved.  If only the fragment is provided, its assumed to be from the current taxonomy.
	 * @param null|string[] $roles (optional) If true include the element text in the result.  If the argument is an array it will be an array of preferred labels.
	 * @param null|string $lang (optional) a language locale
	 * @param string $extendedLinkRole (optional)
	 * @return bool|string A description string or false
	 */
	public function getTaxonomyDescriptionForIdWithDefaults( $href, $roles = null, $lang = null, $extendedLinkRole = null )
	{
		$text = $this->getTaxonomyDescriptionForId( $href, $roles, $lang, $extendedLinkRole );
		if ( $text !== false) return $text;

		// Try some defaults unless the options are already the defaults
		if (
			  (
			  	 ( $roles == null ) ||
			  	 ( $roles == XBRL_Constants::$labelRoleLabel ) ||
			  	 ( is_array( $roles ) && in_array( XBRL_Constants::$labelRoleLabel, $roles ) )
			  ) &&
			  ( $lang == $this->getDefaultLanguage() || $lang == null )
		   ) return false;

		if ( $roles && $lang )
		{
			$text = $this->getTaxonomyDescriptionForId( $href, null, $lang, $extendedLinkRole );
			if ( $text !== false) return $text;
		}

		if ( $extendedLinkRole && $lang )
		{
			$text = $this->getTaxonomyDescriptionForId( $href, $roles, $lang, null );
			if ( $text !== false) return $text;
		}

		return $this->getTaxonomyDescriptionForId( $href, null, null, $extendedLinkRole );
	}

	/**
	 * Returns a description for an element identified by href and optionally a role and language
	 * @param string $href  The id of the element for which a description is to be retrieved.  If only the fragment is provided, its assumed to be from the current taxonomy.
	 * @param null|array[string] (optional) $roles If true include the element text in the result.  If the argument is an array it will be an array of preferred labels.
	 * @param null|string $lang (optional) a language locale
	 * @param string $extendedLinkRole (optional)
	 * @return bool|string A description string or false
	 */
	public function getTaxonomyDescriptionForId( $href, $roles = null, $lang = null, $extendedLinkRole = null )
	{
		if ( is_null( $lang ) ) $lang = $this->getDefaultLanguage();
		if ( is_null( $extendedLinkRole ) ) $extendedLinkRole = XBRL_Constants::$defaultLinkRole;

		// If only an id is passed in the $href parameter pad it out
		if ( strpos( $href, '#' ) === false || strpos( $href, '#' ) === 0 )
		{
			$href = ltrim( $href, '#' );

			// $xsd = pathinfo( $this->schemaLocation, PATHINFO_BASENAME );
			$xsd = $this->getTaxonomyXSD();
			$href = "$xsd#$href";
		}

		// Check the role is valid
		if ( $roles === null || empty( $roles ) )
		{
			$roles = array( XBRL_Constants::$labelRoleLabel );
		}
		elseif ( ! is_array( $roles ) )
		{
			$roles = array( $roles );
		}

		// Step one: find the locator
		$labelRoleRef = &$this->context->labels[ $extendedLinkRole ];
		if ( ! $labelRoleRef )
		{
			$extendedLinkRole = XBRL_Constants::$defaultLinkRole;
			$labelRoleRef = &$this->context->labels[ $extendedLinkRole ];
		}

		$langs = array( $lang );
		if ( strpos( $lang, '-' ) ) $langs[] = strstr( $lang, '-', true );

		foreach ( $langs as $lang )
		{
			// Check it's not already been used and cached.  Once found (below) items are added to a cache.
			if ( isset( $labelRoleRef['cache'][ $href ] ) )
			{
				if ( isset( $labelRoleRef['cache'][ $href ][ $lang ] ) )
				{
					$matchedRoles = array_intersect( $roles, array_keys( $labelRoleRef['cache'][ $href ][ $lang ] ) );
					if ( $matchedRoles )
					{
						$id = parse_url( $href, PHP_URL_FRAGMENT );

						// Make sure this $href is not reported as an error now it been found
						// The href may be missing first time though but not after the labels have been swapped
						if ( isset( $this->context->missingLabels[ $id ] ) )
						{
							unset( $this->context->missingLabels[ $id ] );
						}

						return $labelRoleRef['cache'][ $href ][ $lang ][ reset( $matchedRoles ) ];
					}
				}
			}

			$id = parse_url( $href, PHP_URL_FRAGMENT );

			// Need to have an array indexed by href not label
			if ( ! isset( $labelRoleRef['arcs'][ $href ] ) )
			{
				// $this->log()->err( "Cannot find label locator for '{$href}'" );
				if ( ! isset( $this->context->missingLabels[ $id ] ) )
				{
					$this->context->missingLabels[ $id ] = "Cannot find label locator";
				}
				return false;
			}

			// Step two: Look for the arcs
			// Look for highest priority arc label(s) that is/are not prohibited
			// This is quite complicated because there may be multiple arcs with
			// different priorites and prohibition states.  There are two nested
			// calls to array_reduce.  This is because there can be more than one
			// label nested inside more that one element label like this:
			//
			//	element_label_1
			//		label_label_1
			//			priority_1
			//			prohibited_1
			//		label_label_2
			//			priority_2
			//			prohibited_2
			//	element_label_2
			//		label_label_1
			//			priority_1
			//			prohibited_1
			//
			// The task is to take the label label within each element label with
			// the highest priority or drop it if the one with the highest priority
			// is prohibited.  The only interest is the label label.

			$arcTos = $labelRoleRef['arcs'][ $href ];
			if ( ! count( $arcTos ) )
			{
				// $this->log()->info( "Cannot find label arc" );
				if ( ! isset( $this->context->missingLabels[ $id ] ) )
				{
					$this->context->missingLabels[ $id ] = "Cannot find label arc";
				}
				return false;
			}

			// Re-organize the arcs to group them by hashes.  Same hashes means the
			// same arc even if the labels are different.
			$result = array();
			foreach ( $arcTos as $toLabel => $to )
			{
				foreach ( $to as $toDetail )
				{
					if ( ! isset( $toDetail['hash'] ) )
					{
						$this->log()->warning( "Missing equivalence hash on label arc for '$href' -> $toLabel'.  Using the 'from' label." );
					}
					$result[ isset( $toDetail['hash'] ) ? $toDetail['hash'] : $toLabel ][ $toDetail['priority'] ][ $toLabel ][] = $toDetail;
				}
			}

			// Look at the highest priority to make sure there are no prohibited uses and
			// only select those arcs with the correct roles and language
			$result = array_reduce( array_keys( $result ), function( $carry, $hash ) use( $result, $roles, $lang ) {
				$priorities = $result[ $hash ];
				ksort( $priorities );
				$highestPriorities = end( $priorities );
				$valid = array_reduce( $highestPriorities, function( $carry, $to ) use( $roles, $lang ) {
					return $carry || count( array_filter( $to, function( $toDetail ) use( $roles, $lang ) {
						return ( ! isset( $toDetail['use'] ) || $toDetail['use'] != 'prohibited' ) &&
							$toDetail['lang'] == $lang &&
							in_array( $toDetail['role'], $roles );
					} ) );
				}, false );

				return $valid
					? array_merge( $carry, array( $hash => $highestPriorities ) )
					: $carry;

			}, array() );

			// Grab the labels and make sure they are unique
			$arcLabels = array_flip(
				array_reduce( $result,
					function( $carry, $to ) {
						return array_merge( $carry, array_fill_keys( array_keys( $to ), 1 ) );
					}, array()
				)
			);

			// if ( ! $arcLabels ) return false;
			if ( ! $arcLabels ) continue;

			// if ( ! in_array( XBRL_Constants::$labelRoleLabel, $roles ) ) $roles[] = XBRL_Constants::$labelRoleLabel;

			// Try each of the labels that a valid based on their arc set and preferred role
			// The role is the outer look so the most preferred is tried first for all labels
			foreach ( $roles as $role )
			{
				foreach ( $arcLabels as $arcLabel )
				{
					// Try the preferred label first if there is one
					if ( ! isset ( $labelRoleRef['labels'][ $role ][ $lang ][ $arcLabel ] ) ) continue;

					$label = $labelRoleRef['labels'][ $role ][ $lang ][ $arcLabel ];

					// Make sure this $href is not reported as an error now it been found
					// The href may be missing first time though but not after the labels have been swapped
					if ( isset( $this->context->missingLabels[ $id ] ) )
					{
						unset( $this->context->missingLabels[ $id ] );
					}

					$labelRoleRef['cache'][ $href ][ $lang ][ $role ] = $label['text'];

					return $label['text'];
				}
			}
		}

		return false;
	}

	/**
	 * Return the description or title for a specific role
	 *
	 * @param array $role The role for which the description should be returned
	 * @return string
	 */
	public function getCalculationLinkRoleDescription( $role )
	{
		return $this->getLinkRoleDescription( $role, 'link:calculationLink', 'calculation link' );
	}

	/**
	 * Return the description or title for a specific role
	 *
	 * @param array $role The role for which the description should be returned
	 * @return string
	 */
	public function getGeneralSpecialLinkRoleDescription( $role )
	{
		return $this->getLinkRoleDescription( $role, 'link:definitionLink', 'general special link' );
	}

	/**
	 * Return the description or title for a specific custom role
	 *
	 * @param array $role The role for which the description should be returned
	 * @param string $roleType The role type (eg. link:presentationLink) of the description to retrieve
	 * @param string $roleTitle The title of the role type description being requested
	 * @return string
	 */
	public function getCustomLinkRoleDescription( $role, $roleType, $roleTitle )
	{
		return $this->getLinkRoleDescription( $role, $roleType, $roleTitle );
	}

	/**
	 * Return the description or title for a specific generic role
	 *
	 * @param array $role The role for which the description should be returned
	 * @param string $roleType The role type (eg. link:presentationLink) of the description to retrieve
	 * @param string $roleTitle The title of the role type description being requested
	 * @return string
	 */
	public function getGenericLinkRoleDescription( $role, $roleType, $roleTitle )
	{
		return $this->getLinkRoleDescription( $role, $roleType, $roleTitle );
	}

	/**
	 * Return the description or title for a specific role
	 *
	 * @param array $arcrole The arcrole for which the description should be returned
	 * @return string
	 */
	public function getNonDimensionalArcRoleDescription( $arcrole )
	{
		return $this->GetArcroleDescription( $arcrole, 'link:definitionArc', 'non-dimensions arc' );
	}

	/**
	 * Return the description or title for a specific role
	 *
	 * @param array $role The role for which the description should be returned
	 * @param string $lang a locale to use when returning the text. Defaults to null to use the default.
	 * @return string
	 */
	public function getPresentationLinkRoleDescription( $role, $lang = null )
	{
		return $this->getLinkRoleDescription( $role, 'link:presentationLink', 'presentation link', $lang );
	}

	/**
	 * Return the description or title for a specific role
	 *
	 * @param string $role
	 * @param string $roleType
	 * @param string $roleTitle
	 * @param string $lang a locale to use when returning the text. Defaults to null to use the default.
	 * @return boolean|string
	 */
	public function getLinkRoleDescription( $role, $roleType, $roleTitle, $lang = null )
	{
		$href = parse_url( $role['href'] );
		$basename = basename( $href['path'] );
		$roleTypes = $this->getRoleTypes( $role['href'] );
		if ( $roleTypes === false )
		{
			$this->log()->warning( "A role type cannot be found for '$basename'" );
			return false;
		}

		if ( ! isset( $roleTypes[ $roleType ] ) )
		{
			$this->log()->warning( "The role type corresponding to href '$basename' does not include one used on a $roleTitle.'" );
			return false;
		}

		$linkRoleType = $roleTypes[ $roleType ];
		if ( ! isset( $linkRoleType[ $role['roleUri'] ] ) )
		{
			$fragment = isset( $href['fragment'] ) ? $href['fragment'] : "";
			$this->log()->warning( "The $roleTitle of the role type corresponding to href '$basename' does not include an Id with fragment '$fragment'." );
			return false;
		}

		$link = $linkRoleType[ $role['roleUri'] ];
		$roleTaxonomy = $this->getTaxonomyForXSD( $basename );
		$arcs = $roleTaxonomy->getGenericArc( XBRL_Constants::$genericElementLabel, XBRL_Constants::$defaultLinkRole2008, "$basename#{$link['id']}" );
		if ( $arcs )
		{
			foreach ( $arcs as $arc )
			{
				if ( ! ( $label = $roleTaxonomy->getGenericLabel( XBRL_Constants::$genericRoleLabel, $arc['to'], $lang ? $lang : $this->getDefaultLanguage() ) ) ) continue;
				return $label[ $arc['to'] ]['text'];
			}
		}
		return isset( $link['definition'] ) ? trim( $link['definition'] ) : $role['roleUri'];
	}

	/**
	 * Return the description or title for a specific role
	 *
	 * @param string $arcrole
	 * @param string $arcroleType
	 * @param string $arcroleTitle
	 * @return boolean|string
	 */
	private function GetArcroleDescription( $arcrole, $arcroleType, $arcroleTitle )
	{
		$arcroleTypes = $this->getArcroleTypes();
		if ( $arcroleTypes === false )
		{
			$this->log()->warning( "No arcrole types can be found" );
			return false;
		}

		if ( ! isset( $arcroleTypes[ $arcroleType ] ) )
		{
			$this->log()->warning( "The arcrole types do not include one used on a $arcroleTitle.'" );
			return false;
		}

		$linkRoleType = $arcroleTypes[ $arcroleType ];
		if ( ! isset( $linkRoleType[ $arcrole ] ) )
		{
			$this->log()->warning( "The roleURI of the arcrole type '$arcrole' does not exist." );
			return false;
		}

		$link = $linkRoleType[ $arcrole ];
		return isset( $link['definition'] ) ? trim( $link['definition'] ) : $link['roleURI'];
	}

	/**
	 * Provide an implementation with an opportunity to provide a list of valid dimension members for a node
	 * Doing this allows the use of elements in an instance document to be disambiguated.
	 * This function will be overridden in descendents
	 *
	 * @param array $node The node of the element being processed
	 * @param array $ancestors An array containing the ids of the nodes leading to and including the current node
	 * @return array Returns an empty array
	 */
	public function getValidDimensionMembersForNode( $node, $ancestors )
	{
		return array();
	}

	/**
	 * Get the essenceAlias array
	 */
	public function getEssenceAliasList()
	{
		// return $this->context->essenceAlias;
		$arcs = $this->context->essenceAlias;

		// Remove any prohibited arcs
		if ( $arcs && count( $arcs ) )
		{
			foreach ( $arcs as $from => $targets )
			{
				foreach ( $targets as $to => $node )
				{
					if ( $node['use'] == XBRL_Constants::$xlinkUseOptional ) continue;
					unset( $arcs[ $from ][ $to ] );
				}

				if ( count( $arcs[ $from ] ) ) continue;
				unset( $arcs[ $from ] );
			}
		}

		return $arcs;
	}

	/**
	 * Get the calculation links array for a role after adjusting for order and probibition
	 * @param array|string $filter A list of the roles to include in the result.  If no filter is specified all roles are returned.
	 * @param bool $sort True if the result list should be sorted
	 * @return array
	 */
	public function &getCalculationRoleRefs( $filter = array(), $sort = true )
	{
		// Make sure the filter is initialized and contains lowercase values
		if ( ! is_array( $filter ) ) $filter = array();
		$filter = array_flip( array_map( 'strtolower', $filter ) );

		$refs = $this->context->calculationRoleRefs;

		/**
		 * @var array $result
		 */
		$result = array();

		foreach ( $refs as $refKey => $ref )
		{
			// Apply the filter if one is provided
			if ( count( $filter ) && ! isset( $filter[ strtolower( $refKey ) ] ) ) continue;

			if ( ! isset( $ref['calculations'] ) || ! count( $ref['calculations'] ) ) continue;

			$result[ $refKey ] = $this->context->calculationRoleRefs[ $refKey ];

			$description = $this->getCalculationLinkRoleDescription( $ref );
			$result[ $refKey ]['text'] = $description ? $description : "";

			// Remove any prohibited arcs
			if ( isset( $result[ $refKey ]['calculations'] ) && count( $result[ $refKey ]['calculations'] ) )
			{
				$arcs =& $result[ $refKey ]['calculations'];
				foreach ( $arcs as $from => $targets )
				{
					foreach ( $targets as $to => $node )
					{
						if ( $node['use'] == XBRL_Constants::$xlinkUseOptional ) continue;
						unset( $arcs[ $from ][ $to ] );
					}

					if ( count( $arcs[ $from ] ) ) continue;
					unset( $arcs[ $from ] );
				}
			}
		}

		if ( $sort )
		{
			$this->sortRoleRefs( $result );
		}

		return $result;
	}

	/**
	 * Get the General special links array for a role
	 * @param array|string $filter A list of the roles to include in the result.  If no filter is specified all roles are returned.
	 * @param bool $sort True if the result list should be sorted
	 * @return array
	 */
	public function &getGeneralSpecialRoleRefs( $filter = array(), $sort = true )
	{
		// Make sure the filter is initialized and contains lowercase values
		if ( ! is_array( $filter ) ) $filter = array();
		$filter = array_flip( array_map( 'strtolower', $filter ) );

		$refs = $this->context->generalSpecialRoleRefs;

		/**
		 * @var array $result
		 */
		$result = array();

		foreach ( $refs as $refKey => $ref )
		{
			// Apply the filter if one is provided
			if ( count( $filter ) && ! isset( $filter[ strtolower( $refKey ) ] ) ) continue;

			$result[ $refKey ] = $this->context->generalSpecialRoleRefs[ $refKey ];

			$description = $this->getGeneralSpecialLinkRoleDescription( $ref );
			$result[ $refKey ]['text'] = $description ? $description : "";

			// Remove any prohibited arcs
			if ( isset( $result[ $refKey ]['arcs'] ) && count( $result[ $refKey ]['arcs'] ) )
			{
				$arcs =& $result[ $refKey ]['arcs'];
				foreach ( $arcs as $from => $targets )
				{
					foreach ( $targets as $to => $node )
					{
						if ( $node['use'] == XBRL_Constants::$xlinkUseOptional ) continue;
						unset( $arcs[ $from ][ $to ] );
					}

					if ( count( $arcs[ $from ] ) ) continue;
					unset( $arcs[ $from ] );
				}
			}
		}

		if ( $sort )
		{
			$this->sortRoleRefs( $result );
		}

		return $result;
	}

	/**
	 * Get the custom links array for a role
	 * @param array|string $filter	A list of the roles to include in the result.
	 * 								If no filter is specified all roles are returned.
	 * @param bool $sort			True if the result list should be sorted
	 * @return array
	 */
	public function &getCustomRoleRefs( $filter = array(), $sort = true )
	{
		// Make sure the filter is initialized and contains lowercase values
		if ( ! is_array( $filter ) ) $filter = array();
		$filter = array_flip( array_map( 'strtolower', $filter ) );

		$refs = $this->customRoles;

		/**
		 * @var array $result
		 */
		$result = array();

		foreach ( $refs as $refKey => $ref )
		{
			// Apply the filter if one is provided
			if ( count( $filter ) && ! isset( $filter[ strtolower( $refKey ) ] ) ) continue;

			$result[ $refKey ] = $this->customRoles[ $refKey ];

			$linkElement = null;

			// Look for the corresponding link element
			foreach ( $this->roleTypes as $link => $role )
			{
				if ( ! isset( $role[ $refKey ] ) )
				{
					continue;
				}

				$linkElement = $link;
				break;
			}

			if ( is_null( $linkElement ) )
			{
				continue;
			}

			$description = $this->getCustomLinkRoleDescription( $ref, $linkElement, "Custom link role" );
			$result[ $refKey ]['text'] = $description ? $description : "";

			// Remove any prohibited arcs
			if ( isset( $result[ $refKey ]['arcs'] ) && count( $result[ $refKey ]['arcs'] ) )
			{
				$arcs =& $result[ $refKey ]['arcs'];
				foreach ( $arcs as $from => $targets )
				{
					foreach ( $targets as $to => $node )
					{
						if ( $node['use'] == XBRL_Constants::$xlinkUseOptional ) continue;
						unset( $arcs[ $from ][ $to ] );
					}

					if ( count( $arcs[ $from ] ) ) continue;
					unset( $arcs[ $from ] );
				}
			}
		}

		if ( $sort )
		{
			$this->sortRoleRefs( $result );
		}

		return $result;
	}

	/**
	 * Get the custom links array for a role
	 * @param array|string $filter	A list of the roles to include in the result.
	 * 								If no filter is specified all roles are returned.
	 * @param bool $sort			True if the result list should be sorted
	 * @return array
	 */
	public function &getGenericRoleRefs( $filter = array(), $sort = true )
	{
		// Make sure the filter is initialized and contains lowercase values
		if ( ! is_array( $filter ) ) $filter = array( $filter );
		$filter = array_flip( array_map( 'strtolower', $filter ) );

		$refs = $this->genericRoles['roles'];

		/**
		 * @var array $result
		 */
		$result = array();

		foreach ( $refs as $refUri => $ref )
		{
			// Apply the filter if one is provided
			if ( count( $filter ) && ! isset( $filter[ strtolower( $refUri ) ] ) ) continue;

			$result[ $refUri ] = $refs[ $refUri ];

			foreach ( $result[ $refUri ]['arcroles'] as $arcroleUri => $links )
			{
				foreach ( $links['links'] as $link => $arcelements)
				{
					foreach ( $arcelements['arcelements'] as $arcelement => $arcs )
					{
						$arcs =& $result[ $refUri ]['arcroles'][ $arcroleUri ]['links'][ $link ]['arcelements'][ $arcelement ]['arcs'];

						foreach ( $arcs as $from => $targets )
						{
							foreach ( $targets as $to => $nodes )
							{
								foreach ( $nodes as $key => $node )
								{
									if ( $node['use'] == XBRL_Constants::$xlinkUseOptional ) continue;
									unset( $arcs[ $from ][ $to ][ $key ] );
								}

								if ( ! count( $arcs[ $from ][ $to ] ) )
								{
									unset( $arcs[ $from ][ $to ] );
								}
							}

							if ( count( $arcs[ $from ] ) ) continue;
							unset( $arcs[ $from ] );
						}
					}
					unset( $arcs );
				}
			}

			$linkElement = null;

			// Look for the corresponding link element
			foreach ( $this->roleTypes as $link => $role )
			{
				if ( ! isset( $role[ $refUri ] ) )
				{
					continue;
				}

				$linkElement = $link;
				break;
			}

			if ( is_null( $linkElement ) )
			{
				continue;
			}

			$description = $this->getGenericLinkRoleDescription( $ref, $linkElement, "Generic link role" );
			$result[ $refUri ]['text'] = $description ? $description : "";

		}

		if ( $sort )
		{
			$this->sortRoleRefs( $result );
		}

		return $result;
	}

	/**
	 * Get the General special links array for a role
	 * @param array|string $filter A list of the roles to include in the result.  If no filter is specified all roles are returned.
	 * @param bool $sort True if the result list should be sorted
	 * @return array
	 */
	public function &getNonDimensionalRoleRefs( $filter = array(), $sort = true )
	{
		// Make sure the filter is initialized and contains lowercase values
		if ( ! is_array( $filter ) ) $filter = array();
		$filter = array_flip( array_map( 'strtolower', $filter ) );

		$refs = $this->context->nonDimensionalRoleRefs;

		/**
		 * @var array $result
		 */
		$result = array();

		foreach ( $refs as $refKey => $ref )
		{
			// Apply the filter if one is provided
			if ( count( $filter ) && ! isset( $filter[ strtolower( $refKey ) ] ) ) continue;

			$result[ $refKey ] = $this->context->nonDimensionalRoleRefs[ $refKey ];
			foreach ( $result[ $refKey ] as $arcroleKey => $arcrole )
			{
				// If the acrole type is defined, get the cycles allowed otherwise set it to be 'none'
				$arcRoleTaxonomy = $this->getTaxonomyForXSD( $arcrole['href'] );
				$arcRoleRefs = $arcRoleTaxonomy ? $arcRoleTaxonomy->getArcroleTypes() : false;
				if ( $arcRoleRefs && isset( $arcRoleRefs['link:definitionArc'][ $arcroleKey ] ) )
				{
					$arcRoleRef = $arcRoleRefs['link:definitionArc'][ $arcroleKey ];
					$result[ $refKey ][ $arcroleKey ]['text'] = isset( $arcRoleRef['description'] ) ? $arcRoleRef['description'] : "";
					$result[ $refKey ][ $arcroleKey ]['cyclesAllowed'] = isset( $arcRoleRef['cyclesAllowed'] ) ? $arcRoleRef['cyclesAllowed'] : "none";
				}
				else
				{
					$result[ $refKey ][ $arcroleKey ]['text'] = "";
					$result[ $refKey ][ $arcroleKey ]['cyclesAllowed'] = "none";
				}

				// Add the description
				$description = $arcRoleTaxonomy->getNonDimensionalArcRoleDescription( $arcroleKey );
				$result[ $refKey ][ $arcroleKey ]['text'] = $description ? $description : "";

				// Remove any prohibited arcs
				if ( isset( $result[ $refKey ][ $arcroleKey ]['arcs'] ) && count( $result[ $refKey ][ $arcroleKey ]['arcs'] ) )
				{
					$arcs =& $result[ $refKey ][ $arcroleKey ]['arcs'];
					foreach ( $arcs as $from => $targets )
					{
						foreach ( $targets as $to => $node )
						{
							if ( $node['use'] == XBRL_Constants::$xlinkUseOptional ) continue;
							unset( $arcs[ $from ][ $to ] );
						}

						if ( count( $arcs[ $from ] ) ) continue;
						unset( $arcs[ $from ] );
					}
				}
			}

			if ( $sort )
			{
				$this->sortRoleRefs( $result[ $refKey ] );
			}

		}

		return $result;
	}

	/**
	 * Get the requireElements array
	 */
	public function getRequireElementsList()
	{
		return $this->context->requireElements;
	}

	/**
	 * Used by extension taxonomy processing to rebuild an index array.
	 */
	public function rebuildLabelsByHRef()
	{
		$labels =& $this->context->labels[ XBRL_Constants::$defaultLinkRole ];
		$locators =& $labels['locators'];

		if ( isset( $labels['arcs'] ) )
		{
			$reverseArcs = array();

			foreach ( $labels['arcs'] as $from => $to )
			{
				foreach ( $to as $labelLabel => $items )
				{
					$reverseArcs[ $labelLabel ] = $from;
				}
			}

			if ( isset( $labels['labels'] ) )
			{
				$labelsByHref = array();

				foreach ( $labels['labels'] as $roleKey => $languageLabels )
				{
					foreach ( $languageLabels as $lang => $langLabels )
					{
						foreach ( $langLabels as $labelLabel => $items )
						{
							if ( ! isset( $items['id'] ) ) continue;

							// Lookup the label in the arcs array
							if ( ! isset( $reverseArcs[ $labelLabel ] ) )
							{
								$this->log()->warning( "Ooops. Label $labelLabel not found in the arcs array" );
								continue;
							}

							$label = $reverseArcs[ $labelLabel ];

							/*
							if ( ! isset( $locators[ $label ] ) )
							{
								$this->log()->warning( "Ooops. Label $label not found in the locators array" );
								continue;
							}

							$parts = explode( '#', $locators[ $label ] );
							*/

							$parts = explode( '#', $label );
							if ( ! isset( $labelsByHref[ $parts[0] ] ) )
							{
								$labelsByHref[ $parts[0] ] = array();
							}

							if ( is_array( $items['id'] ) )
							{
								foreach( $items['id'] as $id )
								{
									$labelsByHref[ $parts[0] ][ $id ][] = array(
										'role'	=> $roleKey,
										'label'	=> $labelLabel,
										'lang'	=> $lang,
									);
								}
							}
							else
							{
								$labelsByHref[ $parts[0] ][ $items['id'] ][] = array(
									'role'	=> $roleKey,
									'label'	=> $labelLabel,
									'lang'	=> $lang,
								);
							}
						}
					}
				}

				$labels['labelshref'] = $labelsByHref;
				unset( $labelsByHref );
			}

			unset( $reverseArcs );
		}

		unset( $locators );
		unset( $labels );
	}

	/**
	 * This function allows a descendent to do something with the information before it is deleted if helpful
	 * This function can be overridden by a descendent class
	 *
	 * @param array $dimensionalNode A node which has element 'nodeclass' === 'dimensional'
	 * @param array $parentNode
	 * @return bool True if the dimensional information should be deleted
	 */
	protected function beforeDimensionalPruned( $dimensionalNode, &$parentNode )
	{
		return true;
	}

	/**
	 * This function provides an opportunity for a taxonomy to sanitize and/or translate a string.
	 * This function can be overridden
	 *
	 * @param string $text The text to be sanitized
	 * @param string $type An optional element type such as num:integer
	 * @param string $language An optional language locale
	 * @return string The sanitized string
	 */
	public function sanitizeText( $text, $type = null, $language = null )
	{
		return $text;
	}

	/**
	 * Whether all roles should be used when collecting primary items, members, dimensions and hypercubes (default: true)
	 * @return bool True if all roles are to be used as the basis for collecting primary items
	 */
	public function useAllRoles()
	{
		return false;
	}

	/**
	 * Provides a descendant implementation a chance to define whether or not primary items are allowed for a node in a presentation hierarchy
	 * In US-GAAP taxonomies, primary items are only relevant when there is a statement table.  Without this check, invalid hypercubes and
	 * dimensions can be added.
	 * @param array $nodes An array of presentation hierarchy nodes
	 * @param string $roleRefKey
	 * @return bool True if primary items are allowed (default: true)
	 */
	protected function primaryItemsAllowed( $nodes, $roleRefKey )
	{
		return true;
	}

	/**
	 * Provides a descendant implementation a chance to define whether or not common hypercubes should be accumulated for a node.
	 * @param array $nodes An array of presentation hierarchy nodes
	 * @param string $roleRefKey
	 * @return bool True if primary items are allowed (default: true)
	 */
	protected function accumulateCommonHypercubesForNode( $nodes, $roleRefKey )
	{
		return true;
	}

	/**
	 * This function analyzes a array of presentation $nodes to assign relevant hypercubes to each node.
	 * Presentation nodes that are only dimensional (happens a lot in the UK GAAP taxonomy)
	 * $nodes are tsgged with an element called 'nodeclass' that will have one of these values:
	 *	simple:			Is not a primaryitem or dimensional node
	 *	dimensional:	The node corresponds to a dimension-domain or domain-member
	 *	primaryitem:	The node is a primary item
	 * The process also adds the following elements:
	 *	dimensions:				The dimensions associated with a primary item
	 *	common:					A list of dimensions that are common to all child nodes that are primary items
	 *	dimensionlessNodes:		A list of the primary items that have no dimensions (simple items don't have dimensions)
	 * @param array $nodes				An array of presentation hierarchy nodes.  Passed by reference.
	 * @param array $locators			An array of locators for a presentation linkbase
	 * @param array $presentationRole	The roles being processes
	 * @param int $indent	  			The depth in the $nodes herarchy of the iteration.  Defaults to zero.
	 * @param int $total	  			The total number of node processed. This is passed by reference (defaults to zero).
	 * @param array $parameters			An array of parameters to be passed to recursive calls.  Will be omitted by an external caller.
	 */
	private function assignNodeHypercubes( &$nodes, $locators, $presentationRole, $indent = 0, &$total = 0, $parameters = array() )
	{
		$taxonomy_base_name = $this->getTaxonomyXSD();
		$result = array();

		// Only set these parameters the first time this function is called not on every resursive entry
		if ( ! count( $parameters ) )
		{
			$parameters['primaryitemsallowed'] = $this->primaryItemsAllowed( $nodes, $presentationRole );

			if ( $this->useAllRoles() ) // Some taxonomies use the same role for presentation and definition linkbases. Others (UK-GAAP) do not.
			{
				$parameters['members']		= $this->getDefinitionDimensionMembers(); // These are element names in a definition linkbase that are not primary items
				$parameters['primaryItems']	= $this->getDefinitionPrimaryItems();
				$parameters['hypercubes']	= $this->getDefinitionHypercubes();
				$parameters['dimensions']	= $this->getDefinitionDimensions();
			}
			else
			{
				$parameters['members']		= $this->getDefinitionRoleDimensionMembers( $presentationRole ); // These are element names in a definition linkbase that are not primary items
				$parameters['primaryItems']	= $this->getDefinitionRolePrimaryItems( $presentationRole );
				$parameters['hypercubes']	= $this->getDefinitionRoleHypercubes( $presentationRole );
				$parameters['dimensions']	= $this->getDefinitionRoleDimensions( $presentationRole );
			}
		}

		// Record the path - useful for debugging
		$parameters['path'] = isset( $parameters['parent'] )
			? (
				( ! empty( $parameters['path'] )
					? $parameters['path'] . "/"
					: ""
				) . $parameters['parent']
			  )
			: '';

		foreach ( $nodes as $nodeKey => &$node )
		{
			$total++;
			// if ( $total > 200 && $total % 100 === 0 ) $this->log()->info(  "$total" );
			// $this->log()->info( str_repeat( " ", $indent * 2 ) . "{$node[ 'label' ]}" );
			$path = "{$parameters['path']}/$nodeKey";
			// uk-bus_EntityInformationHeading/uk-bus_EntityOfficersHeading/uk-bus_NameEntityOfficer

			// if ( isset( $primaryItems[ $node['label'] ] ) )
			// $element_xsd = parse_url( $node['label'], PHP_URL_PATH );
			// $key = "$element_xsd#{$node['taxonomy_element']['id']}";
			$key = $node['label'];

			if ( $parameters['primaryitemsallowed'] && isset( $parameters['primaryItems'][ $key ] ) )
			{
				$node['nodeclass'] = 'primaryitem';
				$node['dt'] = 'p';
			}
			// else if ( isset( $members[ $node['label'] ] ) )
			else if ( isset( $parameters['members'][ $key ] ) )
			{
				$node['nodeclass'] = 'dimensional';
				$node['dt'] = 'm';
			}
			// else if ( isset( $dimensions[ $node['label'] ] ) )
			else if ( isset( $parameters['dimensions'][ $key ] ) )
			{
				$node['nodeclass'] = 'dimensional';
				$node['dt'] = 'd';
			}
			// else if ( isset( $hypercubes[ $node['label'] ] ) )
			else if ( isset( $parameters['hypercubes'][ $key ] ) )
			{
				$node['nodeclass'] = 'simple';
				$node['dt'] = 'h';
			}
			else
			{
				$node['nodeclass'] = 'simple';
			}

			if ( $node['nodeclass'] === 'primaryitem' )
			{
				// $node['nodeclass'] = 'simple'; // Assume the worst

				$primaryItem = $parameters['primaryItems'][ $key ];

				if ( ! isset( $primaryItem['roles'] ) )
				{
					$hypercubeRoles = array( $primaryItem['roleUri'] );
				}
				else
				{
					$hypercubeRoles = $primaryItem['roles'];
				}

				$node['nodeclass'] = 'primaryitem'; // Found at least one
				$hypercubes = isset( $node['hypercubes'] ) ? $node['hypercubes'] : array();
				foreach ( $hypercubeRoles as $roleUri )
				{
					if ( ! $this->useAllRoles() && $roleUri !== $presentationRole ) continue;

					$roleHypercubes = $this->getPrimaryItemDRSForRole( array( $roleUri => $primaryItem, 'roles' => array( $roleUri ) ), $roleUri );

					foreach ( $roleHypercubes as $hypercubeId => &$roles )
					{
						if ( ! isset( $roles[ $roleUri ] ) )
						{
							continue;
						}

						$hypercube = &$roles[ $roleUri ];

						if ( ! isset( $hypercube['parents'][ $key ] ) )
						{
							// Should never happen
							$this->log()->warning( "The parents of hypercube '$hypercubeId' do not include primary item '$key' and this should never happen" );
							continue;
						}

						$hypercubes[ $hypercubeId ] = array(
							'namespace' => $hypercube['namespace'],
							'role' => $hypercube['role'],
							'href' => $hypercube['href'],
							'dimensioncount' => count( $hypercube['dimensions'] ),
							'hasdefaults' => array_reduce($hypercube['dimensions'], function( $carry, $dim ) {
								if ( $carry ) return true;
								return isset( $dim['default'] );
							}, false ),
							'closed' => isset( $hypercube['parents'][ $key ]['closed'] ) ? $hypercube['parents'][ $key ]['closed'] : false,
						);

						if ( isset( $node['preferredLabel'] ) )
						{
							$hypercubes[ $hypercubeId ]['preferredLabel'] = $node['preferredLabel'];
						}

					}
				}
				$node['hypercubes'] = $hypercubes;
				$result += $hypercubes; // Build a list of all the hypercubes used by the role
			}

			if ( ! isset( $node['children'] ) ) continue;
			$parameters['parent'] = $nodeKey;
			$result += $this->assignNodeHypercubes( $node['children'], $locators, $presentationRole, $indent + 1, $total, $parameters );

			// Double-check the child nodes.  If the node is simple but all of nodes are 'dimensional' then is really dimensional and should be flagged for removal
			if ( $node['nodeclass'] === 'simple' )
			{
				$isDimensional = true;
				foreach ( $node['children'] as $childNodeKey => $childNode )
				{
					if ( ! isset( $childNode['nodeclass'] ) || $childNode['nodeclass'] !== 'dimensional' )
					{
						$isDimensional = false;
						break;
					}
				}

				if ( $isDimensional ) $node['nodeclass'] = 'dimensional';
			}

			// If the node is now dimensional ignore
			if ( $node['nodeclass'] === 'dimensional' ) continue;

			// If the node is not allowed to accumulate hypercubes, continue
			if ( ! $this->accumulateCommonHypercubesForNode( $node, $presentationRole ) ) continue;

			// Get common hypercubes from sub-nodes
			// $this->log()->info( "Common for '{$node['label']}' ({$node['nodeclass']})" );
			// If this node has hypercubes then start with the local hypercubes
			$common = array(); // isset( $node['hypercubes'] ) && count( $node['hypercubes'] ) > 0 ? $node['hypercubes'] : array();
			$hypercubelessNodes = array(); // An array of the labels of the child nodes that are hypercubeless
			$first = true;
			foreach ( $node['children'] as $childNodeKey => $childNode )
			{
				// Do not consider 'dimensional' nodes
				if ( ! isset( $childNode['nodeclass'] ) || $childNode['nodeclass'] === 'dimensional' ) continue;

				// If the child node has hypercubeless children then do not propagate the common items
				if ( isset( $childNode['hypercubelessNodes'] ) && count( $childNode['hypercubelessNodes'] ) > 0 )
					continue;

				// If any one of the child nodes has no hypercubes there can be no common hypercubes at the node level
				if ( ( ! isset( $childNode['hypercubes'] )	|| count( $childNode['hypercubes'] ) === 0 ) &&
					 ( ! isset( $childNode['common'] )		|| count( $childNode['common'] ) === 0 )
				   )
				{
					if ( $childNode['nodeclass'] !== 'simple' )
					{
						// $this->log()->info( "No hypercubes or common items for '{$childNode['label']}' ({$childNode['nodeclass']}) of '{$node['label']}' ({$node['nodeclass']})" );
						$hypercubelessNodes[] = $childNode['label'];
					}
					continue;
				}

				if ( isset( $childNode['hypercubes'] ) )
				{
					// Initialize the common array
					// $dimList = implode( ", ", array_keys( $childNode['hypercubes'] ) );
					// $this->log()->info( "Common for '{$node['label']}' ({$node['nodeclass']}) of '{$childNode['label']}' ({$childNode['nodeclass']}) - $dimList" );
					$common = $first && count( $common ) === 0 ? $childNode['hypercubes'] : array_intersect_key( $common, $childNode['hypercubes'] );

					$first = false;
				}

				if ( isset( $childNode['common'] ) )
				{
					$common = $first && count( $common ) === 0 ? $childNode['common'] : array_intersect_key( $common, $childNode['common'] );
				}

				$first = false;
			}

			if ( count( $hypercubelessNodes ) > 0 )
			{
				$node['hypercubelessNodes'] = $hypercubelessNodes;
				// $common = array();
			}

			if ( count( $common ) === 0 )
			{
				// $this->log()->info( "Common count: zero" );
				continue;
			}

			// Remove the common hypercubes from the hypercubes list of each node
			// $this->log()->info( str_repeat("\t", $indent) . "{$node[ 'label' ]} Remove the common hypercubes from the hypercubes list of each node" );
			foreach ( $node['children'] as $childNodeKey => &$subNode )
			{
				// $parent = reset($subNode['parents']);
				// $this->log()->info( str_repeat("\t", $indent) . "$childNodeKey - {$subNode['label']} - $parent ({$subNode['nodeclass']})" );
				if ( $subNode['nodeclass'] !== 'primaryitem' ) continue;

				foreach ( $common as $commonHypercubeKey => $commonHypercube )
				{
					if ( ! isset( $subNode['hypercubes'] ) ||
						 ! isset( $subNode['hypercubes'][ $commonHypercubeKey ] ) )
						continue;

					unset( $subNode['hypercubes'][ $commonHypercubeKey ] );
					$subNode['hypercubespruned'] = true;
				}
			}

			if ( $indent === 0 && $node['nodeclass'] === 'primaryitem' )
			{
				foreach ( $common as $commonHypercubeKey => $commonHypercube )
				{
					if ( ! isset( $node['hypercubes'] ) || ! isset( $node['hypercubes'][ $commonHypercubeKey ] ) )
						continue;

					unset( $node['hypercubes'][ $commonHypercubeKey ] );
					$node['hypercubespruned'] = true;
				}
			}

			// $dimList = implode( ", ", array_keys( $common ) );
			// $count = count( $common );
			// $this->log()->info( "Common count: $count '{$node['label']}' - $dimList" );
			$node['common'] = $common;

		}

		return $result;
	}

	/**
	 * Removes all nodes that are flagged with the 'nodeclass' 'dimensional' as these are not useful for reporting purposes.
	 * Returns an array summarizing the presentation node hierarchy that can be used to visualize the hierarchy. This can be
	 * passed through json_encode() to present is in a JSON viewer.
	 * @param array $nodes An array of presentation hierarchy nodes.  Passed by reference.
	 * @param int $indent  The depth in the $nodes herarchy of the iteration.  Defaults to zero.
	 * @param array $parentNode
	 * @returns An array of hierarchical nodes that can be used to verify the hierarchy.
	 */
	private function pruneNodeHypercubes( &$nodes, $indent = 0, &$parentNode = array() )
	{
		$labelName = 'label';
		$collectionName = 'children';

		$results = array();

		foreach ( $nodes as $nodeKey => &$node )
		{
			// $this->log()->info( str_repeat( "\t", $indent ) . "{$node['label']}" );
			if ( isset( $node['nodeclass'] ) && $node['nodeclass'] === "dimensional" )
			{
				// Copy to a local variable as the static variable cannot be used as a funtion directly
				$callback = self::$beforeDimensionalPrunedDelegate;

				// Calling this function allows a descendent to do something with the information if helpful
				// The function will normally return true but can return false to prevent removal.
				if ( is_callable( $callback ) && ! $callback( $this, $node, $parentNode ) ) continue;
				unset( $nodes [ $nodeKey ] );
				continue;
			}

			$line = "{$node['label']} ({$node['nodeclass']})";
			$results[ $line ] = array();

			if ( isset( $node['dimensionlessNodes'] ) && count( $node['dimensionlessNodes'] ) > 0 )
				$results[ $line ]['dimensionlessNodes'] = $node['dimensionlessNodes'];

			if ( isset( $node['dimensions'] ) && count( $node['dimensions'] ) > 0 )
				$results[ $line ]['dimensions'] = $node['dimensions'];

			if ( isset( $node['common'] ) && count( $node['common'] ) > 0 )
			{
				// $this->log()->info( "There are common" );
				$results[ $line ]['common'] = $node['common'];
			}
			if ( ! isset( $node[ $collectionName ] ) ) continue;

			$results[ $line ]['children'] = $this->pruneNodeHypercubes( $node[ $collectionName ], $indent + 1, $node );
		}

		return  count( $results ) === 0 ? "No dimensions" : $results;
	}

	/**
	 * Called once the linkbase types array has been read and processes each of the linkbase refs found.
	 * @return void
	 */
	private function processLinkbases()
	{
		// If the document does not exist the taxonomy has been loaded from a comiled file
		if ( ! $this->xbrlDocument ) return;
	
		// Begin processing any in the appinfo element
		$this->xbrlDocument->registerXPathNamespace( 'link', XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] );
		$this->xbrlDocument->registerXPathNamespace( 'xlink', XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
		$this->xbrlDocument->registerXPathNamespace( 'xs', SCHEMA_NAMESPACE );
		$linkbases = $this->xbrlDocument->xpath("/xs:schema/xs:annotation/xs:appinfo/link:linkbase");

		foreach ( $linkbases as $linkbase )
		{
			// Look inside

			$arcRole = XBRL_Constants::$arcRoleAll;
			$title = "Custom linkbase";
			$function = 'processCustomLinkbaseXml';
			$usedOn = '';

			foreach ( XBRL_Constants::$standardLinkElements as $linkElement => $value )
			{
				if ( ! count( $linkbase->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->$linkElement ) ) continue;

				switch ( $linkElement )
				{
					case 'definitionLink':

						$arcRole = XBRL_Constants::$DefinitionLinkbaseRef;
						$title = 'Definition linkbase';
						$function = 'processDefinitionLinkbaseXml';
						$usedOn = 'link:definitionLink';

						break;

					case 'calculationLink':

						$arcRole = XBRL_Constants::$CalculationLinkbaseRef;
						$title = 'Calculation linkbase';
						$function = 'processCalculationLinkbaseXml';
						$usedOn = 'link:calculationLink';

						break;

					case 'presentationLink':

						$arcRole = XBRL_Constants::$PresentationLinkbaseRef;
						$title = 'Presentation linkbase';
						$function = 'processPresentationLinkbaseXml';
						$usedOn = 'link:presentationLink';

						break;

					case 'labelLink':

						$arcRole = XBRL_Constants::$LabelLinkbaseRef;
						$title = 'Label linkbase';
						$function = 'processLabelLinkbaseXml';
						$usedOn = 'link:label';

						break;

					case 'referenceLink':

						$arcRole = XBRL_Constants::$ReferenceLinkbaseRef;
						$title = 'Reference linkbase';
						$function = 'processReferenceLinkbaseXml';
						$usedOn = 'link:referenceLink';

						break;
				}
			}

			$linkbaseRef = array(
				'type' => 'simple',
				'href' => $this->schemaLocation,
				'role' => XBRL_Constants::$defaultLinkRole,
				'arcrole' => $arcRole,
				'title' => $title,
				'base' => "",
			);

			$this->$function( $linkbaseRef, $linkbase, basename( $linkbaseRef['href'] ), $usedOn );
		}

		foreach ( $this->linkbaseTypes as $linkbaseTypesKey => &$linkbaseType )
		{
			switch ( $linkbaseTypesKey )
			{
				case XBRL_Constants::$anyLinkbaseRef:
					$this->processPresentationLinkbases( $linkbaseType );
					$this->processLabelLinkbases( $linkbaseType );
					$this->processDefinitionLinkbases( $linkbaseType );
					$this->processCalculationLinkbases( $linkbaseType );
					$this->processReferenceLinkbases( $linkbaseType );
					// Handle custom linkbases
					$this->processCustomLinkbases( $linkbaseType );
					break;

				case XBRL_Constants::$PresentationLinkbaseRef:
					$this->processPresentationLinkbases( $linkbaseType );
					break;

				case XBRL_Constants::$LabelLinkbaseRef:
					$this->processLabelLinkbases( $linkbaseType );
					break;

				case XBRL_Constants::$DefinitionLinkbaseRef:
					$this->processDefinitionLinkbases( $linkbaseType );
					break;

				case XBRL_Constants::$CalculationLinkbaseRef:
					$this->processCalculationLinkbases( $linkbaseType );
					break;

				case XBRL_Constants::$ReferenceLinkbaseRef:
					$this->processReferenceLinkbases( $linkbaseType );
					break;

				default:
					// Handle custom linkbases
					$this->processCustomLinkbases( $linkbaseType );
					break;
			}
		}
	}

	/**
	 * Process the calculation linkbase type.
	 * @param array $linkbaseType (by reference) The link base type to process
	 * @return void
	 */
	private function processCustomLinkbases( &$linkbaseType )
	{
		// $this->log()->info( "Process definition linkbases: " . $this->namespace );
		foreach ( $linkbaseType as $linkbaseRefkey => &$linkbaseRef )
		{
			$this->processCustomLinkbaseArcRoles( $linkbaseRef );
			$linkbaseRef['processed'] = true;
		}
	}

	/**
	 * Standardize on the prefix used by the original schema not the name used by the dependent schema.
	 * For example, the generics schema used the prefix 'gen' but a dependent schema might use the
	 * prefix 'generic' or 'gene' or something else entirely.
	 * @param string $inputQName
	 * @param XBRL $taxonomy
	 * @return string
	 */
	private function normalizePrefix( $inputQName, $taxonomy )
	{
		if ( empty( $inputQName ) )
		{
			return $inputQName;
		}

		$qname = $inputQName instanceof QName
			? $inputQName
			: qname( $inputQName, $this->getDocumentNamespaces() );
		if ( is_null( $qname ) ) return $inputQName;

		$namespaceTaxonomy = $taxonomy->getTaxonomyForNamespace( $qname->namespaceURI );
		if ( $namespaceTaxonomy )
		{
			// There is so normalize the prefix
			return "{$namespaceTaxonomy->getPrefix()}:{$qname->localName}";
		}

		return ! is_null( $qname ) && isset( XBRL_Constants::$standardNamespaces[ $qname->namespaceURI ] )
			? XBRL_Constants::$standardNamespaces[ $qname->namespaceURI ] . ":" . $qname->localName
			: $inputQName;
	}

	/**
	 * Converts the types usedon qname to use the prefix of the original taxonomy
	 *
	 * @param array $ts
	 * @param XBRL $taxonomy
	 * @return void
	 */
	private function normalizeUsedOn( &$ts, &$taxonomy )
	{
		foreach ( $ts as $usedOn => $arcroles )
		{
			if ( isset( \XBRL_Constants::$standardArcRoles[ $usedOn ] ) ||
				 \XBRL_Constants::isStandardExtLinkQname( qname( $usedOn, array( 'link' => \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK] ) ) )
			)
			{
				// Nothing to do
				continue;
			}
			else
			{
				// Standardize on the prefix used by the original schema not the name used by the dependent schema.
				// For example, the generics schema used the prefix 'gen' but a dependent schema might use the
				// prefix 'generic' or 'gene' or something else entirely.
				/**
				 * @var QName $usedOnQName
				 */
				$usedOnQName = qname( $usedOn, $taxonomy->getDocumentNamespaces() );

				// Is there a taxonomy for this namespace?
				$usedOnTaxonomy = $taxonomy->getTaxonomyForNamespace( $usedOnQName->namespaceURI );
				if ( $usedOnTaxonomy )
				{
					// There is so normalize the prefix
					$usedOn2 = "{$usedOnTaxonomy->getPrefix()}:{$usedOnQName->localName}";
					// If the prefix was already correct there is nothing to do
					if ( $usedOn == $usedOn2 )
					{
						continue;
					}
					$ts[ $usedOn2 ] = $arcroles;
					unset( $ts[ $usedOn ] );
				}
			}
		}
	}

	/**
	 * Retrieve an array of arcrole types across all taxonomies
	 *
	 * @return array
	 */
	public function getAllArcRoleTypes()
	{
		// Gather all arcrole types
		return array_reduce( $this->context->importedSchemas, function( $carry, $taxonomy )
		{
			$arts = $taxonomy->getArcroleTypes();
			$taxonomy->normalizeUsedOn( $arts, $taxonomy );

			if ( count( $carry ) )
			{
				foreach ( $arts as $usedOn => $arcroles )
				{
					if ( isset( $carry[ $usedOn ] )  )
					{
						foreach ( $arcroles as $arcrole => $details )
						{
							if ( ! isset( $carry[ $usedOn ][ $arcrole ] ) )
							{
								$carry[ $usedOn ][ $arcrole ] = $details;
							}
						}
					}
					else
					{
						$carry[ $usedOn ] = $arcroles;
					}
				}
			}
			else
			{
				$carry = $arts;
			}
			return $carry;
		}, array() );
	}

	/**
	 * Retrieve an array of arcrole types across all taxonomies
	 *
	 * @return array
	 */
	public function getAllDimensions()
	{
		// Gather all arcrole types
		return array_reduce( $this->context->importedSchemas, function( $carry, /** @var XBRL $taxonomy */ $taxonomy )
		{
			$dimensionNames = $taxonomy->getElementDimensions();
			$dimensionElements = array();

			foreach ( $dimensionNames as $dimensionName )
			{
				$element = $taxonomy->getElementByName( $dimensionName );
				$element['namespace'] = $taxonomy->getNamespace();
				$dimensionElements[ "{$taxonomy->getTaxonomyXSD()}#{$element['id']}" ] = $element;
			}

			$carry = array_merge( $carry, $dimensionElements );

			return $carry;
		}, array() );
	}

	/**
	 * Retrieve an array of linkbase role types across all taxonomies
	 *
	 * @return array
	 */
	public function getAllLinkbaseRoleTypes()
	{
		// Gather all role types
		return array_reduce( $this->context->importedSchemas, function( $carry, /** @var XBRL $taxonomy */ $taxonomy )
		{
			$lrts = $taxonomy->getLinkbaseRoleTypes();

			foreach ( $lrts as $linkbase => $definition )
			{
				if ( isset( $carry[ $linkbase ] ) )
				{
					$carry = array_merge( $carry[ $linkbase ], $definition );
				}
				else
				{
					$carry[ $linkbase ] = $definition;
				}
			}

			return $carry;

		}, array() );
	}

	/**
	 * Retrieve an array of role types across all taxonomies
	 *
	 * @return array
	 */
	public function getAllRoleTypes()
	{
		// Gather all role types
		return array_reduce( $this->context->importedSchemas, function( $carry, /** @var XBRL $taxonomy */ $taxonomy )
		{
			$rts = $taxonomy->getRoleTypes();
			$taxonomy->normalizeUsedOn( $rts, $taxonomy );

			if ( count( $carry ) )
			{
				foreach ( $rts as $usedOn => $roles )
				{
					if ( isset( $carry[ $usedOn ] )  )
					{
						foreach ( $roles as $role => $details )
						{
							if ( ! isset( $carry[ $usedOn ][ $role ] ) )
							{
								$carry[ $usedOn ][ $role ] = $details;
							}
						}
					}
					else
					{
						$carry[ $usedOn ] = $roles;
					}
				}
			}
			else
			{
				$carry = $rts;
			}
			return $carry;
		}, array() );
	}

	/**
	 * Process custom links and populate the $this->context->customRoleRefs variable with locators, arcs and links
	 * @param array $linkbaseRef The link base ref to process
	 * @return boolean
	 */
	public function processCustomLinkbaseArcRoles( $linkbaseRef )
	{
		if ( isset( $linkbaseRef['processed'] ) && $linkbaseRef['processed'] )
		{
			return;
		}

		// $this->log()->info( "Process definition linkbase {$linkbaseRef[ 'href' ]}" );
		// The 'href' may contain an XPointer fragment that specifies a target in the document
		$parts = explode( '#', $linkbaseRef['href'] );
		$linkbaseRef['href'] = $parts[0];
		$xml_basename = pathinfo( $parts[0], PATHINFO_BASENAME );
		$fragment = isset( $parts[1] ) ? $parts[1] : "";

		// BMS 2019-09-11 Suggested by tim-vandecasteele
		//				  see https://github.com/tim-vandecasteele/xbrl-experiment/commit/3610466123ffe936fd45b5a0299fa97baa4699ac
		// only skip when this linkbase was processed already for this taxonomy
		// although it's possible it's already processed for another taxonomy, if you
		// wouldn't process it, it would mean that resources etc. would not be correctly
		// assigned. To make sure that formulas are unique, an explicit check is done
		// when the formula is assigned.
		// if ( isset( $this->context->processedLinkbases[ 'gen:arc:' . $xml_basename ] ) )
		if ( isset( $this->processedLinkbases[ $xml_basename ] ) )
		{
			return;
		}
		else
		{
			$this->processedLinkbases[ $xml_basename ] = true;
		}

		// TODO Change this to use SchemaTypes::resolve_path
		$path = XBRL::resolve_path( $linkbaseRef['href'], $linkbaseRef['base'] . $xml_basename );
		// $path = str_replace( "//", "/", pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ) . "/" . $linkbaseRef['base'] . $xml_basename );
		$xml = XBRL::getXml( $path, $this->context );
		if ( $xml === null )
		{
			return $this->reportMissingLinkbaseFile( $linkbaseRef );
		}

		if ( $fragment )
		{
			// TODO Validate the XPointer fragment and set the $xml variable to the location defined by the fragment
			if ( $this->isPointer( $fragment, $xml, null, $name, $domNode ) )
			{
				if ( $domNode )
				{
					$xml = simplexml_import_dom( $domNode );
					unset( $domNode );
				}
			}
		}


		$this->processCustomLinkbaseXml( $linkbaseRef, $xml, $xml_basename, null );
	}

	/**
	 * Processes a custom linkbase given SimpleXMLElement node
	 * @param array $linkbaseRef
	 * @param SimpleXMLElement $xml
	 * @param string $xml_basename
	 * @param string $usedOn
	 */
	private function processCustomLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn )
	{
		$arcroleRefs = array();
		$roleRefs = array();
		$taxonomy_base_name = $this->getTaxonomyXSD();

		if ( property_exists( $xml->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_INSTANCE ] ), "schemaLocation" ) )
		{
			$linkbaseSchemaLocation = (string)$xml->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA_INSTANCE ] )->schemaLocation;
			$parts = array_filter( preg_split( "/\s/s",  $linkbaseSchemaLocation ) );

			$namespace = "";
			foreach ( $parts as $part )
			{
				if ( empty( $namespace ) )
				{
					$namespace = $part;
				}
				else
				{
					// Only load the schema if it not one of the core ones that are pre-loaded
					$schemaLocation = XBRL::resolve_path( pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ), $part );
					if ( ! isset( $this->context->schemaFileToNamespace[ $schemaLocation ] ) )
					{
						if ( isset( $this->context->importedSchemas[ $namespace ] ) )
						{
							$this->context->schemaFileToNamespace[ $schemaLocation ] = $namespace;
						}
						else if ( ! isset( XBRL_Global::$taxonomiesToIgnore[ $schemaLocation ] ) )
						{
							$result = XBRL::withTaxonomy( $schemaLocation, true );
							$this->indirectNamespaces[] = $result->getNamespace();
							$result->AddUserNamespace( $this );
						}
					}

					$namespace = "";
				}
			}
		}

		$arcroleTypes = $this->getAllArcRoleTypes();
		$roleTypes = $this->getAllRoleTypes();

		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->arcroleRef as $arcroleRefKey => $arcroleRef )
		{
			$attributes = $arcroleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$arcroleRefHref = (string) $attributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$arcroleRefParts = explode( "#", $arcroleRefHref );
			if ( ! $arcroleRefParts )
			{
				$arcroleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $arcroleRefParts[0] )
			{
				$arcroleRefParts[0] = $this->getTaxonomyXSD();
				$arcroleRefHref = implode( "#", $arcroleRefParts );
			}

			$arcroleUri = (string) $arcroleRef->attributes()->arcroleURI;
			$parts = explode( '#', $arcroleRefHref );
			if ( ! $parts[0] ) $parts[0] = $this->getTaxonomyXSD();

			if ( isset( XBRL_Global::$taxonomiesToIgnore[ $parts[0] ] ) )
			{
				continue;
			}

			// The arcrole href MUST be absolute
			if ( ! preg_match( "!^https?://!", $arcroleUri ) )
			{
				$this->log()->taxonomy_validation( "Generics 2.1.1", "The arcrole MUST be absolute",
					array(
						'arcrole' => $arcroleUri,
						'error' => 'xbrlgene:nonAbsoluteArcRoleURI'
					)
				);
				continue;
			}

			$taxonomy = $this->getTaxonomyForXSD( $parts[0] );
			if ( ! $taxonomy )
			{
				if ( count( $parts ) == 1 )
				{
					$this->log()->taxonomy_validation( "5.1.4.4", "Cannot locate the schema for the arcroleref",
						array(
							'href' => "'$arcroleRefHref'",
							'linkbase' => "'$xml_basename'",
							'arcroleref' => "'$arcroleUri'",
						)
					);
					continue;
				}

				// $href = XBRL::resolve_path( $linkbaseRef['href'], $parts['path'] );
				$href = XBRL::resolve_path( pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ), $parts[0] );
				XBRL::withTaxonomy( $href, true ); // BMS 2017-04-03 Should probably use XBRL::WithTaxonomy
				$taxonomy = $this->getTaxonomyForXSD( basename( $parts[0] ) );
				if ( ! $taxonomy )
				{
					$this->log()->warning( "The schema ('{$parts[0]}') specified arcrole '$arcroleUri' does not exist.  The linkbase content that makes use of elements defined in this schema cannot be read." );
					continue;
				}

				$this->indirectNamespaces[] = $taxonomy->getNamespace();
				$taxonomy->AddUserNamespace( $this );

				// Re-build the lists in case there are new roles and arcroles
				$arcroleTypes = $this->getAllArcRoleTypes();
				$roleTypes = $this->getAllRoleTypes();
			}

			// This role MUST be defined as 'usedOn' in the linkbaseRef for link:definitionArc or for one the of the element arc types
			$validArcType = function( $arcroleUri ) use( &$arcroleTypes, &$validArcType, &$taxonomy )
			{
				if ( ! count( $arcroleTypes ) ) return false;
				// BMS 2020-09-24 Don't understand the reason for this line
				//				  It appears to mean that if any one arcrole allows link:definitionArc then all arcs are valid?
				if ( isset( $arcroleTypes['link:definitionArc'][ $arcroleUri ] ) ) return 'link:definitionArc';

				// Look for $arcroleUri as a child of one of the elements in $arcroleTypes
				foreach ( $arcroleTypes as $usedOn => $arcRoles )
				{
					if ( isset( $arcRoles[ $arcroleUri ] ) )
					{
						return $usedOn;
					}
				}

				return false;
			};

			if ( ( $usedOn = $validArcType( $arcroleUri ) ) === false )
			{
				if ( XBRL::isValidating() )
				{
					$this->log()->taxonomy_validation( "5.1.4.4", "This arcrole is not defined to be used on a custom linkbase",
						array(
							'arcrole' => $arcroleUri,
						)
					);
				}

				continue;
			}

			$parts['usedOn'] = $usedOn;
			$arcroleRefs[ $arcroleUri ] = $parts;

			if ( ! isset( $this->context->processedLinkbases[ "$usedOn:$xml_basename" ] ) )
			{
				$this->context->processedLinkbases[ "$usedOn:$xml_basename" ] = array( 'linkbase' => $xml_basename, 'usedOn' => $usedOn );
			}
		}

		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->roleRef as $roleRefKey => $roleRef )
		{
			$attributes = $roleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$roleRefHref = (string)$attributes->href;
			$roleUri = (string) $roleRef->attributes()->roleURI;
			$parts = explode( '#', $roleRefHref );

			if ( XBRL::isValidating() )
			{
				if ( isset( $roleRefs[ $roleUri ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one roleRef element with the same @roleURI attribute value",
						array(
							'role' => $roleUri,
							'usedon' => $usedOn,
							'href' => $xml_basename,
						)
					);
				}
			}

			if ( isset( XBRL_Global::$taxonomiesToIgnore[ $parts[0] ] ) )
			{
				continue;
			}

			// The arcrole href MUST be absolute
			if ( ! preg_match( "!^https?://!", $roleUri ) )
			{
				$this->log()->taxonomy_validation( "Generics 2.1.1", "The role MUST be absolute",
					array(
						'arcrole' => $roleUri,
						'error' => 'xbrlgene:nonAbsoluteLinkRoleURI'
					)
				);
				// continue;
			}

			$taxonomy = $this->getTaxonomyForXSD( $parts[0] );

			if ( ! $taxonomy )
			{
				if ( count( $parts ) == 1 )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "Cannot locate the schema for the roleref",
						array(
							'href' => "'$arcroleRefHref'",
							'linkbase' => "'$xml_basename'",
							'roleUri' => "'$roleUri'",
						)
					);
					continue;
				}

				// $href = XBRL::resolve_path( $linkbaseRef['href'], $parts['path'] );
				$href = XBRL::resolve_path( str_replace( "//", "/", pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ) . "/" ), $parts[0] );
				XBRL::withTaxonomy( $href, true ); // BMS 2017-04-03 Should probably use XBRL::WithTaxonomy
				$this->indirectNamespaces[] = $taxonomy->getNamespace();
				if ( ! $taxonomy )
				{
					$this->log()->warning( "The schema ('{$parts[0]}') specified arcrole '$arcroleUri' does not exist.  The linkbase content that makes use of elements defined in this schema cannot be read." );
					continue;
				}

				$taxonomy = $this->getTaxonomyForXSD( basename( $parts[0] ) );
				$taxonomy->AddUserNamespace( $this );

				// Re-build the lists in case there are new roles and arcroles
				$arcroleTypes = $this->getAllArcRoleTypes();
				$roleTypes = $this->getAllRoleTypes();
			}

			// This role MUST be defined as 'usedOn' in the linkbaseRef for link:definitionArc or for one the of the element arc types
			$validRoleType = function( $roleUri ) use( &$roleTypes, &$validRoleType, &$taxonomy )
			{
				if ( ! count( $roleTypes ) ) return false;
				if ( isset( $roleTypes['link:definitionLink'][ $roleUri ] ) ) return 'link:definitionLink';

				// Look for $roleUri as a child of one of the elements in $roleTypes
				foreach ( $roleTypes as $usedOn => $roles )
				{
					if ( isset( $roles[ $roleUri ] ) )
					{
						return $usedOn;
					}
				}

				return false;
			};

			if ( ( $usedOn = $validRoleType( $roleUri ) ) === false )
			{
				if ( XBRL::isValidating() )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "This role is not defined to be used on a custom linkbase",
						array(
							'role' => $roleUri,
						)
					);
				}

				continue;
			}

			$parts['usedOn'] = $usedOn;
			$roleRefs[ $roleUri ] = $parts;
		}

		$linkElements = null; // $this->context->types->getElementsInSubstitutionGroup( "xl:extended" );
		// // Remove the standard link elements such as presentationLink
		// array_shift( $linkElements );
		// $linkElements = array_filter( $linkElements, function( $qname )
		// {
		//	return ! XBRL_Constants::isStandardExtLinkQname( $qname );
		// } );

		if ( XBRL::$validating )
		{
			foreach ( $arcroleRefs as $arcroleUri => $arcroleDetails )
			{
				// Build an array of 'arcroles' from the arc types
				$arcroles = array_flip( array_unique( array_reduce( array_map( 'array_keys', $arcroleTypes ), 'array_merge', array() ) ) );
				if ( ! isset( $arcroles[ $arcroleUri] ) )
				{

					$this->log()->taxonomy_validation( "Generics 2.1.1", "Arcrole ref does not reference a valid arcrole type ",
						array(
							'arcrole' => $arcroleUri,
							'error' => 'xbrlgene:missingRoleRefForArcRole'
						)
					);

				}
			}
		}

		$this->processCustomLinkbase( $linkElements, $roleTypes, $arcroleTypes, $roleRefs, $arcroleRefs, $xml, $linkbaseRef );

		global $use_xbrl_functions;
		if ( $use_xbrl_functions )
		{
			$hasFormulas = false;

			$result = $this->getGenericResource( 'variableset', null, function( $roleUri, $linkbase, $variableSetName, $index, $resource ) use( &$hasFormulas )
			{
				$hasFormulas = true;
				return false;
			} );

			$this->hasFormulas |= $hasFormulas;
		}

	}

	/**
	 * Get the generic label resource for the give role, label and language
	 * @param string $searchRole
	 * @param string $searchLabel
	 * @param string $searchLang
	 * @param string $linkPath
	 * @param string $linkbase
	 * @return boolean|mixed[][]
	 */
	public function getGenericLabel( $searchRole, $searchLabel = null, $searchLang = null, $linkPath = null, $linkbase = null )
	{
		if ( ! isset( $this->genericRoles['roles'] ) ) return false;

		$results = array();

		if ( ! isset( $this->genericRoles['roles'][ $searchRole ]['labels'] ) ) return $results;

		foreach ( $this->genericRoles['roles'][ $searchRole ]['labels'] as $lb => $labels )
		{
			if ( ! is_null( $linkbase ) && $lb != $linkbase ) continue;

			foreach ( $labels as $label => $langs )
			{
				if ( ! is_null( $searchLabel ) && $label != $searchLabel ) continue;

				foreach ( $langs['lang'] as $lang => $text )
				{
					if ( ! is_null( $searchLang ) && $lang != $searchLang ) continue;

					$results[ $label ] = array(
						'linkbase' => $lb,
						'label' => $label,
						'lang' => $lang,
						'text' => isset( $text[ $linkPath ] ) ? $text[ $linkPath] : reset( $text ),
					);
				}

				if ( ! is_null( $searchLabel ) )
				{
					return $results;
				}
			}
		}

		return $results;
	}

	/**
	 * Retrieves a list of resources for the generic resource type and sub type requested.  For example 'variable' and 'filter'.
	 * @param string $resourceType 	This is required and will be variable, variableset or filter
	 * @param string|null $resourceSubType	This value will be a valid sub type for the resource type passed in $resourceType
	 * 									such as 'fact' for 'variable' or 'formula' for 'variableset'
	 * 									If null then any resource of the type $resourceType will be returned.
	 * @param Closure $callback	(optional) A callback to process results (see below)
	 * @param string $roleUri		(optional)
	 * @param string $label			(optional)
	 * @param string $linkbase		(optional) Filter based on the linkbase in which the resource should appear
	 * @return boolean|array[]
	 *
	 * Callback will return true if no further processing is required. The arguments passed to the callback are:
	 * $roleUri, $linkbase, $resourceName, $index, $resource
	 */
	public function getGenericResource( $resourceType, $resourceSubType, $callback = null, $roleUri = null, $label = null, $lb = null )
	{
		if ( ! isset( $this->genericRoles['roles'] ) ) return false;

		$results = array();

		foreach ( ( $roleUri ? array( $roleUri => $this->genericRoles['roles'][ $roleUri ] ) : $this->genericRoles['roles'] ) as $roleUri => $role )
		{
			if ( ! isset( $role['resources'] ) ) continue;

			if ( ! is_null( $lb ) && ! isset( $role['resources'][ $lb ] ) ) continue;

			foreach ( $lb ? array( $lb => $role['resources'][ $lb ] ) : $role['resources'] as $linkbase => $linkbaseResources )
			{
				if ( ! is_null( $label ) && ! isset( $linkbaseResources[ $label ] ) ) continue;

				foreach ( ( $label ? array( $label => $linkbaseResources[ $label ] ) : $linkbaseResources ) as $resourceName => $resourceGroup )
				{
					foreach ( $resourceGroup as $index => $resource )
					{
						if ( $resource['type'] !== $resourceType ) continue;

						if ( ! is_null( $resourceSubType ) )
						{
							if ( ! isset( $resource[ $resourceType . "Type"] ) ) continue;
							if ( $resource[ $resourceType . "Type" ] != $resourceSubType ) continue;
						}

						if ( $callback )
						{
							// Will return 'true' to continue processing or 'false'
							$result = $callback( $roleUri, $linkbase, $resourceName, $index, $resource );
							if ( ! $result ) return false;
						}
						else
						{
							$results[] = array(
								'roleUri' => $roleUri,
								'linkbase' => $linkbase,
								'resourceName' => $resourceName,
								'index' => $index,
								$resourceType => $resource
							);
						}
					}
				}
			}
		}

		return $results;
	}

	public function removeGenerics()
	{
		$this->genericRoles = array();
		$this->customArcsAdded['genericRoles'] = array();
	}

	/**
	 * Update a specific indexed generic resource
	 * Mainly used to update formulas filter
	 * @param string $roleUri
	 * @param string $linkbase
	 * @param string $resourceName
	 * @param int $index
	 * @param array $resource
	 */
	public function updateGenericResource( $roleUri, $linkbase, $resourceName, $index, $resource )
	{
		$this->genericRoles['roles']
				[ $roleUri ]
				['resources']
				[ $linkbase ]
				[ $resourceName ]
				[ $index ] = $resource;
	}

	/**
	 * Returns a list of the concepts used in formulas defined in taxonomies
	 * @param string|array $namespace A taxonomy namespace or an array of namespaces to restrict the query
	 * @return QName[]
	 */
	function getFormulaConcepts( $namespace = null )
	{
		// This is an array with elements 'qname' and 'usedBy' indexed by the clark notation of the qname.  'usedBy' is a reference into $variableSets
		$concepts = array();
		/**
		 * Is a list of elements that includes 'test', 'text' and 'label' where:
		 *	test: the test defined on an assertion
		 *	text: a description provided with the assertion definition
		 *	label: the label of the respective assertion
		 * @var array $variableSets
		 */
		$variableSets = array();

		$formulas = new \XBRL_Formulas();
		if ( $formulas->processFormulasForTaxonomy( $this, null, null, null, false ) )
		{
			$variableSetIndex = array();
			$concepts = array();
			foreach( $formulas->getVariableSets() as $variableSetLabel => $variableSets )
			{
				foreach( $variableSets as $variableSet )
				{
					/** @var VariableSet $variableSet */
					$key = "{$variableSet->linkbase}/{$variableSet->id}";
					if ( ! isset( $variableSetIndex[ $key ] ) )
					{
						$variableSetIndex[ $key ] = array(
							'test' => $variableSet->test,
							'text' => array( $variableSet->description ),
							'label' => $variableSet->label
						);
					}

					foreach( $variableSet->variablesByQName as $qname => $variable )
					{
						foreach( $variable->filters as $filter )
						{
							/** @var ConceptName $filter */
							if ( ! ( $filter instanceof ConceptName ) ) continue;
	
							foreach( $filter->qnames as $clark )
							{
								if ( isset( $concepts[ $clark ] ) ) 
								{
									$concepts[ $clark ]['usedBy'][] = $key;
									continue;
								}
								$qname = qname( $clark, $this->getDocumentNamespaces() );
								$concepts[ $clark ] = array(
									'qname' => $qname,
									'usedBy' => array( $key )
								);
							}
						}
					}
				}
			}
		}
	
		return array( $concepts, $variableSetIndex );

		// The code below yields thesame results a bit quicker but is more fragile and it's less obvious what's going on.
		foreach( $this->context->importedSchemas as $schemaNamespace => $schema )
		{
			/** @var \XBRL $schema */

			if ( $namespace )
			{
				if ( is_array( $namespace ) )
				{
					if ( array_search( $schemaNamespace, $namespace ) === false ) continue;
				}
				else if ( $namespace != $schemaNamespace ) continue;
			}

			if ( ! isset( $schema->genericRoles ) || ! count( $schema->genericRoles ) ) continue;

			$link =& $schema->genericRoles['roles'][ \XBRL_Constants::$defaultLinkRole ];
			$linkArcRoles = $link['arcroles'];
			$filterArcs =& $linkArcRoles[ \XBRL_Constants::$arcRoleVariableFilter ]['links'][ \XBRL_Constants::$genLink ]['arcelements'][ \XBRL_Constants::$linkVariableFilterArc ]['arcs'];
			$variableArcs =& $linkArcRoles[ \XBRL_Constants::$arcRoleVariableSet ]['links'][ \XBRL_Constants::$genLink ]['arcelements'][ \XBRL_Constants::$linkVariableArc ]['arcs'];
			// $labels =& $schema->genericRoles['roles'][ \XBRL_Constants::$genericRoleLabel ]['labels'];
			$labelArcs =& $linkArcRoles[ \XBRL_Constants::$genericElementLabel ]['links'][ \XBRL_Constants::$genLink ]['arcelements'][ \XBRL_Constants::$genArc ]['arcs'];

			foreach ( $filterArcs as $variableLabel => $filters )
			{
				foreach ( $filters as $filter => $filterDetails )
				{
					foreach ( $filterDetails as $filterDetail )
					{
						$fromLinkbase = $filterDetail['fromlinkbase'];
						$thisVariableSets = array();

						foreach( $link['resources'][ $fromLinkbase ] as $variables )
						{
							foreach( $variables as $variable )
							{
								if ( $variable['type'] == 'variableset' )
								{
									$labels = array();
									foreach( $labelArcs[ $variable['label'] ] as $variableArcLabel => $variableArcLabels )
									{
										/** @var string|false */
										$variableArcPath = false;

										foreach( $variableArcLabels as $varibleArc )
										{
											if ( $varibleArc['linkbase'] == $fromLinkbase )
											{
												$variableArcPath = $variable['path'];
												break;
											}
										}
										$label = $schema->getGenericLabel( XBRL_Constants::$genericRoleLabel, $variableArcLabel, 'en', $variableArcPath, $fromLinkbase );
										$labels[] = trim( $label[ $variableArcLabel ]['text'] ?? '' );
										unset( $label );
									}

									$key = "$fromLinkbase/{$variable['id']}";
									$thisVariableSets[ $key ] = $variableSets[ $key ] = array(
										'test' => $variable['test'],
										'text' => $labels,
										'label' => $variable['label']
									);
									unset( $key );
								}
							}
						}

						// Add the qname of any ConceptName filters to the list of $concepts
						foreach ( $link['resources'][ $fromLinkbase ][ $filter ] as $resourceFilterDetail )
						{
							foreach ( $resourceFilterDetail['qnames'] as $qname )
							{
								// if ( array_search( $qname, $concepts ) !== false ) continue;
								if ( ! isset( $concepts[ $qname ] ) )
								{
									$concepts[ $qname ] = array( 'qname' => qname( $qname, $schema->getDocumentNamespaces() ), 'usedBy' => array() );
								}

								foreach( $thisVariableSets as $variableSetLabel => $variableSet )
								{
									$label = $variableSet['label'];
									if ( isset( $variableArcs[ $label ][ $variableLabel ] ) )
									{
										$concepts[ $qname ][ 'usedBy' ][] = $variableSetLabel;
									}
								}

								unset( $variableSetLabel );
								unset( $variableSet );
							}
							unset( $qname );
						}
						unset( $thisVariableSets );
						unset( $resourceFilterDetail );
					}
				}
			}
		}

		return array( $concepts, $variableSets );
	}

	/**
	 * Get an arc for an arcrole and an optional resource.  If the label of a source resource
	 * is not supplied all resources associated with an arc role are returned
	 * @param string $arcRole
	 * @param string $fromRoleUri
	 * @param string $fromResourceName	(optional) The name (label) of the source of the arc
	 * @param string $fromPath			(optional) The path of source of the arc to be filtered
	 * @param string $arcLinkbase		(optional) The linkbase of the arc definition
	 * @param string $fromLinkbase		(optional) The linkbase of the source of the arc to be filtered
	 * @param string $toLinkbase		(optional) The linkbase of the target of the arc to be filtered
	 * @return array
	 */
	public function getGenericArc( $arcRole, $fromRoleUri, $fromResourceName = null, $fromPath = null, $arcLinkbase = null, $fromLinkbase = null, $toLinkbase = null )
	{
		if ( ! isset( $this->genericRoles['roles'] ) ) return false;

		$results = array();

		foreach ( $this->genericRoles['roles'] as $roleUri => $role )
		{
			if ( ! isset( $role['arcroles'] ) || ! isset( $role['arcroles'][ $arcRole ] ) ) continue;

			foreach ( $role['arcroles'][ $arcRole ]['links'] as $linkName => $arcElements )
			{
				foreach ( $arcElements['arcelements'] as $arcName => $arcs )
				{
					foreach ( $arcs['arcs'] as $fromLabel => $tos )
					{
						if ( ! is_null( $fromResourceName ) && $fromLabel != $fromResourceName ) continue;

						foreach ( $tos as $toLabel => $to )
						{
							foreach ( $to as $arc )
							{
								if ( $arc['fromRoleUri'] != $fromRoleUri ) continue;
								if ( ! is_null( $fromPath ) && $fromPath != $arc['frompath'] ) continue;
								if ( ! is_null( $arcLinkbase ) && $arcLinkbase != $arc['linkbase'] ) continue;
								if ( ! is_null( $fromLinkbase ) && $fromLinkbase != $arc['fromlinkbase'] ) continue;
								if ( ! is_null( $toLinkbase ) && $toLinkbase != $arc['tolinkbase'] ) continue;
								$results[] = $arc;
							}
						}
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Process custom links and populate the $this->context->customRoleRefs variable with locators, arcs and links
	 * @param array[QName] $links A list of QName values that are valid link names
	 * @param array[QName] $roleTypes
	 * @param array[QName] $arcroleTypes
	 * @param array[string] $roleRefs
	 * @param string[] $arcroleRefs
	 * @param SimpleXMLElement $linkbase // The linkbase root element
	 * @param array $linkbaseRef
	 * @return boolean
	 *
	 * This function is the most recently implemented linkbase processor.  So where it is different to other linkbase
	 * processors this one is probably using better implementations of underlying XML processing.  Take care if you
	 * choose to change this function to behave like other linkbase processors.
	 *
	 * This function is pretty complicated because it has to take care of all the generics stuff.  Generics is the base
	 * specification for the definition of formulas so it becomes very important.  It's complicated too because it must
	 * handle the inherent flexibility of XML and XLink.  For example, an arc to a label can be from another arc.  Not
	 * sure what this means from an application point of view but it is permitted so must be supported this (see test
	 * 70111 for an example).
	 *
	 * The tests for this function are in three places:
	 *
	 * The main set of tests are in the 70000 series tests from the formula conformance suite;
	 * There is a generic example based on the example given in the generics specification; and
	 * There are some of the tests in the functions registry that rely on custom and generic links (see 90512-90514)
	 *
	 * Linkbase information is stored either in the 'customRoles' property (see getCustomRoles) or the 'genericRoles'
	 * property (see getGenericRoles).  Content is stored indexed by the role of the relevant 'link' element.  Under
	 * the role element are elements for 'labels', 'resources' and 'arcroles'.  The arcrole element has nest elements
	 * that ultimately describe each arc.  They are grouped like this so they form valid networks in the DTS.$this
	 *
	 * [roles]
	 *   [<role uri 1>]
	 *     [arcroles]
	 *   	 [<arcrole uri 1]
	 *         [links]
	 *            [<link qname>] (e.g. gen:link)
	 *              [arclements]
	 *                [<arc qname>] (e.g. gen:arc)
	 *                  [arcs]
	 *                     [<from id or label>] (id or label depends upon the source of the arc)
	 *                       [<to id or label>] (id or label depends upon the source of the arc though usually it will be a label)
	 *                         [<various details about the arc]
	 *
	 *     [labels]
	 *       [<linkbase>] (the name of the linkbase containing the label)
	 *         [<label>] (the label of the label)
	 *           [lang]
	 *             [<language code>]
	 *
	 *     [resources]
	 *       [<linkbase>] (the name of the linkbase containing the resource)
	 *         [<label>] (the label of the resource)
	 *
	 * In addition, any elements in the linkbase that have an id attribute are recorded in an element called 'ids' in
	 * the respective linkbase element of the linkbaseTypes property.  From the information recorded for each element
	 * with an id, you can work out what label should be used to access any related resources or labels.
	 *
	 * That is, when looking for an arc, first check if the reference contains a '#'.  If it does then lookup the
	 * the linkbase from the path and the id using the fragment. If the id exists then use the label found to find the
	 * resource or label (which depend upon the arcrole required).  If the path#fragment does not exist as an id
	 * attempt to use the fragment to lookup the label or resource.
	 *
	 */
	public function processCustomLinkbase( $links, $roleTypes, $arcroleTypes, $roleRefs, $arcroleRefs, $linkbase, $linkbaseRef )
	{
		global $use_xbrl_functions;

		$xml_basename = basename( strpos( $linkbaseRef['href'], '#' ) === false ? $linkbaseRef['href'] : strstr( $linkbaseRef['href'], '#', true ) );
		$linkbaseIds = &$this->linkbaseIds;
		$href = $linkbaseRef['href'];

		if ( ! isset( $this->linkbases[ basename( $href ) ] ) )
		{
			$this->linkbases[ basename( $href ) ]['namespaces'] = $linkbase->getDocNamespaces( true );
		}

		$linkbase->registerXPathNamespace("xlink", XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
		$links = $linkbase->xpath('*[@xlink:type="extended"]');

		// First handle all the resources
		foreach( $links as $link )
		{
			$domLink = dom_import_simplexml( $link );
			$linkPath = $this->getXmlNodePath( $link );

			$linkQName = qname( $domLink->tagName, array_merge( $this->context->types->getProcessedSchemas(), $linkbase->getDocNamespaces() ) );
			// BMS 2018-05-05 Fix up the prefix.  Should used the one of the schema of the namespace
			$schemaQName = $this->normalizePrefix( $linkQName, $this );
			$linkQName = qname( $schemaQName, $this->context->types->getProcessedSchemas() );
			$isGeneric = $this->context->types->resolveToSubstitutionGroup( $linkQName, array( "gen:link" ) );

			$xmlAttributes = $link->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML ] );
			$base = $linkbaseRef['base'] . ( property_exists( $xmlAttributes, 'base' ) ? $xmlAttributes->base : "" );
			$linkAttributes = $link->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );

			$roleUri = property_exists( $linkAttributes, "role" )
				? (string)$linkAttributes['role']
				: XBRL_Constants::$defaultLinkRole;

			$domXPath = new DOMXPath( $domLink->ownerDocument );
			$idNodes = $domXPath->query( $linkPath . "/*/@id" );
			$ids = array(); // Record all the ids in the document

			foreach ( $idNodes as $idNode )
			{
				$id = (string)$idNode->value;
				$idNode = $idNode->parentNode;
				$ids[ $id ] = array(
					'namespace' => $idNode->namespaceURI,
					'localname' => $idNode->localName,
					'path'  => $idNode->getNodePath(),
					'role'	=> $roleUri,
				);

				// It's easier to work with nodes using SimpleXML that DOMNode
				$node = simplexml_import_dom( $idNode );

				$xlinkAttributes = $node->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
				$ids[ $id ]['type']	= isset( $xlinkAttributes->type )	? (string)$xlinkAttributes->type	: null;

				switch ( $ids[ $id ]['type'] )
				{
					case "resource":

						$ids[ $id ]['label']   = isset( $xlinkAttributes->label )	? (string)$xlinkAttributes->label	: null;
						break;

					case "arc":

						$ids[ $id ]['from']    = isset( $xlinkAttributes->from )	? (string)$xlinkAttributes->from	: null;
						$ids[ $id ]['to']      = isset( $xlinkAttributes->to )		? (string)$xlinkAttributes->to		: null;
						$ids[ $id ]['arcrole'] = isset( $xlinkAttributes->arcrole ) ? (string)$xlinkAttributes->arcrole : null;
						break;

					default:
						break;
				}
			}

			// Always add the ids, even if there are none so there is record of the linkbase having been processed
			$linkbaseIds[ basename( $href ) ] = isset( $linkbaseIds[ basename( $href ) ] )
				? array_merge( $linkbaseIds[ basename( $href ) ], $ids )
				: $ids;

			// $linkbaseDescription =  ( $isGeneric ? "Generic" : "Custom" ) . " ({$linkQName->prefix}:{$linkQName->localName})";

			// Check the linkbase definition is valid if the role is not the default role
			if ( $isGeneric && XBRL::isValidating() && $roleUri != XBRL_Constants::$defaultLinkRole )
			{
				// The role MUST be absolute
				if ( ! preg_match( "!^https?://!", $roleUri ) )
				{
					$this->log()->taxonomy_validation( "Generics 2.1.1", "The link role MUST be absolute",
						array(
							'role' => $roleUri,
							'error' => 'xbrlgene:nonAbsoluteLinkRoleURI'
						)
					);

					continue;
				}

				// As a non-default role there MUST be a child <link:roleRef>
				if ( ! property_exists( $linkbase->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK] ), "roleRef" ) )
				{
					$this->log()->taxonomy_validation( "Generics 2.1.1", "When a non-standard link role is used there MUST be link:roleRef child element of the linkbase element",
						array(
							'role' => $roleUri,
							'error' => 'xbrlgene:missingRoleRefForLinkRole'
						)
					);

					continue;
				}

				// $roleRef = $linkbase->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK] )->roleRef;

				// Probably need to handle there being multiple roleRef elements
				// if ( ! property_exists( $roleRef->attributes(), "roleURI" ) || (string)$roleRef->attributes()->roleURI != $roleUri )
				if ( ! isset( $roleRefs[ $roleUri ] )  )
				{
					$this->log()->taxonomy_validation( "Generics 2.1.1", "The roleURI of the roleRef of a non-standard link role MUST be link:roleRef element",
						array(
							'role' => $roleUri,
							'error' => 'xbrlgene:missingRoleRefForLinkRole'
						)
					);

					continue;
				}

				// if ( ! $validRoleType( $roleUri, $roleTypes ) )
				if ( ! isset( $roleTypes["{$linkQName->prefix}:{$linkQName->localName}"][ $roleUri ] ) )
				{
					$this->log()->taxonomy_validation( "Generics 2.1.1", "This role is not defined to be used on a generic linkbase",
						array(
							'role' => $roleUri,
							'link' => "{$linkQName->prefix}:{$linkQName->localName}",
							'error' => 'xbrlgene:missingLinkRoleUsedOnValue',
						)
					);

					continue;
				}
			}

			$roleListName = $isGeneric ? "genericRoles" : "customRoles";

			if ( ! isset( $this->{$roleListName}['roles'][ $roleUri ] ) )
			{
				$this->{$roleListName}['roles'][ $roleUri ] = array(
					'type' => 'simple',
					'href' => XBRL::resolve_path( $href, $this->getTaxonomyXSD() ), // $linkbaseRef['href'],
					'roleUri' => $roleUri,
				);
			}

			// Collect any resources.  Resources are elements in the same namespace that are part
			// of the xl:resource substitutionGroup.  Got to do this using XPath because the resource
			// element local name and prefix can be anything and may not be defined in the schema
			// $linkbasePrefix = "";
			// $linkPrefix = "";
			$xlinkPrefix = "";

			foreach ( $link->getDocNamespaces( true ) as $prefix => $namespace )
			{
				if ( empty( $prefix ) ) continue;

				if ( $namespace == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )
				{
					// $linkbasePrefix = "$prefix:";
				}
				else if ( $namespace == \XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] )
				{
					$xlinkPrefix = "$prefix:";
				}
				else if ( $namespace == $linkQName->namespaceURI )
				{
					// $linkPrefix = "$prefix:";
				}
				$link->registerXPathNamespace( $prefix, $namespace );
			}

			$query = $linkPath . "/*[@{$xlinkPrefix}type = 'resource']";
			$nodes = $link->xpath( $query );
			foreach ( $nodes as $childKey => $childElement )
			{
				$domNode = dom_import_simplexml( $childElement );
				$prefix = $domNode->prefix;
				$namespace = $domNode->namespaceURI;

				$xlinkAttributes = $childElement->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );

				$resourceRoleUri = property_exists( $xlinkAttributes, "role" )
					? (string)$xlinkAttributes['role']
					: XBRL_Constants::$defaultLinkRole;

				// $resourceRoleUri = $roleUri;

				// Check the linkbase definition is valid if the role is not the default role
				if ( $isGeneric &&
					 XBRL::isValidating() &&
					 $resourceRoleUri != XBRL_Constants::$defaultLinkRole &&
					 $resourceRoleUri != XBRL_Constants::$labelRoleLabel &&
					 $resourceRoleUri != XBRL_Constants::$reference2003 &&
					 $resourceRoleUri != XBRL_Constants::$reference2008 )
				{
					// If the role exists it MUST be absolute
					if ( ! preg_match( "!^https?://!", $resourceRoleUri ) )
					{
						$this->log()->taxonomy_validation( "Generics 2.1.2", "The resource role is not absolute",
							array(
								'role' => $resourceRoleUri,
								'error' => 'xbrlgene:nonAbsoluteResourceRoleURI',
							)
						);
					}

					// As a non-default role there MUST be a child <link:roleRef>
					if ( count( $roleRefs ) == 0 )
					{
						$this->log()->taxonomy_validation( "Generics 2.1.2", "When a non-standard resource role is used there MUST be link:roleRef child element of the linkbase element",
							array(
								'role' => $resourceRoleUri,
								'error' => 'xbrlgene:missingRoleRefForResourceRole'
							)
						);

						continue;
					}

					if ( ! count( $roleRefs ) )
					{
						$this->log()->taxonomy_validation( "3.5.2.4", "There are no role refs in the linkbase",
							array(
								'error' => 'xbrlgene:missingRoleRefForArcRole'
							)
						);

						break;
					}

					$qName = "$prefix:" . $childElement->getName();

					if ( ! isset( $roleRefs[ $resourceRoleUri ] ) ||
						 ! isset( $roleTypes[ $qName ] ) ||
						 ! isset( $roleTypes[ $qName ][ $resourceRoleUri ] ) )
					{
						$this->log()->taxonomy_validation( "Generics 2.1.2", "The roleURI of the link:roleRef of a non-standard link role MUST be the same as the role of the resource",
							array(
								'role' => $resourceRoleUri,
								'error' => 'xbrlgene:missingRoleRefForResourceRole'
							)
						);
					}
				}

				if ( $use_xbrl_functions )
				{
					if ( \XBRL\Formulas\Formulas::IsFormulaResource( $namespace, $domNode->localName ) )
					{
						$resource = \XBRL\Formulas\Formulas::ProcessFormulaResource( $this, $roleUri, $href, (string)$xlinkAttributes->label, $childElement, $domNode, $this->log() );

						if ( $resource )
						{
							$resource['roleUri'] = $resourceRoleUri; // Record the element role (default if there is none)
							$resource['linkRoleUri'] = $roleUri;
							$resource['path'] = $linkPath;

							if ( $resource['type'] == 'variableset' )
							{
								$resource['base'] = $base . ( isset( $resource['base'] ) ? $resource['base'] : "" );
							}

							// BMS 2019-09-11 Suggested by tim-vandecasteele
							//				  see https://github.com/tim-vandecasteele/xbrl-experiment/commit/3610466123ffe936fd45b5a0299fa97baa4699ac
							// // If the resource has a name make sure the name is unique
							// if ( isset( $resource['name'] ) )
							// If the resource has a name make sure the name is unique unless this linkbase was already processed
							if ( ! isset( $this->context->processedLinkbases[ 'gen:arc:' . $xml_basename ] ) && isset( $resource['name'] ) )
							{
								$name = is_array( $resource['name'] )
									? ( new QName($resource['name']['originalPrefix'], $resource['name']['namespace'], $resource['name']['name'] ) )->clarkNotation()
									: $resource['name']['name'];

								if ( isset( $this->context->formulaNames[ $name ] ) )
								{
									XBRL_Log::getInstance()->formula_validation( "Variable parameters", "The resource name already exists", array(
										'name' => ($resource['name']['originalPrefix'] ? "{$resource['name']['originalPrefix']}:{$resource['name']['name']}" : $resource['name']['name']),
										'error' => 'xbrlve:parameterNameClash'
									) );

									continue;
								}
							}

							$this->{$roleListName}['roles'][ $roleUri ]['resources'][ basename( $href ) ][ (string)$xlinkAttributes->label ][] = $resource;

							if ( isset( $resource['name'] ) )
							{
								$name = is_array( $resource['name'] )
									? ( new QName($resource['name']['originalPrefix'], $resource['name']['namespace'], $resource['name']['name'] ) )->clarkNotation()
									: $resource['name']['name'];

								// Save the information needed to recover the named resource
								$this->context->formulaNames[ $name ] = array(
									'type' => 'resource',
									'taxonomy' => $this->getTaxonomyXSD(),
									'rolelistname' => $roleListName,
									'role' => $roleUri,
									'linkbase' => basename( $href ),
									'path' => $linkPath,
									'label' => (string)$xlinkAttributes->label,
									'offset' => count( $this->{$roleListName}['roles'][ $roleUri ]['resources'][ basename( $href ) ][ (string)$xlinkAttributes->label ] ) -1
								);
							}
						}
						else
						{
							// TODO Report an error
						}

						continue;
					}
				}

				$content = array();
				if ( count( $childElement->children() ) )
				{
					foreach ( $childElement->children() as $child )
					{
						/** @var \SimpleXMLElement $child */
						$content[] = $child->asXML();
					}
				}
				else
				{
					$content[] = (string)$childElement;
				}

				if ( $namespace == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LABEL ] )
				{
					$xmlAttributes = $childElement->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML ] );
					$lang = property_exists( $xmlAttributes, "lang" ) ? (string)$xmlAttributes->lang : $this->getDefaultLanguage();
					$this->{$roleListName}['roles'][ $resourceRoleUri ]['labels'][ basename( $href ) ][ (string)$xlinkAttributes->label ]['lang'][ $lang ][ $linkPath ] = implode( "", $content );
				}
				else
				{
					$x = $roleListName == 'genericRoles'
						? array(
								$namespace == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SEVERITY ]
									? array(
										'type' => 'resource',
										'resourceType' => "severity",
										'label' => (string)$xlinkAttributes->label
									)
									: array(
										'type' => 'resource',
										'text' => implode( "", $content ),
									)
						)
						: implode( "", $content );

					if ( $namespace == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_REFERENCE ] )
					{
						$this->{$roleListName}['roles'][ $resourceRoleUri ]['references'][ basename( $href ) ][ (string)$xlinkAttributes->label ] = $x; // implode( "", $content );
					}
					else
					{
						// $this->{$roleListName}['roles'][ $roleUri ]['resources'][ basename( $href ) ][ (string)$xlinkAttributes->label ] = implode( "", $content );
						$this->{$roleListName}['roles'][ $roleUri ]['resources'][ basename( $href ) ][ (string)$xlinkAttributes->label ] = $x; // implode( "", $content );
					}
				}
			}

			if ( ! isset( $this->{$roleListName}['roles'][ $roleUri ]['attributes'] ) )
			{
				$this->{$roleListName}['roles'][ $roleUri ]['attributes'] = array();
			}

			$linkAttributes =& $this->{$roleListName}['roles'][ $roleUri ]['attributes'];
			$linkCustomAttributes = $link->attributes( $this->getNamespace() );
			foreach ( $linkCustomAttributes as $customAttributeId => $customAttribute )
			{
				// BMS 2018-04-09 Test candidates changed.
				$type = "xs:anyType";
				$attributeType = $this->context->types->getAttribute( $customAttributeId, $this->getPrefix() );
				if ( $attributeType && isset( $attributeType['types'][0] ) )
				{
					$type = $attributeType['types'][0];
				}

				$linkAttributes[ $customAttributeId ] = array( 'type' => $type, 'value' => (string)$customAttribute  );
				unset( $type );
			}
			unset( $linkAttributes );

		} // $links

		// Used to catch duplicated from/to label pairs which is not allowed by the XLink specification
		// $fromToPairs = array();
		$preferredLabelEquivalenceHashes = array();

		// Next handle all the arcs
		foreach( $links as $link )
		{
			$linkAttributes = $link->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$isGeneric = $this->context->types->resolveToSubstitutionGroup( $linkQName, array( "gen:link" ) );
			$linkPath = $this->getXmlNodePath( $link );
			$roleListName = $isGeneric ? "genericRoles" : "customRoles";
			$linkbaseDescription =  $roleListName . " ({$linkQName->prefix}:{$linkQName->localName})";
			$xlinkPrefix = STANDARD_PREFIX_XLINK;

			$roleUri = property_exists( $linkAttributes, "role" )
				? (string)$linkAttributes['role']
				: XBRL_Constants::$defaultLinkRole;

			// Get a list of the locators for this extended link
			// This must come before evaluatiion of arcroles because although there may be
			// no valid arcroles to process for this link, there may be locators and any one
			// of these may reference a schema that must be discovered.
			$locators = $this->retrieveLocators( $link, $linkbaseDescription, $href );

			$arcroleTypesCopy = $arcroleTypes;

			// TODO Look at the schema defintion for the link type to workout what are valid sub-elements
			//      Instead for now use the $arcroleTypes list to see which of the arcs are used.
			$usedOnQNames  = array(); // Used to record the qnames of any used arcs.  Saves looking them up again later.
			foreach ( $arcroleTypesCopy as $usedOn => $arcroleType )
			{
				if ( isset( \XBRL_Constants::$standardArcRoles[ $usedOn ] ) )
				{
					unset( $arcroleTypesCopy[ $usedOn ] );
					continue;
				}

				// The usedOn values have been normalized to the prefixes of the namespaces used in this taxonomy if
				// the namespace is from a taxonomy.  If the taxonomy is not found use the namespaces of the xml document
				$taxonomy = $this->context->getTaxonomyWithPrefix( strstr( $usedOn, ":", true ) );
				$arcQName = qname( $usedOn, $taxonomy ? $taxonomy->getDocumentNamespaces() : $linkbase->getDocNamespaces() );
				$arcs = $link->children( $arcQName->namespaceURI )->{$arcQName->localName};
				if ( count( $arcs ) )
				{
					if ( $this->context->types->resolveToSubstitutionGroup( $arcQName, array( "gen:arc" ) ) )
					{
						$isGeneric = true;
					}
					$usedOnQNames[ $usedOn ] = $arcQName;
				}
				else
				{
					unset( $arcroleTypesCopy[ $usedOn ] );
				}
				unset( $taxonomy );
			}

			if ( ! count( $arcroleTypesCopy ) )
			{
				if ( $isGeneric )
				{
					$query = $linkPath . "/*[@{$xlinkPrefix}:type = 'arc']";
					$nodes = $link->xpath( $query );

					if ( count( $nodes ) )
					{
						$arcs = implode( ",", array_reduce( $nodes, function( $carry, $arc )
						{
							$carry[] = $arc->getName();
							return $carry;
						}, array() ) );
						$this->log()->taxonomy_validation( "Generic links 2.2.1", "One or more arcs in the generic link do not include a 'usedon' element for this link",
							array(
								'link' => $linkQName->clarkNotation(),
								'arcs' => $arcs,
								'error' => 'xbrlgene:missingArcRoleUsedOnValue',
								'linkbase' => $xml_basename
							)
						);
					}
				}

				continue;
			}

			if ( XBRL::isValidating() && $isGeneric )
			{
				// Look for arcs to check there is an arcRoleRef for each arc role used
				$query = $linkPath . "/*[@{$xlinkPrefix}:type = 'arc']";
				$arcNodes = $link->xpath( $query );
				$arcroles = array_unique( array_filter( array_map( function( $node ) {
					$attributes = $node->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
					return isset( $attributes['arcrole'] ) ? (string)$attributes['arcrole'] : '';
				}, $arcNodes ) ) );

				$x = array_diff( $arcroles, array_keys($arcroleRefs) );
				if ( count( $x ) )
				{
					$this->log()->taxonomy_validation( "2.2.1", "The arcrole used on an arc element MUST be referenced in an arcroleRef element.", array( 'arcroles' => implode( ", ", $arcroles ) ) );
				}
			}

			$linkQName = qname( $domLink->tagName, array_merge( $this->context->types->getProcessedSchemas(), $linkbase->getDocNamespaces() ) );
			// BMS 2018-05-05 Fix up the prefix.  Should used the one of the schema of the namespace
			$schemaQName = $this->normalizePrefix( $linkQName, $this );
			$linkQName = qname( $schemaQName, $this->context->types->getProcessedSchemas() );

			// Detection of duplicate from/to pairs only applies within an extended link so reset this varaible in each new link
			$this->resetValidateFromToArcPairs();

			foreach ( $arcroleTypesCopy as $usedOn => $arcroleType )
			{
				$arcQName = $usedOnQNames[ $usedOn ];
				$preferredLabelArcs = array();

				$processArc = function( $arc, $isPreferredLabelArc = false ) use(
					$arcroleRefs, $arcQName, $arcroleType,
					&$preferredLabelEquivalenceHashes, $href,
					$isGeneric, $linkbaseDescription,
					&$linkbaseIds, $linkQName, $locators,
					&$preferredLabelArcs, $roleListName, $roleRefs,
					$roleUri, $usedOn /* , &$fromToPairs */, $linkPath, $xml_basename
				)
				{
					$domNode = dom_import_simplexml( $arc );

					$xlinkAttributes = $arc->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
					$attributes = $arc->attributes();
					$arcrole = (string) $xlinkAttributes->arcrole;

					// BMS 2018-08-25 References are not supported so element-reference roles are not supported
					if ( $arcrole == XBRL_Constants::$genericElementReference )
					{
						return true;
					}

					$preferredLabelRole = (string)$arc->attributes( XBRL_Constants::$genericPreferredLabel );

					// If the arcrole is not one of the standard roles then there MUST be an arcroleRef (3.5.2.5) except if the arc itself is not standard.
					// If one does not exist, ignore the arc.
					if ( ! XBRL_Constants::isStandardArcrole( $arcrole ) && XBRL_Constants::isStandardArcElement( $domNode->namespaceURI, $domNode->localName ) )
					{
						if ( ! $arcroleRefs || ! isset( $arcroleRefs[ $arcrole ] ) )
						{
							$this->log()->taxonomy_validation( "3.5.2.5", "An arcroleRef element MUST be used when a non-standard arcrole is used on an arc.",
								array(
									'arcrole' => $arcrole,
									'linkbase' => $xml_basename
								)
							);
							return true;
						}
					}

					if ( ! $isPreferredLabelArc && $preferredLabelRole )
					{
						// Record for processing after the initial pass
						$preferredLabelArcs[] = $arc;
						return true;
					}

					$resourceType = $preferredLabelRole || $arcrole == XBRL_Constants::$genericElementLabel
						? "labels"
						: ( $arcrole == XBRL_Constants::$genericElementReference ? "references" : "resources" );

					if ( ! $preferredLabelRole )
					{
						$preferredLabelRole = $arcrole == XBRL_Constants::$genericElementLabel
							? (
									// If the 2008 label role is defined as a roleref use it otherwise usse the 2003 role
									isset( $roleRefs[ XBRL_Constants::$genericRoleLabel ] )
										? XBRL_Constants::$genericRoleLabel
										: XBRL_Constants::$labelRoleLabel
							  )
							: XBRL_Constants::$labelRoleLabel;

						if ( $isPreferredLabelArc )
						{
							foreach ( $roleRefs as $roleRefUri => $details )
							{
								if ( $details['usedOn'] == 'label:label' )
								{
									$preferredLabelRole = $roleRefUri;
									break;
								}
							}
						}
					}

					if ( ! $this->validateXLinkArcAttributes( $xlinkAttributes, $linkbaseDescription ) )
					{
						return true;
					}

					if ( $isGeneric && ! count( $arcroleRefs ) )
					{
						$this->log()->taxonomy_validation( "Generics 2.1.1", "There are no arcrole refs in the linkbase",
							array(
								'error' => 'xbrlgene:missingRoleRefForArcRole'
							)
						);

						return false;
					}

					if ( $isGeneric && XBRL::$validating )
					{
						if ( ! isset( $arcroleType[ $arcrole ] ) )
						{

							// This means a usedOn is not defined for the current arc and arcrole
							$this->log()->taxonomy_validation( "Generics 2.2.1", "This arcrole is not defined as an arcroleRef in the current linkbase file",
								array(
									'arcrole' => $arcrole,
									'error' => 'xbrlgene:missingArcRoleUsedOnValue',
								)
							);
							return true;

						}

						// There MUST be an arcroleRef for this role defined in the linkbase
						if ( ! isset( $arcroleRefs[ $arcrole ] ) )
						{

							$this->log()->taxonomy_validation( "Generics 2.2.1", "This arcrole is not defined as an arcroleRef in the current linkbase file",
								array(
									'arcrole' => $arcrole,
									'error' => 'xbrlgene:missingRoleRefForArcRole',
								)
							);
							return true;

						}
					}

					$node['arcrole'] = $arcrole;

					$fromLabel	= (string) $xlinkAttributes->from;
					$this->validateXLinkLabel( $linkbaseDescription, $fromLabel );

					$toLabel	= (string) $xlinkAttributes->to;
					$this->validateXLinkLabel( $linkbaseDescription, $toLabel );

					$fromList	= isset( $locators[ $fromLabel ] )
						? $locators[ $fromLabel ]
						: $fromLabel;

					$toList	= isset( $locators[ $toLabel ] )
						? $locators[ $toLabel ]
						: $toLabel;

					$validLocators = true;

					if ( ! is_array( $fromList ) )
					{
						// There is no locator so make sure a resource exists for $fromLabel (resources but not labels can be sources)
						if ( ! isset( $this->{$roleListName}['roles'][ $roleUri ]['resources'][ basename( $href ) ][ $fromList ] ) )
						{
							$validLocators = false;
						}
						else
						{
							$fromList = array( $fromList );
						}
					}

					if ( $isPreferredLabelArc )
					{
						// Compute an equivalence hash to identify duplicate preferred labels
						foreach ( $fromList as $from )
						{
							$equivalenceHash = $this->equivalenceHash( "{$arcQName->prefix}:{$arcQName->localName}", "{$linkQName->prefix}:{$linkQName->localName}", $roleUri, $from, "", null, null, $preferredLabelRole );
							if ( in_array( $equivalenceHash, $preferredLabelEquivalenceHashes ) )
							{
								$this->log()->taxonomy_validation( "Preferred label 2.1", "Preferred label has been used more than once for the same arc source and target",
									array(
										'preferredLabelRole' => $preferredLabelRole,
										'arcElement' => "{$arcQName->prefix}:{$arcQName->localName}",
										'linkElement' => "{$linkQName->prefix}:{$linkQName->localName}",
										'roleUri' => $roleUri,
										'arcrole' => $arcrole,
										'fromLabel' => $toLabel,
										'toLabel' => $fromLabel,
										'error' => 'gple:duplicatePreferredLabel',
									)
								);

								return true;
							}
							else
							{
								$preferredLabelEquivalenceHashes[] = $equivalenceHash;
							}
						}
					}

					if ( ! is_array( $toList ) )
					{
						if ( $isPreferredLabelArc )
						{
							if ( isset( $this->{$roleListName}
								 ['roles'][ $roleUri ]['arcroles'][ XBRL_Constants::$genericElementLabel ]
								 ['links']["{$linkQName->prefix}:{$linkQName->localName}"]
								 ['arcelements']["{$arcQName->prefix}:{$arcQName->localName}"]
								 ['arcs'][ $toList ] )
							)
							{
								$target = &$this->{$roleListName}
									['roles'][ $roleUri ]['arcroles'][ XBRL_Constants::$genericElementLabel ]
									['links']["{$linkQName->prefix}:{$linkQName->localName}"]
									['arcelements']["{$arcQName->prefix}:{$arcQName->localName}"]
									['arcs'][ $toList ];

								// This preferred label is going to apply to all elements of $target that have an
								// arcrole with a value of http://xbrl.org/arcrole/2008/element-label
								$preferredLabelRoleFound = false;
								foreach ( $target as $toLabel => &$toDetails )
								{
									if ( isset( $toDetails['arcrole'] ) && $toDetails['arcrole'] == XBRL_Constants::$labelRoleLabel ) continue;

									// look for the label $toLabel to make sure it has the the role $preferredLabelRole
									if ( isset( $this->{$roleListName}
										 ['roles'][ $preferredLabelRole ]
										 ['labels'][ basename( $href ) ][ $toLabel ] )
									)
									{
										$preferredLabelRoleFound = true;
										foreach ( $toDetails as &$node )
										{
											$node['preferredLabel'] = $preferredLabelRole;
										}
										unset( $node );
									}

								}

								unset( $toDetails );
								unset( $target );

								if ( ! $preferredLabelRoleFound )
								{

									// This means a usedOn is not defined for the current arc and arcrole
									$this->log()->taxonomy_validation( "Preferred label 2.1", "This preferred role is not defined on any label",
										array(
											'role' => $roleUri,
											'arcrole' => $arcrole,
											'fromLabel' => $fromLabel,
											'toLabel' => $toLabel,
											'error' => 'gple:missingPreferredLabel',
										)
									);

									return true;
								}

								return true;
							}

							// If this is a preferred label the $to to use is the to of the arc which is from $toLabel
							if ( ! isset( $this->{$roleListName}['roles'][ $roleUri ]['resources'][ basename( $href ) ][ $toList ] ) )
							{
								$fromArc = $this->{$roleListName}['roles'][ $roleUri ]['resources'][ basename( $href ) ][ $toList ];
							}
						}

						// There is no locator so make sure a resource or label exists for $toLabel
						$resourceRole = $resourceType == 'labels'
							? $preferredLabelRole
							: (
								$resourceType == 'references'
									? ( XBRL_Constants::$reference2003 )
									: $roleUri
							  );
						if ( ! isset( $this->{$roleListName}['roles'][ $resourceRole ][ $resourceType ][ basename( $href ) ][ $toList ] ) )
						{
							$validLocators = false;
						}
						else
						{
							$toList = array( $toList );
						}
					}

					if ( ! $validLocators )
					{
						$this->log()->taxonomy_validation( "Generics 2.1.1", "Locators do not exist or resource cannot be found for the label",
							array(
								'role' => $roleUri,
								'arcrole' => $arcrole,
								'link' => "{$linkQName->prefix}:{$linkQName->localName}",
								'arc' => "{$arcQName->prefix}:{$arcQName->localName}",
								'from' => $fromLabel,
								'to' => $toLabel,
								'error' => 'xbrlgene:missingLinkRoleUsedOnValue',
							)
						);
						return true;
					}

					// Import remote resources
					$importRemoteResource = function( $resourceReference, $remoteLinkbase, $label, $id ) use( $xml_basename, $linkPath, $resourceType, $roleListName )
					{
						// Get the remote resource
						if ( ! isset( $this->{$roleListName}['roles'][ $resourceReference['role'] ][ $resourceType ][ $remoteLinkbase ][ $resourceReference['label'] ] ) )
						{
							return false;
						}

						// Handy reference to the remote resources
						$remote = &$this->{$roleListName}['roles'][ $resourceReference['role'] ][ $resourceType ][ $remoteLinkbase ][ $resourceReference['label'] ];

						// If the local resource does not exist, create it
						if ( ! isset( $this->{$roleListName}['roles'][ $resourceReference['role'] ][ $resourceType ][ $xml_basename ][ $label ] ) )
						{
							$this->{$roleListName}['roles'][ $resourceReference['role'] ][ $resourceType ][ $xml_basename ][ $label ] = array();
						}

						// Handy reference to the local resource
						$local = &$this->{$roleListName}['roles'][ $resourceReference['role'] ][ $resourceType ][ $xml_basename ][ $label ];

						// Maybe its already imported
						if ( array_filter( $local, function( $resource ) use( $id )
							{
								return isset( $resource['id'] ) && $resource['id'] == $id;
							} )
						)
						{
							return true;
						}

						// Get the remote resource
						$remoteResources = array_filter( $remote, function ( $resource ) use ( $id )
							{
								return isset( $resource['id'] ) && $resource['id'] == $id;
							}
						);

						// Maybe there isn't one (should have been reported as an error by retrieveLocators
						if ( ! $remoteResources ) return false;

						// Change some of the values
						array_walk( $remoteResources, function( &$remoteResource ) use( $linkPath, $label )
						{
							$remoteResource['label'] = $label;
							$remoteResource['path'] = $linkPath;
						} );

						// Should only be one remote resource found so grab it
						$remoteResource = reset( $remoteResources );

						$local[] = $remoteResource;

						return true;
					};

					foreach ( $fromList as $from )
					{
						foreach ( $toList as $to )
						{
							$fromLinkbase = $xml_basename;
							$fromRoleUri = $roleUri;
							$fromId = null;
							$fromIsSchemaType = false;
							$fromPath = $linkPath;

							if ( strpos( $from, "#" ) !== false )
							{
								$fromParts = parse_url( $from );
								if ( ! isset( $fromParts['fragment'] ) ) $fromParts['fragment'] = "";

								if ( isset( $linkbaseIds[ basename( $fromParts['path'] ) ] ) )
								{
									// Is the fragment a reference to an exising resource label?
									$ids =& $linkbaseIds[ basename( $fromParts['path'] ) ];
									if ( isset( $ids[ $fromParts['fragment'] ] ) ) // && $linkbaseType['ids'][ $parts['fragment'] ]['type'] == 'arc' )
									{
										$remoteResource = $ids[ $fromParts['fragment'] ];
										$from = $remoteResource['type'] == 'resource'
									 		? $remoteResource['label']
									 		: $remoteResource['from'];

									 	$fromLinkbase = basename( $fromParts['path'] );
									 	$fromRoleUri = $ids[ $fromParts['fragment'] ]['role'];
									 	$fromId = $fromParts['fragment'];
									 	$fromPath = dirname( $ids[ $fromParts['fragment'] ]['path'] );
									}
									else
									{
										$resourceType = $arcrole == XBRL_Constants::$genericElementLabel
											? "labels"
											: ( $arcrole == XBRL_Constants::$genericElementReference ? "references" : "resources" ) ;

										if ( isset( $this->{$roleListName}['roles'][ $roleUri ][ $resourceType ][ $fromParts['fragment'] ][ $fromParts['path'] ] ) )
										{
											$from = $fromParts['fragment'];
									 		$fromId = $fromParts['fragment'];
										}
									}
									unset( $ids );
								}
								else
								{
									// Could be an element defined in the schema
									/** @var XBRL_Types $types */
									$types = XBRL_Types::getInstance();

									// Get the taxonomy for the schema
									/** @var XBRL $tax */
									$tax = $this->getTaxonomyForXSD( $fromParts['path'] );
									if ( $tax )
									{
										// Look for a element or a attribute defined by the schema with the name
										$el = $types->getElement( $fromParts['fragment'], $tax->getPrefix() );
										if ( ! $el )
										{
											$el = $types->getAttribute( $fromParts['fragment'], $tax->getPrefix() );
										}

										// BMS 2018-08-25 The from id could also be in the annoations (role/arcrole types) of the schema
										if ( is_array( $el ) ||
											 isset( $tax->roleTypeIds[ $fromParts['fragment'] ] ) ||
											 isset( $tax->arcroleTypeIds[ $fromParts['fragment'] ] ) ||
											 $types->getTypeById( $fromParts['fragment'], $tax->getPrefix() )
										)
										{
											// The equality definition 'from' identifier needs to be the path + fragment
											// Perhaps all should but for now just the equality definition
											// BMS 2018-03-29 Test 61100 V-41 requires the full 'from' identifier
											// $from = $arcrole == XBRL_Constants::$arcRoleVariableEqualityDefinition
											// 	? "{$fromParts['path']}#{$fromParts['fragment']}"
											// 	: $fromParts['fragment'];
									 		// // $fromId = $fromParts['fragment'];
									 		$fromIsSchemaType = true;
										}
									}
								}
							}

							$toLinkbase = $xml_basename;
							$toRoleUri = $roleUri;
							$toId = null;
							$toIsSchemaType = false;
							$toPath = $linkPath;

							if ( strpos( $to, "#" ) !== false )
							{
								$toParts = parse_url( $to );

								if ( isset( $linkbaseIds[ basename( $toParts['path'] ) ] ) )
								{
									// Is the fragment a reference to an exising resource label?
									$ids =& $linkbaseIds[ basename( $toParts['path'] ) ];
									// What conformance test does this condition satisfy?
									// BMS 2018-04-25	Don't yet know but the test makes no sense.
									//					It has the effect of using $ids[ $toParts['fragment'] ]
									//					only when it is known it does not exist!
									//					Need to remove the exclamation
									// if ( ! isset( $ids[ $toParts['fragment'] ] ) && $ids[ $toParts['fragment'] ]['type'] == 'arc' )
									if ( isset( $ids[ $toParts['fragment'] ] ) && $ids[ $toParts['fragment'] ]['type'] == 'arc' )
									{
										$resourceType = $arcrole == XBRL_Constants::$genericElementLabel
											? "labels"
											: ( $arcrole == XBRL_Constants::$genericElementReference ? "references" : "resources" );
										if ( $this->{$roleListName}['roles'][ $roleUri ][ $resourceType ][ $toParts['fragment'] ][ $toParts['path'] ] )
										{
											$to = $toParts['fragment'];
											$toId = $toParts['fragment'];
										}
									}
									// This additional condition is required by test 22090 V-03
									else if ( isset( $ids[ $toParts['fragment'] ] ) && $ids[ $toParts['fragment'] ]['type'] == 'resource' )
									{
										$toLinkbase = basename( $toParts['path'] );
										$toId = $toParts['fragment'];
										$toRoleUri = $ids[ $toParts['fragment'] ]['role'];
										$to = $ids[ $toParts['fragment'] ]['label'];
									 	$toPath = dirname( $ids[ $toParts['fragment'] ]['path'] );
									}

									// unset( $linkbaseType );
									unset( $ids );
								}
								else
								{
									// Could be an element defined in the schema
									/** @var XBRL_Types $types */
									$types = XBRL_Types::getInstance();

									// Get the taxonomy for the schema
									/** @var XBRL $tax */
									$tax = $this->getTaxonomyForXSD( $toParts['path'] );
									if ( $tax )
									{
										// Look for a element or a attribute defined by the schema with the name
										$el = $types->getElement( $toParts['fragment'], $tax->getPrefix() );
										if ( ! $el )
										{
											$el = $types->getAttribute( $toParts['fragment'], $tax->getPrefix() );
										}

										if ( is_array( $el ) )
										{
											// BMS 2018-03-29 Test 61100 V-42 requires the full 'to' identifier
											// $to = $toParts['fragment'];
									 		// $toId = $toParts['fragment'];
									 		$toIsSchemaType = true;
										}
									}
								}
							}

							// $this->validateFromToArcPairs( $roleUri, $arcrole, $from, $to );
							$this->validateFromToArcPairs( $roleUri, $arcrole, $fromId, $toId, $fromLabel, $toLabel, $xml_basename, $this->getXmlNodePath( $arc ) );

							// By now the arcrole has been validated for use on a specific type of arc element
							// But the specifications sometimes require additional validations. Note this code
							// should probably be implemented in its function in a separate file so it can be
							// plugged/unplugged
							global $use_xbrl_functions;
							if ( XBRL::isValidating() && $use_xbrl_functions )
							{
								$resourceType = $arcrole == XBRL_Constants::$genericElementLabel
									? "labels"
									: ( $arcrole == XBRL_Constants::$genericElementReference ? "references" : "resources" ) ;
								$toRoleUri = $resourceType == 'labels'
									? $preferredLabelRole
									: ( $resourceType == 'references'
										? XBRL_Constants::$reference2003
										: $toRoleUri );

								$fromResources	= isset( $this->{$roleListName}['roles'][ $fromRoleUri ]['resources'][ $fromLinkbase ][ $from ] )
									? (
											is_array( $this->{$roleListName}['roles'][ $fromRoleUri ]['resources'][ $fromLinkbase ][ $from ] )
												? array_filter( $this->{$roleListName}['roles'][ $fromRoleUri ]['resources'][ $fromLinkbase ][ $from ],
													function( $item ) use( $fromId )
													{
														return is_null( $fromId ) || ! isset( $item['id'] ) || $item['id'] == $fromId;
													}
									  	 		  )
									  			: array( $this->{$roleListName}['roles'][ $fromRoleUri ]['resources'][ $fromLinkbase ][ $from ] )
									  )
									: ( $fromIsSchemaType
											? array( array( 'type'=> 'schema', 'schema' => $fromParts['path'], 'fragment' => $fromParts['fragment'] ) )
											: null
									  );

								$toResources	= isset( $this->{$roleListName}['roles'][ $toRoleUri ][ $resourceType ][ $toLinkbase ][ $to ] )
									? (
											is_array( $this->{$roleListName}['roles'][ $toRoleUri ][ $resourceType ][ $toLinkbase ][ $to ] )
												? array_filter( $this->{$roleListName}['roles'][ $toRoleUri ][ $resourceType ][ $toLinkbase ][ $to ],
													function( $item ) use( $toId ) { return is_null( $toId ) || ! isset( $item['id'] ) || $item['id'] == $toId; }
												  )
												: array( $this->{$roleListName}['roles'][ $toRoleUri ][ $resourceType ][ $toLinkbase ][ $to ] )
									  )
									: ( $toIsSchemaType
											? array( array( 'type'=> 'schema', 'schema' => $toParts['path'], 'fragment' => $toParts['fragment'] ) )
											: null
									  );

								// BMS 2018-03-27 This is probably redundant because of the similar functionality above
								if ( is_null( $toResources ) )
								{
									if ( strpos( $to, '#' ) )
									{
										$parts = explode( '#', $to );
										// Look in the ids to see if this linkbase/id combination exists
										if ( isset( $linkbaseIds[ $parts[0] ][ $parts[1] ] ) )
										{
											$toResources = isset( $this->{$roleListName}['roles'][ $toRoleUri ][ $resourceType ][ $parts[0] ][ $parts[1] ] )
												? $this->{$roleListName}['roles'][ $toRoleUri ][ $resourceType ][ $parts[0] ][ $parts[1] ]
												: null;
										}
									}
								}

								$testAllResources = function( $resources, $resourceType, $setType, $typeTest )
								{
									if ( is_null( $resources ) || ! count( $resources ) ) return false;

									// Make sure $typeTest is null or an array
									if ( ! is_null( $typeTest ) && ! is_array( $typeTest ) )
									{
										$typeTest = array( $typeTest );
									}

									foreach ( $resources as $resource )
									{
										if ( $resource['type'] != $resourceType  ) return false;
										if ( is_null( $setType ) ) continue;
										if ( isset( $resource[ $setType ] ) )
										{
											if ( is_null( $typeTest ) || in_array( $resource[ $setType ], $typeTest ) ) continue;
										}

										return false;
									}

									return true;
								};

								$testAnyResources = function( $resources, $resourceType, $setType, $typeTest )
								{
									if ( is_null( $resources ) || ! count( $resources ) ) return false;

									// Make sure $typeTest is null or an array
									if ( ! is_null( $typeTest ) && ! is_array( $typeTest ) )
									{
										$typeTest = array( $typeTest );
									}

									// Any one of them must fit the bill
									foreach ( $resources as $resource )
									{
										if ( $resource['type'] != $resourceType  ) return false;
										if ( is_null( $setType ) ) continue;
										if ( isset( $resource[ $setType ] ) )
										{
											if ( is_null( $typeTest ) ) continue;
											if ( in_array( $resource[ $setType ], $typeTest ) ) return true;
										}
									}

									return false;
								};

								switch ( $arcrole )
								{
									case XBRL_Constants::$arcRoleAssertionConsistencyFormula:

										// arc MUST:
										// 		have an arcrole value equal to http://xbrl.org/arcrole/2008/consistency-assertion-formula
										//		have a consistency-assertion at the starting resource of the arc
										//		have a formula as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'assertionset', 'assertionsetType', 'consistencyAssertion' ) ||
											! $testAnyResources( $toResources, 'variableset', 'variablesetType', 'formula' )
										)
										{
											$this->log()->taxonomy_validation( "Consistency assertion", "The arc MUST be from a consistency assertion element to a formula element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													'error' => 'gple:missingPreferredLabel',
												)
											);
										}
										break;

									case XBRL_Constants::$arcRoleAssertionConsistencyParameter:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2008/consistency-assertion-parameter
										//		have a consistency-assertion at the starting resource of the arc
										//		have a parameter as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'assertionset', 'assertionsetType', 'consistencyAssertion' ) ||
											! $testAllResources( $toResources, 'variable', 'variableType', 'parameter' )
										)
										{
											$this->log()->formula_validation( "Consistency assertion", "The arc MUST be from a consistency assertion element to a variable parameter element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case XBRL_Constants::$arcRoleVariableSet:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2008/variable-set
										//		have a variable-set resource at the starting resource of the arc
										//		have a parameter or a fact variable or a general variable as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'variableset', null, null ) ||
											! $testAllResources( $toResources, 'variable', 'variableType', array( 'factVariable', 'generalVariable', 'parameter' ) )
										)
										{
											$this->log()->taxonomy_validation( "Variable set filter", "The arc MUST be from a variable set such as a formula element to a variable element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case XBRL_Constants::$arcRoleVariableFilter:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2008/variable-set
										//		have a variable-set resource at the starting resource of the arc
										//		have a parameter or a fact variable or a general variable as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'variable', null, null ) || // From a variable
											! $testAllResources( $toResources, 'filter', 'filterType', null ) // To any filter
										)
										{
											// See test 21261 V-01
											$this->log()->taxonomy_validation( "Variable filter", "The arc MUST be from a variable element to a any filter element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;
									case XBRL_Constants::$arcRoleVariableSetFilter:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2008/variable-set
										//		have a variable-set resource at the starting resource of the arc
										//		have a parameter or a fact variable or a general variable as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'variableset', null, null ) || // From any variable-set element
											! $testAllResources( $toResources, 'filter', 'filterType', null ) // To any filter
										)
										{
											$this->log()->taxonomy_validation( "Variable set filter", "The arc MUST be from a variable-set resource element to any filter element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$genericElementLabel:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2008/element-label
										//		have an XML element [XML] at the starting resource of the arc
										//		have the generic label as the ending resource of the arc
										if (
											! ( $testAllResources( $fromResources, 'variableset', null, null ) || // From any variable-set element
												$testAllResources( $fromResources, 'assertionset', null, null ) || // From any assertion-set element
												$testAllResources( $fromResources, 'variable', null, null ) || // From any variable element
												$testAllResources( $fromResources, 'filter', 'filterType', null ) || // From any variable element
												$testAllResources( $fromResources, 'resource', null, null ) || // From any other resource element
												$testAllResources( $fromResources, 'schema', null, null ) ) || // From any other resource element
												! count( $toResources )
										)
										{
											$this->log()->taxonomy_validation( "Element label", "The arc MUST be from a resource element to a label element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$arcRoleVariableSetPrecondition:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2008/variable-set-precondition
										//		have a variable-set resource at the starting resource of the arc
										//		have a precondition as the ending resource of the arc

										if (
											! $testAllResources( $fromResources, 'variableset', null, null ) || // From any variable-set element
											! $testAllResources( $toResources, 'variable', 'variableType', 'precondition' ) // To any precondition
										)
										{
											$this->log()->taxonomy_validation( "Variable set precondition", "The arc MUST be from a variable-set resource element to any pre-condition element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$arcRoleAssertionSatisfiedMessage:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2010/assertion-satisfied-message
										//		have an assertion at the starting resource of the arc
										//		have a message as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'variableset', 'variablesetType', array( 'consistencyAssertion', 'existenceAssertion', 'valueAssertion' ) ) || // From any variable-set element
											! $testAllResources( $toResources, 'message', 'messageType', 'message' ) // To any precondition
										)
										{
											$this->log()->taxonomy_validation( "Validation message", "The arc MUST be from a variable-set resource element to a message resource element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$arcRoleAssertionUnsatisfiedMessage:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2010/assertion-satisfied-message
										//		have an assertion at the starting resource of the arc
										//		have a message as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'variableset', 'variablesetType', array( 'consistencyAssertion', 'existenceAssertion', 'valueAssertion' ) ) || // From any variable-set element
											! $testAllResources( $toResources, 'message', 'messageType', 'message' ) // To any precondition
										)
										{
											$this->log()->taxonomy_validation( "Validation message", "The arc MUST be from a variable-set resource element to a message resource element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$arcRoleAssertionUnsatisfiedSeverity:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2010/assertion-satisfied-severity
										//		have an assertion at the starting resource of the arc
										//		have a severity as the ending resource of the arc

										if (
											! $testAllResources( $fromResources, 'variableset', 'variablesetType', array( 'consistencyAssertion', 'existenceAssertion', 'valueAssertion' ) ) || // From any variable-set element
											! $testAllResources( $toResources, 'resource', 'resourceType', 'severity' ) // To any severity resource
										)
										{
											$this->log()->taxonomy_validation( "Severity level", "The arc MUST be from a variable-set resource element to a severity resource element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$arcRoleCustomFunctionImplementation:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2010/function-implementation
										//		have the custom function signature at the starting resource of the arc
										//		have the custom function implementation as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'customfunction', 'customfunctionType', 'signature' ) || // From any variable-set element
											! $testAllResources( $toResources, 'customfunction', 'customfunctionType', 'implementation' ) // To any precondition
										)
										{
											$this->log()->taxonomy_validation( "Custom function message", "The arc MUST be from a variable-set resource element to a message resource element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$arcRoleInstanceVariable:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2010/instance-variable
										//		have an instance resource as the starting resource of the arc
										//		have a factVariable or generalVariable at the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'variable', 'variableType', 'instance' ) || // From any variable-set element
											! $testAllResources( $toResources, 'variable', 'variableType', array( 'factVariable' ) ) // To any precondition
										)
										{
											$this->log()->taxonomy_validation( "Instance variable", "The arc MUST be from an instance element to a fact or general variable element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$arcRoleFormulaInstance:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2010/formula-instance
										//		have a formula resource as the starting resource of the arc
										//		have an instance resource as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'variableset', 'variablesetType', 'formula' ) || // From any variable-set element
											! $testAllResources( $toResources, 'variable', 'variableType', 'instance' ) // To any precondition
										)
										{
											$this->log()->taxonomy_validation( "Formula Instance", "The arc MUST be from a formula element to an instance element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$arcRoleVariablesScope:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2010/variables-scope
										//		have a variable-set resource as the starting resource of the arc
										//		have an variable-set resource as the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'variableset', 'variablesetType', null ) || // From any variable-set element
											! $testAllResources( $toResources, 'variableset', 'variablesetType', null ) // To any precondition
										)
										{
											$this->log()->taxonomy_validation( "Formula Instance", "The arc MUST be from a formula element to an instance element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case \XBRL_Constants::$arcRoleVariableEqualityDefinition:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2008/equality-definition
										//		have a typed-dimension domain definition at the starting resource of the arc
										//		have an equality definition at the ending resource of the arc

										// Look up the from list to make sure they reference a typed dimension
										$fromValid = false;
										foreach ( $fromList as $dimensionLocation )
										{
											// Check the taxonomy of the location is valid
											$dimTaxonomy = $this->getTaxonomyForXSD( $dimensionLocation );
											if ( ! $dimTaxonomy ) continue;

											$dimensions = $this->getDefinitionDimensions();
											foreach ( $dimensions as $key => $dimension )
											{
												// Check the taxonomy of the dimension is valid
												$dimTaxonomy = $this->getTaxonomyForXSD( $dimension['href']['label'] );
												if ( ! $dimTaxonomy ) continue;
												// Get the element which MUST have a typedDomainRef
												$element = $dimTaxonomy->getElementById( $dimension['href']['label'] );
												if ( ! isset( $element['typedDomainRef'] ) ) continue;
												// Compare the typedDomainRef of this dimension with the $dimensionLocation
												$parts = explode( "#", $element['typedDomainRef'] );
												if ( empty( $parts[0] ) ) // Might be a local reference
												{
													$parts[0] = $dimTaxonomy->getTaxonomyXSD();
												}
												if ( implode( "#", $parts ) == $dimensionLocation )
												{
													$fromValid = true;
												}
											}
										}

										if (
											! $fromValid ||
											! $testAllResources( $toResources, 'equality', 'equalityType', array( 'equalityDefinition' ) ) // To any precondition
										)
										{
											$this->log()->taxonomy_validation( "Formula Instance", "The arc MUST be from a formula element to an instance element",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}
										break;

									case XBRL_Constants::$arcRoleBooleanFilter:

										// arc MUST:
										//		have an arcrole value equal to http://xbrl.org/arcrole/2008/boolean-filter
										//		have the Boolean filter at the starting resource of the arc
										//		have the sub-filter at the ending resource of the arc
										if (
											! $testAllResources( $fromResources, 'filter', 'filterType', array( 'andFilter', 'orFilter' ) ) || // To any filter
											! $testAllResources( $toResources, 'filter', 'filterType', null ) // To any filter
										)
										{
											$this->log()->taxonomy_validation( "Boolean filter", "The arc MUST be from am 'and' or 'or' filter to any filter type",
												array(
													'role' => $roleUri,
													'arcrole' => $arcrole,
													'fromLabel' => $from,
													'toLabel' => $to,
													// 'error' => 'xbrlcae:variablesNotAllowed',
												)
											);
										}

										break;

									default:
										$x = 1;
										break;
								}
							}

							$node = array(
								'from' => $from,
								'to' => $to,
								'label' => $to,
								'roleUri' => $roleUri,
								'fromRoleUri' => $fromRoleUri ? $fromRoleUri : $roleUri,
								'toRoleUri' => $toRoleUri ? $toRoleUri : $roleUri,
								'linkbase' => $xml_basename,
								'arcrole' => $arcrole,
								'usedOn' => $usedOn,
								'path' => $linkPath,
								'frompath' => $fromPath,
								'topath' => $toPath,
								'fromlinkbase' => $fromLinkbase,
								'tolinkbase' => $toLinkbase,
							);

							extract( $this->validateArcAttributes( $linkbaseDescription, $fromLabel, $toLabel, $attributes ) );
							$node['priority']	= $priority; // These value is set by the extract() function
							$node['use']		= $use;      // and will not appear in the debugger until used
							$node['order']		= $order;

							if ( property_exists( $xlinkAttributes, 'title' ) )
							{
								$node['title']		= (string) $xlinkAttributes->title;
							}

							if ( property_exists( $xlinkAttributes, 'actuate' ) )
							{
								$node['actuate']	= (string) $xlinkAttributes->actuate;
							}

							if ( property_exists( $xlinkAttributes, 'show' ) )
							{
								$node['show']		= (string) $xlinkAttributes->show;
							}

							if ( property_exists( $attributes, 'name' ) )
							{
								// $namespaces = $arc->getDocNamespaces(true);
								$namespaces = array_merge( $arc->getDocNamespaces(true), $arc->getDocNamespaces(false, false) );

								/**
								 * @var QName $qName
								 */
								// If there is no prefix it should not be resolved to a default namespace
								// BMS 2018-02-16 This is not true for variables (see Variables 3.5.1 Variable-set relationships)
								$source = trim( $attributes->name );
								$qName = strpos( $source, ":" )
									? qname( $source, $namespaces )
									: new QName( "", $arcrole != XBRL_Constants::$arcRoleVariableSet && isset( $namespaces[''] ) ? $namespaces[''] : null, $source );

								if ( is_null( $qName ) )
								{
									$this->log()->formula_validation( "Variables 1.7.1.1", "Variable specifies a namespace prefix that cannot be resolved to a namespace declaration that is in scope",
										array(
											'source' => $source,
											'error' => 'xbrlve:variableNameResolutionFailure'
										)
									);
								}

								if ( ! $qName->prefix )
								{
									foreach ( $namespaces as $prefix => $namespace )
									{
										// Ignore default namespaces
										if ( ! $prefix ) continue;
										if ( $qName->namespaceURI != $namespace ) continue;
										$qName->prefix = $prefix;
										break;
									}
								}

								$node['name'] = array(
									'name' => is_null( $qName ) ? $source : $qName->localName,
									'originalPrefix' => is_null( $qName ) ? null : $qName->prefix,
									'namespace' => is_null( $qName ) ? null : $qName->namespaceURI,
								);
							}

							// Get local namespaces but not any default
							$node['localNamespaces'] = array_filter( $arc->getDocNamespaces( true, false ), function( $namespace, $prefix ) {
								return !empty( $prefix );
							}, ARRAY_FILTER_USE_BOTH );

							// Collect non-standard attributes
							$customAttributes = $arc->attributes( $this->getNamespace() );
							foreach ( $customAttributes as $attributeId => $attribute )
							{
								// BMS 2018-04-09 Test candidates changed.
								$type = "xs:anyType";
								$attributeType = $this->context->types->getAttribute( $attributeId, $this->getPrefix() );
								if ( $attributeType && isset( $attributeType['types'][0] ) )
								{
									$type = $attributeType['types'][0];
								}

								// BMS 2018-04-09 Test candidates changed.
								$value = $type == "xs:boolean"
									? filter_var( (string)$attribute, FILTER_VALIDATE_BOOLEAN )
									: (string)$attribute;

								$node['attributes'][ $attributeId ] = array( 'type' => $type, 'value' => $value  );
							}

							$customAttributes = $arc->attributes();
							foreach ( $customAttributes as $attributeId => $attribute )
							{
								if ( $attributeId == 'id' || isset( $node[ $attributeId ] ) )
								{
									continue;
								}

								// BMS 2018-04-09 Test candidates changed.
								$type = "xs:anyType";
								$attributeType = $this->context->types->getAttribute( $attributeId, $domNode->prefix );
								if ( $attributeType && isset( $attributeType['types'][0] ) )
								{
									$type = $attributeType['types'][0];
								}

								// BMS 2018-04-09 Test candidates changed.
								$value = $type == "xs:boolean"
									? filter_var( (string)$attribute, FILTER_VALIDATE_BOOLEAN )
									: (string)$attribute;

								$node['attributes'][ $attributeId ] = array( 'type' => $type, 'value' => $value  );
							}

							if ( isset( $node['name'] ) )
							{
								// If the resource has a name make sure the name is unique
								$qname = new QName( "", $node['name']['namespace'], $node['name']['name'] );
								if ( isset( $this->variableSetNames[ $fromLinkbase ][ $linkPath ][ $qName->clarkNotation() ] ) )
								{
									// To be equivalent the names must have the same source and role
									$findName =	function( $arc ) use( $from, $roleUri )
									{
										return ! isset( $arc['from'] ) || ! isset( $arc['role'] ) || ( $arc['from'] == $from && $arc['role'] == $roleUri );
									};

									if( array_filter( $this->variableSetNames[ $fromLinkbase ][ $linkPath ][ $qName->clarkNotation() ], $findName ) )
									{
										$this->log()->formula_validation( "Variable-set", "The variable name (defined on an arc) already exists", array(
											'name' => $qName->clarkNotation(),
											'error' => 'xbrlve:duplicateVariableNames'
										) );

										continue;
									}
								}
							}

							$this->{$roleListName}
								['roles'][ $roleUri ]
								['arcroles'][ $arcrole ]
								['links']["{$linkQName->prefix}:{$linkQName->localName}"]
								['arcelements']["{$arcQName->prefix}:{$arcQName->localName}"]
								['arcs'][ $from ][ $to ][] = $node;

							$this->customArcsAdded[ $roleListName ]['roles'][ $roleUri ][] = "{$arcQName->prefix}:{$arcQName->localName}";

							if ( isset( $node['name'] ) && $arcrole == XBRL_Constants::$arcRoleVariableSet )
							{
								// Save the information needed to recover the named arc.  The name can be used
								// again provding the 'from' is different.
								$qname = new QName( "", $node['name']['namespace'], $node['name']['name'] );
								$this->variableSetNames[ $fromLinkbase ][ $linkPath ][ $qname->clarkNotation() ][] = array(
									'type' => 'arc',
									'rolelistname' => $roleListName,
									'role' => $roleUri,
									'linkbase' => basename( $href ),
									'path' => $linkPath,
									'links' => "{$linkQName->prefix}:{$linkQName->localName}",
									'arcroles' => $arcrole,
									'arcelements' => "{$arcQName->prefix}:{$arcQName->localName}",
									'from' => $from,
									'to' => $to,
									'offset' => count( $this->{$roleListName}['roles'][ $roleUri ]
																			 ['arcroles'][ $arcrole ]
																			 ['links']["{$linkQName->prefix}:{$linkQName->localName}"]
																			 ['arcelements']["{$arcQName->prefix}:{$arcQName->localName}"]
																			 ['arcs'][ $from ][ $to ]
												) - 1
								);
							}

						}
					}

					return true;
				};

				foreach ( $link->children( $arcQName->namespaceURI )->{$arcQName->localName} as $arcKey => /** @var SimpleXMLElement $arc */ $arc )
				{
					if ( ! $processArc( $arc ) )
					{
						break;
					}
				}

				// Fix up preferred label arcs. These may reference other arcs so need to be processed after other arcs
				foreach ( $preferredLabelArcs as $arc )
				{
					if ( ! $processArc( $arc, true ) )
					{
						break;
					}
				}
			} // $arcroleTypesCopy
		} // $links
	}

	/**
	 * Called by post processing functions to provide an opportunity to
	 * validate custom arcs for example to find cycles that are not permitted
	 */
	public function validateCustom()
	{
		// Once all the arcs have been processed apply overrides and probition
		foreach ( $this->customArcsAdded as $roleListName => $roles )
		{
			if ( ! isset( $roles['roles'] ) ) continue;

			foreach ( $roles['roles'] as $roleUri => $arcroles )
			{
				foreach ( $this->{$roleListName}['roles'][ $roleUri ]['arcroles'] as $arcroleUri => $links )
				{
					foreach ( $links['links'] as $link => $arcelements)
					{
						foreach ( $arcelements['arcelements'] as $arcelement => $arcs )
						{
							// Check for prohibition or override
							if ( count( $arcs['arcs'] ) < 1 )
							{
								continue;
							}

							$newArcs =& $this->{$roleListName}['roles'][ $roleUri ]
								['arcroles'][ $arcroleUri ]
								['links'][ $link ]
								['arcelements'][ $arcelement ]
								['arcs'];

							foreach ( $arcs['arcs'] as $fromId => $targets )
							{
								// Check for prohibition or override
								if ( count( $targets ) < 1 )
								{
									continue;
								}

								foreach ( $targets as $toId => $nodes )
								{
									// Check for prohibition or override.  There can only be overrides
									//  or prohibitions if the there is more than one node.
									if ( count( $nodes ) < 2 )
									{
										continue;
									}

									// The rules of prohibition and override apply to each of these entries

									$effectiveNodesGroups = array();

									// The first step is to find equivalent nodes.  These are the ones with
									// the same values for the non-exempt attributes.  A the moment the only
									// non-exempt attribute being considered is order.  A future revision
									// will extend this to compare an arbitrary set of attributes and values.

									$equivalents = array_reduce( $nodes, function( $carry, $node ) {
										$carry[ $node['order'] ][] = $node;
										return $carry;
									}, array() );

									// Process each of the equivalents
									foreach ( $equivalents as $order => $equivalentNodes )
									{
										if ( count( $nodes ) < 2 )
										{
											$effectiveNodesGroups[ $order ] = $equivalentNodes;
										}
										else
										{
											// The highest priority beats the  lowest priority so first get a
											// list of the nodes with the highest priority.
											$highestPriority = max( array_map( function( $node  ) { return $node['priority']; }, $nodes ) );
											$prioritizedNodes = array_filter( $equivalentNodes, function( $node ) use( $highestPriority ) {
												return $node['priority'] == $highestPriority;
											} );

											// If any of the nodes with highest priority are prohibited then all are prohibited.
											if ( count( array_filter( $prioritizedNodes, function( $node ) {
													return $node['use'] == XBRL_Constants::$xlinkUseProhibited;
												} )
											)
											) { continue; }

											$effectiveNodesGroups[ $order ] = $prioritizedNodes;
										}
									}

									$effectiveNodes = array_reduce( $effectiveNodesGroups, function( $carry, $nodes )
									{
										$carry = array_merge( $carry, $nodes );
										return $carry;
									}, array() );

									if ( count( $effectiveNodes ) )
									{
										// The $effectiveNodes replace the existing nodes
										$newArcs[ $fromId ][ $toId ] = $effectiveNodes;
									}
									else
									{
										unset( $newArcs[ $fromId ][ $toId ] );
									}
								}

							}

							// If there are no $to elements remove the from
							if ( ! count( $newArcs[ $fromId ] ) )
							{
								unset( $newArcs[ $fromId ] );
							}

						}
					}
				}
			}
		}

		if ( ! XBRL::$validating ) return;

		foreach ( $this->customArcsAdded as $roleListName => $roles )
		{
			foreach ( $roles['roles'] as $roleUri => $arcroles )
			{
				foreach ( $this->{$roleListName}['roles'][ $roleUri ]['arcroles'] as $arcroleUri => $links )
				{
					foreach ( $links['links'] as $link => $arcelements)
					{
						foreach ( $arcelements['arcelements'] as $arcelement => $arcs )
						{
							// Check for cycles
							if ( count( $arcs['arcs'] ) < 1 )
							{
								continue;
							}

							// First find roots.  These are arcs that root are those whose from 'does' not appear as a 'to'
							// $arcs = $this->{$roleListName}['roles'][ $roleUri ]['arcroles'][ $arcroleUri ]['links'][ $link ]['arcelements'][ $arcelement ]['arcs'];

							// Begin by removing all prohibited arcs and build a list of 'to' ids
							$toList = array();
							foreach ( $arcs['arcs'] as $fromId => $parent )
							{
								foreach ( $parent as $toId => $nodes )
								{
									if ( ! count( $nodes ) )
									{
										continue;
									}

									$toList[ $toId ][] = $fromId;

									foreach ( $nodes as $nodeKey => $node )
									{
										if ( ! isset( $node['use'] ) || $node['use'] != XBRL_Constants::$xlinkUseProhibited )
										{
											continue;
										}

										unset( $arcs['arcs'][ $fromId ][ $toId ][ $nodeKey ] );
									}
								}
							}

							// Build an array of 'to' members
							// $toList = array_flip( array_unique( array_reduce( array_map( 'array_keys', $arcs ), 'array_merge', array() ) ) );

							$roots = array_filter( $arcs['arcs'], function( $from ) use( &$toList )
							{
								return ! isset( $toList[ $from ] );
							}, ARRAY_FILTER_USE_KEY );

							// If there are arc and there are no roots then a cycle exists so pick any as the root
							if ( ! count( $roots ) )
							{
								$roots = reset( $arcs['arcs'] );
							}

							$arcroleTypes = $this->getAllArcRoleTypes();

							/**
							 * @param string $fromId A list of 'from' nodes.  These can be used to recursively follow the arc trail
							 * @var Closure $detectCycle
							 */
							$detectCycle = function( $fromId, $parents ) use( &$detectCycle, &$arcs, &$toList, &$arcroleTypes )
							{
								foreach ( $arcs['arcs'][ $fromId ] as $toId => $nodes )
								{
									if ( $fromId == $toId ) continue;

									// Make sure the $toId is not already in the list of parents. If it is there is a cycle
									if ( count( $nodes ) && in_array( $toId, $parents ) )
									{
										// See if any of the nodes do not have an appropriate cycles allowed
										foreach ( $nodes as $node )
										{
											// Cycles allowed is determined by the arcrole
											$cyclesAllowed = $arcroleTypes[ $node['usedOn'] ][ $node['arcrole'] ]['cyclesAllowed'];

											if ( $cyclesAllowed == "none" || $cyclesAllowed == "undirected" )
											{
												// If the arc is not standard then do not report a taxonomy error.  It qualifies if the arc is in the substitution group xl:arc
												/**
												 * @var \lyquidity\xml\QName $arcQName
												 */
												$arcQName = qname( $node['usedOn'], $this->context->types->getProcessedSchemas() );
												$isGenericArc = $this->context->types->resolveToSubstitutionGroup( $node['usedOn'], array( 'gen:arc' ) );
												$isStandardArc = $arcQName->namespaceURI != XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] &&
																 isset( XBRL_Constants::$standardLinkElements[ $arcQName->localName ] );

												if ( ! $isGenericArc && ! $isStandardArc )
												{
													// Ignore this arc
													continue;
													// return false;
												}

												XBRL_Log::getInstance()->taxonomy_validation( "5.2.6.2.1", "The non-standard arcrole arcs contain directed cycles that are not permitted by the arc role cycles allowed",
													array(
														'cyclesAllows' => $cyclesAllowed,
														'path' => implode( '->', array_merge( $parents, array( $fromId ) ) ),
													)
												);

												return true;
											}
										}
									}

									// The 'to' should not have multiple parents
									if ( count( $toList[ $toId ] ) > 1 )
									{
										// See if any of the nodes do not have an appropriate cycles allowed
										foreach ( $nodes as $node )
										{
											// Cycles allowed is determined by the arcrole
											$cyclesAllowed = $arcroleTypes[ $node['usedOn'] ][ $node['arcrole'] ]['cyclesAllowed'];

											if ( $cyclesAllowed == "none" || $cyclesAllowed == "undirected" )
											{
												// If the arc is not standard then do not report a taxonomy error.  It qualifies if the arc is in the substitution group xl:arc
												/**
												 * @var \lyquidity\xml\QName $arcQName
												 */
												$arcQName = qname( $node['usedOn'], $this->context->types->getProcessedSchemas() );
												$isGenericArc = $this->context->types->resolveToSubstitutionGroup( $node['usedOn'], array( 'gen:arc' ) );

												// Generic arc can be undirected
												if ( $isGenericArc && $cyclesAllowed == "undirected" ) continue;

												$isStandardArc = $arcQName->namespaceURI != XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] &&
																 isset( XBRL_Constants::$standardLinkElements[ $arcQName->localName ] );

												if ( ! $isGenericArc && ! $isStandardArc )
												{
													// Ignore this arc
													continue;
													// return false;
												}

												XBRL_Log::getInstance()->taxonomy_validation( "5.2.6.2.1", "The non-standard arcrole arcs contain undirected cycles that are not permitted by the arc role cycles allowed",
													array(
														'cyclesAllows' => $cyclesAllowed,
														'path' => implode( '->', array_merge( $parents, array( $fromId ) ) ),
													)
												);

												return true;
											}
										}
									}

									if ( ! isset( $arcs['arcs'][ $toId ] ) || ! count( $arcs['arcs'][ $fromId ][ $toId ] ) ) continue;

									$result = $detectCycle( $toId, array_merge( $parents, array( $fromId ) ) );

									if ( $result ) return true;
								}

								return false;
							};

							// Only test on standard links
							$linkQName = qname( $link, $this->context->types->getProcessedSchemas() );

							if ( is_null( $linkQName ) ) continue;
							$isStandardLink = $linkQName->namespaceURI != XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] &&
											  isset( XBRL_Constants::$standardLinkElements[ $linkQName->localName ] );
							$isGenericLink = $this->context->types->resolveToSubstitutionGroup($linkQName, array( "gen:link" ) );

							if ( ! $isStandardLink && ! $isGenericLink )
							{
								continue;
							}

							foreach ( $roots as $fromId => $target )
							{
								if ( $detectCycle( $fromId, array() ) )
								{
									break;
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Process the calculation linkbase type.
	 * @param array $linkbaseType The link base type to process
	 * @return void
	 */
	private function processCalculationLinkbases( $linkbaseType )
	{
		// $this->log()->info( "Process definition linkbases: " . $this->namespace );
		foreach ( $linkbaseType as $linkbaseRefkey => $linkbaseRef )
		{
			$this->processCalculationLinkbase( $linkbaseRef );
		}
	}

	/**
	 * Process the definitition linkbase type.
	 * @param array $linkbaseType The link base type to process
	 * @return void
	 */
	private function processDefinitionLinkbases( $linkbaseType )
	{
		// $this->log()->info( "Process definition linkbases: " . $this->namespace );
		foreach ( $linkbaseType as $linkbaseRefkey => $linkbaseRef )
		{
			$this->processDefinitionLinkbase( $linkbaseRef );
		}
	}

	/**
	 * Process a specifc link for essensce-alias and requires-element
	 *
	 * @param SimpleXMLElement $link
	 * @param string $linkbase The base name of the link such as 'calculation'
	 * @param string $role
	 * @param array $standardRoles A list of the roles that are valid for this link type
	 * @param array $arcroleRefs
	 * @param string $href The name of the file containing $link
	 * @param string $xml_basename
	 * @param string $arcName
	 * @param string $arcTitle
	 * return void
	 */
	private function processNonDimensionalLink( &$link, $linkbase, $role, &$standardRoles, &$arcroleRefs, $href, $xml_basename, $arcName = 'definitionArc', $arcTitle = 'Definition' )
	{
		// Detection of duplicate from/to pairs only applies within an extended link so reset this varaible in each new link
		$this->resetValidateFromToArcPairs();

		// Get a list of the locators for this extended link
		$locators = $this->retrieveLocators( $link, $linkbase, $href );
		if ( ! count( $locators ) ) return;

		// Used to catch duplicated from/to label pairs which is not alloed by the XLink specification
		$fromToPairs = array();

		foreach ( $link->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->$arcName as $arcKey => $arc )
		{
			$xlinkAttributes = $arc->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$attributes = $arc->attributes();
			if ( ! $this->validateXLinkArcAttributes( $xlinkAttributes, $linkbase ) )
			{
				continue;
			}

			if ( isset( $fromToPairs[ (string)$xlinkAttributes->from ][ (string)$xlinkAttributes->to ] ) )
			{
				$this->log()->taxonomy_validation( "XLink", "$arcTitle arcs contain repeated from/to label pairs in the same extended link",
					array(
						'role' => $role,
						'from' => (string)$xlinkAttributes->from,
						'to' => (string)$xlinkAttributes->to
					)
				);
			}
			else
			{
				$fromToPairs[ (string)$xlinkAttributes->from ][ (string)$xlinkAttributes->to ] = 1;
			}

			$this->processNonDimensionalArc( $arc, $locators, $linkbase, $role, $xlinkAttributes, $attributes, $standardRoles, $arcroleRefs, $xml_basename, $arcTitle );
		}
	}

	/**
	 * Process a specific link a non-dimensional linkbase element
	 *
	 * @param SimpleXMLElement $arc
	 * @param array $locators
	 * @param string $linkbase The base name of the link such as 'calculation'
	 * @param string $role
	 * @param SimpleXMLElement $xlinkAttributes
	 * @param SimpleXMLElement $attributes
	 * @param array $standardRoles
	 * @param array $arcroleRefs
	 * @param string $xml_basename
	 * @param string $arcTitle
	 */
	private function processNonDimensionalArc( &$arc, &$locators, $linkbase, $role, &$xlinkAttributes, &$attributes, &$standardRoles, &$arcroleRefs, $xml_basename,$arcTitle = 'Definition' )
	{
		$arcroleUri = (string) $xlinkAttributes->arcrole;
		if ( ! in_array( $arcroleUri, $standardRoles ) )
		{
			if ( is_array( $arcroleRefs ) && isset( $arcroleRefs[ $arcroleUri ] ) )
			{
				return;
			}

			$this->log()->taxonomy_validation( "5.2.4.2", "The non-standard arcrole on the $linkbase arc has not been defined",
				array(
					'arcrole' => $arcroleUri,
				)
			);

			return;
		}

		$fromLabel	= (string) $xlinkAttributes->from;
		$this->validateXLinkLabel( $linkbase, $fromLabel );

		$toLabel	= (string) $xlinkAttributes->to;
		$this->validateXLinkLabel( $linkbase, $toLabel );

		$fromList	= $locators[ $fromLabel ];
		$toList		= $locators[ $toLabel ];

		if ( ! is_array( $fromList ) ) $fromList = is_array( $fromList ) ? $fromList : array( $fromList );
		if ( ! is_array( $toList ) )   $toList   = is_array( $toList )   ? $toList   : array( $toList );

		foreach ( $fromList as $from )
		{
			foreach ( $toList as $to )
			{
				// BMS 2017-09-18 See other note with this date
				$node = array(
					'to' => $to,
					'label' => $to, //$toLabel,
				);

				extract( $this->validateArcAttributes( $linkbase, $fromLabel, $toLabel, $attributes ) );
				$node['priority']	= $priority; // These value is set by the extract() function
				$node['use']		= $use;      // and will not appear in the debugger until used
				if ( isset( $order ) )
				{
					$node['order']		= $order;      // and will not appear in the debugger until used
				}

				if ( property_exists( $xlinkAttributes, 'title' ) )
				{
					$node['title']		= (string) $xlinkAttributes->title;
				}

				if ( property_exists( $xlinkAttributes, 'actuate' ) )
				{
					$node['actuate']	= (string) $xlinkAttributes->actuate;
				}

				if ( property_exists( $xlinkAttributes, 'show' ) )
				{
					$node['show']		= (string) $xlinkAttributes->show;
				}

				$arcrole = isset( $arcroleRefs[ $arcroleUri ] ) ? $arcroleRefs[ $arcroleUri ] : $arcroleUri;

				switch ( $arcroleUri )
				{
					case XBRL_Constants::$arcRoleRequiresElement:

						// echo "Requires element\n";

						if ( ! isset( $this->context->requireElements[ $from ] ) )
						{
							$this->context->requireElements[ $from ] = array();
						}

						// $this->context->requireElements[ $from ][ $to ] = $node;
						if ( ! isset( $this->context->requireElements[ $from ][ $to ] ) )
						{
							$this->context->requireElements[ $from ][ $to ] = $node;
							break;
						}

						$current =& $this->context->requireElements[ $from ][ $to ];

						// Are the attributes on this node the same as on the current arc?  If so, then this is just a replacement
						if ( $node['use'] == $current['use'] && $node['priority'] == $current['priority'] && $node['order'] == $current['order'] )
						{
							$current = $node;
							continue 2;  // Go straight to the next item in the forloop
						}

						if (
								( $node['priority'] == $current['priority'] && $node['use'] == XBRL_Constants::$xlinkUseProhibited ) ||
								( $node['priority'] > $current['priority'] )
						   )
						{
							$this->removeFromToArcPair( $role, $arcroleUri, $from, $to, $fromLabel, $toLabel );
							$current = $node;
						}

						unset( $current );

						break;

					case XBRL_Constants::$arcRoleEssenceAlias:

						// echo "Essence Alias\n";

						$taxonomy = $this->getTaxonomyForXSD( $from );
						$fromElement = $taxonomy ? $taxonomy->getElementById( $from ) : false;
						$taxonomy = $this->getTaxonomyForXSD( $to );
						$toElement = $taxonomy ? $taxonomy->getElementById( $to ) : false;

						if ( ( $fromElement && ! $toElement ) || ( ! $fromElement && $toElement ) || $fromElement['periodType'] != $toElement['periodType'] )
						{
							$this->log()->taxonomy_validation( "5.2.6.2.2", "The essence type pair do not have the same period type",
								array(
									'from' => $from,
									'to' => $to,
									'from periodType' => $fromElement ? $fromElement['periodType'] : 'unknown',
									'to periodType' => $toElement ? $toElement['periodType'] : 'unknown',
								)
							);
						}

						if ( ( $fromElement && ! $toElement ) || ( ! $fromElement && $toElement ) || $fromElement['type'] != $toElement['type'] )
						{
							$this->log()->taxonomy_validation( "5.2.6.2.2", "The essence type pair do not have the same type",
								array(
									'from' => $from,
									'to' => $to,
									'from type' => $fromElement ? $fromElement['type'] : 'unknown',
									'to type' => $toElement ? $toElement['type'] : 'unknown',
								)
							);
						}

						if ( ! isset( $this->context->essenceAlias[ $from ] ) )
						{
							$this->context->essenceAlias[ $from ] = array();
						}

						// $this->context->essenceAlias[ $from ][ $to ] = $node;
						if ( ! isset( $this->context->essenceAlias[ $from ][ $to ] ) )
						{
							$this->context->essenceAlias[ $from ][ $to ] = $node;
							break;
						}

						$current =& $this->context->essenceAlias[ $from ][ $to ];

						// Are the attributes on this node the same as on the current arc?  If so, then this is just a replacement
						if ( $node['use'] == $current['use'] && $node['priority'] == $current['priority'] && $node['order'] == $current['order'] )
						{
							$current = $node;
							continue 2;  // Go straight to the next item in the forloop
						}

						if (
								( $node['priority'] == $current['priority'] && $node['use'] == XBRL_Constants::$xlinkUseProhibited ) ||
								( $node['priority'] > $current['priority'] )
						   )
						{
							$this->removeFromToArcPair( $role, $arcroleUri, $from, $to, $fromLabel, $toLabel );
							$current = $node;
						}

						unset( $current );

						break;

					case XBRL_Constants::$arcRoleGeneralSpecial:

						// echo "General Special\n";

						if ( ! isset( $this->context->generalSpecialRoleRefs[ $role ]['rules'] ) )
						{
							$this->context->generalSpecialRoleRefs[ $role ] = array(
								'rules' => array( $from => array() ),
								'href' => $this->schemaLocation,
								'roleUri' => $role,
							);

							$usedOn = 'link:definitionLink';
							if ( ! isset( $this->roleTypes[ $usedOn ] ) )
							{
								$this->roleTypes[ $usedOn ] = array();
							}

							if ( ! isset( $this->roleTypes[ $usedOn ][ $role ] ) )
							{
								$this->roleTypes[ $usedOn ][ $role ] = array(
									'definition' => $arcTitle,
									'roleURI' => $role,
									'taxonomy' => $this->schemaLocation,
									'id' => "",
								);
							}

						}

						if ( ! isset( $this->context->generalSpecialRoleRefs[ $role ]['rules'][ $from ] ) )
						{
							$this->context->generalSpecialRoleRefs[ $role ]['rules'][ $from ] = array();
						}

						// $this->context->generalSpecialRoleRefs[ $role ]['rules'][ $from ][ $to ] = $node;
						if ( ! isset( $this->context->generalSpecialRoleRefs[ $role ]['arcs'][ $from ][ $to ] ) )
						{
							$this->context->generalSpecialRoleRefs[ $role ]['rules'][ $from ][ $to ] = $node;
							break;
						}

						$current =& $this->context->generalSpecialRoleRefs[ $role ]['rules'][ $from ][ $to ];

						// Are the attributes on this node the same as on the current arc?  If so, then this is just a replacement
						if ( $node['use'] == $current['use'] && $node['priority'] == $current['priority'] && $node['order'] == $current['order'] )
						{
							$current = $node;
							continue 2;  // Go straight to the next item in the forloop
						}

						if (
								( $node['priority'] == $current['priority'] && $node['use'] == XBRL_Constants::$xlinkUseProhibited ) ||
								( $node['priority'] > $current['priority'] )
						   )
						{
							$this->removeFromToArcPair( $role, $arcroleUri, $from, $to, $fromLabel, $toLabel );
							$current = $node;
						}

						unset( $current );

						break;

					case XBRL_Constants::$arcRoleSimilarTuples:

						echo "Similar Tuples\n";

						break;

					case XBRL_Constants::$arcRoleSummationItem:

						// echo "Summation Item";
						// The locators of a summation-item arc MUST be numeric (the concept type must resolve to the xs:decimal base type)
						if ( XBRL::isValidating() )
						{
							$taxonomy = $this->getTaxonomyForXSD( $from );
							$element = $taxonomy->getElementByID( $from );
							if ( $element )
							{
								if ( ! XBRL_Types::getInstance()->resolvesToBaseType( $element['type'], array( 'xs:decimal' ) ) )
								{
									$this->log()->taxonomy_validation( "5.2.5.2", "The 'from' concept of a summation-item arc MUST be numeric",
										array(
											'from' => $from,
											'to' => $to,
											'file' => $xml_basename,
											'path' => $this->getXmlNodePath( $arc )
										)
									);
								}
							}

							$taxonomy = $this->getTaxonomyForXSD( $to );
							$element = $taxonomy->getElementByID( $to );
							if ( $element )
							{
								if ( ! XBRL_Types::getInstance()->resolvesToBaseType( $element['type'], array( 'xs:decimal' ) ) )
								{
									$this->log()->taxonomy_validation( "5.2.5.2", "The 'to' concept of a summation-item arc MUST be numeric",
										array(
											'from' => $from,
											'to' => $to,
											'file' => $xml_basename,
											'path' => $this->getXmlNodePath( $arc )
										)
									);
								}
							}
						}

						if ( property_exists( $attributes, 'weight' ) )
						{
							$node['weight'] = (string )$attributes->weight;
						}
						else
						{
							if ( XBRL::isValidating() )
							$this->log()->taxonomy_validation( "5.2.5.2.1", "Weight attribute missing from calculation link",
								array(
									'from' => $from,
									'to' => $to,
									'file' => $xml_basename,
									'path' => $this->getXmlNodePath( $arc )
								)
							);
						}

						if ( $node['weight'] == 0 )
						{
							if ( XBRL::isValidating() )
							$this->log()->taxonomy_validation( "5.2.5.2.1", "Weight attribute on calculation link MUST NOT be zero",
								array(
									'from' => $from,
									'to' => $to,
									'file' => $xml_basename,
									'path' => $this->getXmlNodePath( $arc )
								)
							);
							break;
						}

						if ( XBRL::isValidating() )
							if ( abs( $node['weight'] ) != 1 )
							{
								$this->log()->business_rules_validation( "Calculation", "Not an error in the XBRL 2.1 specification but calculation arc weights should only be 1 or -1.  See discussion with Hoffman 2019-01-25.",
									array(
										'from' => $from,
										'to' => $to,
										'weight' => $node['weight'],
										'file' => $xml_basename,
										'path' => $this->getXmlNodePath( $arc )
									)
								);
								// break;
							}

						// Add all attributes that are not exempt so they can be used for equivalency tests
						$node['attributes'] = $this->getNonExemptArcAttributes( $arc );

						if ( ! isset( $this->context->calculationRoleRefs[ $role ]['calculations'][ $from ] ) )
						{
							$this->context->calculationRoleRefs[ $role ]['calculations'][ $from ] = array();
						}

						// $this->context->nonDimensionalRoleRefs[ $role ]['calculations'][ $from ][ $to ] = $node;
						if ( ! isset( $this->context->calculationRoleRefs[ $role ]['calculations'][ $from ][ $to ] ) )
						{
							$this->context->calculationRoleRefs[ $role ]['calculations'][ $from ][ $to ] = $node;
							break;
						}

						$current =& $this->context->calculationRoleRefs[ $role ]['calculations'][ $from ][ $to ];

						$prohibited = ( $node['priority'] == $current['priority'] &&
										$node['use'] == XBRL_Constants::$xlinkUseProhibited ) ||
									  ( $node['priority'] > $current['priority'] );
						$equivalent = $this->arcAttrbutesAreEquivalent( $current['attributes'], $node['attributes'] );

						// BMS 2018-05-02 XBRL 2.1 test 210 V-03 and 331 V-02...
						if ( $equivalent )
						{
							// Are the attributes on this node the same as on the current arc?  If so, then this is just a replacement
							if ( $node['use'] == $current['use'] && $node['priority'] == $current['priority'] )
							{
								$current = $node;
								continue 2;  // Go straight to the next item in the forloop
							}

							if (
									( $node['priority'] == $current['priority'] &&
									  $node['use'] == XBRL_Constants::$xlinkUseProhibited ) ||
									( $node['priority'] > $current['priority'] )
							   )
							{
								$this->removeFromToArcPair( $role, $arcroleUri, $from, $to, $fromLabel, $toLabel );
								$current = $node;
							}
						}

						unset( $current );

						break;

					default:

						$node['arcrole'] = $arcrole;

						if ( ! isset( $this->context->nonDimensionalRoleRefs[ $role ][ $arcroleUri ] ) )
						{
							$arcrole = $this->arcroleTypes['link:definitionArc'][ $arcroleUri ];
							$this->context->nonDimensionalRoleRefs[ $role ][ $arcroleUri ] = array();
							$this->context->nonDimensionalRoleRefs[ $role ][ $arcroleUri ]['href'] = $arcrole['taxonomy'];
						}

						if ( ! isset( $this->context->nonDimensionalRoleRefs[ $role ][ $arcroleUri ]['arcs'][ $from ] ) )
						{
							$this->context->nonDimensionalRoleRefs[ $role ][ $arcroleUri ]['arcs'][ $from ] = array();
						}

						if ( ! isset( $this->context->nonDimensionalRoleRefs[ $role ][ $arcroleUri ]['arcs'][ $from ][ $to ] ) )
						{
							$this->context->nonDimensionalRoleRefs[ $role ][ $arcroleUri ]['arcs'][ $from ][ $to ] = $node;
							break;
						}

						$current =& $this->context->nonDimensionalRoleRefs[ $role ][ $arcroleUri ]['arcs'][ $from ][ $to ];

						// Are the attributes on this node the same as on the current arc?  If so, then this is just a replacement
						if ( $node['use'] == $current['use'] && $node['priority'] == $current['priority'] && $node['order'] == $current['order'] )
						{
							$current = $node;
							continue 2;  // Go straight to the next item in the forloop
						}

						if (
							( $node['priority'] == $current['priority'] && $node['use'] == XBRL_Constants::$xlinkUseProhibited ) ||
							( $node['priority'] > $current['priority'] )
						   )
						{
							$this->removeFromToArcPair( $role, $arcroleUri, $from, $to, $fromLabel, $toLabel );
							$current = $node;
						}

						unset( $current );

						break;
				}

				$this->validateFromToArcPairs( $role, $arcroleUri, $from, $to, $fromLabel, $toLabel, $xml_basename, $this->getXmlNodePath( $arc ) );
			}
		}
	}

	/**
	 * Return the path of $node
	 * @param SimpleXMLElement $node
	 * @return number
	 */
	private function getXmlNodePath( $node )
	{
		return dom_import_simplexml( $node )->getNodePath();
	}

	/**
	 * Create a list of exempt attributes to be used in an arc equivalency test
	 * 2018-05-01 At the moment this function is only used on calculation link arcs but needs to be applied to all arc types
	 * @param SimpleXMLElement $arc
	 * @return array
	 */
	private function getNonExemptArcAttributes( $arc )
	{
		$arcAttributes = array( 'order' => array( // 3.5.3.9.5 If the order attribute is not defined then it defaults to 1
				'name' => 'order',
				'type' => 'order',
				'namespace' => null,
				'prefix' => "",
				'value' => 1 )
		);

		// Use the DOM classes because they expose the node properties and will enumerate all attributes
		$domNode = dom_import_simplexml( $arc );
		if ( $domNode->hasAttributes() )
		{
			foreach( $domNode->attributes as $attrKey => $attrNode )
			{
				// 'use', 'priority' and the xlink attributes are exempted from the arc equivalency tests
				if ( in_array( $attrNode->localName, array( 'priority', 'use' ) ) || $attrNode->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] ) continue;

				$qname = new QName( $attrNode->prefix, $attrNode->namespaceURI, $attrNode->localName );
				$arcAttributes[ $qname->clarkNotation() ] = array(
					'name' => $attrNode->localName,
					'type' => $attrNode->nodeName,
					'namespace' => $attrNode->namespaceURI,
					'prefix' => $attrNode->prefix,
					'value' => $attrNode->nodeValue
				);

				if ( ! $attrNode->nextSibling ) break;
				$attrNode = $attrNode->nextSibling;
			}
		}

		return $arcAttributes;
	}

	/**
	 * Tests two arrays for equivalence
	 * 2018-05-01 At the moment this function is only used with calculation link arcs but needs to be applied to all arc types
	 * @param array $attributes1 This array will have been generated by a call to getNonExemptArcAttributes
	 * @param array $attributes2 This array will have been generated by a call to getNonExemptArcAttributes
	 * @return bool
	 */
	private function arcAttrbutesAreEquivalent( $attributes1, $attributes2 )
	{
		$array1Keys = array_keys( $attributes1 );
		$array2Keys = array_keys( $attributes2 );

		// Look for mismatched attributes
		if ( array_diff( $array1Keys, $array2Keys ) || array_diff( $array2Keys, $array1Keys ) ) return false;

		foreach ( $attributes1 as $type => $attribute )
		{
			if ( ! isset( $attributes2[ $type ] ) ) return false;
			$typePrefix1 = $this->context->types->getPrefixForNamespace( $attribute['namespace'] );
			$xequalType1 = XBRL_Equality::xequalAttributeType( $this->context->types, $attribute['name'], $typePrefix1 );
			$typePrefix2 = $this->context->types->getPrefixForNamespace( $attributes2[ $type ]['namespace'] );
			$xequalType2 = XBRL_Equality::xequalAttributeType( $this->context->types, $attributes2[ $type ]['name'], $typePrefix2 );

			if ( ! XBRL_Equality::xequal( $attribute['value'], $attributes2[ $type ]['value'], $xequalType1, $xequalType2 ) )
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Create an array of locators for a link
	 *
	 * @param SimpleXMLElement $link
	 * @param string $linkType The base name of the link such as 'calculation'
	 * @param string $href The name of the document containing $link
	 * @param Closure $callback The Callback will be passed the locator $label, $xsd, $fragment
	 * @return array
	 */
	private function retrieveLocators( $link, $linkType, $href, $callback = null )
	{
		$locators = array();

		// Begin by reading the locators.  This should be done for every link:loc element or
		// any element in the link:loc substitution group
		$elements = $this->context->types->getElementsInSubstitutionGroup( STANDARD_PREFIX_LINK . ":loc" );
		if ( ! $elements ) return $locators;

		$linkbaseNamespaces = $link->getDocNamespaces( true );
		foreach ( $elements as $elementQname )
		{
			// Get the namespace for the element
			if ( ! $elementQname ) continue; // Should not happen

			// Do not discover locators that are not <link:loc>
			$linkLoc = $elementQname->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ];

			foreach ( $link->children( $elementQname->namespaceURI )->{$elementQname->localName} as $locatorKey => /** @var SimpleXMLElement $loc */ $loc )
			{
				$xlinkAttributes = $loc->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
				if ( $linkLoc && ! $this->validateXLinkLocatorAttributes( $xlinkAttributes, $linkType, $href ) )
				{
					continue;
				}

				$type = (string) $xlinkAttributes->type;
				$this->validateXLinkLocatorType( $linkType, $type );

				$label = (string) $xlinkAttributes->label;
				$this->validateXLinkLabel( $linkType, $label );

				$locatorHref = (string) $xlinkAttributes->href;
				if ( $locatorHref )
				{
					$locatorHref = strpos( trim( $locatorHref ), '#' ) === 0 ? "$href$locatorHref" : XBRL::resolve_path( $href, $locatorHref );
				}
				$parts = parse_url( $locatorHref );
				if ( ! isset( $parts['path'] ) || empty( $parts['path'] ) )
				{
					$parts['path'] = parse_url( $href, PHP_URL_PATH );
					// return false;
				}

				$fragment = isset( $parts['fragment'] ) ? $parts['fragment'] : "";

				if ( XBRL::endsWith( $parts['path'], '.xml' ) )
				{
					// It's a linkbase reference
					if ( ! isset( $this->linkbaseIds[ basename( $parts['path'] ) ] ) )
					{
						// This syntax will only work in PHP 7.0 or later
						$linkbaseHref = strpos( $locatorHref, "#" ) === false
								? $locatorHref
								: strstr( $locatorHref, "#", true );
						$linkbaseRef = array(
							'type' => (string) $xlinkAttributes->type,
							// BMS 2017-10-27 This makes the linkbase relative to the location of the schema file which is not correct
							// 'href' => XBRL::resolve_path( $taxonomy->getSchemaLocation(), $href ),
							// This one assumes all linkbases are relative but severities.xml is an example of one that is not
							// 'href' => XBRL::resolve_path( $href, $parts['path'] ),
							'href' => XBRL::resolve_path( $href, $linkbaseHref ),
							'role' => $linkType,
							'arcrole' => \XBRL_Constants::$arcRoleLinkbase,
							'title' => '',
							'base' => '',
						);

						switch ( $linkType )
						{
							case XBRL_Constants::$PresentationLinkbaseRef:

								$this->processPresentationLinkbase( $linkbaseRef );
								break;

							case XBRL_Constants::$LabelLinkbaseRef:

								$this->processLabelLinkbase( $linkbaseRef );
								break;

							case XBRL_Constants::$DefinitionLinkbaseRef:

								$this->processDefinitionLinkbase( $linkbaseRef );
								break;

							case XBRL_Constants::$CalculationLinkbaseRef:

								$this->processCalculationLinkbase( $linkbaseRef );
								break;

							case XBRL_Constants::$ReferenceLinkbaseRef:

								$this->processReferenceLinkbase( $linkbaseRef );
								break;

							default:

								// Handle custom linkbases
								$linkbaseRef['role'] = XBRL_Constants::$anyLinkbaseRef;
								$this->processCustomLinkbaseArcRoles( $linkbaseRef );
								break;
						}
					}

					$taxonomy = $this;
					$reference = basename( $parts['path'] );
				}
				else
				{
					// Is the basename the name of an existing schema?
					// $xsd = pathinfo( $parts['path'], PATHINFO_BASENAME );
					$xsd = strpos( $locatorHref, '#' ) === false ? $locatorHref : strstr( $locatorHref, '#', true );
					$taxonomy = $this->getTaxonomyForXSD( $xsd );
					if ( ! $taxonomy )
					{
						// BMS 2019-01-26 Now that namespaces are indexed by full paths, use the basename alternative here
						//                because it will always be disambiguated here.
						$taxonomy = $this->getTaxonomyForXSD( basename( $xsd ) );
						if ( ! $taxonomy )
						{
							// So this gets a iitte complex.  If the path ends in .xsd then the locator
							// is to a an id in a schema that can be discovered (or not).  If the locator
							// does not have an extension then it could be to a valid document or fragment
							// but its not yet known if the referenced document is a discoverable.  This
							// relevant because of this note in test 70015 V-05:
							// 	A document referenced by a link:loc element must be contained in a DTS
							// 	but the document in the test is an XHTML file which can not become a part
							//	of a DTS because of the following definition:
							// 	  XBRL2.1 - Table 1. Terms and definitions.  A DTS is a collection of
							//	  taxonomy schemas and linkbases.

							// Look for the taxonomy and include its contents in the DTS

							// If the path is absolute then just take the path.
							if ( isset( $parts['schema'] ) || $parts['path'][0] == '/' )
							{
								$href = strpos( $locatorHref, "#" ) === false
									? $locatorHref
									: strstr( $locatorHref, "#", true );
							}
							else
							{
								$href = $this->resolve_path( $this->getSchemaLocation(), $xsd );
							}

							$taxonomy = XBRL::withTaxonomy( $href );
							if ( ! $taxonomy )
							{
								// It is an error to reference an element or document that is not part of the DTS
								$this->log()->taxonomy_validation( "3.2", "The locator reference is to an element that is not part of the DTS", array( 'href' => $locatorHref ) );
								continue;
							}
							$this->indirectNamespaces[] = $taxonomy->getNamespace();
							$taxonomy->AddUserNamespace( $this );
						}
					}

					$reference = basename( $xsd );
				}

				if ( $this->isPointer( $fragment, $taxonomy->xbrlDocument, $taxonomy, $name, $domNode ) )
				{
					$fragment = $name;
					unset( $domNode );
				}

				$parts['fragment'] = $fragment;
				if ( ! $this->validateLocator( $xlinkAttributes, $linkType, $parts, $href ) )
				{
					// Do nothing for now
				}

				if ( $fragment ) $reference .= "#$fragment";

				$locators[ $label ][] = $reference;

				if ( $callback == null ) continue;
				$callback( $label, $xsd, $parts['fragment'] );
			}
		}

		return $locators;
	}

	/**
	 * Check to see if the fragment is a valid XPointer and uses only the element scheme
	 * If validating and there are validation errors then conformance warning messages will be emitted
	 * @param string $fragment
	 * @param SimpleXMLElement $xml
	 * @param XBRL $taxonomy An XBR instance will be passed if the fragment MUST be a concept
	 * @param string $name (by reference) Will store the name attribtute
	 * @param DOMNode|string|null $domNode Returns the node pointed to by the fragment if relevant or null
	 * @return true if the fragment is an XPointer (whether valid or not)
	 */
	private function isPointer( $fragment, $xml, $taxonomy, &$name, &$domNode  )
	{
		$domNode = null;
		$result = false;

		// The fragment could be a pointer
		if ( preg_match_all( "/(?<scheme>.*)\((?<pointer>.*)\)/U", $fragment, $matches ) )
		{
			$result = true;
			$pointers = array();

			// There is a pointer but all the match MUST be 'element'
			$filteredSchemes = array_filter( $matches['scheme'], function( $item ) { return $item != 'element'; } );
			if ( count( $filteredSchemes ) )
			{
				if ( XBRL::isValidating() )
				{
					$this->log()->taxonomy_validation( "3.5.4", "The only XPointer scheme supported is 'element'",
						array(
							'schemes' => "'" . implode( ", ", $filteredSchemes ) . "'",
							'fragment' => $fragment,
						)
					);
				}

				return $result;
			}
			else
			{
				// BMS 2018-04-16 Required for XBRL 2.1 conformance test 202 V-05
				$pointers = $matches['pointer'];
			}

			foreach ( $pointers as $pointer )
			{
				// Set a default
				$fragment = $pointer;

				if ( $pointer && $pointer[0] == '/' )
				{
					// This function uses DOMNode to find the elements in the numbered path given in fragment
					$getNode = function( $node, $positions ) use ( &$getNode )
					{
						$position = array_shift( $positions );
						$nodePosition = 0;
						$domNode = $node->firstChild;

						while( true )
						{
							if ( $domNode->nodeType == XML_ELEMENT_NODE )
							{
								$nodePosition++;
								if ( $nodePosition == $position )
								{
									if ( ! $positions ) return $domNode;
									return $getNode( $domNode, $positions );
								}
							}

							if ( is_null( $domNode->nextSibling ) )
							{
								break;
							}

							$domNode = $domNode->nextSibling;
						}

						return null;
					};

					// Resolve this to a name
					$doc = dom_import_simplexml( $xml )->ownerDocument;
					$domNode = $getNode( $doc, array_filter( explode( '/', $pointer ) ) );
					if ( ! $domNode ) continue;

					if ( $taxonomy )
					{
						// The node MUST have a substitution group and the group MUST resolve to xbrli:item
						if (
							! $domNode->hasAttribute('substitutionGroup') ||
							! XBRL_Types::getInstance()->resolveToSubstitutionGroup( $domNode->getAttribute('substitutionGroup'), array( XBRL_Constants::$xbrliItem ) ) )
						{
							if ( XBRL::isValidating() )
							{
								$this->log()->taxonomy_validation( "5.2.2.1", "The XPointer 'element' value does not reference a valid concept",
									array(
										'fragment' => $pointer,
									)
								);
							}
							continue;
						}

						if ( ! $domNode->hasAttribute('name') ) continue;
						$name = $domNode->getAttribute('name');
					}
					else
					{
						$name = $domNode->localName;
					}

					break;
				}
				else
				{
					if ( $taxonomy )
					{
						// Check the element exists
						if ( ! $taxonomy->getElementByName( $pointer ) )
						{
							if ( XBRL::isValidating() )
							{
								$this->log()->taxonomy_validation( "3.5.1.2", "The XPointer 'element' value does not reference a valid concept",
									array(
										'fragment' => $pointer,
									)
								);
							}
							continue;
						}
					}

					$name = $pointer;

					break;
				}
			}
		}

		return $result;
	}

	/**
	 * Find the taxonomy element for the href.  The namespace of the located element is also retrieved.
	 *
	 * @param string $href		Bookmark of the element to retrieve (xxx.com#yyy)
	 * @param string $linkbase  Caller provided label to include in an error message
	 * @param string $namespace	Optional namespace (not used)
	 * @return NULL|array
	 */
	private function findTaxonomyElement( $href, $linkbase, &$namespace = null )
	{
		// BMS 2019-05-20 This function is almost redundant - at least in its original form
		$taxonomy = $this->getTaxonomyForXSD( $href );
		if ( ! $taxonomy )
		{
			$this->log()->warning( "Taxonomy cannot be found for $href" );
			return null;
		}

		$element = $taxonomy->getElementById( $href );
		if ( $element ) return $element;

		$this->log()->warning( "Cannot find an element for linkbase '$linkbase' element '$href'" );
		return null;

		$taxonomy =& $this;

		// BMS 2019-01-26 Don't know why this test is looking at the PHP_URL_PATH as it is unlikely to match
		//                NOTE: This probably needs changing to test $href against schema location now
		//				  		getTaxonomyForXSD works on full paths.
		// $xsd = parse_url( $href, PHP_URL_PATH );
		$xsd = basename( parse_url( $href, PHP_URL_PATH ) );
		if ( $xsd !== $this->getTaxonomyXSD() )
		{
			// $this->log()->err( "Other taxonomy $href" );
			// BMS 2019-01-26 Now full Urls are being used to
			$taxonomy = $this->getTaxonomyForXSD( $xsd );
			if ( ! $taxonomy )
			{
				$this->log()->warning( "Taxonomy cannot be found for $href" );
				return null;
			}
		}

		// $namespace = $taxonomy->getNamespace();

		// Lookup the element
		$fragment = parse_url( $href, PHP_URL_FRAGMENT );
		$element = $taxonomy->getElementById( $fragment );
		if ( $element ) return $element;

		// $elements =& $taxonomy->getElements();
		// $fragment = parse_url( $href, PHP_URL_FRAGMENT );
		// if ( isset( $elements[ $fragment ] ) )
		// {
		// 	$element =& $elements[ $fragment ];
		// 	return $element;
		// }

		$this->log()->warning( "Cannot find an element for linkbase '$linkbase' element '$href'" );

		return null;
	}

	/**
	 * Create an alternate set of calculations from presentation networks
	 * This might be useful if the taxonomy contains no calculation arcs.
	 */
	public function createCalculationsFromPresentation()
	{
		$getMonetaryNodeLabels = function( $nodes )
		{
			$types = $this->context->types;

			$result = array();
			foreach ( $nodes as $label => $node )
			{
				$taxonomy = $this->getTaxonomyForXSD( $label );
				$element = $taxonomy->getElementById( $label );
				if ( ! $types->resolvesToBaseType( $element['type'], array( XBRL_Constants::$xbrliMonetaryItemType ) ) ) continue;
				$result[] = $label;
			}

			return $result;
		};

		$makeCalculations = function( $nodes, &$calculations ) use( &$makeCalculations, &$getMonetaryNodeLabels )
		{
			$monetaryNodeLabels = $getMonetaryNodeLabels( $nodes );

			$totalLabel = null;
			if ( $monetaryNodeLabels )
			{
				$totalLabel = end( $monetaryNodeLabels );
				$calculations[ $totalLabel ] = array();
			}

			$order = 0;
			foreach ( $nodes as $label => $node )
			{
				if ( isset( $node['children'] ) )
				{
					$lastLabel = $makeCalculations( $node['children'], $calculations );
					if ( $totalLabel && $lastLabel )
					{
						$calculations[ $totalLabel ][ $lastLabel ] = array(
							'to' => $lastLabel,
							'label' => $lastLabel,
							'priority' => 0,
							'use' => 'optional',
							'order' => $order,
							'weight' => 1,
							'attributes' => array()
						);
						$order++;
					}
				}

				if ( ! in_array( $label, $monetaryNodeLabels ) || $label == $totalLabel ) continue;

				$calculations[ $totalLabel ][ $label ] = array(
					'to' => $label,
					'label' => $label,
					'priority' => 0,
					'use' => 'optional',
					'order' => $order,
					'weight' => 1,
					'attributes' => array()
				);
				$order++;
			}

			if ( isset( $calculations[ $totalLabel ] ) && ! $calculations[ $totalLabel ] )
			{
				unset( $calculations[ $totalLabel ] );
			}

			return $totalLabel;
		};

		$calculationRoleRefs = array();

		foreach ( $this->getPresentationRoleRefs() as $elr => $presentationRole )
		{
			if ( ! in_array( $elr, $this->presentationRoles ) ) continue;
			$calculationRoleRefs[ $elr ] = array(
				'href' => '',
				'roleUri' => $elr,
				'type' => 'simple',
				'calculations' => array()
			);
			$makeCalculations( $presentationRole['hierarchy'], $calculationRoleRefs[ $elr ]['calculations'] );
			if ( count( $calculationRoleRefs[ $elr ]['calculations'] ) ) continue;
			unset( $calculationRoleRefs[ $elr ] );
		}

		return $calculationRoleRefs;
	}

	/**
	 * Process calculations and populate the $this->calculationRoleRefs variable with locators, arcs and definitions
	 * @param array $linkbaseRef The link base ref to process
	 * @return boolean
	 */
	public function processCalculationLinkbase( $linkbaseRef )
	{
		// $this->log()->info( "Process definition linkbase {$linkbaseRef[ 'href' ]}" );
		// The 'href' may contain an XPointer fragment that specifies a target in the document
		$parts = explode( '#', $linkbaseRef['href'] );
		$linkbaseRef['href'] = $parts[0];
		$xml_basename = pathinfo( $parts[0], PATHINFO_BASENAME );
		$fragment = isset( $parts[1] ) ? $parts[1] : "";
		$usedOn = 'link:calculationLink';

		// Has it been processed?
		if ( isset( $this->context->processedLinkbases[ "$usedOn:$xml_basename" ] ) )
		{
			return;
		}
		$this->context->processedLinkbases[ "$usedOn:$xml_basename" ] = array( 'linkbase' => $xml_basename, 'usedOn' => $usedOn );

		// If this is an http/https scheme then there should be two forward slashes after the colon.
		// If this is not http/https then there should be just one slash
		$path = XBRL::resolve_path( $linkbaseRef['href'], $linkbaseRef['base'] . $xml_basename );
		// $path = str_replace( "//", "/", pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ) . "/" . $linkbaseRef['base'] . "/" . $xml_basename );
		// $path = preg_replace( '~^(https?):/([^/])~', '$1://$2', $path );

		// $path = preg_replace( '~:/([^/])~', '://$1', pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ) . "/" . $linkbaseRef['base'] . "/" . $xml_basename );
		// $path = str_replace( "//", "/", pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ) . "/" . $linkbaseRef['base'] . $xml_basename );

		$xml = XBRL::getXml( $path, $this->context );
		if ( $xml === null )
		{
			return $this->reportMissingLinkbaseFile( $linkbaseRef );
		}

		if ( $fragment )
		{
			// TODO Validate the XPointer fragment and set the $xml variable to the location defined by the fragment
			if ( $this->isPointer( $fragment, $xml, null, $name, $domNode ) )
			{
				if ( $domNode )
				{
					$xml = simplexml_import_dom( $domNode );
					unset( $domNode );
				}
			}
		}

		$this->processCalculationLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn );
	}

	/**
	 * Processes a calculation linkbase given SimpleXMLElement node
	 * @param array $linkbaseRef
	 * @param SimpleXMLElement $xml
	 * @param string $xml_basename
	 * @param string $usedOn
	 */
	private function processCalculationLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn )
	{
		// Make sure this file contains calculation link elements so is valid for the role type
		if ( ! count( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->calculationLink ) )
		{
			if ( ! empty( $linkbaseRef['role'] ) && $linkbaseRef['role'] != XBRL_Constants::$anyLinkbaseRef )
			{
				$this->reportNoRoleLinks( $linkbaseRef, 'calculation' );
			}
			return;
		}

		$arcroleRefs = array();
		$taxonomy_base_name = $this->getTaxonomyXSD();

		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->arcroleRef as $arcroleRefKey => $arcroleRef )
		{
			$attributes = $arcroleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$arcroleRefHref = (string) $attributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$arcroleRefParts = explode( "#", $arcroleRefHref );
			if ( ! $arcroleRefParts )
			{
				$arcroleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $arcroleRefParts[0] )
			{
				$arcroleRefParts[0] = $this->getTaxonomyXSD();
				$arcroleRefHref = implode( "#", $arcroleRefParts );
			}

			$arcroleUri = (string) $arcroleRef->attributes()->arcroleURI;
			$fragment = parse_url( $arcroleRefHref, PHP_URL_FRAGMENT );

			if ( XBRL::isValidating() )
			{
				if ( isset( $arcroleRefs[ $arcroleUri ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one arcroleRef element with the same @arcroleURI attribute value",
						array(
							'arcrole' => $arcroleUri,
							'href' => $xml_basename,
						)
					);
				}
			}

			$arcroleRefs[ $arcroleUri ] = $fragment;

			$taxonomy = $this->getTaxonomyForXSD( $arcroleRefHref );
			if ( ! $taxonomy )
			{
				$parts = explode( '#', $arcroleRefHref );
				if ( isset( XBRL_Global::$taxonomiesToIgnore[ $parts[0] ] ) ) continue;
				if ( count( $parts ) == 1 || ! isset( XBRL_Global::$taxonomiesToIgnore[ $parts[0] ] ) )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "Cannot locate the schema for the arcroleref",
						array(
							'href' => "'$arcroleRefHref'",
							'linkbase' => "'$xml_basename'",
						)
					);
					continue;
				}
			}

			// This role MUST be defined as 'usedOn' in the linkbaseRef for link:calculationArc
			if ( ! count( $taxonomy->arcroleTypes ) || ! isset( $taxonomy->arcroleTypes['link:calculationArc'][ $arcroleUri ] ) )
			{
				$this->log()->taxonomy_validation( "5.1.3.4", "This arcrole is not defined to be used on the calculation linkbase",
					array(
						'arcrole' => "'$arcroleUri'",
						'linkbase' => "'$xml_basename'",
					)
				);
				continue;
			}

			// Make sure this arcrole is in the current taxonomy
			$this->arcroleTypes['link:calculationArc'][ $arcroleUri ] = $taxonomy->arcroleTypes['link:calculationArc'][ $arcroleUri ];
		}

		if ( ! isset( $this->roleTypes[ $usedOn ] ) )
		{
			$this->roleTypes[ $usedOn ] = array();
		}

		$linkbaseRoleRefs = array();

		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->roleRef as $roleRefKey => $roleRef )
		{
			$xlinkAttributes = $roleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$roleRefAttributes = $roleRef->attributes();
			$roleUri = (string) $roleRefAttributes->roleURI;
			$roleRefHref = (string) $xlinkAttributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$roleRefParts = explode( "#", $roleRefHref );
			if ( ! $roleRefParts )
			{
				$roleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $roleRefParts[0] )
			{
				$roleRefParts[0] = $this->getTaxonomyXSD();
				$roleRefHref = implode( "#", $roleRefParts );
			}

			if ( XBRL::isValidating() )
			{
				// BMS 2018-04-26 This test works when the linkbases are in different files not so much when they are all in the schema document
				// if ( isset( $this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] ) )
				if ( isset( $linkbaseRoleRefs[ "$usedOn:$roleUri" ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one roleRef element with the same @roleURI attribute value",
						array(
							'role' => $roleUri,
							'usedon' => $usedOn,
							'href' => $xml_basename,
						)
					);
				}
			}

			$linkbaseRoleRefs[ "$usedOn:$roleUri" ] = $roleRefHref;
			$this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] = $roleRefHref;

			$taxonomy = $this->getTaxonomyForXSD( $roleRefHref );
			if ( ! $taxonomy )
			{
				if ( XBRL::isValidating() )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "The role taxonomy cannot be located",
						array(
							'role' => $roleUri,
							'href' => $roleRefHref,
						)
					);
				}
				continue;
			}

			$this->context->calculationRoleRefs[ $roleUri ] = array(
				'href' => XBRL::resolve_path( $linkbaseRef['href'], $roleRefHref ),
				'roleUri' => $roleUri,
				'type' => (string) $xlinkAttributes->type,
			);


			if ( ! isset( $taxonomy->roleTypes[ $usedOn ][ $roleUri ] ) )
			{
				$taxonomy->roleTypes[ $usedOn ][ $roleUri ] = array(
					'definition' => "",
					'roleURI' => $roleUri,
					'taxonomy' => $taxonomy->schemaLocation,
					'id' => "",
				);
			}
		}

		if ( count( $this->context->calculationRoleRefs ) === 0 || ! isset( $this->context->calculationRoleRefs[ XBRL_Constants::$defaultLinkRole ] ) )
		{
			$roleUri = XBRL_Constants::$defaultLinkRole;

			$this->context->calculationRoleRefs[ XBRL_Constants::$defaultLinkRole ] = array(
				'type' => 'simple',
				// Why is this being done?  It can lead to an invalid url as happens when compiling a fac
				// where the linkbases are in the relations folder and the schema in the reporting styles folder
				'href' => XBRL::resolve_path( $linkbaseRef['href'], $this->getTaxonomyXSD() ), // $linkbaseRef['href'],
				'roleUri' => $roleUri,
			);

			if ( ! isset( $this->roleTypes[ $usedOn ][ $roleUri ] ) )
			{
				$this->roleTypes[ $usedOn ][ $roleUri ] = array(
					'definition' => "",
					'roleURI' => $roleUri,
					'taxonomy' => $this->schemaLocation,
					'id' => "",
				);
			}
		}

		$standardCalculationRoles = array( XBRL_Constants::$arcRoleSummationItem );

		// Process all the role refs
		foreach ( $this->context->calculationRoleRefs as $roleRefsKey => $calculationRoleRef )
		{
			// Find the calculation link with the same role
			foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->calculationLink as $linkKey => $calculationLink )
			{
				// Detection of duplicate from/to pairs only applies within an extended link so reset this varaible in each new link
				$this->resetValidateFromToArcPairs();

				$attributes = $calculationLink->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
				if ( ! property_exists( $attributes, 'role' ) || (string) $attributes->role != $roleRefsKey )
				{
					continue;
				}

				$this->processNonDimensionalLink(
					$calculationLink,
					XBRL_Constants::$CalculationLinkbaseRef,
					$roleRefsKey,
					$standardCalculationRoles,
					$arcroleRefs,
					$linkbaseRef['href'],
					$xml_basename,
					'calculationArc',
					'Calculation'
				);
			}
		}
	}

	/**
	 * Removes an arc from the hierarchy and members or definitions list
	 * @param string $roleUri
	 * @param string $from
	 * @param string $to
	 */
	public function removeDefinitionArc( $roleUri, $from, $to )
	{
		if ( ! isset( $this->definitionRoleRefs[ $roleUri ] ) ) return false;

		if ( isset( $this->definitionRoleRefs[ $roleUri ]['primaryitems'][ $to ] ) )
		{
			unset( $this->definitionRoleRefs[ $roleUri ]['primaryitems'][ $to ] );
		}

		if ( isset( $this->definitionRoleRefs[ $roleUri ]['members'][ $to ] ) )
		{
			unset( $this->definitionRoleRefs[ $roleUri ]['members'][ $to ] );
		}

		if ( isset( $this->definitionRoleRefs[ $roleUri ]['dimensions'][ $to ] ) )
		{
			unset( $this->definitionRoleRefs[ $roleUri ]['dimensions'][ $to ] );
		}
	}

	/**
	 * Process definitions and populate the $this->definitionRoleRefs variable with locators, arcs and definitions
	 * @param array $linkbaseRef The link base ref to process
	 * @return boolean
	 */
	public function processDefinitionLinkbase( $linkbaseRef )
	{
		// $this->log()->info( "Process definition linkbase {$linkbaseRef[ 'href' ]}" );
		// The 'href' may contain an XPointer fragment that specifies a target in the document
		$parts = explode( '#', $linkbaseRef['href'] );
		$linkbaseRef['href'] = $parts[0];
		$xml_basename = pathinfo( $parts[0], PATHINFO_BASENAME );
		$fragment = isset( $parts[1] ) ? $parts[1] : "";
		$usedOn = 'link:definitionLink';

		// Has it been processed?
		if ( isset( $this->context->processedLinkbases[ "$usedOn:$xml_basename" ] ) )
		{
			return;
		}

		$this->context->processedLinkbases[ "$usedOn:$xml_basename" ] = array( 'linkbase' => $xml_basename, 'usedOn' => $usedOn );

		// If this is an http/https scheme then there should be two forward slashes after the colon.
		// If this is not http/https then there should be just one slash
		$path = XBRL::resolve_path( $linkbaseRef['href'], $linkbaseRef['base'] . $xml_basename );
		// $path = str_replace( "//", "/", pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ) . "/" . $linkbaseRef['base'] . "/" . $xml_basename );
		// $path = preg_replace( '~^(https?):/([^/])~', '$1://$2', $path );

		$xml = XBRL::getXml( $path, $this->context );
		if ( $xml === null )
		{
			return $this->reportMissingLinkbaseFile( $linkbaseRef );
		}

		if ( $fragment )
		{
			// TODO Validate the XPointer fragment and set the $xml variable to the location defined by the fragment
			if ( $this->isPointer( $fragment, $xml, null, $name, $domNode ) )
			{
				if ( $domNode )
				{
					$xml = simplexml_import_dom( $domNode );
					unset( $domNode );
				}
			}
		}

		$this->processDefinitionLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn );
	}

	/**
	 * Processes a definition linkbase given SimpleXMLElement node
	 * @param array $linkbaseRef
	 * @param SimpleXMLElement $xml
	 * @param string $xml_basename
	 * @param string $usedOn
	 */
	private function processDefinitionLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn )
	{
		// Make sure this file contains definition link elements so is valid for the role type
		if ( ! count( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->definitionLink ) )
		{
			if ( ! empty( $linkbaseRef['role'] ) && $linkbaseRef['role'] != XBRL_Constants::$anyLinkbaseRef )
			{
				$this->reportNoRoleLinks( $linkbaseRef, 'definition' );
			}
			return;
		}

		$taxonomy_base_name = $this->getTaxonomyXSD();

		$arcroleRefs = array();
		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->arcroleRef as $arcroleRefKey => $arcroleRef )
		{
			$attributes = $arcroleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$arcroleRefHref = (string) $attributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$arcroleRefParts = explode( "#", $arcroleRefHref );
			if ( ! $arcroleRefParts )
			{
				$arcroleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $arcroleRefParts[0] )
			{
				$arcroleRefParts[0] = $this->getTaxonomyXSD();
				$arcroleRefHref = implode( "#", $arcroleRefParts );
			}

			$arcroleUri = (string) $arcroleRef->attributes()->arcroleURI;
			$fragment = parse_url( $arcroleRefHref, PHP_URL_FRAGMENT );

			if ( XBRL::isValidating() )
			{
				if ( isset( $arcroleRefs[ $arcroleUri ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one arcroleRef element with the same @arcroleURI attribute value",
						array(
							'role' => $arcroleUri,
							'href' => $xml_basename,
						)
					);
				}
			}

			$arcroleRefs[ $arcroleUri ] = $fragment;

			$xsd = strpos( $arcroleRefHref, '#' ) === false ? $arcroleRefHref : strstr( $arcroleRefHref, '#', true );
			if ( isset( XBRL_Global::$taxonomiesToIgnore[ $xsd ] ) ) continue;
			$taxonomy = $this->getTaxonomyForXSD( $xsd );

			if ( ! $taxonomy )
			{
				$xsd = $this->resolve_path( $linkbaseRef['href'], $xsd );
				// If the taxonomy is not already loaded, try loading it.
				$taxonomy = $xsd ? XBRL::withTaxonomy( $xsd ) : null;
				if ( ! $taxonomy )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "Taxonomy for arcroleRef href does not exist",
						array(
							'href' => "'$arcroleRefHref'",
							'linkbase' => "'$xml_basename'",
						)
					);

					continue;
				}
				$this->indirectNamespaces[] = $taxonomy->getNamespace();
				$taxonomy->AddUserNamespace( $this );
			}

			// This role MUST be defined as 'usedOn' in the linkbaseRef for link:definitionArc
			if ( ! count( $taxonomy->arcroleTypes ) || ! isset( $taxonomy->arcroleTypes['link:definitionArc'][ $arcroleUri ] ) )
			{
				$this->log()->taxonomy_validation( "5.1.3.4", "This arcrole is not defined to be used on the definition linkbase",
					array(
						'arcrole' => "'$arcroleUri'",
						'linkbase' => "'$xml_basename'",
					)
				);
				continue;
			}

			// Make sure this arcrole is in the current taxonomy
			$this->arcroleTypes['link:definitionArc'][ $arcroleUri ] = $taxonomy->arcroleTypes['link:definitionArc'][ $arcroleUri ];
		}

		if ( ! isset( $this->roleTypes[ $usedOn ] ) )
		{
			$this->roleTypes[ $usedOn ] = array();
		}

		$linkbaseRoleRefs = array();

		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->roleRef as $roleRefKey => $roleRef )
		{
			$xlinkAttributes = $roleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$roleRefAttributes = $roleRef->attributes();
			$roleUri = (string) $roleRefAttributes->roleURI;
			$roleRefHref = (string) $xlinkAttributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$roleRefParts = explode( "#", $roleRefHref );
			if ( ! $roleRefParts )
			{
				$roleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $roleRefParts[0] )
			{
				$roleRefParts[0] = $this->getTaxonomyXSD();
				$roleRefHref = implode( "#", $roleRefParts );
			}

			if ( XBRL::isValidating() )
			{
				// BMS 2018-04-26 This test works when the linkbases are in different files not so much when they are all in the schema document
				// if ( isset( $this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] ) )
				if ( isset( $linkbaseRoleRefs[ "$usedOn:$roleUri" ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one roleRef element with the same @roleURI attribute value",
						array(
							'role' => $roleUri,
							'usedon' => $usedOn,
							'href' => $xml_basename,
						)
					);
				}
			}

			$linkbaseRoleRefs[ "$usedOn:$roleUri" ] = $roleRefHref;
			$this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] = $roleRefHref;

			$taxonomy = $this->getTaxonomyForXSD( $roleRefHref );
			if ( ! $taxonomy )
			{
				// Look for the taxonomy and include its contents in the DTS
				// BMS 2018-09-01 Seems like this is the better thing to do
				// $xsd = $this->resolve_path( $this->getSchemaLocation(), $roleRefHref );
				$xsd = $this->resolve_path( $linkbaseRef['href'], $roleRefHref );

				$taxonomy = XBRL::withTaxonomy( strpos( $xsd, '#' ) ? strstr( $xsd, '#', true ) : $xsd );
				$taxonomy = $this->getTaxonomyForXSD( $roleRefHref );
				if ( ! $taxonomy )
				{
					if ( XBRL::isValidating() )
					{
						$this->log()->taxonomy_validation( "5.1.3.4", "The role taxonomy cannot be located",
							array(
								'role' => $roleUri,
								'href' => $roleRefHref,
							)
						);
					}
					continue;
				}
				$this->indirectNamespaces[] = $taxonomy->getNamespace();
				$taxonomy->AddUserNamespace( $this );
			}

			// This role MUST be defined as 'usedOn' in the linkbaseRef for link:definitionLink IN THE TAXONOMY POINTED TO BY THE HREF
			if ( ! isset( $taxonomy->roleTypes[ $usedOn ][ $roleUri ] ) )
			{
				if (  XBRL::isValidating() )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "This role is not defined to be used on the definition linkbase",
						array(
							'role' => $roleUri,
						)
					);
				}

				$taxonomy->roleTypes[ $usedOn ][ $roleUri ] = array(
					'definition' => "Definition link base",
					'roleURI' => $roleUri,
					'taxonomy' => $taxonomy->schemaLocation,
					'id' => "",
				);
			}

			// Create a placeholder for this role if the role references a different taxonomy.
			// Any definitions for all locally defined roles will be created later.
			// If the role references the default link it will be created later or already have been created.
			if ( $roleUri != XBRL_Constants::$defaultLinkRole && $taxonomy != $this )
			{
				$this->definitionRoleRefs[ $roleUri ] = array(
					'href' => $taxonomy->schemaLocation,
					'roleUri' => $roleUri,
					'type' => XBRL_Constants::$arcRoleTypeSimple,
				);
			}

		}

		// Create a validated list of definition role refs
		foreach( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->definitionLink as $key => $definitionLink )
		{
			$definitionAttributes = $definitionLink->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			if ( ! property_exists( $definitionAttributes, 'role' ) )
			{
				if ( XBRL::isValidating() )
				{
					$this->log()->taxonomy_validation( "3.5.3.3", "The link element MUST have a role attribute",
						array(
							'linkbase' => "'$xml_basename'",
							'schema' => $this->getTaxonomyXSD(),
						)
					);
				}
				continue;
			}

			if ( ! property_exists( $definitionAttributes, 'type' ) )
			{
				if ( XBRL::isValidating() )
				{
					$this->log()->taxonomy_validation( "3.5.3.3", "The link element MUST have a type attribute",
						array(
							'linkbase' => "'$xml_basename'",
							'schema' => $this->getTaxonomyXSD(),
						)
					);
				}
			}
			else
			{
				$type = (string) $definitionAttributes->type;

				if (  XBRL::isValidating() && $type != 'extended' )
				{
					$this->log()->taxonomy_validation( "3.5.3.3", "The link element type attribute MUST be 'extended'",
						array(
							'linkbase' => "'$xml_basename'",
							'type' => "'$type'",
							'schema' => $this->getTaxonomyXSD(),
						)
					);
				}
			}

			$roleUri = (string) $definitionAttributes->role;

			// If this role has been seen already then there's nothing more to do
			if ( isset( $this->definitionRoleRefs[ $roleUri ] ) ) continue;

			if ( $roleUri == XBRL_Constants::$defaultLinkRole )
			{
				// If the default role has not already been used make the first use the 'home' of the default extended link
				if ( ! isset( $this->context->defaultLinkHref ) && $this->getTaxonomyXSD() )
				{
					// $this->context->defaultLinkHref = XBRL::resolve_path( $linkbaseRef['href'], $this->getTaxonomyXSD() );
					$this->context->defaultLinkHref = $this->getSchemaLocation();
				}

				$this->definitionRoleRefs[ XBRL_Constants::$defaultLinkRole ] = array(
					'type' => 'simple',
					'href' => $this->context->defaultLinkHref,
					'roleUri' => $roleUri,
				);

				if ( ! isset( $this->roleTypes[ $usedOn ][ $roleUri ] ) )
				{
					$this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] = $this->getTaxonomyXSD();

					$this->roleTypes[ $usedOn ][ $roleUri ] = array(
						'definition' => "Definition link base",
						'roleURI' => $roleUri,
						'taxonomy' => $this->schemaLocation,
						'id' => "",
					);
				}
			}
			else
			{
				// The role MUST exist in linkbaseRoleTypes
				if ( ! isset( $this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] ) )
				{
					if ( XBRL::isValidating() )
					{
						$this->log()->taxonomy_validation( "3.5.3.3", "The link element role MUST be defined as a roleRef element in the linkbase file",
							array(
								'linkbase' => "'$xml_basename'",
								'role' => "'$roleUri'",
								'schema' => $this->getTaxonomyXSD(),
							)
						);
					}

					continue;
				}

				$parts = explode( '#', $this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] );
				$role_taxonomy = $this->getTaxonomyForXSD( $parts[0] );
				if ( ! $role_taxonomy )
				{
					continue;
				}

				$this->definitionRoleRefs[ $roleUri ] = array(
					'href' => XBRL::resolve_path( $linkbaseRef['href'], $this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] ),
					'roleUri' => $roleUri,
					'type' => $type,
				);
			}
		}

		$standardNonDimensionalRoles = array(
			XBRL_Constants::$arcRoleRequiresElement,
			XBRL_Constants::$arcRoleEssenceAlias,
			XBRL_Constants::$arcRoleGeneralSpecial,
			XBRL_Constants::$arcRoleSimilarTuples,
			XBRL_Constants::$arcRoleSummationItem,
		);

		// Process all the role refs
		foreach ( $this->definitionRoleRefs as $roleRefsKey => $definitionRoleRef )
		{
			$nodes = array();
			$locators = array();
			$hypercubes = array();

			// BMS 2015-03-04
			$home_taxonomy = $definitionRoleRef['href'] && $taxonomy_base_name && strpos( $definitionRoleRef['href'], $taxonomy_base_name ) === false
				? $this->getTaxonomyForXSD( $definitionRoleRef['href'] )
				: $this;

			if ( ! $home_taxonomy )
			{
				$this->log()->taxonomy_validation( "unknown", "Unable to locate a taxonomy for href", array(
					'href' => $definitionRoleRef['href'],
				) );
				continue;
			}

			$primaryItems = $home_taxonomy->getDefinitionRolePrimaryItems( $roleRefsKey, true );

			$xml->registerXPathNamespace( 'link', XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] );
			$xml->registerXPathNamespace( 'xlink', XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$links = $xml->xpath( "/link:linkbase/link:definitionLink[@xlink:role=\"$roleRefsKey\"]" );
			$hasArcRole = count( $xml->xpath( "/link:linkbase/link:definitionLink[@xlink:role=\"$roleRefsKey\"]/link:definitionArc[starts-with(@xlink:arcrole, '" .XBRL_Constants::$arcRoleBase . "')]" ) );

			// Find the definition link with the same role
			foreach ( $links as $linkKey => $definitionLink )
			{
				if ( $hasArcRole )
				{
					$this->processNonDimensionalLink(
						$definitionLink,
						XBRL_Constants::$DefinitionLinkbaseRef,
						$roleRefsKey,
						$standardNonDimensionalRoles,
						$arcroleRefs,
						$linkbaseRef['href'],
						$xml_basename
					);

					continue;
				}

				// Analyse the elements to form a tree
				// Begin by reading the locators
				$me = $this;
				$locators += $this->retrieveLocators(
					$definitionLink,
					XBRL_Constants::$DefinitionLinkbaseRef,
					$linkbaseRef['href']
				);
				unset( $me );

				if ( ! count( $locators ) ) break;

				// Used to catch duplicated from/to label pairs which is not alloed by the XLink specification
				$fromToPairs = array();

				// Process the definition arcs and build a hierarchy
				// $this->log()->info( "Process the definition arcs and build a hierarchy ($roleRefsKey)" );
				foreach ( $definitionLink->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->definitionArc as $arcKey => /** @var SimpleXMLElement $definitionArc */ $definitionArc )
				{
					$xlinkAttributes = $definitionArc->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
					$attributes = $definitionArc->attributes();

					if ( ! $this->validateXLinkArcAttributes( $xlinkAttributes, 'definitions' ) )
					{
						continue;
					}

					if ( isset( $fromToPairs[ (string)$xlinkAttributes->from ][ (string)$xlinkAttributes->to ] ) )
					{
						$this->log()->taxonomy_validation( "XLink", "Definition arcs contain repeated from/to label pairs in the same extended link",
							array(
								'role' => $roleRefsKey,
								'from' => (string)$xlinkAttributes->from,
								'to' => (string)$xlinkAttributes->to
							)
						);
					}
					else
					{
						$fromToPairs[ (string)$xlinkAttributes->from ][ (string)$xlinkAttributes->to ] = 1;
					}

					$arcroleUri = (string) $xlinkAttributes->arcrole;
					// $dimensional = isset( $this->arcroleTypes['link:definitionArc'][ $arcroleUri] );
					if ( ! in_array( $arcroleUri, XBRL_Constants::$arcRoleDimensional ) )
					{
						if ( isset( $arcroleRefs[ $arcroleUri ] ) )
						{
							$arcRoleList = array( $arcroleUri );

							$this->processNonDimensionalArc(
								$definitionArc,
								$locators,
								'definition',
								$roleRefsKey,
								$xlinkAttributes,
								$attributes,
								$arcRoleList,
								$arcroleRefs,
								$xml_basename,
								'Custom arcrole'
							);
							continue;
						}

						if ( XBRL::isValidating() )
						{
							$this->log()->dimension_validation( "5.2.6.2", "The non-standard arcrole on the definition arc has not been defined",
								array(
									'arcrole' => $arcroleUri,
								)
							);
						}

						continue;
					}

					$fromLabel = (string) $xlinkAttributes->from;
					$this->validateXLinkLabel( 'definitions', $fromLabel );

					$toLabel   = (string) $xlinkAttributes->to;
					$this->validateXLinkLabel( 'definitions', $toLabel );

					$fromList	= $locators[ $fromLabel ];
					$toList		= $locators[ $toLabel ];

					$preferredLabel = (string)$definitionArc->attributes( XBRL_Constants::$genericPreferredLabel );
					$preferredLabelFound = false;

					foreach ( $fromList as $from )
					{
						foreach ( $toList as $to )
						{
							// Special case for handling generic preferred label arcs
							if ( $preferredLabel )
							{
								// A label must exist
								if ( isset( $this->context->labels[ XBRL_Constants::$defaultLinkRole ]['arcs'][ $to ] ) )
								{
									$targets = &$this->context->labels[ XBRL_Constants::$defaultLinkRole ]['arcs'][ $to ];
									foreach ( $targets as $target => $labels )
									{
										foreach ( $labels as $label )
										{
											if ( $label['role'] == $preferredLabel )
											{
												$preferredLabelFound = true;
												break;
											}
										}
									}
								}

								continue;
							}

							$arcrole = isset( $arcroleRefs[ $arcroleUri ] ) ? $arcroleRefs[ $arcroleUri ] : $arcroleUri;
							$fromTaxonomyElement = $this->findTaxonomyElement( $from, 'definitions', $namespace );

							if ( is_null( $fromTaxonomyElement ) )
							{
								continue;
							}

							// if ( $arcrole === 'dimension-default' )
							if ( $arcroleUri === XBRL_Constants::$arcRoleDimensionDefault )
							{
								$namespace = null;
								$default_element = $this->findTaxonomyElement( $to, 'definitions', $namespace );
								if ( ! is_array( $default_element ) )
								{
									$this->log()->warning( "The taxonomy element for dimension-default label '$to' cannot be found" );
									continue;
								}

								if ( XBRL::isValidating() )
								{
									// 2.7.1.1 The source must be an explicit dimension declaration [Def 10]
									if ( ! XBRL_Types::getInstance()->resolveToSubstitutionGroup( $fromTaxonomyElement['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) ) )
									{
										$this->log()->dimension_validation( "2.7.1.1", "The source must be an explicit dimension declaration [Def 10]",
											array(
												'concept' => "'$from'",
												'substitutionGroup' => "'{$fromTaxonomyElement['substitutionGroup']}'",
												'error' => 'xbrldte:DimensionDefaultSourceError',
											)
										);
									}

									// 2.7.1.1 The target must be a domain member declaration [Def 11]
									// xbrli:item substitution group and not in the xbrldt:hypercubeItem or xbrldt:dimensionItem
									if ( ! $this->isPrimaryItem( $default_element ) )
									{
										$this->log()->dimension_validation( "2.7.1.1", "The target must be a domain member declaration [Def 11]",
											array(
												'concept' => "'$to'",
												'substitutionGroup' => "'{$default_element['substitutionGroup']}'",
												'error' => 'xbrldte:DimensionDefaultTargetError',
											)
										);
									}
								}

								$default = array(
									'label' => $to,
									'namespace' => $namespace,
									'nodeclass' => 'dimension',
								);

								// XDT 2.7.1 Dimension defaults are global so there should never be two defaults for the same dimension
								if ( isset( $this->context->dimensionDefaults[ $from ] ) )
								{
									if ( xbrl::isValidating() )
									{
										// Except if the default is repeating an existing arc.
										if ( parse_url( $this->context->dimensionDefaults[ $from ]['label'], PHP_URL_FRAGMENT ) != $default_element['id'] )
										{
											$this->log()->dimension_validation( "2.7.1.1", "A dimension should have only one default member",
												array(
													'dimension' => "'$from'",
													'default' => "'$to'",
													'error' => 'xbrldte:TooManyDefaultMembersError',
												)
											);
										}
									}
								}
								else
								{
									$this->context->dimensionDefaults[ $from ] = $default;
								}

								continue;
							}
							else
							{
								extract( $this->validateArcAttributes( 'definitions', $fromLabel, $toLabel, $attributes ) );

								// Create a node for the 'to' component
								// if ( ! isset( $nodes[ $to ] ) && $use != 'prohibited' )
								if ( ! isset( $nodes[ $to ] ) )
								{
									$nodes[ $to ] = array(
										'label' => $to,
										'parents' => array( $from => array() ),
									);
								}
								else if ( ! isset( $nodes[ $to ]['parents'][ $from ] ) )
								{
									$nodes[ $to ]['parents'][ $from ] = array();
								}

								// If its a hypercube, let the merge role function handle the prohibition
								if ( $use == 'prohibited' && ! in_array( $arcroleUri, \XBRL_Constants::$hasHypercube ) )
								{
									echo "Use prohibited ($to)\n";

									if (
										  ! isset( $nodes[ $to ]['parents'][ $from ]['priority'] ) ||
											$nodes[ $to ]['parents'][ $from ]['priority'] >= $priority // $priority here is from the extracted result above
									   )
									{
										// If this node represents a primary item then remove it from the list
										// Otherwise the prohibition will be affected in the merge hierarchy function.
										if ( isset( $primaryItems[ $to ] ) )
										{
											unset( $primaryItems[ $to ] );
										}

										$home_taxonomy->removeDefinitionArc( $roleRefsKey, $from, $to );
									}

									continue;
								}

								if ( ! count( $nodes[ $to ]['parents'][ $from ] ) )
								{
									$nodes[ $to ]['parents'][ $from ]['arcrole'] 	= $arcroleUri;
									$nodes[ $to ]['parents'][ $from ]['order']		= $order;
									$nodes[ $to ]['parents'][ $from ]['use']		= $use;
									$nodes[ $to ]['parents'][ $from ]['title']		= $title;
									$nodes[ $to ]['parents'][ $from ]['priority']	= $priority;
								}
								else if ( $priority > 0 )
								{
									if ( ! isset( $nodes[ $to ]['parents'][ $from ]['priority'] ) ||
										 $priority > $nodes[ $to ]['parents'][ $from ]['priority'] )
									{
										// only replace if the priority is higher
										$nodes[ $to ]['parents'][ $from ] = array();
									}
								}

								$namespace = null;
								$taxonomyElement = $this->findTaxonomyElement( $to, 'definitions', $namespace );

								// if ( $taxonomyElement === null )
								//	; // $this->log()->warning( "The taxonomy element for label '$to' cannot be found" );
								// else
								// {
								//	$nodes[ $to ]['taxonomy_element'] = $taxonomyElement;
								//}

								$xbrldt = $definitionArc->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDT ] );
								$usable = 1;
								if ( property_exists( $xbrldt, 'usable' ) )
								{
									// The value MUST be boolean
									$usable = filter_var( (string) $xbrldt->usable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
									if ( is_null( $usable ) )
									{
										if ( XBRL::isValidating() )
										{
											$this->log()->dimension_validation( "2.5.3.3.1", "The value of the xbrldt:usable attribute must be Boolean",
												array(
													'usable' => "'" . (string) $xbrldt->usable . "'",
													'from' => "'$from'",
													'to' => "'$to'",
													'error' => 'sche:XmlSchemaError',
												)
											);
										}

										$usable = 1;
									}

									if ( XBRL::isValidating() )
									{
										// The attribute MUST only appear on domain-member and dimension domain arcs
										if ( ! in_array( $arcroleUri, array( XBRL_Constants::$arcRoleDimensionDomain, XBRL_Constants::$arcRoleDomainMember ) ) )
										{
											$this->log()->dimension_validation( "2.5.3.3", "The 'usable' attribute MUST only appear on dimension-domain and domain-member arcs",
												array(
													'arcrole' => "'$arcroleUri'",
													'from' => "'$from'",
													'to' => "'$to'",
													'error' => 'sche:XmlSchemaError',
												)
											);
										}
									}
								}

								if ( (string) $xbrldt->targetRole )
								{
									$targetRole = (string) $xbrldt->targetRole;
									// targetRole MUST be a valid URI
									if ( XBRL::isValidating() &&
										 // BMS 2018-05-03 The target role should be validated against the $linkbaseRoleRefs list
										 // ! isset( $this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$targetRole" ] ) &&
										 // ! isset( $this->context->defaultLinkHref )
											! isset( $linkbaseRoleRefs[ "$usedOn:$targetRole" ] ) &&
											$targetRole != XBRL_Constants::$defaultLinkRole
									)
									{
										$this->log()->dimension_validation( "2.4.3.3", "The xbrldt:targetRole attribute itself MUST contain a role declared in the same linkbase",
											array(
												'linkbase' => "'$xml_basename'",
												'targetRole' => "'$targetRole'",
												'error' => 'xbrldte:TargetRoleNotResolvedError',
											)
										);
									}

									$nodes[ $to ]['parents'][ $from ]['targetRole'] = $targetRole;
									// $nodes[ $to ]['parents'][ $from ]['href'] = $to;

									$this->xdtTargetRoles[ $roleRefsKey ][ $targetRole ][ $xml_basename ][ $from ][ $to ] = array(
										'arcrole' => $arcroleUri,
									);
								}

								// If the arcrole is hasAll then there MUST be a 'contextElement' attribute
								if ( in_array( $arcroleUri, array( XBRL_Constants::$arcRoleAll, XBRL_Constants::$arcRoleNotAll ) ) )
								{
									if ( property_exists( $xbrldt, 'contextElement' ) )
									{
										// If context element exists it MUST have a value of 'segment' or 'scenario'
										$contextElement = (string) $xbrldt->contextElement;
										if ( $contextElement && ( $contextElement == XBRL_Constants::$xbrliScenario || $contextElement == XBRL_Constants::$xbrliSegment ) )
										{
											$nodes[ $to ]['parents'][ $from ]['contextElement'] = $contextElement;
										}
										else if ( XBRL::isValidating() )
										{
											$this->log()->dimension_validation( "2.3.2.1", "The xbrldt:contextElement attribute MUST have one of the values segment or scenario",
												array(
													'arcrole' => "'$arcroleUri'",
													'error' => 'sche:XmlSchemaError',
												)
											);
										}
									}
									else if ( XBRL::isValidating() )
									{
										$this->log()->dimension_validation( "2.3.2", "Every has-hypercube arc MUST have an xbrldt:contextElement attribute",
											array(
												'arcrole' => "'$arcroleUri'",
												'from' => '$from',
												'to' => '$to',
												'error' => 'xbrldte:HasHypercubeMissingContextElementAttributeError',
											)
										);
									}
								}

								// Record hypercubes if appropriate
								// $substitutionQName = $this->normalizePrefix( $fromTaxonomyElement['substitutionGroup'], $this );
								// $sourceIsHypercube = XBRL_Types::getInstance()->resolveToSubstitutionGroup( $substitutionQName, array( XBRL_Constants::$xbrldtHypercubeItem ) );
								// $substitutionQName = $this->normalizePrefix( $taxonomyElement['substitutionGroup'], $this );
								// $targetIsHypercube = XBRL_Types::getInstance()->resolveToSubstitutionGroup( $substitutionQName, array( XBRL_Constants::$xbrldtHypercubeItem ) );

								$sourceIsHypercube = XBRL_Types::getInstance()->resolveToSubstitutionGroup( $fromTaxonomyElement['substitutionGroup'], array( XBRL_Constants::$xbrldtHypercubeItem ) );
								$targetIsHypercube = XBRL_Types::getInstance()->resolveToSubstitutionGroup( $taxonomyElement['substitutionGroup'], array( XBRL_Constants::$xbrldtHypercubeItem ) );

								if ( $sourceIsHypercube || $targetIsHypercube )
								{
									if ( ! isset( $hypercubes[ $targetIsHypercube ? $to : $from ] ) )
									{
										$hypercubes[ $targetIsHypercube ? $to : $from ] = array(
											'dimensions' => array(),
											// BMS 2018-07-24
											// 'namespace' => $this->getNamespace(),
											'namespace' => $home_taxonomy->getNamespace(),
											'role' => $roleRefsKey,
											'href' => $targetIsHypercube ? $to : $from,
											'nodeclass' => 'hypercube',
										);
									}
								}

								// If 'has_hypercube' role then get the closed state and record this node in the $hypercubes array
								// switch ( $arcrole )
								switch ( $arcroleUri )
								{
									case XBRL_Constants::$arcRoleAll: // 'all':
									case XBRL_Constants::$arcRoleNotAll: // 'notAll':

										$sourceIsPrimaryItem = $this->isPrimaryItem( $fromTaxonomyElement );

										if ( XBRL::isValidating() )
										{
											// The $from concept MUST be a primary item declaration
											if ( ! $sourceIsPrimaryItem )
											{
												$this->log()->dimension_validation( "2.3.2.1", "The source of an all or notAll arc is not a primary item declaration [Def, 1]",
													array(
														'concept' => "'$from'",
														'substitutionGroup' => "'{$fromTaxonomyElement['substitutionGroup']}'",
														'error' => 'xbrldte:HasHypercubeTargetError',
													)
												);
											}

											// The $to concept MUST be a hypercube declaration
											if ( ! $targetIsHypercube )
											{
												$this->log()->dimension_validation( "2.3.2.1", "The target of an 'all' or 'notAll' arc is not a hypercube declaration [Def, 4]",
													array(
														'concept' => "'$to'",
														'substitutionGroup' => "'{$taxonomyElement['substitutionGroup']}'",
														'error' => 'xbrldte:HasHypercubeTargetError',
													)
												);
											}
										}

										if ( $sourceIsPrimaryItem )
										{
											if ( isset( $nodes[ $from ] ) )
											{
												$nodes[ $from ]['nodeclass'] = 'primary';
											}
											else
											{
												$nodes[ $from ] = array(
													'nodeclass' => 'primary',
													'children' => array(),
													'label' => $from,
													// 'taxonomy_element' => $fromTaxonomyElement,
												);
											}
											// $nodes[ $to ]['parents'][ $from ]['nodeclass'] = 'primary';
											if ( isset( $primaryItems[ $from ] ) )
											{
												// If the hypercube already exists then the task is to add
												// the hypercube to the list of existing hypercubes
												$primaryItems[ $from ]['hypercubes'][] = $to;
												$primaryItems[ $from ]['hypercubes'] = array_unique( $primaryItems[ $from ]['hypercubes'] );
												$primaryItems[ $from ]['localhypercubes'][] = $to;
												$primaryItems[ $from ]['localhypercubes'] = array_unique( $primaryItems[ $from ]['localhypercubes'] );
												// Need to add this to any child primary items
											}
											else
											{
												$primaryItems[ $from ] = array(
													// 'source' => $from,
													// 'arcrole' => $arcroleUri,  // all/notAll
													'hypercubes' => array( $to ),
													'localhypercubes' => array( $to ),
												);
											}

											$closed = 0;
											if ( property_exists( $xbrldt, 'closed' ) )
											{
												// The value MUST be boolean
												$closed = filter_var( (string) $xbrldt->closed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
												if ( is_null( $closed ) )
												{
													if ( XBRL::isValidating() )
													{
														$this->log()->dimension_validation( "2.3.3.1", "The xbrldt:closed attribute, if present, MUST have a Boolean value",
															array(
																'closed' => "'$closed'",
																'error' => 'sche:XmlSchemaError',
															)
														);
													}

													$closed = 0;
												}
											}

											// Defaults to false.  Only valid on the 'all'/'notAll' arcrole.
											$nodes[ $to ]['parents'][ $from ]['closed'] = $closed;

											// Add a parent to the hypercube
											$hypercubes[ $to ]['parents'][ $from ] = $nodes[ $to ]['parents'][ $from ];
										}

										break;

									case XBRL_Constants::$arcRoleDimensionDomain: // 'dimension-domain':

										$nodes[ $to ]['parents'][ $from ]['nodeclass'] = 'member';

										if ( XBRL::isValidating() )
										{
											if ( ! XBRL_Types::getInstance()->resolveToSubstitutionGroup( $fromTaxonomyElement['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) ) ||
												 isset( $fromTaxonomyElement['typedDomainRef'] )
											)
											{
												$this->log()->dimension_validation( "2.5.3.1.1 1", "The source of a dimension-domain relationship MUST be a dimension item but not a typed dimension.",
													array(
														'from' => "'$from'",
														'from substitution group' => "'{$fromTaxonomyElement['substitutionGroup']}'",
														'to' => "'$to'",
														'error' => 'xbrldte:DimensionDomainSourceError',
													)
												);
											}

											if ( ! $this->isPrimaryItem( $taxonomyElement ) )
											{
												$this->log()->dimension_validation( "2.5.3.1.1 1", "The target of a dimension-domain MUST NOT be a dimension or hypercube item",
													array(
														'from' => "'$from'",
														'from substitution group' => "'{$taxonomyElement['substitutionGroup']}'",
														'to' => "'$to'",
														'error' => 'xbrldte:DimensionDomainSourceError',
													)
												);
											}
										}

										break;

									case XBRL_Constants::$arcRoleDomainMember: // 'domain-member':
									case XBRL_Constants::$arcRoleDimensionMember: // 'dimension-member':

										if ( isset( $primaryItems[ $from ] ) )
										{
											if ( ! isset( $nodes[ $from ] ) )
											{
												$nodes[ $from ] = array(
													'children' => array(),
													'label' => $from,
													// 'taxonomy_element' => $fromTaxonomyElement,
												);
											}

											$nodes[ $from ]['nodeclass'] = 'primary';
										}

										if ( XBRL::isValidating() && $arcroleUri == XBRL_Constants::$arcRoleDomainMember )
										{
											// The $from concept MUST be a primary declaration
											if ( ! $this->isPrimaryItem( $fromTaxonomyElement ) )
											{
												$this->log()->dimension_validation( "2.5.3.2.1", "The source of a domain-member arc MUST be a primary item declaration [Def, 4]",
													array(
														'source' => "'$from'",
														'target' => "'$to'",
														'substitutionGroup' => "'{$fromTaxonomyElement['substitutionGroup']}'",
														'linkbase' => $xml_basename,
														'error' => 'xbrldte:DomainMemberSourceError',
													)
												);
											}

											// The $to concept MUST be a primary declaration
											if ( ! $this->isPrimaryItem( $taxonomyElement ) )
											{
												$this->log()->dimension_validation( "2.5.3.2.1", "The target of a domain-member arc MUST be a primary item declaration [Def, 7]",
													array(
														'source' => "'$from'",
														'target' => "'$to'",
														'linkbase' => $xml_basename,
														'error' => 'xbrldte:PrimaryItemPolymorphismError',
													)
												);
											}
										}

										if ( XBRL::isValidating() && $arcroleUri == XBRL_Constants::$arcRoleDimensionMember )
										{
											// The $from concept MUST be a primary declaration
											if ( ! $this->isPrimaryItem( $fromTaxonomyElement ) )
											{
												$this->log()->dimension_validation( "2.5.3.2.1", "The source of a dimension-domain arc MUST be a primary item declaration [Def, 4]",
													array(
														'source' => "'$from'",
														'target' => "'$to'",
														'substitutionGroup' => "'{$fromTaxonomyElement['substitutionGroup']}'",
														'linkbase' => $xml_basename,
														'error' => 'xbrldte:PrimaryItemPolymorphismError',
													)
												);
											}

											// The $to concept MUST be a primary declaration
											if ( ! $this->isPrimaryItem( $taxonomyElement ) )
											{
												$this->log()->dimension_validation( "2.5.3.1.1", "The target of a dimension-domain arc MUST be a primary item declaration [Def, 7]",
													array(
														'source' => "'$from'",
														'target' => "'$to'",
														'substitutionGroup' => "'{$taxonomyElement['substitutionGroup']}'",
														'linkbase' => $xml_basename,
														'error' => 'xbrldte:PrimaryItemPolymorphismError',
													)
												);
											}
										}

										$nodes[ $to ]['parents'][ $from ]['nodeclass'] = 'member';

										break;

									case XBRL_Constants::$arcRoleHypercubeDimension: // 'hypercube-dimension':

										if ( XBRL::isValidating() )
										{
											// The $from concept MUST be a hypercube declaration
											// $substitutionQName = $this->normalizePrefix( $fromTaxonomyElement['substitutionGroup'], $this );
											// if ( ! XBRL_Types::getInstance()->resolveToSubstitutionGroup( $substitutionQName, array( XBRL_Constants::$xbrldtHypercubeItem ) ) )
											if ( ! XBRL_Types::getInstance()->resolveToSubstitutionGroup( $fromTaxonomyElement['substitutionGroup'], array( XBRL_Constants::$xbrldtHypercubeItem ) ) )
											{
												$this->log()->dimension_validation( "2.2.2.1", "The source of a hypercube-dimension arc MUST be a hypercube declaration [Def, 4]",
													array(
														'source' => "'$from'",
														'target' => "'$to'",
														'substitutionGroup' => "'{$fromTaxonomyElement['substitutionGroup']}'",
														'linkbase' => $xml_basename,
														'error' => 'xbrldte:HypercubeDimensionSourceError',
													)
												);
											}

											// The $from concept MUST be a hypercube declaration
											// $substitutionQName = $this->normalizePrefix( $taxonomyElement['substitutionGroup'], $this );
											// if ( ! XBRL_Types::getInstance()->resolveToSubstitutionGroup( $substitutionQName, array( XBRL_Constants::$xbrldtDimensionItem ) ) )
											if ( ! XBRL_Types::getInstance()->resolveToSubstitutionGroup( $taxonomyElement['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) ) )
											{
												$this->log()->dimension_validation( "2.2.2.1", "The target of a hypercube-dimension arc MUST be a dimension declaration [Def, 7]",
													array(
														'source' => "'$from'",
														'target' => "'$to'",
														'substitutionGroup' => "'{$taxonomyElement['substitutionGroup']}'",
														'linkbase' => $xml_basename,
														'error' => 'xbrldte:HypercubeDimensionSourceError',
													)
												);
											}
										}

										$nodes[ $to ]['parents'][ $from ]['nodeclass'] = 'dimension';

										break;
								}

								$nodes[ $to ]['parents'][ $from ]['usable'] = $usable; // Defaults to true
								if ( ! isset( $nodes[ $from ] ) )
								{
									$nodes[ $from ] = array(
										'children' => array(),
										'label' => $from,
									);

									$namespace = null;
									$taxonomyElement = $this->findTaxonomyElement( $from, 'definitions', $namespace );
									if ( $taxonomyElement === null )
										; // $this->log()->warning( "The taxonomy element for label '$from' cannot be found" );
									else
									{
										// $nodes[ $from ]['taxonomy_element'] = $taxonomyElement;
									}
								}

								// $this->log()->info( "Adding relation {$from} -> {$to}" );
								if ( ! isset( $nodes[ $from ]['children'] ) )
								{
									$nodes[ $from ]['children'] = array();
								}

								$nodes[ $from ]['children'][ $to ] =& $nodes[ $to ];
							}
						}
					}

					if ( $preferredLabel && ! $preferredLabelFound )
					{
						$this->log()->taxonomy_validation( "Preferred label 2.1", "This preferred role is not defined on any label",
							array(
								'fromLabel' => $fromLabel,
								'toLabel' => $toLabel,
								'preferredLabelRole' => $preferredLabel,
								'error' => 'gple:missingPreferredLabel',
							)
						);

					}
				}
			}

			if ( ! count( $locators ) ) continue;

			$buildChildHierarchy = function( $nodes, $parent ) use( &$buildChildHierarchy )
			{
				$result = array();

				foreach ( $nodes as $nodeKey => $node )
				{
					if ( ! isset( $node['parents'][ $parent ] ) ) continue;

					$result[ $nodeKey ] = array(
						'label' => $node['label'],
						'order' => $node['parents'][ $parent ]['order'],
						'usable' => $node['parents'][ $parent ]['usable'],
					);

					if ( ! isset( $node['children'] ) ) continue;

					$result[ $nodeKey ]['children'] = $buildChildHierarchy( $node['children'], $nodeKey );
					if ( count( $result[ $nodeKey ]['children'] ) === 0 ) unset( $result[ $nodeKey ]['children'] );
				}

				return $result;
			};

			$targetRoleNodes =	XBRL::array_reduce_key( $nodes, function( $carry, $node, $key )
			{
				if ( ! isset( $node['parents'] ) ) return $carry;
				foreach( $node['parents'] as $parentKey => $parentNode )
				{
					if ( ! isset( $parentNode['targetRole'] ) ) continue;
					$carry[ $key ][ $parentKey ] = $parentNode;
				}
				return $carry;
			}, [] );

			// Process the nodes to popuplate hypercube references with their respective dimensions and defaults
			// It is possible to rely on targetRoles being available even if they are in a different base set
			// because the imports will have been processed first.
			foreach ( $nodes as $nodeKey => $node )
			{
				// No parent?  Must be a root so skip.
				if ( ! isset( $node['parents'] ) ) continue;

				foreach ( $node['parents'] as $parentKey => $parent )
				{
					if ( ! isset( $parent['arcrole'] ) ) continue;

					switch( $parent['arcrole'] )
					{
						case XBRL_Constants::$arcRoleAll:
						case XBRL_Constants::$arcRoleNotAll:

							if ( ! isset( $hypercubes[ $nodeKey ] ) ) break;
							$hypercubes[ $nodeKey ]['parents'][ $parentKey ] = $parent;

							foreach ( $primaryItems as $primaryItemId => $primaryItem )
							{
								// Does the hypercube already exist?
								if ( isset( $primaryItem['hypercubes'] ) && in_array( $nodeKey, $primaryItem['hypercubes'] ) )
								{
									continue;
								}

								// Does it need to be added?
								if ( ! isset( $primaryItem['parents'] ) ) continue;

								foreach ( $primaryItem['parents'] as $primaryItemParentId => $primaryItemParent )
								{
									if ( ! isset( $primaryItems[ $primaryItemParentId ]['hypercubes'] ) ) continue;
									// Takes the hypercubes from the parent
									$primaryItems[ $primaryItemId ]['hypercubes'] += $primaryItems[ $primaryItemParentId ]['hypercubes'];
								}
							}

							break;

						case XBRL_Constants::$arcRoleDomainMember:

							$updateReferences = function( $nodeKey, $parentKey, $parent ) use( &$updateReferences, &$hypercubes, &$nodes, &$primaryItems )
							{
								$nodes[ $nodeKey ]['nodeclass'] = $nodes[ $parentKey ]['nodeclass'];

								if ( isset( $primaryItems[ $nodeKey ] ) )
								{
									// If the hypercube already exists then the task is to add
									// the hypercube to the list of existing hypercubes
									$primaryItems[ $nodeKey ]['parents'][ $parentKey ] = $parent;
								}
								else
								{
									$primaryItems[ $nodeKey ] = array(
										// 'source' => $parentKey,
										// 'arcrole' => $parent['arcrole'],  // all/notAll
										'parents' => array( $parentKey => $parent ),
									);
								}

								if ( isset( $primaryItems[ $parentKey ]['hypercubes'] ) )
								{

									// Add this primary item as a parent of its hypercubes
									foreach ( $primaryItems[ $parentKey ]['hypercubes'] as $hypercubeId )
									{
										if ( ! isset( $hypercubes[ $hypercubeId ]['parents'] ) ) continue;

										$parentCopy = $parent;
										$parentCopy['arcrole']  = $hypercubes[ $hypercubeId ]['parents'][ $parentKey ]['arcrole'];
										if ( isset( $hypercubes[ $hypercubeId ]['parents'][ $parentKey ]['targetRole'] ) )
										{
											$parentCopy['targetRole']  = $hypercubes[ $hypercubeId ]['parents'][ $parentKey ]['targetRole'];
										}
										$parentCopy['contextElement']  = $hypercubes[ $hypercubeId ]['parents'][ $parentKey ]['contextElement'];
										$primaryItems[ $nodeKey ]['hypercubes'][] = $hypercubeId;
										$primaryItems[ $nodeKey ]['hypercubes'] = array_unique( $primaryItems[ $nodeKey ]['hypercubes'] );

										if ( isset( $hypercubes[ $hypercubeId ] ) )
										{
											$hypercubes[ $hypercubeId ]['parents'][ $nodeKey ] = $parentCopy;
										}

										unset( $parentCopy );
									}

								}

							};

							// The relationship of $parent with $node is a domain member
							// If the parent has nodeclass primary then this is primary as well
							// This one inherits any hypercubes from the parent
							if ( ! isset( $nodes[ $parentKey ]['nodeclass'] ) /* && isset( $nodes[ $parentKey ]['parents'] ) */ )
							{
								if ( ! isset( $nodes[ $parentKey ]['parents'] ) )
								{
									break;
								}

								// Look up the hierarhcy to see if a parent is a primary item
								$parentsArePrimary = function( $memberNodes ) use ( &$parentsArePrimary, &$updateReferences, &$nodes, &$primaryItems )
								{
									foreach ( $memberNodes as $key => $memberNode )
									{
										if ( isset ( $memberNode['arcrole'] ) )
										if ( $memberNode['arcrole'] == XBRL_Constants::$arcRoleHypercubeDimension ||
											 $memberNode['arcrole'] == XBRL_Constants::$arcRoleDimensionDomain )
										{
											return false;
										}

										$node = $nodes[ $key ];

										if ( isset( $node['nodeclass'] ) )
										{
											if ( $node['nodeclass'] == 'primary' )
											{
												return $key;
											}
										}

										if ( ! isset( $node['parents'] ) ) continue;

										if ( ( $parentNodeKey = $parentsArePrimary( $node['parents'] ) ) !== false )
										{
											$updateReferences( $key, $parentNodeKey, $nodes[$parentNodeKey] );
											return $key;
										}
									}

									return false;
								};

								if ( ( $parentNodeKey = $parentsArePrimary( $nodes[$parentKey]['parents'] ) ) === false ) continue 2;

								$updateReferences( $parentKey, $parentNodeKey, $nodes[ $parentNodeKey ] );
							}

							$updateReferences( $nodeKey, $parentKey, $parent );

							break;

						case XBRL_Constants::$arcRoleDimensionDomain:

							break;

						case XBRL_Constants::$arcRoleHypercubeDimension:

							if ( $parent['arcrole'] !== XBRL_Constants::$arcRoleHypercubeDimension ) break;
							// if ( $parent['arcrole_old'] !== 'hypercube-dimension' ) continue;

							if ( ! isset( $hypercubes[ $parentKey ] ) ) break;

							if ( ! isset( $hypercubes[ $parentKey ]['dimensions'] ) )
								$hypercubes[ $parentKey ]['dimensions'] = array();

							$taxonomy = $this->getTaxonomyForXSD( $node['label'] );

							$hypercubes[ $parentKey ]['dimensions'][ $node['label'] ] = array(
								'dimension_namespace' => $taxonomy ? $taxonomy->getNamespace() : null,
								'role' => $roleRefsKey,
								// 'taxonomy_element' => $node['taxonomy_element'],
								'order' => isset( $node['parents'][ $parentKey ]['order'] ) ? $node['parents'][ $parentKey ]['order'] : 0,
								'label' => $node['label'],
								'members' => array(),
							);

							if ( isset( $node['children'] ) )
							{
								$hypercubes[ $parentKey ]['dimensions'][ $node['label'] ]['members'] = $buildChildHierarchy( $node['children'], $node['label'] );
							}

							if ( isset( $this->context->dimensionDefaults[ $node['label'] ] ) )
							{
								$hypercubes[ $parentKey ]['dimensions'][ $node['label'] ]['default'] = $this->context->dimensionDefaults[ $node['label'] ];
							}

							break;
					}
				}
			}

			// Sort the nodes ordering child nodes by order then label
			foreach ( array_keys( $nodes ) as $i => $nodeKey )
			{
				if ( ! isset( $nodes[ $nodeKey ]['children'] ) ) continue;
				if ( ! isset( $nodes[ $nodeKey ]['label'] )  )
				{
					$this->log()->warning( "Oops" );
				}
				$label = $nodes[ $nodeKey ]['label'];

				$result = uasort( $nodes[ $nodeKey ]['children'], function($a, $b) use( $label ) {
					// Here the label is the name of the parent
					if ( ! isset( $a['parents'] ) || ! isset( $b['parents'] ) )
					{
						$this->log()->warning( "Sorting: can't find parent $label" );
					}

					$aOrder = $a['parents'][ $label ]['order'] ?? 0;
					$bOrder = $b['parents'][ $label ]['order'] ?? 0;
					if ( $aOrder == $bOrder )
					{
						return strcmp( $a['label'], $b['label'] ); // If the orders are the same order by label
					}
					return $aOrder < $bOrder ? -1 : 1;
				} );
			}

			// Get a list of the root nodes
			// $hierarchy = array_filter( $nodes, function( &$node ) use( $hypercubes ) {
			//	return ! isset( $node['parents'] );
			// });

			$hierarchy = array_filter( $nodes, function( $node ) use( $hypercubes ) {
				return ! isset( $node['parents'] ) && ! isset( $hypercubes[ $node['label'] ] );
			});

			$members = array();
			$dimensions = array();

			// The arcrole of the 'parent' of the root node will not be known
			// but it can be inferred from the arcroles of the children
			// This function will return the inferred role or false if the role cannot be
			// inferred because there are no children or it is ambiguous
			// NOTE: This is not used
			$inferNodeParentArcrole = function( $node ) {

				if ( ! isset( $node['children'] ) ) return false; // Should never happen

				$results = array();
				foreach ( $node['children'] as $childKey => $child )
				{
					foreach ( $child['parents'] as $parentKey => $parent )
					{
						// Look at each child for which it has the node as a parent, has an arc role which has not be seen yet
						if ( $parentKey != $node['label'] ||
							 ! isset( $parent['arcrole'] ) ||
							 isset( $results[ $parent['arcrole'] ] )
						   ) continue;
						$results[ $parent['arcrole'] ] = 1;
					}
				}

				if ( ! count( $results ) ) return false;
				if ( count( $results ) != 1 ) return false; // Potentially ambiguous

				$arcrole = key( $results );
				$arcroleParents = array(
					XBRL_Constants::$arcRoleAll => XBRL_Constants::$arcRoleDomainMember,
					XBRL_Constants::$arcRoleNotAll => XBRL_Constants::$arcRoleDomainMember,
					XBRL_Constants::$arcRoleHypercubeDimension => XBRL_Constants::$arcRoleAll,
					XBRL_Constants::$arcRoleDimensionDomain => XBRL_Constants::$arcRoleHypercubeDimension,
					XBRL_Constants::$arcRoleDomainMember => XBRL_Constants::$arcRoleDimensionDomain,
				);

				if ( ! isset( $arcroleParents[ $arcrole ] ) ) return false;
				return $arcroleParents[ $arcrole ];
			};

			foreach ( $nodes as $definitionNodeKey => $definitionNode )
			{
				// Primary items cannot be dimensions and members
				// if ( isset( $primaryItems[ $definitionNode['label'] ] ) ) continue;

				// No parents? Root so ignore
				if ( ! isset( $definitionNode['parents'] ) )
				{
					// Primary items cannot be members
					if ( isset( $primaryItems[ $definitionNode['label'] ] ) ) continue;
					$taxonomyElement = $this->findTaxonomyElement(  $definitionNode['label'], 'definition' );

					if ( $this->isPrimaryItem( $taxonomyElement ) )
					{
						// If it looks like a primary item but has no members assume its a member.
						// This will help targetRole validation.
						$members[ $definitionNode['label'] ] = array(
							'label' => $definitionNode['label'],
							// 'taxonomy_element' => $definitionNode['taxonomy_element'],
							'parents' => array(),
						);
					}
					else if ( $taxonomyElement['substitutionGroup'] == XBRL_Constants::$xbrldtDimensionItem )
					{
						// If this is a dimensionItem then add it to the dimensions collection
						$dimensions[ $definitionNode['label'] ] = array( 'label' => $definitionNode['label'] );
					}

					continue;
				}

				$label = $definitionNode['label'];

				foreach ( $definitionNode['parents'] as $parentKey => $parent )
				{
					// Not having an arcrole is invalid.
					if ( ! isset( $parent['arcrole'] ) ) continue;

					// Having a parent that is a primary item means it cannot be a member
					if ( isset( $primaryItems[ $parentKey ] ) )
					{
						// This may be a hypercube.  If so, drop the children
						if ( isset( $hypercubes[ $definitionNodeKey ] ) )
						{
							unset( $nodes[ $definitionNodeKey ]['children'] );
						}
						continue;
					}

					switch ( $parent['arcrole'] )
					{
						case XBRL_Constants::$arcRoleAll: // 'all':
						case XBRL_Constants::$arcRoleNotAll: // 'notAll':

							// $this->log()->info( "Hypercube ({$parent['arcrole']})" );

							break;

						case XBRL_Constants::$arcRoleHypercubeDimension: // 'hypercube-dimension':

							$dimensions[ $label ] = array( 'label' => $label );
							$dimensions[ $label ]['parents'][ $parentKey ] = $parent;

							break;

						case XBRL_Constants::$arcRoleDimensionDomain: // 'dimension-domain':
						case XBRL_Constants::$arcRoleDomainMember: // 'domain-member':

							// if the parent is primary item so is this one
							if ( ! isset( $members[ $label ] ) )
							{
								$members[ $label ] = array(
									'label' => $label,
									// 'taxonomy_element' => $definitionNode['taxonomy_element'],
									'parents' => array(),
								);
							}

							$members[ $label ]['parents'][ $parentKey ] = $parent;

							break;
					}
				}
			}

			if ( ! isset ( $home_taxonomy->definitionRoleRefs[ $roleRefsKey ] ) )
			{
				$home_taxonomy->definitionRoleRefs[ $roleRefsKey ] = array(
					'type' => $definitionRoleRef['type'],
					'href' => $definitionRoleRef['href'],
					'roleUri' => $definitionRoleRef['roleUri'],
				);
			}

			$roleRef =& $home_taxonomy->definitionRoleRefs[ $roleRefsKey ];

			// Merge top level nodes from the hierarchy
			foreach ( $hierarchy as $nodeKey => $node )
			{
				// Check to see if the node from the hierarchy needs merging with a pre-existing node in the $roleRef
				$fragment = parse_url( $nodeKey, PHP_URL_FRAGMENT );
				if ( isset( $roleRef['paths'][ $fragment ] ) )
				{
					// It does exist so locate the node and add $node in the correct location
					$this->processNodeByPath( $hierarchy, $roleRef['paths'][ $fragment ], $nodeKey,
						function( &$node, $path, $parentKey ) use( &$hierarchy, &$roleRef )
						{
							// Add the target node to $hierarchy
							$hierarchy = XBRL::mergeHierarchies( $roleRef['hierarchy'], $hierarchy );
						}
					);
				}
			}

			$role = array(
				'type' => $roleRef['type'],
				'href' => $roleRef['href'],
				'roleUri' => $roleRef['roleUri'],
				'members' => $members,
				'hypercubes' => $hypercubes,
				'primaryitems' => $primaryItems,
				'dimensions' => $dimensions
			);

			if ( $this->getNamespace() != $home_taxonomy->getNamespace() )
			{
				// Record this so that if the taxonomy is being compiled is can
				// be saved to be used when the taxonomy is subsequently restored.
				$this->foreignDefinitionRoleRefs[] = $role;
			}

			$roleRef = $this->mergeExtendedRoles( $roleRef, $role, $mergedRoles, false );

			unset( $roleRef );
		}
	}

	/**
	 * Returns true if the item is an item but not dimensional
	 * @param array $item
	 * @return boolean
	 */
	public function isPrimaryItem( $item )
	{
		if ( ! isset( $item['substitutionGroup'] ) ) return false;
		if ( $item['substitutionGroup'] == XBRL_Constants::$xbrldtHypercubeItem /* 'hypercubeItem' */ ) return false;
		if ( $item['substitutionGroup'] == XBRL_Constants::$xbrldtDimensionItem /* 'dimensionItem' */ ) return false;
		if ( XBRL_Types::getInstance()->resolveToSubstitutionGroup( $item['substitutionGroup'] , array( XBRL_Constants::$xbrldtHypercubeItem  ) ) ) return false;
		if ( XBRL_Types::getInstance()->resolveToSubstitutionGroup( $item['substitutionGroup'] , array( XBRL_Constants::$xbrldtDimensionItem  ) ) ) return false;
		return true;
	}

	/**
	 * Process the presentation linkbase type.
	 * @param array $linkbaseType The link base type to process
	 * @return void
	 */
	private function processPresentationLinkbases( $linkbaseType )
	{
		// $this->log()->info( "Process presentation linkbases" );
		foreach ( $linkbaseType as $linkbaseRefKey => $linkbaseRef )
		{
			$this->processPresentationLinkbase( $linkbaseRef );
		}
	}

	/**
	 * Process the reference linkbases.
	 * @param array $linkbaseType The link base type to process
	 * @return void
	 */
	private function processReferenceLinkbases( $linkbaseType )
	{
		// $this->log()->info( "Process presentation linkbases" );
		foreach ( $linkbaseType as $linkbaseRefKey => $linkbaseRef )
		{
			$this->processReferenceLinkbase( $linkbaseRef );
		}
	}

	/**
	 * Process a reference linkbase and populate the $this->referenceLinkRoleRefs variable with locators, arcs and labels
	 * At the moment this does just bare minimum processing to report validation errors
	 * @param array $linkbaseRef The link details
	 * @return boolean
	 */
	private function processReferenceLinkbase( $linkbaseRef )
	{
		// $this->log()->info( "Process reference linkbase {$linkbaseRef['href']}" );
		// The 'href' may contain an XPointer fragment that specifies a target in the document
		$parts = explode( '#', $linkbaseRef['href'] );
		$linkbaseRef['href'] = $parts[0];
		$xml_basename = pathinfo( $parts[0], PATHINFO_BASENAME );
		$fragment = isset( $parts[1] ) ? $parts[1] : "";
		$usedOn = 'link:reference';

		// Has it been processed?
		if ( isset( $this->context->processedLinkbases[ "$usedOn:$xml_basename" ] ) )
		{
			return;
		}
		$this->context->processedLinkbases[ "$usedOn:$xml_basename" ] = array( 'linkbase' => $xml_basename, 'usedOn' => $usedOn );

		// If this is an http/https scheme then there should be two forward slashes after the colon.
		// If this is not http/https then there should be just one slash
		$path = XBRL::resolve_path( $linkbaseRef['href'], $linkbaseRef['base'] . $xml_basename );

		$xml = XBRL::getXml( $path, $this->context );
		if ( $xml == null )
		{
			return $this->reportMissingLinkbaseFile( $linkbaseRef );
		}

		if ( $fragment )
		{
			// TODO Validate the XPointer fragment and set the $xml variable to the location defined by the fragment
			if ( $this->isPointer( $fragment, $xml, null, $name, $domNode ) )
			{
				if ( $domNode )
				{
					$xml = simplexml_import_dom( $domNode );
					unset( $domNode );
				}
			}
		}

		$this->processReferenceLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn );
	}

	/**
	 * Processes a reference linkbase given SimpleXMLElement node
	 * @param array $linkbaseRef
	 * @param SimpleXMLElement $xml
	 * @param string $xml_basename
	 * @param string $usedOn
	 */
	private function processReferenceLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn )
	{
		// Make sure this file contains reference link elements so is valid for the role type
		if ( ! count( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->referenceLink ) )
		{
			if ( ! empty( $linkbaseRef['role'] ) && $linkbaseRef['role'] != XBRL_Constants::$anyLinkbaseRef )
			{
				$this->reportNoRoleLinks( $linkbaseRef, 'reference' );
			}
			return;
		}

		$taxonomy_base_name = $this->getTaxonomyXSD();

		$arcroleRefs = array();
		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->arcroleRef as $arcroleRefKey => $arcroleRef )
		{
			$attributes = $arcroleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$arcroleRefHref = (string) $attributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$arcroleRefParts = explode( "#", $arcroleRefHref );
			if ( ! $arcroleRefParts )
			{
				$arcroleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $arcroleRefParts[0] )
			{
				$arcroleRefParts[0] = $this->getTaxonomyXSD();
				$arcroleRefHref = implode( "#", $arcroleRefParts );
			}

			$arcroleUri = (string) $arcroleRef->attributes()->arcroleURI;
			$fragment = parse_url( $arcroleRefHref, PHP_URL_FRAGMENT );

			if ( XBRL::isValidating() )
			{
				if ( isset( $arcroleRefs[ $arcroleUri ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one arcroleRef element with the same @arcroleURI attribute value",
						array(
							'role' => $arcroleUri,
							'href' => $xml_basename,
						)
					);
				}
			}

			$arcroleRefs[ $arcroleUri ] = $fragment;

			$taxonomy = $this->getTaxonomyForXSD( $arcroleRefHref );
			if ( ! $taxonomy )
			{
				$xsd = strpos( $arcroleRefHref, '#' ) === false ? $arcroleRefHref : strstr( $arcroleRefHref, '#', true );
				if ( isset( XBRL_Global::$taxonomiesToIgnore[ $xsd ] ) ) continue;

				$xsd = $this->resolve_path( $linkbaseRef['href'], $xsd );
				// If the taxonomy is not already loaded, try loading it.
				$taxonomy = $xsd ? XBRL::withTaxonomy( $xsd ) : null;
				if ( ! $taxonomy )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "Taxonomy for arcroleRef href does not exist",
						array(
							'href' => "'$arcroleRefHref'",
							'linkbase' => "'$xml_basename'",
						)
					);
					continue;
				}
				$this->indirectNamespaces[] = $taxonomy->getNamespace();
				$taxonomy->AddUserNamespace( $this );
			}

			// This role MUST be defined as 'usedOn' in the linkbaseRef for link:referenceArc
			if ( ! count( $taxonomy->arcroleTypes ) || ! isset( $taxonomy->arcroleTypes['link:referenceArc'][ $arcroleUri ] ) )
			{
				$this->log()->taxonomy_validation( "5.1.3.4", "This arcrole is not defined to be used on the reference linkbase",
					array(
						'arcrole' => "'$arcroleUri'",
						'linkbase' => "'$xml_basename'",
					)
				);
				continue;
			}

			// Make sure this arcrole is in the current taxonomy
			$this->arcroleTypes['link:referenceArc'][ $arcroleUri ] = $taxonomy->arcroleTypes['link:referenceArc'][ $arcroleUri ];
		}

		if ( ! isset( $this->roleTypes[ $usedOn ] ) )
		{
			$this->roleTypes[ $usedOn ] = array();
		}

		$linkbaseRoleRefs = array();

		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->roleRef as $roleRefKey => $roleRef )
		{
			$xlinkAttributes = $roleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$roleRefAttributes = $roleRef->attributes();
			$roleUri = (string) $roleRefAttributes->roleURI;
			$roleRefHref = (string) $xlinkAttributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$roleRefParts = explode( "#", $roleRefHref );
			if ( ! $roleRefParts )
			{
				$roleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $roleRefParts[0] )
			{
				$roleRefParts[0] = $this->getTaxonomyXSD();
				$roleRefHref = implode( "#", $roleRefParts );
			}

			if ( XBRL::isValidating() )
			{
				// BMS 2018-04-26 This test works when the linkbases are in different files not so much when they are all in the schema document
				// if ( isset( $this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] ) )
				if ( isset( $linkbaseRoleRefs[ "$usedOn:$roleUri" ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one roleRef element with the same @roleURI attribute value",
						array(
							'role' => $roleUri,
							'usedon' => $usedOn,
							'href' => $xml_basename,
						)
					);
				}
			}

			$linkbaseRoleRefs[ "$usedOn:$roleUri" ] = $roleRefHref;
			$this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] = $roleRefHref;

			$taxonomy = $this->getTaxonomyForXSD( $roleRefHref );
			if ( ! $taxonomy )
			{
				// Look for the taxonomy and include its contents in the DTS
				$xsd = $this->resolve_path( $this->getSchemaLocation(), $roleRefHref );

				$taxonomy = XBRL::withTaxonomy( strpos( $xsd, '#' ) ? strstr( $xsd, '#', true ) : $xsd );
				$taxonomy = $this->getTaxonomyForXSD( $roleRefHref );
				if ( ! $taxonomy )
				{
					if ( XBRL::isValidating() )
					{
						$this->log()->taxonomy_validation( "5.1.3.4", "The role taxonomy cannot be located",
							array(
								'role' => $roleUri,
								'href' => $roleRefHref,
							)
						);
					}
					continue;
				}
				$this->indirectNamespaces[] = $taxonomy->getNamespace();
				$taxonomy->AddUserNamespace( $this );
			}

			// This role MUST be defined as 'usedOn' in the linkbaseRef for link:referenceArc IN THE TAXONOMY POINTED TO BY THE HREF
			if ( ! isset( $taxonomy->roleTypes[ $usedOn ][ $roleUri ] ) )
			{
				if (  XBRL::isValidating() )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "This role is not defined to be used on the reference linkbase",
						array(
							'role' => $roleUri,
						)
					);
				}

				$taxonomy->roleTypes[ $usedOn ][ $roleUri ] = array(
					'definition' => "Reference link base",
					'roleURI' => $roleUri,
					'taxonomy' => $taxonomy->schemaLocation,
					'id' => "",
				);
			}

			// Create a placeholder for this role if the role references a different taxonomy.
			// Any definitions for all locally defined roles will be created later.
			// If the role references the default link it will be created later or already have been created.
			if ( $roleUri != XBRL_Constants::$defaultLinkRole && $taxonomy != $this )
			{
				$this->referenceRoleRefs[ $roleUri ] = array(
					'href' => $taxonomy->schemaLocation,
					'roleUri' => $roleUri,
					'type' => XBRL_Constants::$arcRoleTypeSimple,
				);
			}

		}

		// Create a validated list of definition role refs
		foreach( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->referenceLink as $key => $definitionLink )
		{
		}
	}

	/**
	 * Assign dimensions to presentation nodes for a specific role
	 * @param string  $roleKey 	The role of the presentation node being
	 * @return void
	 */
	private function fixupPresentationRoleHypercubes( $roleKey )
	{
		// $this->log()->info( "$role" );
		if ( ! isset( $this->context->presentationRoleRefs[ $roleKey ] ) ) return;
		if ( ! isset( $this->context->presentationRoleRefs[ $roleKey ]['href'] ) ) return;

		// BMS 2016-02-15
		/**
		 * @var XBRL $home_taxonomy
		 */
		$home_taxonomy = $this->getTaxonomyForXSD( $this->context->presentationRoleRefs[ $roleKey ]['href'] );
		// $home_taxonomy = $this;

		if ( ! isset( $this->context->presentationRoleRefs[ $roleKey ]['hierarchy'] ) ) return;

		$home_taxonomy->ResetAllItemCaches();

		$roleHypercubes = $home_taxonomy->assignNodeHypercubes( $this->context->presentationRoleRefs[ $roleKey ]['hierarchy'], $this->context->presentationRoleRefs[ $roleKey ]['locators'], $roleKey );
		$this->context->presentationRoleRefs[ $roleKey ]['hypercubes'] = $roleHypercubes;

		$this->pruneNodeHypercubes( $this->context->presentationRoleRefs[ $roleKey ]['hierarchy'] );

		$before = count( $this->context->presentationRoleRefs[ $roleKey ]['paths'] );
		// Although a 'paths' index has been built it needs to be re-built
		// because the 'pruneNodeHypercubes' function is likely to have removed
		// some nodes meaning the existing index will contain invalid entries
		$this->context->presentationRoleRefs[ $roleKey ]['paths'] = $home_taxonomy->createHierarchyPaths( $this->context->presentationRoleRefs[ $roleKey ]['hierarchy'] );
		$after = count( $this->context->presentationRoleRefs[ $roleKey ]['paths'] );
		if ( $before != $after )
		{
			// $x = 1;
		}
	}

	/**
	 * Assign hypercubes to presentation nodes in all roles of the taxonomy
	 * @return void
	 */
	public function fixupPresentationHypercubes()
	{
		foreach ( $this->context->presentationRoleRefs as $refsKey => $refs )
		{
			$this->fixupPresentationRoleHypercubes( $refsKey );
		}
	}

	/**
	 * Create an index of paths for each definition role
	 * @return void
	 */
	public function fixupDefinitionRoles()
	{
		$roles = $this->getAllDefinitionRoles( true );

		return;

		// BMS 2017-04-05 This is no longer necessary because hierarchies are not used in definitions
		foreach ( $this->definitionRoleRefs as $roleKey => $refs )
		{
			// BMS 2015-03-04
			$home_taxonomy = $this->getTaxonomyForXSD( $refs['href'] );
			// $home_taxonomy = $this;
			if ( ! isset( $home_taxonomy->definitionRoleRefs[ $roleKey ] ) ||
				 ! isset( $home_taxonomy->definitionRoleRefs[ $roleKey ]['hierarchy'] )) continue;

			$home_taxonomy->definitionRoleRefs[ $roleKey ]['paths'] = $home_taxonomy->createHierarchyPaths( $home_taxonomy->definitionRoleRefs[ $roleKey ]['hierarchy'] );
		}
	}

	/**
	 * Display a default label for this class which is the taxonomy file name
	 * @return string
	 */
	public function __toString()
	{
		return $this->schemaLocation
		? "$this->schemaLocation"
		: "<unknown>";
	}

	/**
	 * Process the nodes of a role hierarchy to build a primary items path index
	 * @param array $nodes An array of nodes, ones that have a 'children' element
	 * @param array $paths An array of paths accumulated to date.  Defaults to an empty array.
	 * @param string $path The node path of the parent.  Defaults to an empty string
	 * @return array An array indexed by node labels of paths to each node
	 */
	public function createHierarchyPaths( $nodes, $paths = array(), $path = "" )
	{
		foreach ( $nodes as $nodeKey => $node )
		{
			$nodePath = $path . ( empty( $path ) ? "" : "/" ) . $node['label'];

			$parts = parse_url( $node['label'] );
			$taxonomy = $parts['path'] == $this->getTaxonomyXSD()
				? $this
				: $this->getTaxonomyForXSD( $parts['path'] );
			$taxonomy_element = $taxonomy->getElementById( $parts['fragment'] );
			// $key = strpos( $node['label'], "#" ) === false ? $node['label'] : $parts[ 'fragment' ];
			$key = $taxonomy_element['id'];

			if ( isset( $paths[ $key ] ) )
			{
				// Check to make sure there is no recursion.  This is indicative
				// of a circular reference but these will be formally detected
				// in the validation function.
				if ( strpos( $path, $node['label'] ) !== false )
				{
					continue;
				}
			}
			else
			{
				$paths[ $key ] = array();
			}

			$paths[ $key ][] = $nodePath;

			if ( ! isset( $node['children'] ) || count( $node['children'] ) === 0 ) continue;
			$paths = $this->createHierarchyPaths( $node['children'], $paths, $nodePath );
		}

		if ( empty( $path ) ) ksort( $paths );
		return $paths;
	}

	/**
	 * After processing an entry in $xbrl->context->presentationRoleRefs for each role uri
	 * Each entry is an array with these elements:
	 * 	 type		The role type
	 *   href		A reference such as uk-gaap-2009-09-01.xsd#IncomeStatement
	 *	 roleUri	A role type uri that corresponds to the presentation link role eg http://www.xbrl.org/uk/role/ProftAndLossAccount
	 *	 locators   An array of locator hrefs indexed by label
	 *	 nodes		An array of StdClass instances representing the 'from' side of a presentation arc.
	 *	 hierarchy	The root node of the hierarchy for the presentationLink
	 *
	 * Nodes have the following members
	 * 	 children   An array of StdClass instance that represent the to' side of an arc
	 *   label      The name of the node.  Will have been the 'from' or 'to' attribute of the arc.
	 *   parent     The parent of the node (the ultimate root will not have this property)
	 *	 arcrole    Should be the parent-child role
	 * 	 order      The order of the node within its parent
	 *   priority   The priority (default 'optional')
	 *   use		Default 'optional'
	 *
	 * @param array $linkbaseRef Is a linkbaseRef array
	 * @return void
	 */
	public function processPresentationLinkbase( $linkbaseRef )
	{
		// $this->log()->info( "Process presentation linkbase {$linkbaseRef['href']}" );
		// The 'href' may contain an XPointer fragment that specifies a target in the document
		$parts = explode( '#', $linkbaseRef['href'] );
		$linkbaseRef['href'] = $parts[0];
		$xml_basename = pathinfo( $parts[0], PATHINFO_BASENAME );
		$fragment = isset( $parts[1] ) ? $parts[1] : "";
		$usedOn = 'link:presentationLink';

		// Has it been processed?
		if ( isset( $this->context->processedLinkbases[ "$usedOn:$xml_basename" ] ) )
		{
			return;
		}
		$this->context->processedLinkbases[ "$usedOn:$xml_basename" ] = array( 'linkbase' => $xml_basename, 'usedOn' => $usedOn );

		// If this is an http/https scheme then there should be two forward slashes after the colon.
		// If this is not http/https then there should be just one slash
		$path = XBRL::resolve_path( $linkbaseRef['href'], $linkbaseRef['base'] . $xml_basename );
		// $path = str_replace( "//", "/", pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ) . "/" . $linkbaseRef['base'] . "/" . $xml_basename );
		// $path = preg_replace( '~^(https?):/([^/])~', '$1://$2', $path );

		$xml = XBRL::getXml( $path, $this->context );
		if ( $xml == null )
		{
			return $this->reportMissingLinkbaseFile( $linkbaseRef );
		}

		if ( $fragment )
		{
			// TODO Validate the XPointer fragment and set the $xml variable to the location defined by the fragment
			if ( $this->isPointer( $fragment, $xml, null, $name, $domNode ) )
			{
				if ( $domNode )
				{
					$xml = simplexml_import_dom( $domNode );
					unset( $domNode );
				}
			}
		}

		$this->processPresentationLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn );
	}

	/**
	 * Processes a presentation linkbase given SimpleXMLElement node
	 * @param array $linkbaseRef
	 * @param SimpleXMLElement $xml
	 * @param string $xml_basename
	 * @param string $usedOn
	 */
	private function processPresentationLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn )
	{
		// Make sure this file contains presentation link elements so is valid for the role type
		if ( ! count( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->presentationLink ) )
		{
			if ( ! empty( $linkbaseRef['role'] ) && $linkbaseRef['role'] != XBRL_Constants::$anyLinkbaseRef )
			{
				$this->reportNoRoleLinks( $linkbaseRef, 'presentation' );
			}
			return;
		}

		$arcroleRefs = array();
		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->arcroleRef as $arcroleRefKey => $arcroleRef )
		{
			$attributes = $arcroleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$arcroleRefHref = (string) $attributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$arcroleRefParts = explode( "#", $arcroleRefHref );
			if ( ! $arcroleRefParts )
			{
				$arcroleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $arcroleRefParts[0] )
			{
				$arcroleRefParts[0] = $this->getTaxonomyXSD();
				$arcroleRefHref = implode( "#", $arcroleRefParts );
			}

			$arcroleUri = (string) $arcroleRef->attributes()->arcroleURI;
			$fragment = parse_url( $arcroleRefHref, PHP_URL_FRAGMENT );

			if ( XBRL::isValidating() )
			{
				if ( isset( $arcroleRefs[ $arcroleUri ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one arcroleRef element with the same @arcroleURI attribute value",
						array(
							'role' => $arcroleUri,
							'href' => $xml_basename,
						)
					);
				}
			}

			$arcroleRefs[ $arcroleUri ] = $fragment;

			$taxonomy = $this->getTaxonomyForXSD( $arcroleRefHref );
			if ( ! $taxonomy )
			{
				$xsd = strpos( $arcroleRefHref, '#' ) === false ? $arcroleRefHref : strstr( $arcroleRefHref, '#', true );
				if ( isset( XBRL_Global::$taxonomiesToIgnore[ $xsd ] ) ) continue;

				$xsd = $this->resolve_path( $linkbaseRef['href'], $xsd );
				// If the taxonomy is not already loaded, try loading it.
				$taxonomy = $xsd ? XBRL::withTaxonomy( $xsd ) : null;
				if ( ! $taxonomy )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "Taxonomy for arcroleRef href does not exist",
						array(
							'href' => "'$arcroleRefHref'",
							'linkbase' => "'$xml_basename'",
						)
					);
					continue;
				}
				$this->indirectNamespaces[] = $taxonomy->getNamespace();
				$taxonomy->AddUserNamespace( $this );
			}

			// This role MUST be defined as 'usedOn' in the linkbaseRef for link:definitionArc
			if ( ! count( $taxonomy->arcroleTypes ) || ! isset( $taxonomy->arcroleTypes['link:presentationArc'][ $arcroleUri ] ) )
			{
				$this->log()->taxonomy_validation( "5.1.3.4", "This arcrole is not defined to be used on the presentation linkbase",
					array(
						'arcrole' => "'$arcroleUri'",
						'linkbase' => "'$xml_basename'",
					)
				);
				continue;
			}

			// Make sure this arcrole is in the current taxonomy
			$this->arcroleTypes['link:presentationArc'][ $arcroleUri ] = $taxonomy->arcroleTypes['link:presentationArc'][ $arcroleUri ];
		}

		if ( ! isset( $this->roleTypes[ $usedOn ] ) )
		{
			$this->roleTypes[ $usedOn ][ XBRL_Constants::$defaultLinkRole ] = array(
				'definition' => "Presentation link base",
				'roleURI' => XBRL_Constants::$defaultLinkRole,
				'taxonomy' => basename( $this->schemaLocation ),
				'id' => "",
			);
		}

		$linkbaseRoleRefs = array();

		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->roleRef as $roleRefKey => $roleRef )
		{
			$xlinkAttributes = $roleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$roleRefAttributes = $roleRef->attributes();
			$roleUri = (string) $roleRefAttributes->roleURI;
			$roleRefHref = (string) $xlinkAttributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$roleRefParts = explode( "#", $roleRefHref );
			if ( ! $roleRefParts )
			{
				$roleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $roleRefParts[0] )
			{
				$roleRefParts[0] = $this->getTaxonomyXSD();
				$roleRefHref = implode( "#", $roleRefParts );
			}

			if ( XBRL::isValidating() )
			{
				// BMS 2018-04-26 This test works when the linkbases are in different files not so much when they are all in the schema document
				// if ( isset( $this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] ) )
				if ( isset( $linkbaseRoleRefs[ "$usedOn:$roleUri" ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one roleRef element with the same @roleURI attribute value",
						array(
							'role' => $roleUri,
							'usedon' => $usedOn,
							'href' => $xml_basename,
						)
					);
				}
			}

			$linkbaseRoleRefs[ "$usedOn:$roleUri" ] = $roleRefHref;
			$this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] = $roleRefHref;

			$roleRefHref =  XBRL::resolve_path( $linkbaseRef['href'], $roleRefHref );

			$taxonomy = $this->getTaxonomyForXSD( $roleRefHref );
			if ( ! $taxonomy )
			{
				$taxonomy = $this->withTaxonomy( strstr( $roleRefHref, '#', true ), true );
				if ( ! $taxonomy )
				if ( XBRL::isValidating() )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "The role taxonomy cannot be located",
						array(
							'role' => $roleUri,
							'href' => $roleRefHref,
						)
					);
				}

				if ( ! isset( $this->context->presentationRoleRefs[ $roleUri ] ) )
				{
					$this->context->presentationRoleRefs[ $roleUri ] = array(
						'type' => (string) $xlinkAttributes->type,
						'href' => $roleRefHref,
						'roleUri' => $roleUri,
					);
				}

				continue;
			}

			// This role MUST be defined as 'usedOn' in the roleType for link:presentationLink
			// unless the href is fully qualified
			if ( count( $taxonomy->roleTypes ) )
			{
				if ( ! isset( $taxonomy->roleTypes[ $usedOn ][ $roleUri ] ) )
				{
					// Make sure this one is really used
					$el = dom_import_simplexml( $xml );
					$xpath = new DOMXPath( $el->ownerDocument );
					$xpath->registerNamespace( 'link', XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] );
					$nodes = $xpath->query( "/link:linkbase/link:presentationLink[@xlink:role='$roleUri']", $el );
					if ( ! $nodes->length ) continue;

					// $scheme = parse_url( $roleRefHref, PHP_URL_SCHEME );
					// if ( ! in_array( $scheme, array( 'http', 'https' ) ) )
					{
						$this->log()->taxonomy_validation( "5.1.3.4", "This role is not defined to be used on the presentation linkbase",
							array(
								'role' => $roleUri,
							)
						);
					}
				}

			}

			if ( ! isset( $this->context->presentationRoleRefs[ $roleUri ] ) )
			{
				$this->context->presentationRoleRefs[ $roleUri ] = array(
					'type' => (string) $xlinkAttributes->type,
					// 'href' => XBRL::resolve_path( $linkbaseRef['href'], $roleRefHref ),
					'href' => $roleRefHref,
					'roleUri' => $roleUri,
				);
			}
		}

		$taxonomy_base_name = $this->getTaxonomyXSD();

		if ( count( $this->context->presentationRoleRefs ) === 0 )
		{
			$this->context->presentationRoleRefs[ XBRL_Constants::$defaultLinkRole ] = array(
				'type' => 'simple',
				'href' => XBRL::resolve_path( $linkbaseRef['href'], $this->getTaxonomyXSD() ), // $linkbaseRef['href'],
				'roleUri' => XBRL_Constants::$defaultLinkRole,
			);
		}

		// Find the presentation link with the same role
		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->presentationLink as $linkKey => $presentationLink )
		{
			// Detection of duplicate from/to pairs only applies within an extended link so reset this varaible in each new link
			$this->resetValidateFromToArcPairs();

			$presentationLinkattributes = $presentationLink->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );

			if ( ! property_exists( $presentationLinkattributes, 'role' ) )
			{
				continue;
			}

			$roleRefsKey = (string) $presentationLinkattributes->role;
			if ( ! isset( $this->context->presentationRoleRefs[ $roleRefsKey ] ) )
			{
				if ( $roleRefsKey == XBRL_Constants::$defaultLinkRole )
				{
					$this->context->presentationRoleRefs[ XBRL_Constants::$defaultLinkRole ] = array(
						'type' => 'simple',
						'href' => XBRL::resolve_path( $linkbaseRef['href'], $this->getTaxonomyXSD() ), // $linkbaseRef['href'],
						'roleUri' => XBRL_Constants::$defaultLinkRole,
					);
				}
				else
				{
					$this->log()->warning( "There is no presentation role ref that corresponds to $roleRefsKey" );
					continue;
				}
			}

			unset( $presentationRoleRef );
			$presentationRoleRef =& $this->context->presentationRoleRefs[ $roleRefsKey ];
			$presentationRoleRef['used'] = true;

			// BMS 2015-02-15
			$home_taxonomy = strpos( $presentationRoleRef['href'], $taxonomy_base_name ) === false
				? $this->getTaxonomyForXSD( $presentationRoleRef['href'] )
				: $this;

			unset( $presentationRoleRef );

			// BMS 2016-02-15
			// $additions = $this->getProxyPresentationNodes( $roleRefsKey );
			$additions = $home_taxonomy->getProxyPresentationNodes( $roleRefsKey );

			// Analyse the elements to form a tree
			// Begin by reading the locators
			$locators = array();
			// BMS 2018-04-16 Change to use the standard 'retrieveLocators' function like other linkbase processors
			$locators = $this->retrieveLocators( $presentationLink, XBRL_Constants::$PresentationLinkbaseRef, $linkbaseRef['href'] );

			// BMS 2018-04-16 For now, presentation locators are single dimension at the moment so re-org the $locators array
			$locators = array_map( function( $refsArray ) { return $refsArray[0]; }, $locators );
			if ( $additions !== false && isset( $additions['locators'] ) && is_array( $additions['locators'] ) && count( $additions['locators'] ) > 0 )
			{
				$locators = array_merge( $locators, $additions['locators'] );
			}

			$root = null;
			$nodes = array();

			// Used to catch duplicated from/to label pairs which is not alloed by the XLink specification
			$fromToPairs = array();
			// Record nodes that are both the source and target of a preferred label pair
			$nodesToPrune = array();

			// Get this extended role content
			// BMS 2019-05-21 Moved from further down (see comment with the same date)
			//					So nodes from other ELRs with the same role uri can be referenced
			$roleRef =& $this->context->presentationRoleRefs[ $roleRefsKey ];

			// Process the presentation arcs and build a hierarchy
			// $this->log()->info( "Process the presentation arcs and build a hierarchy ($roleRefsKey)" );
			foreach ( $presentationLink->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->presentationArc as $arcKey => $presentationArc )
			{
				$xlinkAttributes = $presentationArc->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
				$arcAttributes = $presentationArc->attributes();
				if ( ! $this->validateXLinkArcAttributes( $xlinkAttributes, 'presentation' ) )
				{
					continue;
				}

				// $lineNo = dom_import_simplexml( $presentationArc )->getLineNo();

				if ( isset( $fromToPairs[ (string)$xlinkAttributes->from ][ (string)$xlinkAttributes->to ] ) )
				{
					$this->log()->taxonomy_validation( "XLink", "Arcs contain repeated from/to label pairs in the same extended link",
						array(
							'role' => $roleRefsKey,
							'from' => (string)$xlinkAttributes->from,
							'to' => (string)$xlinkAttributes->to
						)
					);
				}
				else
				{
					$fromToPairs[ (string)$xlinkAttributes->from ][ (string)$xlinkAttributes->to ] = 1;
				}

				$arcroleUri = (string) $xlinkAttributes->arcrole;
				if ( $arcroleUri != XBRL_Constants::$arcRoleParentChild )
				{
					// if ( isset( $arcroleRefs[ $arcroleUri ] ) )
					// {
					// 	continue;
					// }

					if ( ! isset( $arcroleRefs[ $arcroleUri ] ) )
					{
						$this->log()->taxonomy_validation( "5.2.4.2", "The non-standard arcrole on the presentation arc has not been defined",
							array(
								'arcrole' => $arcroleUri,
							)
						);

						continue;
					}
				}

				$fromLabel	= (string) $xlinkAttributes->from;
				$this->validateXLinkLabel( 'presentation', $fromLabel );
				$toLabel	= (string) $xlinkAttributes->to;
				$this->validateXLinkLabel( 'presentation', $toLabel );

				$from	= $locators[ $fromLabel ];
				$to		= $locators[ $toLabel ];

				$preferredLabel = property_exists( $arcAttributes, 'preferredLabel' )
					? (string) $arcAttributes->preferredLabel
					: "";

				// This MUST be called *before* the 'to' component could be renamed in the next step
				$taxonomyElement = $this->findTaxonomyElement( $to, 'presentation' );

				// This is a kludge to handle the scenario that a concept appears twice
				// and one is an opening balance and one is a closing balance. A 'proper'
				// solution will be to support nodes having multiple children with more
				// than one child with the same label.
				// This kludge looks for the condition that the preferred label is start
				// or end.  If it is, then the task is to modify the 'to' label and also
				// update the corresponding locator so it finds the new label.
				// if ( in_array( $preferredLabel, array( XBRL_Constants::$labelRolePeriodStartLabel, XBRL_Constants::$labelRolePeriodEndLabel ) ) )
				$preferredLabelPairs = $preferredLabel && $this->getBeginEndPreferredLabelPair( $preferredLabel );
				if ( $preferredLabelPairs )
				{
					// Resolve the toLabel...
					$href = $locators[ $toLabel ];

					// ...and get the components
					$parts = parse_url( $href );
					$xsd = $parts["path"];
					$fragment = $parts["fragment"];

					/**
					 * @var XBRL $taxonomy
					 */
					$taxonomy = $xsd === $taxonomy_base_name ? $this : $this->getTaxonomyForXSD( $xsd );
					if ( $taxonomy === null || $taxonomy === false )
					{
						$this->log()->err( "Taxonomy cannot be found for $href" );
						exit;
					}

					// Create the suffix
					$preferredRoleName = str_replace( XBRL_Constants::$rolePrefix, '', $preferredLabel );

					// Add a new element so the create hierarchy paths will work OK
					$elements =& $taxonomy->getElements();
					$elements[ $fragment . $preferredRoleName ] = $elements[ $fragment ];

					if ( $this->context->isExtensionTaxonomy() )
					{
						$this->extraElements[ "$href$preferredRoleName" ] = $href;
					}
					// Add a new locator
					$locators[ $toLabel . $preferredRoleName ] = $locators[ $toLabel ] . $preferredRoleName;

					// And test it
					$to		= $locators[ $toLabel . $preferredRoleName ];

					// Need to fix up any nodes with parents that are $href
					// Get a list of node key with this condition
					// This happens in the DK IFRS (see ./2017-01-01/dst/001dst_pre.xml and look for
					// fsa.xsd#fsa_ManufacturedGoodsAndGoodsForResale).  This concept is the head of a block
					// in the ELR http://xbrl.dcca.dk/role/001.00/InformationForStatisticsDenmark
					// The is referenced by fsa.xsd#fsa_StocksAbstract using both period start and end preferred labels.
					// The whole block has be presented as a child of both perferred label nodes.
					// This code looks for such instances and copies the block.
					$keys = array_keys( array_filter( $nodes, function( $node ) use( $href ) { return isset( $node['parents'][ $href ] ); } ) );
					foreach ( $keys as $key )
					{
						// -- $parent =& $nodes[ $key ]['parents'][ $href ];
						$parent = $nodes[ $key ]['parents'][ $href ]; // ++
						$parent['preferredLabel'] = $preferredLabel; // ++
						// -- unset( $nodes[ $key ]['parents'][ $href ] );
						$nodes[ $key ]['parents'][ $href . $preferredRoleName ] = $parent;
						unset( $parent );
					}

					if ( isset( $nodes[ $href ] ) )
					{
						$nodesToPrune[ $href ][] = $to;

						// -- $node =& $nodes[ $href ];
						// -- unset( $nodes[ $href ] );
						$node = array();
						$node['label'] = $to;
						$node['parents'] =& $nodes[ $href ]['parents']; // ++
						$node['children'] =& $nodes[ $href ]['children']; // ++
						$nodes[ $to ] = $node;
						unset ( $node );
					}
					else if ( $nodesToPrune && isset( $nodesToPrune[ $href ] ) )
					{
						foreach ( $nodesToPrune[ $href ] as $nodeToPrune )
						{
							if ( ! isset( $nodes[ $nodeToPrune ] ) ) continue;

							// Clone this one using the $to as an index and add new parents to any children
							$nodes[ $to ] = $nodes[ $nodeToPrune ];
							$nodes[ $to ]['label'] = $to;
							foreach( $nodes[ $to ]['children'] as $subLabel => $node )
							{
								if ( ! isset( $node['parents'][ $nodeToPrune ] ) ) continue;
								$parent = $node['parents'][ $nodeToPrune ];
								$parent['preferredLabel'] = $preferredLabel;
								$nodes[ $to ]['children'][ $subLabel ]['parents'][ $to ] = $parent;
							}

							unset( $subLabel );
							unset( $node );

							break;
						}

						unset( $nodeToPrune );
					}
					// BMS 2019-05-23
					// Look to see if this node has been defined in another instance of the ELR
					// This happens in the UK GAAP and DK IFRS.
					// In the UK GAAP uk-gaap-2009-09-01.xsd#uk-gaap_CashBankInHand is defined as the parent of
					// uk-gaap-2009-09-01.xsd#uk-gaap_CashBank and uk-gaap-2009-09-01.xsd#uk-gaap_CashInHand in
					// one instance of the ELR http://www.xbrl.org/uk/role/Notes.  This block appears as a child
					// of uk-gaap-2009-09-01.xsd#uk-gaap_CurrentAssetHeading.  It is also referenced as a child
					// of uk-gaap-2009-09-01.xsd#uk-gaap_Cash-MovementAnalysisHeading with period start AND
					// period end preferred labels.
					// Note that uk-gaap-2009-09-01.xsd#uk-gaap_CashBankInHand is also referenced as a child of
					// uk-gaap-2009-09-01.xsd#uk-gaap_CurrentAssets in the ELR http://www.xbrl.org/uk/role/BalanceSheet
					// However, this is a reference to the concept alone not the block.
					else if ( isset( $roleRef['paths'][ $fragment ] ) )
					{
						foreach ( $roleRef['paths'][ $fragment ] as $path )
						{
							if ( ! XBRL::endsWith( $path, $fragment ) ) continue;

							$node = false;
							$this->processNode( $roleRef['hierarchy'], $path, function( &$pathNode ) use( &$node )
							{
								if ( ! isset( $pathNode['children'] ) ) return;
								$node = $pathNode;
							} );

							if ( ! $node ) continue;

							// Process the node
							$nodes[ $to ] = array();
							$nodes[ $to ]['label'] = $to;
							foreach( $node['children'] as $childLabel => $childNode )
							{
								unset( $childNode['label'] );
								unset( $childNode['parent'] );
								$childNode['preferredLabel'] = $preferredLabel;
								$nodes[ $to ]['children'][ $childLabel ] = array(
									'label' => $childLabel,
									'parents' => array( $to => $childNode )
								);
							// 	$parent = $childNode['parents'][ $href ];
							// 	$parent['preferredLabel'] = $preferredLabel;
							// 	$nodes[ $to ]['children'][ $subLabel ]['parents'][ $to ] = $parent;
							}
							unset( $node );
							break;
						}
					}

				}

				// Create a node for the 'to' component
				// A 'to' node can previously exist in two scenarios:
				//	1) The node is used by two (or more) parts of the hierarchy such as is the case in
				//     the UK GAAP 2009 (eg ProfitLossFormat1Heading and ProfitLossFormat2Heading)
				//	2) The node has a beginning and ending balance variant
				if ( ! isset( $nodes[ $to ] ) )
				{
					$nodes[ $to ] = array(
						'label' => $to,
						'parents' => array( $from => array() ),
					);
				}

				// the 'parents' array is used to hold the details of a specific link from $from to $to
				extract( $this->validateArcAttributes( 'presentation', $fromLabel, $toLabel, $arcAttributes ) );
				$nodes[ $to ]['parents'][ $from ] = array(
					'order'		=> $order,
					'use'		=> $use,
					'priority'	=> $priority,
				);

				if ( ! empty( $preferredLabel ) )
				{
					$nodes[ $to ]['parents'][ $from ]['preferredLabel'] = (string) $arcAttributes->preferredLabel;
				}

				$taxonomyElement = $this->findTaxonomyElement( $from, 'presentation' );
				if ( ! isset( $nodes[ $from ] ) )
				{
					$nodes[ $from ] = array(
						'children' => array(),
						'label' => $from,
						'arcrole'  => $arcroleUri,
					);
				}

				// $this->log()->info( "Adding relation {$from} -> {$to}" );
				// Relate $from->$to nodes
				if ( XBRL::isTuple( $taxonomyElement ) && false )
				{
					// Remove the $to nodes because they are redundant
					unset( $nodes[ $to ] );
				}
				else
				{
					if ( ! isset( $nodes[ $from ]['children'] ) )
						$nodes[ $from ]['children'] = array();

					$nodes[ $from ]['children'][ $to ] =& $nodes[ $to ];
				}
			} // Presentation arcs

			// Process any arcs that must be removed
			if ( $additions !== false && isset( $additions['removearcs'] ) && is_array( $additions['removearcs'] ) && count( $additions['removearcs'] ) > 0 )
			{
				foreach ( $additions['removearcs'] as $index => $arc )
				{
					if ( ! isset( $nodes[ $arc['from'] ] ) )
					{
						$this->log()->warning( "Remove failed arc '{$arc['from']}' not found" );
						continue;
					}

					$fromNode =& $nodes[ $arc['from'] ];
					if ( ! isset( $nodes[ $arc['from'] ]['children'] ) )
					{
						$this->log()->info( "No children" );
						continue;
					}

					// Remove the $to from the list of $from node's children
					$indexes = array();
					foreach ( $fromNode['children'] as $index => &$node )
					{
						if ( $arc['to'] !== $node['label'] ) continue;
						$indexes[] = $index;
					}

					foreach ( $indexes as $index )
						unset( $fromNode['children'][ $index ] );

					// Get the $to
					$toNode   =& $nodes[ $arc['to'] ];
					// Remove the from node from the list of parents

					// Remove the $to from the list of $from node's children
					$indexes = array();
					foreach ( $toNode['parents'] as $index => $parent )
					{
						if ( $arc['from'] !== $parent ) continue;
						$indexes[] = $index;
					}

					foreach ( $indexes as $label )
						unset( $toNode['parents'][ $label ] );
				}
			} // if additions

			// Process any arcs that must be deleted
			if ( $additions !== false && isset( $additions['deletenodes'] ) && is_array( $additions['deletenodes'] ) && count( $additions['deletenodes'] ) > 0 )
			{
				foreach ( $additions['deletenodes'] as $label => $parentArc )
				{
					if ( ! isset( $nodes[ $label ] ) )
					{
						$this->log()->warning( "Delete failed for node with label '$label'" );
						continue;
					}

					$fromNode =& $nodes[ $label ];

					// Check it has no parents
					if ( isset( $fromNode['parents'] ) && is_array( $fromNode['parents'] ) && count( $fromNode['parents'] ) > 0 )
					{
						$this->log()->warning( "Unable to delete the node with label '$label' because it is not an orphan" );
						continue;
					}

					unset( $nodes[ $label ] );
				}
			} // if additions

			// Process any arcs that must be added
			if ( $additions !== false && isset( $additions['addarcs'] ) && is_array( $additions['addarcs'] ) && count( $additions['addarcs'] ) > 0 )
			{
				foreach ( $additions['addarcs'] as $index => $arc )
				{
					$from = $arc['from'];
					$to = $arc['to'];
					$order = isset( $arc['order'] ) ? $arc['order'] : 1;

					if ( ! isset( $nodes[ $from ] ) )
					{
						$this->log()->warning( "Adding arc failed because '$from' has not been found" );
						continue;
					}

					$fromNode =& $nodes[ $from ];
					// $this->log()->info( "Adding: $from -> $to" );
					// Check the 'to' reference is not already the label of a child node
					if ( isset( $fromNode['children'] ) )
					{
						$found = false;
						foreach ( $fromNode['children'] as $index => &$node )
						{
							if ( $to === $node['label'] )
							{
								$found = true;
								break;
							}
						}

						if ( $found )
						{
							$this->log()->warning( "The node with label '$to' is already a child of '$from' so cannot be added again" );
							continue;
						}
					}

					if ( isset( $nodes[ $to ] ) )
					{
						// $this->log()->info( "Existing to: $to" );
						$toNode =& $nodes[ $to ];
						// Check the 'from' label is not a reference to an existing parent
						if ( isset( $toNode['parents'] ) )
						{
							if ( is_array( $toNode['parents'] ) && count( $toNode['parents'] ) > 0 )
							{
								$found = false;
								foreach ( $toNode['parents'] as $index => $label )
								{
									if ( $from === $label )
									{
										$found = true;
										break;
									}
								}

								if ( $found )
								{
									$this->log()->warning( "The node with label '$from' is already a parent of '$to' so cannot be added again" );
									continue;
								}
							}
						}
						else
							$toNode['parents'] = array();

						$toNode['parents'][ $from ] = array( 'order' => $order, 'use' => 'optional', 'priority' => 1 );
					}
					else
					{
						// $this->log()->info( "To does not exist: $to" );
						$taxonomyElement = $this->findTaxonomyElement( $to, 'presentation' );
						if ( $taxonomyElement === null )
						{
							$this->log()->warning( "The taxonomy element for label '$to' cannot be found" );
							continue;
						}

						// print_r( $taxonomyElement );

						// Add the arc (basically a copy of the process above) though it should not really happen
						// Create a node for the 'to' component
						$nodes[ $to ] = array(
							'label'				=> $to,
							// 'taxonomy_element'	=> $taxonomyElement,
							'order'				=> $order,
						);
					}

					// If the node has been added via a prior 'from' then add the extra information
					// There will no parent if the node is a 'from'
					if ( ! isset( $nodes[ $to ]['parents'] ) )
					{
						$nodes[ $to ]['parents'] = array(
							$from => array(
								'order'		=> isset( $arc['order'] )		? $arc['order']		: 1,
								'use'		=> isset( $arc['use'] )			? $arc['use']		: 'optional',
								'priority'	=> isset( $arc['priority'] )	? $arc['priority']	: 0,
							)
						);
					}

					if ( ! isset( $nodes[ $from ] ) )
					{
						$nodes[ $from ] = array(
							'children' => array(),
							'label' => $from,
							'arcrole'  => $arcroleUri,
						);

						$taxonomyElement = $this->findTaxonomyElement( $from, 'presentation' );
						if ( $taxonomyElement === null )
							; // $this->log()->warning( "The taxonomy element for label '$from' cannot be found" );
						else
						{
							// $nodes[ $from ]['taxonomy_element'] = $taxonomyElement;
						}
					}

					$this->log()->info( "Adding relation {$from} -> {$to}" );
					if ( ! isset( $nodes[ $from ]['children'] ) )
						$nodes[ $from ]['children'] = array();

					$nodes[ $from ]['children'][ $to ] =& $nodes[ $to ];

				}
			} // if additions

			// Process any arcs that must be added
			if ( $additions !== false && isset( $additions['aliases'] ) && is_array( $additions['aliases'] ) && count( $additions['aliases'] ) > 0 )
			{
				foreach ( $additions['aliases'] as $name => $alias )
				{
					if ( ! isset( $nodes[ $name ] ) )
					{
						$this->log()->warning( "Adding alias failed because '$name' has not been found" );
						continue;
					}

					$fromNode =& $nodes[ $name ];
					if ( ! isset( $fromNode['alt_label'] ) ) $fromNode['alt_label'] = array();
					$fromNode['alt_label'] = $alias;

				}
			} // if additions

			foreach ( array_keys( $nodes ) as $i => $nodeKey )
			{
				if ( ! isset( $nodes[ $nodeKey ]['children'] ) ) continue;
				$label = $nodes[ $nodeKey ]['label'];
				$label = $nodeKey;

				// Need to implement a https://en.wikipedia.org/wiki/Schwartzian_transform because in PHP < 7.0
				// the order of keys when the sort terms is equal is undefined.  In this case we want the original
				// order because that's how its defined in the taxonomy. Do this by temporarily adding a sequence
				// value to each node.
				$seq = 0; foreach ( $nodes[ $nodeKey ]['children'] as $key => &$child ) $child['parents'][ $label ]['seq'] = $seq++; unset( $child ); unset( $seq );
				$result = uasort( $nodes[ $nodeKey ]['children'], function($a, $b) use( $label ) {
					// Here the label is the name of the parent
					if ( ! isset( $a['parents'] ) || ! isset( $b['parents'] ) )
					{
						$this->log()->warning( "Sorting: can't find parent $label" );
						return 0;
					}

					// if ( ! isset( $a['parents'][ $label ]['order'] ) || ! isset( $b['parents'][ $label ]['order'] ) )
					// {
					//	$x = 1;
					// }

					if ( $a['parents'][ $label ]['order'] == $b['parents'][ $label ]['order'] )
					{
						return isset( $a['parents'][ $label ]['seq'] ) && isset( $b['parents'][ $label ]['seq'] )
							? ( $a['parents'][ $label ]['seq'] < $b['parents'][ $label ]['seq'] ? -1 : 1 )
							: 0;
					}
					return ( $a['parents'][ $label ]['order'] < $b['parents'][ $label ]['order'] ) ? -1 : 1;
				} );
				foreach ( $nodes[ $nodeKey ]['children'] as $key => &$child ) if ( isset( $child['parents'][ $label ]['seq'] ) ) unset( $child['parents'][ $label ]['seq'] ); unset( $child );
			}

			// Convert the hierarchy from a collection of references into the $nodes array to a fixed collection
			// At this time the 'parents' information can be deleted.
			$realizeHierarchy = function( $nodes, $parent_label = null ) use( &$realizeHierarchy )
			{
				$new_nodes = array();

				foreach ( $nodes as $nodeKey => $node )
				{
					if ( $parent_label !== null && isset( $node['parents'][ $parent_label ] ) )
					{
						// Copy the values into the node
						$node = array_merge( $node, $node['parents'][ $parent_label ] );
						unset( $node['parents'][ $parent_label ] );
						unset( $node['parents'] );
						$node['parent'] = $parent_label;
					}

					$label = $node['label'];

					// $result = array();
					if ( isset( $node['children'] ) )
					{
						$children = $node['children'];
						unset( $node['children'] );
						$node['children'] = $realizeHierarchy( $children, $label );
						if ( array_key_exists( 'children', $node ) && is_null( $node['children'] ) )
						{
							unset( $node['children'] );
						}
					}

					if ( array_key_exists( 'children', $node ) && is_null( $node['children'] ) )
					{
						unset( $node['children'] );
					}
					$new_nodes[ $nodeKey ] = $node;
				}
				return $new_nodes;
			}; // $realizeHierarchy

			$hierarchy = array_filter( $nodes, function( $node ) {
				return ! isset( $node['parents'] ) || count( $node['parents'] ) === 0;
			});

			$hierarchy = $realizeHierarchy($hierarchy);

			// Find the key node in $nodes.  This function will recursively traverse the
			// node hierarchy to find the node.  It will then work out if the node is to
			// be removed or if it is to be left alone.
			$findSameNode = function( &$nodes, $node ) use( &$findSameNode )
			{
				if ( isset( $nodes[ $node['label'] ] ) )
				{
					// First need to check if any existing links need to be removed because they have been prohibited
					foreach ( $node['children'] as $childNodeKey => $childNode )
					{
						// Only interested in probited uses
						if ( ! isset( $childNode['use'] ) || $childNode['use'] != XBRL_Constants::$xlinkUseProhibited ) continue;

						// OK, there is one so need to get the correponding child node from $nodes[ $key ]
						if ( isset( $nodes[ $node['label'] ]['children'][ $childNodeKey ] ) )
						{
							// Need to compare the priority of the existing node with the priority of the new node
							$currentPriority = $nodes[ $node['label'] ]['children'][ $childNodeKey ]['priority'] ?? 0;
							$newPriority = $childNode['priority'] ?? 0;

							// If the new priority is the same or greater than the existing priority then it needs to be removed
							if ( $newPriority >= $currentPriority )
							{
								unset( $nodes[ $node['label'] ]['children'][ $childNodeKey ] );
							}
						}

						// Remove the $childNode from $node['children'] so it is not added in the next step
						unset( $node['children'][ $childNodeKey ] );
					}

					$h = XBRL::mergeHierarchiesInner( $nodes[ $node['label'] ], $node );
					return true;
				}

				foreach ( $nodes as $nodeKey => &$childNode )
				{
					if ( ! isset( $childNode['children'] ) ) continue;
					if ( $findSameNode( $childNode['children'], $node['label'], $node ) ) return true;
				}

				return false;
			}; // $findSameNode

			// The $findSameNode function will traverse the entire node structure which, if the
			// node list is large, may adversely affect performance.  This function allows a set
			//  of paths to be used as an index to the location of the node in the hierarchy.
			$findSameNodeByPath = function( &$nodes, &$paths, $node ) use( &$findSameNodeByPath, &$findSameNode ) {

				$result = true;

				foreach ( $paths as $pathKey => $path )
				{
					$currentNodes =& $nodes;
					if ( isset( $pathNode ) ) unset( $pathNode );
					$pathNode = false;
					$currentPath = "";

					$pathsParts = explode( '/', $path );
					for ( $i = 0; $i < count( $pathsParts ); $i++ )
					{
						if ( ! isset( $currentNodes[ $pathsParts[ $i ] ] ) )
						{
							XBRL_Log::getInstance()->err( "This is bad" );
							$currentNodes = false;
							break;
						}

						if ( $pathsParts[ $i ] == $node['label'] )
						{
							break;
						}

						$pathNode =& $currentNodes[ $pathsParts[ $i ] ];
						$currentNodes =& $pathNode['children'];
					}

					if ( ! $currentNodes )
					{
						XBRL_Log::getInstance()->warning( "There are no current nodes so something went wrong evaluation path '$path'" );
						continue;
					}

					$result &= $findSameNode( $currentNodes, $node );
				}

				return $result;
			}; // $findSameNodeByPath

			// Get this extended role content
			// BMS 2019-05-21 Moved above $presentationArc loop
			// $roleRef =& $this->context->presentationRoleRefs[ $roleRefsKey ];

			// BMS 2018-05-02 For some reason this element initializer was in the for loop below
			if ( ! isset( $roleRef['hierarchy'] ) )
			{
				$roleRef['hierarchy'] = array();
			}

			// Transfer to the role refs hierarchy
			foreach ( $hierarchy as $nodeKey => &$node )
			{
				$result = false;
				$taxonomy_element = $this->findTaxonomyElement( $node['label'], 'presentation' );
				if ( isset( $roleRef['paths'][ $taxonomy_element['id'] ] ) )
				{
					if ( isset( $roleRef['paths'][ $taxonomy_element['id'] ] ) && ! isset( $roleRef['hierarchy'][ $nodeKey ] ) )
					{
						$result = $findSameNodeByPath( $roleRef['hierarchy'], $roleRef['paths'][ $taxonomy_element['id'] ], $node );
					}
					else
					{
						$result = $findSameNode( $roleRef['hierarchy'], $node );
					}
				}

				if ( ! $result )
				{
					$roleRef['hierarchy'][ $nodeKey ] = $node;
				}
			} // foreach $hierachy

			$roleRef['locators'] = array_merge( isset( $roleRef['locators'] ) ? $roleRef['locators'] : array(), $locators );
			$roleRef['paths'] = isset( $roleRef['hierarchy'] ) ? $this->createHierarchyPaths( $roleRef['hierarchy'] ) : array();
			unset( $roleRef );
		} // $presentationLink

		// Remove unused roles
		$this->context->presentationRoleRefs = array_filter( $this->context->presentationRoleRefs, function( $item ) { return isset( $item['used'] ) && $item['used']; } );
	}

	/**
	 * Iterate over a set of nodes calling a callback for each one
	 *
	 * @param array $nodes		A root node collection to process
	 * @param string $callback	A function called for each node.  The node and id are passed.
	 * 							If the function returns false the processing will stop
	 */
	public static function processAllNodes( &$nodes, $callback = false )
	{
		if ( ! $callback ) return; // Nothing to do except waste time

		foreach( $nodes as $id => $node )
		{
			$result = $callback( $node, $id );
			if ( $result === false ) return false;

			if ( ! isset( $node['children'] ) ) continue;

			$result = self::processAllNodes( $node['children'], $callback );
			if ( $result === false ) return false;
		}
	}

	/**
	 * Find all nodes assocated with an array of paths then call a callback when found or not found
	 * @param array $nodes		 		A root node collection in which to find $node
	 * @param array $paths		 		An array of path indices
	 * @param string $node		 		A label representing the node to find
	 * @param Closure $successCallback	A function to call when a node is located.
	 * 							 		Will pass $node, the path to $node and the parent key of $node.
	 * 							 		The parent key will be false if $node is the root.
	 * @param Closure $failureCallback Called if $node is not found in $nodes.  Passes $path.
	 * @return boolean|array	 		An array representing the the node of $node from the $nodes hierarchy
	 */
	public function processNodeByPath( &$nodes, &$paths, $node, $successCallback = false, $failureCallback = false )
	{
		$result = true;
		if ( is_null( $nodes ) ) return;

		foreach ( $paths as $pathKey => $path )
		{
			unset( $currentNodes );
			unset( $currentNode );
			unset( $parentNode );

			$currentNodes =& $nodes;
			$currentPath = "";
			$currentNode = false;
			$parentNode = false;

			$pathsParts = explode( '/', $path );
			for ( $i = 0; $i < count( $pathsParts ); $i++ )
			{
				if ( ! isset( $currentNodes[ $pathsParts[ $i ] ] ) )
				{
					// XBRL_Log::getInstance()->err( "This is bad" );
					unset( $currentNodes );
					$currentNodes = false;
					$parentNode = false;
					break;
				}

				$currentNode =& $currentNodes[ $pathsParts[ $i ] ];

				if ( $pathsParts[ $i ] == $node )
				{
					break;
				}

				$parentNode = $currentNode;

				if ( isset( $currentNode['children'] ) )
				{
					$currentNodes =& $currentNode['children'];
				}

			}

			if ( ! $currentNodes )
			{
				if ( $failureCallback )
				{
					$failureCallback( $path );
				}
				else
				{
					XBRL_Log::getInstance()->warning( "There are no current nodes so something went wrong evaluating the path '$path'" );
				}
				continue;
			}

			if ( ! $successCallback ) continue;
			$successCallback( $currentNode, $path, $parentNode === false ? $parentNode : $parentNode['label'] );
		}

		return $result;
	}

	/**
	 * Process the labels linkbase type.
	 * @param array $linkbaseType The link base type to process
	 * @return void
	 */
	private function processLabelLinkbases( $linkbaseType )
	{
		// $this->log()->info( "Process label linkbases: " . $this->namespace );
		foreach ( $linkbaseType as $linkbaseRefkey => $linkbaseRef )
		{
			$this->processLabelLinkbase( $linkbaseRef );
		}
	}

	/**
	 * Part of the validation system to report missing XLink attributes
	 *
	 * @param string $section The numeric identifier of this attribute issue
	 * @param string $message The message to display
	 * @param string $attributeName The name of the missing attribute
	 * @param string $linkbase
	 * @return false
	 */
	private function reportMissingXLinkAttribute( $section, $message, $attributeName, $linkbase ) {
		$this->log()->taxonomy_validation( $section, $message,
			array(
				'attribute' => $attributeName,
				'linkbase' => $linkbase,
			)
		);
		return false;
	}

	/**
	 * Part of the validation system to report missing locator XLink attributes
	 *
	 * @param string $section The numeric identifier of this attribute issue
	 * @param string $attributeName The name of the missing attribute
	 * @param string $linkbase
	 * @return false
	 */
	private function reportMissingLocatorAttribute( $section, $attributeName, $linkbase ) {
		$this->reportMissingXLinkAttribute( $section, "Locators MUST include required XLink attributes", $attributeName, $linkbase );
		return false;
	}

	/**
	 * Part of the validation system to report missing resource XLink attributes
	 *
	 * @param string $section The numeric identifier of this attribute issue
	 * @param string $attributeName The name of the missing attribute
	 * @param string $linkbase
	 * @return false
	 */
	private function reportMissingResourceAttribute( $section, $attributeName, $linkbase ) {
		$this->reportMissingXLinkAttribute( $section, "Resources MUST include required XLink attributes", $attributeName, $linkbase );
		return false;
	}

	/**
	 * Part of the validation system to report missing arc XLink attributes
	 *
	 * @param string $section The numeric identifier of this attribute issue
	 * @param string $attributeName The name of the missing attribute
	 * @param string $linkbase
	 * @return false
	 */
	private function reportMissingArcAttribute( $section, $attributeName, $linkbase ) {
		$this->reportMissingXLinkAttribute( $section, "Arcs MUST include required XLink attributes", $attributeName, $linkbase );
		return false;
	}

	/**
	 * Part of the validation system to report missing linkbase files
	 *
	 * @param array $linkbaseRef An array containing standard linkbase information
	 * @return false
	 */
	private function reportMissingLinkbaseFile( $linkbaseRef )
	{
		XBRL_Log::getInstance()->taxonomy_validation( "4.3.2", "The file at this location does not exist",
			array(
				'url' => $linkbaseRef['href'],
			)
		);
		return false;
	}

	/**
	 * Part of the validation system to report that the content of the linkbase file does not match the role specified
	 *
	 * @param array $linkbaseRef An array containing standard linkbase information
	 * @param string $linkbase
	 * @return false
	 */
	private function reportNoRoleLinks( $linkbaseRef, $linkbase )
	{
		$xml_basename = basename( $linkbaseRef['href'] );

		$this->log()->taxonomy_validation( "4.3.3", "The content of the linkbase file does not match the role specified",
			array(
				'url' => "'$xml_basename'",
				'role' => "'{$linkbaseRef['role']}'",
				'link type' => $linkbase,
			)
		);
		return false;
	}

	/**
	 * Report a problem with the value of an XLink type attribute
	 *
	 * @param string $section
	 * @param string $message The message to display
	 * @param string $linkbase The linkbase type containing the error
	 * @param string $value  The value of the type
	 * @return boolean
	 */
	private function reportXLinkTypeError( $section, $message, $linkbase, $value )
	{
		$this->log()->taxonomy_validation( $section, $message,
			array(
				'linkbase' => $linkbase,
				'value' => $value,
			)
		);

		return false;
	}

	/**
	 * Report a problem with the value of an XLink type attribute of a 'locator' element
	 *
	 * @param string $section The section of the specificiation for which an error is being reported
	 * @param string $linkbase The linkbase type containing the error
	 * @param string $value  The value of the type
	 * @return boolean
	 */
	private function reportXLinkLocatorTypeError( $section, $linkbase, $value )
	{
		$this->reportXLinkTypeError( $section, "The content of the locator type MUST be 'locator'", $linkbase, $value );
		return false;
	}

	/**
	 * Report a problem with the value of an XLink type attribute of a 'resource' element
	 *
	 * @param string $section The section of the specificiation for which an error is being reported
	 * @param string $linkbase The linkbase type containing the error
	 * @param string $value  The value of the type
	 * @return boolean
	 */
	private function reportXLinkResourceTypeError( $section, $linkbase, $value )
	{
		$this->reportXLinkTypeError( $section, "The content of the locator type MUST be 'resource'", $linkbase, $value );
		return false;
	}

	/**
	 * Report a problem with the value of an XLink type attribute of a 'resource' element
	 *
	 * @param string $section The section of the specificiation for which an error is being reported
	 * @param string $linkbase The linkbase type containing the error
	 * @param string $value  The value of the type
	 * @return boolean
	 */
	private function reportXLinkArcTypeError( $section, $linkbase, $value )
	{
		$this->reportXLinkTypeError( $section, "The content of the locator type MUST be 'arc'", $linkbase, $value );
		return false;
	}

	/**
	 * Validate an XLink label
	 *
	 * @param string $linkbase
	 * @param string $value  The value of the type
	 * @return boolean
	 */
	private function validateXLinkLabel( $linkbase, $value )
	{
		if ( ! XBRL::isValidating() ) return true;

		if ( preg_match( '/^[\pL_]/', $value ) ) return true;
		// if ( ! empty( $value ) && ( ctype_alpha( $value[0] ) || $value[0] == '_' ) ) return true;

		return $this->reportXLinkTypeError( "3.5.3.7.3", "Error validating linkbase", $linkbase, $value );
	}

	/**
	 * A locator MUST have a type and its value MUST be 'locator'
	 *
	 * @param string $linkbase
	 * @param string $value  The value of the type
	 * @return boolean
	 */
	private function validateXLinkLocatorType( $linkbase, $value )
	{
		if ( ! XBRL::isValidating() ) return true;

		if ( $value == 'locator' ) return true;

		return $this->reportXLinkLocatorTypeError( "3.5.3.7.1", $linkbase, $value );
	}

	/**
	 * Validate the existence of XLink properties for locators
	 *
	 * @param SimpleXMLElement $xlinkAttributes
	 * @param string $linkbaseName
	 * @param array $linkbaseHRef
	 * @return boolean
	 */
	private function validateXLinkLocatorAttributes( $xlinkAttributes, $linkbaseName, $linkbaseHRef )
	{
		if ( ! XBRL::isValidating() ) return true;

		$result = true;

		if ( ! property_exists( $xlinkAttributes, 'type' ) )
		{
			$this->reportMissingLocatorAttribute( '3.5.3.7.1', 'type', $linkbaseName );
			$result = false;
		}

		if ( ! property_exists( $xlinkAttributes, 'label' ) )
		{
			$this->reportMissingLocatorAttribute( '3.5.3.7.3', 'label', $linkbaseName );
			$result = false;
		}

		if ( ! property_exists( $xlinkAttributes, 'href' ) )
		{
			$this->reportMissingLocatorAttribute( '3.5.3.7.2', 'href', $linkbaseName );
			return false;
		}

		// BMS 2018-04-16 This have been moved to a function called 'validateLocator'
		// $href = (string) $xlinkAttributes->href;
		// $parts = parse_url( $href );
		// if ( ! isset( $parts['path'] ) )
		// {
		// 	if ( ! isset( $parts['fragment'] ) )
		// 	{
		// 		$this->log()->taxonomy_validation( "3.5.3.7.2", "The href of the locator is not valid",
		// 			array(
		// 				'linkbase' => $linkbaseName,
		// 				'href' => $href,
		// 			)
		// 		);
        //
		// 		return false;
		// 	}
        //
		// 	// The locator points to a reference that has already been loaded.  Just check that it has.
		// 	$result = isset( $this->linkbaseIds[ basename( $linkbaseHRef ) ][ trim( $parts['fragment'], '#' ) ] );
		// 	return $result;
		// }
        //
		// if ( XBRL::endsWith( $parts['path'], '.xml' ) )
		// {
		// 	// It's a linkbase reference
		// 	if ( isset( $this->linkbaseIds[ $parts['path'] ] ) )
		// 	{
		// 		return true;
		// 	}
        //
		// 	$linkbaseRef = array(
		// 		'type' => (string) $xlinkAttributes->type,
		// 		// BMS 2017-10-27 This makes the linkbase relative to the location of the schema file which is not correct
		// 		// 'href' => XBRL::resolve_path( $taxonomy->getSchemaLocation(), $href ),
		// 		'href' => XBRL::resolve_path( $this->schemaLocation, $parts['path'] ),
		// 		'role' => XBRL_Constants::$anyLinkbaseRef,
		// 		'arcrole' => \XBRL_Constants::$arcRoleLinkbase,
		// 		'title' => '',
		// 		'base' => '',
		// 	);
        //
		// 	$this->processPresentationLinkbase( $linkbaseRef );
		// 	$this->processLabelLinkbase( $linkbaseRef );
		// 	$this->processDefinitionLinkbase( $linkbaseRef );
		// 	$this->processCalculationLinkbase( $linkbaseRef );
		// 	// Handle custom linkbases
		// 	$this->processCustomLinkbaseArcRoles( $linkbaseRef );
        //
		// 	return true;
		// }
        //
		// /**
		//  * @var XBRL $taxonomy
		//  */
		// $taxonomy = $parts['path'] == $this->getTaxonomyXSD()
		// 	? $this
		// 	: $this->getTaxonomyForXSD( $parts['path'] );
        //
		// if ( ! $taxonomy )
		// {
		// 	// If the linkbase is 'label' look in the labels
		// 	if ( $linkbaseName == 'labels' )
		// 	{
		// 		$labelsHref =& $this->context->labels[ XBRL_Constants::$defaultLinkRole ]['labelshref'];
		// 		if ( ! isset( $labelsHref[ basename( $parts['path'] ) ] ) ||
		// 			 ! isset( $labelsHref[ basename( $parts['path'] ) ][ $parts['fragment'] ] ) )
		// 		{
		// 			$result = false;
		// 		}
		// 	}
		// 	else
		// 	{
		// 		// Look for the taxonomy and include its contents in the DTS
		// 		$xsd = $this->resolve_path( $this->getSchemaLocation(), $href );
		// 		$xsd = strpos( $xsd, '#' ) ? strstr( $xsd, '#', true ) : $xsd;
        //
		// 		$taxonomy = XBRL::withTaxonomy( $xsd );
		//		$this->indirectNamespaces[] = $taxonomy->getNamespace();
		//		$taxonomy->AddUserNamespace( $this );
		// 	}
		// }
        //
		// if ( $taxonomy )
		// {
		// 	// BMS 2018-04-16	This is required by the XBRL 2.1 specification (see test 2012 V-02)
		// 	$element =& $taxonomy->getElementById( $parts['fragment'] );
		// 	if ( ! $element )
		// 	{
		// 		$this->log()->taxonomy_validation( "3.5.3.9.2", "The concept of the locator does not exist in the DTS",
		// 			array(
		// 				'href' => $href,
		// 				'linkbase' => $linkbaseName,
		// 			)
		// 		);
		// 		$result = false;
		// 	}
		// }
		// else
		// {
		// 	$this->log()->taxonomy_validation( "3.5.3.7.2", "The document of the locator does not exist in the DTS",
		// 		array(
		// 			'href' => $href,
		// 			'linkbase' => $linkbaseName,
		// 		)
		// 	);
		// 	$result = false;
		// }

		return $result;
	}

	/**
	 * Validate the HRef
	 * @param SimpleXMLElement $xlinkAttributes
	 * @param string $linkbaseName
	 * @param array $locatorParts An array of url parts
	 * @param string $linkbaseUrl
	 * @return boolean
	 */
	private function validateLocator( $xlinkAttributes, $linkbaseName, $locatorParts, $linkbaseUrl )
	{
		$result = true;

		$href = (string) $xlinkAttributes->href;
		// $parts = parse_url( $href );
		if ( ! isset( $locatorParts['path'] ) )
		{
			if ( ! isset( $locatorParts['fragment'] ) )
			{
				$this->log()->taxonomy_validation( "3.5.3.7.2", "The href of the locator is not valid",
					array(
						'linkbase' => $linkbaseName,
						'href' => $linkbaseUrl,
					)
				);

				return false;
			}

			// The locator points to a reference that has already been loaded.  Just check that it has.
			$result = isset( $this->linkbaseIds[ basename( $linkbaseUrl ) ][ trim( $locatorParts['fragment'], '#' ) ] );
			return $result;
		}

		if ( XBRL::endsWith( $locatorParts['path'], '.xml' ) )
		{
			// //BMS 2018-05-04 Dont think this is necessary.  Copied to retrieveLocator for now
			// // It's a linkbase reference
			// if ( isset( $this->linkbaseIds[ basename( $locatorParts['path'] ) ] ) )
			// {
			// 	return true;
			// }
            //
			// $linkbaseRef = array(
			// 	'type' => (string) $xlinkAttributes->type,
			// 	// BMS 2017-10-27 This makes the linkbase relative to the location of the schema file which is not correct
			// 	// 'href' => XBRL::resolve_path( $taxonomy->getSchemaLocation(), $href ),
			// 	'href' => XBRL::resolve_path( $this->schemaLocation, $locatorParts['path'] ),
			// 	'role' => XBRL_Constants::$anyLinkbaseRef,
			// 	'arcrole' => \XBRL_Constants::$arcRoleLinkbase,
			// 	'title' => '',
			// 	'base' => '',
			// );
            //
			// $this->processPresentationLinkbase( $linkbaseRef );
			// $this->processLabelLinkbase( $linkbaseRef );
			// $this->processDefinitionLinkbase( $linkbaseRef );
			// $this->processCalculationLinkbase( $linkbaseRef );
			// $this->processReferenceLinkbase( $linkbaseRef );
			// // Handle custom linkbases
			// $this->processCustomLinkbaseArcRoles( $linkbaseRef );

			return true;
		}

		/**
		 * @var XBRL $taxonomy
		 */
		$taxonomy = basename( $locatorParts['path'] ) == $this->getTaxonomyXSD()
			? $this
			// BMS 2019-01-26 Changed to explicity use the basename as getTaxonomyForXSD no longer will
			: $this->getTaxonomyForXSD( basename( $locatorParts['path'] ) );

		if ( ! $taxonomy )
		{
			// If the linkbase is 'labelLink' look in the labels
			if ( $linkbaseName == 'labelLink' )
			{
				$labelsHref =& $this->context->labels[ XBRL_Constants::$defaultLinkRole ]['labelshref'];
				if ( ! isset( $labelsHref[ basename( $locatorParts['path'] ) ] ) ||
					 ! isset( $labelsHref[ basename( $locatorParts['path'] ) ][ $locatorParts['fragment'] ] ) )
				{
					$result = false;
				}
			}
			else
			{
				if ( PHP_SAPI === 'cli' && function_exists( 'xdebug_break' ) ) 
				{
					xdebug_break();
					error_log('xdebug_break');					
				}
				
				// Look for the taxonomy and include its contents in the DTS
				$xsd = $this->resolve_path( $this->getSchemaLocation(), $href );
				$xsd = strpos( $xsd, '#' ) ? strstr( $xsd, '#', true ) : $xsd;

				$taxonomy = XBRL::withTaxonomy( $xsd );
				$this->indirectNamespaces[] = $taxonomy->getNamespace();
				$taxonomy->AddUserNamespace( $this );
			}
		}

		if ( empty( $locatorParts['fragment'] ) )
		{
			return $result;
		}

		if ( $taxonomy )
		{
			// BMS 2018-04-16	This is required by the XBRL 2.1 specification (see test 2012 V-02)
			$element =& $taxonomy->getElementById( $locatorParts['fragment'] );
			if ( ! $element &&
				 ! isset( $taxonomy->roleTypeIds[ $locatorParts['fragment'] ] ) &&
				 ! isset( $taxonomy->arcroleTypeIds[ $locatorParts['fragment'] ] ) &&
				 ! $taxonomy->context->types->getTypeById( $locatorParts['fragment'], $taxonomy->getPrefix() )
			)
			{

				$this->log()->taxonomy_validation( "3.5.3.9.2", "The id of the locator does not exist in the DTS",
					array(
						'href' => basename( $linkbaseUrl ),
						'linkbase' => $linkbaseName,
					)
				);
				$result = false;
			}
		}
		else
		{
			$this->log()->taxonomy_validation( "3.5.3.7.2", "The document of the locator does not exist in the DTS",
				array(
					'href' => $locatorParts['path'],
					'linkbase' => $linkbaseName,
				)
			);
			$result = false;
		}

		return $result;
	}

	/**
	 * A locator MUST have a type and its value MUST be 'resource'
	 *
	 * @param string $linkbase
	 * @param string $value  The value of the type
	 * @return boolean
	 */
	private function validateXLinkResourceType( $linkbase, $value )
	{
		if ( ! XBRL::isValidating() ) return true;

		if ( $value == 'resource' ) return true;

		return $this->reportXLinkResourceTypeError( "3.5.3.8.1", $linkbase, $value );
	}

	/**
	 * Validate the existence of XLink properties for resources
	 *
	 * @param SimpleXMLElement $xlinkAttributes
	 * @param string $linkbase
	 * @return boolean
	 */
	private function validateXLinkResourceAttributes( $xlinkAttributes, $linkbase )
	{
		if ( ! XBRL::isValidating() ) return true;

		$result = true;

		if ( ! property_exists( $xlinkAttributes, 'type' ) )
		{
			$this->reportMissingResourceAttribute( '3.5.3.8.1', 'type', $linkbase );
			$result = false;
		}

		if ( ! property_exists( $xlinkAttributes, 'role' ) )
		{
			$this->reportMissingResourceAttribute( '3.5.3.8.3', 'role', $linkbase );
			$result = false;
		}

		if ( ! property_exists( $xlinkAttributes, 'label' ) )
		{
			$this->reportMissingResourceAttribute( '3.5.3.8.2', 'label', $linkbase );
			$result = false;
		}

		return true;
	}

	/**
	 * Check the priority, order and use attributes and return them in an indexed array
	 * @param string $linkbase
	 * @param string $from
	 * @param string $to
	 * @param SimpleXMLElement $attributes
	 * @return array
	 */
	private function validateArcAttributes( $linkbase, $from, $to, $attributes )
	{
		$result['priority'] = property_exists( $attributes, 'priority' ) ? (string) $attributes->priority	: "0";
		$result['use']		= property_exists( $attributes, 'use' ) 	 ? (string) $attributes->use		: XBRL_Constants::$xlinkUseOptional;
		$result['order']	= property_exists( $attributes, 'order' ) 	 ? (string) $attributes->order		: "1";
		$result['title']	= property_exists( $attributes, 'title' ) 	 ? (string) $attributes->title		: "";

		if ( filter_var( $result['priority'], FILTER_VALIDATE_INT ) === false )
		{
			$this->log()->taxonomy_validation( "3.5.3.9.5", "The arc 'priority' value MUST be an integer",
				array(
					'linkbase' => $linkbase,
					'from' => $from,
					'to' => $to,
					'priority' => $result['priority'],
				)
			);
		}

		if ( ! in_array( $result['use'], XBRL_Constants::$xlinkUseValues ) )
		{
			$this->log()->taxonomy_validation( "3.5.3.9.5", "The arc 'use' value MUST be an integer",
				array(
					'linkbase' => $linkbase,
					'from' => $from,
					'to' => $to,
					'use' => $result['use'],
					'should be' => join( ' or ', XBRL_Constants::$xlinkUseValues )
				)
			);
		}

		if ( ! is_numeric( $result['order'] ) )
		{
			$this->log()->taxonomy_validation( "3.5.3.9.5", "The arc 'order' value MUST be numeric",
				array(
					'linkbase' => $linkbase,
					'from' => $from,
					'to' => $to,
					'order' => $result['order'],
				)
			);
		}

		return $result;
	}

	/**
	 * A locator MUST have a type and its value MUST be 'arc'
	 *
	 * @param string $linkbase
	 * @param string $value  The value of the type
	 * @return boolean
	 */
	private function validateXLinkArcType( $linkbase, $value )
	{
		if ( ! XBRL::isValidating() ) return true;

		if ( $value == 'arc' ) return true;

		return $this->reportXLinkArcTypeError( "3.5.3.9.1", $linkbase, $value );
	}

	/**
	 * A locator MUST have a type and its value MUST be XBRL_Constants::$arcRoleConceptLabel
	 *
	 * @param string $linkbase
	 * @param string $value  The value of the type
	 * @return boolean
	 */
	private function validateXLinkArcrole( $linkbase, $value )
	{
		if ( ! XBRL::isValidating() ) return true;

		if ( $value == XBRL_Constants::$arcRoleConceptLabel ) return true;

		return $this->reportXLinkArcTypeError( "3.5.3.9.4", $linkbase, $value );
	}

	/**
	 * Validate the existence of XLink properties for arcs
	 *
	 * @param SimpleXMLElement $xlinkAttributes
	 * @param string $linkbase
	 * @return boolean
	 */
	private function validateXLinkArcAttributes( $xlinkAttributes, $linkbase )
	{
		if ( ! XBRL::isValidating() ) return true;

		$result = true;

		if ( ! property_exists( $xlinkAttributes, 'arcrole' ) )
		{
			$this->reportMissingArcAttribute( '3.5.3.9.4', 'arcrole', $linkbase );
			$result = false;
		}

		if ( ! property_exists( $xlinkAttributes, 'to' ) )
		{
			$this->reportMissingArcAttribute( '3.5.3.9.3', 'to', $linkbase );
			$result = false;
		}

		if ( ! property_exists( $xlinkAttributes, 'from' ) )
		{
			$this->reportMissingArcAttribute( '3.5.3.9.2', 'from', $linkbase );
			$result = false;
		}

		return true;
	}

	/**
	 * A container to hold arc validation information that enable arcrole duplication
	 * @var array
	 */
	private $extendedLinkFromToPairs = array();

	/**
	 * A container to hold calculation arc validation information that enable arcrole duplication
	 * @var array
	 */
	private $calculationExtendedLinkFromToPairs = array();

	/**
	 * This is an XLink constraint.  See https://www.w3.org/TR/xlink/#xlink-arcs
	 * and the section titled 'Constraint: No Arc Duplication'
	 *
	 * @param string $role
	 * @param string $arcrole
	 * @param string $fromId
	 * @param string $toId
	 * @param string $fromLabel
	 * @param string $toLabel
	 * @param string $xml_basename
	 * @param int $xml_lineno
	 * @return boolean
	 */
	private function validateFromToArcPairs( $role, $arcrole, $fromId, $toId, $fromLabel, $toLabel, $xml_basename, $xml_path )
	{
		if ( ! XBRL::isValidating() ) return true;

		$summationItem = $arcrole == XBRL_Constants::$arcRoleSummationItem;

		$result = true;
		$hashFromTo = hash( 'SHA256', "{$arcrole}-{$fromLabel}-{$toLabel}" );

		if ( isset( $this->extendedLinkFromToPairs[ $role ][ $hashFromTo ] ) )
		{
			$path = key( $this->extendedLinkFromToPairs[ $role ][ $hashFromTo ] );

			if ( $path != $xml_path )
			{
				// As the paths are repeated but are not exactly the same, then if they are in the same ELR raise an error
				$pattern = '^/.*/(?<link>.*)/(?<arc>.*)$';
				$pathMatches = array();
				if ( ! preg_match( "|$pattern|", $path, $pathMatches ) )
				{
					$this->log()->debug("Unable parse path '$path' using regular expression '$pattern'");
					return false;
				}

				$xmlPathMatches = array();
				if ( ! preg_match( "|$pattern|", $xml_path, $xmlPathMatches ) )
				{
					$this->log()->debug("Unable parse path '$xml_path' using regular expression '$pattern'");
					return false;
				}

				if ( $pathMatches['link'] == $xmlPathMatches['link'] && $pathMatches['arc'] != $xmlPathMatches['arc'] )
				{
					$this->log()->taxonomy_validation( "XLink", "The XLink specification does not permit the 'from' and 'to' attribute values to be repeated within the same extended link",
						array(
							'from' => $fromLabel,
							'to' => $toLabel,
							'spec' => "'https://www.w3.org/TR/xlink/#xlink-arcs'",
							'role' => "'$role'",
							'file' => $xml_basename,
							'path' => $xml_path,
						)
					);

					$result = false;
				}
			}
		}
		else
		{
			$this->extendedLinkFromToPairs[ $role ][ $hashFromTo ][ $xml_path ] = "{$arcrole}-{$fromLabel}-{$toLabel}";
		}

		if ( $summationItem )
		{
			$hashFromTo = hash( 'SHA256', "{$arcrole}-{$fromId}-{$toId}" );

			if ( isset( $this->calculationExtendedLinkFromToPairs[ $role ][ $hashFromTo ] ) )
			{
				$this->log()->business_rules_validation( "Calculations", "The same locator from/to pair cannot appear twice in the same calculation arc extended link role",
					array(
						'from' => $fromId,
						'to' => $toId,
						'role' => "'$role'",
						'file' => $xml_basename,
						'path' => $xml_path,
						'note' => 'This is not strictly an error covered by the XBRL 2.1 specification but two identical arcs makes no sense.  See https://bugzilla.xbrl.org/show_bug.cgi?id=787'
					)
				);

				$result = false;
			}
			else
			{
				$this->calculationExtendedLinkFromToPairs[ $role ][ $hashFromTo ][ $xml_path ] = "{$arcrole}-{$fromId}-{$toId}";
			}
		}

		return $result;
	}

	/**
	 * Reset the extendedLinkFromToPairs array
	 */
	private function resetValidateFromToArcPairs()
	{
		$this->extendedLinkFromToPairs = array();
		$this->calculationExtendedLinkFromToPairs = array();
	}

	/**
	 * Called to remove an entry from the $extendedLinkFromToPairs array
	 * @param string $role
	 * @param string $arcrole
	 * @param string $fromId
	 * @param string $toId
	 * @param string $fromLabel
	 * @param string $toLabel
	 * @return void
	 */
	private function removeFromToArcPair( $role, $arcrole, $fromId, $toId, $fromLabel, $toLabel )
	{
		if ( ! XBRL::isValidating() ) return true;

		$hashFromTo = hash( 'SHA256', "{$arcrole}-{$fromId}-{$toId}" );
		unset( $this->extendedLinkFromToPairs[ $role ][ $hashFromTo ] );

		if ( $arcrole == XBRL_Constants::$arcRoleSummationItem )
		{
			$hashFromTo = hash( 'SHA256', "{$arcrole}-{$fromLabel}-{$toLabel}" );
			unset( $this->calculationExtendedLinkFromToPairs[ $role ][ $hashFromTo ] );
		}
	}

	/**
	 * Check to confirm the node does not contain attributes defined by the XBRL specification
	 * @param SimpleXMLElement $node
	 * @return boolean
	 */
	public function validateLegalAttributes( $node )
	{
		if ( ! XBRL::isValidating() ) return true;

		// Tuple elements should not have attributes from XBRL namespaces
		$illegalAttributes = array();
		foreach ( XBRL_Constants::$standardPrefixes as $prefix => $namespace )
		{
			if ( $prefix == STANDARD_PREFIX_SCHEMA ) continue;
			if ( $prefix == STANDARD_PREFIX_SCHEMA_INSTANCE ) continue;

			foreach ( $node->attributes( $namespace ) as $key => $attribute )
			{
				/** @var \SimpleXMLElement $attribute */
				$illegalAttributes[] = $prefix . ":" . $attribute->getName();
			}
		}

		if ( $illegalAttributes )
		{
			$this->log()->taxonomy_validation( "4.9", "Attributes assigned to tuples should not be ones defined in XBRL specification namespaces",
				array(
					'name' => $node->getName(),
					'attributes' => "'" . join( ', ', $illegalAttributes ) . "'",
				)
			);

			return true;
		}

		return false;
	}

	/**
	 * Examine the arcs recursively to look for directed cycles.
	 * If there are directed cyles then the label of an arc will appear in the list of $parents
	 *
	 * @param string $linkbase The linkbase containing these nodes
	 * @param string $role The extended link role
	 * @param array $nodes A hierarchical set of node to examine
	 * @param array $existingParents An array of ids that represent existing parents which should not recur.
	 * @param Closure $errorCallback A callback to report the error.  The function will be passed
	 * 								  the role, the node and the linkbase containing the error.
	 * @return bool
	 */
	public function validateDirectedCycles( $linkbase, $role, $nodes, $existingParents, $errorCallback )
	{
		if ( ! XBRL::isValidating() ) return true;

		$detectDirected = function( $role, $nodes, $parents = array()  ) use( &$detectDirected )
		{
			foreach ( $nodes as $nodeKey => $node )
			{
				// This is for the presentation linkbase
				if ( isset( $node['usage'] ) && ! $node['usage'] ) continue;
				// This is for definition linkbase
				if ( isset( $node['parents'] ) )
				{
					$nodeParent = array_intersect_key( $node['parents'], array_flip( $parents ) );
					$usable = array_reduce( $nodeParent, function( $carry, $parent ) {
						$carry &= ! isset( $parent['usable'] ) || $parent['usable'];
						return $carry;
					}, true );

					if ( ! $usable )
					{
						continue;
					}
				}

				if ( in_array( $nodeKey, $parents ) ) return $nodeKey;
				if ( ! isset( $node['children'] ) ) continue;

				$result = $detectDirected( $role, $node['children'], array_merge( $parents, array( $nodeKey ) ) );
				if ( $result )
				{
					return $result;
				}
			}

			return false;
		};

		$result = $detectDirected( $role, $nodes, $existingParents );

		if ( ! $result ) return true;
		if ( ! $errorCallback ) return false;

		$errorCallback( $role, $result, $linkbase );

		return false;
	}

	/**
	 * Process labels and populate the $this->labelLinkRoleRefs variable with locators, arcs and labels
	 * @param array $linkbaseRef The link details
	 * @return boolean
	 */
	public function processLabelLinkbase( $linkbaseRef )
	{
		// $this->log()->info( "Process label linkbase {$linkbaseRef[ 'href' ]}" );
		// The 'href' may contain an XPointer fragment that specifies a target in the document
		$parts = explode( '#', $linkbaseRef['href'] );
		$linkbaseRef['href'] = $parts[0];
		$xml_basename = pathinfo( $parts[0], PATHINFO_BASENAME );
		$fragment = isset( $parts[1] ) ? $parts[1] : "";
		$usedOn = 'link:label';

		// Has it been processed?
		if ( isset( $this->context->processedLinkbases[ "$usedOn:$xml_basename" ] ) )
		{
			return;
		}
		$this->context->processedLinkbases[ "$usedOn:$xml_basename" ] = array( 'linkbase' => $xml_basename, 'usedOn' => $usedOn );

		// If this is an http/https scheme then there should be two forward slashes after the colon.
		// If this is not http/https then there should be just one slash
		$path = XBRL::resolve_path( $linkbaseRef['href'], $linkbaseRef['base'] . $xml_basename );
		// $path = str_replace( "//", "/", pathinfo( $linkbaseRef['href'], PATHINFO_DIRNAME ) . "/" . $linkbaseRef['base'] . "/" . $xml_basename );
		// $path = preg_replace( '~^(https?):/([^/])~', '$1://$2', $path );

		$xml = XBRL::getXml( $path, $this->context );
		if ( $xml === null )
		{
			return $this->reportMissingLinkbaseFile( $linkbaseRef );
		}

		if ( $fragment )
		{
			// TODO Validate the XPointer fragment and set the $xml variable to the location defined by the fragment
			if ( $this->isPointer( $fragment, $xml, null, $name, $domNode ) )
			{
				if ( $domNode )
				{
					$xml = simplexml_import_dom( $domNode );
					unset( $domNode );
				}
			}
		}

		$this->processLabelLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn );
	}

	/**
	 * Processes a label linkbase given SimpleXMLElement node
	 * @param array $linkbaseRef
	 * @param SimpleXMLElement $xml
	 * @param string $xml_basename
	 * @param string $usedOn
	 */
	private function processLabelLinkbaseXml( $linkbaseRef, $xml, $xml_basename, $usedOn )
	{
		// Make sure this file contains label link elements so is valid for the role type
		if ( ! count( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->labelLink ) )
		{
			if ( ! empty( $linkbaseRef['role'] ) && $linkbaseRef['role'] != XBRL_Constants::$anyLinkbaseRef )
			{
				$this->reportNoRoleLinks( $linkbaseRef, 'label' );
			}
			return;
		}

		$arcroleRefs = array();
		foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->arcroleRef as $arcroleRefKey => $arcroleRef )
		{
			$attributes = $arcroleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			$arcroleRefHref = (string) $attributes->href;

			// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
			// because a role reference to a type might be a relative reference to an id in the schema doc.
			$arcroleRefParts = explode( "#", $arcroleRefHref );
			if ( ! $arcroleRefParts )
			{
				$arcroleRefHref = $this->getTaxonomyXSD();
			}
			else if ( ! $arcroleRefParts[0] )
			{
				$arcroleRefParts[0] = $this->getTaxonomyXSD();
				$arcroleRefHref = implode( "#", $arcroleRefParts );
			}

			$arcroleUri = (string) $arcroleRef->attributes()->arcroleURI;
			$fragment = parse_url( $arcroleRefHref, PHP_URL_FRAGMENT );

			if ( XBRL::isValidating() )
			{
				if ( isset( $arcroleRefs[ $arcroleUri ] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one arcroleRef element with the same @arcroleURI attribute value",
						array(
							'role' => $arcroleUri,
							'href' => $xml_basename,
						)
					);
				}
			}

			$arcroleRefs[ $arcroleUri ] = $fragment;

			$taxonomy = $this->getTaxonomyForXSD( $arcroleRefHref );
			if ( ! $taxonomy )
			{
				$xsd = strpos( $arcroleRefHref, '#' ) === false ? $arcroleRefHref : strstr( $arcroleRefHref, '#', true );
				if ( isset( XBRL_Global::$taxonomiesToIgnore[ $xsd ] ) ) continue;

				$xsd = $this->resolve_path( $linkbaseRef['href'], $xsd );
				// If the taxonomy is not already loaded, try loading it.
				$taxonomy = $xsd ? XBRL::withTaxonomy( $xsd ) : null;
				if ( ! $taxonomy )
				{
					$this->log()->taxonomy_validation( "5.1.3.4", "Taxonomy for arcroleRef href does not exist",
						array(
							'href' => "'$arcroleRefHref'",
							'linkbase' => "'$xml_basename'",
						)
					);
					continue;
				}
				$this->indirectNamespaces[] = $taxonomy->getNamespace();
				$taxonomy->AddUserNamespace( $this );
			}

			// This role MUST be defined as 'usedOn' in the linkbaseRef for link:labelArc
			if ( ! count( $taxonomy->arcroleTypes ) || ! isset( $taxonomy->arcroleTypes['link:labelArc'][ $arcroleUri ] ) )
			{
				$this->log()->taxonomy_validation( "5.1.3.4", "This arcrole is not defined to be used on the label linkbase",
					array(
						'arcrole' => "'$arcroleUri'",
						'linkbase' => "'$xml_basename'",
					)
				);
				continue;
			}

			// Make sure this arcrole is in the current taxonomy
			$this->arcroleTypes['link:labelArc'][ $arcroleUri ] = $taxonomy->arcroleTypes['link:labelArc'][ $arcroleUri ];
		}

		$labelRoles = array(
			XBRL_Constants::$labelRoleLabel,
			XBRL_Constants::$labelRoleTerseLabel,
			XBRL_Constants::$labelRoleVerboseLabel,
			XBRL_Constants::$labelRolePositiveLabel,
			XBRL_Constants::$labelRolePositiveTerseLabel,
			XBRL_Constants::$labelRolePositiveVerboseLabel,
			XBRL_Constants::$labelRoleNegativeLabel,
			XBRL_Constants::$labelRoleNegativeTerseLabel,
			XBRL_Constants::$labelRoleNegativeVerboseLabel,
			XBRL_Constants::$labelRoleZeroLabel,
			XBRL_Constants::$labelRoleZeroTerseLabel,
			XBRL_Constants::$labelRoleZeroVerboseLabel,
			XBRL_Constants::$labelRoleTotalLabel,
			XBRL_Constants::$labelRolePeriodStartLabel,
			XBRL_Constants::$labelRolePeriodEndLabel,
			XBRL_Constants::$labelRoleDocumentation,
			XBRL_Constants::$labelRoleDefinitionGuidance,
			XBRL_Constants::$labelRoleDisclosureGuidance,
			XBRL_Constants::$labelRolePresentationGuidance,
			XBRL_Constants::$labelRoleMeasurementGuidance,
			XBRL_Constants::$labelRoleCommentaryGuidance,
			XBRL_Constants::$labelRoleExampleGuidance,
		);

		$taxonomy_basename = $this->getTaxonomyXSD();

		foreach ( $labelRoles as $role )
		{
			$roleName = str_replace( XBRL_Constants::$rolePrefix, '', $role );

			$this->context->labelRoleRefs[ $role ] = array(
					'type' => 'simple',
					'href' => "$taxonomy_basename#$roleName",
					'roleUri' => $role,
			);
		}

		unset( $role );
		if ( ! isset( $this->roleTypes[ $usedOn ] ) )
		{
			$this->roleTypes[ $usedOn ] = array();
		}

		if ( count( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->roleRef ) )
		{
			$linkbaseRoleRefs = array();

			/*
				<roleRef xlink:type="simple" xlink:href="uk-gaap-pt-2004-12-01.xsd#restated" roleURI="http://www.xbrl.org/2003/frta/role/restated" />
				<labelLink xlink:type="extended" xlink:role="http://www.xbrl.org/2003/role/link">
			 */
			foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->roleRef as $roleRefKey => $roleRef )
			{
				$xlinkAttributes = $roleRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
				$roleRefAttributes = $roleRef->attributes();
				$roleUri = (string) $roleRefAttributes->roleURI;
				$roleRefHref = (string) $xlinkAttributes->href;

				// BMS 2015-04-26 This is neessary now linkbases defined in the schema document are supported
				// because a role reference to a type might be a relative reference to an id in the schema doc.
				$roleRefParts = explode( "#", $roleRefHref );
				if ( ! $roleRefParts )
				{
					$roleRefHref = $this->getTaxonomyXSD();
				}
				else if ( ! $roleRefParts[0] )
				{
					$roleRefParts[0] = $this->getTaxonomyXSD();
					$roleRefHref = implode( "#", $roleRefParts );
				}

				if ( XBRL::isValidating() )
				{
					// BMS 2018-04-26 This test works when the linkbases are in different files not so much when they are all in the schema document
					// if ( isset( $this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] ) )
					if ( isset( $linkbaseRoleRefs[ "$usedOn:$roleUri" ] ) )
					{
						$this->log()->taxonomy_validation( "3.5.2.4.5", "There MUST NOT be more than one roleRef element with the same @roleURI attribute value",
							array(
								'role' => $roleUri,
								'usedon' => $usedOn,
								'href' => $xml_basename,
							)
						);
					}
				}

				$linkbaseRoleRefs[ "$usedOn:$roleUri" ] = $roleRefHref;
				$this->linkbaseRoleTypes[ $xml_basename ][ "$usedOn:$roleUri" ] = $roleRefHref;

				$taxonomy = $this->getTaxonomyForXSD( $roleRefHref );
				if ( ! $taxonomy )
				{
					// Look for the taxonomy and include its contents in the DTS
					$xsd = $this->resolve_path( $this->getSchemaLocation(), $roleRefHref );

					$taxonomy = XBRL::withTaxonomy( strpos( $xsd, '#' ) ? strstr( $xsd, '#', true ) : $xsd );
					$taxonomy = $this->getTaxonomyForXSD( $roleRefHref );
					if ( ! $taxonomy )
					{
						if ( XBRL::isValidating() )
						{
							$this->log()->taxonomy_validation( "5.1.3.4", "The role taxonomy cannot be located",
								array(
									'role' => $roleUri,
									'href' => $roleRefHref,
									'linkbase' => 'labels',
								)
							);
						}
						continue;
					}
					$this->indirectNamespaces[] = $taxonomy->getNamespace();
					$taxonomy->AddUserNamespace( $this );
				}

				// This role MUST be defined as 'usedOn' in the linkbaseRef for link:labelLink
				if ( XBRL::isValidating() && count( $taxonomy->roleTypes ) )
				{
					if ( ! isset( $taxonomy->roleTypes['link:labelLink'][ $roleUri ] ) && ! isset( $taxonomy->roleTypes[ $usedOn ][ $roleUri ] ) )
					{
						$scheme = parse_url( $roleRefHref, PHP_URL_SCHEME );

						if ( ! in_array( $scheme, array( 'http', 'https' ) ) )
						{
							$this->log()->taxonomy_validation( "5.1.3.4", "This role is not defined to be used on the label linkbase",
								array(
									'role' => $roleUri,
								)
							);
						}
					}
				}

				if ( isset( $this->roleTypes['link:labelLink'][ $roleUri ] ) )
				{
					$this->context->labelLinkRoleRefs[ $roleUri ] = array(
						'type' => (string) $xlinkAttributes->type,
						'href' => XBRL::resolve_path( $linkbaseRef['href'], (string) $xlinkAttributes->href ),
						'roleUri' => $roleUri,
					);
				}

				if ( isset( $this->roleTypes['link:label'][ $roleUri ] ) )
				{
					$this->context->labelRoleRefs[ $roleUri ] = array(
						'type' => (string) $xlinkAttributes->type,
						'href' => XBRL::resolve_path( $linkbaseRef['href'], (string) $xlinkAttributes->href ),
						'roleUri' => $roleUri,
					);
				}
			}
		}

		if ( ! isset( $this->context->labelRoleRefs[ XBRL_Constants::$labelRoleLabel ] ) )
		{
			$this->context->labelRoleRefs[ XBRL_Constants::$labelRoleLabel ] = array(
				'type' => 'simple',
				'href' => XBRL::resolve_path( $linkbaseRef['href'], $this->getTaxonomyXSD() ),
				'roleUri' => XBRL_Constants::$labelRoleLabel,
			);
		}

		if ( count( $this->context->labelLinkRoleRefs ) === 0 )
		{
			$this->context->labelLinkRoleRefs[ XBRL_Constants::$defaultLinkRole ] = array(
				'type' => 'simple',
				'href' => XBRL::resolve_path( $linkbaseRef['href'], $this->getTaxonomyXSD() ),
				'roleUri' => XBRL_Constants::$defaultLinkRole,
			);
		}

		foreach ( $this->context->labelLinkRoleRefs as $roleRefsKey => $labelRoleRef )
		{
			// Find the label link with the same role
			// Note: the XBRL specification defines 'labelLink' but the XLink spec will allow for an element with any
			//		 local name as long as it has an attribute 'type' with the value 'extended'
			foreach ( $xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->labelLink as $linkKey => /* @var SimpleXMLElement $labelLink */$labelLink )
			{
				// Detection of duplicate from/to pairs only applies within an extended link so reset this varaible in each new link
				$this->resetValidateFromToArcPairs();

				$attributes = $labelLink->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
				if ( ! property_exists( $attributes, 'role' ) || (string) $attributes->role !== $roleRefsKey )
				{
					// The role MUST either be  the default link role or be one of the roles with a 'usedOn' value for the label link element
					if ( XBRL::isValidating() && (string) $attributes->role != XBRL_Constants::$defaultLinkRole && ! isset( $this->context->labelLinkRoleRefs[ (string) $attributes->role ] ) )
					{
					 	$this->log()->taxonomy_validation( "", "Label link with non-standard roles does not have a usedon defined in a role type",
					 		array(
					 			'role' => (string) $attributes->role
					 		)
					 	);
					}
					continue;
				}

				// Process the labels
				//
				// <label
				//		id="uk-gaap-pt_ProfitLossAccountReserve_lbl_3"
				//		xlink:type="resource"
				//		xlink:role="http://www.xbrl.org/2003/role/label"
				//		xlink:label="uk-gaap-pt_ProfitLossAccountReserve_lbl"
				//		xml:lang="en" xlink:title="UKD_profitAndLosAccountRes">
				//	Profit and loss account reserve
				// </label>

				// Begin by reading the locators which have this sort of form
				//
				// <loc
				//		xlink:href="../../pt/2004-12-01/uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_ProfitLossAccountReserve"
				//		xlink:label="uk-gaap-pt_ProfitLossAccountReserve"
				//		xlink:type="locator"
				//	/>

				// Note: The XBRL specification explicitly defines an element called 'loc' to be the locator.  However,
				//		 the XLink specification allows an element with any local name so long as it has an attribute
				//		 called 'type' with a value of 'locator'.
				$locators = array();
				$locators = $this->retrieveLocators( $labelLink, XBRL_Constants::$LabelLinkbaseRef, $linkbaseRef['href'] );
				// BMS 2018-04-16 For now, label locators are single dimension so re-org the $locators array which allows mutiple text values per label
				$locators = array_map( function( $refsArray ) { return $refsArray[0]; }, $locators );

				/**
				 * A list of the labels indexed by [$role][$lang][$label]
				 * @var array $labels
				 */
				$labels = array();
				/**
				 * A list of labels indexed by [$xml_basename][$id] where an id attribute is available
				 * @var array $labelsByHref
				 */
				$labelsByHref = array();
				/**
				 * A list of the labels indexed by the xlink:label attribute
				 * @var array $labelsByLabel
				 */
				$labelsByLabel = array();

				// Note: The XBRL specification explicitly defines an element called 'label' to be the label value.  However,
				//		 the XLink specification allows an element with any local name so long as it has an attribute
				//		 called 'type' with a value of 'resource'.
				foreach ( $labelLink->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->label as $labelKey => /* @var SimpleXMLElement $labelEl */ $labelEl )
				{
					$xlinkAttributes = $labelEl->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
					if ( ! $this->validateXLinkResourceAttributes( $xlinkAttributes, 'labels' ) )
					{
						continue;
					}

					$type = (string) $xlinkAttributes->type;
					if ( ! $this->validateXLinkResourceType( 'labels', $type ) )
					{
						continue;
					}

					$role = (string)$xlinkAttributes->role;
					if ( ! $role ) $role = XBRL_Constants::$labelRoleLabel;

					if ( ! isset( $this->context->labelRoleRefs[ $role ] ) )
					{
						$this->log()->warning( "The label role '$role' has not been defined in the schema" );
					}

					$labelLocator = (string) $xlinkAttributes->label;
					$this->validateXLinkLabel( 'labels', $labelLocator );

					$label = isset( $locators[ $labelLocator ] ) ? $locators[ $labelLocator ] : $labelLocator;

					$xmlAttributes = $labelEl->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML ] );
					$lang = property_exists( $xmlAttributes, 'lang' ) ? (string) $xmlAttributes->lang : $this->getDefaultLanguage();
					if ( XBRL::isValidating() )
					{
						$space = (string)$xmlAttributes->space;
						if ( $space && ! in_array( $space, array( 'default', 'preserve' ) ) )
						{
							$this->log()->taxonomy_validation( "XML", "The xml:space element can only contain the values 'default' or 'preserve'",
								array(
									'space' => "'$space'"
								)
							);
						}

						if ( $lang && ! preg_match( "/^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$/", $lang ) )
						{
							$this->log()->taxonomy_validation( "XML", "The xml:lang element can only contain values with this regular expression '[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*'",
								array(
									'lang' => "'$lang'"
								)
							);
						}
					}

					$text = $labelEl->__toString();

					if ( ! isset( $labels[ $role ] ) )
					{
						$labels[ $role ] = array();
					}

					if ( ! isset( $labels[ $role ][ $lang ] ) )
					{
						$labels[ $role ][ $lang ] = array();
					}

					$labels[ $role ][ $lang ][ $label ] = array(
						'text' => $text,
					);

					$byLabel = array(
						'text' => $text,
						'lang' => $lang,
						'role' => $role,
					);

					$labelsByLabel[ $label ][] =& $byLabel;

					$attributes = $labelEl->attributes();

					if ( ! property_exists( $attributes, 'id' ) )
					{
						unset( $byLabel );
						continue;
					}

					$id = (string) $attributes->id;
					$labels[ $role ][ $lang ][ $label ]['id'] = $id;
					$byLabel['id'] = $id;
					unset( $byLabel );

					if ( ! isset( $labelsByHref[ $xml_basename ] ) )
					{
						$labelsByHref[ $xml_basename ] = array();
					}

					if ( ! isset( $labelsByHref[ $xml_basename ][ $id ] ) )
					{
						$labelsByHref[ $xml_basename ][ $id ] = array();
					}

					$labelsByHref[ $xml_basename ][ $id ][] = array(
						'role' => $role,
						'label' => $label,
						'lang' => $lang,
					);
				}

				$arcs = array();

				/*
				 * Process the label arcs
				 * In the general case they look like this
				 *
				 *	<labelArc
				 *		priority="1"
				 *		use="prohibited"
				 *		xlink:arcrole="http://www.xbrl.org/2003/arcrole/concept-label"
				 *		xlink:from="uk-gaap-pt_ProfitLossAccountReserve"
				 *		xlink:to="uk-gaap-pt_ProfitLossAccountReserve_lbl_1-orig"
				 *		xlink:type="arc"
				 *	/>
				 *
				 * This is a curious arrangement and comes from the Companies House AE 2009 taxonomy:
				 *
				 *	uk-gaap-ae-2009-06-21-label.xml
				 *	<loc
				 *		xlink:href="uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_ProfitLossAccountReserve"
				 *		xlink:label="uk-gaap-pt_ProfitLossAccountReserve"
				 *		xlink:type="locator"
				 *	/>
				 *	<loc
				 *		xlink:href="uk-gaap-pt-2004-12-01-labels.xml#uk-gaap-pt_ProfitLossAccountReserve_lbl_0"
				 *		xlink:label="uk-gaap-pt_ProfitLossAccountReserve_lbl_0-orig"
				 *		xlink:type="locator"
				 *	/>
				 *	<labelArc
				 *		priority="1"
				 *		use="prohibited"
				 *		xlink:arcrole="http://www.xbrl.org/2003/arcrole/concept-label"
				 *		xlink:from="uk-gaap-pt_ProfitLossAccountReserve"
				 *		xlink:to="uk-gaap-pt_ProfitLossAccountReserve_lbl_0-orig"
				 *		xlink:type="arc"
				 *	/>
				 *
				 *	uk-gaap-pt-2004-12-01-labels.xml
				 *	<loc
				 *		xlink:type="locator"
				 *		xlink:href="uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_ProfitLossAccountReserve"
				 *		xlink:label="uk-gaap-pt_ProfitLossAccountReserve"
				 *	/>
				 *	<labelArc
				 *		xlink:type="arc"
				 *		xlink:arcrole="http://www.xbrl.org/2003/arcrole/concept-label"
				 *		xlink:from="uk-gaap-pt_ProfitLossAccountReserve"
				 *		xlink:to="uk-gaap-pt_ProfitLossAccountReserve_lbl"
				 *	/>
				 *  <label
				 *  	id="uk-gaap-pt_ProfitLossAccountReserve_lbl_0"
				 *  	xlink:type="resource"
				 *  	xlink:role="http://www.xbrl.org/2003/role/periodEndLabel"
				 *  	xlink:label="uk-gaap-pt_ProfitLossAccountReserve_lbl"
				 *  	xml:lang="en">Profit and loss account reserve, end of period
				 *  </label>
				 *
				 *	It seems unlikely that the two label arcs shown in this example are equivalent and, so, represent the same relationship
				 *	(see http://www.xbrl.org/Specification/XBRL-2.1/REC-2003-12-31/XBRL-2.1-REC-2003-12-31+corrected-errata-2013-02-20.html#_3.5.3.9.7.4)
				 *	but they do.
				 *
				 *	There are examples from two files here.  The first locator in each defines the label 'uk-gaap-pt_ProfitLossAccountReserve'
				 *	to reference the same concept.  This label forms the 'from' side of the xlink relationships defined by the <labelArc>
				 *	in each file.
				 *
				 *	The second locator in uk-gaap-ae-2009-06-21-label.xml references a label in uk-gaap-ae-2009-06-21-labels.xml file
				 *	by id.  This locator is referenced by 'label' 'uk-gaap-pt_ProfitLossAccountReserve_lbl_0-orig' which is the
				 *	label used as the 'to' side of the labelArc.
				 *
				 *	The 'label' of the <label> is uk-gaap-pt_ProfitLossAccountReserve_lbl.  This makes the 'to' part of the relationship.
				 *	Therefore, the 'to' of the <labelArc> in uk-gaap-ae-2009-06-21-label.xml is really the 'label' of the <label>.  This
				 *	makes the two labelArc equivalent.  As a consequence, both are excluded from the relatationship network.
				 */

				// Used to catch duplicated from/to label pairs which is not allowed by the XLink specification
				$fromToPairs = array();

				// Note: The XBRL specification explicitly defines an element calle 'labelArc' to be the arc.  However,
				//		 the XLink specification allows an element with any local name so long as it has an attribute
				//		 called 'type' with a value of 'arc'.
				foreach ( $labelLink->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->labelArc as $arcKey => /* @var SimpleXMLElement $labelArc */ $labelArc )
				{
					$xlinkAttributes = $labelArc->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );

					if ( ! $this->validateXLinkArcAttributes( $xlinkAttributes, 'labels' ) )
					{
						continue;
					}

					if ( isset( $fromToPairs[ (string)$xlinkAttributes->from ][ (string)$xlinkAttributes->to ] ) )
					{
						$this->log()->taxonomy_validation( "XLink", "Label arcs contain repeated from/to label pairs in the same extended link",
							array(
								'role' => $roleRefsKey,
								'from' => (string)$xlinkAttributes->from,
								'to' => (string)$xlinkAttributes->to,
								'linkbase' => $xml_basename
							)
						);
					}
					else
					{
						$fromToPairs[ (string)$xlinkAttributes->from ][ (string)$xlinkAttributes->to ] = 1;
					}

					$arcroleUri = (string) $xlinkAttributes->arcrole;
					if ( $arcroleUri != XBRL_Constants::$arcRoleConceptLabel )
					{
						if ( isset( $arcroleRefs[ $arcroleUri ] ) )
						{
							continue;
						}

						$this->log()->taxonomy_validation( "5.2.6.2", "The non-standard arcrole on the label arc has not been defined",
							array(
								'arcrole' => $arcroleUri,
							)
						);

						continue;
					}

					$fromLabel	= (string) $xlinkAttributes->from; // The label on the locator
					$this->validateXLinkLabel( 'labels', $fromLabel );

					if ( ! isset( $locators[ $fromLabel ] ) )
					{
						$href = basename( parse_url( $linkbaseRef['href'], PHP_URL_PATH ) );

						$this->log()->taxonomy_validation( "3.5.3.9.2", "The locator does not exist.  Review prior errors.",
							array(
								'linkbase' => $href,
								'from' => $fromLabel
							)
						);

						continue;
					}
					$from		= $locators[ $fromLabel ];
					$toLabel	= (string) $xlinkAttributes->to;   // The label on the <label>
					$this->validateXLinkLabel( 'labels', $toLabel );

					// OK, now the arcs have been read and anomolies addressed look for set
					// and make sure priorities and any prohibitions are taken into account

					$attributes = $labelArc->attributes();
					extract( $this->validateArcAttributes( 'labels', $fromLabel, $toLabel, $attributes ) );
					// $priority	= property_exists( $attributes, 'priority' ) ? (string) $attributes->priority	: "0";
					// $use			= property_exists( $attributes, 'use' ) 	 ? (string) $attributes->use		: "optional";
					// $order		= property_exists( $attributes, 'order' ) 	 ? (string) $attributes->order		: "1";

					// Create an arc for the 'to' component
					if ( ! isset( $arcs[ $from ] ) )
					{
						$arcs[ $from ] = array();
					}

					if ( ! isset( $arcs[ $from ][ $toLabel ] ) )
					{
						$arcs[ $from ][ $toLabel ] = array();
					}

					// Look to see if the 'to' label needs to be fixed.  This could happen if the locator points to a specific label.
					// If it does, then the priority and prohibited states need to be applied to label
					// Get the locator(s) for this 'to' if any exist.  There will be no locator if the $to value refers to a label in the current file.
					if ( isset( $locators[ $toLabel ] ) )
					{
						// It is an error for an arc to reference a locator unless it is probited (XBRL 2.1 5.2.2.3)
						if ( XBRL::isValidating() )
						{
							$error = false;
							if ( $use != 'prohibited' )
							{
								$details = array(
									"lineNo"	=> dom_import_simplexml( $labelArc )->getLineNo(),
									"file"		=> $linkbaseRef['href'],
									"arcLabel"	=> $toLabel,
									"arcHref"	=> $locators[ $toLabel ],
								);
								$this->log()->taxonomy_validation( "5.2.2.3", "Arc to remote resource not prohibited", $details );
								$error = true;
							}

							// A locator for the to label cannot be to a schema element
							// BMS 2019-01-25 So first check its a schema file

							$taxonomy = $this->getTaxonomyForXSD( $locators[ $toLabel ], false );
							if ( $taxonomy )
							{
								$this->log()->taxonomy_validation( "5.2.2.3", "The to attribute of a label arc cannot reference a schema concept",
									array(
										'to label' => "'$toLabel'",
										'to locator' => "'{$locators[ $toLabel ]}'",
										'file' => $linkbaseRef['href'],
									)
								);
								$error = true;
							}
							if ( $error ) continue;
						}

						$parts = parse_url( $locators[ $toLabel ] );
						$xsd = pathinfo( $parts['path'], PATHINFO_BASENAME ); // Could also be a linkbase xml file

						if ( ! isset( $this->context->labels[ XBRL_Constants::$defaultLinkRole ]['labelshref'][ $parts['path'] ] ) ) continue;
						$labelsByHref = $this->context->labels[ XBRL_Constants::$defaultLinkRole ]['labelshref'][ $parts['path'] ];

						if ( ! isset( $labelsByHref[ $parts['fragment'] ] ) ) continue;
						$hrefLabels = $labelsByHref[ $parts['fragment'] ];

						foreach ( $hrefLabels as $hrefLabel )
						{
							// Reconstruct the hash to reflect the reference to the label
							$hash = $this->equivalenceHash( 'labelArc','labelLink', $roleRefsKey, $from, "$xsd#{$hrefLabel['label']}{$hrefLabel['role']}{$hrefLabel['lang']}" );
							// $this->log()->info( $hash );
							// $this->log()->info( "'labelArc', 'labelLink', '$roleRefsKey', '$from', '$xsd#{$hrefLabel['label']}{$hrefLabel['role']}{$hrefLabel['lang']}'" );

							$arcs[ $from ][ $toLabel ][] = array(
								'label'		=> $toLabel,
								'priority'	=> $priority, // Set by extract() function so not visible in debugger until used
								'use'		=> $use, // Set by extract() function so not visible in debugger until used
								'role'		=> $hrefLabel['role'],
								'lang'		=> $hrefLabel['lang'],
								'hash'		=> $hash, // Hash is used to determine arc equivalence
							);
						}
					}
					else
					{
						$arcLables = $labelsByLabel[ $toLabel ];

						foreach ( $arcLables as $arcLabel )
						{
							// The $fromHref and $toHref used in this function should be the same ones used to define the respective label
							// It will be the xs/xml file and the to/from label as a fragment
							// $hash = $this->equivalenceHash( 'labelArc', 'labelLink', $roleRefsKey, $from, "$xml_basename#$toLabel" );
							$hash = $this->equivalenceHash( 'labelArc', 'labelLink', $roleRefsKey, $from, "$xml_basename#$toLabel{$arcLabel['role']}{$arcLabel['lang']}" );

							$arcs[ $from ][ $toLabel ][] = array(
									'label' => $toLabel,
									'priority' => $priority, // Set by extract() function so not visible in debugger until used
									'use' => $use, // Set by extract() function so not visible in debugger until used
									'role' => $arcLabel['role'],
									'lang' => $arcLabel['lang'],
									'hash' => $hash, // Hash is used to determine arc equivalence
							);
						};
					}

				}

				unset( $labelsByLabel );

				// Check to see if any of the arcs already exist. They may have been
				// defined in another extended link perhaps in a different linkbase.
				// If they do this can cause a problem so they should be made unique

				foreach ( $arcs as $from => $to )
				{
					foreach ( $to as $toLabel => $details )
					{
						// BMS 2019-05-13	Applying this change to ALL labels because the same label might be used
						//					in different linkbases (for example, see DK IFRS 2016 label_newItem
						//					which is used in four linkbases
						// if ( ! isset( $this->context->labels[ $roleRefsKey ]['arcs'][ $from ][ $toLabel ] ) ) continue;

						// A label with this combination already exists so this $toLabel needs
						// to be made unique or it will cause problem accessing the label later
						// Doing so means the corresponding labels need to change

						// The label should be unique within an extended link so generating a hash
						// that includes the extended link role and the linkbase file name should
						// be good enough
						// $newLabel = "{$toLabel}_" . hash( "SHA256", "$roleRefsKey$xml_basename" );
						// BMS 2019-05-13	See comment above with the same date
						//					Using MD5 because it generates a 32 char hash and its good enough
						$newLabel = hash( "MD5", "{$toLabel}_{$roleRefsKey}_{$xml_basename}" );

						// if ( $roles )
						// {
						// Look for labels with these characteristics
						foreach ( $details as $index => $detail )
						{
							if ( ! isset( $labels[ $detail['role'] ][ $detail['lang'] ][ $toLabel ] ) )
							{
								continue;
							}

							$existing = $labels[ $detail['role'] ][ $detail['lang'] ][ $toLabel ];
							unset( $labels[ $detail['role'] ][ $detail['lang'] ][ $toLabel ] );
							$labels[ $detail['role'] ][ $detail['lang'] ][ $newLabel ] = $existing;
						}
						// }

						// Now change this arc label
						$arc = $arcs[ $from ][ $toLabel ];
						unset( $arcs[ $from ][ $toLabel ] );
						$arcs[ $from ][ $newLabel ] = $arc;
					}
				}

				// Now there is a set of locators, arcs, labels and $labelsByHref to store in the context
				$this->context->addLabels( $locators, $arcs, $labels, $labelsByHref, $roleRefsKey );

				// Keep a copy in the extension instance so they can be recovered easily
				if ( $this->context->isExtensionTaxonomy() )
				{
					$this->labels = array(
						// BMS 2018-04-27 Change to allow custom link roles
						// XBRL_Constants::$defaultLinkRole => array(
						$roleRefsKey => array(
							'locators' => $locators,
							'arcs' => $arcs,
							'labels' => $labels,
							'labelshref' => $labelsByHref,
						)
					);
				}

				// break;
			}
		}

		return true;
	}

	/**
	 * Create an array of elements from the schema document
	 * @return void
	 */
	private function indexComplexTypes()
	{
		// $this-log()->info( "indexComplexTypes" );

		foreach ( $this->xbrlDocument->complexType as $nodeKey => $node )
		{
			$name = (string) $node['name'];

			$element = array(
				'name' => $name,
				'content' => $node->children()->asXml(),
			);

			$this->complexTypes[ $name ] =& $element;
		}
	}

	/**
	 * Retrieve the element which is the head of the substitution group (with xbrli:item) or false
	 * @param array $element An array representing the current item
	 * @param XBRL_Types $types A reference to the current types
	 * @return array|false
	 */
	private function getAncestorElement( $element, $types )
	{
		// May be this is already OK
		if ( in_array( $element['substitutionGroup'], XBRL_Constants::$xbrliSubstitutionHeads ) )
		{
			return $element;
		}

		// Look for the type with this substitution group
		$typeElement = $types->getElement( $element['substitutionGroup'] );
		if ( ! $typeElement )
		{
			return false; // Oops
		}

		// Get the corresponding taxonomy
		$taxonomy = $this->context->getTaxonomyWithPrefix( $typeElement['prefix'] );
		if ( ! $taxonomy ) return false;

		// Get the corresponding taxonomy element
		$element = $taxonomy->getElementByName( $typeElement['name'] );
		return $this->getAncestorElement( $element, $types );
	}

	/**
	 * Return an element with attributes updated if necessary
	 *
	 * @param array $element An array representing the current item
	 * @param XBRL_Types $types A reference to the current types
	 * @return array Either the descendant array with any missing pieces or the descendant array unchanged
	 */
	private function getCompleteDescendantElement( $element, $types )
	{
		$ancestor = $this->getAncestorElement( $element, $types );
		if ( ! $ancestor )
		{
			if ( XBRL::isValidating() )
			{
				$this->log()->taxonomy_validation( "5.1", "Unable to locate the ancestor element for the derived element",
					array(
						'concept' => $element['name'],
					)
				);
			}
			return $element;
		}
		else
		{
			if ( XBRL_Instance::isEmpty( $element, 'type' ) ) unset( $element['type'] );
			if ( XBRL_Instance::isEmpty( $element, 'substitutionGroup' ) ) unset( $element['substitutionGroup'] );
			return array_merge( $ancestor, $element );
		}
	}

	/**
	 * Create an array of elements from the schema document
	 * @param bool $forceNodeProcessing Set to make sure element types are processed.  Typically this will only be set
	 * 									true after *including* another schema because the namespace is the same.
	 * @return void
	 */
	private function indexElements( $forceNodeProcessing )
	{
		$types = $this->context->types;
		$isTaxonomySchema = $this->xbrlDocument->getName() == 'schema' && ! isset( XBRL_Constants::$standardPrefixes[ $this->getPrefix() ] );

		if ( $forceNodeProcessing || ! $types->hasProcessedSchema( $this->prefix ) || ! $types->getPrefixForNamespace( $this->getNamespace() ) )
		{
			$types->processNode( $this->xbrlDocument, $this->xbrlDocument->getName(), $this->getPrefix(), true );
		}

		foreach ( $this->xbrlDocument->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] ) as $nodeKey => $node )
		{
			/** @var \SimpleXMLElement $node */

			if ( $nodeKey == 'redefine' )
			{
				if ( XBRL::isValidating() )
				$this->log()->taxonomy_validation( "5.1.5", "The 'redefine' tag is prohibited in XBRL schema taxonomies", array() );
				continue;
			}

			if ( $nodeKey == 'element' )
			{
				$attributes = $node->attributes();

				$id = (string) $attributes['id'];
				$name = (string) $attributes['name'];

				if ( empty( $id ) )
				{
					$id = $name;
				}
				else if ( empty( $name ) )
				{
					$name = $id;
				}

				$element = array(
					'id' => $id,
					'name' => $name,
					'type' => (string) $attributes['type'],
					'substitutionGroup' => $this->normalizePrefix( (string) $attributes['substitutionGroup'], $this ),
					'abstract' => (string) $attributes['abstract'] === 'true' ? 1 : 0,
					'nillable' => (string) $attributes['nillable'] === 'true' ? 1 : 0,
				);

				$this->indexXDT( $types, $node, $element );
				$this->indexCustom( $types, $node, $element );

				if ( $isTaxonomySchema )
				{
					$element['node'] = $node; // Keep this temporarily
					// $element['nodeKey'] = $nodeKey; // Keep this temporarily
				}

				$xbrliAttributes = $node->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] );

				if ( property_exists( $xbrliAttributes, 'periodType' ) )
				{
					$element['periodType'] = (string) $xbrliAttributes['periodType'];
				}

				if ( property_exists( $xbrliAttributes, 'balance' ) )
				{
					$element['balance'] = (string) $xbrliAttributes['balance'];
				}

				$this->elementIndex[ $id ] = $element;

			}
		}

		$taxonomy_namespace = $this->getNamespace();
		// Its possible that when multiple versions of a taxonomy are loaded that the
		// same prefix will appear.  Assume the first loaded is the correct one to use.
		if ( ! $types->hasProcessedSchema( $this->prefix ) )
		{
			$types->setProcessedSchema( $this->prefix, $taxonomy_namespace );
		}
	}

	/**
	 * Process the current node to extract XDT attributes that exist
	 * @param XBRL_Types $types
	 * @param SimpleXMLElement $node The current node
	 * @param array $element
	 */
	public function indexCustom( &$types, &$node, &$element )
	{
		foreach ( $this->documentPrefixes as $prefix => $namespace )
		{
			// Ignore standard namespaces
			// BMS 2018-04-09 Fixing the kluge
			// if ( $prefix == "xs" ) $prefix = "xsd";
			if ( empty( $prefix ) || isset( \XBRL_Constants::$standardPrefixes[ $prefix ] ) )
			{
				continue;
			}

			// Look for attributes with a non-standard prefix
			foreach ( $node->attributes( $namespace ) as $key => $attribute )
			{
				$element['custom'][ "$prefix:$key" ] = (string)$attribute;
			}
		}
	}

	/**
	 * Process the current node to extract XDT attributes that exist
	 * @param XBRL_Types $types
	 * @param SimpleXMLElement $node The current node
	 * @param array $element
	 */
	public function indexXDT( &$types, &$node, &$element )
	{
		$xbrldtAttributes = $node->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDT ] );
		if ( property_exists( $xbrldtAttributes, 'typedDomainRef' ) )
		{
			$element['typedDomainRef'] = (string) $xbrldtAttributes->typedDomainRef;
		}

		if ( ! empty( $element['substitutionGroup'] ) )
		{
			$id = $element['id'];
			$name = $element['name'];

			if ( $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) ) )
			{
				$this->elementDimensions[] = $name;
			}
			else if ( $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrldtHypercubeItem ) ) )
			{
				$this->elementHypercubes[] = $name;
			}
			else if ( $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xlArc ) ) )
			{
				$this->elementArcTypes[] = $name;
			}
			else if ( $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xlExtended ) ) )
			{
				$this->elementLinkTypes[] = $name;
			}
			// else if ( $element['type'] == XBRL_Constants::$enumItemType || $element['type'] == XBRL_Constants::$enumSetItemType )
			else if ( $types->resolvesToBaseType( $element['type'], array( XBRL_Constants::$enumItemType, XBRL_Constants::$enumSetItemType ), false ) )
			{
				// Record that this element is an extensible enumeration concept
				$this->enumerations[  $id ] = $id;

				// Make sure there is a linkrole and domain attribute
				$enumAttributes = $node->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_ENUMERATIONS ] );

				if ( property_exists( $enumAttributes, 'domain' ) )
				{
					$element['enumDomain'] = (string)$enumAttributes['domain'];
				}
				else
				{
					if ( $this->isValidating() )
					{
						$this->log()->taxonomy_validation( "Extensible enumerations", "The extensible concept does not include a domain",
							array(
								'id' => $id,
								'name' => $name,
								'type' => $element['substitutionGroup'],
								'error' => 'enumte:MissingDomainError'
							)
						);
					}
				}

				if ( property_exists( $enumAttributes, 'linkrole' ) )
				{
					$element['enumLinkrole'] = (string)$enumAttributes['linkrole'];
				}
				else
				{
					if ( $this->isValidating() )
					{
						$this->log()->taxonomy_validation( "Extensible enumerations", "The extensible concept does not include a linkrole",
							array(
								'id' => $id,
								'name' => $name,
								'type' => $element['substitutionGroup'],
								'error' => 'enumte:MissingLinkRoleError'
							)
						);
					}
				}

				$element['enumHeadUsable'] = property_exists( $enumAttributes, 'headUsable' )
					? filter_var( $enumAttributes['headUsable'], FILTER_VALIDATE_BOOLEAN )
					: false;
			}
		}

	}

	/**
	 * Validate the XDT features of the schema
	 *
	 * @param XBRL_Types $types
	 * @param string $id
	 * @param string $name
	 * @param array $element
	 */
	public function validateXDTElements( &$types, $id, $name, $element)
	{
		if ( isset( $element['substitutionGroup'] ) )
		{
			// Dimensional items MUST abstract
			if ( $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrldtHypercubeItem ) ) )
			{
				// If $element[substitutionGroup] is not xbrldt:hypercubeItem make sure any missing attributes are available
				$element = $this->getCompleteDescendantElement( $element, $types );

				if ( ! isset( $element['abstract'] ) || ! $element['abstract'] )
				{
					$this->log()->dimension_validation(
						"2.2.1",
						"An element that is in the substitution group of the xbrldt:hypercubeItem element is not abstract.",
						array(
							'name' => $name,
							'schema' => $this->getSchemaLocation(),
							'error' => 'xbrldte:HypercubeElementIsNotAbstractError',
						)
					);
				}
			}
			else if ( $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) ) )
			{
				// If $element[substitutionGroup] is not xbrldt:dimensionItem make sure any missing attributes are available
				$element = $this->getCompleteDescendantElement( $element, $types );

				if ( ! isset( $element['abstract'] ) || ! $element['abstract'] )
				{
					$this->log()->dimension_validation(
						"2.5.1",
						"An element that is in the substitution group of the xbrldt:dimensionItem element is not abstract.",
						array(
							'name' => $name,
							'schema' => $this->getSchemaLocation(),
							'error' => '',
						)
					);
				}
			}
		}

		// Check the typedDomainRef attribute if one exists
		if ( isset( $element['typedDomainRef'] ) )
		{
			if ( ! $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) ) )
			{
				$this->log()->dimension_validation(
					"2.5.1",
					"An element with a typedDomainRef attribute MUST be in the substitution group xbrldt:dimensionalItem",
					array(
						'name' => $name,
						'schema' => $this->getSchemaLocation(),
						'error' => 'xbrldte:TypedDomainRefError',
					)
				);
			}

			$parts = explode( "#", $element['typedDomainRef'] );
			if ( count( $parts ) != 2 )
			{
				$this->log()->dimension_validation(
					"2.5.2.1.1",
					"The typedDomainRef value is not a valid URI.",
					array(
						'typedDomainRef' => "'{$element['typedDomainRef']}'",
						'schema' => $this->getSchemaLocation(),
						'error' => 'xbrldte:TypedDimensionURIError',
					)
				);
			}
			else
			{
				$taxonomy = $this;

				// If there is a domain in the reference it must be part of the DTS
				if ( ! empty( $parts[0] ) )
				{
					$taxonomy = $this->getTaxonomyForXSD( $parts[0] );

					if ( ! $taxonomy )
					{
						$this->log()->dimension_validation(
							"2.5.2.1.1",
							"The typedDomainRef references a domain that is not part of the DTS",
							array(
								'typedDomainRef' => "'{$element['typedDomainRef']}'",
								'schema' => $this->getSchemaLocation(),
								'error' => 'xbrldte:OutOfDTSSchemaError',
							)
						);
					}
				}

				if ( empty( $parts[1] ) )
				{
					$this->log()->dimension_validation(
						"2.5.2.1.1",
						"The typedDomainRef value does not include a fragment",
						array(
							'typedDomainRef' => "'{$element['typedDomainRef']}'",
							'schema' => $this->getSchemaLocation(),
							'error' => 'xbrldte:TypedDimensionURIError',
						)
					);
				}
				else if ( $taxonomy )
				{
					// Check to see that the fragment is valid
					$typedDomainElement = $taxonomy->getElementById( $parts[1] );

					if ( ! $typedDomainElement )
					{
						// Check to see if this is missing or to a type
						$type = $types->getTypeById( $parts[1], $this->getPrefix() );

						if ( $type )
						{
							$this->log()->dimension_validation(
								"2.5.2.1.1",
								"The typedDomainRef references a type defintition",
								array(
									'typedDomainRef' => "'{$element['typedDomainRef']}'",
									'schema' => $this->getSchemaLocation(),
									'error' => 'xbrldte:TypedDimensionError',
								)
							);
						}
						else
						{
							$this->log()->dimension_validation(
								"2.5.2.1.1",
								"The typedDomainRef references a domain that is not part of the DTS",
								array(
									'typedDomainRef' => "'{$element['typedDomainRef']}'",
									'schema' => $this->getSchemaLocation(),
									'error' => 'xbrldte:OutOfDTSSchemaError',
								)
							);
						}

					} else if ( isset( $typedDomainElement['abstract'] ) && $typedDomainElement['abstract'] )
					{
						$this->log()->dimension_validation(
							"2.5.2.1.1",
							"The typedDomainRef reference is to an abstract element",
							array(
								'typedDomainRef' => "'{$element['typedDomainRef']}'",
								'schema' => $this->getSchemaLocation(),
								'error' => 'xbrldte:TypedDimensionError',
							)
						);
					}
				}

			}
		}

	}

	/**
	 * Iterates over arcs in any presentation linkbases and locates any preferred role labels or reports an error
	 * This function is provided to validate XBRL 2.1 conformance suite tests 299 (preferredLabel.xml)
	 */
	private function validatedPresentationLinkbasePreferredLabels()
	{
		// This test is only needed by the XBRL 2.1 conformance suite test 299
		if ( ! defined( 'CONFORMANCE_TEST_SUITE_XBRL_21') ) return;

		$roleRefs = $this->getPresentationRoleRefs();
		$step = 0;
		// Iterate over all the role refs
		foreach ( $roleRefs as $roleUri => $roleRef )
		{
			$step = "1 - $roleUri";
			// Traverse all the nodes to look for any with a preferred label
			self::processAllNodes( $roleRef['hierarchy'], function( $node, $id ) use( $roleRef, $roleUri )
			{
				// To distinguish presentation hiearchy nodes that are periodStart or periodEnd but have the same id
				// (such as uk-gaap-pt-2004-12-01.xsd#uk-gaap-pt_NetDebtFunds) the process of creating the hierarchy
				// generates two ids with the 'periodEndLabel' or 'periodStartLabel'.  It also adds these to the
				// element index of the respective taxonomy (uk-gaap-pt-2004-12-01.xsd in this case).  Because there
				// will be no label for a manufactured id (such as uk-gaap-pt_NetDebtFundsperiodEndLabel) it's
				// necessary to lookup the manufactured element in the respective taxonmy and use the id of the element
				// array that's returned.
				$nodeTaxonomy = $this->getTaxonomyForXSD($node['label']);
				$nodeElement = $nodeTaxonomy->getelementById($node['label']);
				$nodeLabel = "{$nodeTaxonomy->getTaxonomyXSD()}#{$nodeElement['id']}";

				if ( ! isset( $node['preferredLabel'] ) ) return true;

				$step = "2 - {$node['preferredLabel']}";

				// The label should have an arc
				// BMS 2018-05-08 Labels use the default link role
				// if ( isset( $this->context->labels[ $roleUri ]['arcs'][ $node['label'] ] ) )
				// if ( isset( $this->context->labels[ \XBRL_Constants::$defaultLinkRole ]['arcs'][ $node['label'] ] ) )
				if ( isset( $this->context->labels[ \XBRL_Constants::$defaultLinkRole ]['arcs'][ $nodeLabel ] ) )
				{
					$step = "3 - {$node['label']}";

					// Create a shortcut to a sub-element
					// $labels =& $this->context->labels[ $roleUri ];
					$labels =& $this->context->labels[ \XBRL_Constants::$defaultLinkRole ];

					// Check the preferred label exists
					if ( isset( $labels['labels'][ $node['preferredLabel'] ] ) )
					{
						$step = "4";

						// Now check there is a preferred label for the arc target
						// foreach ( $labels['arcs'][ $node['label'] ] as $targetLabel => $details )
						foreach ( $labels['arcs'][ $nodeLabel ] as $targetLabel => $details )
						{
							$step = "5 - {$targetLabel}";
							// and locate the label in any lanuage
							foreach ( $labels['labels'][ $node['preferredLabel'] ] as $lang => $labelLabel )
							{
								$step = "6 - {$lang}";

								// If it exists then
								if ( isset( $labelLabel[ $targetLabel ] ) )
								{
									return true;
								}
							}
						}
					}
				}

				$this->log()->taxonomy_validation( "5.2.4.2.1", "A presentation arc preferred label role assigment is not defined as a label",
					array(
						'preferred label' => $node['preferredLabel'],
						'arc to' => $node['label']
					)
				);

			} );
		}
	}

	/**
	 * Validate the taxonomy following the XBRL 2.1 specification
	 * However while this function does validate elements it is really a second pass over the elements
	 * to continue processing them by, for example, pulling out the tuple elements
	 */
	public function validateTaxonomy21()
	{
		if ( XBRL::isValidating() )
		{
			$this->validateAppInfo();
			$this->validatedPresentationLinkbasePreferredLabels();
		}

		// BMS 2018-04-20 This test was too simplistic after adding the many prefixes to support formulas
		// $isTaxonomySchema = $this->xbrlDocument->getName() == 'schema' && ! isset( XBRL_Constants::$standardPrefixes[ $this->getPrefix() ] );
		$isTaxonomySchema = $this->xbrlDocument->getName() == 'schema' && ! ( isset( XBRL_Constants::$standardPrefixes[ $this->getPrefix() ] ) && XBRL_Constants::$standardPrefixes[ $this->getPrefix() ] == $this->getNamespace() );

		$types = $this->context->types;

		foreach ( $this->elementIndex as $elementKey => &$element )
		{
			$node = &$element['node'];
			unset( $element['node'] );

			// If not validating then at least the SimpleXMLNode references need to be removed
			if ( /* ! XBRL::isValidating() || */ ! $isTaxonomySchema )
			{
				continue;
			}

			$id = isset( $element['id'] ) ? $element['id'] : "";
			$name = isset( $element['name'] ) ? $element['name'] : "";

			if ( ! empty( $element['substitutionGroup'] ) ) // Only applies when a substitution group is expressed
			{
				// Try resolving the group using type information but element information may be missing
				if ( XBRL::isValidating() && ! $types->resolveToSubstitutionGroup( $element['substitutionGroup'], XBRL_Constants::$xbrliSubstitutionHeads ) )
				{
					// If the type is not one of the XBRL base types then ignore it
					// BMS 2018-04-09 Test candidates changed.
					if ( ! $types->resolvesToBaseType( strpos( $element['type'], ":" ) ? $element['type'] : "xs:{$element['type']}" , $types->xbrlItemTypeNames() ) )
					{
						continue;
					}

					$this->log()->taxonomy_validation(
						"4.6",
						"The substitution group of concepts MUST lead to xbrli:item or xbrli:tuple ",
						array(
							'name' => $name,
							'schema' => $this->getSchemaLocation(),
						)
					);
				}

				if ( $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrliItem ) ) )
				{
					// If $element[substitutionGroup] is not xbrli:item OR xbrldt:dimensionItem make sure any missing attributes are available
					if ( ! in_array( $element['substitutionGroup'], array( XBRL_Constants::$xbrliItem ) ) )
					{
						$element = $this->getCompleteDescendantElement( $element, $types );
					}

					if ( XBRL::isValidating() && XBRL_Instance::isEmpty( $element, 'periodType' ) )
					{
						$this->log()->taxonomy_validation(
							"5.1.1.1",
							"A period type attribute MUST exist on concepts in the xbrli:item substitution group",
							array(
								'concept' => $name,
								'schema' => $this->getSchemaLocation(),
							)
						);
					}

					if ( XBRL::isValidating() && ! XBRL_Instance::isEmpty( $element, 'balance' ) )
					{
						// Check the balance is credit or debit
						if ( ! in_array( $element['balance'], array( 'credit', 'debit' ) ) )
						{
							$this->log()->taxonomy_validation(
								"5.1.1.2",
								"The value of a balance attribute MUST be either 'credit' or 'debit'",
								array(
									'concept' => $name,
									'value' => $element['balance'],
								)
							);
						}

						// Check the items with a balance attribute has a type that is or is decended from monetaryItemType
						if ( ! isset( $element['type'] ) || ! $types->resolvesToBaseType( $element['type'], array( XBRL_Constants::$xbrliMonetaryItemType ) ) )
						{
							$this->log()->taxonomy_validation(
								"5.1.1.2",
								"The type of a balance attribute MUST be or be derived from 'monetaryItemType'",
								array(
									'concept' => $name,
									'type' => $element['type'],
								)
							);
						}
					}

					// The type must be simple, or complex and derived from fractionItemType
					$type = $types->getType( $element['type'] );
					if ( XBRL::isValidating() )
					{
						if ( $type )
						{
							if ( isset( $type['class'] ) )
							{
								if ( $type['class'] == 'complex' && ! ( $type['name'] == 'fractionItemType' &&  $type['prefix'] == 'xbrli' ) )
								{
									// If its complex then the type MUST be derived by restriction from fractionItemType
									$this->log()->taxonomy_validation(
										"5.1.1.3",
										"Items should only use simple types unless derived by restriction from fractionItemType",
										array(
											'concept' => $id,
											'type' => $element['type'],
										)
									);
								}
							}
							else
							{
								// BMS 2018-04-23 Why is this necessary?  XBRL 2.1 conformance test 160 V-05 fails because of this for no obviously good reason.
								// $this->log()->taxonomy_validation(
								// 	"1.4",
								// 	"Unable to find the class for the type",
								// 	array(
								// 		'type' => $element['type'],
								// 	)
								// );
							}
						}
						else
						{
							$this->log()->taxonomy_validation(
								"1.4",
								"Unable to find the type for an item concept",
								array(
									'concept' => $id,
								)
							);
						}
					}
				}
				// According to the XML schema specification an existing global element IS a substitution group.
				// If it exists then it has already been checked so it cam be assumed it already leads to one of the permitted types
				else if ( $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrliTuple ) ) )
				{
					$refs = array();
					$permittedTypes = array();

					if ( XBRL::isValidating() && $node )
					{
						$this->validateLegalAttributes( $node );
					}

					if ( XBRL::isValidating() && ! XBRL_Instance::isEmpty( $element, 'periodType' ) )
					{
						// Tuples should not have a period type
						$this->log()->taxonomy_validation(
							"5.1.1.1",
							"A period type attribute MUST NOT exist on concepts in the xbrli:tuple substitution group",
							array(
								'schema' => $this->getTaxonomyXSD(),
								'concept' => $name,
							)
						);
					}

					if ( XBRL::isValidating() && ! XBRL_Instance::isEmpty( $element, 'balance' ) )
					{
						// Tuples should not have a balance
						$this->log()->taxonomy_validation(
							"5.1.1.1",
							"A balance attribute MUST NOT exist on concepts in the xbrli:tuple substitution group",
							array(
								'schema' => $this->getTaxonomyXSD(),
								'concept' => $name,
							)
						);
					}

					// Get the definition of the node
					$definition = $types->getElement( $name, $this->getPrefix() );
					if ( ! $definition )
					{
						if ( XBRL::isValidating() )
						$this->log()->taxonomy_validation(
							"4.9",
							"Unable to locate the schema element for tuple",
							array(
								'schema' => $this->getTaxonomyXSD(),
								'name' => $name,
							)
						);

						continue;
					}

					// Use the types in the definition to fill out the tuple element details
					if ( empty( $definition['types'] ) )
					{
						$permittedTypes[] = XBRL_Constants::$xbrliItem;
					}
					else
					{
						foreach ( $definition['types'] as $type )
						{
							if ( is_string( $type ) )
							{
								$type = $types->getType( $type );
							}

							if ( is_array( $type ) )
							{
								$me =& $this;
								$fn = function( $type ) use( &$fn, &$me, &$types, &$permittedTypes, &$refs, $name )
								{
									$result = false;

									// Check that any xbrli attributes do not have attributes themselves.
									if ( isset( $type['attributes'] ) )
									foreach ( $type['attributes'] as $qname => $attribute )
									{
										// If there is a prefix then see if it is one of the XBRL ones
										if ( strpos( $qname, ":" ) !== false && in_array( strstr( $qname, ":", true ), array_keys( XBRL_Constants::$standardPrefixes ) ) )
										// if ( ! XBRL::startsWith( $qname, STANDARD_PREFIX_XBRLI ) ) continue;
										// The attribute begins with 'xbrli' so it cannot exist in a tuple definition

										$this->log()->taxonomy_validation( "4.9", "Attributes assigned to tuples should not be ones defined in XBRL specification namespaces",
											array(
												'name' => $qname,
											)
										);
									}

									// A complex type definition may not have any elements directly under it
									// in which case the validations regarding global elements do not apply
									// and this flag captures whether or not there are elements to test
									$hasElements = false;

									foreach ( array( 'elements', 'sequence', 'choice', 'group' ) as $typeComponent )
									{
										if ( isset( $type[ $typeComponent ] ) )
										{
											$hasElements |= $typeComponent == 'elements';

											if ( XBRL::isValidating() && isset( $type['mixed'] ) && $type['mixed'] )
											{
												XBRL_Log::getInstance()->taxonomy_validation( "4.9", 'Tuple types MUST NOT include mixed content',
													array(
														'schema' => $me->getTaxonomyXSD(),
														'concept' => $name,
													)
												);
											}

											if ( XBRL::isValidating() && isset( $type['class'] ) && $type['class'] == 'simple' )
											{
												XBRL_Log::getInstance()->taxonomy_validation( "4.9", 'Tuple types MUST NOT include simple content',
													array(
														'schema' => $me->getTaxonomyXSD(),
														'concept' => $name,
													)
												);
											}

											// If there is a restriction then dig out the type
											$base = isset( $type['base'] )
												? $type['base']
												: null;

											$baseType = $base
												? $types->getType( $base )
												: null;

											$contentType = isset( $type['contentType'] )
												? $type['contentType']
												: 'extension'; // Assume extension which is the more forgiving option but still a hack

											$elements = array();
											$basetypeElements = null;

											foreach ( $type[ $typeComponent ] as $id => $details )
											{
												if ( $id == 'restrictionType' ) continue;

												if ( $typeComponent == 'sequence' && $id == 'elements' )
												{
													if ( $fn( $type[ $typeComponent ] ) ) continue;
												}
												else if ( $fn( $details ) ) continue;

												// BMS 2018-04-14	Added the condition to exclude complex types from further checking
												//					as any element definitions should now be in the $refs array
												$isComplex = isset( $type['class'] ) && $type['class'] == 'complex';

												if ( ! $hasElements && $isComplex ) continue;

												if ( XBRL::isValidating() )
												{
													$isGlobal = isset( $details['global'] ) && $details['global'];

													if ( ! $isGlobal )
													{
														// Tuple sub-elements cannot be anonymous
														XBRL_Log::getInstance()->taxonomy_validation( "4.9", "Tuple sub-elements cannot be anonymous (they must have top-level instance)",
															array(
																'schema' => $me->getTaxonomyXSD(),
																'concept' => $name,
																'element' => $details['name'],
															)
														);
													}

													// Tuple sub-elements must be global, have a substitutionGroup and the group must resolve to item or tuple or the type *is* item or tuple
													if ( ! isset( $details['substitutionGroup'] ) ||
														 ! $types->resolveToSubstitutionGroup( $details['substitutionGroup'], XBRL_Constants::$xbrliSubstitutionHeads )
													)
													{
														if ( ! in_array( "{$details['prefix']}:{$details['name']}", XBRL_Constants::$xbrliSubstitutionHeads ) )
														{
															XBRL_Log::getInstance()->taxonomy_validation( "4.9", "Tuple sub-elements must be global, have a substitutionGroup and the group must resolve to item or tuple",
																array(
																	'schema' => $me->getTaxonomyXSD(),
																	'concept' => $name,
																)
															);
														}
													}

													if ( isset( $details['substitutionGroup'] ) &&
														 ( ! isset( $details['global'] ) || ! $details['global'] )
													)
													{
														XBRL_Log::getInstance()->taxonomy_validation( "4.9", 'Substitution groups are only valid on global element definitions',
															array(
																'schema' => $me->getTaxonomyXSD(),
																'name' => $details['name'],
																'concept' => $name,
															)
														);
													}
												}

												// If there is a base type and the base type is a type defined in this
												// taxonomy validate that the these members are members of the base type.
												if ( $baseType && $baseType['prefix'] == $me->getPrefix() )
												{
													if ( is_null( $basetypeElements ) )
													{
														$basetypeElements = $types->gatherElementsFromType( $baseType );
													}

													if ( $basetypeElements )
													{
														// If the $contentType is 'restriction' then the detail is retricted to the
														// members in $typeElements

														switch ( $contentType )
														{
															case 'restriction':

																if ( XBRL::isValidating() && ! isset( $basetypeElements[ "{$details['prefix']}:{$details['name']}" ] ) )
																{
																	XBRL_Log::getInstance()->taxonomy_validation( "schema", "The type does not match the type restriction",
																		array(
																			'container' => "'{$type['name']}'",
																			'type' => "'{$details['prefix']}:{$details['name']}'",
																			'valid types' => "'" . join( "', '", array_keys( $basetypeElements ) ) . "'",
																		)
																	);
																}

																break;

															case 'extension':

																// In this case the $typeElements are added to the base types
																$basetypeElements = $basetypeElements + $types->gatherElementsFromType( "{$type['prefix']}:{$type['name']}" );

																if ( XBRL::isValidating() && ! isset( $basetypeElements[ "{$details['prefix']}:{$details['name']}" ] ) )
																{
																	XBRL_Log::getInstance()->taxonomy_validation( "schema", "The type does not match the type extension",
																		array(
																			'container' => "'{$type['name']}'",
																			'type' => "'{$details['prefix']}:{$details['name']}'",
																			'valid types' => "'" . join( "', '", array_keys( $basetypeElements ) ) . "'",
																		)
																	);
																}

																break;

														}
													}
												}

												if ( $details['abstract'] )
												{
													$permittedTypes[] = "{$details['prefix']}:{$details['name']}";
												}
												else
												{
													$ref = array(
														'name' => $details['name'],
														'namespace' => $me->getNamespaceForPrefix( $details['prefix'] ),
													);

													if ( isset( $details['minOccurs'] ) )
													{
														$ref['minOccurs'] = $details['minOccurs'];
													}

													if ( isset( $details['maxOccurs'] ) )
													{
														$ref['maxOccurs'] = $details['maxOccurs'];
													}

													$elements[ count( $refs ) + count( $elements ) ] = $ref;
													$result = true;
												}
											}

											$refs = array_merge( $refs, $elements );
										}
									}

									return  $result;
								};

								$fn( $type );
								unset( $me );
							}
						}
					}

					// Record the references
					$element['tuple_elements'] = $refs;
					$element['permitted_types'] = $permittedTypes;

				}

				if ( isset( $element['custom'] ) )
				{
					// Check that the custom attribute has a valid value.  For now limit to numeric check.
					foreach( $element['custom'] as $attribute => $value )
					{
						// If there is no type then ignore the element
						$attributePrefix = strstr( $attribute, ":", true );
						$attributeNamespace = $this->getDocumentNamespaces()[ $attributePrefix ];
						$tax = $this->getTaxonomyForNamespace( $attributeNamespace );
						// If there is no taxonomy it may be a built-in
						// if ( ! $tax ) continue;
						$prefix = $tax
							? $tax->getPrefix()
							:	(
									isset( XBRL_Constants::$standardNamespaces[ $attributeNamespace ] )
										? XBRL_Constants::$standardNamespaces[ $attributeNamespace ]
										: null
								);
						if ( ! $prefix ) continue;
						if ( ! ( $attributeType = $types->getAttribute( trim( strstr( $attribute, ":" ), ":" ), $prefix ) ) ) continue;
						if ( ! isset( $attributeType['types'][0] ) ) continue;

						// Check to see if the element type resolves to a decimal (such as an integer)
						if ( ! $types->resolvesToBaseType( $attributeType['types'][0], array( 'xs:decimal' ) ) ) continue;

						// If it does, can the value be coerced to a numeric?
						if ( is_numeric( $value ) ) continue;

						$this->log()->taxonomy_validation( "4.9", "Element attribute value type mismatch",
							array(
								'element' => $element['name'],
								'attribute' => $attribute,
								'value' => $value
							)
						);

					}
				}
			}

			$this->validateXDTElements( $types, $id, $name, $element);

		}
	}

	/**
	 * Validate the XDT dimensions
	 * @param bool $main True if called from the postprocess function
	 */
	public function validateDimensions( $main = false )
	{
		if ( ! XBRL::isValidating() )
		{
			return;
		}

		$flatten = function( $nodes, $usableOnly = true, $parents = array() ) use( &$flatten )
		{
			$result = array();

			foreach ( $nodes as $nodeKey => $node )
			{
				// Only need usable nodes
				if ( isset( $node['usable'] ) && ! $node['usable'] ) continue;
				// Ignore circular references
				if ( in_array( $nodeKey, $parents ) ) continue;
				$result[ $nodeKey ] = $node;
				if ( ! isset( $node['children'] ) ) continue;
				$result += $flatten( $node['children'], $usableOnly, $parents + array( $nodeKey ) );
			}

			return $result;
		};

		if ( count( $this->xdtTargetRoles ) )
		{
			$linkbaseroleTypes = $this->getAllLinkbaseRoleTypes();

			// Check the targetRoles use valid consecutive members
			foreach ( $this->xdtTargetRoles as $sourceRole => $targets )
			{
				foreach ( $targets as $targetRole => $linkbases )
				{
					foreach ( $linkbases as $linkbase => $target )
					{
						// BMS 2017-11-01 Changed to allow for target roles in linkbases in different taxonomies
						// Look for a match in any one of the linkbases
						// array_reduce( array_map( 'array_keys', $arcs ), 'array_merge', array() );
						$matches = array_reduce( array_keys( $linkbaseroleTypes ), function( $carry, $item ) use( &$linkbaseroleTypes, $targetRole, &$target ) {
							if ( isset( $linkbaseroleTypes[ $item ][ "link:definitionLink:$targetRole" ] ) )
							{
								$carry[] = $item;
							}
							return $carry;
						}, array() );

						// if ( ! isset( $this->linkbaseRoleTypes[ $linkbase ][ "link:definitionLink:$targetRole" ] ) )
						if ( ! count( $matches ) )
						{
							$this->log()->dimension_validation( "2.4.3", "Unable to locate the target role as a definition extended link",
								array(
									'role' => "'$targetRole'",
									'error' => 'xbrldte:TargetRoleNotResolvedError',
								)
							);

							continue;
						}
					}
				}
			}
		}

		// Make sure the extensible enumerations contain valid references after all elements are in and the dimension information is available
		foreach ( $this->enumerations as $id )
		{
			// Get the element
			$element = $this->getElementById( $id );
			$valid = false; // Be pessimistic

			if ( $element )
			{
				// This condition has been reported already
				if ( ! isset( $element['enumDomain'] ) || ! isset( $element['enumLinkrole'] ) ) continue;

				$parts = explode( ":", $element['enumDomain'] );
				if ( count( $parts ) == 2 )
				{
					$namespace = $this->getNamespaceForPrefix( $parts[0] );
					if ( $namespace)
					{
						$taxonomy = $this->getTaxonomyForNamespace( $namespace );
						$domainElement = $taxonomy->getElementByName( $parts[1] );
						if ( $domainElement )
						{
							$valid = ! $this->context->types->resolveToSubstitutionGroup( $domainElement['substitutionGroup'], array( 'xbrldt:hypercubeItem', 'xbrldt:dimensionItem', 'xbrli:tuple' ) );
						}
					}
				}
			}

			if ( ! $valid )
			{
				$this->log()->taxonomy_validation( "extensible enumerations", "The domain value does not point to a valid domain member",
					array(
						'id' => $id,
						'domain' => $element['enumDomain'],
						'substitutionGroup' => $domainElement['substitutionGroup'],
						'error' => 'enumte:InvalidDomainError',
					)
				);
			}
		}

		$this->enumerations = array();

		if ( ! $main ) return;

		// Resolve all the primary item DRSs. This will force the target roles to be tested again.
		// Check cycles

		$hypercubesChecked = array(); // Only need to check the role/hypercube combination once not for each primary item

		$primaryItems = $this->getDefinitionPrimaryItems( true );

		foreach ( $primaryItems as $primaryItemId => $primaryItem )
		{
			$drsHypercubes = $this->getPrimaryItemDRS( $primaryItem );

			foreach ( $drsHypercubes as $hypercubeId => $roles )
			{
				foreach ( $roles as $roleUri => $hypercube )
				{
					// BMS 2018-05-03 Added $primaryItemId because the hypercube may be the target of more than one primary item
					//				  For example, a primary item may have an arc to another concept making it a primary item of
					//				  the same hypercude(s).  An example is in XDT conformance test 115 V-01.
					if ( isset( $hypercubesChecked[ $roleUri ][ $hypercubeId ][ $primaryItemId ] ) )
					{
						continue;
					}

					$hypercubesChecked[ $roleUri ][ $hypercubeId ][ $primaryItemId ] = true;

					foreach ( $hypercube['dimensions'] as $dimensionId => $dimension )
					{
						if ( ! isset( $dimension['members'] ) ) continue;

						if ( ! isset( $dimension['unusablemembers'] ) || ! count( $dimension['unusablemembers'] ) )
						{
							$members = $flatten( $dimension['members'] );
							if ( isset( $members[ $primaryItemId ] ) )
							{
								$this->log()->taxonomy_validation(
									"2.5.3.2.1",
									"A primary item source of a hypercube is also a member of an explicit dimension's domain",
									array(
										'primaryitem' => "'$primaryItemId'",

											'role' => "'$roleUri'",
										'error' => 'xbrldte:PrimaryItemPolymorphismError',
									)
								);
							}
						}

						// Cycles detection depends upon the role involved.  Cycles allowed by role are:
						// all/notAll			Undirected
						// hypercube-dimension	None
						// dimension-domain		None
						// domain-member		Undirected
						$this->validateDirectedCycles(
							'definition', $roleUri, $dimension['members'],
							array( $primaryItemId, $hypercube['href'], $dimensionId ),
							function( $role, $result, $linkbase ) {
								XBRL_Log::getInstance()->taxonomy_validation(
									"2.4.3",
									"The linkbase contains circular references which are not permitted",
									array(
										'role' => "'$role'",
										'node' => "'$result'",
										'linkbase' => "'$linkbase'",
										'error' => 'xbrldte:DRSDirectedCycleError',
									)
								);
							}
						);

					}
				}
			}
		}

	}

	/**
	 * Process all the primary items to generate dimensional relationship sets
	 */
	public function generateAllDRSs()
	{
		$primaryItems = $this->getDefinitionPrimaryItems( true );
		foreach ( $primaryItems as $primaryItemKey => $primaryItem )
		{
			$this->getPrimaryItemDRS( $primaryItem );
		}
	}

	/**
	 * Used to cache previously resolved DRSs for a hypercube/role combination
	 * @var array $resolvedDRSCache
	 */
	private $resolvedDRSCache = array();

	/**
	 * Create DRSs for a primary item across all its roles
	 * @param array $primaryItem An array of elements which are the roles in which the primary item appears.
	 * 							 The array also includes an element 'roles' which is a list of all the roles.
	 * @return array An array of hypercubes of the form [ $hypercubeId ][ $roleUri ][]
	 */
	public function getPrimaryItemDRS( $primaryItem )
	{
		$result = array();

		if ( ! isset( $primaryItem ) || ! $primaryItem ) return $result;

		foreach ( $primaryItem['roles'] as $roleUri )
		{
			// $result = array_merge( $result, $this->getPrimaryItemDRSForRole( $primaryItem, $roleUri ) );
			$roleResult =  $this->getPrimaryItemDRSForRole( $primaryItem, $roleUri );
			foreach ( $roleResult as $hypercubeId => $roleHypercube )
			{
				if ( ! isset( $result[ $hypercubeId ] ) )
				{
					$result[ $hypercubeId ] = $roleHypercube;
					continue;
				}

				foreach ( $roleHypercube as $role => $hypercube )
				{
					$result[ $hypercubeId ][ $role ] = $hypercube;
				}

			}
		}

		return $result;
	}

	/**
	 * Called by getPrimaryItemDRS to retreive the DRS for a specific primary item in a specific role
	 * @param array $primaryItem
	 * @param string $roleUri
	 * @return mixed
	 */
	public function getPrimaryItemDRSForRole( $primaryItem, $roleUri )
	{
		if ( ! isset( $primaryItem[ $roleUri ] ) )
		{
			$this->log()->warning( "The role '$roleUri' does not exist in the requested primary item" );
			return array();
		}

		// Nothing to do if there are no hypercubes for the primary item
		if ( !isset( $primaryItem[ $roleUri ]['hypercubes'] ) || ! count( $primaryItem[ $roleUri ]['hypercubes'] ) )
		{
			return array();
		}

		$role = $this->getDefinitionRoleRef( $roleUri );
		if ( ! $role )
		{
			return array();
		}

		$primaryItemId = $primaryItem[ $roleUri ]['label'];

		$result = array();

		foreach ( $primaryItem[ $roleUri ]['hypercubes'] as $hypercubeId )
		{
			if ( ! $this->resolvePrimaryItemHypercubeDRS( $primaryItemId, $hypercubeId, $role ) )
			{
				// Something went wrong or there is a prohibited hypercube targetrole
				continue;
			}

			if ( ! isset( $this->resolvedDRSCache[ $roleUri ][ $hypercubeId ] ) )
			{
				// Probably not good
				continue;
			}

			$result[ $hypercubeId ][ $roleUri ] = $this->resolvedDRSCache[ $roleUri ][ $hypercubeId ];
		}

		return $result;
	}

	/**
	 * Build the dimension and members hierachies
	 *
	 * @param array $items An array nodes that are characterised by having a 'parents' element that list the nodes parents
	 * @param string $itemsName The name of the result element containing the generated hierachy (default: roots)
	 * @param string $itemsParentsName The name of the result element containing the list of roots (default: rootParents)
	 * @return array An array of results which includes 'roots', 'rootParents' and 'unusableMembers'
	 */
	private function buildHierarchyFromParents( &$items, $itemsName = 'roots', $itemsParentsName = 'rootParents' )
	{
		$results = array();
		$rootParents = array();
		$unusable = array();

		foreach ( $items as $itemId => &$item )
		{
			$item['label'] = $itemId;

			if ( ! isset( $item['parents'] ) )
			{
				$item['root'] = true;

				// If any of the existing results have this $itemId as a parent then add the result as a child
				foreach ( $results as $resultId => &$result )
				{
					if ( $itemId == $resultId || ! isset( $result['parents'] ) ) continue;

					foreach ( $result['parents'] as $resultParentId => $resultParent )
					{
						if ( $resultParentId != $itemId ) continue;
						$item['children'][ $resultId ] = &$result;
					}
				}
				unset( $result );
				$results[ $itemId ] =& $item;
				// $results[ $itemId ]['children'] = array();
				$rootParents[ $itemId ][] = $itemId;
				continue;
			}

			foreach ( $item['parents'] as $parentId => $parent )
			{
				// Exclude unusable arcs
				if ( isset( $parent['usable'] ) && ! $parent['usable'] )
				{
					$unusable[ $parentId ][] = $itemId;
				}

				// The parent may be a domain
				if ( ! isset( $items[ $parentId ] ) )
				{
					$item['root'] = true;
					// If any of the existing results have this $itemId as a parent then add the result as a child
					foreach ( $results as $resultId => &$result )
					{
						foreach ( $result['parents'] as $resultParentId => $resultParent )
						{
							if ( $resultParentId != $itemId ) continue;
							$item['children'][ $resultId ] = &$result;
						}
					}
					unset( $result );
					$results[ $itemId ] = &$item;
					$rootParents[ $parentId ][] = $itemId;
					continue;
				}

				if ( ! isset( $results[ $parentId ] ) )
				{
					$results[ $parentId ] = $items[ $parentId ];
					$results[ $parentId ]['children'] = array();
				}

				if ( isset( $results[ $itemId ] ) )
				{
					$item = &$results[ $itemId ];
				}

				$results[ $parentId ]['children'][ $itemId ] = &$item;
				if ( isset( $results[ $itemId ] ) ) continue;
				$results[ $itemId ] = &$item;
			}
		}

		unset( $item );

		$results = array_filter( $results, function( $member ) { return isset( $member['root'] ); } );
		foreach ( $results as $rootKey => &$root )
		{
			unset( $root['root'] );
		}

		unset( $root );

		return array( $itemsName => $results, $itemsParentsName => $rootParents, 'unusableMembers' => $unusable );
	}

	/**
	 * Compute the dimensional relationship set (DRS) for the $hypercubeId starting in $role
	 * @param string $primaryItemId The id of the primary item that will be the root of the DRS
	 * @param string $hypercubeId The id of the hypercube in $role that has a has-hypercube relationship with the primary item
	 * @param array $role A set of elements representing the role that identifies the linkbase containing the has-hypercube relationship
	 * @return boolean
	 */
	private function resolvePrimaryItemHypercubeDRS( $primaryItemId, $hypercubeId, $role )
	{
		// Record this because the $role array will be overwritten
		$primaryItemRole = $role['roleUri'];

		if ( isset( $this->resolvedDRSCache[ $primaryItemRole ][ $hypercubeId ] ) ) return true;

		// Check the hypercubeId exists in the in the role hypercube list
		if ( ! isset( $role['hypercubes'] ) )
		{
			$this->log()->taxonomy_validation( "drs", "Unable to locate the hypercube list",
				array(
					'role' => "'{$role['roleUri']}'",
					'hypercube' => "'$hypercubeId'",
				)
			);
			return false;
		}

		if ( ! isset( $role['hypercubes'][ $hypercubeId ] ) )
		{
			$this->log()->taxonomy_validation( "drs", "Unable to locate the hypercube item in the role hypercube list",
				array(
					'role' => "'{$role['roleUri']}'",
					'hypercube' => "'$hypercubeId'",
				)
			);
			return false;
		}

		// Take a copy of the hypercube. This is the root of this DRS (well techically
		// the primary item is the root but the task is to resolve this hypercube so the
		// same resolved hypercubes can be reused when is is used in other primary items).
		$hypercube = $role['hypercubes'][ $hypercubeId ];

		// Confirm that $primaryItemId is a parent of $hypercubeId
		if ( ! isset( $hypercube['parents'][ $primaryItemId ] ) )
		{
			$this->log()->taxonomy_validation( "drs", "The primary item is not a parent of the hypercube",
				array(
					'role' => "'{$role['roleUri']}'",
					'hypercube' => "'$hypercubeId'",
					'primaryItem' => "'$primaryItemId'",
				)
			);
			return false;
		}

		// Arcrole with parent should be all or notAll
		if ( ! isset( $hypercube['parents'][ $primaryItemId ]['arcrole'] ) ||
			 ! in_array( $hypercube['parents'][ $primaryItemId ]['arcrole'], XBRL_Constants::$hasHypercube )
		)
		{
			// Unable to locate the hypercube in the hierarchy
			$this->log()->taxonomy_validation( "drs", "The arcrole from the primary item to the hypercube is not has-hypercube",
				array(
					'role' => "'{$role['roleUri']}'",
					'hypercube' => "'$hypercubeId'",
					'primaryItem' => "'$primaryItemId'",
				)
			);
			return false;
		}

		$mergedRoles = array();

		if ( isset( $hypercube['parents'][ $primaryItemId ]['targetRole'] ) )
		{
			$parent = $hypercube['parents'][ $primaryItemId ];
			// BMS 2020-09-02 Account for prohibited hypercube links
			// BMS 2020-09-02 Account for prohibited hypercube links
			if ( isset( $parent['use'] ) && $parent['use'] == XBRL_Constants::$xlinkUseProhibited ) return false;

			// Need to merge hypercube from targetRole.  First check it is valid.
			$targetRoleUri = $parent['targetRole'];

			$targetRole = $this->getDefinitionRoleRef( $targetRoleUri );

			if ( ! $targetRole )
			{
				// Look to see if the role is in the taxonomy that owns $targetRoleUri
				$taxonomy = $this->getTaxonomyForXSD( $role['href'] );
				if ( $taxonomy )
				{
					$targetRole = $taxonomy->getDefinitionRoleRef( $targetRoleUri );
				}
				unset( $taxonomy );
			}

			if ( $targetRole )
			{
				if ( ! isset( $targetRole['hypercubes'][ $hypercubeId ] ) &&
					 ! isset( $targetRole['members'][ $primaryItemId ] ) &&
					 ! isset( $targetRole['dimensions'][ $primaryItemId ] ) &&
					 ! isset( $targetRole['primaryitems'][ $primaryItemId ] )
				)
				{
					$this->log()->dimension_validation( "2.4.3", "The hypercube cannot be located in the hypercubes collection of the target role",
						array(
							'target role' => "'{$parent['targetRole']}'",
							'hypercube' => "'$hypercubeId'",
						)
					);
					return false;
				}

				$originalRole = $role;
				unset( $role ); // Make sure the original role is not affected because it may be used in other targets
				$role = $this->mergeExtendedRoles( $originalRole, $targetRole, $mergedRoles );
				if ( $role === false )
				{
					// Something went horribly wrong
					return false;
				}
				unset( $originalRole );
			}

			unset( $parent );
			unset( $targetRole );
			unset( $targetRoleUri );
		}

		// Next handle dimensions with targetRoles recursively
		foreach ( $role['dimensions'] as $dimensionId => $dimension )
		{
			if ( ! isset( $dimension['parents'] ) ) continue;
			foreach ( $dimension['parents'] as $parentId => $parent )
			{
				// Can only merge if there is a targetRole
				if ( ! isset( $parent['targetRole'] ) ) continue;
				// BMS 2020-09-02 Account for prohibited hypercube links
				if ( isset( $parent['use'] ) && $parent['use'] == XBRL_Constants::$xlinkUseProhibited ) continue;
				// No need to merge if the role has already been seen and merged
				if ( in_array( $parent['targetRole'], $mergedRoles ) ) continue;

				// Need to merge dimension from targetRole.  First check it is valid.
				$targetRole = $this->getDefinitionRoleRef( $parent['targetRole'] );

				if ( ! isset( $targetRole['dimensions'][ $dimensionId ] ) )
				{
					$this->log()->dimension_validation( "2.4.3", "The dimension cannot be located in the dimensions collection of the target role",
						array(
							'target role' => "'{$parent['targetRole']}'",
							'dimension' => "'$dimensionId'",
							'location' => 'resolvePrimaryItemHypercubeDRS',
							'error' => 'xbrldte:TargetRoleNotResolvedError'
						)
					);
					continue;
				}

				// Because a target role is being merged the arcs down stream from this
				// should not apply so remove the connection.  From the XDT specification 2.4:
				// If [a targetRole] is present on a relationship, any other relationship that represents a
				// consecutive relationship [Def, 2] in the source role [Def, 5] MUST NOT be considered as
				// part of the dimensional relationship set [Def, 3]. Instead, relationships representing
				// consecutive relationships [Def, 2] in the target role [Def, 6]  MUST be considered for
				// the construction of the dimensional relationship set [Def, 3].
				foreach ( $role['members'] as $memberId => $member )
				{
					if ( ! isset( $member['parents'][ $dimensionId ] ) ) continue;
					unset( $role['members'][ $memberId ]['parents'][ $dimensionId ] );
				}

				$role = $this->mergeExtendedRoles( $role, $targetRole, $mergedRoles );
			}
		}

		// The last merge is to handle members with targetRoles recursively
		foreach ( $role['members'] as $memberId => $member )
		{
			if ( ! isset( $member['parents'] ) ) continue;
			foreach ( $member['parents'] as $parentId => $parent )
			{
				// Can only merge if there is a targetRole
				if ( ! isset( $parent['targetRole'] ) ) continue;
				// BMS 2020-09-02 Account for prohibited hypercube links
				if ( isset( $parent['use'] ) && $parent['use'] == XBRL_Constants::$xlinkUseProhibited ) continue;
				// No need to merge if the role has already been seen and merged
				if ( in_array( $parent['targetRole'], $mergedRoles ) ) continue;

				// Need to merge dimension from targetRole.  First check it is valid.
				$targetRole = $this->getDefinitionRoleRef( $parent['targetRole'] );

				if ( ! isset( $targetRole['members'][ $memberId ] ) )
				{
					$this->log()->dimension_validation( "2.4.3", "The member cannot be located in the members collection of the target role",
						array(
							'target role' => "'{$targetRole['roleUri']}'",
							'member' => "'$memberId'",
							'error' => 'xbrldte:TargetRoleNotResolvedError',
						)
					);
					continue;
				}

				// Because a target role is being merged the arcs down stream from this
				// should not apply so remove the connection.  From the XDT specification 2.4:
				// If [a targetRole] is present on a relationship, any other relationship that represents a
				// consecutive relationship [Def, 2] in the source role [Def, 5] MUST NOT be considered as
				// part of the dimensional relationship set [Def, 3]. Instead, relationships representing
				// consecutive relationships [Def, 2] in the target role [Def, 6]  MUST be considered for
				// the construction of the dimensional relationship set [Def, 3].
				foreach ( $role['members'] as $childMemberId => $member )
				{
					if ( ! isset( $member['parents'][ $memberId ] ) ) continue;
					unset( $role['members'][ $childMemberId ]['parents'][ $memberId ] );
				}

				$role = $this->mergeExtendedRoles( $role, $targetRole, $mergedRoles );
			}
		}

		// Get the members that have no parents
		extract( $this->buildHierarchyFromParents( $role['members'], 'members', 'memberParents' ) );

		// Add dimensions to the hypercubes
		foreach ( $role['dimensions'] as $dimensionId => $dimension )
		{
			if ( isset(  $dimension['parents'] ) )
			foreach ( $dimension['parents'] as $parentKey => $parent )
			{
				if ( isset( $role['hypercubes'][ $parentKey ] ) )
				{
					$role['hypercubes'][ $parentKey ]['dimensions'][ $dimensionId ] = $parent + array( 'label' => $dimensionId );
				}
			}
		}

		// OK, time to replace the members on all hypercube dimensions
		// foreach ( $role['hypercubes'] as $hypercubeId => $hypercube )
		$hypercube = $role['hypercubes'][ $hypercubeId ];
		if ( isset( $hypercube['dimensions'] ) ) // || ! isset( $hypercube['parents'] ) ) continue;
		{

			foreach ( $hypercube['dimensions'] as $dimensionId => &$dimension )
			{
				$dimTax = $this->getTaxonomyForXSD( $dimensionId );
				$element = $dimTax->getElementById( strpos( $dimensionId, '#' ) ? ltrim( strstr( $dimensionId, '#' ), '#' ) : $dimensionId );

				$dimension['explicit'] = ! isset( $element['typedDomainRef'] );
				$dimension['unusablemembers'] = array();

				if ( isset( $this->context->dimensionDefaults[ $dimensionId ] ) )
				{
					$dimension['default'] = $this->context->dimensionDefaults[ $dimensionId ];
				}

				if ( ! isset( $memberParents[ $dimensionId ] ) )
				{
					continue;
				}

				// Assign members to the dimension
				foreach ( $memberParents[ $dimensionId ] as $id => $domain )
				{
					$dimension['members'][ $domain ] = $members[ $domain ];
				}
				$dimension['memberpaths'] = $this->createHierarchyPaths( $dimension['members'] );

				// Look to see if any of the unusables are in this dimension but only in explicit dimensions
				if ( ! $dimension['explicit'] ) continue;

				// $unusableMembers is value extracted from the buildHierarchyFromParents function result
				foreach ( $unusableMembers as $from => $targets )
				{
					foreach ( $targets as $to )
					{
						if ( ! isset( $dimension['memberpaths'][ parse_url( $to, PHP_URL_FRAGMENT ) ] ) ) continue;

						// The $from exists so find it in the member hierarchy
						$this->processNodeByPath(
							$dimension['members'],
							$dimension['memberpaths'][ parse_url( $to, PHP_URL_FRAGMENT ) ],
							$to,
							function( $node, $path, $nodeKey ) use( &$dimension, $from ) {

								// Check the node has a parent which is $from
								if ( ! isset( $node['parents'][ $from ] ) || in_array( $node['label'], $dimension['unusablemembers'] ) ) return;
								$dimension['unusablemembers'][] = $node['label'];
							},
							function( $path )
							{
								// Failing is OK in this scenario.  It means the unusable member does not exist in this dimension
							}
						);
					}
				}

			}

			unset( $dimension );

			// Record the hypercube
			$this->resolvedDRSCache[ $primaryItemRole ][ $hypercubeId ] = $hypercube;
		}

		return true;
	}

	/**
	 * Converts a member definition in a target role into a primary item definition in a new role
	 *
	 * @param string $memberId The id of the member to be promoted
	 * @param string $parentId The id of the member's parent.  May be 'false' if the member and parent ids are the same
	 * @param array $newPrimaryItems An array of elements representing the new role being created
	 * @param array $targetMembers An array of elements representing the role being merged
	 * @param array $targetRoleUri The uri of the target role
	 */
	private function promoteToPrimaryItem( $memberId, $parentId, &$newPrimaryItems, &$targetMembers, $targetRoleUri )
	{
		// Might have been promoted already because its also a child of another member
		if ( ! isset( $targetMembers[ $memberId ] ) ) return;

		if ( ! $parentId ) $parentId = $memberId;

		// Might already exist as a primary item
		if ( isset( $newPrimaryItems[ $memberId ] ) )
		{
			// In which case add a parent reference
			if ( $parentId != $memberId )
			{
				$newPrimaryItems[ $memberId ]['parents'][ $parentId ] = $targetMembers[ $memberId ]['parents'][ $parentId ];
			}
			$newPrimaryItems[ $memberId ]['roleUri'] = $targetRoleUri;
			return;
		}

		if ( ! isset( $newPrimaryItems[ $parentId ] ) )
		{
			XBRL_Log::getInstance()->warning( "Error trying to promote a member '$memberId' to primary item." );
			return;
		}

		$newPrimaryItems[ $memberId ] = array(
			'arcrole' => $targetMembers[ $memberId ]['parents'][ $parentId ]['arcrole'],
			'hypercubes' => array(), // $newPrimaryItems[ $parentId ]['hypercubes'],
			'parents' => array(
				$parentId => $targetMembers[ $memberId ]['parents'][ $parentId ],
			),
			'roleUri' => $targetRoleUri,
		);
	}

	/**
	 * Processes a set of member ids that are to be promoted.  The most important task of this
	 * function is to make sure an dependents are also promoted.
	 *
	 * @param array $newRole An array of elements representing the new role being created
	 * @param array $targetRole An array of elements representing the role being merged
	 * @param array $membersToPromote An array of member id thay are to be promoted
	 * @param string $parentId The id of the member's parent.  May be 'false' if the member and parent ids are the same
	 * @param array|false $membersByParent An array of members indexed by the parents
	 */
	function promoteMembersByRole( &$newRole, &$targetRole, $membersToPromote, $parentId = false, $membersByParent = false )
	{
		$this->promoteMembers( $newRole['primaryitems'], $targetRole['members'], $targetRole['roleUri'], $membersToPromote, $parentId, $membersByParent );
	}

	/**
	 * Processes a set of member ids that are to be promoted.  The most important task of this
	 * function is to make sure an dependents are also promoted.
	 *
	 * @param array $newPrimaryItems An array of elements representing the new role being created
	 * @param array $targetMembers (by reference) An array of elements representing the role being merged
	 * @param array $targetRoleUri The uri of the target role
	 * @param array $membersToPromote An array of member id thay are to be promoted
	 * @param string $parentId The id of the member's parent.  May be 'false' if the member and parent ids are the same
	 * @param array|false $membersByParent An array of members indexed by the parents
	 */
	function promoteMembers( &$newPrimaryItems, &$targetMembers, $targetRoleUri, $membersToPromote, $parentId = false, $membersByParent = false )
	{
		if ( ! $membersToPromote ) return;

		if ( ! $membersByParent )
		{
			// Create an index by parent
			$membersByParent = array();

			foreach ( $targetMembers as $memberId => $member )
			{
				if ( ! isset( $member['parents'] ) ) continue;

				foreach ( $member['parents'] as $memberParentId => $memberParent )
				{
					$membersByParent[ $memberParentId ][ $memberId ] = $memberId;
				}
			}
		}

		foreach ( $membersToPromote as $memberId )
		{
			// Promote this member.  If $parendId is false then this is
			// a root node so the memberId and parentId are the same
			$this->promoteToPrimaryItem( $memberId, $parentId, $newPrimaryItems, $targetMembers, $targetRoleUri );

			// Any members that have this memberId as a parent also need to be promoted
			if ( isset( $membersByParent[ $memberId ] ) )
			{
				$this->promoteMembers( $newPrimaryItems, $targetMembers, $targetRoleUri, $membersByParent[ $memberId ], $memberId, $membersByParent );
			}

			unset( $targetMembers[ $memberId ], $primaryItemsSource );
		}
	}

	/**
	 * Merges the primaryItems, hypercubes, dimensions and members of two roles
	 * Used by the function 'resolvePrimaryItemHypercubeDRS'
	 * @param array $role				An array representing one role
	 * @param array $targetRole			An array representing a second role
	 * @param array $mergedRoles		A list of the roles that have been merged so far (to prevent redundant merging)
	 * @param bool $mergeTargetRoles	Whether to merge nested target roles specified in dimensions and members
	 * @return array|bool False or an array containing the merge roles
	 */
	private function mergeExtendedRoles( $role, $targetRole, &$mergedRoles, $mergeTargetRoles = true )
	{
		if ( ! is_array( $mergedRoles ) || ! count( $mergedRoles ) )
		{
			$mergedRoles = array( $role['roleUri'] );
		}

		if ( $mergeTargetRoles )
		{
			$mergedRoles[] = $targetRole['roleUri'];
		}

		try
		{
			// Now merge the two roles.  Combine members, dimensions, hypercubes, primary items
			// Begin by taking the fixtures from the source role ($role)
			$newRole = array(
				'members' => array(),
				'primaryitems' => array(),
				'dimensions' => array(),
				'hypercubes' => array(),
			);

			// Merge the primary items
			if ( isset( $role['primaryitems'] ) )
			{
				$newRole['primaryitems'] = $role['primaryitems'];
				if ( isset( $targetRole['primaryitems'] ) )
				foreach ( $targetRole['primaryitems'] as $targetPrimaryItemKey => $targetPrimaryItem )
				{
					if ( ! isset( $newRole['primaryitems'][ $targetPrimaryItemKey ] ) )
					{
						$newRole['primaryitems'][ $targetPrimaryItemKey ] = $targetPrimaryItem;
						continue;
					}

					if ( isset( $targetPrimaryItem['localhypercubes'] ) && count( $targetPrimaryItem['localhypercubes'] ) )
					{
						$localhypercubes = isset( $newRole['primaryitems'][ $targetPrimaryItemKey ]['localhypercubes'] )
							? $newRole['primaryitems'][ $targetPrimaryItemKey ]['localhypercubes']
							: array();

						$diff = array_diff( $targetPrimaryItem['localhypercubes'], $localhypercubes );
						$newRole['primaryitems'][ $targetPrimaryItemKey ]['localhypercubes'] = array_merge( $localhypercubes, $diff );
					}

					if ( ! isset( $targetPrimaryItem['parents'] ) ) continue;
					foreach ( $targetPrimaryItem['parents'] as $targetParentKey => $targetParent )
					{
						if ( isset( $newRole['primaryitems'][ $targetPrimaryItemKey ]['parents'][ $targetParentKey ] ) )
						{
							$newRolePriority = $newRole['primaryitems'][ $targetPrimaryItemKey ]['parents'][ $targetParentKey ]['priority'] ?? 0;
							$newRoleUse = $newRole['primaryitems'][ $targetPrimaryItemKey ]['parents'][ $targetParentKey ]['use'] ?? XBRL_Constants::$xlinkUseOptional;

							// If the $targetParent use is 'prohibited' and the $targetParent priority is = the newRole parent or
							// if the $targetParent priority is < the newRole parent then there is nothing to do because newRole wins
							if (
									$targetParent['priority'] ?? 0 < $newRolePriority ||
									( $targetParent['priority'] ?? 0 == $newRolePriority && ( $targetParent['use'] ?? XBRL_Constants::$xlinkUseOptional ) != XBRL_Constants::$xlinkUseProhibited )
							)
							{
								continue;
							}
						}

						// Otherwise replace the newrole parent with the target role parent
						$newRole['primaryitems'][ $targetPrimaryItemKey ]['parents'][ $targetParentKey ] = $targetParent;
					}
				}
			}
			else if ( isset( $targetRole['primaryitems'] ) )
			{
				$newRole['primaryitems'] = $targetRole['primaryitems'];
			}

			// Merge the dimensions
			if ( isset( $role['dimensions'] ) )
			{
				$newRole['dimensions'] = $role['dimensions'];

				if ( isset( $targetRole['dimensions'] ) )
				foreach ( $targetRole['dimensions'] as $targetDimensionKey => $targetDimension )
				{
					if ( ! isset( $newRole['dimensions'][ $targetDimensionKey ] ) )
					{
						$newRole['dimensions'][ $targetDimensionKey ] = $targetDimension;
						continue;
					}

					if ( ! isset( $targetDimension['parents'] ) ) continue;
					foreach ( $targetDimension['parents'] as $targetParentKey => $targetParent )
					{
						if ( isset( $newRole['dimensions'][ $targetDimensionKey ]['parents'][ $targetParentKey ] ) )
						{
							$newRolePriority = $newRole['dimensions'][ $targetDimensionKey ]['parents'][ $targetParentKey ]['priority'] ?? 0;
							$newRoleUse = $newRole['dimensions'][ $targetDimensionKey ]['parents'][ $targetParentKey ]['use'] ?? XBRL_Constants::$xlinkUseOptional;

							// If the $targetParent use is 'prohibited' and the $targetParent priority is = the newRole parent or
							// if the $targetParent priority is < the newRole parent then there is nothing to do because newRole wins
							if ( $targetParent['priority'] ?? 0 < $newRolePriority ||
								 ( $targetParent['priority'] ?? 0 == $newRolePriority && ( $targetParent['use'] ?? XBRL_Constants::$xlinkUseOptional ) != XBRL_Constants::$xlinkUseProhibited )
							)
							{
								continue;
							}
						}

						// Otherwise replace the newrole parent with the target role parent
						$newRole['dimensions'][ $targetDimensionKey ]['parents'][ $targetParentKey ] = $targetParent;
					}
				}
			}
			else if ( isset( $targetRole['dimensions'] ) )
			{
				$newRole['dimensions'] = $targetRole['dimensions'];
			}

			if ( $mergeTargetRoles )
			{
				// Before merging members make sure the target role members are not really primary items in the main role
				$membersToPromote = array_keys( array_intersect_key( $targetRole['members'], $role['primaryitems'] ) );
				if ( $membersToPromote )
				{
					$this->promoteMembersByRole( $newRole, $targetRole, $membersToPromote );
				}
			}

			// Members can't be merged simply because the 'usable' attribute it if exists on one side and is false MUST win (XDT 2.5.3.3)
			// Plus the priority should also be taken into account
			if ( isset( $role['members'] ) )
			{
				$newRole['members'] = $role['members'];
				if ( isset( $targetRole['members'] ) )
				foreach ( $targetRole['members'] as $targetMemberKey => $targetMember )
				{
					if ( ! isset( $newRole['members'][ $targetMemberKey ] ) )
					{
						$newRole['members'][ $targetMemberKey ] = $targetMember;
						continue;
					}

					if (
							( isset( $newRole['members'][ $targetMemberKey ]['usable'] ) && ! $newRole['members'][ $targetMemberKey ]['usable'] ) ||
							( isset( $targetRole['members'][ $targetMemberKey ]['usable'] ) && ! $targetRole['members'][ $targetMemberKey ]['usable'] )
					)
					{
						$newRole['members'][ $targetMemberKey ]['usable'] = false;
					}

					if ( ! isset( $targetMember['parents'] ) ) continue;
					foreach ( $targetMember['parents'] as $targetParentKey => $targetParent )
					{
						if ( isset( $newRole['members'][ $targetMemberKey ]['parents'][ $targetParentKey ] ) )
						{
							$newRolePriority = $newRole['members'][ $targetMemberKey ]['parents'][ $targetParentKey ]['priority'] ?? 0;
							$newRoleUse = $newRole['members'][ $targetMemberKey ]['parents'][ $targetParentKey ]['use'] ?? XBRL_Constants::$xlinkUseOptional;

							// If the $targetParent use is 'prohibited' and the $targetParent priority is = the newRole parent or
							// if the $targetParent priority is < the newRole parent then there is nothing to do because newRole wins
							if ( $targetParent['priority'] ?? 0 < $newRolePriority ||
								 ( $targetParent['priority'] ?? 0 == $newRolePriority && ( $targetParent['use'] ?? XBRL_Constants::$xlinkUseOptional ) != XBRL_Constants::$xlinkUseProhibited )
							)
							{
								continue;
							}
						}

						// Otherwise replace the newrole parent with the target role parent
						$newRole['members'][ $targetMemberKey ]['parents'][ $targetParentKey ] = $targetParent;
					}
				}
			}
			else if ( isset( $targetRole['members'] ) )
			{
				$newRole['members'] = $targetRole['members'];
			}

			if ( isset( $role['hypercubes'] ) )
			{
				// Add source hypercubes
				$newRole['hypercubes'] = $role['hypercubes'];

				/*
				 * Only hypercubes originating in the source role are included when different roles are being merged
				 * See XDT spec 2.6.1 and example 11.
				 */

				if ( ! $mergeTargetRoles )
				{
					// Add the unique target role hypercubes
					if ( isset( $targetRole['hypercubes'] ) )
					{
						// Add any additional hypercubes
						foreach ( $targetRole['hypercubes'] as $hypercubeKey => $hypercube )
						{
							if ( isset( $newRole['hypercubes'][ $hypercubeKey ] ) )
							{
								foreach ( $hypercube as $key => $items )
								{
									switch ( $key )
									{
										case 'namespace':
										case 'role':
										case 'href':
										case 'nodeclass':
											break;

										default:
											if ( ! isset( $newRole['hypercubes'][ $hypercubeKey ][ $key ] ) )
											{
												$newRole['hypercubes'][ $hypercubeKey ][ $key ] = array();
											}

											$newRole['hypercubes'][ $hypercubeKey ][ $key ] = array_merge(
												$newRole['hypercubes'][ $hypercubeKey ][ $key ],
												$items
											);
									}
								}
							}
							else
							{
								$newRole['hypercubes'][ $hypercubeKey ] = $hypercube;
							}
						}
					}
				}
			}
			else if ( ! $mergeTargetRoles )
			{
				$newRole['hypercubes'] = $targetRole['hypercubes'];
			}

			// If not merging nested roles (because this function is called from processDefintionLinkbase) then exit
			if ( ! $mergeTargetRoles )
			{
				$newRole['type'] = $role['type'];
				$newRole['href'] = $role['href'];
				$newRole['roleUri'] = $role['roleUri'];
				return $newRole;
			}

			// But drop the dimensions because they are going to be rebuilt
			foreach ( $newRole['hypercubes'] as $hypercubeKey => $hypercube )
			{
				$newRole['hypercubes'][ $hypercubeKey ]['dimensions'] = array();
			}

			// Next handle dimensions with targetRoles recursively
			foreach ( $newRole['dimensions'] as $dimensionId => $dimension )
			{
				if ( ! isset( $dimension['parents'] ) ) continue;
				foreach ( $dimension['parents'] as $parentId => $parent )
				{
					// Can only merge if there is a targetRole
					if ( ! isset( $parent['targetRole'] ) ) continue;

					// No need to merge if the role has already been seen and merged
					if ( in_array( $parent['targetRole'], $mergedRoles ) ) continue;

					// Need to merge dimension from targetRole.  First check it is valid.
					$targetRole = $this->getDefinitionRoleRef( $parent['targetRole'] );

					if ( ! isset( $targetRole['dimensions'][ $dimensionId ] ) )
					{
						$this->log()->dimension_validation( "2.4.3", "The dimension cannot be located in the dimensions collection of the target role",
							array(
								'target role' => "'{$parent['targetRole']}'",
								'dimension' => "'$dimensionId'",
								'location' => 'mergeExtendedRoles',
								'error' => 'xbrldte:TargetRoleNotResolvedError',
							)
						);
						continue;
					}

					// Because a target role is being merged the arcs down stream from this
					// should not apply so remove the connection.  From the XDT specification 2.4:
					// If [a targetRole] is present on a relationship, any other relationship that represents a
					// consecutive relationship [Def, 2] in the source role [Def, 5] MUST NOT be considered as
					// part of the dimensional relationship set [Def, 3]. Instead, relationships representing
					// consecutive relationships [Def, 2] in the target role [Def, 6]  MUST be considered for
					// the construction of the dimensional relationship set [Def, 3].
					foreach ( $newRole['members'] as $memberId => $member )
					{
						if ( ! isset( $member['parents'][ $dimensionId ] ) ) continue;
						unset( $newRole['members'][ $memberId ]['parents'][ $dimensionId ] );
					}

					$newRole = $this->mergeExtendedRoles( $newRole, $targetRole, $mergedRoles );
				}
			}

			// The last merge is to handle members with targetRoles recursively
			foreach ( $newRole['members'] as $memberId => $member )
			{
				if ( ! isset( $member['parents'] ) ) continue;
				foreach ( $member['parents'] as $parentId => $parent )
				{
					// Can only merge if there is a targetRole
					if ( ! isset( $parent['targetRole'] ) ) continue;
					// No need to merge if the role has already been seen and merged
					if ( in_array( $parent['targetRole'], $mergedRoles ) ) continue;

					// Need to merge dimension from targetRole.  First check it is valid.
					$targetRole = $this->getDefinitionRoleRef( $parent['targetRole'] );

					if ( ! isset( $targetRole['members'][ $memberId ] ) )
					{
						$this->log()->dimension_validation( "2.4.3", "The member cannot be located in the members collection of the target role",
							array(
								'target role' => "'{$targetRole['roleUri']}'",
								'member' => "'$memberId'",
							)
						);
						continue;
					}

					// Because a target role is being merged the arcs down stream from this
					// should not apply so remove the connection.  From the XDT specification 2.4:
					// If [a targetRole] is present on a relationship, any other relationship that represents a
					// consecutive relationship [Def, 2] in the source role [Def, 5] MUST NOT be considered as
					// part of the dimensional relationship set [Def, 3]. Instead, relationships representing
					// consecutive relationships [Def, 2] in the target role [Def, 6]  MUST be considered for
					// the construction of the dimensional relationship set [Def, 3].
					foreach ( $newRole['members'] as $childMemberId => $member )
					{
						if ( ! isset( $member['parents'][ $memberId ] ) ) continue;
						unset( $newRole['members'][ $childMemberId ]['parents'][ $memberId ] );
					}

					$newRole = $this->mergeExtendedRoles( $newRole, $targetRole, $mergedRoles );
				}
			}

			return $newRole;
		}
		catch( \Exception $ex )
		{
			// Do nothing
		}

		return false;
	}

	/**
	 * Lax validation of the elements in the appinfo element
	 */
	private function validateAppInfo()
	{
		$nodes = $this->xbrlDocument->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] );
		if ( property_exists( $nodes, 'annotation' ) )
		{
			$annotations = $nodes->annotation;
			foreach ( $annotations as $annotationKey => $annotation )
			{
				if ( property_exists( $annotation, 'appinfo' ) )
				{
					/**
					 * @var SchemaTypes $types
					 */
					$types = XBRL_Types::getInstance();

					$domNode = dom_import_simplexml( $annotation->appinfo );
					foreach( $domNode->childNodes as $element => /** @var DOMNode $node */ $node )
					{
						if ( $node->nodeType != XML_ELEMENT_NODE ) continue;

						if ( $node->localName == 'linkbase' )
						{
							$this->validateLinkbase( $node );
							continue;
						}

						// If there is no type then ignore the element
						$tax = $this->getTaxonomyForNamespace( $node->namespaceURI );
						// Non-existant or built-in ones like 'linkbase' will not return a taxonomy
						if ( ! $tax ) continue;
						$prefix = $tax->getPrefix();
						if ( ! ( $elementType = $types->getElement( $node->localName, $prefix ) ) ) continue;
						if ( ! isset( $elementType['types'][0] ) ) continue;

						// Check to see if the element type resolves to a decimal (such as an integer)
						if ( ! $types->resolvesToBaseType( $elementType['types'][0], array( 'xs:decimal' ) ) ) continue;

						// If it does, can the value be coerced to a numeric?
						if ( is_numeric( $node->nodeValue ) ) continue;

						$this->log()->taxonomy_validation( "4.9", "Element value type mismatch",
							array(
								'element' => $node->tagName,
								'value' => $node->nodeValue
							)
						);
					}
				}
			}
		}
	}

	/**
	 * A list of linkbases associated with this taxonomy
	 * @var array
	 */
	private $localLinkbases = array();

	/**
	 * Validate the contents of a linkbase element found in an appinfo element
	 * @param DOMElement $domElement
	 * @return void
	 */
	private function validateLinkbase( $domElement )
	{
		$linkbasePath = $domElement->getNodePath();
		$this->localLinkbases[ $linkbasePath ] = array( 'roleRef' => array(), 'arcroleRef' => array() );

		// All the children of $domElement should be extended links
		foreach( $domElement->childNodes as $element => /** @var DOMElement $domLink */ $domLink )
		{
			if ( $domLink->nodeType != XML_ELEMENT_NODE ) continue;

			if ( $domLink->localName == 'roleRef' )
			{
				// Create a hash for the roleRef
				$href = $domLink->getAttributeNodeNS( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK], 'href' );
				$roleUri = $domLink->getAttribute("roleURI");
				$hash = $href->nodeValue . $roleUri;
				if ( in_array( $hash, $this->localLinkbases[ $linkbasePath ]['roleRef'] ) )
				{
					$this->log()->taxonomy_validation( "3.5.2.4.5", "Linkbase roleRef has been duplicated",
						array(
							'element' => $domLink->tagName,
							'href' => $href->nodeValue,
							'roleUri' => $roleUri
						)
					);
				}

				$this->localLinkbases[ $linkbasePath ]['roleRef'][] = $hash;
				continue;
			}

			if ( $domLink->localName == 'arcroleRef' )
			{
				// Create a hash for the roleRef
				$href = $domLink->getAttributeNodeNS( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK], 'href' );
				$arcroleUri = $domLink->getAttribute("arcroleURI");
				$hash = $href->nodeValue . $arcroleUri;
				if ( in_array( $hash, $this->localLinkbases[ $linkbasePath ]['arcroleRef'] ) )
				{
					$this->log()->taxonomy_validation( "linkbase", "Linkbase arcroleRef has been duplicated",
						array(
							'element' => $domLink->tagName,
							'href' => $href->nodeValue,
							'arcroleUri' => $arcroleUri
						)
					);
				}

				$this->localLinkbases[ $linkbasePath ]['arcroleRef'][] = $hash;
				continue;
			}

			// Check the xlink:type is extended
			if ( ! $domLink->hasAttributeNS( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK], 'type' ) )
			{
				$this->log()->taxonomy_validation( "linkbase", "Child elements of a linkbase element should be extended links",
					array(
						'element' => $domLink->tagName
					)
				);

				continue;
			}

			if ( ( $domLinkAttribute = $domLink->getAttributeNS( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK], 'type' ) ) != 'extended' )
			{
				$this->log()->taxonomy_validation( "linkbase", "Child elements of a linkbase element should be extended links",
					array(
						'element' => $domLink->tagName,
						'value' => $domLinkAttribute
					)
				);
			}

			// All the children of $domLink should be locators or resources or arcs
			foreach( $domLink->childNodes as $linkElement => /** @var DOMElement $domLinkElement */ $domLinkElement )
			{
				if ( $domLinkElement->nodeType != XML_ELEMENT_NODE ) continue;

				// Check the xlink:type is extended
				if ( ! $domLinkElement->hasAttributeNS( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK], 'type' ) )
				{
					$this->log()->taxonomy_validation( "linkbase", "Child elements of a link element should have an xlink type attribute",
						array(
							'element' => $domLinkElement->tagName
						)
					);

					continue;
				}

				$domLinkElementAttribute = $domLinkElement->getAttributeNS( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK], 'type' );

				if ( ! in_array( $domLinkElementAttribute, array( 'arc', 'locator', 'resource' ) ) )
				{
					$this->log()->taxonomy_validation( "linkbase", "Child elements of a link element should have an xlink type of 'arc' or 'locator' or 'resource'",
						array(
							'element' => $domLinkElement->tagName,
							'value' => $domLinkElementAttribute
						)
					);
				}
			}
		}
	}

	/**
	 * Creates a role types array ($this->roleTypes) from information in a taxonomy schema document
	 * @return void
	 */
	private function createRoleTypesList()
	{
		$nodes = $this->xbrlDocument->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] );
		if ( property_exists( $nodes, 'annotation' ) )
		{
			$annotations = $nodes->annotation;
			foreach ( $annotations as $annotationKey => $annotation )
			{
				if ( property_exists( $annotation, 'appinfo' ) )
				{
					$roleTypes = $annotation->appinfo->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->roleType;

					if ( ! count( $roleTypes ) ) continue;

					// Create an array indexing the existing role types
					$rolesByUse = array();
					foreach ( $this->roleTypes as $usedOn => $roleType )
					{
						foreach ( $roleType as $roleUri => $roleDetails )
						{
							if ( ! isset( $rolesByUse[ $roleUri ] ) ) $rolesByUse[ $roleUri ] = array();
							if ( in_array( $usedOn, $rolesByUse[ $roleUri ] ) ) continue;
							$rolesByUse[ $roleUri ][] = $usedOn;
						}
					}

					foreach ( $roleTypes as $roleTypesKey => $roleType )
					{
						$id = (string) $roleType->attributes()->id;
						$roleUri = (string) $roleType->attributes()->roleURI;
						// BMS 2020-09-22 Adds $usedOn and $namne
						$usedOn = (string) $roleType->attributes()->usedOn;
						$name = (string) $roleType->attributes()->name;

						if ( $this->isValidating() )
						{
							if ( $id )
							{
								$pattern = "/^" . SchemaTypes::$ncName . "$/u";
								if ( ! preg_match( $pattern, $id, $matches ) )
								{
									$this->log()->taxonomy_validation( "Role Type", "id attribute is not a valid NCName", array( 'id' => $id ) );
								}
							}
							// BMS 2020-09-22 Updated to check for usedOn and name attributes
							if ( ! ( $roleUri || isset( \XBRL_Global::$taxonomiesToIgnore[ $this->getSchemaLocation() ] ) || ( $usedOn && $name ) ) )
							{
								$this->log()->taxonomy_validation( "Role Type", "roleURI attribute does not exist or is empty", array() );
							}
						}

						$qnames = array();
						$roleUsedOnList = array();

						foreach ( $roleType->usedOn as $usedOnKey => /** @var SimpleXMLElement $usedOn  */ $usedOn )
						{
							// This weird use of DOMElement and DOMXPath occurs because SimpleXMLElement does not
							// allow access to namespaces defined on a specific element. The conformance suite
							// test 205.07 includes an example of the prefix 'foo' being defined twice: once on
							// each of two 'usedOn' elements but with different namespace values.  SimpleXMLElement
							// getDocNamespaces() function only picks up the first use of 'foo' which is a problem
							// because then the link usedOn values appear to be the same when they are not.
							// The use of DOMElement allows these uses of 'foo' to be disambiguated.
							$prefixes = array();
							/**
							 * @var DOMElement $el
							 */
							$el = dom_import_simplexml( $usedOn );
							$xpath = new DOMXPath( $el->ownerDocument );
							foreach ( $xpath->query( 'namespace::*', $el ) as $node )
							{
								$prefixes[ str_replace( array( 'xmlns', ':' ), array( '', '' ), $node->nodeName ) ] = $node->nodeValue;
							}
							$qname = qname( (string) $usedOn, $prefixes );
							$qnames[] = (string) $qname;
							if ( is_null( $qname->prefix ) && $qname->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )
							{
								$qname->prefix = STANDARD_PREFIX_LINK;
							}

							if ( $qname->localName == 'label' && ! isset( $this->context->labelRoleRefs[ $roleUri ] ) )
							{
								$this->context->labelRoleRefs[ $roleUri ] = array(
									'type' => 'simple',
									'href' => basename( $this->schemaLocation ) . "#$id",
									'roleUri' => $roleUri,
								);
							}

							$roleUsedOnList[] = "{$qname->prefix}:{$qname->localName}";
						}

						// BMS 2020-09-22
						// http://www.xbrl.org/2003/xbrl-role-2003-07-31.xsd uses 'usedOn' as an attribute
						// even though the 'roleType' schema definition does not define this attribute
						if ( $usedOn && $name )
						{
							$originalUsedOn = $usedOn;
							$usedOn = strpos( $usedOn, ':' ) === false
								? "link:$usedOn"
								: $usedOn;

							$roleUri = "http://www.xbrl.org/2003/role/$name";
							$id = $name;

							if ( $originalUsedOn == 'label' && ! isset( $this->context->labelRoleRefs[ $roleUri ] ) )
							{
								$this->context->labelRoleRefs[ $roleUri ] = array(
									'type' => 'simple',
									'href' => basename( $this->schemaLocation ) . "#$name",
									'roleUri' => $roleUri,
								);
							}

							$roleUsedOnList[] = $usedOn;

							unset( $originalUsedOn );

						}

						$duplicates = array_filter( array_count_values( $qnames ), function( $count ) { return $count > 1; } );

						if ( $duplicates )
						{
							$this->log()->taxonomy_validation( "5.1.3.4", "Used on values in role type definitions should not be duplicated" , array(
								'usedOn' => implode( ", ", array_map( function( $qname ) use( $prefixes ) {
									$q = qname( $qname, $prefixes ); return "{$q->prefix}:{$q->localName}";
								}, array_keys( $duplicates ) ) ) ,
								'roleType' => "'$roleUri'",
							) );
						}

						// Ignore this test is reading http://www.xbrl.org/2003/xbrl-role-2003-07-31.xsd
						// because it doesn't conform anyway as it uses 'usedO' as an attribute
						if ( ! ( $usedOn && $name ) )
						if ( isset( $rolesByUse[ $roleUri ] ) && count( $rolesByUse[ $roleUri ] ) )
						{
							// Make sure the list of usedOn items are consistent.
							// All of the existing ones MUST be present in the new one.
							if ( count( array_intersect( $rolesByUse[ $roleUri ], $roleUsedOnList ) ) != count( $rolesByUse[ $roleUri ] ) )
							{
								$this->log()->taxonomy_validation( "5.1.3.4", "Role types cannot be redefined.  Within a <roleType> element there MUST NOT be S-Equal <usedOn> elements",
									array(
										'role' => $roleUri,
										'usedOn' => implode( ",", $roleUsedOnList ),
										'alreadyUsedOn' => implode( ",", $rolesByUse[ $roleUri ] ),
									)
								);
							}
						}

						// Look for the new usedOn entries
						if ( isset( $rolesByUse[ $roleUri ] ) )
						{
							$roleUsedOnList = array_diff( $roleUsedOnList, $rolesByUse[ $roleUri ] );
						}

						$rt = array(
							'definition' => (string) $roleType->definition,
							'roleURI' => $roleUri,
							'taxonomy' => $this->schemaLocation,
							'id' => $id,
						);

						foreach ( $roleUsedOnList as $usedOn )
						{
							if ( ! isset( $this->roleTypes[ $usedOn ] ) )
							{
								$this->roleTypes[ $usedOn ] = array();
							}

							$this->roleTypeIds[ $id ] = "$usedOn/$roleUri";
							$this->roleTypes[ $usedOn ][ $roleUri ] = $rt;
							$rolesByUse[ $roleUri ][] = $usedOn;
						}

						unset( $roleUsedOnList );
					}
				}
			}
		}
	}

	/**
	 * Creates a arcrole types array ($this->arcroleTypes) from information in a taxonomy schema document
	 * @return void
	 */
	private function createArcRoleTypesList()
	{
		$nodes = $this->xbrlDocument->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] );
		if ( property_exists( $nodes, 'annotation' ) )
		{
			$annotations = $nodes->annotation;
			foreach ( $annotations as $annotationKey => $annotation )
			{
				if ( property_exists( $annotation, 'appinfo' ) )
				{
					$arcroleTypes = $annotation->appinfo->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->arcroleType;
					if ( ! count( $arcroleTypes ) ) continue;

					// Create an array indexing the arcroles by uri
					$arcrolesByUse = array();
					foreach ( $this->arcroleTypes as $usedOn => $arcroleType )
					{
						foreach ( $arcroleType as $arcroleUri => $arcroleDetails )
						{
							if ( ! isset( $arcrolesByUse[ $arcroleUri ] ) ) $arcrolesByUse[ $arcroleUri ] = array();
							if ( in_array( $usedOn, $arcrolesByUse[ $arcroleUri ] ) ) continue;
							$arcrolesByUse[ $arcroleUri ][ $arcroleDetails['id'] ][ $arcroleDetails['cyclesAllowed'] ][] = $usedOn;
						}
					}

					foreach ( $arcroleTypes as $arcroleTypesKey => $arcroleType )
					{
						$id = (string) $arcroleType->attributes()->id;
						$arcroleUri = (string) $arcroleType->attributes()->arcroleURI;
						$cyclesAllowed = (string) $arcroleType->attributes()->cyclesAllowed;
						// BMS 2020-09-22 Adds $usedOn and $namne
						$usedOn = (string) $arcroleType->attributes()->usedOn;
						$name = (string) $arcroleType->attributes()->name;

						if ( $this->isValidating() )
						{
							if ( property_exists( $arcroleType->attributes(), 'id' ) &&
								 ! empty( $arcroleType->attributes()->id )
							   )
							{
								$pattern = "/^" . SchemaTypes::$ncName . "$/u";
								if ( ! preg_match( $pattern, $arcroleType->attributes()->id, $matches ) )
								{
									$this->log()->taxonomy_validation( "5.1.4.2", "id attribute on <roleType> element is not a valid NCName",
										array(
											'id' => $arcroleType->attributes()->id,
											'xsd' => $this->getTaxonomyXSD()
										)
									);
   								}
							}

							// BMS 2020-09-22 Updated to check for usedOn and name attributes
							if ( ! ( $usedOn && $name ) )
							if ( ! $arcroleUri )
							{
								$this->log()->taxonomy_validation( "5.1.4.1", "The arcrole types MUST include an arcroleURI attribute",
									array(
										'xsd' => $this->getTaxonomyXSD(),
									)
								);
							}
							else if ( ! parse_url( $arcroleUri, PHP_URL_SCHEME ) )
							{
								// XBRL 2.1 conformance test 161 V-15 requires this test claiming section 5.1.3 though I can't find this requirement in the text
								$this->log()->taxonomy_validation( "5.1.3", "The arcrole types MUST include an arcroleURI attribute",
									array(
										'xsd' => $this->getTaxonomyXSD(),
									)
								);
							}

							if ( ! property_exists( $arcroleType->attributes(), 'cyclesAllowed' ) ||
								 empty( $arcroleType->attributes()->cyclesAllowed )
							   )
							{
								$this->log()->taxonomy_validation( "5.1.4.3", "The arcrole types MUST include an cyclesAllowed attribute",
									array(
										'xsd' => $this->getTaxonomyXSD(),
									)
								);
							}
							else if ( ! in_array( $arcroleType->attributes()->cyclesAllowed, array( 'any', 'none', 'undirected' ) ) )
							{
								$this->log()->taxonomy_validation( "5.1.4.3", "The cyclesAllowed attribute on an arcroleType element MUST be one of 'any, 'none' or undirected",
									array(
										'cyclesAllowed' => $arcroleType->attributes()->cyclesAllowed,
										'xsd' => $this->getTaxonomyXSD(),
									)
								);
							}
						}

						$qnames = array();
						$arcroleUseOnList = array();
						foreach ( $arcroleType->usedOn as $usedOnKey => $usedOn )
						{
							// This weird use of DOMElement and DOMXPath occurs because SimpleXMLElement does not
							// allow access to namespaces defined on a specific element. The conformance suite
							// test 205.07 includes an example of the prefix 'foo' being defined twice: once on
							// each of two 'usedOn' elements but with different namespace values.  SimpleXMLElement
							// getDocNamespaces() function only picks up the first use of 'foo' which is a problem
							// because then the link usedOn values appear to be the same when they are not.
							// The use of DOMElement allows these uses of 'foo' to be disambiguated.
							$prefixes = array();
							/**
							 * @var DOMElement $el
							 */
							$el = dom_import_simplexml( $usedOn );
							$xpath = new DOMXPath( $el->ownerDocument );
							foreach ( $xpath->query( 'namespace::*', $el ) as $node )
							{
								$prefixes[ str_replace( array( 'xmlns', ':' ), array( '', '' ), $node->nodeName ) ] = $node->nodeValue;
							}
							$qname = qname( (string) $usedOn, $prefixes );
							$qnames[] = (string) $qname;
							if ( is_null( $qname->prefix ) && $qname->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )
							{
								$qname->prefix = STANDARD_PREFIX_LINK;
							}
							$arcroleUseOnList[] = trim( (string) $usedOn );
						}

						// BMS 2020-09-22
						// http://www.xbrl.org/2003/xbrl-role-2003-07-31.xsd uses 'usedOn' as an attribute
						// even though the 'arcroleType' schema definition does not define this attribute
						if ( $usedOn && $name )
						{
							$qname = strpos( $usedOn, ':' ) === false
								? "link:$usedOn"
								: $usedOn;

							$arcroleUri = "http://www.xbrl.org/2003/role/$name";
							$id = $name;

							$qnames[] = $qname;
							$arcroleUseOnList[] = $qname;

							unset( $qname );

						}

						$duplicates = array_filter( array_count_values( $qnames ), function( $count ) { return $count > 1; } );

						if ( $duplicates )
						{
							$this->log()->taxonomy_validation( "5.1.3.4", "Used on values in role type definitions should not be duplicated" , array(
								'usedOn' => implode( ", ", array_map( function( $qname ) use( $prefixes ) {
									$q = qname( $qname, $prefixes ); return "{$q->prefix}:{$q->localName}";
								}, array_keys( $duplicates ) ) ) ,
								'roleType' => "'$arcroleUri'",
							) );
						}

						// The $arcroleUri already exists
						if ( isset( $arcrolesByUse[ $arcroleUri ] ) )
						{
							// If the id does not already exist, it is an invalid definition
							if ( ! isset( $arcrolesByUse[ $arcroleUri ][ $id ] ) )
							{
								$existingIdsList = array_keys( $arcrolesByUse[ $arcroleUri ] );
								$existingId = $existingIdsList[0]; // There should only ever be one

								$this->log()->taxonomy_validation( "5.1.4", "An existing arcrole type cannot be redefined and use a different 'id' attribute",
									array(
										'arcrole' => "'$arcroleUri'",
										'existing id' => "'$existingId'",
										'new id' => "'$id'",
									)
								);
								continue;
							}

							if ( ! isset( $arcrolesByUse[ $arcroleUri ][ $id ][ $cyclesAllowed ] ) )
							{
								$existingCyclesList = array_keys( $arcrolesByUse[ $arcroleUri ][ $id ] );
								$existingCycles = $existingCyclesList[0]; // There should only ever be one

								$this->log()->taxonomy_validation( "5.1.4", "An existing arcrole type cannot be redefined and use a different 'cyclesAllowed' attribute(s)",
									array(
										'arcrole' => "'$arcroleUri'",
										'existing cyclesAllowed' => "'$existingCycles'",
										'cyclesAllowed' => "'$cyclesAllowed'",
									)
								);
								continue;
							}

							// Getting this far means the arcrole and id exist

							// All of the existing ones MUST be present in the new one.
							if ( count( array_intersect( $arcrolesByUse[ $arcroleUri ][ $id ][ $cyclesAllowed ], $arcroleUseOnList ) ) != count( $arcrolesByUse[ $arcroleUri ][ $id ][ $cyclesAllowed ] ) )
							{
								$this->log()->taxonomy_validation( "5.1.4", "Role types cannot be redefined.  Within a <roleType> element there MUST NOT be S-Equal <usedOn> elements",
									array(
										'role'			=> $arcroleUri,
										'usedOn'		=> implode( ",", $arcroleUseOnList ),
										'alreadyUsedOn'	=> implode( ",",
											array_map( function( $item ) {
												return $item['usedOn'];
											}, $arcrolesByUse[ $arcroleUri ] )
										),
									)
								);
							}
							else
							{
								// If there are no differences then there is nothing to do
								if ( ! count( array_diff( $arcroleUseOnList, $arcrolesByUse[ $arcroleUri ][ $id ][ $cyclesAllowed ] ) ) )
								{
									continue;
								}
							}
						}

						$art = array(
							'definition'	=> (string) $arcroleType->definition,
							'roleURI'		=> $arcroleUri,
							'taxonomy'		=> $this->schemaLocation,
							'id'			=> $id,
							'cyclesAllowed'	=> $cyclesAllowed,
						);

						foreach ( $arcroleUseOnList as $usedOn )
						{
							if ( ! isset( $this->arcroleTypes[ $usedOn ] ) )
							{
								$this->arcroleTypes[ $usedOn ] = array();
							}

							$this->arcroleTypeIds[ $id ] = "$usedOn/$arcroleUri";
							$this->arcroleTypes[ $usedOn ][ $arcroleUri ] = $art;
							$arcrolesByUse[ $arcroleUri ][ $id ][ $cyclesAllowed ][] = $usedOn;
						}
					}
				}
			}
		}
	}

	/**
	 * Creates a linkbase types array ($xbrl->linkbaseTypes) from information in a taxonomy schema document
	 * @return void
	 */
	private function createLinkbaseRefList()
	{
		$linkbaseBase = (string)$this->xbrlDocument->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML ] )->base;

		$nodes = $this->xbrlDocument->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] );
		if ( property_exists( $nodes, 'annotation' ) )
		{
			$annotations = $nodes->annotation;
			foreach( $annotations as $annotation )
			{
				$base = $linkbaseBase;
				$base .= (string)$annotation->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML ] )->base;
				if ( property_exists( $annotation, 'appinfo' ) )
				{
					$appInfo = $annotation->appinfo;

					$base .= (string)$appInfo->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML ] )->base;
					$linkbaseRefs = $appInfo->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->linkbaseRef;

					foreach ( $linkbaseRefs as $linkbaseRefskey => $linkbaseRef )
					{
						$xlinkAttributes = $linkbaseRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
						$attributes = $linkbaseRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML ] );

						$role = (string) $xlinkAttributes->role;

						if ( empty( $role ) ) $role = XBRL_Constants::$anyLinkbaseRef;

						$base .= (string) $attributes->base;
						// BMS 2018-04-24 The base should include all the separators is needs
						// if ( ! empty( $base ) && ! XBRL::endsWith( $base, '/' ) )
						// {
						//	$base .= '/';
						// }

						if ( $base )
						{
							$parts = array_filter( explode( "/", $base ), function( $part) { return $part != '.'; } );
							// This pair of instructions removes any existing terminating node
							// such as x/y/z so that only x/y remains.  /x/y/z/ will not be changed
							array_pop( $parts );
							array_push( $parts, "" );
							$base = implode( "/", $parts );
						}

						$linkbaseRef = array(
							'type' => (string) $xlinkAttributes->type,
							'href' => XBRL::resolve_path( $this->schemaLocation, (string) $xlinkAttributes->href ),
							'role' => $role,
							'arcrole' => (string) $xlinkAttributes->arcrole,
							'title' => (string) $xlinkAttributes->title,
							'base' => $base,
						);

						$this->linkbaseTypes[ $role ][] = $linkbaseRef;
					}

					$linkbases = $appInfo->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->linkbase;

					foreach ( $linkbases as $linkbaseskey => $linkbase )
					{
						// TODO
					}
				}
			}
		}

	}

	/**
	 * This function is to support the conformance suites.  Some test creators have been lazy
	 * in my view by creating one schema document that is to be use in multiple test where
	 * each test has a different linkbase file.  Instead of create a clone of the core schema
	 * and adding relevant linkbase reference they use the testcase instructions to load a
	 * linkbase.
	 *
	 * @param string $href The linkbase file to load
	 * @param string $title The title to use
	 * @param string $type (default: simple)
	 * @param string $role (default: http://www.xbrl.org/2003/role/definitionLinkbaseRef)
	 * @param string $arcrole (default: http://www.w3.org/1999/xlink/properties/linkbase)
	 * @param string $base (default: )
	 */
	public function addLinkbaseRef( $href, $title, $type = "simple", $role = "http://www.xbrl.org/2003/role/definitionLinkbaseRef", $arcrole="http://www.w3.org/1999/xlink/properties/linkbase", $base = "" )
	{
		$linkbaseRef = array(
				'type' => $type,
				'href' => XBRL::resolve_path( $this->schemaLocation, $href ),
				'role' => $role,
				'arcrole' => $arcrole,
				'title' => $title,
				'base' => $base,
		);

		$this->linkbaseTypes[ $role ][] = $linkbaseRef;

		$this->loadSuccess = false;
		$this->linkbasesProcessed = false;

		switch ( $role )
		{
			case XBRL_Constants::$PresentationLinkbaseRef:
				// $this->processPresentationLinkbases( $this->linkbaseTypes[ $role ] );
				$this->processPresentationLinkbase( $linkbaseRef );
				break;

			case XBRL_Constants::$LabelLinkbaseRef:
				// $this->processLabelLinkbases( $this->linkbaseTypes[ $role ] );
				$this->processLabelLinkbase( $linkbaseRef );
				break;

			case XBRL_Constants::$DefinitionLinkbaseRef:
				// $this->processDefinitionLinkbases( $this->linkbaseTypes[ $role ] );
				$this->processDefinitionLinkbase( $linkbaseRef );
				break;

			case XBRL_Constants::$CalculationLinkbaseRef:
				// $this->processCalculationLinkbases( $this->linkbaseTypes[ $role ] );
				$this->processCalculationLinkbase( $linkbaseRef );
				break;

			case XBRL_Constants::$ReferenceLinkbaseRef:
				// $this->processReferenceLinkbases( $linkbaseType );
				$this->processReferenceLinkbase( $linkbaseRef );
				break;

			default:
				// Handle custom linkbases
				$this->processCustomLinkbases( $this->linkbaseTypes[ $role ] );
				break;
		}

		$this->fixupDefinitionRoles(); // Mainly adds a 'paths' index to the 'hierarchy' element of each role.

		$this->linkbasesProcessed = true;
		$this->linkbasesProcessInProgress = false;

		if ( $this->xbrlDocument )
		{
			$this->validateTaxonomy21();
		}
		// BMS 2018-05-03 Changed pass 'true' because without it the directed cycle validation tests are not performed
		$this->validateDimensions( true );
		$this->validateCustom();

		$this->loadSuccess = true;

	}

	/**
	 * Process the <import> tags to load each referenced schema iteratively
	 * @param int $depth
	 * @return void
	 */
	private function importSchemas( $depth = 0 )
	{
		$xsiAttributes = $this->xbrlDocument->attributes( SCHEMA_INSTANCE_NAMESPACE );

		// BMS 2018-06-09 Add this code to interpret any schema location atttibute
		//					because generics test 70012 failed because the generics
		//					was not being loaded otherwise.
		// Load any schemas specified in the schema location attribute.
		if ( isset( $xsiAttributes['schemaLocation'] ) )
		{
			$parts = array_filter( preg_split( "/\s/s",  (string)$xsiAttributes['schemaLocation'] ) );

			$key = "";
			foreach ( $parts as $part )
			{
				if ( empty( $key ) )
				{
					$key = $part;
				}
				else
				{
					// Only load the schema if it is not already loaded
					$schemaLocation = XBRL::resolve_path( $this->getSchemaLocation(), trim( $part ) );
					if ( ! isset( $this->context->schemaFileToNamespace[ $schemaLocation ] ) )
					{
						if ( ! isset( XBRL_Global::$taxonomiesToIgnore[ $schemaLocation ] ) )
						{
							$this->log()->info( str_repeat( "\t", $depth ) . "$schemaLocation" );

							$this->importSchema( $schemaLocation, $depth );
						}
					}

					$key = "";
				}
			}

		}

		foreach ( $this->xbrlDocument->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ] )->import as $key => $node )
		{
			$attributes = $node->attributes();

			if ( ! isset( $attributes['schemaLocation'] ) )
			{
				continue;
			}

			if ( ! isset( $attributes['namespace'] ) )
			{
				continue;
			}

			$namespace = (string) $attributes['namespace'];

			// If this taxonomy already exists bail
			if ( isset( $this->context->importedSchemas[ $namespace ] ) )
			{
				continue;
			}

			// $schemaLocation = XBRL::resolve_path( pathinfo( $this->schemaLocation, PATHINFO_DIRNAME ), trim( (string) $attributes['schemaLocation'] ) );
			$schemaLocation = XBRL::resolve_path( $this->schemaLocation, trim( (string) $attributes['schemaLocation'] ) );

			// $taxonomiesToIgnore is a list of the XML and XBRL core taxonomies that are taken as read so do not need to be processed.
			if ( isset( XBRL_Global::$taxonomiesToIgnore[ $schemaLocation ] ) ) continue;

			$this->log()->info( str_repeat( "\t", $depth ) . "$schemaLocation" );

			$this->importSchema( $schemaLocation, $depth );
		}
	}

	/**
	 * Import a specific schema
	 * @param string $schemaLocation	The filename of the schema to load
	 * @param int $depth				The depth of this schema in the net be processed
	 * @param bool $mainSchema			If true the namespace of the imported schema will become the main schema of the object.
	 * 									This is useful for extension taxonomies.
	 * @return void
	 */
	protected function importSchema( $schemaLocation, $depth = 0, $mainSchema = false )
	{
		$xbrlDocument = XBRL::getXml( $schemaLocation, $this->context );
		if ( $xbrlDocument === null )
		{
			$this->log()->taxonomy_validation( "schema", "Unable to load taxonomy file ",
				array(
					'xsd' => "'$schemaLocation '",
				)
			);
			return;
		}

		$namespace = (string) $xbrlDocument['targetNamespace'];
		$classname = XBRL::class_from_namespace( $namespace );

		/**
		 * @var XBRL $xbrl
		 */
		$xbrl = new $classname();
		$xbrl->context =& $this->context;
		// if ( $mainSchema )
		// {
		// 	$xbrl->context->setIsExtensionTaxonomy();
		// 	$xbrl->context->types->clearElements();
		// }
		// $taxonomy = $xbrl->loadTaxonomy( $schemaLocation, $xbrlDocument, $namespace, $depth + 1 );
		$taxonomy = $xbrl->loadSchema( $schemaLocation, $xbrlDocument, $namespace, $depth + 1 );
		if ( ! $taxonomy )
		{
			$this->log()->warning( "Unable to create taxonomy from schema file '$schemaLocation'" );
			return;
		}
		$this->indirectNamespaces[] = $taxonomy->getNamespace();
		$taxonomy->AddUserNamespace( $this );

		$this->importedFiles[] = $schemaLocation;
		$namespace = $taxonomy->getNamespace();
		// if ( $mainSchema ) $this->namespace = $namespace;
	}

	/**
	 * A temporary variable set to true when a schema is being included
	 * @var bool $including
	 */
	private $including = false;

	/**
	 * Includes the content of one schema inside another.  Really the included file is
	 * an addition to the parent schema so the file name will be the same and the
	 * target namespace MUST be the same
	 *
	 * @param string $taxonomy_schema The file containing the taxonomy xsd
	 * @param SimpleXMLElement $xmlDocument
	 * @param string $targetNamespace The namespace of the taxonomy being loaded
	 * @param int $depth The nesting level of this call.  Used to indent any echo'd output
	 * @return SimpleXMLElement|null
	 */
	private function includeSchemas( $taxonomy_schema, $xmlDocument, $targetNamespace, $depth )
	{
		$includes = $xmlDocument->children( "http://www.w3.org/2001/XMLSchema" )->include;
		if ( ! $includes ) return false;

		$basenames = array();
		foreach ( $includes as $key => $include )
		{
			if ( ! property_exists( $include->attributes(), 'schemaLocation' ) ) continue;
			$basename = basename( (string) $include->attributes()->schemaLocation );
			$basenames[] = $basename;

			$includeFile = XBRL::resolve_path( $taxonomy_schema, (string) $include->attributes()->schemaLocation );
			$includeDocument = $this->getXml( $includeFile, $this->context );

			// Check the file exists
			if ( ! $includeDocument )
			{
				if ( XBRL::isValidating() )
				$this->log()->taxonomy_validation( "5.1", "The xml schema document referenced in the 'include' tag is not valid",
					array(
						'include' => "'$basename'",
					)
				);

				return null;
			}

			// Check the target namespace is the same if one is provided
			$namespace = (string) $includeDocument['targetNamespace'];
			if ( ! empty( $namespace ) && $namespace != $targetNamespace )
			{
				if ( XBRL::isValidating() )
				$this->log()->taxonomy_validation( "5.1", "The namespace of the schema file to include is not the same as that if the main schema file",
					array(
						'expected' => "'$targetNamespace'",
						'actual' => "'$namespace'",
					)
				);

				return null;
			}

			$this->includedFiles[] = $includeFile;
			$this->context->schemaFileToNamespace[ $includeFile ] = $targetNamespace;
			$this->context->schemaFileToNamespace[ basename( $includeFile ) ] = $targetNamespace;

			// Note the use of the schemaLocation variable of *$this* because the component of the
			// included file are really being added to the current schema.
			$this->including = true;
			// $taxonomy = $this->loadSchema( $this->schemaLocation, $includeDocument, $targetNamespace, $depth + 1 );
			$taxonomy = $this->loadSchema( $includeFile, $includeDocument, $targetNamespace, $depth + 1 );
			$this->including = false;
		}

		return true;
	}

	/**
	 * Used by resolve_path to obtain the root element of a uri or file path.
	 * This is necessary because a schema or linkbase uri may be absolute but without a host.
	 *
	 * @param string The file
	 * @return string The root
	 */
	private static function get_schema_root( $file )
	{
		if ( filter_var( $file, FILTER_VALIDATE_URL ) === false )
		{
			// my else codes goes
			if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' )
			{
				// First case is c:\
				if ( strlen( $file ) > 1 && substr( $file, 1, 1 ) === ":" )
					$root = "{$file[0]}:";
				// Second case is a volume
				elseif ( strlen( $file ) > 1 && substr( $file, 0, 2 ) === "\\\\" )
				{
					$pos = strpos( $file, '\\', 2 );

					if ( $pos === false )
						$root = $file;
					else
						$root = substr( $file, 0, $pos );
				}
				// The catch all is that no root is provided
				else
					$root = pathinfo( $file, PATHINFO_EXTENSION ) === ""
						? $file
						: pathinfo( $file, PATHINFO_DIRNAME );
			}
		}
		else
		{
			$components = parse_url( $file );
			$root = "{$components['scheme']}://{$components['host']}";
		}

		return $root;
	}

	/**
	 * Used to compute an absolute path for a resource ($target) with respect to a source.
	 * For example, the presentation linkbase file will be specified as relative to the
	 * location of the host schema.
	 * @param string $source The resource for the source
	 * @param string $target The resource for the target
	 * @return string
	 */
	public static function resolve_path( $source, $target )
	{
		// $target = urldecode( $target );

		$source = str_replace( '\\', '/', $source );
		// Remove any // instances as they confuse the path normalizer but take care to
		// not to remove ://
		$offset = 0;
		while ( true )
		{
			$pos = strpos( $source, "//", $offset );
			if ( $pos === false ) break;
			$offset = $pos + 2;
			// Ignore :// (eg https://)
			if ( $pos > 0 && $source[ $pos-1 ] == ":" ) continue;
			$source = str_replace( "//", "/", $source );
			$offset--;
		}

		// Using the extension to determine if the source is a file or directory reference is problematic unless it is always terminated with a /
		// This is because the source directory path may include a period such as x:/myroot/some.dir-in-a-path/
		$source = XBRL::endsWith( $source, '/' ) || pathinfo( $source, PATHINFO_EXTENSION ) === "" //  || is_dir( $source )
			? $source
			: pathinfo( $source, PATHINFO_DIRNAME );

		$sourceIsUrl = filter_var( $source, FILTER_VALIDATE_URL );
		$targetIsUrl = filter_var( $target, FILTER_VALIDATE_URL );

		// Absolute
		if ( $target && ( filter_var( $target, FILTER_VALIDATE_URL ) || ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' && ( $target[1] === ':' || substr( $target, 0, 2 ) === '\\\\' ) ) ) )
			$path = $target;

		// Relative to root
		elseif ( $target && ( $target[0] === '/' || $target[0] === '\\' ) )
		{
			$root = XBRL::get_schema_root( $source );
			$path = $root . $target;
		}
		// Relative to source
		else
		{
			if ( XBRL::endsWith( $source, ":" ) ) $source .= "/";
			$path = trailingslashit( $source ) . $target;
		}

		// Process the components
		// BMS 2018-06-06 By ignoring a leading slash the effect is to create relative paths on linux
		//				  However, its been done to handle http://xxx sources.  But this is not necessary (see below)
		$parts = explode( '/', $path );
		$safe = array();
		foreach ( $parts as $idx => $part )
		{
			// if ( empty( $part ) || ( '.' === $part ) )
			if ( '.' === $part )
			{
				continue;
			}
			elseif ( '..' === $part )
			{
				array_pop( $safe );
				continue;
			}
			else
			{
				$safe[] = $part;
			}
		}

		// BMS 2108-06-06 See above
		return implode( '/', $safe );

		// Return the "clean" path
		return $sourceIsUrl || $targetIsUrl
			? str_replace( ':/', '://', implode( '/', $safe ) )
			: implode( '/', $safe );
	}

	/**
	 * Accesses and opens an xsd or xml file.  If an http request and caching is enabled
	 * the request will be serviced from the cache.  If caching is enabled and the file
	 * is an http request but no available in the cache it will be cached.
	 *
	 * @param string $url The file to access.  Assumes this is a fully qualified file.
	 * @param XBRL_Global $context The singleton containing the global information
	 * @returns A SimpleXMLElement instance or FALSE
	 */
	public static function getXml( $url, $context )
	{
		$xml = null;
		$xml_string = XBRL::getXmlInner( $url, $context );
		if ( $xml_string )
		{
			try
			{
				// Replace the handler because the status test handler traps any error and terminates the session
				$previousHandler = set_error_handler(null);
				$xml = @new SimpleXMLElement( $xml_string );
				set_error_handler( $previousHandler );
			}
			catch( Exception $ex )
			{

			}

			// Example using xpath
			// $elements = $xml->xpath( "/default:schema/default:element[@name='AccountingPolicies']" );
			if ( $xml !== null )
			{
				$ns = $xml->getDocNamespaces();
				foreach ( $ns as $prefix => $namespace )
				{
					$xml->registerXPathNamespace( empty( $prefix ) ? "default" : $prefix, $namespace );
				}
			}
		}
		else
		{
			// error_log('Empty XML file');
		}
		return $xml;
	}

	/**
	 * Get the XML for a resource
	 * @param string url The file to access.  Assumes this is a fully qualified file.
	 * @param XBRL_Global $context The singleton containing the global information
	 * @return string|boolean
	 */
	public static function getXmlInner( $url, $context )
	{
		// In the future the urls of taxonomies may change.  For example
		// 		http://resources.companieshouse.gov.uk/ef/xbrl/gaap/gcd/2004-12-01/uk-gcd-2004-12-01.xsd
		// now maps to:
		// 		http://resources.companieshouse.gov.uk/ef/xbrl/gaap/gcd/2004-12-01/uk-gcd-2004-12-01.xsd
		// This allows an application using this class to remap the address of a taxonomy.
		// $mapUrl is a function that accepts one url parammeter and returns a url.  If the function does
		// not handle mapping for the url passed as an argument then it should return the passed url.
		global $mapUrl;
		if ( $mapUrl ) $url = $mapUrl( $url );

		// If caching is not being used or the url is a local file get the file and return it
		if ( ! $context->useCache || ! filter_var( $url, FILTER_VALIDATE_URL ) )
		{
			// Replace the handler because the status test handler traps any error and terminates the session
			$previousHandler = set_error_handler(null);
			$result = @file_get_contents( $url );
			set_error_handler( $previousHandler );
			return $result;
		}

		// Its an HTTP request so get it then cache it and update the map
		$path = $context->findCachedFile( $url );
		if ( $path !== false )
		{
			return @file_get_contents( $path );
		}
		else
		{
			// Get the file and store it updating the map
			// Replace the handler because the status test handler traps any error and terminates the session
			$previousHandler = set_error_handler(null);
			$xml = @file_get_contents( $url );
			set_error_handler( $previousHandler );
			if ( $xml === false)
				return null;

			$context->saveCacheFile( $url, $xml );
			return $xml;

		}
	}

	/**
	 * Utility function to return a GUID. If on Windows it will use the PHP functon
	 * com_create_guid otherwise is will use mt_rand to create a GUID of the form
	 * 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX'
	 * @return string
	 */
	public static function GUID()
	{
		if ( function_exists( 'com_create_guid' ) === true )
		{
			return trim( com_create_guid(), '{}' );
		}

		return sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand( 0, 65535 ), mt_rand( 0, 65535 ), mt_rand( 0, 65535 ), mt_rand( 16384, 20479 ), mt_rand( 32768, 49151 ), mt_rand( 0, 65535 ), mt_rand( 0, 65535 ), mt_rand( 0, 65535 ) );
	}

	/**
	 * Return an error string
	 * @return string
	 */
	public static function json_last_error_msg()
	{
		switch ( json_last_error() )
		{
			case JSON_ERROR_NONE:
				return ' - No errors';

			case JSON_ERROR_DEPTH:
				return ' - Maximum stack depth exceeded';

			case JSON_ERROR_STATE_MISMATCH:
				return ' - Underflow or the modes mismatch';

			case JSON_ERROR_CTRL_CHAR:
				return ' - Unexpected control character found';

			case JSON_ERROR_SYNTAX:
				return ' - Syntax error, malformed JSON';

			case JSON_ERROR_UTF8:
				return ' - Malformed UTF-8 characters, possibly incorrectly encoded';

			case JSON_ERROR_INF_OR_NAN:
				return ' - One or more NAN or INF values in the value to be encoded';

			case JSON_ERROR_UNSUPPORTED_TYPE:
				return ' - A value of a type that cannot be encoded was given';

			case JSON_ERROR_INVALID_PROPERTY_NAME:
				return ' - A property name that cannot be encoded was given';

			case JSON_ERROR_UTF16:
				return ' - Malformed UTF-16 characters, possibly incorrectly encoded';

			default:
				return json_last_error() . ' - Unknown error';
		}
	}

	/**
	 * Find out if $haystack starts with $needle
	 * @param string $haystack
	 * @param string $needle
	 * @return bool
	 */
	public static function startsWith( $haystack, $needle )
	{
		return strpos( $haystack, $needle ) === 0;
	}

	/**
	 * Find out if $haystack ends with $needle
	 * @param string $haystack
	 * @param string $needle
	 * @return boolean
	 */
	public static function endsWith( $haystack, $needle )
	{
		$strlen = strlen( $haystack );
		$testlen = strlen( $needle );
		if ( $testlen > $strlen ) return false;
		return substr_compare( $haystack, $needle, $strlen - $testlen, $testlen ) === 0;
	}

	/**
	 * Merges two nodes used as definition hierarchies.  This is needed because the built-in array_merge_recursive is not sufficiently intelligent.
	 * @param array $left Node array
	 * @param array $right Node array
	 * @return boolean|array Returns the merged array if successful or false
	 */
	public static function mergeHierarchies( &$left, &$right )
	{
		// If only one side has anything to merge, take it.
		if ( count( $right ) === 0 ) return $left;
		if ( count( $left ) === 0 ) return $right;

		// There should be just one node at the root and it should have the same name in both left and right
		if ( count( $left ) !== count( $right ) )
		{
			XBRL_Log::getInstance()->warning( "The two hierarchies have unbalanced roots" );
			return false;
		}

		if ( count( $left ) !== 1 )
		{
			XBRL_Log::getInstance()->warning( "The hierarchies have more than one root node" );
			return false;
		}

		$leftRoot = array_keys( $left );
		$rightRoot = array_keys( $right );

		if ( $leftRoot[0] !== $rightRoot[0] )
		{
			XBRL_Log::getInstance()->warning( "The root nodes do not have the same name" );
			return false;
		}

		return XBRL::mergeHierarchiesInner( $left[ $leftRoot[0] ], $right[ $rightRoot[0] ] )
			? $left
			: false;
	}

	/**
	 * Merges two nodes used as definition hierarchies.  This is needed because the built-in array_merge_recursive is not sufficiently intelligent.
	 * @param array $left Node array
	 * @param array $right Node array
	 * @param int depth
	 * @return boolean|array Returns the merged array if successful or false
	 */
	public static function mergeHierarchiesInner( &$left, &$right )
	{
		// Check to see if any changes need to be made to the left and right hierarchies
		$rightMissing = array_diff_key( $left, $right );
		$rightMissingChildren = isset( $rightMissing['children'] );
		foreach ( $rightMissing as $key => $value )
		{
			$right[ $key ] = $left[ $key ];
		}

		ksort( $right );

		$leftMissing = array_diff_key( $right, $left );
		$leftMissingChildren = isset( $leftMissing['children'] );
		$same = array_intersect_key( $left, $right );
		foreach ( $leftMissing as $key => $value )
		{
			$left[ $key ] = $right[ $key ];
		}

		// If the left and right have the save values, make sure the right wins (except children
		foreach ( $same as $key => $value )
		{
			if ( $key === 'children' ) continue;
			$left[ $key ] = $right[ $key ];
		}

		ksort( $left );

		// If there are no children or a children element has been copied from left to right or from right to left then they are now the same
		if ( ! isset( $left['children'] ) || $leftMissingChildren || $rightMissingChildren ) return true;

		$rightMissing = array_diff_key( $left['children'], $right['children'] );
		foreach ( $rightMissing as $key => $value )
		{
			$right['children'][ $key ] = array();
		}

		ksort( $right['children'] );

		$leftMissing = array_diff_key( $right['children'], $left['children'] );
		foreach ( $leftMissing as $key => $value )
		{
			$left['children'][ $key ] = array();
		}

		ksort( $left['children'] );

		foreach ( $left['children'] as $leftKey => $leftNode )
		{
			if ( XBRL::mergeHierarchiesInner( $left['children'][ $leftKey ], $right['children'][ $leftKey ] ) === false ) return false;
		}

		return true;
	}

	/**
	 * Creates a flipped array but allows for the possibility that the same fragment can be referenced by more than one locator
	 * @param array $array Array of locators
	 * @return array
	 */
	public static function array_flip2( $array )
	{
		$result = array();
		foreach ( $array as $key => $value )
		{
			if ( isset( $result[ $value ] ) && in_array( $key, $result[ $value ] ) ) continue;
			$result[ $value ][] = $key;
		}
		return $result;
	}

	/**
	 * Missing array function to reduce an array and have access to the key
	 * @param array $array The array to reduce
	 * @param callable $callback The function to call.
	 * @param mixed $initial The initial value
	 * @return mixed
	 * callable $callback mixed $carry, mixed $value, mixed $key
	 */
	public static function array_reduce_key( &$array, $callback, $initial )
	{
		$carry = $initial;
		foreach ( array_keys( $array) as $key )
		{
			$carry = $callback( $carry, $array[ $key ], $key );
		}

		return $carry;
	}


	/**
	 * Get the member corresponding to a member label.  Dimension members are held in a hierarchical structure.
	 * This function is able to find a member in such a structure.
	 * @param array $nodes A collection of nodes prepresenting the member hierarchy of a hypercube dimension
	 * @param string $label The label corresponding to a dimension member or dimension domain
	 * @return array|boolean Return false if the member is not found or the member.
	 */
	public static function findDimensionMember( $nodes, $label )
	{
		if ( isset( $nodes[ $label ] ) ) return $nodes[ $label ];

		foreach ( $nodes as $nodeKey => $node )
		{
			if ( ! isset( $node['children'] ) ) continue;
			$result = XBRL::findDimensionMember( $node['children'], $label );
			if ( $result !== false ) return $result;
		}

		return false;
	}

	/**
	 * Strips out and resolves ./ and ../ parts of the string
	 * @param string $path
	 * @return mixed
	 */
	public static function normalizePath($path)
	{
		// Test this function using these paths

		// $path = '/var/.////./user/./././..//.//../////../././.././test/////';
		// $path = '/a/b/c/../../../d/e/file.txt'; // should resolve to /d/e/file.txt
		// $path = '/a/b/c/../../d/e/file.txt'; // which resolves to /a/d/e/file.txt
		// $path = 'a/b/../c';

		$patterns = array('~/{2,}~', '~/(\./)+~', '~([^/\.]+/(?R)*\.{2,}/)~', '~\.\./~');
	    $replacements = array('/', '/', '', '');
	    $prefix = '';
		if ( strpos( $path, 'http://' ) === 0 )
		{
			$prefix = 'http:/';
			$path = substr( $path, 6 );
		}
		if ( strpos( $path, 'https://' ) === 0 )
		{
			$prefix = 'https:/';
			$path = substr( $path, 7 );
		}

	    return $prefix . preg_replace($patterns, $replacements, $path);
	}

	/**
	 * Return a list of array members for with the key contains $fragment
	 * @param array $array The associative array with keys to search
	 * @param string $fragment The test fragment to search for
	 * @param bool $caseSensitive
	 * @return array
	 */
	public static function keyContains( $array, $fragment, $caseSensitive = false )
	{
		return XBRL::array_reduce_key( $array, function( $carry, $value, $key ) use( $fragment, $caseSensitive )
		{
			if ( $caseSensitive )
			{
				if ( strpos( $key, $fragment ) === false ) return $carry;
			}
			else
			{
				if ( stripos( $key, $fragment ) === false ) return $carry;
			}
			$carry[ $key ] = $value;
			return $carry;
		}, array() );
	}

	public static function preferredLabelToDescription( $preferredLabelBasename )
	{
		$text = preg_match("/^([a-z]+)/", $preferredLabelBasename, $matches ) ? ucfirst( $matches[1] ) : '';
		if( preg_match_all("/([0-9]+|[A-Z][a-z]*)/", $preferredLabelBasename, $matches ) )
		{
			/** @var string[][] $matches An intellisense warning is generatred without this */
			if ( $text ) $text .= ' ';
			$text .= implode( ' ', array_filter( array_map( function( $item ) { return lcfirst( $item ); }, $matches[1] ), function( $preferredLabelBasename ) { return $preferredLabelBasename != 'label'; } ) );
		}

		if ( $text == 'Label' ) $text = 'Standard';

		return $text;
	}

	/**
	 * Get the link role registry information.
	 * Create the file lrr.json if it does not exist
	 */
	public static function getLRR()
	{
		$filename =  __DIR__ . '/lrr.json';

		if ( ! file_exists( $filename ) )
		{
			error_log('The getLRR function should only be called standalone when a new file needs to be generated.');
			$lrr = array();

			$linkTypes = [];
			$linkTypes = array(
				\XBRL_Constants::$linkFootnote,
				\XBRL_Constants::$linkLabel,
				\XBRL_Constants::$linkReference
			);

			$builtInRoles = array(
				\XBRL_Constants::$labelRoleLabel => "Standard label for a concept.",
				\XBRL_Constants::$labelRoleTerseLabel => "Short label for a concept, often omitting text that should be inferable when the concept is reported in the context of other related concepts.",
				\XBRL_Constants::$labelRoleVerboseLabel => "Extended label for a concept, making sure not to omit text that is required to enable the label to be understood on a stand alone basis.",
				\XBRL_Constants::$labelRolePositiveLabel => "Standard label for a concept when the value of the concept is positive.",
				\XBRL_Constants::$labelRolePositiveTerseLabel => "Terse label for a concept when the value of the concept is positive.",
				\XBRL_Constants::$labelRolePositiveVerboseLabel => "Verbose label for a concept when the value of the concept is positive.",
				\XBRL_Constants::$labelRoleZeroLabel => "Standard label of a concept when the value of the concept is negative.",
				\XBRL_Constants::$labelRoleZeroTerseLabel => "Terse label of a concept when the value of the concept is negative.",
				\XBRL_Constants::$labelRoleZeroVerboseLabel => "Verbose label of a concept when the value of the concept is negative.",
				\XBRL_Constants::$labelRoleNegativeLabel => "Standard label of a concept when the value of the concept is negative.",
				\XBRL_Constants::$labelRoleNegativeTerseLabel => "Terse label of a concept when the value of the concept is negative.",
				\XBRL_Constants::$labelRoleNegativeVerboseLabel => "Verbose label of a concept when the value of the concept is negative.",
				\XBRL_Constants::$labelRoleTotalLabel => "The label for a concept for use in presenting values associated with the concept when it is being reported as the total of a set of other values.",
				\XBRL_Constants::$labelRolePeriodStartLabel => "The label for a concept with instantaneous=\"true\" for use in presenting values associated with the concept when it is being report as a beginning of period value.",
				\XBRL_Constants::$labelRolePeriodEndLabel => "The label for a concept with instantaneous=\"true\" for use in presenting values associated with the concept when it is being reported as an end of period value.",
				\XBRL_Constants::$labelRoleDocumentation => "Documentation of a concept, providing an explanation of its meaning and its appropriate usage and any other documentation deemed necessary.",
				\XBRL_Constants::$labelRoleDefinitionGuidance => "A precise definition of a concept, providing an explanation of its meaning and its appropriate usage.",
				\XBRL_Constants::$labelRoleDisclosureGuidance => "An explanation of the disclosure requirements relating to the concept. Indicates whether the disclosure is mandatory (i.e. prescribed by authoritative literature), recommended (i.e. encouraged by authoritative literature), common practice (i.e. not prescribed by authoritative literature, but disclosure is common place), or structural completeness (i.e. merely included to complete the structure of the taxonomy).",
				\XBRL_Constants::$labelRolePresentationGuidance => "An explanation of the rules guiding presentation (placement and/or labeling) of this concept in the context of other concepts in one or more specific types of business reports. For example, \"Net Surplus should be disclosed on the face of the Profit and Loss statement\".",
				\XBRL_Constants::$labelRolePlacementGuidance => "An explanation of the rules guiding placement of this concept in the context of other concepts in one or more specific types of business reporting.",
				\XBRL_Constants::$labelRoleMeasurementGuidance => "An explanation of the method(s) required to be used when measuring values associated with this concept in business reports.",
				\XBRL_Constants::$labelRoleCommentaryGuidance => "Any other general commentary on the concept that assists in determining definition, disclosure, measurement, presentation or usage.",
				\XBRL_Constants::$labelRoleExampleGuidance => "An example of the type of information intended to be captured by the concept.",
				\XBRL_DFR::$originallyStatedLabel => "Label indicating a concept representing the value that was originally stated."
			);

			foreach ( $builtInRoles as $roleURI => $definition )
			{
				$label = basename( $roleURI );
				$lrr[ $roleURI ] = array(
					'text' => self::preferredLabelToDescription( $label ),
					'definition' => $definition,
					'href' => "http://www.xbrl.org/2003/xbrl-role-2003-07-31.xsd#$label",
					'label' => $label,
					'namespace' => 'http://www.xbrl.org/2003/role',
					'prefix' => 'role'
				);
			}

			$doc = simplexml_load_file("http://www.xbrl.org/lrr/lrr.xml");
			foreach( $doc->children('lrr', true)->roles as $lrrRoles )
			{
				foreach ( $lrrRoles as $lrrRole )
				{
					$href = trim( $lrrRole->authoritativeHref );
					$uri = strstr( $href, '#', true );
					$namespace = '';
					$prefix = '';
					if ( ! isset( $linkTypes[ $uri ] ) )
					{
						$taxonomy = \XBRL::load_taxonomy( $uri );
						$namespace = $taxonomy->getNamespace();
						$prefix = $taxonomy->getPrefix();
						$roleTypes[ $uri ] = $taxonomy->getRoleTypes( $taxonomy->getTaxonomyXSD() );
					}

					$roleURI = trim( $lrrRole->roleURI->__toString() );

					$role = null;
					foreach ( $linkTypes as $linkType )
					{
						if ( ! isset( $roleTypes[ $uri ][ $linkType ][ $roleURI ] ) ) continue;
						$role = $roleTypes[ $uri ][ $linkType ][ $roleURI ];
						break;
					}
					if ( ! $role ) continue;

					$label = basename($roleURI);

					$lrr[ $roleURI ] = array(
						'text' => self::preferredLabelToDescription( $label ),
						'definition' => $role['definition'],
						'href' => $href,
						'label' => $label,
						'namespace' => $namespace,
						'prefix' => $prefix
					);
				}
			}

			file_put_contents( $filename, json_encode( $lrr ) );
		}
		else
		{
			$lrr = json_decode( file_get_contents( $filename ), true );
		}

		return $lrr;
	}

	/**
	 * Get a list of the item$itemTypes types for a specific taxonomy
	 * @param string $category
	 * @param mixed
	 * @param string $clean
	 */
	public function getItemTypes( $category, &$itemTypes, $clean = true )
	{
		$types = $this->context->types->toArray()['types'];

		$getAncestorTypes = function( $qname ) use( &$getAncestorTypes, &$types )
		{
			$typeList = [];
			while ( isset( $types[ $qname ]['parent'] ) )
			{
				$qname = $types[ $qname ]['parent'];

				if ( isset( $types[ $qname ]['types'] ) )
				{
					$typeList[] = implode('|', $types[ $qname ]['types'] );
				}
				else
				{
					$typeList[] = $qname;
				}
			}

			return $typeList;
		};

		$prefix = $this->getPrefix();
		foreach( $types as $qname => &$type )
		{
			if ( ! isset( $type['prefix'] ) || $type['prefix'] != $prefix ) continue;
			$hasParent = isset( $type['contentType'] ) && $type['contentType'] == 'restriction' && isset( $type['parent'] );
			$hasContextRef = isset( $type['attributeGroups'] ) && count( array_intersect_key($type['attributeGroups'][0]['attributes'], array( 'xbrli:contextRef' => 1 ) ) );
			if ( $hasContextRef || $hasParent )
			{
				$typeList = $getAncestorTypes( $qname );
				$itemTypes[ $qname ] = array(
					"category" => $category,
					"numeric" => isset( $type['numeric'] ) && $type['numeric'],
					"description" => preg_replace( "/([A-Z][a-z]+)/", "$1 ", ucfirst( str_replace( 'ItemType', "", $type['name'] ) ) ) . "($prefix)",
					"prefix" => $prefix,
					"unitRef" => isset( $type['attributeGroups'][0]['attributes']['xbrli:unitRef'] ),
					"parent" => $hasParent ? $type['parent'] : false,
					"schemaType" => join(',', $typeList),
					"pattern" => isset( $type['pattern'] ) ? $type['pattern'] : null,
					"values" => isset( $type['values'] ) ? implode( ',', array_map( function( $value ) { return $value['value']; }, $type['values'] ) ) : null
				);
			}
		}

		unset( $type );

		if ( ! $clean ) return;

		// Now all the item types have been gathered run over them to remove any that do not have a parent that is also an item type
		foreach ( $itemTypes as $qname => $type )
		{
			// Base item types (defined in xbrli) will have no parent
			if ( ! $type['parent'] || isset( $itemTypes[ $type['parent'] ] ) ) continue;
			unset( $itemTypes[ $qname ] );
		}
	}

	private static $cacheDTRItems = array();

	/**
	 * Get a list of core item types (xbrli, num, nonnum, dtr)
	 * Note: When forcing a refresh the global context will be reset when
	 * 		 this function is called so call it before any other calls
	 * @param boolean $forceRefresh Forces any existing file to be refreshed
	 * @return array
	 */
	public static function getDTRItems( $forceRefresh = false )
	{
		if ( $forceRefresh || ! self::$cacheDTRItems )
		{
			self::$cacheDTRItems = array();

			$filename =  __DIR__ . '/dtr.json';
			if ( $forceRefresh || ! file_exists( $filename ) )
			{
				$urls = array(
					'http://www.xbrl.org/2003/xbrl-instance-2003-12-31.xsd' => 'General',
					'https://www.xbrl.org/dtr/type/nonNumeric-2009-12-16.xsd' => 'Non-numeric',
					'http://www.xbrl.org/dtr/2013-03-31/numeric-2013-03-31.xsd' => 'Numeric',
					'https://www.xbrl.org/dtr/type/2020-01-21/types.xsd' => 'DTR Types'
				);

				$itemTypes = [];
				foreach( $urls as $url => $category)
				{
					$taxonomy = \XBRL::load_taxonomy( $url );
					$taxonomy->getItemTypes( $category, $itemTypes );
					\XBRL_Global::reset();
					unset( $taxonomy );
				}

				file_put_contents( $filename, json_encode( $itemTypes ) );
				self::$cacheDTRItems = $itemTypes;
			}
			else
			{
				self::$cacheDTRItems = json_decode( file_get_contents( $filename ), true );
			}
		}

		return self::$cacheDTRItems;
	}

	private static $cacheDTRItemDefaults = array();

	/**
	 * Get a list of defaults for each of the item types
	 * @param boolean $forceRefresh Forces any existing file to be refreshed
	 * @return array
	 */
	public static function getDTRItemDefaults()
	{
		if ( ! self::$cacheDTRItemDefaults )
		{
			self::$cacheDTRItemDefaults = array();

			$filename =  __DIR__ . '/dtr-defaults.json';
			if ( ! file_exists( $filename ) ) return array();

			self::$cacheDTRItemDefaults = json_decode( file_get_contents( $filename ), true );
			if ( ! self::$cacheDTRItemDefaults )
			{
				error_log( 'getDTRItemDefaults' . \XBRL::json_last_error_msg() );
				self::$cacheDTRItemDefaults = array();
			}
		}

		return self::$cacheDTRItemDefaults;
	}

	private static $cacheUTRItems = array();

	/**
	 * Get a list of unit types
	 * @param boolean $forceRefresh Forces any existing file to be refreshed
	 * @return array
	 */
	public static function getUTRItems( $forceRefresh = false )
	{
		if ( $forceRefresh || ! self::$cacheUTRItems )
		{
			self::$cacheUTRItems = array();

			$filename =  __DIR__ . '/utr.json';
			if ( $forceRefresh || ! file_exists( $filename ) )
			{
				$url = 'https://www.xbrl.org/utr/2017-07-12/utr.xml';

				// The document element will be <utr>
				$utrDoc = simplexml_load_file( $url );

				/**
				 * @var SimpleXMLElement $units
				 */
				$units = $utrDoc->units;

				$unitTypes = [];

				$fields = array(
					'unitId',
					'unitName',
					'nsUnit',
					'itemType',
					'itemTypeDate',
					'symbol',
					'definition',
					'status',
					'versionDate',
					'nsItemType',
					'numeratorItemType',
					// 'nsNumeratorItemType',
					'denominatorItemType',
					// 'nsDenominatorItemType'
				);

				$prefixes = array_flip( \XBRL_Constants::$standardPrefixes );
				foreach ( $units->unit as /** @var SimpleXMLElement $unit */ $unit )
				{
					foreach( $fields as $field )
					{
						$id = (string)$unit->attributes()->id;
						$unitTypes[ $id ][ $field ] = (string)$unit->$field;
					}

					$unitTypes[ $id ]['prefix'] = (string)$unit->nsUnit && isset( $prefixes[ (string)$unit->nsUnit ] )
						? $prefixes[ (string)$unit->nsUnit ]
						: null;

					$unitTypes[ $id ]['prefixNumerator'] = (string)$unit->nsNumeratorItemType && isset( $prefixes[ (string)$unit->nsNumeratorItemType ] )
						? $prefixes[ (string)$unit->nsNumeratorItemType ]
						: null;

					$unitTypes[ $id ]['prefixDenominator'] = (string)$unit->nsDenominatorItemType && isset( $prefixes[ (string)$unit->nsDenominatorItemType ] )
						? $prefixes[ (string)$unit->nsDenominatorItemType ]
						: null;

					$unitTypes[ $id ] = array_filter( $unitTypes[ $id ] );
				}

				// Reorganize to group by itemType
				$unitTypes = \XBRL::array_reduce_key( $unitTypes, function( $carry, $unit, $id )
				{
					$unit['id'] = $id;
					$carry[ $unit['itemType'] ][ $unit['unitId'] ] = $unit;
					return $carry;
				}, array() );

				// Add kph and mph because they are missing
				$unitTypes["speedItemType"] = array(
					"kph" => array(
			            "unitId" => "kph",
			            "unitName" => "Kilometres\/Hour",
			            "itemType" => "speedItemType",
			            "itemTypeDate" => "2020-08-24",
			            "definition" => "Length \/ Duration",
			            "status" => "LYQUIDITY",
			            "versionDate" => "2012-10-31",
			            "numeratorItemType" => "lengthItemType",
			            "denominatorItemType" => "durationItemType",
			            "prefixNumerator" => "num",
			            "prefixDenominator" => "xbrli"
					),
					"mph" => array(
			            "unitId" => "mph",
			            "unitName" => "Miles\/Hour",
			            "itemType" => "speedItemType",
			            "itemTypeDate" => "2020-08-24",
			            "definition" => "Length \/ Duration",
			            "status" => "LYQUIDITY",
			            "versionDate" => "2012-10-31",
			            "numeratorItemType" => "lengthItemType",
			            "denominatorItemType" => "durationItemType",
			            "prefixNumerator" => "num",
			            "prefixDenominator" => "xbrli"
					)
				);

				file_put_contents( $filename, json_encode( $unitTypes ) );
				self::$cacheUTRItems = $unitTypes;
			}
			else
			{
				self::$cacheUTRItems = json_decode( file_get_contents( $filename ), true );
			}
		}

		return self::$cacheUTRItems;
	}

	/**
	 * Returns a list of item types unique to a taxonomy
	 * @param string $category The category to assign to item types found in the current taxonomy
	 */
	public function getTaxonomyItemTypes( $category )
	{
		// Get the core/DTR item types
		$original = $itemTypes = \XBRL::getDTRItems();
		foreach ( $this->context->importedSchemas as $namespace => /** @var XBRL $xbrl */ $xbrl )
		{
			if ( in_array( $xbrl->getPrefix(), array( 'xbrli', 'num', 'nonnum', 'dtr-types' ) ) ) continue;
			$xbrl->getItemTypes( $category, $itemTypes, false );
		}

		// Clean up after getting the items types for the main taxonomy
		$this->getItemTypes( $category, $itemTypes, true );

		// Return the difference between the built-in and taxonomy item types
		return array_diff_key( $itemTypes, $original );
	}

	/**
	 * Returns a reference to the log function
	 *
	 * @return XBRL_Log
	 */
	protected static function log()
	{
		return XBRL_Log::getInstance();
	}

	/**
	 * Create a hash for an arc
	 *
	 * @param string $elementName		eg labelArc
	 * @param string $linkName			eg labelLink
	 * @param string $linkRole			eg http://www.xbrl.org/2003/role/link
	 * @param string $fromHref			The Href
	 * @param string $toHref			The Href
	 * @param number $order				eg 2 defaults to 1
	 * @param number $weight			eg 2 defaults to null
	 * @param string $preferredLabel	defaults to null
	 * @return string
	 */
	private function equivalenceHash( $elementName, $linkName, $linkRole, $fromHref, $toHref, $order = 1, $weight = null, $preferredLabel = null )
	{
		if ( ! is_numeric( $order ) ) $order = null;
		if ( ! is_numeric( $weight ) ) $weight = null;

		return hash( "sha256", "$elementName-$linkName-$linkRole-$fromHref-$toHref-$order-$weight-$preferredLabel" );
	}

	/**
	 * Process a schema recursively so the xml and xsds referenced are added to the cache implied by $context
	 * @param string $xml
	 * @param SimpleXMLElement $schema
	 * @param XBRL_Global $context
	 * @param XBRL_Log $log
	 */
	public static function processSchema( $xsd, $schema, $context, $log, &$loaded, $loadAllSchemas = false, $includedSchemas = false )
	{
		if ( ! $schema ) return;

		$xsSchemaNamespace = XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_SCHEMA ];

		// Process the linkbases
		if ( $schema->children( $xsSchemaNamespace )->annotation && $schema->children( $xsSchemaNamespace )->annotation->appinfo )
		foreach( $schema->children( $xsSchemaNamespace )->annotation->appinfo->children('link', true)->linkbaseRef as $x => /** @var SimpleXMLElement $element */ $element )
		{
			$location = (string)$element->attributes('xlink', true)->href;
			$href = XBRL::resolve_path( $xsd, $location );
			if ( array_search( $href, $loaded ) !== false ) continue;
			$loaded[] = $href;
			$mapping = \XBRL::getXml( $href, $context );
		}

		$processTag = function( $tag ) use( &$schema, &$context, &$loaded, &$log, $includedSchemas, $loadAllSchemas, $xsd, $xsSchemaNamespace )
		{
			// Process the includes and redefines
			foreach( $schema->children( $xsSchemaNamespace )->$tag as $x => /** @var SimpleXMLElement $element */ $element )
			{
				$location = (string)$element->attributes()->schemaLocation;
				$href = XBRL::resolve_path( $xsd, $location );
				if ( array_search( $href, $loaded ) !== false ) continue;
				if ( ! $loadAllSchemas && XBRL::startsWith( $href, 'http://www.xbrl.org' ) ) continue;
				if ( ! $loadAllSchemas && isset( XBRL_Global::$taxonomiesToIgnore[ $href ] ) ) continue;

				$import = \XBRL::getXml( $href, $context );
				$loaded[] = $href;
				XBRL::processSchema( $href, $import, $context, $log, $loaded, $loadAllSchemas, $includedSchemas );
			}
		};

		$tags = array_merge( $includedSchemas ? array('include','redefine') : array(), array('import') );
		foreach( $tags as $tag )
		{
			$processTag( $tag );
		}
	}
}

/**
 * Call the function
 */
// initialize_xsd_to_class_map();
// With the bootloader in place these are two classes that MUST be loaded
require_once __DIR__ . '/XBRL-Constants.php';

global $use_xbrl_functions;
if ( $use_xbrl_functions )
{
	// If composer autoload is being used this class will be loaded automatically
	if ( ! class_exists( "\lyquidity\XPath2\FunctionTable", true ) )
	{
		$xpathPath = isset( $_ENV['XPATH20_LIBRARY_PATH'] )
			? $_ENV['XPATH20_LIBRARY_PATH']
			: ( defined( 'XPATH20_LIBRARY_PATH' ) ? XPATH20_LIBRARY_PATH : __DIR__ . "/../XPath2/" );

		require_once $xpathPath . '/bootstrap.php';
	}

	require_once __DIR__ . '/XBRL-Functions.php';
	require_once __DIR__ . '/Formulas/Formulas.php';
}
else
{
	// If composer autoload is being used this class will be loaded automatically
	if ( ! class_exists( "\lyquidity\xml\schema\SchemaTypes", true ) )
	{
		$xmlSchemaPath = isset( $_ENV['XML_LIBRARY_PATH'] )
			? $_ENV['XML_LIBRARY_PATH']
			: ( defined( 'XML_LIBRARY_PATH' ) ? XML_LIBRARY_PATH : __DIR__ . "/../xml/" );

		require_once $xmlSchemaPath . '/bootstrap.php';
	}
}

if ( ! function_exists("__") )
{
	/**
	 * A polyfill for the getText __() function
	 * @param string $message
	 * @param string $domain
	 * @return string
	 */
	function __( $message, $domain )
	{
		return "$message\n";
	}
}

/**
 * Load XBRL class files
 * @param string $classname
 */
function xbrl_autoload( $classname )
{
	// Special case
	if ( $classname == "QName" )
	{
		require_once __DIR__ . '/XBRL-QName.php';
		return true;
	}

	if ( substr( $classname, 0, 4 ) != "XBRL" )
	{
		return false;
	}

	if ( strpos( $classname, 'XBRL\\' ) === 0 )
	{
		$classname = substr( $classname, 5 );
	}
	$filename = __DIR__ . "/" . str_replace( "_", "-", $classname . ".php" );
	if ( ! file_exists( $filename ) )
	{
		return false;
	}

	require_once $filename;
}

spl_autoload_register( 'xbrl_autoload' );

/**
 * Called to begin initialization of the class
 * Each taxonomy specific decendent PHP file name will begin 'XBRL-' (case insensitive)
 * and this function will load each one automatically.  This means that when a taxonomy
 * is loaded and if it needs to use a taxonomy specific descendent class, it will be
 * available.
 *
 * @return void
 */
function initialize_xsd_to_class_map()
{
	$xbrl_directory = __DIR__;

	if ( $handle = opendir( $xbrl_directory ) )
	{
		try
		{
			while ( false !== ( $file = readdir( $handle ) ) )
			{
				if ( $file === "." || $file === ".." || $file === "xbrl.php" || strpos( strtolower( $file ), 'xbrl-' ) !== 0 ) continue;

				$filename  = $xbrl_directory . DIRECTORY_SEPARATOR . $file;

				if ( ! is_file( $filename ) ) continue;

				require_once $filename;
			}
		}
		catch(Exception $ex)
		{}

		closedir( $handle );
	}
}

XBRL::constructor();

