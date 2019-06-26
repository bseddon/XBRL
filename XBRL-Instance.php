<?php

/**
 * XBRL Instance
 * @author Bill Seddon
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

use XBRL\Formulas\Exceptions\FormulasException;
use lyquidity\xml\schema\SchemaTypes;
use lyquidity\xml\QName;

/**
 * XBRL instance document class
 *
 * @author Bill Seddon
 * @version 0.9
 *
 * Copyright (C) 2016 Lyquidity Solutions Limited
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

/**
 * Allow the simple xml element to be extended
 */
$utilitiesPath = isset( $_ENV['UTILITIIES_LIBRARY_PATH'] )
	? $_ENV['UTILITIES_LIBRARY_PATH']
	: ( defined( 'UTILITIES_LIBRARY_PATH' ) ? UTILITIES_LIBRARY_PATH : __DIR__ . "/../utilities/" );

require_once $utilitiesPath . 'SimpleXMLElementToArray.php';
require_once $utilitiesPath . 'tuple-dictionary.php';

/**
 * Provides functions to read and interpret instance documents
 * If an instance document references a schema that has not been loaded it will
 * be loaded and stored in a static array indexed by the schema filename.
 * @author Bill Seddon
 */
class XBRL_Instance
{
	/**
	 * A static array of instances so the same taxonomy is used
	 * @var array
	 */
	private static $instance_taxonomy = array();

	/**
	 * A list of the units defined in the instance document
	 * @var array
	 */
	private $units = array();

	/**
	 * A list of the contexts defined in the instance document
	 * @var array
	 */
	private $contexts = array();

	/**
	 * A list of the segments defined in the instance document
	 * @var array
	 */
	private $segments = array();

	/**
	 * A list of the elements defined in the instance document
	 * @var array
	 */
	private $elements = array();

	/**
	 * The instance document XML
	 * @var SimpleXMLElement
	 */
	private $instance_xml = null;

	/**
	 * The namespaces in the instance document
	 * @var array
	 */
	private $instance_namespaces = array();

	/**
	 * The name of the instance document
	 * @var string
	 */
	private $schemaFilename = "";

	/**
	 * An array of taxonomies (XBRL instances) associated with the instance document indexed by namespace
	 * @var array
	 */
	private $taxonomyToNamespaceMap = null;

	/**
	 * The currency of the instance document
	 * @var string
	 */
	private $defaultCurrency = "USD";

	/**
	 * The name of the document used for this instance
	 * @var string
	 */
	private $document_name = "";

	/**
	 * A unique identifier for the document
	 * @var string
	 */
	private $guid = null;

	/**
	 * Any error associated with problems parsing the instance document
	 * @var string
	 */
	private $error = null;

	/**
	 * A list of references to tuples
	 * @var array $tupleRefs
	 */
	private $tupleRefs = array();

	/**
	 * An array of footnote arcs and texts.  The array will have two elements:
	 * One indexed by:
	 * 	  [the standard footnote role]
	 * 		  [lang]
	 * 			  [footnote label] = text
	 *
	 * The other indexed by 'arcs'
	 *	  [element id]
	 *		 [0] = footnote label 1
	 *		 [1] = footnote label x
	 *       ...
	 *
	 * @var array
	 */
	private $footnotes = array();

	/**
	 * An array indexed by the guid of fact entries.  This behaves like
	 * clustered index.
	 *
	 * The content of each item in the array will be the id of the element
	 * holding the corresponding fact entry. A fact entry can then be retrieved
	 * by accessing the element based on the id held against the guid and then
	 * searching for the guid within the list of entries assigned to the
	 * element. Usually be only two or three so the lookup time will be tiny.
	 *
	 * @var array
	 */
	private $uniqueFactIds = array();

	/**
	 * An array of the contexts being used in the report
	 * @var array
	 */
	public	$usedContexts = array();

	/**
	 * True if the caller wants to allow one or more <xbrl> containers to appear in a larger document
	 * @var bool $allowNested
	 */
	public $allowNested = false;

	/**
	 * Location if there is one of compiled taxonomies
	 * @var string
	 */
	private $compiledLocation = null;

	/**
	 * The name of the class to initialize
	 * @var string (Defaults to 'XBRL')
	 */
	private $className = 'XBRL';

	/**
	 * Create an instance from an instance document using a base taxonomy if possible
	 *
	 * @param string $instance_document The file containing the instance information
	 * @param string $compiledLocation The location of compiled taxonomies
	 * @param string $className The name of an XBRL class to initialize (defaults to 'XBRL')
	 * @param bool $useCache (default: false) If true the instance document will be read from the cache
	 * @return XBRL_Instance|bool
	 */
	public static function FromInstanceDocumentWithExtensionTaxonomy( $instance_document, $compiledLocation, $className = 'XBRL', $useCache = false )
	{
		try
		{
			$instance = new XBRL_Instance();
			$instance->compiledLocation = $compiledLocation;
			$instance->className = $className;

			if ( ! $instance->initialise( $instance_document, null, $useCache ) )
			{
				return false;
			}
		}
		catch ( FormulasException $ex)
		{
			throw $ex;
		}
		catch( Exception $ex )
		{
			$instance->error = "Error initialising the instance document. It may contain invalid XML.\n";
			return false;
		}

		return $instance;
	}

	/**
	 * Create an instance from an instance document
	 *
	 * @param string $instance_document The file containing the instance information
	 * @param string $taxonomy_file The taxonomy for the instance document
	 * @param XBRL_Instance $instance A reference to the instance created by this call
	 * @param bool $allowNested (optional: false) True if the caller wants to allow one or more <xbrl> containers to appear in a larger document
	 * @param bool $useCache (default: false) If true the instance document will be read from the cache
	 * @return XBRL_Instance|bool
	 */
	public static function FromInstanceDocument( $instance_document, $taxonomy_file = null, &$instance = null, $allowNested = false, $useCache = false )
	{
		try
		{
			$instance = new XBRL_Instance();
			$instance->allowNested = $allowNested;
			if ( ! $instance->initialise( $instance_document, $taxonomy_file, $useCache ) )
			{
				return false;
			}
		}
		catch ( FormulasException $ex)
		{
			throw $ex;
		}
		catch( Exception $ex )
		{
			$instance->error = "Error initialising the instance document. It may contain invalid XML.\n";
			return false;
		}

		return $instance;
	}

	/**
	 * Creates an instance object from a JSON string, perhaps in a zip file
	 * @param string $cache_path
	 * @param string $cache_basename
	 * @param string $taxonomyNamespace
	 * @param string $compiledTaxonomyFile
	 * @return XBRL_Instance
	 */
	public static function FromInstanceCache( $cache_path, $cache_basename, $taxonomyNamespace, $compiledTaxonomyFile )
	{
		$xbrl = XBRL::load_taxonomy( $compiledTaxonomyFile );
		if ( ! $xbrl ) return false;

		$json = null;

		if ( XBRL::endsWith( $cache_basename, '.zip' ) )
		{
			$zip = new \ZipArchive();
			$zip->open( "$cache_path/$cache_basename" );
			$json = $zip->getFromName( basename( $cache_basename, '.zip' ) . '.json' );
		}
		else
		{
			$json = file_get_contents( "$cache_path/$cache_basename" );
		}

		$instance = new XBRL_Instance();

		$array = json_decode( $json, true );

		$instance->allowNested = $array['allowNested'];
		$instance->contextDimensionMemberList = $array['contextDimensionMemberList'];
		$instance->contexts = $array['contexts'];
		$instance->document_name = $array['document_name'];
		$instance->duplicateFacts = TupleDictionary::fromJSON( $array['duplicateFacts'] );
		$instance->elements = $array['elements'];
		$instance->error = $array['error'];
		$instance->footnotes = $array['footnotes'];
		$instance->guid = $array['guid'];
		$instance->instance_namespaces = $array['instance_namespaces'];
		$instance->segments = $array['segments'];
		$instance->tupleRefs = $array['tupleRefs'];
		$instance->uniqueFactIds = $array['uniqueFactIds'];
		$instance->units = $array['units'];
		$instance->usedContexts = $array['usedContexts'];
		$xml = XBRL::getXml( $instance->document_name, XBRL_Global::getInstance() );
		if ( $xml )
		{
			$instance->instance_xml = $xml;
		}

		$taxonomy = $xbrl->getTaxonomyForNamespace( $taxonomyNamespace );
		XBRL_Instance::$instance_taxonomy[ $taxonomy->getSchemaLocation() ] = $taxonomy;
		$instance->schemaFilename = $taxonomy->getSchemaLocation();
		$instance->defaultCurrency = $taxonomy->getDefaultCurrency();
		$instance->taxonomyToNamespaceMap = $taxonomy->getImportedSchemas();

		return $instance;
	}

	/**
	 * Perist an instance to a file containing a JSON representation
	 * @param string $output_path
	 * @param string $output_basename
	 * @return bool
	 */
	public function toInstanceCache( $output_path, $output_basename )
	{
		$json = json_encode( array(
			// 'instance_taxonomy' => $this->instance_taxonomy,
			'allowNested' => $this->allowNested,
			// 'cacheContextElements' => $this->cacheContextElements,
			// 'cacheDocumentNamespaces' => $this->cacheDocumentNamespaces,
			// 'cacheNamespaces' => $this->cacheNamespaces,
			'contextDimensionMemberList' => $this->contextDimensionMemberList,
			'contexts' => $this->contexts,
			// 'defaultCurrency' => $this->defaultCurrency,
			'document_name' => $this->document_name,
			'duplicateFacts' => $this->duplicateFacts->toJSON(),
			'elements' => $this->elements,
			'error' => $this->error,
			'footnotes' => $this->footnotes,
			'guid' => $this->guid,
			'instance_namespaces' => $this->instance_namespaces,
			// 'instance_xml' => $this->instance_xml,
			// 'schemaFilename' => $this->schemaFilename,
			'segments' => $this->segments,
			// 'taxonomyToNamespaceMap' => array_keys( $this->taxonomyToNamespaceMap ),
			'tupleRefs' => $this->tupleRefs,
			'uniqueFactIds' => $this->uniqueFactIds,
			'units' => $this->units,
			'usedContexts' => $this->usedContexts,
		) );

		file_put_contents( "$output_path/$output_basename.json", $json );
		$zip = new ZipArchive();
		$zip->open( "$output_path/$output_basename.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFile( "$output_path/$output_basename.json", "$output_basename.json" );

		if ( $zip->close() === false )
		{
			XBRL_Log::getInstance()->err( "Error closing zip file" );
			XBRL_Log::getInstance()->err( $zip->getStatusString() );
		}

	}

	/**
	 * Returns the type of the element
	 * @param string $element The element from which to access the type
	 * @return the type as a string
	 */
	public static function getElementType( $element )
	{
		return isset( $element['taxonomy_element'] ) ? $element['taxonomy_element']['type'] : "";
	}

	/**
	 * Resets the lists of existing instance taxonomies
	 * @param string $resetGlobal
	 */
	public static function reset( $resetGlobal = true )
	{
		XBRL_Instance::$instance_taxonomy = array();
		if ( ! $resetGlobal ) return;
		XBRL_Global::reset();
		XBRL_Types::reset();
	}

	/**
	 * The original name of the instance document
	 * @return string
	 */
	public function getDocumentName()
	{
		return $this->document_name;
	}

	/**
	 * Get the entity of the element.  For a simple concept this is easy but for a tuple its necessary to look at the tuple elements
	 * @param array $element
	 * @return string The entity of the element
	 */
	public function getEntityForElement( $element )
	{
		if ( isset( $element['contextRef'] ) )
		{
			return $this->contexts[ $element['contextRef'] ]['entity']['identifier']['value'];
		}

		if ( isset( $element['tuple_elements'] ) )
		{
			foreach ( $element['tuple_elements'] as $elementKey => $tuple_elements )
			{
				foreach ( $tuple_elements as $tupleIndex => $tuple_element )
				{
					$result = $this->getEntityForElement( $tuple_element );
					if ( $result ) return $result;
				}
			}
		}

		throw new Exception( "The element '{$element['taxonomy_element']['id']}' has no context ref but also has no tuple members (getEntityForElement)" );
	}

	/**
	 * Get the year of the element.  For a tuple concept return false.
	 * @param array $element
	 * @return string The entity of the element
	 */
	public function getYearForElement( $element )
	{
		return isset( $element['contextRef'] ) ? substr( $this->contexts[ $element['contextRef'] ]['period']['endDate'], 0, 4 ) : false;
	}

	/**
	 * Any information about document processing issues
	 * @return string
	 */
	public function getError()
	{
		return $this->error;
	}

	/**
	 * A unique identifier for this instance
	 * @return string
	 */
	public function getGuid()
	{
		return $this->guid;
	}

	/**
	 * Get the elements from the instance document
	 * @return InstanceElementsFilter
	 */
	public function getElements()
	{
		return new InstanceElementsFilter( $this, $this->elements );
	}

	/**
	 * Get the instance namespaces
	 */
	public function &getInstanceNamespaces()
	{
		return $this->instance_namespaces;
	}

	/**
	 * Add a namespace to the list
	 * @param string $prefix
	 * @param string $namespace
	 * @return void
	 */
	public function addNamespace( $prefix, $namespace )
	{
		$this->instance_namespaces[ $prefix ] = $namespace;
		$this->cacheNamespaces = null;
	}

	/**
	 * Get the SimpleXMLElement of the document
	 * @return SimpleXMLElement
	 */
	public function getInstanceXml()
	{
		return $this->instance_xml;
	}

	/**
	 * Get the taxonomy for the instance document
	 * @return XBRL
	 */
	public function &getInstanceTaxonomy()
	{
		return XBRL_Instance::$instance_taxonomy[ $this->schemaFilename ];
	}

	/**
	 * Get the instance namespace for a specific prefix
	 * @param string $prefix
	 * @return string The corresponding namespace or null
	 */
	public function getNamespaceForPrefix( $prefix )
	{
		if ( ! isset( $this->instance_namespaces[ $prefix ] ) ) return null;
		return $this->instance_namespaces[ $prefix ];
	}

	/**
	 * Get the segments from the instance document
	 * @return array
	 */
	public function getSegments()
	{
		return $this->segments;
	}

	/**
	 * Get the contexts from the instance document
	 * @return ContextsFilter
	 */
	public function getContexts()
	{
		return new ContextsFilter( $this, $this->contexts );
	}

	/**
	 * Get a specific context
	 * @param string $id The id of the context to retrieve
	 * @return array|null
	 */
	public function getContext( $id )
	{
		return isset( $this->contexts[ $id ] ) ? $this->contexts[ $id ] : null;
	}

	/**
	 * Returns true if the dimension reference can be found in a segment or scenario
	 * @param string $contextRef
	 * @param string $dimension
	 * @param boolean $includeDefault
	 * @return boolean
	 */
	public function hasDimension( $contextRef, $dimension, $includeDefault = false )
	{
		if ( ! isset( $this->contexts[ $contextRef ] ) )
		{
			return false;
		}

		$paths = array(
			array( 'entity', 'segment' ),
			array( 'scenario' ),
			array( 'entity', 'scenario' ),
			array( 'segment' ),
		);

		foreach ( $paths as $path )
		{
			$context = $this->contexts[ $contextRef ];
			foreach ( $path as $element )
			{
				if ( ! isset( $context[ $element ] ) ) continue 2;
				$context = $context[ $element ];
			}

			if ( isset( $context['explicitMember'] ) )
			{
				foreach ( $context['explicitMember'] as $dimensionDefinition )
				{
					if ( $dimensionDefinition['dimension'] == $dimension ) return true;
				}
			}

			if ( isset( $context['typedMember'] ) )
			{
				foreach ( $context['typedMember'] as $dimensionDefinition )
				{
					if ( $dimensionDefinition['dimension'] == $dimension ) return true;
				}
			}
		}

		return false;
	}

	/**
	 * Returns true if the $dimension has a default
	 * @param string $dimension
	 * @return boolean
	 */
	public function hasDefaultDimensionMember( $dimension )
	{
		$qname = qname( $dimension, $this->instance_namespaces );
		$taxonomy = $this->getInstanceTaxonomy()->getTaxonomyForNamespace( $qname->namespaceURI );
		$element = $taxonomy->getElementByName( $qname->localName );
		if ( isset( $taxonomy->context->dimensionDefaults[ "{$taxonomy->getTaxonomyXSD()}#{$element['id']}" ] ) )
		{
			return true;
		}

		return false;
	}

	/**
	 * Get the default currency
	 * @return string
	 */
	public function getDefaultCurrency()
	{
		return $this->defaultCurrency;
	}

	/**
	 * Set the default currency
	 * @param string $defaultCurrency The default currency to use
	 * @return void
	 */
	public function setDefaultCurrency( $defaultCurrency )
	{
		$this->defaultCurrency = $defaultCurrency;
	}

	/**
	 * Get the schema file name of the instances used in this report
	 * @return string
	 */
	public function getSchemaFilename()
	{
		return $this->schemaFilename;
	}

	/**
	 * Get the units of the instances used in this report
	 * @return array
	 */
	public function getUnits()
	{
		return $this->units;
	}

	/**
	 * Get a specific unit
	 * @param string $id The id of the unit to retrieve
	 * @return string|null
	 */
	public function getUnit( $id )
	{
		return isset( $this->units[ $id ] ) ? $this->units[ $id ] : null;
	}

	/**
	 * Get a specific element
	 * @param string $id The id of the element to retrieve
	 * @return string|null
	 */
	public function getElement( $id )
	{
		$result = array();

		if ( isset( $this->elements[ $id ] ) )
		{
			foreach ( $this->elements[ $id ] as $key => &$element )
			{
				if ( isset( $element['parent'] ) ) continue;
				$element['parent'] = 'xbrl';
			}
			unset( $element );

			$result = $this->elements[ $id ];
		}

		if ( ! isset( $this->tupleRefs[ $id ] ) )
		{
			return $result;
		}
		// The rest of this needs sorting
		$tuple = $this->tupleRefs[ $id ];
		$tupleId = key( $tuple );

		if ( ! isset( $this->elements[ $tupleId ] ) )
		{
			return $result;
		}

		// $fact = array();

		foreach ( $tuple[ $tupleId ] as $index )
		{
			foreach ( $this->elements[ $tupleId ][ $index ]['tuple_elements'][ $id ] as $key => $entry )
			{
				if ( ! isset( $entry['tupleid'] ) )
				{
					$entry['tupleid'] = $tupleId;
				}
				$result[ $entry['guid'] ] = $entry;
			}
		}

		return $result;
	}

	/**
	 * Add an entry to a fact element id
	 *
	 * @param string $id The id if the fact being recorded
	 * @param array $entry  The entry arry to be recorded
	 */
	private function addElement( $id, $entry )
	{
		$this->elements[ $id ][] = $entry;
	}

	/**
	 * A cache of a reverse lookup of instance document namespaces
	 * @var array
	 */
	private $cacheDocumentNamespaces = null;

	/**
	 * Returns a prefix for an xbrlInstance namespace
	 * This only looks at the namespaces in the root of the instance dopcument
	 * @param string $namespace
	 */
	public function getPrefixForDocumentNamespace( $namespace )
	{
		if ( is_null( $this->cacheDocumentNamespaces ) )
		{
			$this->cacheDocumentNamespaces = array_flip( $this->instance_xml->getDocNamespaces() );
			// If the default namespace is one of the standards ones then the prefix may be missing
			$standardNamespaces = array_flip( XBRL_Constants::$standardPrefixes );
			$docNamespace = array_search( '', $this->cacheDocumentNamespaces );
			if ( $docNamespace )
			{
				if ( isset( $standardNamespaces[ $docNamespace ] ) )
				{
					$this->cacheDocumentNamespaces[ $docNamespace ] = $standardNamespaces[ $docNamespace ];
				}
				else
				{
					// Get the namespace for the respective namespace
					$taxonomy = $this->getInstanceTaxonomy()->getTaxonomyForNamespace( $docNamespace );
					if ( $taxonomy )
					{
						$this->cacheDocumentNamespaces[ $docNamespace ] = $taxonomy->getPrefix();
					}
				}
			}
		}

		return isset( $this->cacheDocumentNamespaces[ $namespace ] )
			? $this->cacheDocumentNamespaces[ $namespace ]
			: false;
	}

	/**
	 * A cache of a reverse lookup of $this->instance_namespaces
	 * @var array
	 */
	private $cacheNamespaces;

	/**
	 * Get the prefix of a namespace
	 *
	 * @param string $namespace
	 * @return string|false  Will return the prefix or false if one does not exist
	 */
	public function getPrefixForNamespace( $namespace )
	{
		if (
			  ! isset( $this->cacheNamespaces ) ||
			  count( $this->cacheNamespaces ) != count( $this->instance_namespaces )
		   )
		{
			$this->cacheNamespaces = array_flip( $this->instance_namespaces );
			// If the default namespace is one of the standards ones then the prefix may be missing
			$standardNamespaces = array_flip( XBRL_Constants::$standardPrefixes );
			if ( isset( $this->instance_namespaces[''] ) )
			{
				if ( isset( $standardNamespaces[ $this->instance_namespaces[''] ] ) )
				{
					$this->cacheNamespaces[ $this->instance_namespaces[''] ] = $standardNamespaces[ $this->instance_namespaces[''] ];
				}
			}
		}

		return isset( $this->cacheNamespaces[ $namespace ] )
			? $this->cacheNamespaces[ $namespace ]
			: false;
	}

	/**
	 * Get the taxonomy associated with a namespace
	 *
	 * @param string $namespace
	 * @return XBRL|false  Will return the XBRL representation of the schema or false if one does not exist
	 */
	public function getTaxonomyForNamespace( $namespace )
	{
		if ( ! isset( $this->taxonomyToNamespaceMap[ $namespace ] ) ) return false;

		return $this->taxonomyToNamespaceMap[ $namespace ];
	}

	/**
	 * Get the segment of the context
	 * @param array $context
	 * @return array|NULL
	 */
	public function getContextSegment( $context )
	{
		return isset( $context['entity']['segment'] )
			? $context['entity']['segment']
			: ( isset( $context['entity']['scenario'] )
				? $context['entity']['scenario']
				: (isset( $context['segment'] )
					? $context['segment']
					: ( isset( $context['scenario'] )
						? $context['scenario']
						: null
					)
				)
			);
	}

	/**
	 * Caches context refs so they do not need to be accessed more than once
	 * @var array $cacheContextElements
	 */
	private $cacheContextElements = array();

	/**
	 * Get the log instance
	 * @return XBRL_Log
	 */
	private function log()
	{
		return XBRL_Log::getInstance();
	}

	/**
	 * Returns an entry for a guid or false
	 * @param string $guid
	 * @return array|false
	 */
	private function &getEntryForGuid( $guid )
	{
		if ( ! isset( $this->uniqueFactIds[ $guid ] ) )
		{
			return false;
		}

		// Get the element
		$facts = $this->getElement( $this->uniqueFactIds[ $guid ] );

		// And find the entry with the corresponding guid
		foreach ( $facts as $key => &$entry )
		{
			if ( $entry['guid'] == $guid ) return $entry;
		}

		return false;
	}

	/**
	 * Get an array of explicit dimension member elements for a context
	 *
	 * @param array|string $contextRef If a string the name of a context; if an array, one containing the context detail
	 * @param boolean|array[string] $getText If true include the element text in the result.  If the argument is an array it will be an array of preferred labels.
	 * @param string $elementName The name of the element within the entity element to use [segment|scenario]
	 * @return False if the requested context id does not exist or the context is not dimensional otherwise any array of dimension member elements
	 * @TODO If the context does not contain an explicitMember element the function should check the dimensional taxonomy.
	 *       This will require that the primary element is passed as an argument
	 */
	public function getElementsForContext( $contextRef, $getText = false, $elementName = '' )
	{
		if ( isset( $this->cacheContextElements[ $contextRef ][ $elementName ] ) )
		{
			return $this->cacheContextElements[ $contextRef ][ $elementName ];
		}

		if ( $elementName && ( $elementName !== 'segment' && $elementName !== 'scenario' ) )
		{
			$this->log()->err( "The element name must be 'segment' or 'scenario'" );
			return false;
		}

		// If the context parameter provided is a string then lookup the context
		if ( is_string( $contextRef ) )
		{
			if ( ! isset( $this->contexts[ $contextRef ] ) )
			{
				$this->log()->err( "The context '$contextRef' does not exist" );
				return false;
			}

			$context = $this->contexts[ $contextRef ];
		}
		else
			$context = $contextRef;

		// print_r($context);
		if ( ! isset( $context['entity'] ) )
		{
			// $this->log()->err( "Cannot find element 'entity' in the context array for context reference '$contextRef'." );
			return false;
		}

		$entity = $context['entity'];

		$segment = $elementName
			? ( isset( $context[ $elementName ] )
				? $context[ $elementName ]
				: null )
			: $this->getContextSegment( $context );

		if ( is_null( $segment ) )
		{
			if ( ! isset( $entity[ $elementName ] ) )
			{
				// $this->log()->err( "Cannot find element '$elementName' in the 'entity' element of the context array for context reference '$contextRef'." );
				$this->cacheContextElements[ $contextRef ][ $elementName ] = false;
				return false;
			}

			$segment = $entity[ $elementName ];
		}

		if ( isset( $segment['explicitMember'] ) || isset( $segment['typedMember'] ) || isset( $segment['member'] ) )
		{

			$elements = array();

			if ( isset( $segment['explicitMember'] ) )
			{
				$segmentMember = $segment['explicitMember'];

				foreach ( $segmentMember as $key => $member )
				{
					if ( ! isset( $member['dimension'] ) || ! isset( $member['member'] ) )
					{
						$this->log()->err( "The dimension or member component element of the context cannot be found" );
						continue;
					}

					$components = array( 'type' => 'explicitMember' );
					$elementNames = array( 'dimension', 'member' );
					foreach ( $elementNames as $name )
					{
						$component = $member[ $name ];
						$parts = explode( ":", $component );
						$prefix = $parts[0];
						$componentName = $parts[1];

						$components[ $name ] = $this->getElementForReference( $prefix, $componentName, $getText );
						if ( ! $components[ $name ] )
						{
							$components[ $name ] = array( 'namespace' => $parts[0], 'element' => $parts[1] );
						}
					}

					$elements[] = $components;
				}
			}

			if ( isset( $segment['typedMember'] ) )
			{

				$segmentMember = $segment['typedMember'];

				foreach ( $segmentMember as $key => $member )
				{
					if ( ! isset( $member['dimension'] ) || ! isset( $member['member'] ) )
					{
						$this->log()->err( "The dimension or member component element of the context cannot be found" );
						continue;
					}

					$components = array( 'type' => 'typedMember' );
					$component = $member[ 'dimension' ];
					$parts = explode( ":", $component );
					$prefix = $parts[0];
					$componentName = $parts[1];

					$components['dimension'] = $this->getElementForReference( $prefix, $componentName, $getText );

					$components['member'] = $member['member'];

					$elements[] = $components;
				}

			}

			if ( isset( $segment['member'] ) )
			{
				$segmentMember = $segment['member'];

				foreach ( $segmentMember as $key => $member )
				{
					if ( ! isset( $member['name'] ) || ! isset( $member['member'] ) )
					{
						$this->log()->err( "The $elementName component element  cannot be found" );
						continue;
					}

					$elements[] = $member;

					/*
					$components = array();
					$elementNames = array( 'name', 'member' );
					foreach ( $elementNames as $name )
					{
						$component = $member[ $name ];
						$parts = explode( ":", $component );
						$prefix = $parts[0];
						$componentName = $parts[1];

						// $components[ $name ] = $this->getElementForReference( $prefix, $componentName, $getText );
					}

					$elements[] = $components;
					*/
				}
			}

			$this->cacheContextElements[ $contextRef ][ $elementName ] = $elements;
			return $elements;
		}
		else
		{
			// $this->log()->err( "Cannot find element 'explicitMember' in the '$elementName' element of the context array for context reference '$contextRef'." );
			$this->cacheContextElements[ $contextRef ][ $elementName ] = false;
			return false;
		}

	}

	/**
	 * Get an element for the given namespace prefix and element name (not id)
	 * @param string $prefix The namespace prefix to use to get the taxonomy
	 * @param string $name The name of the element to find
	 * @param boolean|array[string] $getDescription If true include the element text in the result.  If the argument is an array it will be an array of preferred labels.
	 * @return array|boolean Returns the element or false if not found
	 */
	public function getElementForReference( $prefix, $name, $getDescription = false )
	{
		if ( ! isset( $this->instance_namespaces[ $prefix ] ) )
		{
			$this->log()->err( "The namespace prefix '$prefix' does not exist in the document namespace list." );
			return false;
		}
		$namespace = $this->instance_namespaces[ $prefix ];

		/**
		 * @var XBRL $taxonomy
		 */
		$taxonomy = $this->getInstanceTaxonomy();
		if ( ! $taxonomy ) return array();

		$taxonomy = $taxonomy->getTaxonomyForNamespace( $namespace );
		if ( ! $taxonomy ) return array();

		$element = $taxonomy->getElementByName( $name );
		if ( ! $element ) return array();

		return $getDescription !== false
			? array( 'element' => $element, 'namespace' => $namespace, 'text' => $taxonomy->getTaxonomyDescriptionForIdWithDefaults( $element['id'], $getDescription ) )
			: array( 'element' => $element, 'namespace' => $namespace );
	}

	/**
	 * Default contructor
	 */
	public function __construct() {
		$this->guid = XBRL::GUID();
	}

	/**
	 * Read the instance document
	 * @param string $instance_document The name of the file containing the instance document
	 * @param string $taxonomy_file A .json or .zip file containing the taxonomy
	 * @param bool $useCache (default: false) If true the instance document will be read from the cache
	 * @return void
	 */
	private function initialise( $instance_document, $taxonomy_file = null, $useCache = false )
	{
		if ( strtolower( pathinfo( $instance_document, PATHINFO_EXTENSION ) ) === STANDARD_PREFIX_SCHEMA )
		{
			$this->log()->err( "The instance document provided is a schema document" );
			return false;
		}

		if ( filter_var( $instance_document, FILTER_VALIDATE_URL ) === false )
		{
			// If the taxonomy file is not a url make sure the file exists
			if ( ! file_exists( $instance_document ) )
			{
				$this->log()->err( "The instance document provided does not exist" );
				return false;
			}

			$useCache = false;
		}

		$this->document_name = $instance_document;

		if ( $useCache )
		{
			$context = XBRL_Global::getInstance();
			if ( $context->useCache )
			{
				$cache_document = $context->findCachedFile( $instance_document );
				if ( $cache_document ) $instance_document = $cache_document;
			}
		}

		libxml_clear_errors();
		// $this->instance_xml = new SimpleXMLElement( file_get_contents( $instance_document ) );
		$this->instance_xml = simplexml_load_file( $instance_document, "SimpleXMLElement", LIBXML_NOBLANKS );
		$xml_errors = libxml_get_errors();

		if ( ! is_a( $this->instance_xml, 'SimpleXMLElement' ) || $xml_errors )
		{
			XBRL_Log::getInstance()->err( array( "There are errors parsing the XML document '$instance_document'" ) + $xml_errors );
			return false;
		}

		if ( $this->instance_xml->getName() !== 'xbrl' )
		{
			if ( ! $this->allowNested )
			{
				$this->log()->instance_validation( "instance", "The <xbrl> element must be the root element of the document", array( 'document' => $instance_document) );
				return false;
			}

			if ( ! $this->instance_xml->registerXPathNamespace( "xbrli", "http://www.xbrl.org/2003/instance" ) )
			{
				echo "";
			}

			// Check to see if the 'xbrl' element is embedded in the document
			$xbrl = $this->instance_xml->xpath( "xbrli:xbrl" );
			if ( ! $xbrl )
			{
				XBRL_Log::getInstance()->err( "The instance document provided is not a valid xbrl document as its root element is not 'xbrl'" );
				return false;
			}

			$this->instance_xml = $xbrl[0];
			$this->instance_namespaces = $this->instance_xml->getDocNamespaces( true );
		}
		else
		{
			$this->instance_xml->registerXPathNamespace( STANDARD_PREFIX_XBRLI, XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] );
			$this->instance_namespaces = $this->instance_xml->getDocNamespaces( true );
		}

		// Check there is a schemaRef element
		$link_children = $this->instance_xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] );
		if ( ! property_exists( $link_children, 'schemaRef' ) )
		{
			XBRL_Log::getInstance()->instance_validation( "4.2", "There must be at least one schemaRef element in the instance document", array() );
			return false;
		}

		// BMS 2018-07-20 Make sure the xbrli namespace exists and is first in the list so units and contexts are read before elements
		$xbrliPrefix = array_search( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ], $this->instance_namespaces );
		if ( $xbrliPrefix === false )
		{
			$xbrliPrefix = "xbrli";
		}
		else
		{
			unset( $this->instance_namespaces[ $xbrliPrefix ] );
		}

		$this->instance_namespaces = array( $xbrliPrefix => XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ) + $this->instance_namespaces;

		// Import schema references
		$base = "";

		$taxonomy_files = array();
		$xbrl = null;

		// There may be more than one schema referenced
		foreach ( $link_children->schemaRef as $elementKey => $schemaRef )
		{
			$xlinkAttributes = $schemaRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );

			// Check there is an href
			if ( ! isset( $xlinkAttributes['href'] ) )
			{
				XBRL_Log::getInstance()->instance_validation( "4.2.2", "There must be an href attribute on the schemaRef element ", array() );
				return false;
			}

			$schemaFilename = (string) $xlinkAttributes['href'];
			// Adjust the url if necessary.  This will be important if the schemaRef is taken
			// from an instance document contained in a package and the schemaRef is local
			// (into the package) but the schema has been added to the cache.
			global $mapUrl;  // A map url function may have been created by one of the package classes.
			if ( $mapUrl )
			{
				$schemaFilename = $mapUrl( $schemaFilename );
			}

			// BMS 2019-03-10 Moved down
			// $resolvedPath = XBRL::resolve_path( $this->document_name, $schemaFilename );

			// Maybe a base as well
			$attributes = $schemaRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XML ] );
			$base = isset( $attributes['base'] )
				? ( (string) $attributes['base'] )
				: "";

			if ( ! empty( $base ) && ! XBRL::endsWith( $base , '/' ) )
			{
				$base .= '/';
			}

			// BMS 2019-03-10 Moved from above
			$resolvedPath = XBRL::resolve_path( $this->document_name, $base . $schemaFilename );

			// The schema may have been loaded already for example by a custom linkbase definition
			// $xsd = pathinfo( $resolvedPath, PATHINFO_BASENAME );
			if ( isset( XBRL_Global::getInstance()->schemaFileToNamespace[ $resolvedPath ] ) )
			{
				$xbrl = XBRL_Global::getInstance()->getTaxonomyForXSD( $resolvedPath );
				continue;
			}

			// BMS 2019-03-10 Why is a local file being used?
			// $path = pathinfo( $instance_document, PATHINFO_DIRNAME ) . "/" . $base . basename( $resolvedPath );
			// if ( ! file_exists( $path ) )
			// {
			// $path = $resolvedPath;
			// }

			// $taxonomy_files[] = $path;
			$taxonomy_files[] = $resolvedPath;
			unset($xlinkAttributes );

		}

		if ( is_null( $taxonomy_file ) )
		{
			if ( count( $taxonomy_files ) )
			{
				// If there is a compiled taxonomy folder then see if any of the xsds
				// are available in compiled form.  If they are load the compiled form.
				$xbrl = null;
				if ( $this->compiledLocation )
				{
					new $this->className();
					foreach ( $taxonomy_files as $taxonomy_file )
					{
						// Begin looking in the instance document folder
						$filename = preg_replace( "|\.xsd$|", "", $taxonomy_file ) . ".zip";
						if ( file_exists( $filename ) )
						{
							$xbrl = XBRL::load_taxonomy( $filename );
							break;
						}

						// Now look for a compiled file in the compiled folder
						$compiledTaxonomyFilename = XBRL::compiled_taxonomy_for_xsd( basename( $taxonomy_file ) );
						if ( ! $compiledTaxonomyFilename ) continue;
						$basename = basename( $compiledTaxonomyFilename );
						if ( ! file_exists( "{$this->compiledLocation}/$basename.zip" ) ) continue;
						// BMS 2019-06-11 Changed to use load_taxonomy.  Why was loadExtensionXSD in this case?
						$xbrl = XBRL::load_taxonomy( "{$this->compiledLocation}/$basename.zip" );
						// $xbrl = XBRL::loadExtensionXSD( $taxonomy_file, $this->className, null, $this->compiledLocation );

						break;
					}

					// If there is no compiled taxonomy for any of the directly referenced schemas then look inside them
					if ( ! $xbrl )
					{
						foreach ( $taxonomy_files as $taxonomy_file )
						{
							if ( filter_var( $taxonomy_file, FILTER_VALIDATE_URL ) && ( $localFile = $context->findCachedFile( $taxonomy_file ) ) )
							{
								$taxonomy_file = $localFile;
							}

							$xsd = simplexml_load_file( $taxonomy_file );
							foreach ( $xsd->children()->import as /** @var SimpleXMLElement $importElement */ $importElement )
							{
								$schemaLocation = (string)$importElement->attributes()->schemaLocation;
								if ( isset( XBRL_Global::$taxonomiesToIgnore[ $schemaLocation ] ) ) continue;

								$resolvedPath = XBRL::resolve_path( $taxonomy_file, $schemaLocation );
								$compiledTaxonomyFilename = XBRL::compiled_taxonomy_for_xsd( basename( $resolvedPath ) );
								if ( ! $compiledTaxonomyFilename ) continue;
								if ( ! file_exists( "{$this->compiledLocation}/" . basename( $compiledTaxonomyFilename ) . ".zip" ) ) continue;
								$xbrl = XBRL::loadExtensionXSD( $taxonomy_file, $this->className, null, $this->compiledLocation );
								break 2;
							}
						}
					}
				}

				if ( ! $xbrl )
				{
					$xbrl = XBRL::load_taxonomy( $taxonomy_files );
				}

				if ( ! $xbrl )
				{
					XBRL_Log::getInstance()->instance_validation( "Taxonomy", "Unable to load the taxonomy", array( 'taxonomies' => implode( ',', $taxonomy_files ) ) );
					return false;
				}

				$this->schemaFilename = $xbrl->getSchemaLocation();
				XBRL_Instance::$instance_taxonomy[ $xbrl->getSchemaLocation() ] = $xbrl;

				foreach ( $taxonomy_files as $taxonomy_file )
				{
					$taxonomy = $xbrl->getTaxonomyForXSD( $taxonomy_file );
					if ( ! $taxonomy ) continue;

					XBRL_Instance::$instance_taxonomy[ $taxonomy->getSchemaLocation() ] = $taxonomy;

					if ( empty( $this->schemaFilename ) )
					{
						$this->schemaFilename = $taxonomy->getSchemaLocation();
						// $this->schemaFilename = $schemaFilename;
					}
				}

			}
			else if ( $xbrl && empty( $this->schemaFilename ) )
			{
				$this->schemaFilename = $xbrl->getSchemaLocation();
				XBRL_Instance::$instance_taxonomy[ $xbrl->getSchemaLocation() ] = $xbrl;
			}
		}
		else
		{
			$xbrl = XBRL::load_taxonomy( $taxonomy_file );
			if ( ! $xbrl ) return false;
			XBRL_Instance::$instance_taxonomy[ $xbrl->getSchemaLocation() ] = $xbrl;
			$this->schemaFilename = $xbrl->getSchemaLocation();
		}

		$xsiAttributes = $this->instance_xml->attributes( SCHEMA_INSTANCE_NAMESPACE );

		// Load any schemas specified in the schema location attribute.  Not sure if
		// this should come before or after processing the schemaRef value
		if ( /* ! $taxonomy_file && */ isset( $xsiAttributes['schemaLocation'] ) )
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
					$key = "";

					// Only load the schema if it not one of the core ones that are pre-loaded
					$href = XBRL::resolve_path( $instance_document, $part );
					// If the schema location is one of the imported schemas ignore
					if ( in_array( $href, $taxonomy_files ) ) continue;
					if ( isset( $this->context->schemaFileToNamespace[ $href ] ) ) continue;

					if ( isset( XBRL_Global::$taxonomiesToIgnore[ $href ] ) ) continue;

					// This taxonomy may already exist in the global cache
					if ( isset( XBRL_Global::getInstance()->schemaFileToNamespace[ $href ] ) )
					{
						$xbrl = XBRL_Global::getInstance()->getTaxonomyForXSD( $href );
					}
					else
					{
						$result = XBRL::withTaxonomy( $href, true );
					}

				}
			}

		}

		$taxonomy = XBRL_Instance::$instance_taxonomy[ $this->schemaFilename ];
		$this->defaultCurrency = $taxonomy->getDefaultCurrency();

		$this->taxonomyToNamespaceMap = $taxonomy->getImportedSchemas();

		// $this->elements = $this->processElement();
		$this->processElement( $base );

		return true;
	}

	/**
	 * Return the schema prefix corresponding to the prefix used locally
	 * @param string $localPrefix
	 * @return string The updated prefix or the local one if there is no schema
	 */
	private function normalizePrefix( $localPrefix )
	{
		if ( $localPrefix == 'xml' )
			return $localPrefix;
		$namespace = $this->getInstanceNamespaces()[ $localPrefix ];
		$taxonomy = $this->getInstanceTaxonomy()->getTaxonomyForNamespace( $namespace );
		return $taxonomy
			? $taxonomy->getPrefix()
			: $localPrefix;
	}

	/**
	 * Process a scenario or segment node
	 *
	 * @param array $validPrefixes A list of the prefixes that are valid in this document
	 * @param SimpleXMLElement $nodes
	 * @param string $componentName 'scenario' or 'segment'
	 * @param string $id Context id
	 * @return boolean|array
	 */
	private function processComponent( $validPrefixes, $nodes, $componentName, $id )
	{
		// Move the current DOMNode to the first element child node
		$moveToChildElement = function( /** @var DOMElement $domElement */ $domElement )
		{
			if ( $domElement->nodeType != XML_ELEMENT_NODE ) throw new Exception( 'The DOM node passed must be an element' );

			if ( ! $domElement->hasChildNodes() ) return null;

			/** @var DOMElement $childElement */
			$childElement = $domElement->firstChild;

			while( $childElement )
			{
				if ( $childElement->nodeType == XML_ELEMENT_NODE )
				{
					return $childElement;
				}

				$childElement = $childElement->nextSibling;
			}

			return null;
		};

		// if ( ! property_exists( $nodes, $componentName ) ) return false;
		if ( $nodes->getName() != $componentName ) return false;

		$types = XBRL_Types::getInstance();

		$component = array();
		$ordinal = 0;

		$domNode = dom_import_simplexml( $nodes );
		// while ( $childNode = $moveToChildElement( $domNode ) )
		foreach ( $domNode->childNodes as $childNode )
		{
			if ( $childNode->nodeType != XML_ELEMENT_NODE ) continue;

			$memberKey = $childNode->localName;
			$member = simplexml_import_dom( $childNode );

			if (
				   ( $childNode->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDI ] ) &&
				   ( $memberKey == 'explicitMember' || $memberKey == 'typedMember' )
			   )
			{
				$memberAttributes = $member->attributes();
				if ( $memberKey == 'explicitMember' )
				{
					$component[ $member->getName() ][] = array(
						'dimension' => (string) $memberAttributes['dimension'],
						'member' => (string) $member,
						'ordinal' => $ordinal
					);
				}
				else
				{
					$members = array();
					$namespaces = array();
					// foreach ( $this->getInstanceNamespaces() as $prefix => $namespace )
					foreach ( $member->getNamespaces( true ) as $prefix => $namespace )
					{
						if ( isset( $namespaces[ $namespace ] ) ) continue;
						$namespaces[ $namespace ] = $prefix;

						$namespaceMembers = $member->children( $namespace );
						if ( ! count( $namespaceMembers ) ) continue;

						/* Need to handle a typed value like this
							<xbrldi:typedMember dimension="concept:MoonDim" xmlns:a="http://xbrl.org/formula/conformance/example">
								<a:dimVal><a:planetVal>a:Earth</a:planetVal></a:dimVal>
							</xbrldi:typedMember>
						 */

						// BMS 2018-01-12 Changed this to accommodate local namespaces
						// $name = empty( $prefix )
						// 	? $namespaceMembers->getName()
						// 	: $prefix . ":" . $namespaceMembers->getName();

						// Check the prefix.  If the namespace is local convert the prefix to the global one.
						$prefixMap = array();
						$localNamespaces = $namespaceMembers->getDocNamespaces(true, false);
						$globalNamespaces = array_flip( $namespaceMembers->getDocNamespaces(false,true) );
						foreach ( $localNamespaces as $localPrefix => $localNamespace )
						{
							if ( ! isset( $globalNamespaces[ $localNamespace ] ) ) continue;
							$globalPrefix = $globalNamespaces[ $localNamespace ];
							$prefixMap[ $localPrefix ] = $globalPrefix;
						}

						if ( empty( $prefix ) )
						{
							$name = $namespaceMembers->getName();
						}
						else
						{
							// Lookup the prefix for the namespace
							if ( isset( $prefixMap[ $prefix ] ) )
							{
								$prefix = $prefixMap[ $prefix ];
							}
							$name = $prefix . ":" . $namespaceMembers->getName();
						}

						$members[ $name ] = array();
						foreach ( $namespaceMembers as $namespaceMember )
						{
							$xml = $namespaceMember->asXML();
							foreach ( $prefixMap as $localPrefix => $globalPrefix )
							{
								$xml = str_replace( "$localPrefix:", "$globalPrefix:", $xml );
							}
							// $value = (string)$namespaceMember;
							// $xml = preg_replace( "/((^<{$name}[^>]*>)|(<\/{$name}>$))/UD", "", $xml );
							$members[ $name ][] = $xml;
						}
					}
					$component[ $member->getName() ][] = array(
						'dimension' => (string) $memberAttributes['dimension'],
						'member' => $members,
						'ordinal' => $ordinal
					);
				}
			}
			else
			{
				// See if the element has a schema definition
				$element = $types->getElement( $childNode->localName, $this->normalizePrefix( $childNode->prefix ) );
				if ( $element )
				{
					// The schema definition may not specify any types
					if ( isset( $element['types'][0] ) && $types->resolvesToBaseType( $element['types'][0], array( 'xs:decimal' ) ) )
					{
						// Check the value is numeric
						if ( ! is_numeric( $childNode->nodeValue ) )
						{
							$this->log()->taxonomy_validation( "4.9", "Element value type mismatch",
								array(
									'element' => $childNode->tagName,
									'value' => $childNode->nodeValue
								)
							);
						}
					}
				}

				if ( $moveToChildElement( $childNode ) )
				{
					// the prefix for the xbrli namespace in the initialize function
					$convert = new SimpleXMLElementToArray( $member );
					$conversion = $convert->toArray( $childNode->namespaceURI, false );
					$conversion['prefix'] = $childNode->prefix;
					// BMS 2018-02-17 Leaving this message so that when the XBRL 2.1 conformance tests
					//				  start failing I will be reminded why.  This has been changed so
					//				  the XBRL Formula fact generation will work.  Look at test 47207 V-02
					// $component[ $conversion['name'] ] = $conversion;
					$conversion['ordinal'] = $ordinal;
					$component['member'][] = $conversion;
				}
				else
				{
					$memberValue = (string) $member;
					$type = null;
					if ( $element )
					{
						// $type = $types->getTypeForDOMNode( $childNode );
						if ( isset( $element['types'][0] ) && ! is_array( $element['types'][0] ) ) $type = $types->getType( $element['types'][0] );
						if ( $type && $types->resolvesToBaseType( $type, array( 'xs:double', 'xsd:double', 'xs:float', 'xsd:float', 'xs:decimal', 'xsd:decimal' ) ) )
						{
							$memberValue = doubleval( $memberValue );
							if ( ! $memberValue && isset( $element['default'] ) ) $memberValue = doubleval( $element['default'] );
						}
						else if ( $type && $types->resolvesToBaseType( $type, array( 'xs:boolean', 'xsd:boolean' ) ) )
						{
							$memberValue = filter_var( $memberValue,  FILTER_VALIDATE_BOOLEAN );
							if ( ! $memberValue && isset( $element['default'] ) ) $memberValue = $element['default'];
						}
						else
						{
							if ( ! $memberValue && isset( $element['default'] ) ) $memberValue = $element['default'];
						}
					}

					$componentMember = array(
						'name' => $childNode->tagName,  // "$nsKey:$name",
						'member' => $memberValue,
						'ordinal' => $ordinal,
						'type' => $type ? $type : "xs:string"
					);

					if ( $childNode->hasAttributes() )
					{
						$attributes = array();

						foreach ( $childNode->attributes as $attributeNode )
						{
							$prefix = $this->normalizePrefix( $childNode->prefix );
							// $prefix = $types->getPrefixForNamespace( $childNode->namespaceURI );
							$type = $types->getType( $attributeNode->localName, $prefix );
							$attributes[ $attributeNode->name ] = array(
								'name' => $attributeNode->name,
								'value' => (string)$attributeNode->nodeValue,
								'type' => is_null( $type['parent'] ) ? null : $type['parent'],
								'prefix' => $type['prefix']
							);
						}

						$componentMember['attributes'] = $attributes;
					}

					// Look for any attributes defined on the type that also have a default value.  If there are, make sure the attribute exists.
					if ( $element && isset( $element['types']['0'] ) && is_array( $element['types']['0'] ) && isset( $element['types'][0]['attributes'] ) )
					{
						foreach ( $element['types']['0']['attributes'] as $type => $attributeType )
						{
							if ( ! isset( $attributeType['default'] ) ) continue;
							if ( isset( $componentMember['attributes'][ $attributeType['name'] ] ) ) continue;

							$componentMember['attributes'][ $attributeType['name'] ] = array(
								'name' => $attributeType['name'],
								'value' => (string)$attributeType['default'],
								'type' => isset( $attributeType['types'][0] ) ? $attributeType['types'][0] : null,
								'prefix' => $attributeType['prefix']
							);

						}
					}

					$component['member'][] = $componentMember;
				}
			}

			$ordinal++;
			// $childNode = $childNode->nextSibling;
		}

		// foreach ( $validPrefixes as $nsKey => $ns )
		// {
		// 	foreach ( $componentElement->children( $ns ) as $memberKey => /* @var $member SimpleXMLElement */ $member )
		// 	{
		// 		if (
		// 			   ( $ns == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLDI ] ) &&
		// 			   ( $memberKey == 'explicitMember' || $memberKey == 'typedMember' )
		// 		   )
		// 		{
		// 			$memberAttributes = $member->attributes();
		// 			if ( $memberKey == 'explicitMember' )
		// 			{
		// 				$component[ $member->getName() ][] = array( 'dimension' => (string) $memberAttributes['dimension'], 'member' => (string) $member );
		// 			}
		// 			else
		// 			{
		// 				$members = array();
		// 				$namespaces = array();
		// 				// foreach ( $this->getInstanceNamespaces() as $prefix => $namespace )
		// 				foreach ( $member->getNamespaces( true ) as $prefix => $namespace )
		// 				{
		// 					if ( isset( $namespaces[ $namespace ] ) ) continue;
		// 					$namespaces[ $namespace ] = $prefix;
        //
		// 					$namespaceMembers = $member->children( $namespace );
		// 					if ( ! count( $namespaceMembers ) ) continue;
        //
		// 					/* Need to handle a typed value like this
		// 						<xbrldi:typedMember dimension="concept:MoonDim" xmlns:a="http://xbrl.org/formula/conformance/example">
		// 							<a:dimVal><a:planetVal>a:Earth</a:planetVal></a:dimVal>
		// 						</xbrldi:typedMember>
		// 					 */
        //
		// 					// BMS 2018-01-12 Changed this to accommodate local namespaces
		// 					// $name = empty( $prefix )
		// 					// 	? $namespaceMembers->getName()
		// 					// 	: $prefix . ":" . $namespaceMembers->getName();
        //
		// 					// Check the prefix.  If the namespace is local convert the prefix to the global one.
		// 					$prefixMap = array();
		// 					$localNamespaces = $namespaceMembers->getDocNamespaces(true, false);
		// 					$globalNamespaces = array_flip( $namespaceMembers->getDocNamespaces(false,true) );
		// 					foreach ( $localNamespaces as $localPrefix => $localNamespace )
		// 					{
		// 						if ( ! isset( $globalNamespaces[ $localNamespace ] ) ) continue;
		// 						$globalPrefix = $globalNamespaces[ $localNamespace ];
		// 						$prefixMap[ $localPrefix ] = $globalPrefix;
		// 					}
        //
		// 					if ( empty( $prefix ) )
		// 					{
		// 						$name = $namespaceMembers->getName();
		// 					}
		// 					else
		// 					{
		// 						// Lookup the prefix for the namespace
		// 						if ( isset( $prefixMap[ $prefix ] ) )
		// 						{
		// 							$prefix = $prefixMap[ $prefix ];
		// 						}
		// 						$name = $prefix . ":" . $namespaceMembers->getName();
		// 					}
        //
		// 					$members[ $name ] = array();
		// 					foreach ( $namespaceMembers as $namespaceMember )
		// 					{
		// 						$xml = $namespaceMember->asXML();
		// 						foreach ( $prefixMap as $localPrefix => $globalPrefix )
		// 						{
		// 							$xml = str_replace( "$localPrefix:", "$globalPrefix:", $xml );
		// 						}
		// 						// $value = (string)$namespaceMember;
		// 						// $xml = preg_replace( "/((^<{$name}[^>]*>)|(<\/{$name}>$))/UD", "", $xml );
		// 						$members[ $name ][] = $xml;
		// 					}
		// 				}
		// 				$component[ $member->getName() ][] = array( 'dimension' => (string) $memberAttributes['dimension'], 'member' => $members );
		// 			}
		// 		}
		// 		else if ( count( $member ) )
		// 		{
		// 			// the prefix for the xbrli namespace in the initialize function
		// 			$convert = new SimpleXMLElementToArray( $member );
		// 			$conversion = $convert->toArray( $ns );
		// 			$conversion['prefix'] = $nsKey;
		// 			// BMS 2018-02-17 Leaving this message so that when the XBRL 2.1 conformance tests
		// 			//				  start failing I will be reminded why.  This has been changed so
		// 			//				  the XBRL Formula fact generation will work.  Look at test 47207 V-02
		// 			// $component[ $conversion['name'] ] = $conversion;
		// 			$component['member'][] = $conversion;
		// 		}
		// 		else
		// 		{
		// 			$domNode = dom_import_simplexml( $member );
		// 			if ( $domNode->prefix != $nsKey ) continue;
		// 			$name = $member->getName();
		// 			$component['member'][] = array( 'name' => "$nsKey:$name", 'member' => (string) $member );
		// 		}
		// 	}
		// }

		return $component;
	}

	/**
	 * A list of duplicate facts.  Duplicate facts require a validate warning.
	 * @var array
	 */
	private $duplicateFacts = null;

	/**
	 * Process the elements to create an array of instance elements.
	 * Any contexts and unit refs are extracted.
	 * Discovered elements are added to the $this->elements arrat
	 *
	 * @param string $base The value of the base attribute
	 * @param SimpleXMLElement $rootElement The Xml root element
	 * @param string $parent The GUID of the parent node
	 * @param string $indent The level of indentation
	 * @return array Returns the discovered elements that are immediate descendants of the $rootElement
	 */
	private function processElement( $base, $rootElement = null, $parent = null, $indent = "" )
	{
		// BMS 2018-07-20 Don't know why this variable is used since $this->elements is updated anyway
		$elements = array();
		$tupleIds = array();
		$tupleRefs = array();
		$types =& XBRL_Types::getInstance();
		$arcroleRefs = array();
		$roleRefs = array();

		if ($rootElement === null) $rootElement = $this->instance_xml;
		if ( ! $this->duplicateFacts ) $this->duplicateFacts = new TupleDictionary();

		$linkbasesLoaded = false;

		$namespaces = array(); // Used to prevent the same namespace with a different prefix being used more than once
		foreach ( $this->instance_namespaces as $prefix => $namespace )
		{
			if ( isset( $namespaces[ $namespace ] ) ) continue;
			$namespaces[ $namespace ] = $prefix;

			// $this->log()->err( $indent . "Namespace $prefix:$namespace" );

			// Workout if the namespace is one of the standard schema
			// If the namespace is the linkbase, xlink, xl, or xsd namespace then ignore as these should have been handled already
			if ( $namespace === XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )
			{
				foreach ( $rootElement->children( $namespace ) as $elementKey => $element )
				{
					$attributes = $element->attributes();

					switch ( $elementKey )
					{
						case "context":

							if ( ! isset( $attributes['id'] ) )
							{
								XBRL_Log::getInstance()->instance_validation( "4.7.1", "All contexts MUST have an id attribute", array() );
								continue;
							}

							$context = array();

							if ( property_exists( $element, 'entity' ) )
							{
								$entityElement = $element->entity;

								$entity = array();

								$identifierElement = $entityElement->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )->identifier;
								if ( $identifierElement )
								{
									$identifierAttributes = $identifierElement->attributes();
									$identifier = array( 'scheme' => (string) $identifierAttributes['scheme'], 'value' => trim( (string) $identifierElement ) );
									$entity['identifier'] = $identifier;
								}

								// Create a list of the usable namespaces
								$invalidNamespaces = array_flip( XBRL_Constants::$standardNamespaces );
								unset( $invalidNamespaces['xbrldi'] );
								$validPrefixes = array_diff( $this->instance_namespaces, $invalidNamespaces );

								// Process segment
								$component = $this->processComponent(
									$validPrefixes,
									// BMS 2018-02-19 Changed pass just the segment node
									// $entityElement->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ),
									$entityElement->segment,
									'segment',
									(string) $attributes['id']
								);
								if ( $component ) $entity['segment'] = $component;

								$context['entity'] = $entity;
							}

							// Process scenario
							$component = $this->processComponent(
								$validPrefixes,
								// BMS 2018-02-19 Changed pass just the segment node
								// $element->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ),
								$element->scenario,
								'scenario',
								(string) $attributes['id']
							);
							if ( $component ) $context['scenario'] = $component;

							// Process period
							if ( property_exists( $element, 'period' ) )
							{
								$periodElement = $element->period;

								$period = array( 'is_instant' => false, 'type' => 'duration' );

								foreach ( $periodElement->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ) as $periodChildKey => $periodChild )
								{
									$date = (string) $periodChild;

									switch ( $periodChild->getName() )
									{
										case 'endDate':

											$period['endDate'] = $date;

											break;

										case 'startDate':

											$period['startDate'] = $date;

											break;

										case 'instant':

											$period['is_instant'] = true;
											$period['type'] = 'instant';
											$period['startDate'] = $date;
											$period['endDate'] = $date;

											break;

										case 'forever':

											$period['type'] = 'forever';
											$period['startDate'] = date( "Y-m-d", 0 );
											$period['endDate'] = date( "Y-m-d", PHP_INT_MAX );

											break;
									}
								}

								$context['period'] = $period;
							}

							$this->contexts[ (string) $attributes['id'] ] = $context;

							break;

						case "unit":

							$addMeasures = function( $measures )
							{
								$result = array();
								foreach ( $measures as $key => $measure )
								{
									$value = (string) $measure;
									// if ( strpos( $value, ":" ) === false )
									// {
									// 	// Add the default prefix (usually xbrli)
									// 	$defaultNamespace = $this->getNamespaceForPrefix( '' );
									// 	$defaultPrefix = $this->getPrefixForNamespace( $defaultNamespace );
                                    //
									// 	$value = "$defaultPrefix:$value";
									// }
									$result[] = $value;
								}
								return $result;
							};

							if ( property_exists( $element, 'measure' ) )
							{
								$measures = $element->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )->measure;

								if ( count( $measures ) == 1 )
								{
									$value = (string) $element->measure;
									// if ( strpos( $value, ":" ) === false )
									// {
									// 	// Add the default prefix (usually xbrli)
									// 	$defaultNamespace = $this->getNamespaceForPrefix( '' );
									// 	$defaultPrefix = $this->getPrefixForNamespace( $defaultNamespace );
                                    //
									// 	$value = "$defaultPrefix:$value";
									// }
									$this->units[ (string) $attributes['id'] ] = $value;
								}
								else if ( count( $measures ) > 1 )
								{
									$this->units[ (string) $attributes['id'] ]['measures'] = $addMeasures( $measures );
								}
							}
							else if ( property_exists( $element, 'divide' ) )
							{
								$divide = $element->divide;

								if ( property_exists( $divide, 'unitNumerator' ) )
								{
									$this->units[ (string) $attributes['id'] ]['divide']['numerator'] = array();

									$unitNumerator = $divide->unitNumerator;
									if ( property_exists( $unitNumerator, 'measure' ) )
									{
										$this->units[ (string) $attributes['id'] ]['divide']['numerator'] += $addMeasures( $unitNumerator->measure );
									}
								}

								if ( property_exists( $divide, 'unitDenominator' ) )
								{
									$this->units[ (string) $attributes['id'] ]['divide']['denominator'] = array();

									$unitDenominator = $divide->unitDenominator;
									if ( property_exists( $unitDenominator, 'measure' ) )
									{
										$this->units[ (string) $attributes['id'] ]['divide']['denominator'] += $addMeasures( $unitDenominator->measure );
									}
								}
							}

							break;

						default:
							$this->log()->instance_validation( "4.9", "Elements other than context and unit are not allowed in the namespace", array( 'namespace' => 'xbrli' ) );
							break;
					}
				}

				continue;
			}
			else if ( $namespace === XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )
			{
				$this->footnotes = array();

				foreach ( $rootElement->children( $namespace ) as $elementKey => $element )
				{
					switch ( $elementKey )
					{
						case 'footnoteLink':
							$this->processFootnoteLinkbase( $element );
							break;

						case 'schemaRef':
							// Validated already
							break;

						// BMS 2017-09-20 Moved roleRef to its own section
						case 'roleRef':

							$xlinkAttributes = $element->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );

							if ( ! property_exists( $xlinkAttributes, 'type' ) || $xlinkAttributes->type != 'simple' )
							{
								$this->log()->instance_validation( "3.5.2.4.1", "A 'type' attribute MUST exist on a roleRef element and MUST have the content 'simple'", array() );
								continue;
							}

							if ( ! property_exists( $xlinkAttributes, 'href' ) )
							{
								$this->log()->instance_validation( "3.5.2.4.2", "A 'href' attribute MUST exist on a roleRef element and MUST be a valid URI", array() );
								continue;
							}

							if ( ! property_exists( $element->attributes(), 'roleURI' ) )
							{
								$this->log()->instance_validation( "3.5.2.4.5", "An 'roleURI' attribute MUST exist on a roleRef element and MUST be a valid URI", array() );
								continue;
							}

							$roleRefHref = (string) $xlinkAttributes->href;
							$roleUri = (string) $element->attributes()->roleURI;
							$fragment = parse_url( $roleRefHref, PHP_URL_FRAGMENT );

							if ( isset( $roleRefs[ $roleUri ] ) )
							{
								$this->log()->instance_validation( "3.5.2.4.5", "Only one roleRef element with the same 'roleURI' attribute value is allowed",
									array(
										'roleUri' => $roleUri
									)
								);
								continue;
							}

							$roleRefs[ $roleUri ] = $fragment;

							break;

						// BMS 2017-09-20 Moved arcroleRef to its own section
						case 'arcroleRef':

							$xlinkAttributes = $element->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );

							if ( ! property_exists( $xlinkAttributes, 'type' ) || $xlinkAttributes->type != 'simple' )
							{
								$this->log()->instance_validation( "3.5.2.5.1", "A 'type' attribute MUST exist on a arcroleRef element and MUST have the content 'simple'", array() );
								continue;
							}

							if ( ! property_exists( $xlinkAttributes, 'href' ) )
							{
								$this->log()->instance_validation( "3.5.2.5.2", "A 'href' attribute MUST exist on a arcroleRef element and MUST be a valid URI", array() );
								continue;
							}

							if ( ! property_exists( $element->attributes(), 'arcroleURI' ) )
							{
								$this->log()->instance_validation( "3.5.2.5.5", "An 'arcroleURI' attribute MUST exist on a arcroleRef element and MUST be a valid URI", array() );
								continue;
							}

							$arcroleRefHref = (string) $xlinkAttributes->href;
							$arcroleUri = (string) $element->attributes()->arcroleURI;
							$fragment = parse_url( $arcroleRefHref, PHP_URL_FRAGMENT );

							if ( isset( $arcroleRefs[ $arcroleUri ] ) )
							{
								$this->log()->instance_validation( "3.5.2.4.5", "Only one arcroleRef element with the same 'arcroleURI' attribute value is allowed",
									array(
										'arcroleUri' => $arcroleUri
									)
								);
								continue;
							}

							$arcroleRefs[ $arcroleUri ] = $fragment;

							break;

						// case 'arcroleRef':
						// case 'roleRef':
						case 'linkbaseRef':

							$xlinkAttributes = $element->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );

							if ( ! property_exists( $xlinkAttributes, 'type' ) || $xlinkAttributes->type != 'simple' )
							{
								$this->log()->instance_validation( "4.3.1", "A 'type' attribute MUST exist on a linkbaseRef element and MUST have the content 'simple'", array() );
								continue;
							}

							if ( ! property_exists( $xlinkAttributes, 'arcrole' ) || $xlinkAttributes->arcrole != XBRL_Constants::$arcRoleLinkbase )
							{
								$this->log()->instance_validation( "4.3.3", "An 'arcrole' attribute MUST exist on a linkbaseRef element and MUST have fixed content 'simple'",
									array(
										'content' => XBRL_Constants::$arcRoleLinkbase,
									)
								);
								continue;
							}

							if ( ! property_exists( $xlinkAttributes, 'href' ) )
							{
								$this->log()->instance_validation( "4.3.2", "A 'href' attribute MUST exist on a linkbaseRef element", array() );
								continue;
							}

							$href = (string) $xlinkAttributes->href;
							$role = property_exists( $xlinkAttributes, 'role' )
								? (string) $xlinkAttributes->role
								: XBRL_Constants::$anyLinkbaseRef;

							$taxonomy = $this->getInstanceTaxonomy();
							if ( $taxonomy->getLinkbase( $href ) )
							{
								continue;
							}

							$linkbaseRef = array(
								'type' => (string) $xlinkAttributes->type,
								// BMS 2017-10-27 This makes the linkbase relative to the location of the schema file which is not correct
								// 'href' => XBRL::resolve_path( $taxonomy->getSchemaLocation(), $href ),
								'href' => XBRL::resolve_path( $this->document_name, $href ),
								'role' => $role,
								'arcrole' => (string) $xlinkAttributes->arcrole,
								'title' => '',
								'base' => $base,
							);

							switch ( $role )
							{
								case XBRL_Constants::$anyLinkbaseRef:

									$taxonomy->processPresentationLinkbase( $linkbaseRef );
									$taxonomy->processLabelLinkbase( $linkbaseRef );
									$taxonomy->processDefinitionLinkbase( $linkbaseRef );
									$taxonomy->processCalculationLinkbase( $linkbaseRef );
									// Handle custom linkbases
									$taxonomy->processCustomLinkbaseArcRoles( $linkbaseRef );

									break;

								case XBRL_Constants::$PresentationLinkbaseRef:
									$taxonomy->processPresentationLinkbase( $linkbaseRef );
									break;

								case XBRL_Constants::$LabelLinkbaseRef:
									$taxonomy->processLabelLinkbase( $linkbaseRef );
									break;

								case XBRL_Constants::$DefinitionLinkbaseRef:
									$taxonomy->processDefinitionLinkbase( $linkbaseRef );
									break;

								case XBRL_Constants::$CalculationLinkbaseRef:
									$taxonomy->processCalculationLinkbase( $linkbaseRef );
									break;

								case XBRL_Constants::$ReferenceLinkbaseRef:
									// TODO Add reference linkbase support
									break;

								default:
									// Handle custom linkbases
									$taxonomy->processCustomLinkbaseArcRoles( $linkbaseRef );
									break;
							}

							$linkbasesLoaded = true;

							break;

						default:
							$this->log()->instance_validation( "4.1", "Elements other than footnoteLink, arcroleRef, roleRef schemaRef, and linkbaseRef are not allowed in the namespace", array( 'namespace' => 'link' ) );
							break;
					}
				}

				if ( $linkbasesLoaded )
				{
					// $taxonomy->validateTaxonomy21();
					$taxonomy->validateDimensions( true );
					// $taxonomy->validateCustom();
				}

				continue;
			}
			else if ( isset( XBRL_Constants::$standardNamespaces[ $namespace ] ) || ! isset( $this->taxonomyToNamespaceMap[ $namespace ] ) )
			{
				if ( count( $rootElement->children( $namespace ) ) )
				{
					$this->log()->instance_validation( "4.1", "There should be no elements in the standard namespace", array( 'namespace' => $namespace ) );
				}

				continue;
			}

			// Lookup the taxonomy
			/**
			 * @var XBRL $taxonomy
			 */
			$taxonomy = $this->taxonomyToNamespaceMap[ $namespace ];

			// Default to the root element
			if ($rootElement === null) $rootElement = $this->instance_xml;

			foreach ( $rootElement->children( $namespace ) as $elementKey => $element )
			{
				// $this->log()->err( "$indent$elementKey" );
				$guid = XBRL::GUID();

				$domNode = dom_import_simplexml( $element );
				if ( $domNode->hasAttributes() )
				{
					foreach ( $domNode->attributes as $attributeNode )
					{
						// Standard attributes like contextRef, unitRef, precision and decimals will have no prefix
						if ( ! $attributeNode->prefix ) continue;

						$attrPrefix = $this->normalizePrefix( $attributeNode->prefix );
						$attribute = $types->getAttribute( $attributeNode->localName, $attrPrefix );

						if ( isset( $attribute['types'][0] ) && $types->resolvesToBaseType( $attribute['types'][0], array( 'xs:decimal' ) ) )
						{
							// Check the value is numeric
							if ( ! is_numeric( $attributeNode->nodeValue ) )
							{
								$this->log()->taxonomy_validation( "4.9", "Attribute value type mismatch",
									array(
										'attribute' => $attributeNode->nodeName,
										'value' => $attributeNode->nodeValue
									)
								);
							}
						}
					}
				}

				$attributes = $element->attributes();

				// Must be an element
				if ( ! $taxonomy || ! $taxonomy_element = &$taxonomy->getElementByName( $elementKey ) )
				{
					$this->log()->instance_validation( "4.6", "The element is not defined in the referenced taxonomy schema", array( 'element' => $elementKey, 'taxonomy' => $taxonomy->getTaxonomyXSD() ) );
					continue;
				}

				// Make space for this element
				if ( ! isset( $elements[ $elementKey ] ) )
				{
					$elements[ $elementKey ] = array();
				}

				$instance_element = array(
					// 'taxonomy_element' => $elementsByName[ $elementKey ],
					'taxonomy_element' => $taxonomy_element,
					'namespace' => $prefix,
					'guid' => $guid,
					'parent' => is_null( $parent ) ? 'xbrl' : $parent,
					'label' => $taxonomy->getTaxonomyXSD() . "#{$taxonomy_element['id'] }",
				);

				$tupleRefs = array();

				// if ( isset( $taxonomy_element['tuple_elements'] ) )
				if ( XBRL::isTuple( $instance_element['taxonomy_element'], $types ) )
				{
					// Tuple element should not include attributes defined by the XBRL specification
					$this->getInstanceTaxonomy()->validateLegalAttributes( $element );

					// The instant element will have children
					// $this->log()->err( "Tuple identified ($prefix:$elementKey)" );
					$tuple_elements = $this->processElement( $base, $element, $guid, "  " );

					// Get the tuple type
					$elementType = $types->getElement( $taxonomy_element['name'], $taxonomy->getPrefix() );
					foreach ( $tuple_elements as $tuple_key => $tuple_element )
					{
						// Picking the first one is OK because they are all the same element
						$tuple_member = reset( $tuple_element );

						// BMS 2017-08-29 Added to allow for a complex type definition permitting any elements
						if ( $elementType )
						{
							// The tuple type may be complex and allow any elements
							$type = isset( $elementType['types'][0] ) ? $elementType['types'][0] : $type;
							if ( is_string( $type ) )
							{
								$type = $types->getType( $type );
							}

							if ( $type['class'] == 'complex' && isset( $type['any'] ) && $type['any'] )
							{
								// Allow this element to be used
								$taxonomy_element['permitted_types'][] = $tuple_member['taxonomy_element']['substitutionGroup'];
							}
						}

						// First see if there are permitted types and if the $tuple_member is one of the permitted types
						if ( ! empty( $taxonomy_element['permitted_types'] ) )
						{
							// Check to find out if the type of $tuple_member is one these
							if ( $types->resolveToSubstitutionGroup( $tuple_member['taxonomy_element']['substitutionGroup'], $taxonomy_element['permitted_types'] ) )
							{
								$namespace = isset( $this->instance_namespaces [ $tuple_member['namespace'] ] )
									? $this->instance_namespaces [ $tuple_member['namespace'] ]
									: '';

								// If it does add the $tuple_member to the $taxonomy_element['tuple_elements'] array
								$taxonomy_element['tuple_elements'][] = array(
									'name' => $tuple_member['taxonomy_element']['name'],
									'namespace' => $namespace,
								);
							}
						}

						// Look for the $tuple_key as a member of the taxonomy element.
						$tuple_defined = function( $type, $elementName, $tuple_elements )
						{
							if ( isset( $type['sequence']['any'] ) )
							{
								return true;
							}

							foreach ( $tuple_elements as $teKey => $te )
							{
								if ( isset( $te['name'] ) && $te['name'] === $elementName &&
									 isset( $te['namespace'] ) && $te['namespace'] === $te['namespace']
								)
									return true;
							}

							return false;
						};

						if ( ! isset( $taxonomy_element['tuple_elements'] ) || ! $tuple_defined( $type, $tuple_key, $taxonomy_element['tuple_elements'] ) )
						{
							$this->log()->instance_validation( "4.9", "Oops! The tuple member is not defined in the taxonomy",
								array(
									'element' => $elementKey,
									'tuple' => $tuple_key,
								)
							);
							continue;
						}

						// $tupleRefs[ $tuple_key ] = $elementKey;
					}

					$instance_element['tuple_elements'] = $tuple_elements;
					$instance_element['tuple'] = $elementKey;

					// tupleID is an idea from the inline-XBRL specification
					if ( isset( $attributes['tupleID'] ) )
					{
						$instance_element['tupleID'] = (string) $attributes['tupleID'];
						$tupleIds[ $instance_element['tupleID'] ] = $elementKey;
					}

					// Store the element
					$elements[ $elementKey ][ $instance_element['guid'] ] = $instance_element;
					$this->elements[ $elementKey ][ $instance_element['guid'] ] = $instance_element;
					$this->uniqueFactIds[ $instance_element['guid'] ] = $elementKey;

					/*
					// A tuple name can be used multiple times in an instance document to create difference contexts
					// If there are tuple references for this key add a reference to the index of the $elementKey
					if ( $tupleRefs )
					{
						// Add the index of the instance_element within the elementKey
						$offset = count( $this->elements[ $elementKey ] ) - 1;
						array_walk( $tupleRefs, function( &$ref ) use( $offset ) {
							$ref = array( $ref => array( $offset ) );
						} );

						$this->tupleRefs = array_merge_recursive( $this->tupleRefs, $tupleRefs );
					}
					*/

					unset( $instance_element );
				}
				else
				{
					// Look for the type
					$schemaElement = $types->getElement( $domNode->localName, $this->normalizePrefix( $domNode->prefix ) );
					$type = isset( $schemaElement['types'][0] )
						? $types->getType( $schemaElement['types'][0] )
						: null;
					$numeric = isset( $schemaElement['types'][0] )
						? $types->resolvesToBaseType( $schemaElement['types'][0], array( 'xs:decimal' ) )
						: false;

					if ( isset( $attributes['precision'] ) )
					{
						$instance_element['precision'] = (string) $attributes['precision'];
					}
					else if ( $numeric )
					{
						// See if there is a default in the type
						if ( isset( $type['attributes']["{$type['prefix']}:precision"]['fixed'] ) )
						{
							$instance_element['precision'] = $type['attributes']["{$type['prefix']}:precision"]['fixed'];
						}
					}

					$instance_element['contextRef'] = (string) $attributes['contextRef'];
					$instance_element['unitRef'] = (string) $attributes['unitRef'];
					$instance_element['value'] = (string) $element;

					// If there is no fact value look to see if there is a default
					if ( ! $instance_element['value'] )
					{
						if ( isset( $schemaElement['default'] ) )
						{
							$instance_element['value'] = $numeric ? doubleval( $schemaElement['default'] ) : $schemaElement['default'];
						}
					}

					if ( isset( $attributes['id'] ) )
					{
						$instance_element['id'] = (string) $attributes['id'];
					}

					if ( isset( $attributes['format'] ) ) // format is an iXBRL attribute
					{
						$instance_element['format'] = (string) $attributes['format'];
					}

					if ( isset( $attributes['sign'] ) )
					{
						$instance_element['sign'] = (string) $attributes['sign'];
					}

					if ( isset( $attributes['decimals'] ) )
					{
						$instance_element['decimals'] = (string) $attributes['decimals'];
					}
					else if ( $numeric )
					{
						// See if there is a default in the type
						if ( isset( $type['attributes']["{$type['prefix']}:decimals"]['default'] ) )
						{
							$instance_element['decimals'] = $type['attributes']["{$type['prefix']}:decimals"]['default'];
						}
					}

					if ( isset( $attributes['tupleRef'] ) )
					{
						$instance_element['tupleRef'] = (string) $attributes['tupleRef'];
						$tupleRefs[ $instance_element['tupleRef'] ][] = $elementKey;
					}

					if ( isset( $attributes['order'] ) )
					{
						$instance_element['order'] = (string) $attributes['order'];
					}

					$xsiAttributes = $element->attributes( XBRL_Constants::$standardPrefixes['xsi'] );
					if ( property_exists( $xsiAttributes, 'nil' ) )
					{
						$instance_element['nil'] = (string) $xsiAttributes->nil;
					}

					// Make sure the element is not a duplicate

					foreach ( $elements[ $elementKey ] as $guid => $fact )
					{
						if (  $fact['parent'] != $instance_element['parent'] ||
							  ! XBRL_Equality::unit_equal( $fact['unitRef'], $instance_element['unitRef'], $types, $namespaces ) ||
							  ( $fact['contextRef'] ) != $instance_element['contextRef'] &&
								! XBRL_Equality::context_equal( $this->getContext( $fact['contextRef'] ), $this->getContext( $instance_element['contextRef'] )
							  )
						)
						{
							continue;
						}

						// if ( $numeric )
						// {
						// 	if ( $this->getNumericPresentation( $fact ) != $this->getNumericPresentation( $instance_element ) )
						// 	{
						// 		continue;
						// 	}
						// }
						// else
						// {
						// 	if ( $fact['value'] != $instance_element['value'] )
						// 	{
						// 		continue;
						// 	}
						// }

						$elements[ $elementKey ][ $guid ]['duplicate'] = true;
						$this->elements[ $elementKey ][ $guid ]['duplicate'] = true;
						$instance_element['duplicate'] = true;
						$this->duplicateFacts->addValue( [ $instance_element['taxonomy_element']['id'], $instance_element['parent'], $instance_element['contextRef'], $instance_element['unitRef'], $instance_element['guid'] ], 1 );
						$this->duplicateFacts->addValue( [ $fact['taxonomy_element']['id'], $fact['parent'], $fact['contextRef'], $fact['unitRef'], $fact['guid'] ], 1 );
						// continue 2;

					}

					// Store the element
					$elements[ $elementKey ][ $instance_element['guid'] ] = $instance_element;
					$this->elements[ $elementKey ][ $instance_element['guid'] ] = $instance_element;
					$this->uniqueFactIds[ $instance_element['guid'] ] = $elementKey;

					unset( $instance_element );
				}
			}
		}

		// Fixup tuple ids/refs (these are from the inline XBRL specification)
		foreach ( $tupleRefs as $tupleRefKey => $elementRefs )
		{
			foreach ( $elementRefs as $elementRefKey => $elementKey )
			{
				if ( ! isset( $tupleIds[ $tupleRefKey ] ) ) continue;

				// Find the element
				$tuple_element_id = $tupleIds[ $tupleRefKey ];
				// The tuple root element must exist
				if ( ! isset( $elements[ $tuple_element_id ] ) ) continue;
				// As must the tuple member element
				if ( ! isset( $elements[ $elementKey ] ) ) continue;

				foreach ( $elements[ $tuple_element_id ] as $tupleElementKey => &$tuple_element )
				{
					if ( ! isset( $tuple_element['taxonomy_element']['name'] ) || $tuple_element['taxonomy_element']['name'] !== $tuple_element_id ) continue;
					if ( ! isset( $tuple_element['taxonomy_element']['tuple_elements'] ) ) continue;

					// The $elementkey should be one of the named members of the ['taxonomy_element']['tuple_element'] array
					if ( ! array_filter( $tuple_element['taxonomy_element']['tuple_elements'], function( $item ) use( $elementKey ) { return $item['name'] === $elementKey; } ) ) continue;

					// It is so add the element to list of tuple elements and delete it from the elements list
					$tuple_element['tuple_elements'][ $elementKey ] = $elements[ $elementKey ];
					unset( $elements[ $elementKey ] );
				}
			}
		}

		return $elements;
	}

	/**
	 * Process footnotes and populate the $this->footnotes variable with locators, arcs and labels
	 * This is a simplified version of the code in XBRL processLabelLinkbase().  Simplified because
	 * footnote locators can only be to elements in the same document.
	 *
	 * @param array $footnoteLink The element containing the link base type to process
	 * @return boolean
	 */
	private function processFootnoteLinkbase( $footnoteLink )
	{
		$taxonomy_basename = $this->getInstanceTaxonomy();
		$attributes = $footnoteLink->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
		$linkRole = (string)$attributes->role;

		// Note: The XBRL specification explicitly defines an element calle 'loc' to be the locator.  However,
		//		 the XLink specification allows an element with any local name so long as it has an attribute
		//		 called 'type' with a value of 'locator'.
		$locators = array();
		foreach ( $footnoteLink->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->loc as $locatorKey => /* @var SimpleXMLElement $loc */ $loc )
		{
			$attributes = $loc->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			if ( ! property_exists( $attributes, 'href' ) )
			{
				$this->log()->instance_validation( "4.11.1", "Footnote locator missing 'href'.", array() );
				continue;
			}
			if ( ! property_exists( $attributes, 'label' ) )
			{
				$this->log()->instance_validation( "4.11.1", "Footnote locator missing 'label'.", array() );
				continue;
			}

			$parts = parse_url( (string) $attributes->href );
			if ( isset( $parts['path'] ) )
			{
				// As it exists, the path MUST be to the instance document
				if ( ! XBRL::endsWith( $this->document_name, pathinfo( $parts['path'], PATHINFO_BASENAME ) ) )
				{
					$this->log()->instance_validation( "4.11.1.1", "Footnote locator href MUST reference facts and tuple elements within the instance document.",
						array(
							'document' => $parts['path'],
						)
					);
					continue;
				}
			}

			$label = (string) $attributes->label;
			$locators[ $label ][] = "#{$parts['fragment']}";
		}

		/**
		 * A list of the labels indexed by [$role][$lang][$label]
		 * @var array $footnotes
		 */
		$footnotes = array();

		/**
		 * A list of the labels indexed by the xlink:label attribute
		 * @var array $footnotesByLabel
		 */
		$footnotesByLabel = array();

		// Note: The XBRL specification explicitly defines an element calle 'label' to be the label value.  However,
		//		 the XLink specification allows an element with any local name so long as it has an attribute
		//		 called 'type' with a value of 'resource'.
		foreach ( $footnoteLink->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->footnote as $footnoteKey => /* @var SimpleXMLElement $footnoteEl */ $footnoteEl )
		{
			$attributes = $footnoteEl->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			if ( ! property_exists( $attributes, 'label' ) )
			{
				$this->log()->instance_validation( "4.11.1.2", "Footnote 'label' attribute missing", array() );
				continue;
			}

			$labelLocator = (string) $attributes->label;

			if ( ! property_exists( $attributes, 'type' ) || ( (string) $attributes->type) !== "resource" )
			{
				$this->log()->instance_validation( "4.11.1.2", "Footnote 'type' attribute missing or invalid",
					array(
						'label' => $labelLocator,
					)
				);
				continue;
			}

			$role = property_exists( $attributes, 'role' )
				? (string) $attributes->role
				: XBRL_Constants::$footnote;

			$label = isset( $locators[ $labelLocator ] ) ? $locators[ $labelLocator ] : $labelLocator;

			$xmlAttributes = $footnoteEl->attributes( 'http://www.w3.org/XML/1998/namespace' );
			if ( ! property_exists( $xmlAttributes, 'lang' ) )
			{
				$this->log()->instance_validation( "4.11.1.2.1", "Footnote 'lang' attribute missing",
					array(
						'label' => $labelLocator,
					)
				);
				continue;
			}

			$lang = property_exists( $xmlAttributes, 'lang' ) ? (string) $xmlAttributes->lang : $this->getDefaultLanguage();
			$text = $footnoteEl->__toString();

			if ( ! isset( $footnotes[ $role ] ) )
			{
				$footnotes[ $role ] = array();
			}

			if ( ! isset( $footnotes[ $role ][ $lang ] ) )
			{
				$footnotes[ $role ][ $lang ] = array();
			}

			// Added the $linkRole component to the array path
			$footnotes[ $role ][ $lang ][ $label ][ $linkRole ] = $text;

			$byLabel = array(
				'text' => $text,
				'lang' => $lang,
				'role' => $role,
			);

			$footnotesByLabel[ $label ][] =& $byLabel;

			$attributes = $footnoteEl->attributes();

			if ( ! property_exists( $attributes, 'id' ) )
			{
				// It's OK for the id to be missing.
				unset( $byLabel );
				continue;
			}

			$id = (string) $attributes->id;
			$footnotes[ $role ][ $lang ][ $label ]['id'] = $id;
			$byLabel['id'] = $id;
			unset( $byLabel );

		}

		$arcs = array();

		// Note: The XBRL specification explicitly defines an element calle 'labelArc' to be the arc.  However,
		//		 the XLink specification allows an element with any local name so long as it has an attribute
		//		 called 'type' with a value of 'arc'.
		foreach ( $footnoteLink->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->footnoteArc as $arcKey => /* @var SimpleXMLElement $footnoteArc */ $footnoteArc )
		{
			$xlinkAttributes = $footnoteArc->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );
			if ( ! property_exists( $xlinkAttributes, 'arcrole' ) ) // || ( (string) $xlinkAttributes->arcrole ) !== XBRL_Constants::$arcRoleFactFootnote )
			{
				$this->log()->instance_validation( "4.11.1.3.1", "Label link 'arcrole' missing or invalid", array() );
				continue;
			}

			$arcrole = (string)$xlinkAttributes->arcrole;
			// The arcrole must be defined if not the stansard one.
			if ( ( (string) $xlinkAttributes->arcrole ) != XBRL_Constants::$arcRoleFactFootnote )
			{
				$artypes = $this->getInstanceTaxonomy()->getArcroleTypes();
				if ( ! isset( $artypes[ XBRL_Constants::$linkFootnoteArc ][ $arcrole ] ) )
				{
					$this->log()->instance_validation( "4.11.1.3.1", "Label link 'arcrole' missing or invalid", array() );
					continue;
				}
			}

			if ( ! isset( $this->footnotes['arcroles'][ $arcrole ] ) )
			{
				$this->footnotes['arcroles'][ $arcrole ][] = $linkRole;
			}
			else if ( ! in_array( $linkRole, $this->footnotes['arcroles'][ $arcrole ] ) )
			{
				$this->footnotes['arcroles'][ $arcrole ][] = $linkRole;
			}

			if ( ! property_exists( $xlinkAttributes, 'from' ) )
			{
				$this->log()->instance_validation( "4.11.1.3", "Label 'from' attribute missing", array() );
				continue;
			}
			if ( ! property_exists( $xlinkAttributes, 'to' ) )
			{
				$this->log()->instance_validation( "4.11.1.3", "Label 'to' attribute missing", array() );
				continue;
			}

			$invalidArc = false;
			$fromFootnote = false;

			$fromLabel	= (string) $xlinkAttributes->from; // The label on the locator
			if ( ! isset( $locators[ $fromLabel ] ) )
			{

				// If the arc role is not the standard then the from label may be another footnote as the XBRL constraints do not apply
				if ( $arcrole != XBRL_Constants::$arcRoleFactFootnote )
				{
					$fromFootnote = isset( $footnotesByLabel[ $fromLabel ] );
				}

				if ( ! $fromFootnote )
				{
					$reason = "The locator may be in a different extended link";

					if ( isset( $footnotesByLabel[ $fromLabel ] ) )
					{
						$reason = "The arc 'from' label is a footnote label";
					}

					$this->log()->instance_validation( "4.11.1.3", "The arc 'from' label does not exist as a locator",
						array(
							'from' => $fromLabel,
							'reason' => $reason,
						)
					);

					$invalidArc = true;
				}
			}

			$toLabel	= (string) $xlinkAttributes->to;   // The label on the <label>

			if ( ! isset( $footnotesByLabel[ $toLabel ] ) )
			{
				$reason = "The footnote resource may be in a different extended link";

				if ( isset( $locators[ $toLabel ] ) )
				{
					$reason = "The arc 'to' label is a locator label";
				}

				$this->log()->instance_validation( "4.11.1.3", "Arc 'to' label does not exist as a footnote label",
					array(
						'from' => $toLabel,
						'reason' => $reason,
					)
				);

				$invalidArc = true;
			}

			if ( $fromFootnote )
			{
				$this->log()->warning( "Footnote to footnote arcs are not supported ($fromLabel -> $toLabel) in link $role" );
				$invalidArc = true;
			}

			if ( $invalidArc ) continue;

			$from		=  $locators[ $fromLabel ];

			// OK, now the arcs have been read and anomolies addressed look for set
			// and make sure priorities and any prohibitions are taken into account

			$attributes = $footnoteArc->attributes();
			$priority	= property_exists( $attributes, 'priority' ) ? (string) $attributes->priority	: "0";
			$use		= property_exists( $attributes, 'use' ) 	 ? (string) $attributes->use		: "optional";
			$order		= property_exists( $attributes, 'order' ) 	 ? (string) $attributes->order		: "1";
			$title		= '';
			$show		= '';
			$actuate	= '';

			$arcRoles = array( 'footnote' => $toLabel ); // Might this ever use a custom role?

			if ( property_exists( $xlinkAttributes, 'title' ) )	$title = $xlinkAttributes->title;
			if ( property_exists( $xlinkAttributes, 'show' ) )	$show = $xlinkAttributes->show;
			if ( property_exists( $xlinkAttributes, 'actuate' ) ) $actuate = $xlinkAttributes->actuate;

			foreach ( $from as $href )
			{
				// Create an arc for the 'to' component
				if ( ! isset( $arcs[ $href ] ) )
				{
					$arcs[ $href ] = array();
				}

				foreach ( $arcRoles as $arcrole => $label )
				{
					if ( isset( $arcs[ $href ][ $linkRole ][ $arcrole ] ) && in_array( $label, $arcs[ $href ][ $linkRole ][ $arcrole ] ) )
					{
						continue;
					}
					$arcs[ $href ][ $linkRole ][ $arcrole ][] = $label;
				}
			}
		}

		unset( $footnotesByLabel );

		// Now there is a set of locators, arcs, labels and $footnotesByHref to store in the context
		// $this->context->addLabels( $locators, $arcs, $footnotes, $footnotesByHref );
		$footnotes['arcs'] = $arcs;

		// $this->footnotes['arcs'] = array_merge_recursive( $this->footnotes['arcs'], $arcs );
		$this->footnotes = array_merge_recursive( $this->footnotes, $footnotes );

		return true;
	}

	/**
	 * Get the footnotes links array for a role
	 * @return array
	 */
	public function &getFootnoteRoleRefs()
	{
		return $this->footnotes;
	}

	/**
	 * Get a footnote for a fact or returns false
	 *
	 * @param array $fact The element array carrying the id
	 * @param string $lang The language code of the footnote to retrieve
	 * @param string $linkrole
	 * @param string $arcrole
	 * @param string $footnoterole
	 * @return array A list of the footnotes with an arc starting with this fact
	 */
	public function getFootnoteForFact( $fact, $lang = null, $linkrole = null, $arcrole = null, $footnoterole = null )
	{
		if ( ! is_array( $fact ) ) return false;
		if ( ! isset( $fact['id'] ) ) return false;

		$href = "#{$fact['id']}";
		if ( ! isset( $this->footnotes['arcs'][ $href ] ) ) return false;

		if ( ! $lang ) $lang = $this->getInstanceTaxonomy()->getDefaultLanguage();
		if ( ! isset( $this->footnotes[ XBRL_Constants::$footnote ][ $lang ] ) )
		{
			if ( ( $pos = strpos( $lang, '-' ) ) ) $lang = substr( $lang, 0, $pos );
		if ( ! isset( $this->footnotes[ XBRL_Constants::$footnote ][ $lang ] ) ) return false;
		}

		if ( is_null( $linkrole ) ) $linkrole = XBRL_Constants::$defaultLinkRole;
		if ( is_null( $arcrole ) ) $arcrole = XBRL_Constants::$arcRoleFactFootnote;
		if ( is_null( $footnoterole ) ) $footnoterole = XBRL_Constants::$footnote;

		// The role implied by $arcrole must be valid (contain arcs that use the arcrole)
		if ( ! isset( $this->footnotes['arcroles'][ $arcrole ] ) || ! in_array( $linkrole, $this->footnotes['arcroles'][ $arcrole ] ) )
		{
			return array();
		}

		// Based on the linkrole, workout the valid footnotes to include
		$footnoteIds = $this->footnotes['arcs'][ $href ][ $linkrole ]['footnote'];
		// Use the footnote ids to select the label text
		$footnotes = array();
		foreach ( $footnoteIds as $footnodeId )
		{
			if ( ! isset( $this->footnotes[ $footnoterole ][ $lang ][ $footnodeId ][ $linkrole ] ) )
			{
				continue;
			}
			$footnotes[] = $this->footnotes[ $footnoterole ][ $lang ][ $footnodeId ][ $linkrole ];
		}

		return $footnotes;
	}

	/**
	 * A test function for inferPrecision()
	 */
	public function testInferPrecision()
	{
		$value_offset = 0;
		$decimals_offset = 1;
		$result_offset = 2;

		// These are the examples provided in the 2.1 specification in section 4.6.6
		$examples = array(
			array( "123", 2, 5 ),
			array( "123.4567", 2, 5 ),
			array( "123e5", -3, 5 ),
			array( "123.45e5", -3, 5 ),
			array( "0.1e-2", 5, 3 ),
			array( "0.001E-2", 5, 1 ),
			array( "0.001e-3", 4, 0 ),
			array( "+.0000E01", 8, 9 ), // An example from the conformance suite (variation 1 e1000)
			array( ".0000", 0, 0 ), // An example from the conformance suite (variation 1 e45)
			array( ".0000", 1, 1 ), // An example from the conformance suite (variation 1 e48)
			array( "+.0000", 1, 1 ), // An example from the conformance suite (variation 1 e49)
			array( ".0000", -1, 0 ), // An example from the conformance suite (variation 1 e45)
			array( "-.001234", -1, 0 ),  // An example from the conformance suite (variation 7 e44)
		);

		foreach ( $examples as $example )
		{
			// Count the total number of digits (includes decimal places)
			$value = $example[0];
			$result = XBRL_Instance::inferPrecision( $example[ $value_offset ], $example[ $decimals_offset ] );
			$agree = $result == $example[ $result_offset ];
			if ( $agree ) continue;
			echo "Does not agree\n";
		}
	}

	/**
	 * A test function for inferDecimals()
	 */
	function testInferDecimals()
	{
		$value_offset = 0;
		$precision_offset = 1;
		$result_offset = 2;

		$examples = array(
				array( 0, 5, 'INF' ),
				array( 123.4567, 0, false ),
				array( 123e5, "INF", "INF" ),
				array( 123.45e5, 6, -2 ),
				array( 0.1e-2, 5, 7 ),
				array( 0.001E-2, 5, 9 ),
				array( 0.001e-3, 4, 9 ),
		);

		foreach ( $examples as $example )
		{
			// Count the total number of digits (includes decimal places)
			$value = $example[0];
			$result = XBRL_Instance::inferDecimals( $example[ $value_offset ], $example[ $precision_offset ] );
			$agree = $result == $example[ $result_offset ];
		}
	}

	/**
	 * Infer the decimals given a value and a precision value
	 *
	 * @param number|string $value
	 * @param number|string $precision
	 * @return int
	 */
	public static function inferDecimals( $value, $precision )
	{
		if ( is_string( $value ) )
		{
			// BMS 2017-08-22 	Removed this line because functions test 80154 V-05 fails
			//					and none of the XBRL 2.1 conformance tests seem to touch it.
			// $value = trim( $value, "0 " );

			if ( $value == 'INF' ) return "INF";
			$value = intval( $value );
		}

		// BMS 2017-12-18 Changed test to use ===
		if ( $value == 0 || $precision === "INF" ) return INF;
		if ( $precision == 0 ) return false;

		return $precision - intval( floor( log10( abs( $value ) ) ) ) - 1;
	}

	/**
	 * Get the decimal specified or implied by the fact attributes
	 *
	 * @param array $fact
	 * @return false|number
	 */
	function getDecimals( $fact )
	{
		if (
				( ! isset( $fact['decimals'] )  || ! strlen( $fact['decimals'] ) ) &&
				( ! isset( $fact['precision'] ) || ! strlen( $fact['precision'] ) )
		 )
		{
			return INF;
		}

		return isset( $fact['decimals'] )
			? ( $fact['decimals'] == 'INF' ? INF : $fact['decimals'] )
			: XBRL_Instance::inferDecimals( $fact['value'], $fact['precision'] );
	}

	/**
	 * Infer the precision given a value and a decimals value
	 *
	 * @param string|number $value
	 * @param string|number $decimals
	 * @return string|boolean|number
	 */
	public static function inferPrecision( $value, $decimals )
	{
		if ( $decimals == "INF" ) return INF;
		if ( ! is_numeric( $value ) ) return false;

		$fValue = floatval( $value );

		if ( $value === false ) return false;
		if ( $decimals === "" ) return 0;

		$precision = 0;
		if ( preg_match( '/[-+]?[0]*([1-9]?[0-9]*)([.])?(0*)([1-9]?[0-9]*)?([eE])?([-+]?[0-9]*)?/', $value, $matches ) )
		{
			list( $all, $nonZeroInt, $period, $zeroDec, $nonZeroDec, $e, $exp ) = $matches;
			$precision = (
				$nonZeroInt
					? strlen( $nonZeroInt )
					: ( $nonZeroDec ? -strlen( $zeroDec ) : 0 )
				) + ( $exp ? intval( $exp ) : 0 ) + ( intval( $decimals ) );

			if ( $precision < 0 ) $precision = 0;
		}

		return $precision;
	}

	/**
	 * Get the decimal specified or implied by the fact attributes
	 *
	 * @param array $fact
	 * @return false|number
	 */
	function getPrecision( &$fact )
	{
		if ( ! isset( $fact['precision'] ) && ! isset( $fact['decimals'] ) )
		{
			return INF;
		}

		return isset( $fact['precision'] )
			? $fact['precision']
			: XBRL_Instance::inferPrecision( $fact['value'], $fact['decimals'] );
	}

	/**
	 * Generate a presentation of a fact value taking into account decimals and precision settings
	 *
	 * @param array $entry (by reference) The fact entry containing the value
	 * @return string
	 */
	function getNumericPresentation( &$entry )
	{
		if ( isset( $entry['precision'] ) && strval( $entry['precision'] ) === '0' )
		{
			return NAN;
		}

		// BMS 2019-06-21
		if ( empty( $entry['value'] ) ) $entry['value'] = 0;

		// Using PHP_ROUND_HALF_EVEN see XBRL 2.1 section 4.6.7.2
		$decimals = $this->getDecimals( $entry );
		return $decimals == INF
			? $entry['value']
			: round( $entry['value'], $this->getDecimals( $entry ), PHP_ROUND_HALF_EVEN );
	}

	/**
	 * Check the instance for correct and adequate information
	 * @return boolean True if the document is valid
	 */
	public function validate()
	{
		// Process each element.
		$instance_taxonomy = $this->getInstanceTaxonomy();
		$types = $instance_taxonomy->context->types;
		// Create a collection of prefixes from this document and the backing schema(s)
		// BMS 2018-08-22 Added the array_filter() call so that taxonomies without a prefix do not cause a log notice
		// $prefixes = array_flip( array_map( function( $taxonomy ) { return $taxonomy->getPrefix(); }, $instance_taxonomy->getImportedSchemas() ) );
		$prefixes = array_flip( array_filter( array_map( function( $taxonomy ) { return $taxonomy->getPrefix(); }, $instance_taxonomy->getImportedSchemas() ) ) );

		$prefixes = $this->instance_namespaces + $prefixes + array( STANDARD_PREFIX_XBRLI => XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] );

		if ( is_null( $types ) )
		{
			throw new \ErrorException( 'Unable to validate the instance because there are no types' );
		}

		// Begin by checking the element in the xbrli namespace are valid
		$xml = $this->getInstanceXml();

		if ( strcasecmp( $xml->getName(), STANDARD_PREFIX_XBRL ) !== 0 )
		{
			$this->log()->instance_validation( "4.1", "The instance document root element is not 'xbrl'.", array() );
			return false;
		}

		$rootElement = $types->getElement( STANDARD_PREFIX_XBRL, STANDARD_PREFIX_XBRLI );

		// The requirements for the <xbrl> root node are described in the schema type.  In summary:
		// 1) There *must* be a schemaref element and it *must* precede all other nodes
		// 2) There *may* be linkrbaseRef, roleRef, an arcroleRefs (in this order)
		// 3) There will be zero or more elements belonging to xbrli:item, xbrli:tuple, xbrli:context, xbrli:unit or link:footnoteLink substitution groups

		// The root element *must* have a schemaRef element and at least one each of a fact, unit and context

		if ( ! count( $this->instance_xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->schemaRef ) )
		{
			$this->log()->instance_validation( "4.2", "There must be at least one schemaRef element in the instance document", array() );
		}
		else
		{
			// The schemaRef MUST have an 'href' attribute and 'type' attribute
			foreach ( $this->instance_xml->children( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_LINK ] )->schemaRef as $schemaRefKey => $schemaRef )
			{
				$attributes = $schemaRef->attributes( XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XLINK ] );

				if ( ! property_exists( $attributes, 'type' ) )
				{
					$this->log()->instance_validation( "4.2.1", "There must be a type attribute on the schemaRef element ", array() );
				}
				else if ( (string) $attributes->type != 'simple' )
				{
					$this->log()->instance_validation( "4.2.1", "The value of the type attribute on the schemaRef element must be 'simple'", array() );
				}

				if ( ! property_exists( $attributes, 'href' ) )
				{
					$this->log()->instance_validation( "4.2.2", "There must be an href attribute on the schemaRef element ", array() );
				}
			}
		}

		$numericTypes = false;
		foreach ( $this->getElements()->getItemTypes() as $key => $typeName )
		{
			// Look up the type in the types list to see if it is numeric
			$qName = qname( $typeName, $prefixes );
			if ( $types->isNumeric( $qName ) )
			{
				$numericTypes = true;
				break;
			}
		}

		if ( count( $this->elements ) > 0 && ( ! count( $this->contexts ) ) )
		{
			$this->log()->instance_validation( "4.6.1", "If there are facts defined there must also be at least one context defined", array() );
		}

		if ( $numericTypes && ! count( $this->units ) )
		{
			$this->log()->instance_validation( "4.6.2", "If there are numeric facts defined (pure, shares, monetary items, etc.) there must also be at least one unit defined", array() );
		}

		// Next check that the contexts are valid
		$contexts = $this->getContexts();

		// if is instant make sure the date exists and is valid
		// if is start/end make sure both dates exist, are valid and the end > start
		// check segment or scenario
		//   check the children do not use namespaceUri http://xbrl.org/2006/xbrldi
		//   check the children are themselves not concepts

		foreach ( $this->contexts as $id => $context )
		{
			/* ---------------------------------------------------------
			 * Validate the ID
			 * ---------------------------------------------------------
			 */
			if ( empty( $id ) ) // Should never happen here because these should be filtered when the context is read
			{
				$this->log()->instance_validation( "4.7.1", "All contexts must have a valid id", array() );
			}

			/* ---------------------------------------------------------
			 * Validate the period
			 * ---------------------------------------------------------
			 */
			$period = $context['period'];

			if ( ! isset( $period['startDate'] ) )
			{
				$this->log()->instance_validation( "4.7.2", "A start date must be defined", array( 'context' => $id ) );
			}

			if ( ! isset( $period['startDate'] ) )
			{
				$this->log()->instance_validation( "4.7.2", "An end date must be defined", array( 'context' => $id ) );
			}

			if ( ! isset( $period['startDate'] ) || ! isset( $period['startDate'] ) ) continue;

			// Check the start dates and the end dates are valid
			$validDate = function( $date )
			{
				// Could be 'forever'
				if ( $date == 'forever' ) return true;

				// Next try using a full ISO8601 formatted date
				$result = DateTime::createFromFormat( DATE_ATOM, $date );
				if ( $result ) return true;

				// Next try using an ISO8601 formatted date but without the time zone
				$result = DateTime::createFromFormat( "Y-m-d\TH:i:s", $date );
				if ( $result ) return true;

				// Now try just the date
				$result = DateTime::createFromFormat( "Y-m-d", $date );
				if ( $result ) return true;

				return false;
			};

			if ( ! $validDate( $period['startDate'] ) )
			{
				$this->log()->instance_validation( "4.7.2", "The start date must be a valid ISO 8601 date",
					array(
						'context' => $id,
						'date' => "{$period['startDate']}",
					)
				);
			}

			if ( ! $validDate( $period['endDate'] ) )
			{
				$this->log()->instance_validation( "4.7.2", "The end date must be a valid ISO 8601 date",
					array(
						'context' => $id,
						'date' => "{$period['endDate']}",
					)
				);
			}

			if ( $period['type'] == 'instant' )
			{
				// Both the dates must be the same
				if ( $period['startDate'] != $period['endDate'] )
				{
					$this->log()->instance_validation( "4.7.2", "In an 'instant' context the start date and end dates must be the same",
						array(
							'context' => $id,
							'startdate' => "{$period['startDate']}",
							'enddate' => "{$period['endDate']}",
						)
					);
				}
			}
			else if ( $period['type'] == 'forever' )
			{
				// Both the dates must be the same
				if ( $period['startDate'] != $period['endDate'] )
				{
					$this->log()->instance_validation( "4.7.2", "In an 'forever' context the start date and end dates must be the same",
						array(
							'context' => $id,
							'startdate' => "{$period['startDate']}",
							'enddate' => "{$period['endDate']}",
						)
					);
				}
			}
			else
			{
				// For duration context the end date must be greater than the start date
				$startDate = $period['startDate'];
				if ( ! strpos( $startDate, 'T' ) ) $startDate .= "T00:00:00Z";

				$endDate = $period['endDate'];
				if ( ! strpos( $endDate, 'T' ) ) $endDate .= "T23:59:59Z";

				$diff = DateTime::createFromFormat( DATE_ATOM, $endDate )->getTimestamp() - DateTime::createFromFormat( DATE_ATOM, $startDate )->getTimestamp();
				if ( $diff <= 0 )
				{
					$this->log()->instance_validation( "4.7.2", "In a 'duration' context the start date must precede the end date", array( 'context' => $id ) );
				}
			}

			/* ---------------------------------------------------------
			 * Validate the entity
			 * ---------------------------------------------------------
			 */

			if ( ! isset( $context['entity'] ) )
			{
				$this->log()->instance_validation( "4.7.3", "An entity element MUST exist in a context", array( 'context' => $id ) );
				continue;
			}

			$entity = $context['entity'];

			if ( ! isset( $entity['identifier'] ) )
			{
				$this->log()->instance_validation( "4.7.3.1", "An entity element in a context MUST contain an identifier", array( 'context' => $id ) );
			}
			else
			{
				$identifier = $entity['identifier'];

				if ( ! isset( $identifier['scheme'] ) )
				{
					$this->log()->instance_validation( "4.7.3.1", "An identifier element in a context MUST include a 'scheme' attribute to identify the namespace", array( 'context' => $id ) );
				}

				if ( ! isset( $identifier['value'] ) )
				{
					$this->log()->instance_validation( "4.7.3.1", "An identifier element in a context MUST include a value", array( 'context' => $id ) );
				}
			}

			/* ---------------------------------------------------------
			 * Validate the segments
			 * ---------------------------------------------------------
			 */

			$validationTypes = array(
				'explicitMember' => 'validateExplicitMembers',
				'member' => 'validateNonDimensionMembers',
				'other' => 'validateOtherComponentTypes',
				'typedMember' => 'validateTypedMembers',
			);

			// A block of data the functions can pass to themselves for cross-function testing
			$data = null;

			if ( isset( $entity[ XBRL_Constants::$xbrliSegment ] ) )
			{
				foreach ( $validationTypes as $segmentType => $functionName )
				{
					// A block of data the functions can pass to themselves for cross-function testing
					// $data = null;

					if ( $segmentType == 'other' )
					{
						$this->validateOtherComponentTypes( $entity[ XBRL_Constants::$xbrliSegment ], $id, $types,  XBRL_Constants::$xbrliSegment, $data );
						continue;
					}

					if ( ! isset( $entity[ XBRL_Constants::$xbrliSegment ][ $segmentType ] ) ) continue;

					$segments = $entity[  XBRL_Constants::$xbrliSegment ][ $segmentType ];

					foreach ( $segments as $segment )
					{
						$this->$functionName( $segment, $id, $types,  XBRL_Constants::$xbrliSegment, $data );
					}

				}
			}

			/* ---------------------------------------------------------
			 * Validate the scenarios
			 * ---------------------------------------------------------
			 */

			// Shouldn't this be just like validating segments?

			if ( isset( $context[ XBRL_Constants::$xbrliScenario ] ) )
			{

				foreach ( $validationTypes as $scenarioType => $functionName )
				{
					// A block of data the functions can pass to themselves for cross-function testing
					// $data = null;

					if ( $scenarioType == 'other' )
					{
						$this->validateOtherComponentTypes( $context[ XBRL_Constants::$xbrliScenario ], $id, $types,  XBRL_Constants::$xbrliScenario, $data );
						continue;
					}

					if ( ! isset( $context[ XBRL_Constants::$xbrliScenario ][ $scenarioType ] ) ) continue;

					$scenarios = $context[ XBRL_Constants::$xbrliScenario ][ $scenarioType ];

					foreach ( $scenarios as $scenario )
					{
						$this->$functionName( $scenario, $id, $types,  XBRL_Constants::$xbrliScenario, $data );
					}
				}
			}

			// Need to check there are no dimensions spread across

		}

		/* ---------------------------------------------------------
		 * Validate the units
		 * ---------------------------------------------------------
		 */

		foreach ( $this->units as $id => $unit )
		{
			/* ---------------------------------------------------------
			 * Validate the ID
			 * ---------------------------------------------------------
			 */
			if ( empty( $id ) ) // Should never happen here because these should be filtered when the context is read
			{
				$this->log()->instance_validation( "4.8.1", "All units must have a valid id", array() );
			}

			// A unit definition can be a single measure in which case the unit is a simple string.
			if ( is_string( $unit ) )
			{
				$this->validateMeasure( $unit, $id, $types );
				continue;
			}

			// Otherwise its an array of components which may be two or more
			// measures or a numerator followed by a denominator

			if ( isset( $unit['measures'] ) && isset( $unit['divide'] ) )
			{
				$this->log()->instance_validation( "4.8.1", "Both a 'measure' and a 'divide' provided.  Should provide one or the other", array( 'unit' => $id ) );
			}

			if ( isset( $unit['measures'] ) )
			{
				$measures = $unit['measures'];
				foreach ( $measures as $measure )
				{
					$this->validateMeasure( $measure, $id, $types );
				}
			}

			if ( isset( $unit['divide'] ) )
			{
				$this->validateDivide( $unit['divide'], $id, $types );
			}
		}

		/* ---------------------------------------------------------
		 * Validate the facts
		 * ---------------------------------------------------------
		 */

		$this->validateFacts( $this->elements, 'xbrl', $types, $prefixes );

		/* ---------------------------------------------------------
		 * Validate footnotes
		 * ---------------------------------------------------------
		 */

		if ( isset( $this->footnotes['arcs'] ) )
		{
			// Build a list of elements with ids
			$ids = array_reduce( $this->elements, function( $ids, $element ) {
				$ids += array_reduce( $element, function( $ids, $fact ) {
					if ( isset( $fact['id'] ) ) $ids[ $fact['id'] ] = $fact;
					return $ids;
				}, array() );
				return $ids;
			}, array() );

			foreach ( $this->footnotes['arcs'] as $id => $labels )
			{
				$fragment = parse_url( $id, PHP_URL_FRAGMENT );
				// Look for an element with this id
				if ( isset( $ids[ $fragment ] ) ) continue;

				$target = "The id does not exist";
				// Look to see if the id is for a context, unit or element
				if ( $this->getContext( $fragment ) )
				{
					$target = "The id references a context";
				}
				else if ( $this->getUnit( $fragment ) )
				{
					$target = "The id references a unit";
				}
				else if ( $this->getElements( $fragment ) )
				{
					$target = "The id references a fact probably in a different extended link";
				}

				$labels = array_reduce( $labels, function( $carry, $item )
				{
					$carry = array_merge( $carry, isset( $item['footnote'] ) ? $item['footnote'] : array() );
					return $carry;
				}, array() );

				$this->log()->instance_validation( "4.11.1.1", "The footnote fact does not exist.",
					array(
						'id' => $fragment,
						'footnotes' => implode( ", ", $labels ),
						'target' => $target,
					)
				);
			}
		}

		/* ---------------------------------------------------------
		 * Validate require elements
		 * ---------------------------------------------------------
		 */

		$this->validateRequiresElementArcs( $types );

		/* ---------------------------------------------------------
		 * Validate essence alias
		 * ---------------------------------------------------------
		 */

		$this->validateEssenceAliasArcs( $types );

		/* ---------------------------------------------------------
		 * Validate calculations
		 * ---------------------------------------------------------
		 */

		$this->validateCalculations( $types );

		/* ---------------------------------------------------------
		 * Validate GeneralSpecial
		 * ---------------------------------------------------------
		 */

		$this->validateGeneralSpecial( $types );

		/* ---------------------------------------------------------
		 * Validate non-dimensional
		 * ---------------------------------------------------------
		 */

		$this->validateNonDimensionalArcRoles( $types, $this->getInstanceTaxonomy() );

		return ! $this->log()->hasInstanceValidationWarning();
	}

	/**
	 * Process each fact in the instance document which may have multiple entries
	 *
	 * @param array $elements
	 * @param string $parent An identifier representing the parent node in the instance document
	 * @param XBRL_Types $types
	 * @param array $prefixes
	 */
	private function validateFacts( $elements, $parent, &$types, &$prefixes )
	{
		$instanceTaxonomy = $this->getInstanceTaxonomy();
		$primaryItems = $instanceTaxonomy->getDefinitionPrimaryItems( false );

		foreach ( $elements as $factKey => $fact )
		{
			$drsHypercubes = false;

			foreach ( $fact as $entryKey => $entry )
			{
				// This function will return an empty array if there is no primary item or if the primary item is not
				// associated with any hypercubes.  Otherwise it will contain summary information about the dimensional
				// validation of the primary item.  If the dimensional validation fails it means the fact is not
				// associated with any hypercubes valid for the fact context.
				$primaryItem = isset( $primaryItems[ $entry['label'] ] ) ? $primaryItems[ $entry['label'] ] : false;
				if ( $primaryItem && ! $drsHypercubes )
				{
					$drsHypercubes = $this->getInstanceTaxonomy()->getPrimaryItemDRS( $primaryItem );
				}

				$dimensionalValidation = $this->validateElementEntry( $entry, $factKey, $parent, $types, $prefixes, $primaryItem, $drsHypercubes );
			}
		}
	}

	/**
	 * Validate any requires element arcs
	 *
	 * @param XBRL_Types $types
	 * @return boolean
	 */
	private function validateRequiresElementArcs( XBRL_Types &$types )
	{

		$arcs = $this->getInstanceTaxonomy()->getRequireElementsList();
		if ( ! $arcs ) return true;

		foreach ( $arcs as $from => $targets )
		{
			// echo "$from";

			// If the $from exists then the $to exists
			$parts = parse_url( $from );
			if ( ! isset( $parts['path'] ) || ! isset( $parts['fragment'] ) ) continue;

			$fromTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForXSD( $parts['path'] );
			if ( ! $fromTaxonomy )
			{
				$this->log()->instance_validation( "5.2.6.2.4", "The taxonomy of the source does not exist in the DTS",
					array(
						'from' => $from,
					)
				);
				continue;
			}

			$fromElement = $fromTaxonomy->getElementById( $parts['fragment'] );
			if ( ! $fromElement )
			{
				$this->log()->instance_validation( "5.2.6.2.4", "The concept of the source does not exist in the DTS",
					array(
						'from' => $from,
					)
				);
				continue;
			}

			// Look for the fact in the instance document
			$fromFact = $this->getElement( $fromElement['id'] );

			// If the $from does not exist there is noting else to do
			if ( ! $fromFact ) continue;

			foreach ( $targets as $target => $details )
			{
				// echo " -> $target\n";

				$parts = parse_url( $target );
				if ( ! isset( $parts['path'] ) || ! isset( $parts['fragment'] ) ) continue;

				$toTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForXSD( $parts['path'] );
				if ( ! $toTaxonomy )
				{
					$this->log()->instance_validation( "5.2.6.2.4", "The taxonomy of the target does not exist in the DTS",
						array(
							'from' => $from,
						)
					);
					continue;
				}

				$toElement = $toTaxonomy->getElementById( $parts['fragment'] );
				if ( ! $toElement )
				{
					$this->log()->instance_validation( "5.2.6.2.4", "The concept of the target does not exist in the DTS",
						array(
							'to' => $to,
						)
					);
					continue;
				}

				// Look for the fact in the instance document
				$toFact = $this->getElement( $toElement['id'] );

				// If the $from does not exist there is noting else to do
				if ( $toFact ) continue;

				// The $from exist but the $to does not so report the error.
				$this->log()->instance_validation( "5.2.6.2.4", "The required source fact exists but the target one does not exist.",
					array(
						'from' => $from,
						'to' => $target,
					)
				);

			}
		}
	}

	/**
	 * Validate any essence alias arcs
	 *
	 * @param XBRL_Types $types
	 * @return boolean
	 */
	private function validateEssenceAliasArcs( XBRL_Types &$types )
	{

		$arcs = $this->getInstanceTaxonomy()->getEssenceAliasList();
		if ( ! $arcs ) return true;

		/* ---------------------------------------------------------
		 * Look for directed cycles
		 * ---------------------------------------------------------
		 */

		/**
		 * Examine the arcs recursively to look for directed cycles.
		 * If there are directed cyles then the $alias of an arc will appear in the list of $parents
		 *
		 * @var function $detectDirected
		 * @param string $essence
		 * @param array $parents
		 * @return bool
		 */
		$detectDirected = function( $essence, $parents = array() ) use( &$detectDirected, &$arcs )
		{
			foreach ( $arcs[ $essence ] as $alias => $details )
			{
				if ( $alias == $essence || in_array( $alias, $parents ) ) return true;

				if ( ! isset( $arcs[ $alias ] ) ) continue;

				if ( $detectDirected( $alias, array_merge( $parents, array( $essence ) ) ) )
				{
					XBRL_Log::getInstance()->instance_validation( "5.2.6.2.2", "The essence alias arcs contain directed cycles",
						array(
							'path' => implode( '->', $parents ),
						)
					);

					return true;
				}
			}

			return false;
		};

		foreach ( $arcs as $essence => $aliases )
		{
			if ( $detectDirected( $essence ) )
			{
				return false;
			}
		}

		/* ---------------------------------------------------------
		 * Check each arc
		 * ---------------------------------------------------------
		 */

		foreach ( $arcs as $essence => $aliases )
		{
			// echo "$essence\n";

			$parts = parse_url( $essence );
			if ( ! isset( $parts['path'] ) || ! isset( $parts['fragment'] ) ) continue;

			$essenceTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForXSD( $parts['path'] );
			if ( ! $essenceTaxonomy )
			{
				$this->log()->instance_validation( "5.2.6.2.2", "The taxonomy of the source does not exist in the DTS",
					array(
						'from' => $essence,
					)
				);
				continue;
			}

			$essenceElement = $essenceTaxonomy->getElementById( $parts['fragment'] );
			if ( ! $essenceElement )
			{
				$this->log()->instance_validation( "5.2.6.2.2", "The concept of the source does not exist in the DTS",
					array(
						'essence' => $essence,
					)
				);
				continue;
			}

			$type = $types->getType( $essenceElement['type'] );
			if ( ! $type )
			{
				$this->log()->instance_validation( "5.2.6.2.2" , "Cannot locate type information for the essence",
					array(
						'type' => $essenceElement['type'],
						'element' => $essence,
					)
				);
				continue;
			}

			// Look for the essence fact(s) in the instance document
			$essenceFact = $this->getElement( $essenceElement['id'] );

			// If the $from does not exist then infer the value
			$essenceAliasValues = array(); // Holds a list of inferred values grouped by parent nodes
			$combinations = new TupleDictionary();

			foreach ( $aliases as $alias => $details )
			{
				// echo "\t-> $alias\n";

				$parts = parse_url( $alias );
				if ( ! isset( $parts['path'] ) || ! isset( $parts['fragment'] ) ) continue;

				$aliasTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForXSD( $parts['path'] );
				if ( ! $aliasTaxonomy )
				{
					$this->log()->instance_validation( "5.2.6.2.2", "The taxonomy of the alias does not exist in the DTS",
						array(
							'alias' => $alias,
						)
					);
					continue;
				}

				$aliasElement = $aliasTaxonomy->getElementById( $parts['fragment'] );
				if ( ! $aliasElement )
				{
					$this->log()->instance_validation( "5.2.6.2.2", "The concept of the alias does not exist in the DTS",
						array(
							'alias' => $alias,
						)
					);
					continue;
				}

				// The element definitions should always have an type but its worth checking
				if ( XBRL_Instance::isEmpty( $aliasElement, 'type' ) || XBRL_Instance::isEmpty( $essenceElement, 'type' ) )
				{
					$this->log()->taxonomy_validation( "5.1.1.1", "Missing type attribute on essence or alias",
						array(
							'essence' => $essence,
							'alias' => $alias,
							'point' => '3',
							'essence_type' => $essenceElement['type'],
							'alias_type' => $aliasElement['type'],
						)
					);
					continue;
				}

				// The type of the essence and the alias must be the same
				if ( $aliasElement['type'] != $essenceElement['type'] )
				{
					continue;
				}

				// The element definitions should always have a periodType but its worth checking
				if ( XBRL_Instance::isEmpty( $aliasElement, 'periodType' ) || XBRL_Instance::isEmpty( $essenceElement, 'periodType' ) )
				{
					$this->log()->taxonomy_validation( "5.1.1.1", "Missing periodType attribute on essence or alias",
						array(
							'essence' => $essence,
							'alias' => $alias,
							'point' => '3',
							'essence_period_type' => $essenceElement['periodType'],
							'alias_period_type' => $aliasElement['periodType'],
						)
					);
					continue;
				}

				// The period type of both concepts MUST be the same
				if ( $aliasElement['periodType'] != $essenceElement['periodType'] )
				{
					$this->log()->instance_validation( "5.2.6.2.2", "The period types of the essence and alias are not compatible",
						array(
							'essence' => $essence,
							'alias' => $alias,
							'point' => '3',
							'essence_period_type' => $essenceElement['periodType'],
							'alias_period_type' => $aliasElement['periodType'],
						)
					);
					continue;
				}

				// If the alias and essence concepts include balance attribute then the value MUST be the same
				if ( ! XBRL_Instance::isEmpty( $aliasElement, 'balance' ) && ! XBRL_Instance::isEmpty( $essenceElement, 'balance' ) )
				{
					if (  $aliasElement['balance'] != $essenceElement['balance'] )
					{
						continue;
					}
				}

				// Look for the fact in the instance document
				$aliasFact = $this->getElement( $aliasElement['id'] );

				// If the $from does not exist there is noting else to do
				if ( ! $aliasFact ) continue;

				// Getting here means there is an essence concept and an alias concept that are equal
				// Now take a look at the facts

				if ( ! $essenceFact )
				{
					// If there is no essence fact infer any values
					foreach ( $aliasFact as $aliasEntryKey => $aliasEntry )
					{
						$value = $type['numeric']
							? $this->getNumericPresentation( $aliasEntry )
							: $aliasEntry['value'];

						// Create a key
						$key = array(
							'name' => $aliasElement['id'],
							'parent' => $aliasEntry['parent'],
							'context' => $this->getContext( $aliasEntry['contextRef'] ),
							'unit' => $type['numeric'] ? $this->getUnit( $aliasEntry['unitRef'] ) : null,
							'value' => $value,
						);

						$existing =& $combinations->getValue( $key, null );
						if ( ! is_null( $existing ) )
						{
							$source = array(
								'section' => '4.10',
								'name' => $aliasElement['id'],
								'contextRef' => "{$aliasEntry['contextRef']} vs {$existing['context']}",
								'unitRef' => "{$aliasEntry['contextRef']} vs {$existing['unit']}",
								'value' => "{$value} vs {$existing['value']}",
							);

							$this->log()->warning( sprintf( "Duplicate item: %s", $this->log()->arrayToDescription( $source ) ) );
						}

						$combinations->addValue( $key, array(
							'name' => $aliasElement['id'],
							'context' => $aliasEntry['contextRef'],
							'unit' => $type['numeric'] ? $aliasEntry['unitRef'] : null,
							'value' => $value,
						) );

						$essenceAliasValues[ $aliasEntry['parent'] ]['alias'][ $alias ] = $value;
						$essenceAliasValues[ $aliasEntry['parent'] ]['contexts'][ $aliasEntry['contextRef'] ] = $aliasEntry['contextRef'];
						$essenceAliasValues[ $aliasEntry['parent'] ]['units'][ $aliasEntry['unitRef'] ] = $aliasEntry['unitRef'];
					}
				}
				else
				{
					// There is an essence fact so make sure the essence and alias facts are c-equal, u-equal, p-equal and v-equal
					foreach ( $essenceFact as $essenceEntryKey => $essenceEntry )
					{
						// Using PHP_ROUND_HALF_EVEN see XBRL 2.1 section 4.6.7.2
						$essenceValue = $type['numeric']
						 	? $this->getNumericPresentation( $essenceEntry )
							: $essenceEntry['value'];

						foreach ( $aliasFact as $aliasEntryKey => $aliasEntry )
						{
							if ( ! XBRL_Instance::isEmpty( $aliasEntry, 'nil' ) && filter_var( $aliasEntry['nil'], FILTER_VALIDATE_BOOLEAN ) ) continue;

							// The essence and alias should be p-equal
							if ( $essenceEntry['parent'] != $aliasEntry['parent'] )
							{
								/*
								$this->log()->instance_validation( "5.2.6.2.2", "The scope for the arc is not valid because the essence parent and the alias parent are not the same",
									array(
										'essence' => $essence,
										'alias' => $alias,
										'point' => '3',
									)
								);
								*/
								continue;
							}

							// check the essence and alias are C-Equal (by definition also S-Equal), P-Equal, U-Equal, V-Equal
							if ( $essenceEntry['contextRef'] != $aliasEntry['contextRef'] &&
								 ! XBRL_Equality::context_equal( $this->getContext( $essenceEntry['contextRef'] ), $this->getContext( $aliasEntry['contextRef'] ) )
							   )
							{
								/*
								$this->log()->instance_validation( "5.2.6.2.2", "The contexts on the essence and alias are not compatible",
									array(
										'essence' => $essence,
										'alias' => $alias,
										'point' => '3',
										'essence_context' => $essenceEntry['contextRef'],
										'alias_context' => $aliasEntry['contextRef'],
									)
								);
								*/
								continue;
							}

							if ( $essenceEntry['unitRef'] != $aliasEntry['unitRef'] &&
								 ! XBRL_Equality::unit_equal( $this->getUnit( $essenceEntry['unitRef'] ), $this->getUnit( $aliasEntry['unitRef'] ), $types, $this->getInstanceNamespaces() )
							   )
							{
								$this->log()->instance_validation( "5.2.6.2.2", "The units on the essence and alias are not compatible",
									array(
										'essence' => $essence,
										'alias' => $alias,
										'point' => '3',
										'essence_unit' => $essenceEntry['unitRef'],
										'alias_unit' => $aliasEntry['unitRef'],
									)
								);
								continue;
							}

							$value = $type['numeric']
								? $this->getNumericPresentation( $aliasEntry )
								: $aliasEntry['value'];

							// Create a key
							$key = array(
								'name' => $aliasElement['id'],
								'parent' => $aliasEntry['parent'],
								'context' => $this->getContext( $aliasEntry['contextRef'] ),
								'unit' => $type['numeric'] ? $this->getUnit( $aliasEntry['unitRef'] ) : null,
								'value' => $value,
							);

							$existing =& $combinations->getValue( $key, null );
							if ( ! is_null( $existing ) )
							{
								$source = array(
									'section' => '4.10',
									'name' => $aliasElement['id'],
									'contextRef' => "{$aliasEntry['contextRef']} vs {$existing['context']}",
									'unitRef' => "{$aliasEntry['contextRef']} vs {$existing['unit']}",
									'value' => "{$value} vs {$existing['value']}",
								);

								$this->log()->warning( sprintf( "Duplicate item: %s", $this->log()->arrayToDescription( $source ) ) );
							}

							$combinations->addValue( $key, array(
								'name' => $aliasElement['id'],
								'context' => $aliasEntry['contextRef'],
								'unit' => $type['numeric'] ? $aliasEntry['unitRef'] : null,
								'value' => $value,
							) );

							$essenceAliasValues[ $essenceEntry['parent'] ]['essencevalue'] = $essenceValue;
							$essenceAliasValues[ $essenceEntry['parent'] ]['essence'] = $essence;

							$essenceAliasValues[ $aliasEntry['parent'] ]['alias'][ $alias ] = $value;
							$essenceAliasValues[ $aliasEntry['parent'] ]['contexts'][ $aliasEntry['contextRef'] ] = $aliasEntry['contextRef'];
							$essenceAliasValues[ $aliasEntry['parent'] ]['units'][ $aliasEntry['unitRef'] ] = $aliasEntry['unitRef'];
						}
					}
				}
			}

			// If an essence fact exists then check its values are consistent with the alias value(s)
			// If an essence fact does not exist then create one based on the values in the $inferredParents
			foreach ( $essenceAliasValues as $parentKey => $details )
			{
				if ( isset( $details['essencevalue'] ) )
				{
					foreach ( $details['alias'] as $alias => $value )
					{
						if ( $value === $details['essencevalue'] ) continue;
						$this->log()->instance_validation( "5.2.6.2.2", "Essence alias values do not match",
							array(
								'essence' => $details['essence'],
								'alias' => $alias,
								'essence_value' => $details['essencevalue'],
								'alias_value' => "'$value'",
							)
						);
					}
				}
				else
				{
					// echo "Need to create an inferred fact\n";
					// Create a namespace prefix as known in the instance document for consistency
					// This gets the namespace for the essence element looks up the corresponding prefix in the instance document
					$namespace = $this->getPrefixForNamespace( $essenceTaxonomy->getNamespaceForPrefix( $essenceTaxonomy->getPrefix() ) );
					$newEntry = array(
						'taxonomy_element' => $essenceElement,
						'namespace' => $namespace,
					);

					// All the values must be the same if a value for the essence is to be inferred
					$diff = array_diff( $details['alias'], $details['alias'] );
					if ( ! $diff ) continue;

					$this->log()->instance_validation( "5.2.6.2.2", "The values of all aliases should all be the same for an essence value to be inferre",
						array(
							'essence' => $essence,
							'alias' => $this->log()->arrayToDescription( $details['alias'] ),
						)
					);

					$newEntry['contextRef'] = reset( $details['contexts'] );
					$newEntry['unitRef'] = reset( $details['units'] );

					$this->addElement( $essence, $newEntry );
				}
			}
		}
	}

	/**
	 * Returns true if $entry is a child of $ancestor or is child of any sibling of $ancestor
	 * or false
	 * This is used to determine if calculation linkbase summation item nodes are in scope
	 * @param array $entry
	 * @param array $ancestor
	 * @return false
	 */
	private function hasAncestorGuid( $entry, $ancestor )
	{
		if (
				( isset( $entry['parent'] ) && isset( $ancestor['parent'] ) && ( $entry['parent'] == $ancestor['parent'] ) ) || // The item is a sibling
				( isset( $entry['parent'] ) && ( $entry['parent'] == $ancestor['guid'] ) ) || // The item is a child
				( $entry['guid'] == $ancestor['guid'] ) // Same
		   )
		{
			return true;
		}

		if ( $entry['guid'] == 'xbrl' || $entry['parent'] == 'xbrl' )
		{
			return false; // If here and the entry guid is xbrl there can be no parent or match
		}

		$parent =& $this->getEntryForGuid( $entry['parent'] );
		if ( ! $parent ) return false;
		return $this->hasAncestorGuid( $parent, $ancestor );
	}

	/**
	 * THIS IS NO LONGER USED
	 * Returns the guid of the grand parent of the fact entry in $entry
	 * or false if there is no grand parent
	 * @param array $entry
	 * @return string|false
	 */
	private function getGrandParentGuid( $entry )
	{
		if ( $entry['parent'] == 'xbrl' ) return false; // There can't be a grandparent of this entry
		$parent =& $this->getEntryForGuid( $entry['parent'] );
		return $parent['parent'];
	}

	/**
	 * Returns true if the $elementName of $entry is empty.  That is, it is either not defined or has a zero length.
	 * The built-in empty() function return true if the text in an element can be coerced to 'false' such as a zero
	 * which makes it unsuitable for use when values of zero may be valid.
	 *
	 * @param array $entry An array representing a fact
	 * @param string $elementName The name of the arrey element to test
	 * @return boolean
	 */
	public static function isEmpty( $entry, $elementName )
	{
		return ! isset( $entry[ $elementName ] ) || ( is_array( $entry[ $elementName ] ) ? ! count( $entry[ $elementName ] ) : ! strlen( $entry[ $elementName ] ) );
	}

	/**
	 * Validate any calculations
	 *
	 * @param XBRL_Types $types
	 */
	private function validateGeneralSpecial( XBRL_Types &$types )
	{
		$roles =& $this->getInstanceTaxonomy()->getGeneralSpecialRoleRefs();

		/* ---------------------------------------------------------
		 * Look for directed cycles
		 * ---------------------------------------------------------
		 */

		/**
		 * Examine the arcs recursively to look for directed cycles.
		 * If there are directed cyles then the $alias of an arc will appear in the list of $parents
		 *
		 * @var function $detectDirected
		 * @param string $source
		 * @param array $parents
		 * @return bool
		 */
		$detectDirected = function( $roleKey, $source, $parents = array() ) use( &$detectDirected, &$roles )
		{
			foreach ( $roles[ $roleKey ]['rules'][ $source ] as $target => $details )
			{
				if ( $target == $source || in_array( $target, $parents ) )
				{
					XBRL_Log::getInstance()->instance_validation( "5.2.6.2.1", "The general special arcs contain directed cycles",
						array(
							'path' => implode( '->', array_merge( $parents, array( $target ) ) ),
						)
					);
					return true;
				}

				if ( ! isset( $roles[ $roleKey ]['rules'][ $target ] ) ) continue;

				if ( $detectDirected( $roleKey, $target, array_merge( $parents, array( $source ) ) ) ) return true;
			}

			return false;
		};

		foreach ( $roles as $roleKey => $role )
		{
			if ( ! isset( $role['rules'] ) ) continue;

			foreach ( $role['rules'] as $from => $items )
			{
				if ( $detectDirected( $roleKey, $from ) )
				{
					break;
				}
			}
		}

	}

	/**
	 * Validate any non-dimensional arc roles
	 *
	 * @param XBRL_Types $types
	 * @param XBRL $taxonomy
	 */
	private function validateNonDimensionalArcRoles( XBRL_Types &$types, $taxonomy )
	{
		$roles =& $taxonomy->getNonDimensionalRoleRefs();

		/* ---------------------------------------------------------
		 * Look for directed cycles
		 * ---------------------------------------------------------
		 */

		/**
		 * Examine the arcs recursively to look for directed cycles.
		 * If there are directed cyles then the $alias of an arc will appear in the list of $parents
		 *
		 * @var function $detectDirected
		 * @param string $arcroleKey
		 * @param string $arcrole
		 * @param string $source
		 * @param array $parents
		 * @return bool
		 */
		// Detects the case that a node is its own parent
		$detectDirected = function( $roleKey, $arcroleKey, $source, $parents = array() ) use( &$detectDirected, &$roles )
		{
			foreach ( $roles[ $roleKey ][ $arcroleKey ]['arcs'][ $source ] as $target => $details )
			{
				if ( $target == $source || in_array( $target, $parents ) )
				{
					XBRL_Log::getInstance()->instance_validation( "5.2.6.2.1", "The non-standard arcrole arcs contain directed cycles",
						array(
							'path' => implode( '->', array_merge( $parents, array( $target ) ) ),
						)
					);
					return true;
				}

				if ( ! isset( $roles[ $roleKey ][ $arcroleKey ]['arcs'][ $target ] ) ) continue;

				if ( $detectDirected( $roleKey, $arcroleKey, $target, array_merge( $parents, array( $source ) ) ) ) return true;
			}

			return false;
		};

		foreach ( $roles as $roleKey => $arcroles )
		{
			foreach ( $arcroles as $arcroleKey => $arcrole )
			{
				$cyclesAllowed = isset( $arcrole['cyclesAllowed'] ) ? $arcrole['cyclesAllowed'] : "none";
				switch ( $cyclesAllowed )
				{
					case 'none':
						// If no cycles are allowed then check for directed and undirected cycles
						$directed = false;
						foreach ( $arcrole['arcs'] as $from => $items )
						{
							$directed = $detectDirected( $roleKey, $arcroleKey, $from );
							if ( $directed )
							{
								break;
							}
						}

						if ( $directed ) break;

						// Detects the case that a node has more than one parent
						$scra = array();
						$undirected = false;

						// Invert the arcs
						foreach ( $arcrole['arcs'] as $from => $fromItems )
						{
							foreach ( $fromItems as $target => $targetItems )
							{
								if ( ! isset( $scra[ $target ] ) || ! isset( $scra[ $target ][ $from ] ) )
								{
									$scra[ $target ][ $from ] = "$from -> $target";
									// Any one target should have only one parent.
									// If there is more than one then this is an undirected cycle
									$undirected = count( $scra[ $target ] ) > 1;
									if ( $undirected )
									{
										XBRL_Log::getInstance()->instance_validation( "5.2.6.2.1", "The non-standard arcrole arcs contain undirected cycles",
											array(
												'path' => implode( ', ', $scra[ $target ] ),
											)
										);

										break;
									}
								}
							}

							if ( $undirected ) break;
						}

						break;

					case 'undirected':
						// If undirected cycles are allow then only look for directed cycles
						foreach ( $arcrole['arcs'] as $from => $items )
						{
							if ( $detectDirected( $roleKey, $arcroleKey, $from ) )
							{
								break;
							}
						}
						break;
				}
			}
		}

	}

	/**
	 * Validate any calculations
	 *
	 * @param XBRL_Types $types
	 */
	private function validateCalculations( XBRL_Types &$types )
	{
		// Get the calculation links array for a role after adjusting for order and probibition
		$roles =& $this->getInstanceTaxonomy()->getCalculationRoleRefs();

		/* ---------------------------------------------------------
		 * Look for directed cycles
		 * ---------------------------------------------------------
		 */

		/**
		 * Examine the arcs recursively to look for directed cycles.
		 * If there are directed cyles then the $alias of an arc will appear in the list of $parents
		 *
		 * @var function $detectDirected
		 * @param string $source
		 * @param array $parents
		 * @return bool
		 */
		$detectDirected = function( $roleKey, $source, $parents = array() ) use( &$detectDirected, &$roles )
		{
			foreach ( $roles[ $roleKey ]['calculations'][ $source ] as $target => $details )
			{
				if ( $target == $source || in_array( $target, $parents ) )
				{
					XBRL_Log::getInstance()->instance_validation( "5.2.5.2", "The calculation summation-item arcs contain directed cycles",
						array(
							'path' => implode( '->', array_merge( $parents, array( $target ) ) ),
						)
					);
					return true;
				}

				if ( ! isset( $roles[ $roleKey ]['calculations'][ $target ] ) ) continue;

				if ( $detectDirected( $roleKey, $target, array_merge( $parents, array( $source ) ) ) ) return true;
			}

			return false;
		};

		foreach ( $roles as $roleKey => $role )
		{
			if ( ! isset( $role['calculations'] ) ) continue;
			$cycleDetected = false;

			foreach ( $role['calculations'] as $from => $items )
			{
				$parts = parse_url( $from );
				if ( ! isset( $parts['path'] ) || ! isset( $parts['fragment'] ) ) continue;

				$fromTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForXSD( $parts['path'] );
				if ( ! $fromTaxonomy )
				{
					$this->log()->instance_validation( "5.2.5.2.2", "The taxonomy of the summation source does not exist in the DTS",
						array(
							'role' => $roleKey,
							'from' => $from,
						)
					);
					continue;
				}

				if ( ! $cycleDetected )
				{
					// No need to detect cycles between summation arcs
					// $cycleDetected |= $detectDirected( $roleKey, $from );
				}

				$fromElement = $fromTaxonomy->getElementById( $parts['fragment'] );
				if ( ! $fromElement )
				{
					$this->log()->instance_validation( "5.2.5.2.2", "The concept of the summation source does not exist in the DTS",
						array(
							'role' => $roleKey,
							'from' => $from,
						)
					);
					continue;
				}

				$type = $types->getType( $fromElement['type'] );
				if ( ! $type )
				{
					$this->log()->instance_validation( "5.2.5.2.2" , "Cannot locate type information for the summation item",
						array(
							'role' => $roleKey,
							'type' => $fromElement['type'],
							'from' => $from,
						)
					);
					continue;
				}

				if ( ! $type['numeric'] )
				{
					$this->log()->instance_validation( "5.2.5.2.2" , "The summation item type is not numeric",
						array(
							'role' => $roleKey,
							'type' => $fromElement['type'],
							'from' => $from,
						)
					);
					continue;
				}

				// Look for the from fact(s) in the instance document
				// BMS 2018-04-27 Should use the name when retrieving the facts.  Id works OK when the id and name are the same.
				// $fromFact = $this->getElement( $fromElement['id'] );
				$fromFact = $this->getElement( $fromElement['name'] );

				if ( ! $fromFact )
				{
					// If the source fact does not exist then there is nothing to check
					/*
					$this->log()->instance_validation( "5.2.5.2.2" , "Cannot locate source fact for the summation item",
						array(
							'type' => $fromElement['type'],
							'element' => $from,
						)
					);
					*/
					continue;
				}

				$fromCombinations = new TupleDictionary(); // Detect duplicates

				foreach ( $fromFact as $fromFactKey => $fromFactEntry )
				{
					if ( isset( $fromFactEntry['duplicate'] ) && $fromFactEntry['duplicate'] )
					{
						continue;
					}

					$fromValue = $this->getNumericPresentation( $fromFactEntry );

					// Create a key to test if this source has been used before.  Values are not included.
					$key = array(
						'name'		=> $fromElement['id'],
						'parent'	=> $fromFactEntry['parent'],
						'context'	=> $this->getContext( $fromFactEntry['contextRef'] ),
						'unit'		=> $this->getUnit( $fromFactEntry['unitRef'] ),
					);

					$existing =& $fromCombinations->getValue( $key, null );
					if ( ! is_null( $existing ) )
					{
						$source = array(
							'section'	 => '5.2.5.2',
							'name'		 => $fromElement['id'],
							'contextRef' => "{$fromFactEntry['contextRef']} vs {$existing['context']}",
							'unitRef'	 => "{$fromFactEntry['unitRef']} vs {$existing['unit']}",
							'value'		 => "{$fromValue} vs {$existing['value']}",
						);

						$this->log()->warning( sprintf( "Duplicate summation-item: %s", $this->log()->arrayToDescription( $source ) ) );

						// Ignore the duplicate
						continue;
					}

					$fromCombinations->addValue( $key, array(
						'name'		=> $fromElement['id'],
						'context'	=> $fromFactEntry['contextRef'],
						'unit'		=> $fromFactEntry['unitRef'],
						'value'		=> $fromValue,
					) );

					// Ignore sources with nil values
					if ( ! XBRL_Instance::isEmpty( $fromFactEntry, 'nil' ) && filter_var( $fromFactEntry['nil'], FILTER_VALIDATE_BOOLEAN ) ) continue;

					$itemCombinations = new TupleDictionary(); // Detect duplicates
					$itemValues = array();
					$hasDuplicates = false;
					$missingItemFacts = array();

					foreach ( $items as $itemKey => $item )
					{
						// A weight must be defined
						if ( ! isset( $item['weight'] ) )
						{
							$this->log()->instance_validation( "5.2.5.2.1", "A weight must exist on the calculation link",
								array(
									'role' => $roleKey,
									'from' => $from,
									'item' => $item['to'],
								)
							);
						}

						$parts = parse_url( $item['to'] );
						if ( ! isset( $parts['path'] ) || ! isset( $parts['fragment'] ) ) continue;

						// A taxonomy for the item must exist
						$itemTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForXSD( $parts['path'] );
						if ( ! $itemTaxonomy )
						{
							$this->log()->instance_validation( "5.2.5.2.2", "The taxonomy of the alias does not exist in the DTS",
								array(
									'role' => $roleKey,
									'item' => $item['to'],
								)
							);
							continue;
						}

						// A concept for the item must exist
						$itemElement = $itemTaxonomy->getElementById( $parts['fragment'] );
						if ( ! $itemElement )
						{
							$this->log()->instance_validation( "5.2.5.2.2", "The concept of the alias does not exist in the DTS",
								array(
									'role' => $roleKey,
									'item' => $item['to'],
								)
							);
							continue;
						}

						// The concept must have a type
						$itemType = $types->getType( $itemElement['type'] );
						if ( ! $itemType )
						{
							$this->log()->instance_validation( "5.2.5.2.2" , "Cannot locate type information for the summation item",
								array(
									'role' => $roleKey,
									'type' => $itemElement['type'],
									'element' => $item['to'],
								)
							);
							continue;
						}

						// The concept type must be numeric
						if ( ! $itemType['numeric'] )
						{
							$this->log()->instance_validation( "5.2.5.2.2" , "The summation item type is not numeric",
								array(
									'type' => $itemElement['type'],
									'element' => $item['to'],
								)
							);
							continue;
						}

						// Look for the item fact(s) in the instance document
						// BMS 2018-04-27 Should use the name when retrieving the facts.  Id works OK when the id and name are the same.
						// $itemFact = $this->getElement( $itemElement['id'] );
						$itemFact = $this->getElement( $itemElement['name'] );

						if ( ! $itemFact ) continue;

						// Only select fact entries that have the same context ref
						$itemFact = array_filter( $itemFact, function( $itemFactEntry ) use( $fromFactEntry )
						{
							return	$itemFactEntry['contextRef'] == $fromFactEntry['contextRef'] ||
									XBRL_Equality::context_equal( $this->getContext( $fromFactEntry['contextRef'] ), $this->getContext( $itemFactEntry['contextRef'] ) );
						} );

						if ( ! $itemFact )
						{
							$missingItemFacts[ $itemElement['id'] ] = $itemElement;
							continue;
						}

						foreach ( $itemFact as $itemFactKey => $itemFactEntry )
						{
							if ( isset( $itemFactEntry['duplicate'] ) && $itemFactEntry['duplicate'] )
							{
								$hasDuplicates = true;
								break;
							}

							// The item cannot be nil valued
							if ( ! XBRL_Instance::isEmpty( $itemFactEntry, 'nil' ) && filter_var( $itemFactEntry['nil'], FILTER_VALIDATE_BOOLEAN ) ) continue;

							// This entry MUST be c-equal with the source (5.2.5.2)
							// check the source and item are c-Equal (by definition also s-Equal)
							if ( $fromFactEntry['contextRef'] != $itemFactEntry['contextRef'] &&
								! XBRL_Equality::context_equal( $this->getContext( $fromFactEntry['contextRef'] ), $this->getContext( $itemFactEntry['contextRef'] ) )
							)
							{
								continue;
							}

							// This entry MUST be u-equal with the source (5.2.5.2)
							// check the source and item are u-Equal (by definition also s-Equal)
							if ( $fromFactEntry['unitRef'] != $itemFactEntry['unitRef'] &&
								! XBRL_Equality::unit_equal( $this->getUnit( $fromFactEntry['unitRef'] ), $this->getUnit( $itemFactEntry['unitRef'] ), $types, $this->getInstanceNamespaces() )
							)
							{
								continue;
							}

							// This entry MUST be a sibling of the source, a descendent of the source or a descendent of one of its siblings
							if ( ! $this->hasAncestorGuid( $itemFactEntry, $fromFactEntry ) )
							{
								// Out of scope
								continue;
							}

							$itemValue = $this->getNumericPresentation( $itemFactEntry );

							// Create a key to test if this item has been used before
							$key = array(
								'name'		=> $itemElement['id'],
								'parent'	=> $itemFactEntry['parent'],
								'context'	=> $this->getContext( $itemFactEntry['contextRef'] ),
								'unit'		=> $this->getUnit( $itemFactEntry['unitRef'] ),
								// 'value'		=> $itemValue,
							);

							$existing =& $itemCombinations->getValue( $key, null );
							if ( ! is_null( $existing ) )
							{
								$source = array(
									'section'	 => '5.2.5.2',
									'name'		 => $itemElement['id'],
									'contextRef' => "{$itemFactEntry['contextRef']} vs {$existing['context']}",
									'unitRef'	 => "{$itemFactEntry['unitRef']} vs {$existing['unit']}",
									'value'		 => "{$itemValue} vs {$existing['value']}",
								);

								$this->log()->warning( sprintf( "Duplicate summation-item: %s", $this->log()->arrayToDescription( $source ) ) );

								// Ignore the duplicate but if there is a duplicate the comparison is aborted
								$hasDuplicates = true;
								// BMS 2018-07-20 A duplicate item means the calculation is void (section 5.2.5.2) so move to the next calculation
								break 2;
							}

							$itemCombinations->addValue( $key, array(
								'name'		=> $itemElement['id'],
								'context'	=> $itemFactEntry['contextRef'],
								'unit'		=> $itemFactEntry['unitRef'],
								'value'		=> $itemValue,
							) );

							// If both the 'from' and the 'to' concepts have a balance then make sure the item
							// weight complies with the constraints defined in the XBRL 2.1 spec 5.1.1.2 table 6
							if ( ! empty( $fromElement['balance'] ) && ! empty( $itemElement['balance'] ) )
							{
								// Balance @	Balance @	CalulationArc
								// From item	To item		Illegal weight values
								// =========	=========	=====================
								// debit		debit		Negative (< 0)
								// debit		credit		Positive (> 0)
								// credit		debit		Positive (> 0)
								// credit		credit		Negative (< 0)
								$constraints = array(
									'debit'  => array(
										'debit'  => '>',
										'credit' => '<',
									),
									'credit' => array(
										'debit'  => '<',
										'credit' => '>',
									),
								);

								$expected = $constraints[ $fromElement['balance'] ][ $itemElement['balance'] ];
								if ( $expected == '>' && $item['weight'] < 0 || $expected == '<' && $item['weight'] > 0 )
								{
									$this->log()->instance_validation( "5.1.1.2" , "Calculation arc weight not valid for the 'from' and 'to' concept balance values",
										array(
											'role' => $roleKey,
											'from' => $fromElement['id'],
											'to' => $itemElement['id'],
											'from balance' => $fromElement['balance'],
											'to balance' => $itemElement['balance'],
											'expected weight' => $expected == '>' ? "a positive value" : "a negative value",
										)
									);
								}
							}

							// Check that the context of the from is s-equal to the context of the item
							if ( $itemFactEntry['contextRef'] != $fromFactEntry['contextRef'] )
							{
								if ( ! XBRL_Equality::context_equal( $this->getContext( $itemFactEntry['contextRef'] ), $this->getContext( $fromFactEntry['contextRef'] ) ) )
								{
									// TODO
									// $x = 1;
								}
							}

							$itemValues[ $itemElement['id'] ][] = ( $itemValue ? $itemValue : 0 ) * $item['weight'];
						}

						if ( $hasDuplicates )
						{
							// BMS 2018-07-20 A duplicate item means the calculation is void (section 5.2.5.2) so move to the next calculation
							break;
						}

					}

					// If there are no values then there is no comparison to perform (5.2.5.2 - '1. S has at least one contributing item')
					// If there are duplicate items the comparison cannot be performed (5.2.5.2 - '3. None of the contributing items are duplicates')
					if ( $hasDuplicates || ! count( $itemValues ) )
					{
						continue;
					}

					// Compare the item values with the source value
					$sum = array_sum( array_map( function( $item ) { return array_sum( $item ); }, $itemValues ) );
					$inferredDecimals = $this->getDecimals( $fromFactEntry );
					$comparisonTotal = is_infinite( $inferredDecimals ) ? $sum : round( $sum, $this->getDecimals( $fromFactEntry ), PHP_ROUND_HALF_EVEN );

					// The $sum value has to be rounded using the same level of precision as the source
					if ( $comparisonTotal == $fromValue )
					{
						continue;
					}

					$flattenedValues = array_map( function( $item ) {
						return "[" . join( ',', $item ) . "]";
					}, $itemValues );

					$this->log()->instance_validation( "5.2.5.2" , "The calculation source and corresponding items are not equivalent",
						array(
							'role' => $roleKey,
							'from' => $from,
							'from value' => $fromValue,
							'contextRef' => $fromFactEntry['contextRef'],
							'precision' => $this->getPrecision( $fromFactEntry ) ,
							'item total' => $sum,
							'comparison total' => $comparisonTotal,
							'item values' => $this->log()->arrayToDescription( $flattenedValues ),
							'missing items' => $missingItemFacts
								? implode( ", ", array_map( function( $item ) { return $item['id']; }, $missingItemFacts ) )
								: 'None',
						)
					);

				}
			}
		}
	}

	/**
	 * A record of the context dimension information read.
	 * This is cached for fact validation process to preven
	 * repeated requests for the same information.
	 *
	 * @var array $contextDimensionMemberList
	 */
	private $contextDimensionMemberList = array();

	/**
	 * Validate a specific entry associated with a specific type of fact
	 *
	 * @param array $entry			An array of standard elements describing a fact entry
	 * @param string $factKey		The key (name) of the fact entry
	 * @param string $parent				An identifier representing the parent node in the instance document
	 * @param XBRL_Types $types				A types instance
	 * @param array $prefixes				A list of valid prefixes and namespaces for this document
	 * @param array|false $primaryItem		A primary item associated with the entry or false
	 * @param array|false $drsHypercubes	A list of the hypercubes associated with the primary item
	 * @return array The array will be empty if there is no primary item.  If there is a primary
	 * 				 item the result will indicate the status of the dimensional validation tests.
	 */
	private function validateElementEntry( $entry, $factKey, $parent, $types, $prefixes, $primaryItem, $drsHypercubes )
	{
		// This should support extensible enumerations 2.0 as well as 1.0 but until there is a conformance suite to validate that assertion...
		if ( $types->resolvesToBaseType( $entry['taxonomy_element']['type'], array( XBRL_Constants::$enumItemType, XBRL_Constants::$enumSetItemType ) ) )
		{
			// Although the type is correct, the enumeration defintion must be valid to be processed.
			// Any errors will have been reported in taxonomy validation.
			if ( $entry['value'] && ! isset( $entry['nil'] ) &&
				 isset( $entry['taxonomy_element']['enumDomain'] ) &&
				 isset( $entry['taxonomy_element']['enumLinkrole'] ) &&
				 isset( $entry['taxonomy_element']['enumHeadUsable'] )
			)
			{
				$valid = false;

				$domainQname = qname( $entry['taxonomy_element']['enumDomain'], $this->getInstanceNamespaces() );
				if ( $domainQname )
				{
					$taxonomy = $this->getTaxonomyForNamespace( $domainQname->namespaceURI );
					if ( $taxonomy )
					{
						$members = $taxonomy->getDefinitionRoleDimensionMembers( $entry['taxonomy_element']['enumLinkrole'] );
						$mergedMembers = $members;

						// Pull in any target role members
						foreach ( $members as $memberId => $member )
						{
							foreach ( $member['parents'] as $parentId => $parent )
							{
								if ( ! isset( $parent['targetRole'] ) ) continue;
								$targetMembers = $taxonomy->getDefinitionRoleDimensionMembers( $parent['targetRole'] );
								$mergedMembers = array_merge( $targetMembers, $mergedMembers );
								unset( $mergedMembers[ $memberId ]['parents'][ $parentId ]['targetRole'] );
							}
						}

						$members = $mergedMembers;
						unset( $mergedMembers );

						$domainElement = $taxonomy->getElementByName( $domainQname->localName );

						if ( $domainElement )
						{
							$domainId = "{$taxonomy->getTaxonomyXSD()}#{$domainElement['id']}";

							// In extensible enumerations 2.0 there can be more that one value in a 'token'
							// which means the parts can be separated by any number of white space chars
							$values = array_filter( preg_split("/[\s\t\r\n]+/", $entry['value'] ) );
							foreach ( $values as $value )
							{
								$valueQname = qname( $value, $this->getInstanceNamespaces() );
								if ( $valueQname )
								{
									if ( $domainQname->namespaceURI == $valueQname->namespaceURI )
									{
										$memberElement = $taxonomy->getElementByName( $valueQname->localName );
										if ( $memberElement )
										{
											$memberId = "{$taxonomy->getTaxonomyXSD()}#{$memberElement['id']}";

											// Look for a domain member with an id the same as the member element id
											if ( isset( $members[ $memberId ] ) )
											{
												// If the entry concept domain does not equal the member element id then it must equal a parent of the member element
												$valid = $memberId == $domainId;
												if ( $valid && ! $entry['taxonomy_element']['enumHeadUsable'] )
												{
													$valid = false;
												}
												else
												{
													if ( ! $valid )
													{
														$traverseParents = function( $member, $needle ) use ( &$traverseParents, &$members )
														{
															foreach ( $member['parents'] as $parentId => $parent )
															{
																if ( isset( $parent['usable'] ) && ! $parent['usable'] ) continue;
																if ( $parentId == $needle ) return true;
																if ( ! isset( $members[ $parentId ] ) ) continue;
																if ( $traverseParents( $members[ $parentId ], $needle ) ) return true;
															}

															return false;
														};

														$valid = $traverseParents( $members[ $memberId ], $domainId );
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}

				if ( ! $valid )
				{
					$this->log()->instance_validation( 'extensible enumeration', '',
						array(
							'id' => $entry['taxonomy_element']['id'],
							'value' => $entry['value'],
							'error' => 'enumie:InvalidFactValue',
						)
					);
				}
			}
		}
		else if ( XBRL::isTuple( $entry['taxonomy_element'] ) )
		{
			// echo "Tuple";
			// 4.9 The tuple entry MUST NOT have a context ref or unit ref
			// 4.9 Tuples must be in the tuple substitution group
			if ( ! XBRL_Instance::isEmpty( $entry, 'contextRef' ) )
			{
				$this->log()->instance_validation( "4.9", "A tuple entry MUST NOT include context reference",
					array(
						'tupleid' => $entry['taxonomy_element']['id'],
					)
				);
			}

			if ( ! XBRL_Instance::isEmpty( $entry, 'unitRef' ) )
			{
				$this->log()->instance_validation( "4.9", "A tuple entry MUST NOT include unit reference",
					array(
						'tupleid' => $entry['taxonomy_element']['id'],
					)
				);
			}

			$this->validateFacts( $entry['tuple_elements'], $entry['taxonomy_element']['id'], $types, $prefixes );

			return;

		}

		// All fact items must have a context
		if ( XBRL_Instance::isEmpty( $entry, 'contextRef' ) )
		{
			$this->log()->instance_validation( "4.6", "The fact is missing context ref attribute",
				array(
					'fact' => $entry['taxonomy_element']['id'],
				)
			);

			return;
		}

		// The context ref must exist
		$context = $this->getContext( $entry['contextRef'] );
		if ( ! $context )
		{
			$reason = "The contextRef does not exist";
			// Look to see if the id is for a unit or element
			if ( $this->getUnit( $entry['contextRef'] ) )
			{
				$reason = "The contextRef references a unit";
			}
			else if ( $this->getElements( $entry['contextRef'] ) )
			{
				$reason = "The contextRef references a fact";
			}

			$this->log()->instance_validation( "4.6", "The fact context ref does not refer to an existing context defintion",
				array(
					'fact' => $entry['taxonomy_element']['id'],
					'context' => $entry['contextRef'],
					'reason' => $reason,
				)
			);

			return;
		}

		$periodType = $entry['taxonomy_element']['periodType'];

		// 4.7.2 If the fact has a period type of duration then the context must have different start and end date or be forever
		// If the fact has a period type of instant then the context must also be an instant type
		if (
			 ( ( $periodType == 'duration' || $periodType == 'forever' ) && $context['period']['type'] == 'instant' ) ||
			 ( $periodType == 'instant' && $context['period']['type'] != 'instant' )
		   )
		{
			$this->log()->instance_validation( "4.7.2", "The period type of the taxonomy element and the period type of the context do not agree.",
				array(
					'id' => $entry['taxonomy_element']['id'],
					'concept_period_type' => $entry['taxonomy_element']['periodType'],
					'contextRef' => $entry['contextRef'],
					'context_period_type' => $context['period']['type'],
				)
			);
		}

		$isNil = ! XBRL_Instance::isEmpty( $entry, 'nil' ) && filter_var( $entry['nil'], FILTER_VALIDATE_BOOLEAN );
		if ( $isNil )
		{
			// There should be no value
			if ( ! XBRL_Instance::isEmpty( $entry, 'value' ) )
			{
				$this->log()->instance_validation( "4.6", "The period type of the taxonomy element and the period type of the context do not agree.",
					array(
						'id' => $entry['taxonomy_element']['id'],
						'concept_period_type' => $entry['taxonomy_element']['periodType'],
						'contextRef' => $entry['contextRef'],
						'context_period_type' => $context['period']['type'],
					)
				);
			}

			if ( ! XBRL_Instance::isEmpty( $entry, 'precision' ) || ! XBRL_Instance::isEmpty( $entry, 'decimals' ) )
			{
				$this->log()->instance_validation( "4.6.3", "Nil facts MUST NOT have either a 'precision' or a 'decimals' attribute",
					array(
						'id' => $entry['taxonomy_element']['id'],
					)
				);
			}

			// BMS 2018-05-02 XBRL 2.1 test 397 V-05 is an example where a nilled fact does have a unitref
			// if ( ! XBRL_Instance::isEmpty( $entry, 'unitRef' ) )
			// {
			// 	$this->log()->instance_validation( "4.6.2", "Nil facts MUST NOT have 'unitRef' attribute",
			// 		array(
			// 			'id' => $entry['taxonomy_element']['id'],
			// 		)
			// 	);
			// }
		}

		$qName = qname( $entry['taxonomy_element']['type'], $prefixes );
		$numeric = $types->isNumeric( $qName );
		$fractionType = false;

		if ( ! $isNil && $numeric )
		{
			// 4.6.2 All numeric facts must have a unit ref
			if ( XBRL_Instance::isEmpty( $entry, 'unitRef' ) )
			{
				$this->log()->instance_validation( "4.6.2", "Numeric facts MUST have a unit reference",
					array(
						'id' => $entry['taxonomy_element']['id'],
					)
				);
			}
			// The unit ref must exist
			else if ( ! $this->getUnit( $entry['unitRef'] ) )
			{
				$reason = "The contextRef does not exist";
				// Look to see if the id is for a context or element
				if ( $this->getContext( $entry['unitRef'] ) )
				{
					$reason = "The unitRef references a context";
				}
				else if ( $this->getElements( $entry['unitRef'] ) )
				{
					$reason = "The unitRef references a fact";
				}

				$this->log()->instance_validation( "4.6", "The fact unit ref does not refer to an existing unit defintion",
					array(
						'fact' => $entry['taxonomy_element']['id'],
						'context' => $entry['unitRef'],
						'reason' => $reason,
					)
				);

				return false;
			}

			$fractionType = $types->resolvesToBaseType( $entry['taxonomy_element']['type'], array( "xbrli:fractionItemType" ) ) && ! $isNil;
			if ( ! $fractionType )
			{
				$numeric = true;
				// 4.6.3 A numeric fact MUST have a precision or decimals unless fractionItemType or nil
				if ( XBRL_Instance::isEmpty( $entry, 'precision' ) && XBRL_Instance::isEmpty( $entry, 'decimals' ) )
				{
					if ( ! $isNil )
					{
						$this->log()->instance_validation( "4.6.3", "Numeric facts MUST have either a 'precision' or a 'decimals' attribute",
							array(
								'id' => $entry['taxonomy_element']['id'],
							)
						);
					}
				}

				// 4.6.3 but not both
				if ( ! XBRL_Instance::isEmpty( $entry, 'precision' ) && ! XBRL_Instance::isEmpty( $entry, 'decimals' ) )
				{
					$this->log()->instance_validation( "4.6.3", "Numeric facts MUST NOT have both a 'precision' and a 'decimals' attribute",
						array(
							'id' => $entry['taxonomy_element']['id'],
						)
					);
				}

				// 4.6.4 A precision must be positive integer or INF
				if ( ! XBRL_Instance::isEmpty( $entry, 'precision' ) && ! ( ( filter_var( $entry['precision'], FILTER_VALIDATE_INT ) !== false && $entry['precision'] >= 0 ) || strtoupper( $entry['precision'] ) == 'INF' ) )
				{
					$this->log()->instance_validation( "4.6.4", "The numeric fact 'precision' MUST be a positive integer or be INF",
						array(
							'id' => $entry['taxonomy_element']['id'],
							'precision' => $entry['precision'],
						)
					);
				}

				// 4.6.5 A decimals must be an integer or INF
				if ( ! XBRL_Instance::isEmpty( $entry, 'decimals' ) && ! ( filter_var( $entry['decimals'], FILTER_VALIDATE_INT ) !== false || strtoupper( $entry['decimals'] ) == 'INF' ) )
				{
					$this->log()->instance_validation( "4.6.5", "The numeric facts 'decimals' attribute MUST be an integer or INF",
						array(
							'id' => $entry['taxonomy_element']['id'],
							'decimals' => $entry['decimals'],
						)
					);
				}

				// The value must be numeric
				if ( ! $isNil &&
					 ! XBRL_Instance::isEmpty( $entry, 'value' ) &&
			 		 ! is_numeric( $this->getInstanceTaxonomy()->removeNumberValueFormatting( $entry ) )
				   )
				{
					$this->log()->instance_validation( "4.6.5", "The numeric fact value must be a number",
						array(
							'id' => $entry['taxonomy_element']['id'],
							'value' => $entry['value'],
						)
					);
				}

				$monetaryType = ! $isNil && $types->resolvesToBaseType( $entry['taxonomy_element']['type'], array( XBRL_Constants::$xbrliMonetaryItemType ) );

				if ( $monetaryType )
				{
					$valid = false;
					$reason = "";

					// Get the unit - it must exist as its been validated already but you never know
					if ( $this->units[ $entry['unitRef'] ] )
					{
						$unit = $this->units[ $entry['unitRef'] ];

						// There should be only one measure
						if ( is_string( $unit ) )
						{
							$valid = $this->validateMeasure( $unit, $entry['unitRef'], $types, true );
							$reason = sprintf( "The local name part is not correct (%s)", $unit );
							$valid = true;
						}
						else
						{
							$reason = "The xbrli namespace cannot be found or is incorrect";
						}
					}

					if ( ! $valid )
					{
						$this->log()->instance_validation( "4.8.1", "Invalid unit for monetary fact",
							array(
								'id' => $entry['taxonomy_element']['id'],
								'unit' => $entry['unitRef'],
								'reason' => $reason,
							)
						);
					}
				}

				$sharesType = ! $isNil && $types->resolvesToBaseType( $entry['taxonomy_element']['type'], array( XBRL_Constants::$xbrliSharesItemType ) );
				$pureType = ! $isNil && $types->resolvesToBaseType( $entry['taxonomy_element']['type'], array( XBRL_Constants::$xbrliPureItemType ) );
				if ( $pureType || $sharesType )
				{
					$valid = false;
					$reason = "";

					// Get the unit - it must exist as its been validated already but you never know
					if ( $this->units[ $entry['unitRef'] ] )
					{
						$unit = $this->units[ $entry['unitRef'] ];

						// There should be only one measure
						if ( is_string( $unit ) )
						{
							$measureQName = qname( $unit, $this->getInstanceNamespaces() );
							if ( $pureType )
							{
								$valid = $measureQName->localName == 'pure';
							}
							else
							{
								$valid = $measureQName->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] && $measureQName->localName == 'shares';
							}

							if ( ! $valid ) $reason = "The xbrli namespace cannot be found or is incorrect";
						}
					}

					if ( ! $valid )
					{
						$this->log()->instance_validation( "4.8.1", $pureType ? "Invalid unit for pure fact" : "Invalid unit for share fact",
							array(
								'id' => $entry['taxonomy_element']['id'],
								'unit' => $entry['unitRef'],
								'reason' => $reason,
							)
						);
					}
				}

			}
		}

		if ( ! $numeric )
		{
			// 4.6.2 All non-numeric facts must not have a unit ref
			if ( ! XBRL_Instance::isEmpty( $entry, 'unitRef' ) )
			{
				$this->log()->instance_validation( "4.6.2", "Non-numeric facts MUST NOT have a 'unitRef' attribute",
					array(
						'id' => $entry['taxonomy_element']['id'],
					)
				);
			}

			if ( $qName->localName == 'QNameItemType' && $qName->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )
			{
				$factValueQName = qname( $entry['value'], $this->getInstanceNamespaces() );
				if ( ! $factValueQName )
				{
					$this->log()->instance_validation( "QNameItemType", "Invalid QName value",
						array(
							'id' => $entry['taxonomy_element']['id'],
							'value' => $entry['value'],
						)
					);
				}
			}
		}

		if ( ! $numeric || $fractionType )
		{
			// 4.6.3 A non-numeric or fraction fact cannot have decimal or precision
			if ( ! XBRL_Instance::isEmpty( $entry, 'precision' ) || ! XBRL_Instance::isEmpty( $entry, 'decimals' ) )
			{
				$this->log()->instance_validation( "4.6.3", "Non-numeric facts MUST NOT have either a 'precision' or a 'decimals' attribute",
					array(
						'id' => $entry['taxonomy_element']['id'],
					)
				);
			}
		}

		if ( ! $primaryItem ) return array();

		// If the context has a dimensional segment or scenario then the element MUST be a primary item
		// Resolve the dimensional relationship set for the entry primary item
		if ( ! count( $drsHypercubes ) ) return;

		// $xsd = $this->getInstanceTaxonomy()->getTaxonomyForNamespace( $this->getNamespaceForPrefix( $entry['namespace'] ) )->getTaxonomyXSD();
		$primaryItemId = $entry['label'];

		// Hypercubes in different roles are evaluated independently
		$anyValidHypercubeSet = false;

		// Validation result by role
		$validationResult = array();

		foreach ( $primaryItem['roles'] as $roleUri )
		{
			// If the primary items does not have any hypercubes then there is no dimensional validation to do
			if ( isset( $primaryItem[ $roleUri ]['hypercubes'] ) && $primaryItem[ $roleUri ]['hypercubes'] )
			{
				// Hypercubes in within a role are 'conjoined' (XDT specification 2.3.1)
				$validHypercubes = true; // Assume valid

				foreach ( $drsHypercubes as $hypercubeId => &$roles )
				{
					if ( ! isset( $roles[ $roleUri ] ) )
					{
						continue;
					}

					$hypercube = &$roles[ $roleUri ];

					if ( ! isset( $hypercube['parents'][ $primaryItemId ] ) )
					{
						// Should never happen
						$this->log()->warning( "The parents of hypercube '$hypercubeId' do not include primary item '$primaryItemId' and this should never happen" );
						continue;
					}

					// See if the dimension information has been retrieved for this context
					$contextElement = $hypercube['parents'][ $primaryItemId ]['contextElement'];
					if ( ! isset( $this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ] ) )
					{
						$this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['data'] = array();
						$this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['extra'] = false;

						// No so retrieve and record
						$dimInfo = $this->getElementsForContext( $entry['contextRef'], false, $contextElement );
						if ( $dimInfo )
						{
							foreach ( $dimInfo as $dimInfoKey => $item )
							{
								if ( isset( $item['dimension'] ) )
								{
									$dimTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForNamespace( $item['dimension']['namespace'] );
									$dimXsd = $dimTaxonomy->getTaxonomyXSD();
									$dim = "$dimXsd#{$item['dimension']['element']['id']}";

									$this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['data'][ $dim ] = array();
									$dimElement = $dimTaxonomy->getElementById( $dim );
									if ( ! $dimElement )
									{
										continue;
									}

									if ( isset( $item['type'] ) && $item['type'] == 'explicitMember' )
									{
										$memElement = false;
										$memTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForNamespace( $item['member']['namespace'] );
										if ( $memTaxonomy )
										{
											$memXsd = $memTaxonomy->getTaxonomyXSD();
											$mem = "$memXsd#{$item['member']['element']['id']}";
											$memElement = $memTaxonomy->getElementById( $mem );

											if ( $memElement )
											{
												$this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['data'][ $dim ][] = $mem;
											}
										}
									}

									if ( isset( $item['type'] ) && $item['type'] == 'typedMember' )
									{
										foreach ( $item['member'] as $type => $codes )
										{
											foreach ( $codes as $code )
											{
												$code = trim( $code );
												$this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['data'][ $dim ][] = $code;
											}
										}

										$this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['data'][ $dim ] = array_unique( $this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['data'][ $dim ] );
									}
								}
								else
								{
									$this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['extra'] = true;
								}
							}
						}
					}

					$hasHypercube = $hypercube['parents'][ $primaryItemId ]['arcrole'] == XBRL_Constants::$arcRoleAll;
					$isClosed = isset( $hypercube['parents'][ $primaryItemId ]['closed'] ) && $hypercube['parents'][ $primaryItemId ]['closed'];

					// Identify any context dimensions that are not part of the hypercube
					$additionalContextDimensions = array_diff_key( $this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['data'], $hypercube['dimensions'] );
					if ( $isClosed && ( $this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['extra'] || count( $additionalContextDimensions ) ) )
					{
						// There are additional dimensions but the hypercube is closed
						$validHypercubes = $hasHypercube ? false : true;
						break;
					}

					$dimensions = array();
					foreach ( $hypercube['dimensions'] as $dimensionId => &$dimension )
					{
						if ( ! isset( $dimension['namespace'] ) )
						{
							$taxonomy = $this->getInstanceTaxonomy()->getTaxonomyForXSD( $dimensionId );
							if ( ! $taxonomy )
							{
								$this->log()->warning( "A taxonomy cannot be located for namespace '$namespace'" );
								continue;
							}

							$dimension['namespace'] = $taxonomy->getNamespace();
						}

						// See if there is an entry for this dimension
						$dimensions[ $dimensionId ] = null;

						if ( isset( $this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['data'][ $dimensionId ] ) )
						{
							$contextMembers = $this->contextDimensionMemberList[ $entry['contextRef'] ][ $contextElement ]['data'][ $dimensionId ];

							foreach ( $contextMembers as $contextMember )
							{
								if ( $dimension['explicit'] )
								{
									$parts = explode( "#", $contextMember );
									// Should validate the $parts array is OK (has two items)

									if ( ! isset( $dimension['memberpaths'][ $parts[1] ] ) )
									{
										continue;
									}

									// Find the member
									$this->getInstanceTaxonomy()->processNodeByPath(
										$dimension['members'],
										$dimension['memberpaths'][ $parts[1] ],
										$contextMember,
										function( $node, $path, $id ) use( &$dimensions, &$dimension, &$entry ) {

											// OK, found the label so record it

											// If there is a default make sure this is not it
											if ( isset( $dimension['default'] ) )
											{
												if ( $node['label'] == $dimension['default']['label'] )
												{
													XBRL_Log::getInstance()->dimension_validation( "3.1.4.2", "The default value for a dimension appears in the instance context",
														array(
															'dimension' => $dimension['label'],
															'context' => $entry['contextRef'],
															'error' => 'xbrldie:DefaultValueUsedInInstanceError',
														)
													);

													return;
												}
											}

											if ( in_array( $node['label'], $dimension['unusablemembers'] ) ) return;

											$dimensions[ $dimension['label'] ] = $node['label'];
										}
									);

									// $element = $memberTaxonomy->getElementById( $parts[1] );
									// $name = $element['name'];
								}
								else
								{
									$dimensions[ $dimension['label'] ] = $contextMember;
								}
							}
						}

						// Add the default if there is one
						if ( $hasHypercube && is_null( $dimensions[ $dimensionId ] ) && isset( $dimension['default'] ) )
						{
							$dimensions[ $dimensionId ] = $dimension['default']['label'];
						}

					}

					unset( $dimension );

					// If there are no null dimensions then the hypercube is valid
					$unmatchedDimensions = array_filter( $dimensions, function( $dimension ) { return is_null( $dimension ); } );
					$hypercubeValid = ! count( $unmatchedDimensions );

					// A hypercube is valid if the has-hypercube role is /all and there are no unmatched dimensions or
					// the has-hypercube role is /notAll and *all* the dimensions are unmatched.
					// Within the base set it is the conjunction of the hypercubes that is valid or not
					// 2.3.1 The instantiation of a primary item declaration [Def, 1] in an instance document is dimensionally
					//       valid with respect to a conjunction of hypercubes only if it is valid with respect to all of the
					//	     conjoined hypercubes individually. A negated hypercube notAll is valid if the non negated version
					//		 of the same hypercube definition is invalid. The conjunction of a single hypercube is the hypercube
					//		 itself.
					$validHypercubes &= $hasHypercube ? $hypercubeValid : ! $hypercubeValid;
					if ( ! $validHypercubes )
					{
						break;
					}

					unset( $hypercube );
				}

				unset( $roles );

				// 2.3 These relationships MAY be in different base sets. When has-hypercube relationships are in different base sets,
				//     a Primary Item that is dimensionally valid in any base set is dimensionally valid.
				if ( $validHypercubes )
				{
					$anyValidHypercubeSet = true;
					// break;
				}

				$validationResult['roles'][ $roleUri ] = array(
					'valid' => $validHypercubes,
					'hypercubes' => $primaryItem[ $roleUri ]['hypercubes'],
				);
			}
		}

		$validationResult['success'] = count( array_filter( $validationResult['roles'], function( $role ) { return $role['valid']; } ) ) > 0;

		if ( ! $anyValidHypercubeSet )
		{
			$this->log()->dimension_validation( "3.1.1", "There are no valid hypercubes in the context for the primary item",
				array(
					'context' => "'{$entry['contextRef']}'",
					'primary item' => "'$primaryItemId'",
					'error' => 'xbrldie:PrimaryItemDimensionallyInvalidError',
				)
			);
		}

		return $validationResult;
	}

	/**
	 * Validates the measure component of a unit definition
	 *
	 * @param string $divide
	 * @param string $unitId
	 * @param XBRL_Types $types
	 */
	private function validateDivide( $divide, $unitId, $types )
	{
		$numeratorValidation = true;
		$denominatorValidation = true;

		// The divide MUST and a numerator and a denominator
		if ( isset( $divide['numerator'] ) )
		{
			if ( empty( $divide['numerator'] ) )
			{
				$this->log()->instance_validation( "4.8.4", "The 'unitNumerator' MUST NOT be empty", array( 'unit' => $unitId ) );
				$numeratorValidation = false;
			}
			else
			{
				foreach ( $divide['numerator'] as $measure )
				{
					$numeratorValidation &= $this->validateMeasure( $measure, "$unitId (numerator)", $types );
				}
			}
		}
		else
		{
			$this->log()->instance_validation( "4.8.3", "Both a 'unitNumerator' and a 'unitDenominator' MUST be provided.  The numerator is missing.", array( 'unit' => $unitId ) );
			$numeratorValidation = false;
		}

		// The divide MUST and a numerator and a denominator
		if ( isset( $divide['denominator'] ) )
		{
			if ( empty( $divide['denominator'] ) )
			{
				$this->log()->instance_validation( "4.8.4", "The 'unitDenominator' MUST NOT be empty", array( 'unit' => $unitId ) );
				$denominatorValidation = false;
			}
			else
			{
				foreach ( $divide['denominator'] as $measure )
				{
					$denominatorValidation &= $this->validateMeasure( $measure, "$unitId (denominator)", $types );
				}
			}
		}
		else
		{
			$this->log()->instance_validation( "4.8.3", "Both a 'unitNumerator' and a 'unitDenominator' MUST be provided. The denominator is missing.", array( 'unit' => $unitId ) );
			$denominatorValidation = false;
		}

		if ( ! $numeratorValidation || ! $denominatorValidation ) return false;

		// Check the denominator measures do not appear in the numerator measures
		// First create a list of all the numerator qnames
		$qNames = array();
		foreach ( $divide['numerator'] as $measure )
		{
			$qNames[] = qname( $measure, $this->instance_namespaces )->clarkNotation();
		}

		foreach ( $divide['denominator'] as $measure )
		{
			$qName = qname( $measure, $this->instance_namespaces + array( STANDARD_PREFIX_XBRLI => XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ) );
			if ( in_array( $qName->clarkNotation(), $qNames ) )
			{
				XBRL_Log::getInstance()->instance_validation( "4.8.4", "The measure in a denominator cannot appear in a numerator", array( 'unit' => $unitId, 'measure' => $measure ) );
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates the divide component of a unit definition
	 *
	 * @param string $measure
	 * @param string $unitId
	 * @param XBRL_Types $types
	 * @param bool $forceCurrencyCheck
	 *
	 * @return bool Return false if there has been any validation issue
	 */
	private function validateMeasure( $measure, $unitId, $types, $forceCurrencyCheck = false )
	{
		if ( empty( $measure ) )
		{
			$this->log()->instance_validation( "4.8.2", "The measure cannot be empty", array( 'unit' => $unitId ) );
			return false;
		}

		// The measure must have a value that is a qname with a prefix in scope or a value with a namespace provided as an attribute.
		$qName = qname( $measure, $this->instance_namespaces + array( STANDARD_PREFIX_XBRLI => XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ) );

		if ( ! $qName )
		{
			$this->log()->instance_validation( "4.8.2", "The measure value is not valid",
				array(
					'unit' => $unitId,
					'measure' => $measure,
				)
			);
			return false;
		}

		if ( $qName->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_ISO4217 ] )
		{
			// The local name value MUST be a valid ISO country code.  A minimum is that it should be three characters.
			if ( strlen( $qName->localName ) == 3 ) return true;
			$this->log()->instance_validation( "4.8.2", "The measure value is not a valid ISO country code",
				array(
					'unit' => $unitId,
					'measure' => $measure,
				)
			);
			return false;
		}
		if ( $forceCurrencyCheck )
		{
			$this->log()->instance_validation( "4.8.2", "The measure value for monetary facts must be a valid ISO country code",
				array(
					'unit' => $unitId,
					'measure' => $measure,
				)
			);
			return false;
		}

		// BMS 2018-04-18 This test is not quite right because it's the local name that MUST be pure or shares if the namespace resolves to xbrli
		//				  From 4.8.2:	A <measure> element with a namespace prefix that resolves to the
		//								"http://www.xbrl.org/2003/instance" namespace MUST have a local
		//								part of either "pure" or "shares".
		// if ( $qName->localName == 'pure' && ! isset( $this->instance_namespaces[ $qName->prefix ] ) )
		// if ( $qName->localName == 'pure' && $qName->prefix == STANDARD_PREFIX_XBRLI )
		// {
		// 	if ( $qName->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ) return true;
		// 	$this->log()->instance_validation( "4.8.2", "The xbrli:pure measure must have a namespace of http://www.xbrl.org/2003/instance",
		// 		array(
		// 			'unit' => $unitId,
		// 			'measure' => $measure,
		// 		)
		// 	);
		// 	return false;
		// }
        //
		// if ( $qName->localName == 'shares' )
		// {
		// 	if ( $qName->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ) return true;
		// 	$this->log()->instance_validation( "4.8.2", "The shares measure must have a namespace of http://www.xbrl.org/2003/instance",
		// 		array(
		// 			'unit' => $unitId,
		// 			'measure' => $measure,
		// 		)
		// 	);
		// 	return false;
		// }

		if ( $qName->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )
		{
			if ( ! in_array( $qName->localName, array( 'pure', 'shares' ) ) )
			{
				$this->log()->instance_validation( "4.8.2", "A measure with a namespace of http://www.xbrl.org/2003/instance MUST be 'pure' or 'shares'",
					array(
						'unit' => $unitId,
						'measure' => $measure,
					)
				);

				return false;
			}
		}

		// Otherwise the namespace must be in scope
		return true;
	}

	/**
	 * Used to validate explicit members in dimensional texonomies
	 *
	 * @param array $component The explicit segment or scenario member information
	 * @param string $contextId The id of the context
	 * @param XBRL_Types $types
	 * @param string $componentName The name of the component: scenario or segment
	 * @param array|null $data An object of data created to pass information between function calls
	 * @return boolean
	 */
	private function validateExplicitMembers( $component, $contextId, $types, $componentName, &$data )
	{
		// The component should not be empty
		if ( XBRL_Instance::isEmpty( $component, 'dimension' ) || XBRL_Instance::isEmpty( $component, 'member' ) )
		{
			XBRL_Log::getInstance()->instance_validation(
				$componentName == XBRL_Constants::$xbrliScenario
					? "4.7.4"
					: "4.7.3.2", "A $componentName cannot be empty",
				array(
					'context' => $contextId
				)
			);
		}

		// To finish when doing dimensional validation
		if ( is_null( $data ) ) $data = array();

		// Anyone dimension should appear only once in a component
		if ( isset( $data[ $component['dimension'] ] ) )
		{
			$this->log()->dimension_validation(
				"3.1.4.2",
				"Dimensions have been repeated in scenario or segment components of a context",
				array(
					'context' => "'$contextId'",
					'dimension' => "'{$component['dimension']}'",
					'error' => 'xbrldie:RepeatedDimensionInInstanceError',
				)
			);

			return false;
		}

		// Record this for any future iteration
		$data[ $component['dimension'] ] = true;

		// $dimTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForNamespace( $item['dimension']['namespace'] );
		// $dimXsd = $dimTaxonomy->getTaxonomyXSD();
		// $dim = "$dimXsd#{$item['dimension']['element']['id']}";
		// $dimElement = $dimTaxonomy->getElementById( $dim );

		$parts = explode( ':', $component['dimension'] );
		$dimension = $this->getElementForReference( $parts[0], $parts[1], false );

		// The dimension MUST be explicit 3.1.4.5.2 xbrldie:ExplicitMemberNotExplicitDimensionError
		if ( ! $dimension ||
			 ! $types->resolveToSubstitutionGroup( $dimension['element']['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) ) ||
			 isset( $dimension['element']['typedDomainRef'] )
		)
		{
			$this->log()->dimension_validation(
				"3.1.4.5.2",
				"The dimension MUST resolve to an explicit dimension taxonomy element",
				array(
					'context' => "'$contextId'",
					'dimension' => "'{$component['dimension']}'",
					'error' => 'xbrldie:ExplicitMemberNotExplicitDimensionError',
				)
			);
		}

		$memElement = false;
		$parts = explode( ':', $component['member'] );
		$member = $this->getElementForReference( $parts[0], $parts[1], false );

		// The content of the xbrldi:explicitMember element MUST be a QName whose global element
		// definition can be found in the taxonomy schema referenced by the namespace of the QName.
		// A dimensional processor MUST raise an error xbrldie:ExplicitMemberUndefinedQNameError
		// if this rule is violated.

		if ( ! $member )
		{
			$this->log()->dimension_validation(
				"3.1.4.5.3",
				"The dimension member MUST resolve to a taxonomy element",
				array(
					'context' => "'$contextId'",
					'dimension' => "'{$component['dimension']}'",
					'member' => "'{$component['member']}'",
					'error' => 'xbrldie:ExplicitMemberUndefinedQNameError',
				)
			);
		}

		return true;
	}

	/**
	 * Used to validate typed members in dimensional texonomies
	 *
	 * @param array $component The typed segment or scenario member information
	 * @param string $contextId The id of the context
	 * @param XBRL_Types $types
	 * @param string $componentName The name of the component: scenario or segment
	 * @param array|null $data An object of data created to pass information between function calls
	 * @return boolean
	 */
	private function validateTypedMembers( $component, $contextId, $types, $componentName, &$data )
	{
		// The component should not be empty
		if ( XBRL_Instance::isEmpty( $component, 'dimension' ) || XBRL_Instance::isEmpty( $component, 'member' ) )
		{
			XBRL_Log::getInstance()->instance_validation(
				$componentName == XBRL_Constants::$xbrliScenario
					? "4.7.4"
					: "4.7.3.2", "A $componentName cannot be empty",
				array(
					'context' => $contextId
				)
			);
		}

		// To finish when doing dimensional validation
		if ( is_null( $data ) ) $data = array();

		// Anyone dimension should appear only once in a component (3.1.4..2 (1))
		if ( isset( $data[ $component['dimension'] ] ) )
		{
			$this->log()->dimension_validation(
				"3.1.4.2",
				"Dimensions have been repeated in scenario and segment components of a context",
				array(
					'context' => "'$contextId'",
					'dimension' => "'{$component['dimension']}'",
					'error' => 'xbrldie:RepeatedDimensionInInstanceError',
				)
			);

			return false;
		}

		// 3.1.4.2 (2) (xbrldie:DefaultValueUsedInInstanceError)
		// is handled when evaluating primary items

		// Record this for any future iteration
		$data[ $component['dimension'] ] = true;

		$parts = explode( ':', $component['dimension'] );
		$dimension = $this->getElementForReference( $parts[0], $parts[1], false );

		// The dimension MUST be typed 3.1.4.4.2 xbrldie:TypedMemberNotTypedDimension
		if ( ! $dimension ||
			 ! $types->resolveToSubstitutionGroup( $dimension['element']['substitutionGroup'], array( XBRL_Constants::$xbrldtDimensionItem ) ) ||
			 ! isset( $dimension['element']['typedDomainRef'] )
		)
		{
			$this->log()->dimension_validation(
				"3.1.4.4.2",
				"The dimension MUST resolve to an typed member's dimension taxonomy element",
				array(
					'context' => "'$contextId'",
					'dimension' => "'{$component['dimension']}'",
					'error' => 'xbrldie:TypedMemberNotTypedDimension',
				)
			);

			return false;
		}

		foreach ( $component['member'] as $type => $codes )
		{
			// 3.1.4.4.3 (3) The typed dimension content [Def, 15] MUST be an instantiation of the element
			// pointed to by the @xbrldt:typedDomainRef of the typed dimension indicated in the @dimension
			// attribute of the xbrldi:typedMember element. A dimensional processor MUST raise an error
			// xbrldie:IllegalTypedDimensionContentError if this rule is violated.
			// Note:
			// 3.1.4.4.3 (1) Should be checked when reading the context
			// 3.1.4.4.3 (2) Should be checked when validaing the fact value

			// Need to map the local prefix to the source taxonomy prefix
			$parts = explode( ":", $type );
			$parts[0] = $this->normalizePrefix( $parts[0] );
			$typeElement = $types->getElement( implode( ":", $parts ) );
			if ( ! $typeElement ||
				 ! count( $typeElement['types'] )
			)
			{
				$this->log()->dimension_validation(
					"3.1.4.4.3",
					"The type element of the member MUST be defined by the schema document",
					array(
						'context' => "'$contextId'",
						'dimension' => "'{$dimension['element']['id']}'",
						'type' => "'$type'",
						'error' => 'xbrldie:IllegalTypedDimensionContentError',
					)
				);
			}

			// Get the type ref of the dimension
			$typedDomainRef = $dimension['element']['typedDomainRef'];
			// The typedDomainRef will be something like xxx.xsd#elementName whereas the
			// instance Xml element will be ns:elementName.  The two address forms need to
			// be normalized so the domain ref value and element can be compared.

			$validContentElement = false;

			// BMS 2018-09-09 The existing implementation (immediately below) is too restrictive and wrong
			// It sees $this->getInstanceTaxonomy as the default taxonomy if the $typedDomainRef which is not correct.
			// $domainTaxonomy = XBRL::startsWith( $typedDomainRef, '#')
			//	 ? $this->getInstanceTaxonomy()
			//	 : $this->getInstanceTaxonomy()->getTaxonomyForXSD( $typedDomainRef );

			// If there is no explicit schema the taxonomy of the typedDomainRef is defined by $dimension['namespace']
			$domainTaxonomy = XBRL::startsWith( $typedDomainRef, '#')
				? $this->getInstanceTaxonomy()->getTaxonomyForNamespace( $dimension['namespace'] )
				: $this->getInstanceTaxonomy()->getTaxonomyForXSD( $typedDomainRef );
			if ( ! $domainTaxonomy )
			{
				$this->log()->dimension_validation(
					"3.1.4.4.3",
					"The dimension type member MUST be defined by the DTS",
					array(
						'context' => "'$contextId'",
						'dimension' => "'{$dimension['element']['id']}'",
						'namespace' => $dimension['namespace'],
						'type' => "'$type'",
						'error' => 'xbrldie:IllegalTypedDimensionContentError',
					)
				);

			}

			if ( $domainTaxonomy )
			{
				// $domainNamespace = $domainTaxonomy->getNamespace();
				// $prefix = strstr( $type, ':', true );
				// $elementNamespace = $domainTaxonomy->getNamespaceForPrefix( $prefix );
				$domainId = substr( strstr( $typedDomainRef, '#' ), 1 );
				$domainElement = $domainTaxonomy->getElementById( $domainId );

				// The requirement of this test is for the qname of the typed member to be the same as the qname of the $typedDomainRef
				$typeQName = qname( $type, $this->getInstanceNamespaces() );
				$validContentElement = $typeQName && $typeQName->namespaceURI == $domainTaxonomy->getNamespace() && $typeQName->localName == $domainElement['name'];
				// $domainElementType = $types->getTypeById( $domainId, $domainTaxonomy->getPrefix() );
				// $elementName = substr( strstr( $type, ':' ), 1 );

				// $validContentElement = isset( $domainElementType['name'] ) && $domainNamespace == $elementNamespace && $domainElementType['name'] == $elementName;
			}

			if ( ! $validContentElement )
			{
				$this->log()->dimension_validation(
					"3.1.4.4.3",
					"The type element of the member MUST be defined by the typedDomainRef value of the dimension item",
					array(
						'context' => "'$contextId'",
						'dimension' => "'{$dimension['element']['id']}'",
						'type' => "'$type'",
						'error' => 'xbrldie:IllegalTypedDimensionContentError',
					)
				);

				continue;
			}

			/**
			 * @param string $code The code to validate
			 * @param array $schemaTypes A list of the schema type assigned to the element
			 * @param XBLR_Types $types
			 * @return bool True if the $code can be validated
			 */
			$validateSchemaType = function( $code, $schemaTypes, $types )
			{
				$result = false;
				foreach ( $schemaTypes as $schemaType )
				{
					$type = $types->getType( $schemaType );
					if ( isset( $type['pattern'] ) )
					{
						$result |= preg_match( "/{$type['pattern']}/", $code );
					}
					else if ( $type['name'] == 'string' )
					{
						$result |= is_string( $code );
					}
					else
					{
						// TODO Implement other schema-based validations
						$result = true;
					}
				}
				return $result;
			};

			foreach ( $codes as $code )
			{
				$code = trim( $code );

				if ( $typeElement &&
					 count( $typeElement['types'] ) &&
					 ! $validateSchemaType( $code, $typeElement['types'] , $types )
				)
				{
					$this->log()->dimension_validation(
						"3.1.4.4.3",
						"The typed element dimension member value cannot be validated against the schema",
						array(
							'context' => "'$contextId'",
							'dimension' => "'{$dimension['element']['id']}'",
							'value' => "'$code'",
							'schema types' => "." . implode( "', '", $typeElement['types'] ) . "'",
							'error' => 'xbrldie:IllegalTypedDimensionContentError',
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Validate segment and scenarios that have custom elements as their content
	 *
	 * @param array $componenttMembers A list of nodes
	 * @param string $contextId The id of the current context
	 * @param XBRL_Types $types A reference to the global types instance
	 * @param string $componentName The name of the component: scenario or segment
	 * @param array|null $data An object of data created to pass information between function calls
	 * @return boolean
	 */
	private function validateOtherComponentTypes( $componenttMembers, $contextId, $types, $componentName, &$data )
	{
		// Can't have element types that are defined in the XBRLI namespace
		// Workout what prefix is used for the XBRLI namespace
		$xbrliPrefixes = array( 'xbrli' );
		foreach ( $this->instance_namespaces as $prefix => $namespace )
		{
			if ( $namespace == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )
			{
				array_push( $xbrliPrefixes, $prefix );
			}
		}

		$findPrefixes = function( $nodes ) use( &$findPrefixes, $xbrliPrefixes )
		{
			if ( isset( $nodes['prefix'] ) && in_array( $nodes['prefix'], $xbrliPrefixes ) ) return true;
			if ( isset( $nodes['children'] ) )
			{
				if ( $findPrefixes( $nodes['children'] ) ) return true;
			}
			else
			{
				foreach ( $nodes as $key => $node )
				{
					if ( ! is_array( $node ) ) continue;
					if ( $findPrefixes( $node ) ) return true;
				}
			}

			return false;
		};

		if ( $findPrefixes( $componenttMembers ) )
		{
			XBRL_Log::getInstance()->instance_validation( "4.7.3.2", "The context $componentName content contains one or more elements in the XBRLI namespace",
				array(
					'content' => $contextId,
				)
			);
		}

		return true;
	}

	/**
	 * Used to validate non-dimension segments and scenarios
	 *
	 * @param array $component The explicit member information
	 * @param string $contextId The id of the context
	 * @param XBRL_Types $types
	 * @param string $componentName The name of the component: scenario or segment
	 * @param array|null $data An object of data created to pass information between function calls
	 * @return boolean
	 */
	private function validateNonDimensionMembers( $component, $contextId, $types, $componentName, &$data )
	{
		if ( empty( $component ) )
		{
			XBRL_Log::getInstance()->instance_validation(
				$componentName == XBRL_Constants::$xbrliScenario
					? "4.7.4"
					: "4.7.3.2", "A $componentName cannot be empty",
				array(
					'context' => $contextId,
				)
			);
			return false;
		}

		$qName = qname( $component['name'], $this->instance_namespaces );

		// BMS 2018-04-30 This is rubbish.  It's the elements inside the component that must not be in xbrli
		// The $component element cannot be in the xbrli namespace
		// if ( $qName->namespaceURI == XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] )
		// {
		// 	XBRL_Log::getInstance()->instance_validation(
		// 		$componentName == 'scenario'
		// 			? "4.7.4"
		// 			: "4.7.3.2",
		// 		"A $componentName element cannot be in the xbrl instance namespace",
		// 		array(
		// 			'context' => $contextId,
		// 		)
		// 	);
		// 	return false;
		// }

		// Check the member is valid
		// Don't look for the element type in the types collection.
		// Look for it in the taxonomy element

		$taxonomy = $this->getInstanceTaxonomy()->getTaxonomyForNamespace( $qName->namespaceURI );
		if ( $taxonomy )
		{
			$element = $taxonomy->getElementByName( $qName->localName );
			if ( ! XBRL_Instance::isEmpty( $element, 'substitutionGroup' ) )
			{
				if ( $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( XBRL_Constants::$xbrliItem ) ) )
				{
					XBRL_Log::getInstance()->instance_validation(
						$componentName == 'scenario'
							? "4.7.4"
							: "4.7.3.2", "A $componentName cannot include an element that is in the xbrli:item substitution group",
						array(
							'context' => $contextId,
						)
					);
					return false;
				}
			}

			if ( $element && ( ! XBRL_Instance::isEmpty( $element, 'type' ) || isset( $element['types'] ) ) )
			{
				$type = XBRL_Instance::isEmpty( $element, 'type' )
					? ( count( $element['types'] ) ? $element['types'][0] : "" )
					: $element['type'];

				// If the schema type is a string lookup the type
				if ( is_string( $type ) )
				{
					$qName = qname( $type, $this->instance_namespaces + array( 'xbrli' => XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_XBRLI ] ) );
					$type = $types->getType( $qName->localName, $qName->prefix );
				}

				if ( is_array( $type ) )
				{
					if ( isset( $type['values'] ) )
					{
						// if ( in_array( $component['member'], $type['values'] ) )
						if ( in_array( $component['member'], array_map( function( $value ) { return $value['value']; }, $type['values'] ) ) )
						{
							return true;
						}

						XBRL_Log::getInstance()->instance_validation(
							$componentName == 'scenario'
							? "4.7.4"
							: "4.7.3.2",
							"The $componentName type/member is not valid",
							array(
								'context' => $contextId,
							)
						);
						return false;
					}
					else if ( isset( $type['sequence'] ) )
					{
						echo "seq\n";
					}
				}

			}
		}

		return true;
	}

	/**
	 * Display a default label for this class which is the instance file name
	 * @return string
	 */
	function __toString()
	{
		return $this->schemaFilename
			? "$this->schemaFilename"
			: "<unknown>";
	}

	/* ------------------------------------------------
	 * Dimension validation functions
	 * ------------------------------------------------*/

	/**
	 * Get a list of the dimensions and associated member for a context
	 * @param array $context
	 * @return array
	 */
	public function getContextDimensions( $context )
	{
		if ( isset( $context['entity']['segment'] ) )
			return $this->getComponentDimensions( $context['entity']['segment'] );
		elseif ( isset( $context['segment'] ) )
			return $this->getComponentDimensions( $context['segment'] );
		elseif ( isset( $context['entity']['scenario'] ) )
			return $this->getComponentDimensions( $context['entity']['scenario'] );
		elseif ( isset( $context['scenario'] ) )
			return $this->getComponentDimensions( $context['scenario'] );
		else
			return array();
	}

	/**
	 * Get a list of the dimensions and associated member for a scenario or segment context component
	 * @param array $component
	 * @return array
	 */
	public function getComponentDimensions( $component )
	{
		$result = array();

		if ( isset( $component['explicitMember'] ) )
			$dimensions = $component['explicitMember'];
		else if ( isset( $component['typedMember'] ) )
			$dimensions = $component['typedMember'];
		else return $result;

		foreach (  $dimensions as $dimension )
		{
			$result[ $dimension['dimension'] ] = $dimension['member'];
		}

		return $result;
	}

	/**
	 * Tests whether a context is valid for the dimensions of a hypercube
	 * @param array|string $context
	 * @param array $hypercubeDimensions
	 * @param boolean $closed
	 * @return boolean
	 */
	public function isDimensionallyValid( $context, $hypercubeDimensions, $closed )
	{
		if ( is_string( $context ) )
		{
			$context = $this->getContext( $context );
		}

		// The context dimensions must be hypercube dimensions
		$contextDimensions = $this->getContextDimensions( $context );
		// $hypercubeDimensions = $details['details']['dimensions'];

		$invalidDimensions = array_diff_key( $contextDimensions, $hypercubeDimensions );

		// If there are any dimensions required by the context that are
		// not supported by the hypercube then the context is not valid
		if ( $invalidDimensions ) return false;

		// The dimension validity must take into account that any
		// of the hypercube dimension may have a default member
		$validDimensions = array_intersect_key( $contextDimensions, $hypercubeDimensions );

		if ( count( $validDimensions ) < count( $hypercubeDimensions ) )
		{
			// The order of the parameters is important.  The result should
			// have the missing members of the $reportInfoDimensions array.
			$missingDimensions = array_diff_key( $hypercubeDimensions, $validDimensions );

			// If there are no defaults an all missing dimensions then lose this context
			foreach ( $missingDimensions as $dimensionId => $dimension )
			{
				if ( ! isset( $dimension['default'] ) ) return false;
			}
		}
		elseif ( count( $contextDimensions ) > count( $hypercubeDimensions ) )
		{
			// If there are more context dimensions than report info dimensions then the dimension match is wrong
			return false;
		}

		return true;
	}

	/**
	 * Tests whether a context is valid for a hypercube
	 * @param array|string $context A context ref or an array for a specific context
	 * @param array $hypercube
	 * @param boolean $closed
	 * @return boolean
	 */
	public function isHypercubeDimensionallyValid( $context, $hypercube, $closed )
	{
		// Convert the array of dimensions into one indexed by qname as required by the isDimensionallyValid function
		$hypercubeDimensions = array_reduce( $hypercube['dimensions'], function( $carry, $dimension ) use( &$hypercubeDimensions )
		{
			$dimTaxonomy = $this->getInstanceTaxonomy()->getTaxonomyForXSD( $dimension['label'] );
			$element = $dimTaxonomy->getElementById( $dimension['label'] );

			$carry[ "{$dimTaxonomy->getPrefix()}:{$element['name']}" ] = $dimension;
			return $carry;
		}, array() );

		return $this->isDimensionallyValid( $context, $hypercubeDimensions, $closed );
	}

	/**
	 * Returns true if ANY hypercube in a DRS is valid for the context
	 * @param array $fact An array for a specific fact
	 * @param array $drs A DRS array returned by a call to getPrimaryItemDRS
	 * @return boolean
	 */
	public function isDRSValidForFact( $fact,  $drs)
	{
		foreach ( $drs as $hypercube => $roles )
		{
			foreach ( $roles as $role => $hypercubeItems )
			{
				if ( ! isset( $hypercubes[ $hypercube ][ $role ] ) )
				{
					$closed = isset( $hypercubeItems['parents'][ $fact['label'] ]['closed'] ) && $hypercubeItems['parents'][ $fact['label'] ]['closed'];

					if ( ! $this->isHypercubeDimensionallyValid( $fact['contextRef'], $hypercubeItems, $closed ) ) continue;

					return true;
				}
			}
		}

		return false;
	}
}

/**
 * Initialized with a list of contexts it allows the caller to filter the list
 * @author Administrator
 */
class ContextsFilter
{
	/**
	 * An array of contexts
	 * @var Array
	 */
	private $contexts = null;
	/**
	 * The XBRL_Instance containing the contexts to filter
	 * @var XBRL_Instance $instance
	 */
	private $instance = null;

	/**
	 * Constructor
	 * @param XBRL_Instance $instance (by reference) The XBRL_Instance containing the contexts to filter
	 * @param array $contexts The context to add
	 * @return ContextsFilter
	 */
	function __construct( &$instance, $contexts )
	{
		$this->contexts = $contexts;
		$this->instance =& $instance;
	}

	/**
	 * Clone the current instance and return a new one
	 */
	public function clone()
	{
		return new ContextsFilter( $this->instance, $this->contexts );
	}

	/**
	 * Adds the contexts from $contexts to the existing lisy
	 * @param array $contexts MUST be an array
	 */
	public function add( $contexts )
	{
		if ( ! is_array( $contexts ) )
		{
			throw new Exception('The contexts parameter MUST be an array');
		}

		$this->contexts = array_merge( $this->contexts, $contexts );
	}

	/**
	 * Get the duration across all contexts
	 */
	public function getDuration()
	{
		$minDate = null;
		$maxDate = null;

		foreach ( $this->contexts as $contextRef => $context )
		{
			$maxDate = $maxDate
				? ( $context['period']['endDate'] > $maxDate ? $context['period']['endDate'] : $maxDate )
				: $context['period']['endDate'];
			$minDate = $minDate
				? ( $context['period']['startDate'] < $minDate ? $context['period']['startDate'] : $minDate )
				: $context['period']['startDate'];
		}
		// $start = $date = DateTime::createFromFormat( "!Y-m-d", $minDate );
		// $end = $date = DateTime::createFromFormat( "!Y-m-d", $maxDate );
		// $diff = $start->diff( $end );

		return array(
			'startDate' => $minDate,
			'endDate' => $maxDate,
		//	'duration' => $diff->days
		);
	}

	/**
	 * Generates an array containing an element for each unique period range.
	 * Each element is an array containing a text description and a list of
	 * relevant context references.
	 * The context references in each element are those duration contexts with
	 * the same start and end date plus those instant contexts that have the
	 * same end date.  Duration contexts that are contained within the range
	 * of another context are ignored.  Instant contexts that have a different
	 * end date but which is within the range of another context are given their
	 * own element.
	 */
	public function getDiscreteDateRanges()
	{
		// An array of unique ranges
		$ranges = array();

		// Start with the contexts with the widest date range.  Then others will fit inside
		foreach( $this->sortByDuration( true )->getContexts() as $contextRef => $context )
		{
			$endDate = $context['period']['endDate'];

			// If the end of the range has already been added, consider adding the context to it
			if ( isset( $ranges[ $endDate ] ) )
			{
				// If the context is an instant then it can be added.
				// If its a duration the start date must be the same as the start date of the range
				if ( ! $context['period']['is_instant'] )
				{
					$duration = ($ranges[ $endDate ])->getDuration();
					// If the start date is not the same then do not add it
					if ( $context['period']['startDate'] != $duration['startDate'] ) continue;
				}
				$ranges[ $endDate ]->add( array( $contextRef => $context ) );
			}
			else
			{
				// If there is no range with the same end date then look to see if this
				// is a context that fits within an existing range
				foreach ( $ranges as $date => /** @var ContextsFilter $range */ $range )
				{
					// Does the range contain the end date (remember the contexts have
					// been sorted in duration order so a subsequent context cannot be
					// wider than an existing range).
					$contextsForDate = $range->ContextsContainDate( $endDate );
					// If there is no existing range, drop through and add another
					if ( $contextsForDate->count() )
					{
						// There should only be one context found but its an array
						foreach ( $contextsForDate->getContexts() as $contextForDate )
						{
							// If the context has the same end date as the range then drop through to add it
							if ( $contextForDate['period']['endDate'] != $endDate )
							{
								if ( $context['period']['is_instant'] )
								{
									// Drop out of the outer for-loop to add a new range
									break 2;
								}

								// Go to the next context as there will no other range with a containing range
								continue 3;
							}

							// add the context to an existing range then go to the next context
							$range->add( array( $contextRef => $context ) );
							continue 3;
						}

						break;
					}
					unset( $contextForDate );
					unset( $contextsForDate );
				}
				unset( $range );

				$ranges[ $endDate ] = new ContextsFilter( $instance, array( $contextRef => $context ) );
			}
		}

		return array_map( function( /** @var ContextsFilter $range */ $range )
		{
			return array(
				'contextRefs' => array_keys( $range->getContexts() ),
				'text' => $range->getPeriodLabel()
			);
		}, $ranges );
	}

	/**
	 * Return the count of the contexts
	 * @return int
	 */
	public function count()
	{
		return count( $this->contexts );
	}

	/**
	 * Return the contexts of the instance
	 * @return array
	 */
	public function &getContexts()
	{
		return $this->contexts;
	}

	/**
	 * Get a specific context by reference
	 * @param string $ref
	 * @return boolean|Array
	 */
	public function getContext( $ref )
	{
		return isset( $this->contexts[ $ref ] )
			? $this->contexts[ $ref ]
			: false;
	}

	/**
	 * Get a set of contexts by reference
	 * @param string $refs
	 * @return ContextsFilter
	 */
	public function getContextsByRef( $refs )
	{
		$contexts = array();

		if ( is_array( $refs ) )
		foreach ( $refs as $ref )
		{
			if ( ! isset( $this->contexts[ $ref ] ) ) continue;
			$contexts[ $ref ] = $this->contexts[ $ref ];
		}

		return new ContextsFilter( $this->instance, $contexts );
	}

	/**
	 * Returns all contexts that include $date in their date range
	 * @param string|DateTime $date
	 * @return ContextsFilter
	 */
	public function ContextsContainDate( $date )
	{
		if ( is_string( $date ) )
			$date = DateTime::createFromFormat( "!Y-m-d", $date );

		if ( ! is_a( $date, 'DateTime' ) )
		{
			throw new Exception( 'Date parameter passes to ContextsFilter::ContextsContainDate is not a string or DateTime object' );
		}

		$filtered = array_filter( $this->contexts, function ( $context ) use( $date ) {
			// The context may be invalid in which case exclude
			if ( ! isset( $context['period'] ) || ! isset( $context['period']['startDate'] ) || ! isset( $context['period']['endDate'] ) ) return false;

			$startDate = DateTime::createFromFormat( "!Y-m-d", $context['period']['startDate'] );
			if ( ! $context['period']['is_instant'] && $startDate >= $date ) return false;

			$endDate   = DateTime::createFromFormat( "!Y-m-d", $context['period']['endDate'] );
			return $context['period']['is_instant'] ? $endDate == $date : $endDate >= $date;
		} );

		return new ContextsFilter( $this->instance, $filtered );
	}

	/**
	 * Return a list of the context with start or end date in $year
	 * @param int|string $year
	 * @param bool $matchEndDate True if the year should match only the end date.  Otherwise the start date is compared as well.
	 * @return ContextsFilter
	 */
	public function ContextsForYear( $year, $matchEndDate = true )
	{
		$filtered = array_filter( $this->contexts, function ( $context ) use( $year, $matchEndDate ) {

			// The context may be invalid in which case exclude
			if ( ! isset( $context['period'] ) ) return false;

			// All contexts have start so get the year
			$parts = explode( "-", $context['period'][ $matchEndDate ? 'endDate' : 'startDate' ] );

			// If the year matches then it's good
			// if ( $parts[0] == $year ) return true;

			// // If the context is an instant then start == end and since the start failed the end will also fail
			// if ( $context['period']['is_instant'] ) return false;
            //
			// // Its a duration so check the end
			// $parts = explode( "-", $context['period']['C'] );
			return $parts[0] == $year;

		}  );

		return new ContextsFilter( $this->instance, $filtered );
	}

	/**
	 * Return context with a specified number of months duration
	 * @param int $months
	 */
	public function ContextWithDuration( $months )
	{
		$durationContexts = $this->DurationContexts();
		$oneDay = new DateInterval("P1D");
		return new ContextsFilter( $instance, array_filter( $durationContexts->getContexts(), function( $context ) use( $months, $oneDay )
		{
			$interval = date_diff(
				new DateTime( $context["period"]["startDate"] ),
				(new DateTime( $context["period"]["endDate"] ))->add( $oneDay ) );

			return $months == ( $interval->m + ( $interval->y * 12 ) );
		} ) );
	}

	/**
	 * Returns a list of all the instant contexts
	 * @return ContextsFilter
	 */
	public function InstantContexts()
	{
		return $this->InstantTypeFilter( true );
	}

	/**
	 * Returns a list of all the duration contexts
	 * @return ContextsFilter
	 */
	public function DurationContexts()
	{
		return $this->InstantTypeFilter( false );
	}

	/**
	 * Returns the context for a specfic entity
	 * @param string $entity The value identifying the entity
	 * @param string $scheme Optionally the scheme associated with the value
	 * @return ContextsFilter
	 */
	public function EntityContexts( $entity, $scheme = null )
	{
		$filtered = array_filter( $this->contexts, function ( $context ) use( $entity, $scheme ) {

			// The context may be invalid in which case exclude
			if ( ! isset( $context['entity']['identifier']['value'] ) ) return false;

			if ( $context['entity']['identifier']['value'] == $entity )
			{
				// If supplied also check the scheme
				return $scheme != null && isset( $context['entity']['identifier']['scheme'] )
				? ( strtolower( $scheme ) == strtolower( $context['entity']['identifier']['scheme'] ) )
				: true;
			}

			return false;
		} );

		return new ContextsFilter( $this->instance, $filtered );
	}

	/**
	 * Returns contexts filtered by any combination of dimension/namespace and member/namespace.
	 * If no parameters are provided then all contexts with segment information are returned.
	 * By default, $dimension and $member will be compared with the element name.
	 * Prefix the $dimension and $member parameters with a # character to filter by the id.
	 * @param string $dimension
	 * @param string $dimensionNamespace
	 * @param string $member
	 * @param string $memberNamespace
	 * @return ContextsFilter
	 */
	public function SegmentContexts( $dimension = null, $dimensionNamespace = null, $member = null, $memberNamespace = null )
	{
		// In PHP 7.0 $this is passed to closure functions automatically but not in earlier versions
		$contexts = &$this->contexts;
		$instance = &$this->instance;

		$filtered = array_filter( array_keys( $this->contexts ), function ( $context ) use( $dimension, $dimensionNamespace, $member, $memberNamespace, &$contexts, &$instance ) {

			// The context may be invalid in which case exclude
			if ( ! isset( $this->contexts[ $context ] ) ) return false;
			$contextSegment = $this->instance->getContextSegment( $this->contexts[ $context ] );
			if ( is_null( $contextSegment ) ) return false;

			if ( ! ( isset( $contextSegment['explicitMember'] ) && count( $contextSegment['explicitMember'] ) ) &&
				 ! ( isset( $contextSegment['typedMember'] ) && count( $contextSegment['typedMember'] ) ) )
				return false;

			// If no components are requested return all
			if ( $dimension == null && $member == null && $dimensionNamespace == null && $memberNamespace == null )
				return true;

			// Each context may have more than one segment.  If the filter matches any one segment the context will be included.
			$context_segments = $instance->getElementsForContext( $context, true );

			foreach ( $context_segments as $key => $segment )
			{
				if ( $dimension !== null )
				{
					$dimFilter = strtolower( $dimension[0] == '#' ? strtolower( substr( $dimension, 1 ) ) : $dimension );
					$dimName = strtolower( $segment['dimension']['element'][ $dimension[0] == '#' ? 'id' : 'name' ] );

					$dimMatch = $dimFilter == $dimName;
					if ( $dimFilter != $dimName ) continue;
				}

				if ( $dimensionNamespace !== null )
				{
					if ( strtolower( $segment['dimension']['namespace'] ) != strtolower( $dimensionNamespace ) )
						continue;
				}

				if ( $member !== null )
				{
					$memFilter = strtolower( $member[0] == '#' ? strtolower( substr( $member, 1 ) ) : $member );
					$memName = strtolower( $segment['member']['element'][ $member[0] == '#' ? 'id' : 'name' ] );

					$memMatch = $memFilter == $memName;
					if ( $memFilter != $memName ) continue;
				}

				if ( $memberNamespace !== null )
				{
					if ( strtolower( $segment['member']['namespace'] ) != strtolower( $memberNamespace ) )
						continue;
				}

				return true;
			}

			return false;
		} );

		return new ContextsFilter( $this->instance, array_intersect_key( $this->contexts, array_flip( $filtered ) ) );
	}

	/**
	 * Return a list of the contexts without a segment
	 * @return ContextsFilter
	 */
	public function NoSegmentContexts()
	{
		$filtered = array_filter( $this->contexts, function( $context ) {
			// The context may be invalid in which case exclude
			// return ( ! isset( $context['entity']['segment']['explicitMember'] ) || count( $context['entity']['segment']['explicitMember'] ) == 0 ) &&
			// 	   ( ! isset( $context['entity']['scenario']['explicitMember'] ) || count( $context['entity']['scenario']['explicitMember'] ) == 0 ) &&
			// 	   ( ! isset( $context['segment']['explicitMember'] ) || count( $context['segment']['explicitMember'] ) == 0 ) &&
			// 	   ( ! isset( $context['scenario']['explicitMember'] ) || count( $context['scenario']['explicitMember'] ) == 0 ) &&
			// 	   ( ! isset( $context['entity']['segment']['typedMember'] ) || count( $context['entity']['segment']['typedMember'] ) == 0 ) &&
			// 	   ( ! isset( $context['entity']['scenario']['typedMember'] ) || count( $context['entity']['scenario']['typedMember'] ) == 0 ) &&
			// 	   ( ! isset( $context['segment']['typedMember'] ) || count( $context['segment']['typedMember'] ) == 0 ) &&
			// 	   ( ! isset( $context['scenario']['typedMember'] ) || count( $context['scenario']['typedMember'] ) == 0 );

			$segment = $this->instance->getContextSegment( $context );

			return is_null( $segment ) || ! ( isset( $segment['explicitMember'] ) || isset( $segment['typedMember'] ) );
		} );

		return new ContextsFilter( $this->instance, $filtered );
	}

	/**
	 * Return an array of all namespaces used by the contexts
	 * @return array of strings
	 */
	public function AllNamespaces()
	{
		// In PHP 7.0 $this is passed to closure functions automatically but not in earlier versions
		$contexts = &$this->contexts;
		$instance = &$this->instance;

		$result = array_reduce( array_keys( $this->contexts ), function( $carry, $context ) use( &$contexts, &$instance ) {

			// The context may be invalid in which case exclude
			if ( ! isset( $contexts[ $context ]['entity']['segment']['explicitMember'] ) || count( $contexts[ $context ]['entity']['segment']['explicitMember'] ) == 0 )
				return $carry;

			// Each context may have more than one segment.  If the filter matches any one segment the context will be included.
			$context_segments = $instance->getElementsForContext( $context, true );

			foreach ( $context_segments as $key => $segment )
			{
				if ( ! isset( $segment['dimension']['namespace'] ) || isset( $carry[ $segment['dimension']['namespace'] ] ) ) continue;
				$carry[ $segment['dimension']['namespace'] ] = 1;

				if ( ! isset( $segment['member']['namespace'] ) || isset( $carry[ $segment['dimension']['namespace'] ] ) ) continue;
				$carry[ $segment['member']['namespace'] ] = 1;
			}

			return $carry;

		}, array() );

			return array_keys( $result );
	}

	/**
	 * Return an array of all entities used by the contexts
	 * @return array of strings
	 */
	public function AllEntities()
	{
		// In PHP 7.0 $this is passed to closure functions automatically but not in earlier versions
		$contexts = &$this->contexts;

		$result = array_reduce( array_keys( $this->contexts ), function( $carry, $context ) use( &$contexts ) {

			if ( ! isset( $contexts[ $context ]['entity']['identifier']['value'] ) ) return $carry;

			/**
			 * @var \lyquidity\xml\QName $qname
			 */
			$qname = new QName(  null, $contexts[ $context ]['entity']['identifier']['scheme'], $contexts[ $context ]['entity']['identifier']['value'] );

			if ( isset( $carry[ $qname->clarkNotation() ] ) ) return $carry;

			$carry[ $qname->clarkNotation() ] = 1;
			return $carry;

		}, array() );

		return array_keys( $result );
	}

	/**
	 * Return an array of all explicit dimensions used by the contexts
	 * @return array of strings
	 */
	public function AllExplicitDimensions()
	{
		// In PHP 7.0 $this is passed to closure functions automatically but not in earlier versions
		$contexts = &$this->contexts;
		$namespaces = $this->instance->getInstanceNamespaces();

		$result = array_reduce( array_keys( $this->contexts ), function( $carry, $context ) use( &$contexts, &$namespaces )
		{
			$ctx = $contexts[ $context ];
			$explicitMembers = isset( $ctx['entity']['segment']['explicitMember'] )
				? $ctx['entity']['segment']['explicitMember']
				: ( isset( $ctx['segment']['explicitMember'] )
					  ? $ctx['segment']['explicitMember']
					  : ( isset( $ctx['entity']['scenario']['explicitMember'] )
							? $ctx['entity']['scenario']['explicitMember']
							: ( isset( $ctx['scenario']['explicitMember'] )
					  			? $ctx['scenario']['explicitMember']
								: null
							  )
					  	)
				  );

			if ( ! $explicitMembers ) return $carry;

			foreach ( $explicitMembers as $explicitMember )
			{
				$qname = qname( $explicitMember['dimension'], $namespaces );
				$carry[ $qname->clarkNotation() ][] = $context;
			}

			return $carry;

		}, array() );

		return $result;
	}

	/**
	 * Return an array of all typed dimensions used by the contexts
	 * @return array of strings
	 */
	public function AllTypedDimensions()
	{
		// In PHP 7.0 $this is passed to closure functions automatically but not in earlier versions
		$contexts = &$this->contexts;
		$namespaces = $this->instance->getInstanceNamespaces();

		$result = array_reduce( array_keys( $this->contexts ), function( $carry, $context ) use( &$contexts, &$namespaces )
		{
			$ctx = $contexts[ $context ];
			$typedMembers = isset( $ctx['entity']['segment']['typedMember'] )
				? $ctx['entity']['segment']['typedMember']
				: ( isset( $ctx['segment']['typedMember'] )
					  ? $ctx['segment']['typedMember']
					  : ( isset( $ctx['entity']['scenario']['typedMember'] )
							? $ctx['entity']['scenario']['typedMember']
							: ( isset( $ctx['scenario']['typedMember'] )
					  			? $ctx['scenario']['typedMember']
								: null
							  )
					  	)
				  );

			if ( ! $typedMembers ) return $carry;

			foreach ( $typedMembers as $typedMember )
			{
				$qname = qname( $typedMember['dimension'], $namespaces );
				$carry[ $qname->clarkNotation() ][] = $context;
			}

			return $carry;

		}, array() );

		return $result;
	}

	/**
	 * Return an array of all years used by the contexts
	 * @return array of strings
	 */
	public function AllYears()
	{
		$result = array_reduce( $this->contexts, function( $carry, $context ) {

			if ( ! isset( $context['period'] ) ) return $carry;
			// Get the year of the start date
			$parts = explode( "-", $context['period']['startDate'] );
			$carry[ $parts[0] ] = isset( $carry[ $parts[0] ] ) ? $carry[ $parts[0] ] + 1 : 1;
			$parts = explode( "-", $context['period']['endDate'] );
			$carry[ $parts[0] ] = isset( $carry[ $parts[0] ] ) ? $carry[ $parts[0] ] + 1 : 1;
			return $carry;

		}, array() );

		ksort( $result );
		return array_keys( $result );
	}

	/**
	 * Return the available segment element from a context
	 * @param array|string $context
	 * @return array|NULL
	 */
	private function getSegment( $context )
	{
		if ( is_string( $context ) )
		{
			if ( ! isset( $this->contexts[ $context ] ) ) return null;
			$context = $this->contexts[ $context ];
		}

		if ( isset( $context['segment'] ) ) return $context['segment'];
		if ( isset( $context['entity']['segment'] ) ) return $context['entity']['segment'];

		return null;
	}

	/**
	 * Return the available scenario element from a context
	 * @param array|string $context
	 * @return array|NULL
	 */
	private function getScenario( $context )
	{
		if ( is_string( $context ) )
		{
			if ( ! isset( $this->contexts[ $context ] ) ) return null;
			$context = $this->contexts[ $context ];
		}

		if ( isset( $context['scenario'] ) ) return $context['scenario'];
		if ( isset( $context['entity']['scenario'] ) ) return $context['entity']['scenario'];

		return null;
	}

	/**
	 * Return a period label for the context collection
	 * @return string
	 */
	public function getPeriodLabel()
	{
		$contexts = $this->contexts; // Protect the contexts
		try
		{
			$durationContexts = $this->DurationContexts();

			// If there are only instant context and all the contexts have the same date...
			if ( ! $durationContexts->count() && count( $this->AllYears() ) == 1 )
			{
				$context = reset( $this->contexts );
				return $context['period']['endDate'];
			}
			else
			{
				$durationContexts->sortByStartDate();
				$startContext = reset( $durationContexts->getContexts() );
				$durationContexts->sortByEndDate();
				$endContext = end( $durationContexts->getContexts() );
				return "{$startContext['period']['startDate']} - {$endContext['period']['endDate']}";
			}
		}
		finally
		{
			$this->contexts = $contexts; // Restore
		}
	}

	/**
	 * Return a context filter instance with only those contexts that have the same segment(s) as those in $context
	 * @param array $context
	 * @return ContextsFilter
	 */
	public function SameContextSegment( $context )
	{
		$segment = $this->getSegment( $context );
		if ( ! $segment )
		{
			$segment = $this->getScenario( $context );
		}

		if ( ! $segment )
		{
			return $this->NoSegmentContexts();
		}

		return $this->SameSegment( $segment );
	}

	/**
	 * Return a context filter instance with only those contexts that have the same segment(s) as those in $segment
	 * @param array $context_segment segments of a context
	 * @return ContextsFilter
	 */
	public function SameSegment( $context_segment )
	{
		$contexts = array();
		if ( $this->contexts )
		{
			foreach ( $this->contexts as $contextRef => $context)
			{
				$segment = $this->getSegment( $context );
				if ( ! $segment ) $segment = $this->getScenario( $context );
				if ( ! $segment ) continue;
				if ( ! XBRL_Equality::segment_equal( $segment, $context_segment) ) continue;
				$contexts[ $contextRef] = $context;
			}
		}

		return new ContextsFilter( $this->instance, $contexts );
	}

	/**
	 * Remove one or more contexts.  The instance will be changed and will be removed.
	 * @param string|string[]|ContextFilter $contexts
	 * @return ContextsFilter
	 */
	public function remove( $contexts )
	{
		if ( is_string( $contexts ) )
		{
			unset( $this->contexts[ $contexts ] );
		}
		else if ( $contexts instanceof ContextsFilter )
		{
			$contexts = array_keys( $contexts->getContexts() );
		}

		if ( is_array( $contexts ) )
		{
			foreach ( $contexts as $context )
			{
				unset( $this->contexts[ $context ] );
			}
		}

		return $this;
	}

	/**
	 * Sorts the current context collection by the period start date
	 * @return ContextsFilter
	 */
	public function sortByStartDate()
	{
		uksort( $this->contexts, function( $a, $b ) use ( &$contexts )
		{
			return -1 * strcmp( $this->contexts[ $a] ['period']['startDate'], $this->contexts[ $b]['period']['startDate'] );
		} );

		return $this;
	}

	/**
	 * Sorts the current context collection by the period start date
	 * @return ContextsFilter
	 */
	public function sortByEndDate()
	{
		uksort( $this->contexts, function( $a, $b ) use ( &$contexts )
		{
			return -1 * strcmp( $this->contexts[ $a] ['period']['endDate'], $this->contexts[ $b]['period']['endDate'] );
		} );

		return $this;
	}

	/**
	 *
	 */
	public function sortByDuration( $invert = false )
	{
		uksort( $this->contexts, function( $a, $b ) use( $invert )
		{
			$date = new DateTime( $this->contexts[ $a ]['period']['startDate'] );
			$diff = $date->diff( new DateTime($this->contexts[ $a ]['period']['endDate'] ) );
			$daysA = $diff->days;

			$date = new DateTime( $this->contexts[ $b ]['period']['startDate'] );
			$diff = $date->diff( new DateTime($this->contexts[ $b ]['period']['endDate'] ) );
			$daysB = $diff->days;

			return ( $daysA - $daysB ) * ( $invert ? -1 : 1 );
		} );

		return $this;
	}

	/**
	 * Return a list of context keys
	 * @return array[string]
	 */
	public function Keys()
	{
		return array_keys( $this->contexts );
	}

	/**
	 * Returns a list of contexts that match the instance state given by $instant
	 * @param XBRL_Instance $instant
	 * @return ContextsFilter
	 */
	private function InstantTypeFilter( $instant )
	{
		$filtered = array_filter( $this->contexts, function ( $context ) use( $instant ) {
			// The context may be invalid in which case exclude
			if ( ! isset( $context['period'] ) ) return false;

			return $context['period']['is_instant']
			? $instant
			: ! $instant;
		} );

		return new ContextsFilter( $this->instance, $filtered );
	}
}

/**
 * Initialized with a list of elements it allows the caller to filter the list
 * @author Administrator
 */
class InstanceElementsFilter
{
	/**
	 * An array of contexts
	 * @var Array
	 */
	private $elements = null;
	/**
	 * The XBRL_Instance containing the contexts to filter
	 * @var XBRL_Instance $instance
	 */
	private $instance = null;

	/**
	 * Constructor
	 * @param XBRL_Instance $instance The XBRL_Instance containing the elements to filter
	 * @param array $elements The elements to add
	 */
	function __construct( &$instance, $elements )
	{
		$this->elements = $elements;
		$this->instance =& $instance;
	}

	/**
	 * Return the count of the elements
	 * @return int
	 */
	public function count()
	{
		return count( $this->elements );
	}

	/**
	 * Return the elements of the instance
	 * @return array
	 */
	public function &getElements()
	{
		return $this->elements;
	}

	/**
	 * Return a list of elements filtered by one or more names
	 * @param array|string $names The parameter can be the name of an element, a comma delimited list of names or an array of names
	 * @throws Exception
	 * @return InstanceElementsFilter
	 */
	public function ElementsByName( $names )
	{
		if ( is_string( $names ) )
			$names = array_map( 'trim', explode( ",", $names ) );

		if ( ! is_array( $names ) )
			throw new Exception( 'InstanceElementsFilter::ElementsByName $names must be a string or array' );

		$names = array_flip( $names );
		return new InstanceElementsFilter( $this->instance, array_intersect_key( $this->elements, $names ) );
	}

	/**
	 * Return a list of elements based on a set of contexts
	 * @param array|string $contexts The parameter can be the name of an context, a comma delimited list of contexts or an array of contexts
	 * @throws Exception
	 * @return InstanceElementsFilter
	 */
	public function ElementsByContexts( $contexts )
	{
		if ( is_string( $contexts ) )
			$contexts = array_map( 'trim', explode( ",", $contexts ) );

		if ( is_a( $contexts, 'ContextsFilter' ) )
			$contexts = $contexts->Keys();

		if ( ! is_array( $contexts ) )
			throw new Exception( 'InstanceElementsFilter::ElementsByName $contexts must be a string or array' );

		$contexts = array_flip( array_map( 'strtolower', $contexts ) );

		// In PHP 7.0 $this is passed to closure functions automatically but not in earlier versions
		$elements = &$this->elements;

		$filtered = array_reduce( array_keys( $this->elements ), function( $carry, $elementName ) use( $contexts, &$elements ) {

			$contextElements = array_filter( $elements[ $elementName ], function( $element ) use( &$contexts, &$elements ) {

				if ( isset( $element['contextRef'] ) )
				{
					return isset( $contexts[ strtolower( $element['contextRef'] ) ] );
				}

				// Could be a tuple in which case the tuple members should be checked
				return array_reduce( $element['tuple_elements'], function( $carry, $tuple_element ) use( $contexts ) {

					if ( $carry ) return true; // Once one valid member is found there's no need to look for any others
					return array_reduce( $tuple_element, function( $carry, $entry ) use( $contexts ) {

						if ( $carry ) return true; // Once one valid member is found there's no need to look for any others
						return isset( $contexts[ strtolower( $entry['contextRef'] ) ] );

					} );

				}, false );

			} );

			if ( ! $contextElements ) return $carry;

			$carry[ $elementName ] = $contextElements;

			return $carry;

		}, array() );

		return new InstanceElementsFilter( $this->instance, $filtered );
	}

	/**
	 * Return a list of elements based on a set of namespaces
	 * @param array|string $namespaces The parameter can be the name of an namespace, a comma delimited list of namespaces or an array of namespaces
	 * @throws Exception
	 * @return InstanceElementsFilter
	 */
	public function ElementsByNamespaces( $namespaces )
	{
		if ( is_string( $namespaces ) )
			$namespaces = array_map( 'trim', explode( ",", $namespaces ) );

		if ( ! is_array( $namespaces ) )
			throw new Exception( 'InstanceElementsFilter::ElementsByName $namespaces must be a string or array' );

		$namespaces = array_flip( array_map( 'strtolower', $namespaces ) );

		$instance_namespaces = $this->instance->getInstanceNamespaces();

		// In PHP 7.0 $this is passed to closure functions automatically but not in earlier versions
		$elements = &$this->elements;

		$filtered = array_reduce( array_keys( $this->elements ), function( $carry, $elementName ) use( &$elements, $instance_namespaces, $namespaces ) {

			$namespaceElements = array_filter( $elements[ $elementName ], function( $entry ) use( &$elements, $instance_namespaces, $namespaces ) {
				$namespace = isset( $instance_namespaces[ $entry['namespace'] ] ) ? $instance_namespaces[ $entry['namespace'] ] : $entry['namespace'];
				return isset( $namespaces[ strtolower( $namespace ) ] );
			} );

			if ( ! $namespaceElements ) return $carry;

			$carry[ $elementName ] = $namespaceElements;

			return $carry;

		}, array() );

		return new InstanceElementsFilter( $this->instance, $filtered );
	}

	/**
	 * Return a list of elements based on a set of item types
	 * @param array|string $types The parameter can be the name of a item type, a comma delimited list of item types or an array of item types
	 * @throws Exception
	 * @return InstanceElementsFilter
	 */
	public function ElementsByItemType( $types )
	{
		if ( is_string( $types ) )
			$types = array_map( 'trim', explode( ",", $types ) );

		if ( ! is_array( $types ) )
			throw new Exception( 'InstanceElementsFilter::ElementsByName $types must be a string or array' );

		$types = array_flip( array_map( 'strtolower', $types ) );

		// In PHP 7.0 $this is passed to closure functions automatically but not in earlier versions
		$elements = &$this->elements;

		$filtered = array_reduce( array_keys( $this->elements ), function( $carry, $elementName ) use( &$elements, $types  ) {

			$namespaceElements = array_filter( $elements[ $elementName ], function( $entry ) use( $types ) {
				$type = $entry['taxonomy_element']['type'];
				return isset( $types[ strtolower( $type ) ] );
			} );

			if ( ! $namespaceElements ) return $carry;

			$carry[ $elementName ] = $namespaceElements;

			return $carry;

		}, array() );

		return new InstanceElementsFilter( $this->instance, $filtered );
	}

	/**
	 * Return a list of the namespaces used by the elements
	 * @return array
	 */
	public function getNamespaces()
	{
		$namespaces = $this->instance->getInstanceNamespaces();

		$usedNamespaces = array_reduce( $this->elements, function( $carry, $element ) use( $namespaces ) {

			foreach ( $element as $key => $entry )
			{
				$namespace = isset( $namespaces[ $entry['namespace'] ] ) ? $namespaces[ $entry['namespace'] ] : $entry['namespace'];
				if ( ! isset( $carry[ $namespace ] ) )
					$carry[ $namespace ] = 1;
			}

			return $carry;
		}, array() );

		return array_keys( $usedNamespaces );
	}

	/**
	 * Return a list of the substitution groups used by the elements
	 * @return array
	 */
	public function getSubstitutionGroups()
	{
		$usedGroups = array_reduce( $this->elements, function( $carry, $element ) {

			foreach ( $element as $key => $entry )
			{
				$group = $entry['taxonomy_element']['substitutionGroup'];
				if ( ! isset( $carry[ $group ] ) )
					$carry[ $group ] = 1;
			}

			return $carry;
		}, array() );

		return array_keys( $usedGroups );
	}

	/**
	 * Return a list of the types used by the elements
	 * @return array
	 */
	public function getItemTypes()
	{
		$usedTypes = array_reduce( $this->elements, function( $carry, $element ) {
			foreach ( $element as $key => $entry )
			{
				$type = $entry['taxonomy_element']['type'];
				if ( empty( $type ) ) continue;

				if ( ! isset( $carry[ $type ] ) )
				{
					$carry[ $type ] = 1;
				}
			}

			return $carry;
		}, array() );

		return array_keys( $usedTypes );
	}

	/**
	 * Reduce elements so they only have unique entries
	 * @return InstanceElementsFilter
	 */
	public function UniqueElementEntries()
	{
		$result = array(); // Build a new array

		foreach ( $this->elements as $name => $entries )
		{
			if ( ! isset( $result[ $name ] ) ) $result[ $name ] = array();

			foreach ( $entries as $key => $entry )
			{
				if ( isset( $entry['tuple_elements'] ) )
				{
					// Just take it
					$result[ $name ][] = $entry;
					continue;
				}

				if ( $this->entry_is_unique( $result[ $name ], $entry ) )
					$result[ $name ][] = $entry;
			}
		}

		return new InstanceElementsFilter( $this, $result );
	}

	/**
	 * Compare $entry with $existingElements to determine if the associated contextRefs are equivalent.
	 * @param array $existingElements An array of instance entries to compare to $entry for context and value equivalence
	 * @param array $entry An entry node representing a record in the instance document
	 * @return Returns true if $entry is unique.
	 */
	private function entry_is_unique( $existingElements, $entry )
	{
		if ( count( $existingElements ) == 0 || isset( $entry['tuple_elements'] ) ) return true;

		foreach ( $existingElements as $key => $existingElement )
		{
			if ( $existingElement['contextRef'] !== $entry['contextRef'] ) continue;
			if ( $existingElement['value'] !== $entry['value'] ) continue;
			// $this->log()->err( "The $entry with contextRef {$entry['contextRef']} and value {$entry['value']} is not unique" );
			return false;
		}
		return true;
	}

	/**
	 * Get the log instance
	 * @return XBRL_Log
	 */
	private function log()
	{
		return XBRL_Log::getInstance();
	}
}

?>