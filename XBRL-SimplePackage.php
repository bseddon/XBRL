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
	 * Returns true if the zip file represents an SEC package
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
				if ( $found ) $this->schemaFile = ( $path ? "$path/" : "" ) . $name;
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
	 * Save the taxonomy from the package to the cache location
	 * @param string $cacheLocation
	 * @return boolean
	 */
	public function saveTaxonomy( $cacheLocation )
	{
		// Initialize the context
		$context = XBRL_Global::getInstance();
		$context->useCache = true;
		$context->cacheLocation = $cacheLocation;
		$context->initializeCache();

		// Find the schema document
		$content = trim( $this->getFile( $this->schemaFile ) );

		$result = $this->processSchemaDocument( $context, $this->schemaFile, $content, false );
		$this->setUrlMap();

		if ( ! $result )
		{
			$this->errors[] = "Unable to process the schema document";
			return false;
		}


		// Look for the schema file
		$this->traverseContents( function( $path, $name, $type ) use( &$context )
		{
			if ( $type == PATHINFO_DIRNAME ) return true;
			$extension = pathinfo( $name, PATHINFO_EXTENSION );
			if ( ! in_array( $extension, array( 'xml', 'xbrl' ) ) ) return true;
			$path = $path ? "$path/$name" : $name;
			$content = $this->getFile( $path );
			if ( ! $context->findCachedFile( "{$this->schemaNamespace}/$path" ) ) return true;
			$context->saveCacheFile( "{$this->schemaNamespace}/$path", $content );
			return true;
		} );

		return true;
	}

}
