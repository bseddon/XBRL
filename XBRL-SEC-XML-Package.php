<?php

/**
 * XBRL SEC XML Package handler
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

/**
 * Implements functions to read a taxonomy package
 */
class XBRL_SEC_XML_Package extends XBRL_SEC_JSON_Package
{
	/**
	 * Notes about using this package instance
	 * @var string
	 */
	const notes = <<<EOT
The SEC implementations use the namespace of the schema document to create a path to use in the cache.
The namespace format appears to be <domain>/<period> where <period> is the value of the EDGAR period.
A path is not used in the instance document <schemaRef> so the instance document expects to be in the
same folder as the taxonomy.  It is likely that a mapped URL will need to be used so the correct
schema can be accessed from the cache.\n\n
EOT;

	/**
	 * Name of the manifest file
	 */
	const manifestFilename = "manifext.xml";

	/**
	 * The namespace used in the manifest file
	 * @var string
	 */
	const edgarNamespace = 'http://www.sec.gov/Archives/edgar';

	/**
	 * Title for the package
	 * @var string
	 */
	public $title;

	/**
	 * Link to the EDGAR site
	 * @var string
	 */
	public $link;

	/**
	 * The list of package files
	 * @var array
	 */
	public $xbrlFiles = array();

	/**
	 * Default constructor
	 * @param ZipArchive $zipArchive
	 */
	public function __construct( ZipArchive $zipArchive  )
	{
		parent::__construct( $zipArchive );
	}

	/**
	 * Returns true if the zip file represents an SEC package
	 * {@inheritDoc}
	 * @see XBRL_IPackage::isPackage()
	 */
	public function isPackage()
	{
		// A SEC JSON package has a file called <ciknumber>-manifest.xml
		try
		{
			$manifest = $this->getFile( XBRL_SEC_XML_Package::manifestFilename );
			if ( ! $manifest )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:metadataFileNotFound", "The package does not contain a manifest.xml file" );
			}

			return $this->isValidMetaFile();
		}
		catch ( Exception $ex )
		{
			$code = $ex instanceof XBRL_TaxonomyPackageException ? $ex->error : $ex->getCode();
			$this->errors[ $code ] = $ex->getMessage();
		}

		return false;

	}

	/**
	 * Check the meta file is valid
	 */
	private function isValidMetaFile()
	{
		if ( $this->metaFile ) return true;

		try
		{
			$this->metaFile = $this->getFileAsXML( XBRL_SEC_XML_Package::manifestFilename );
			$this->processElement( $this->metaFile->children() );
			$xbrlFilings = $this->metaFile->children('http://www.sec.gov/Archives/edgar')->xbrlFiling;
			$this->processElement( $xbrlFilings->children( XBRL_SEC_XML_Package::edgarNamespace ) );
			$xbrlFiles = $xbrlFilings->xbrlFiles;
			$this->processXBRLFiles( $xbrlFiles->children( XBRL_SEC_XML_Package::edgarNamespace ) );

			$schemaFileList = array_filter( $this->files, function( $item ) {
				return XBRL::endsWith( $item, '.xsd' );
			} );

			if ( count( $schemaFileList ) != 1 )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:schemaFileNotFound", "The package does not contain a schema (.xsd) file" );
			}

			// $this->schemaFile = reset( $schemaFileList );
			$this->determineSchemaFile();
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
	 * Grab the values from the XML
	 * @param SimpleXMLElement $element
	 */
	private function processElement( $elements )
	{
		foreach ( $elements as $name => $value )
		{
			if ( $value->count() ) continue;

			if ( ! property_exists( $this, $name ) ) continue;
			$this->$name = (string)$value;
		}
	}

	/**
	 * Extract the file names from the manifest
	 * @param SimpleXMLElement $elements
	 */
	private function processXBRLFiles( $elements )
	{
		/**
		 * An array of files each with the following elements
		 * 		sequence="1"
		 * 		file="quest_10k.htm"
		 * 		type="10-K"
		 * 		size="314940"
		 * 		description="FORM 10-K"
		 * 		url="http://www.sec.gov/Archives/edgar/data/1627554/000147793216008346/quest_10k.htm"
		 * @var array $xbrlFiles
		 */
		$xbrlFiles = array();

		foreach ( $elements as $name => $element )
		{
			$xbrlFile = array();

			foreach ( $element->attributes( XBRL_SEC_XML_Package::edgarNamespace ) as $attributeName => $value )
			{
				$xbrlFile[ $attributeName ] = (string)$value;
			}

			$xbrlFiles[] = $xbrlFile;
		}

		$this->xbrlFiles = $xbrlFiles;

		// Create the array contain just the file names
		$this->files = array_map( function( $item ) {
			return $item['file'];
		}, $xbrlFiles );
	}

}