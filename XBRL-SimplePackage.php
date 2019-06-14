<?php

/**
 * XBRL Simple Package handler
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
class XBRL_SimplePackage extends XBRL_Package
{
	/**
	 * Notes about using this package instance
	 * @var string
	 */
	const notes = <<<EOT
Simple package implementations will use the namespace of the schema document to create a path to use in the cache.
If a path is not used in the instance document <schemaRef> (so the instance document expects to be in the same
folder as the taxonomy) it is likely that a mapped URL will need to be used so the correct schema can be accessed
from the cache.\n\n
EOT;

	/**
	 * Returns true if the zip file represents no other specific package
	 * {@inheritDoc}
	 * @see XBRL_IPackage::isPackage()
	 */
	public function isPackage()
	{
		// A simple package does not have a manifest file
		try
		{
			$found = false;

			$this->traverseContents( function( $path, $name, $type ) use( &$found )
			{
				if ( $type == PATHINFO_DIRNAME ) return true;
				$found = $name == XBRL_SEC_XML_Package::manifestFilename || XBRL::endsWith( $name, '.json' );
				return ! $found;
			} );

			if ( $found )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:metadataFileFound", "The package contains a manifest.xml file or JSON manifest file." );
			}

			// Look for the schema file
			$this->traverseContents( function( $path, $name, $type ) use( &$found )
			{
				if ( $type == PATHINFO_DIRNAME ) return true;
				$found = $name == XBRL::endsWith( $name, '.xsd' );
				if ( $found ) $this->schemaFile = XBRL::normalizePath( ( $path ? "$path/" : "" ) . $name );
				return ! $found;
			} );

			if ( ! $found )
			{
				throw XBRL_TaxonomyPackageException::withError( "tpe:noSchemaFileFound", "The package must contain a schema file (.xsd)." );
			}

			return true;
		}
		catch ( Exception $ex )
		{
			$code = $ex instanceof XBRL_TaxonomyPackageException ? $ex->error : $ex->getCode();
			$this->errors[ $code ] = $ex->getMessage();
		}

		return false;
	}

	/**
	 * Workout which file is the schema file
	 * @return void
	 * @throws "tpe:schemaFileNotFound"
	 */
	protected function determineSchemaFile()
	{
		if ( $this->schemaFile && $this->schemaNamespace ) return;

		$schemaFileList = array();
		$this->traverseContents( function( $path, $name, $type) use( &$schemaFileList )
		{
			if ( $type == PATHINFO_DIRNAME ) return true;
			$extension = pathinfo( $name, PATHINFO_EXTENSION );
			if ( $extension != 'xsd' ) return true;
			$schemaFileList[] = "$path$name";
			return true;
		} );

		if ( count( $schemaFileList ) != 1 )
		{
			throw XBRL_TaxonomyPackageException::withError( "tpe:schemaFileNotFound", "The package does not contain just one schema (.xsd) file" );
		}

		$this->schemaFile = reset( $schemaFileList );
		$content = $this->getFile( $this->schemaFile );
		$this->schemaNamespace = $this->getTargetNamespace( $this->schemaFile, $content );
		if ( ! XBRL::endsWith( $this->schemaNamespace, '/' ) )
		{
			$this->schemaNamespace.= "/";
		}

		// Fix up the schema file
		$scheme = parse_url( $this->schemaFile, PHP_URL_SCHEME );
		if ( $scheme ) return;
		$this->schemaFile = $this->schemaNamespace . $this->schemaFile;
	}

	/**
	 * Returns a localized version of the schema file path
	 * @param string $uri
	 * @return string
	 */
	protected function getActualUri( $uri )
	{
		return str_replace( $this->schemaNamespace, '', $uri );
	}

	/**
	 * Save the taxonomy from the package to the cache location
	 * @param string $cacheLocation
	 * @return boolean
	 */
	public function saveTaxonomy( $cacheLocation )
	{
		// Initialize the context
		$context = XBRL_Global::getInstance();
		if ( ! $context->useCache )
		{
			$context->useCache = true;
			$context->cacheLocation = $cacheLocation;
			$context->initializeCache();
		}

		$this->determineSchemaFile();

		if ( ! $this->schemaFile || ! $this->schemaNamespace )
		{
			$this->errors[] = "Unable to process the schema document";
			return false;
		}

		// $this->determineSchemaFile();

		// Find the schema document
		$content = trim( $this->getFile( $this->getActualUri( $this->schemaFile ) ) );

		$result = $this->processSchemaDocument( $context, $content, false );
		$this->setUrlMap();

		if ( ! $result )
		{
			$this->errors[] = "Unable to process the schema document";
			return false;
		}

		// Look for the non-schema file
		$this->traverseContents( function( $path, $name, $type ) use( &$context )
		{
			if ( $type == PATHINFO_DIRNAME ) return true;
			$extension = pathinfo( $name, PATHINFO_EXTENSION );
			if ( ! in_array( $extension, array( 'xml', 'xbrl', 'xsd' ) ) ) return true;
			$path = $path ? "$path$name" : "$name";
			$content = $this->getFile( $path );
			$uri = XBRL::resolve_path( $this->schemaFile, $path ); // $common = $this->getCommonRootFolder( $path, $this->schemaFile );
			if ( $context->findCachedFile( $uri ) ) return true;
			$context->saveCacheFile( $uri, $content );
			return true;
		} );

		return true;
	}

	/**
	 * Gets the class name for the taxonomy
	 * {@inheritDoc}
	 * @see XBRL_Package::getXBRLClassname()
	 */
	private $taxonomyPrefixes = array(
		"http://fasb.org/us-gaap"
	);

	public function getXBRLClassname()
	{
		if ( $this->schemaFile && $this->schemaNamespace )
		{
			$xml = $this->getInstanceDocument();
			$namespaces = $xml->getDocNamespaces();
			if ( array_filter( $namespaces, function( $namespace )
				{
					foreach( $this->taxonomyPrefixes as $prefix )
					{
						if ( strpos( $namespace, $prefix ) !== false ) return true;
					}
					return false;
				} ) )
			{
				return "XBRL_US_GAAP_2015";
			}
		}
		return parent::getXBRLClassname();
	}
}
