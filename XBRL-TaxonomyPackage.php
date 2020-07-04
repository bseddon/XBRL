<?php

/**
 * XBRL Taxonomy Package handler
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

use \lyquidity\xml;
use function lyquidity\xml\isXml;

/**
 * Implements functions to read a taxonomy package
 */
class XBRL_TaxonomyPackage extends XBRL_Package
{
	/**
	 * Notes about using this package instance
	 * @var string
	 */
	const notes = <<<EOT
Uses the path of the entry point in the taxonomyPackage.xml file to determine the path to use in the cache.
This should be the same as the value used in the schemaRef element in the instance document.
The 'identifier' value in the taxonomyPackage.xml file appears to be the namespace of the taxonomy.\n\n
EOT;

	/**
	 * Name of the meta file root eleent
	 * @var string
	 */
	const rootElementNme = "taxonomyPackage";

	/**
	 * Name of the catalog file root eleent
	 * @var string
	 */
	const catalogRootElementNme = "catalog";

	/**
	 * Name of the meta file
	 * @var string
	 */
	const taxonomyPackageFilename = "taxonomyPackage.xml";

	/**
	 * Name of the catalog file
	 * @var string
	 */
	const catalogFilename = "catalog.xml";

	/**
	 * Name of the meta folder
	 * @var string
	 */
	const metaFolderName = "META-INF";

	/**
	 * A reference to the catalog in the package
	 * @var SimpleXMLElement
	 */
	private $catalog;

	/**
	 * Required. Provides a URI that uniquely identifies the package.
	 * @var string
	 */
	public $identifier;

	/**
	 * Optional (*) Provides a human-readable name for the taxonomy. The <tp:name> element is a Multi-lingual Element.
	 * @var array[string] indexed by country code or empty if non provided
	 */
	public $name = array();

	/**
	 * Optional (*) Provides a human-readable descripton for the taxonomy. The <tp:description> element is a Multi-lingual Element.
	 * @var array[string] indexed by country code or empty if non provided
	 */
	public $description = array();

	/**
	 * Optional (1) Provides a version identifier for the taxonomy as a whole.
	 * @var string
	 */
	public $version;

	/**
	 * Optional (1) Provides a link to licensing terms for the taxonomy. The element has a @href attribute,
	 * 				which contains a link to the license documentation, and a @name attribute which should
	 * 				be used to provide a human-readable name for the license
	 * @var string
	 */
	public $license;

	/**
	 * Optional (*)
	 * @var array[string] indexed by country code or empty if non provided
	 * Describes the entity responsible for publishing the taxonomy. This is a Multi-lingual Element.
	 */
	public $publisher = array();

	/**
	 * Optional (1) Provides a URL for the entity publishing the taxonomy. This element SHOULD be used to provide
	 * 				the primary website of the publishing entity. The URL used SHOULD be the same as that used in
	 * 				other Taxonomy Packages published by the same entity.
	 * @var string
	 */
	public $publisherURL;

	/**
	 * Optional (1) Provides the country or region of the taxonomy publisher. The value space of the element is the
	 * 				list of alpha-2 codes defined by [ISO3166-1] as being either assignments or reservations in the
	 * 				standard, or made subsequently by the ISO 3166 maintenance agency or governing standardization bodies.
	 * @var string
	 */
	public $publisherCountry;

	/**
	 * Optional (1)  Provides a date on which the taxonomy was published.
	 * @var string
	 */
	public $publicationDate;

	/**
	 * This element is not supported
	 * Optional (1) Provides the identifer of a Taxonomy Package which is superseded by the current taxonomy.
	 * @var array[taxonomyPackageRef]
	 */
	public $supersededTaxonomyPackages = array();

	/**
	 * This element is not supported
	 * Optional (1) Identifies an XBRL Versioning Report that is relevant to this Taxonomy Package.
	 * 				The @href attribute provides a URL to the document
	 * var array[versioningReport]
	 */
	public $versioningReports = array();

	/**
	 * Optional (*) Defines an a collection of entry points.
	 * 				Each element represents an 'entry point' which has the following elements
	 * 					name (string - *)
	 * 					description (string - *)
	 * 					version (string - 1)
	 * 					entryPointDocument (Uri - *)
	 * 					languages (string - *)
	 * 				If the entryPointDocument uri points to anything other than a schema or linkbase the package will be invalid
	 * @var array[entryPoint]
	 */
	public $entryPoints = array();

	/**
	 * A list of prefix to rewrite maps
	 * @var array $rewriteURIs
	 */
	public $rewriteURIs = array();

	/**
	 * Default constructor
	 * @param ZipArchive $zipArchive
	 */
	public function __construct( ZipArchive $zipArchive  )
	{
		parent::__construct( $zipArchive );
	}

	/**
	 * Compile a taxonmy
	 * @param string $output_basename Name of the compiled taxonomy to create
	 * @param string $compiledPath (optional) Path to the compiled taxonomies folder
	 * @param string $schemaFile
	 * @return bool
	 * @throws Exception
	 */
	public function compile( $output_basename = null, $compiledPath = null,  $schemaFile = null  )
	{
		return parent::compile( $output_basename, $compiledPath, $schemaFile );
	}

	/**
	 * Returns true if the zip file represents a package that meets the taxonomy package specification
	 * {@inheritDoc}
	 * @see XBRL_IPackage::isPackage()
	 */
	public function isPackage()
	{
		// A taxonomy package has a file called TaxonomyPackage.xml in a META-INF folder

		try
		{
			// There should be one directory under the root
			if ( count( $this->contents ) != 1 )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:invalidDirectoryStructure", "More than one directory exists at the root of the zip file" );
			}

			$topLevelName = key( $this->contents );
			if ( ! isset( $this->contents[ $topLevelName ][ XBRL_TaxonomyPackage::metaFolderName ] ) )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:metadataDirectoryNotFound", "The package does not contain a META-INF directory" );
			}

			if ( ! in_array( XBRL_TaxonomyPackage::taxonomyPackageFilename, $this->contents[ $topLevelName ]['META-INF'] ) )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:metadataFileNotFound", "The package does not contain a taxonomyPackage.xml file" );
			}

			return $this->isValidMetaFile() && $this->isValidCatalogFile();
		}
		catch ( Exception $ex )
		{
			$code = $ex instanceof XBRL_TaxonomyPackageException ? $ex->error : $ex->getCode();
			$this->errors[ $code ] = $ex->getMessage();
		}

		return false;
	}

	/**
	 * Returns the name of the class implementing the XBRL instance implied by this taxonomy
	 * return string
	 */
	public function getXBRLClassname()
	{
		return "XBRL";
	}

	/**
	 * Check the meta file is valid
	 */
	public function isValidMetaFile()
	{
		if ( $this->metaFile ) return true;

		$metaFilePath = key( $this->contents ) . "/" . XBRL_TaxonomyPackage::metaFolderName . "/" . XBRL_TaxonomyPackage::taxonomyPackageFilename;

		try
		{
			// The XML should be valid
			$xml = $this->getFileAsXML( $metaFilePath );
			// Can't access the namespace of an element from SimpleXML so use the DOM version
			$dom = dom_import_simplexml( $xml );
			$namespace = $dom->namespaceURI;
			unset( $dom );

			// The root element should be taxonomyPackage
			if ( $xml->getName() != XBRL_TaxonomyPackage::rootElementNme )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:invalidMetaDataFile", $ex->getMessage() );
			}

			$this->metaFile = $xml;

			// Now time to read elements and validate the document
			$xmlAttributes = $xml->attributes( 'xml', true );
			$lang = isset( $xmlAttributes['lang'] ) ? (string)$xmlAttributes['lang'] : '';

			foreach ( $xml->children( $namespace ) as $name => $element )
			{
				switch ( $name )
				{
					case 'name':
					case 'description':
					case 'publisher':

						$elementXmlAttributes = $element->attributes( 'xml', true );
						$elementLang = isset( $elementXmlAttributes['lang'] ) ? (string)$elementXmlAttributes['lang'] : $lang;

						if ( empty( $elementLang ) )
						{
							throw XBRL_TaxonomyPackageException::withError( "tpe:missingLanguageAttribute", "The language code must not be empty") ;
						}

						if ( isset( $this->$name[ $elementLang ] ) )
						{
							throw XBRL_TaxonomyPackageException::withError( "tpe:duplicateLanguagesForElement", "The language must be unique across all names");
						}

						$this->$name[ $elementLang ] = (string)$element;

						unset( $elementAttributes );
						break;

					case 'identifier':
					case 'version':
					case 'publisherURL':
					case 'publisherCountry':
					case 'publicationDate':
					case 'publisherCountry':

						// There should be only one instance
						if ( ! is_null( $this->$name ) )
						{
							throw XBRL_TaxonomyPackageException::withError( "tpe:invalidMetaDataFile", "The element '$name' must not be repeated");
						}

						// Check for a valid country code if relevant
						if ( $name == 'publisherCountry' && ( strlen( (string)$element ) != 2 || ! isset( self::$countries[ (string)$element ] ) ) )
						{
							throw XBRL_TaxonomyPackageException::withError( 'tpe:invalidMetaDataFile', "The contry code '$element' is not a valid 2-letter country code" );
						}

						$this->$name = (string)$element;
						break;

					case 'license':

						// There should be only one instance
						if ( ! is_null( $this->$name ) )
						{
							throw XBRL_TaxonomyPackageException::withError( "tpe:invalidMetaDataFile", "The element '$name' must not be repeated");
						}

						$elementAttributes = $xml->attributes();
						$licenseName = isset( $elementAttributes['name'] ) ? (string)$elementAttributes['name'] : '';
						if ( ! $licenseName ) continue;
						$href = isset( $elementAttributes['href'] ) ? (string)$elementAttributes['href'] : '';

						$this->$name['name'] = $licenseName;
						$this->$name['href'] = $href;

						unset( $licenseName );
						unset( $href );
						unset( $elementAttributes );
						break;

					case 'entryPoints':

						$elementXmlAttributes = $element->attributes( 'xml', true );
						$elementLang = isset( $elementXmlAttributes['lang'] ) ? (string)$elementXmlAttributes['lang'] : $lang;

						$entryPoints = array();

						foreach ( $element->children( $namespace ) as $entryPointName => $entryPoint )
						{
							$elementXmlAttributes = $entryPoint->attributes( 'xml', true );
							$elementLang2 = isset( $elementXmlAttributes['lang'] ) ? (string)$elementXmlAttributes['lang'] : $elementLang;

							$entryPoints[] = array();
							$index = count( $entryPoints ) - 1;

							foreach ( $entryPoint->children( $namespace ) as $elementName => $element )
							{
								switch ( $elementName )
								{
									case 'name':
									case 'description':

										$elementXmlAttributes = $element->attributes( 'xml', true );
										$elementLang3 = isset( $elementXmlAttributes['lang'] ) ? (string)$elementXmlAttributes['lang'] : $elementLang2;

										if ( empty( $elementLang3 ) )
										{
											throw XBRL_TaxonomyPackageException::withError( "tpe:missingLanguageAttribute", "The language code must not be empty") ;
										}

										if ( isset( $entryPoints[ $index ][ $elementName ][ $elementLang3 ] ) )
										{
											throw XBRL_TaxonomyPackageException::withError( "tpe:duplicateLanguagesForElement", "The language must be unique across all names");
										}

										$entryPoints[ $index ][ $elementName ][ $elementLang3 ] = (string)$element;

										unset( $elementXmlAttributes );
										break;

									case 'version':

										// There should be only one instance
										if ( isset( $entryPoints[ $index ][ $elementName ] ) )
										{
											throw XBRL_TaxonomyPackageException::withError( "tpe:invalidMetaDataFile", "The element '$elementName' must not be repeated");
										}

										$entryPoints[ $index ][ $elementName ] = (string)$element;
										break;

									case 'entryPointDocument':

										$elementAttributes = $element->attributes();
										if ( ! isset( $elementAttributes->href ) )
										{
											throw XBRL_TaxonomyPackageException::withError( "tpe:invalidMetaDataFile", "Missing the @href on element '$elementName'");
										}

										$href = (string)$elementAttributes->href;
										$entryPoints[ $index ][ $elementName ][] = $href;

										unset( $href );
										unset( $elementAttributes );
										break;

									case 'languages':

										foreach ( $element as $languageElement => $language )
										{
											$entryPoints[ $index ][ $elementName ][] = (string)$language;
										}
										unset( $languageElement );
										unset( $language );
										break;
								}
							}

							unset( $element );
							unset( $elementName );
						}


						$this->entryPoints = $entryPoints;

						unset( $entryPoints );
						unset( $entryPoint );
						unset( $entryPointName );

						break;

					default:

						// supersededTaxonomyPackages
						// versioningReports
						break;
				}
			}

			unset( $element );

		}
		catch ( XBRL_TaxonomyPackageException $ex )
		{
			throw $ex;
		}
		catch ( Exception $ex )
		{
			throw XBRL_TaxonomyPackageException::withError( "tpe:invalidMetaDataFile", $ex->getMessage() );
		}

		return true;
	}

	/**
	 * Check the meta file is valid
	 */
	public function isValidCatalogFile()
	{
		if ( $this->catalog || !
			 isset( $this->contents[ key( $this->contents ) ][ XBRL_TaxonomyPackage::metaFolderName ] ) ||
			 ! in_array( XBRL_TaxonomyPackage::catalogFilename, $this->contents[ key( $this->contents ) ][ XBRL_TaxonomyPackage::metaFolderName ] ) ) return true;

		$catalogFilePath = key( $this->contents ) . "/" . XBRL_TaxonomyPackage::metaFolderName . "/" . XBRL_TaxonomyPackage::catalogFilename;

		try
		{
			// The XML should be valid
			$xml = $this->getFileAsXML( $catalogFilePath );
			// Can't access the namespace of an element from SimpleXML so use the DOM version
			$dom = dom_import_simplexml( $xml );
			$namespace = $dom->namespaceURI;
			unset( $dom );

			// The root element should be taxonomyPackage
			if ( $xml->getName() != XBRL_TaxonomyPackage::catalogRootElementNme )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:invalidCatalogFile", $ex->getMessage() );
			}

			$this->catalog = $xml;
			$rewriteURIs = array();

			foreach ( $xml->children( $namespace ) as $name => $element )
			{
				switch ( $name )
				{
					case 'rewriteURI':

						$elementAttributes = $element->attributes();
						if ( ! isset( $elementAttributes->rewritePrefix ) )
						{
							throw XBRL_TaxonomyPackageException::withError( "tpe:invalidCatalogFile", "The @rewriteURI attribute is missing" );
						}

						if ( ! isset( $elementAttributes->uriStartString ) )
						{
							throw XBRL_TaxonomyPackageException::withError( "tpe:invalidCatalogFile", "The @uriStartString attribute is missing" );
						}

						$rewriteURI = (string)$elementAttributes->rewritePrefix ;
						$uriStartString = (string)$elementAttributes->uriStartString ;

						if ( isset( $rewriteURIs[ $uriStartString ] ) )
						{
							throw XBRL_TaxonomyPackageException::withError( "tpe:multipleRewriteURIsForStartString", "There is more than one @uriStartString with the value '$uriStartString'" );
						}

						$rewriteURIs[ $uriStartString ] = $rewriteURI;

						unset( $elementAttributes );
						unset( $rewriteURI );
						unset( $uriStartString );

						break;
				}
			}

			$this->rewriteURIs = $rewriteURIs;

			unset( $name );
			unset( $element );
			unset( $xml );
		}
		catch ( XBRL_TaxonomyPackageException $ex )
		{
			throw $ex;
		}
		catch ( Exception $ex )
		{
			throw XBRL_TaxonomyPackageException::withError( "tpe:invalidMetaDataFile", $ex->getMessage() );
		}

		return true;
	}

	/**
	 * Return the contents of a file given a path
	 * @param string $path
	 * @return SimpleXMLElement
	 * @throws Exception if the requested file does not exist
	 */
	public function getFileAsXML( $path )
	{
		try
		{
			$xml = $this->getFile( $this->getActualUri( $path ) );
			isXml( $xml );
			return simplexml_load_string( $xml );
		}
		catch ( Exception $ex )
		{
			throw XBRL_TaxonomyPackageException::withError( "tpe:invalidMetaDataFile", $ex->getMessage() );
		}
	}

	/**
	 * Returns an array of schema file names defined as entry points
	 */
	public function getSchemaEntryPoints()
	{
		return array_reduce( $this->entryPoints, function( $carry, $entryPoint ) {
			$schemaFiles = array_filter( $entryPoint['entryPointDocument'], function( $entryPointDocument ) {
				return XBRL::endsWith( $entryPointDocument, ".xsd" );
			} );
			return array_merge( $carry, $schemaFiles );
		}, array() );
	}

	/**
	 * Return the details for an entry point identified by index or document name
	 * @param int|string $entryPointId
	 * @return array
	 */
	public function getDetailForEntryPoint( $entryPointId )
	{
		if ( is_numeric( $entryPointId ) )
		{
			if ( isset( $this->entryPoints[ $entryPointId ] ) )
				return $this->entryPoints[ $entryPointId ];
		}
		else if ( is_string( $entryPointId ) )
		{
			return @reset( array_filter( $this->entryPoints, function( $entryPoint ) use ( $entryPointId )
			{
				return isset( $entryPoint['entryPointDocument'][0] ) &&
					   $entryPoint['entryPointDocument'][0] == $entryPointId;
			} ) );
		}

		return array();
	}

	/**
	 * Return the url for the 'all' entry point
	 */
	public function getAllEntryPoint()
	{
		$entryPoints = $this->getSchemaEntryPoints();
		$alls = array_filter( $entryPoints, function( $entryPoint ) { return strpos( $entryPoint, "entryAll" ) !== false; } );
		return $alls ? reset( $alls ) : false;
	}

	/**
	 * Workout which file is the schema file
	 * @return void
	 * @throws "tpe:schemaFileNotFound"
	 */
	protected function determineSchemaFile( )
	{
		if ( ! is_null( $this->schemaFile ) ) return;

		$schemaFilesList = $this->getSchemaEntryPoints();

		if ( count( $schemaFilesList ) == 0 )
		{
			throw XBRL_TaxonomyPackageException::withError( "tpe:schemaFileNotFound", "The package does not contain a schema (.xsd) file" );
		}

		foreach ( $schemaFilesList as $schemaFile )
		{
			$actualUri = $this->getActualUri( $schemaFile );
			$content = $this->getFile( $actualUri );
			if ( $content )
			{
				$this->schemaNamespace = $this->getTargetNamespace( $schemaFile, $content );
				$this->schemaFile = $schemaFile;

				$this->setUrlMap();
			}
		}
	}

	/**
	 * Returns a localized version of the schema file path
	 * @param string $uri
	 * @return string
	 */
	protected function getActualUri( $uri )
	{
		foreach ( $this->rewriteURIs as $prefix => $rewriteUri )
		{
			if ( strpos( $uri, $prefix ) !== 0 ) continue;
			$actualUri =  str_replace( $prefix, $rewriteUri, $uri );
			$actualUri = XBRL::normalizePath( $this->getFirstFolderName() . "/" . XBRL_TaxonomyPackage::metaFolderName . "/" . $actualUri );
			return $actualUri;
		}

		return $uri;
	}

	/**
	 * Save the taxonomy from the package to the cache location
	 * @param string $cacheLocation
	 * @return boolean
	 */
	public function saveTaxonomy( $cacheLocation )
	{
		$getCommonRootFolder = function( $actualUri, $uri )
		{
			$uriParts = array_reverse( explode( "/", $uri ) );
			$actualUriParts = array_reverse( explode( "/", $actualUri ) );

			$count = min( array( count( $uriParts ), count( $actualUriParts ) ) );

			for( $i = 0; $i < $count; $i++ )
			{
				if ( $uriParts[ $i ] != $actualUriParts[ $i ] ) break;

			}

			if ( $uriParts[ $i ] === '' && isset( $uriParts[ $i + 1 ] ) && preg_match_all( "/https?:/", $uriParts[ $i + 1 ] ) )
			{
				$i--;
			}

			return array(
				// 'actual' => implode( "/", array_reverse( array_slice( $actualUriParts, $i -1 ) ) ),
				// 'uri'    => implode( "/", array_reverse( array_slice( $uriParts, $i -1 ) ) )
				'actual' => implode( "/", array_reverse( array_slice( $actualUriParts, $i ) ) ),
				'uri'    => implode( "/", array_reverse( array_slice( $uriParts, $i ) ) )
			);
		};

		// Initialize the context
		$context = XBRL_Global::getInstance();
		$context->useCache = true;
		$context->cacheLocation = $cacheLocation;
		$context->initializeCache();

		$this->determineSchemaFile();

		if ( $context->findCachedFile( $this->schemaFile ) )
		{
			$this->errors[] = "The schema file '{$this->schemaFile}' already exists in the cache.";
			return false;
		}

		// Look at the entry points and remap them to their location in the zip file
		foreach ( $this->entryPoints as $index => $entryPoint )
		{
			// $entryPoint is an array with one of the elements called entryPointDocument being an array of uris
			foreach ( $entryPoint['entryPointDocument'] as $uri )
			{
				// Is there a rewrite?
				$actualUri = $this->getActualUri( $uri );

				// If there is a rewrite or the entry point uri is relative it is to an address in the package
				$parts = parse_url( $actualUri );
				if ( isset( $parts['scheme'] ) || $parts['path'][0] == '/' ) continue; // absolute

				// Should call XBRL_Package::getCommonRootFolder()
				$commonRootFolder = $getCommonRootFolder( $actualUri, $uri );

				// if ( isset( $parts['scheme'] ) || $parts['path'][0] == '/' ) // absolute
				// {
				//	$xml = XBRL::getXml( $actualUri, $context );
				// }
				// else
				{
					// Handle the relative case
					// $folder = $this->getElementForPath( dirname( $actualUri ) );
					$folder = $this->getElementForPath( $commonRootFolder['actual'] );

					// Create a copy of all files and folder in the cache location
					$copy = function( $folder, $path, $uri ) use( &$copy, &$context )
					{
						foreach ( $folder as $index => $item )
						{
							// if $index is numeric then $item is a file
							// if ( is_numeric( $index ) && $index < 2000) // The index might be 2015 or 20161001
							if ( ! is_array( $item ) )
							{
								$content = $this->getFile( "$path/$item" );
								if ( ! $content ) continue;
								if ( $context->findCachedFile( "$uri/$item" ) ) continue;
								$context->saveCacheFile( "$uri/$item", $content );
							}
							else
							{
								if ( $index == "META-INF" ) continue;

								// Handle any directories
								$newUri = "$uri/$index";
								$newPath = $path ? "$path/$index" : $index;
								$copy( $item, $newPath, $newUri );
							}
						}
					};

					// $copy( $folder, dirname( $actualUri ),  dirname( $uri ) );
					$copy( $folder, $commonRootFolder['actual'],  $commonRootFolder['uri'] );
				}
			}
		}

		return true;
	}

	/**
	 * Returns a list of countries
	 * @param boolean $addCode
	 * @return array
	 */
	public static function getCountries( $addCode = false )
	{
		return $addCode
			? XBRL::array_reduce_key( self::$countries, function( $carry, $country, $code )
				{
					$carry[ $code ] = "$country ($code)";
					return $carry;
				}, [] )
			: self::$countries;
	}

	/**
	 * A list of countries indexed by their 2-letter code
	 * @var array[string]
	 */
	private static $countries = array(
		'EU' => 'European Union',
		'US' => 'United States',
		'CA' => 'Canada',
		'GB' => 'United Kingdom',
		'AF' => 'Afghanistan',
		'AX' => '&#197;land Islands',
		'AL' => 'Albania',
		'DZ' => 'Algeria',
		'AS' => 'American Samoa',
		'AD' => 'Andorra',
		'AO' => 'Angola',
		'AI' => 'Anguilla',
		'AQ' => 'Antarctica',
		'AG' => 'Antigua and Barbuda',
		'AR' => 'Argentina',
		'AM' => 'Armenia',
		'AW' => 'Aruba',
		'AU' => 'Australia',
		'AT' => 'Austria',
		'AZ' => 'Azerbaijan',
		'BS' => 'Bahamas',
		'BH' => 'Bahrain',
		'BD' => 'Bangladesh',
		'BB' => 'Barbados',
		'BY' => 'Belarus',
		'BE' => 'Belgium',
		'BZ' => 'Belize',
		'BJ' => 'Benin',
		'BM' => 'Bermuda',
		'BT' => 'Bhutan',
		'BO' => 'Bolivia',
		'BQ' => 'Bonaire, Saint Eustatius and Saba',
		'BA' => 'Bosnia and Herzegovina',
		'BW' => 'Botswana',
		'BV' => 'Bouvet Island',
		'BR' => 'Brazil',
		'IO' => 'British Indian Ocean Territory',
		'BN' => 'Brunei Darrussalam',
		'BG' => 'Bulgaria',
		'BF' => 'Burkina Faso',
		'BI' => 'Burundi',
		'KH' => 'Cambodia',
		'CM' => 'Cameroon',
		'CV' => 'Cape Verde',
		'KY' => 'Cayman Islands',
		'CF' => 'Central African Republic',
		'TD' => 'Chad',
		'CL' => 'Chile',
		'CN' => 'China',
		'CX' => 'Christmas Island',
		'CC' => 'Cocos Islands',
		'CO' => 'Colombia',
		'KM' => 'Comoros',
		'CD' => 'Congo, Democratic People\'s Republic',
		'CG' => 'Congo, Republic of',
		'CK' => 'Cook Islands',
		'CR' => 'Costa Rica',
		'CI' => 'Cote d\'Ivoire',
		'HR' => 'Croatia/Hrvatska',
		'CU' => 'Cuba',
		'CW' => 'Cura&Ccedil;ao',
		'CY' => 'Cyprus',
		'CZ' => 'Czechia',
		'DK' => 'Denmark',
		'DJ' => 'Djibouti',
		'DM' => 'Dominica',
		'DO' => 'Dominican Republic',
		'TP' => 'East Timor',
		'EC' => 'Ecuador',
		'EG' => 'Egypt',
		'GQ' => 'Equatorial Guinea',
		'SV' => 'El Salvador',
		'ER' => 'Eritrea',
		'EE' => 'Estonia',
		'ET' => 'Ethiopia',
		'FK' => 'Falkland Islands',
		'FO' => 'Faroe Islands',
		'FJ' => 'Fiji',
		'FI' => 'Finland',
		'FR' => 'France',
		'GF' => 'French Guiana',
		'PF' => 'French Polynesia',
		'TF' => 'French Southern Territories',
		'GA' => 'Gabon',
		'GM' => 'Gambia',
		'GE' => 'Georgia',
		'DE' => 'Germany',
		'GR' => 'Greece',
		'GH' => 'Ghana',
		'GI' => 'Gibraltar',
		'GL' => 'Greenland',
		'GD' => 'Grenada',
		'GP' => 'Guadeloupe',
		'GU' => 'Guam',
		'GT' => 'Guatemala',
		'GG' => 'Guernsey',
		'GN' => 'Guinea',
		'GW' => 'Guinea-Bissau',
		'GY' => 'Guyana',
		'HT' => 'Haiti',
		'HM' => 'Heard and McDonald Islands',
		'VA' => 'Holy See (City Vatican State)',
		'HN' => 'Honduras',
		'HK' => 'Hong Kong',
		'HU' => 'Hungary',
		'IS' => 'Iceland',
		'IN' => 'India',
		'ID' => 'Indonesia',
		'IR' => 'Iran',
		'IQ' => 'Iraq',
		'IE' => 'Ireland',
		'IM' => 'Isle of Man',
		'IL' => 'Israel',
		'IT' => 'Italy',
		'JM' => 'Jamaica',
		'JP' => 'Japan',
		'JE' => 'Jersey',
		'JO' => 'Jordan',
		'KZ' => 'Kazakhstan',
		'KE' => 'Kenya',
		'KI' => 'Kiribati',
		'KW' => 'Kuwait',
		'KG' => 'Kyrgyzstan',
		'LA' => 'Lao People\'s Democratic Republic',
		'LV' => 'Latvia',
		'LB' => 'Lebanon',
		'LS' => 'Lesotho',
		'LR' => 'Liberia',
		'LY' => 'Libyan Arab Jamahiriya',
		'LI' => 'Liechtenstein',
		'LT' => 'Lithuania',
		'LU' => 'Luxembourg',
		'MO' => 'Macau',
		'MK' => 'Macedonia',
		'MG' => 'Madagascar',
		'MW' => 'Malawi',
		'MY' => 'Malaysia',
		'MV' => 'Maldives',
		'ML' => 'Mali',
		'MT' => 'Malta',
		'MH' => 'Marshall Islands',
		'MQ' => 'Martinique',
		'MR' => 'Mauritania',
		'MU' => 'Mauritius',
		'YT' => 'Mayotte',
		'MX' => 'Mexico',
		'FM' => 'Micronesia',
		'MD' => 'Moldova, Republic of',
		'MC' => 'Monaco',
		'MN' => 'Mongolia',
		'ME' => 'Montenegro',
		'MS' => 'Montserrat',
		'MA' => 'Morocco',
		'MZ' => 'Mozambique',
		'MM' => 'Myanmar',
		'NA' => 'Namibia',
		'NR' => 'Nauru',
		'NP' => 'Nepal',
		'NL' => 'Netherlands',
		'AN' => 'Netherlands Antilles',
		'NC' => 'New Caledonia',
		'NZ' => 'New Zealand',
		'NI' => 'Nicaragua',
		'NE' => 'Niger',
		'NG' => 'Nigeria',
		'NU' => 'Niue',
		'NF' => 'Norfolk Island',
		'KP' => 'North Korea',
		'MP' => 'Northern Mariana Islands',
		'NO' => 'Norway',
		'OM' => 'Oman',
		'PK' => 'Pakistan',
		'PW' => 'Palau',
		'PS' => 'Palestinian Territories',
		'PA' => 'Panama',
		'PG' => 'Papua New Guinea',
		'PY' => 'Paraguay',
		'PE' => 'Peru',
		'PH' => 'Philippines',
		'PN' => 'Pitcairn Island',
		'PL' => 'Poland',
		'PT' => 'Portugal',
		'PR' => 'Puerto Rico',
		'QA' => 'Qatar',
		'XK' => 'Republic of Kosovo',
		'RE' => 'Reunion Island',
		'RO' => 'Romania',
		'RU' => 'Russian Federation',
		'RW' => 'Rwanda',
		'BL' => 'Saint Barth&eacute;lemy',
		'SH' => 'Saint Helena',
		'KN' => 'Saint Kitts and Nevis',
		'LC' => 'Saint Lucia',
		'MF' => 'Saint Martin (French)',
		'SX' => 'Saint Martin (Dutch)',
		'PM' => 'Saint Pierre and Miquelon',
		'VC' => 'Saint Vincent and the Grenadines',
		'SM' => 'San Marino',
		'ST' => 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe',
		'SA' => 'Saudi Arabia',
		'SN' => 'Senegal',
		'RS' => 'Serbia',
		'SC' => 'Seychelles',
		'SL' => 'Sierra Leone',
		'SG' => 'Singapore',
		'SK' => 'Slovak Republic',
		'SI' => 'Slovenia',
		'SB' => 'Solomon Islands',
		'SO' => 'Somalia',
		'ZA' => 'South Africa',
		'GS' => 'South Georgia',
		'KR' => 'South Korea',
		'SS' => 'South Sudan',
		'ES' => 'Spain',
		'LK' => 'Sri Lanka',
		'SD' => 'Sudan',
		'SR' => 'Suriname',
		'SJ' => 'Svalbard and Jan Mayen Islands',
		'SZ' => 'Swaziland',
		'SE' => 'Sweden',
		'CH' => 'Switzerland',
		'SY' => 'Syrian Arab Republic',
		'TW' => 'Taiwan',
		'TJ' => 'Tajikistan',
		'TZ' => 'Tanzania',
		'TH' => 'Thailand',
		'TL' => 'Timor-Leste',
		'TG' => 'Togo',
		'TK' => 'Tokelau',
		'TO' => 'Tonga',
		'TT' => 'Trinidad and Tobago',
		'TN' => 'Tunisia',
		'TR' => 'Turkey',
		'TM' => 'Turkmenistan',
		'TC' => 'Turks and Caicos Islands',
		'TV' => 'Tuvalu',
		'UG' => 'Uganda',
		'UA' => 'Ukraine',
		'AE' => 'United Arab Emirates',
		'UY' => 'Uruguay',
		'UM' => 'US Minor Outlying Islands',
		'UZ' => 'Uzbekistan',
		'VU' => 'Vanuatu',
		'VE' => 'Venezuela',
		'VN' => 'Vietnam',
		'VG' => 'Virgin Islands (British)',
		'VI' => 'Virgin Islands (USA)',
		'WF' => 'Wallis and Futuna Islands',
		'EH' => 'Western Sahara',
		'WS' => 'Western Samoa',
		'YE' => 'Yemen',
		'ZM' => 'Zambia',
		'ZW' => 'Zimbabwe'
	);
}
