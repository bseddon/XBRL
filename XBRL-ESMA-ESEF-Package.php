<?php
/**
 * XBRL ESMA Taxonomy Package handler
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
 * Implements functions to read an ESMA taxonomy package
 */
class XBRL_ESMA_ESEF_Package extends XBRL_TaxonomyPackage
{
	/**
	 * Returns true if the zip file represents a package that meets the taxonomy package specification
	 * {@inheritDoc}
	 * @see XBRL_Package::isPackage()
	 */
	public function isPackage()
	{
		if ( ! parent::isPackage() ) return false;

		// If it is a package then is an IFRS taxonomy package?
		// An IFRS package is characterised by having one or more of the
		// package entry points be the entry posints in the XBRL_IFRS entry point list
		// and no instance document.
		// Check the entry points first because they are already in an array.
		foreach ( $this->getSchemaEntryPoints() as $entryPoint )
		{
			// echo "{$attributes['namespace']}\n";
			$nameOfXBRLClass = $this->getXBRLClassname();
			if ( ! $nameOfXBRLClass::compiled_taxonomy_for_xsd( basename( $entryPoint ) ) ) continue;

			return true;

		}

		// Now check the schema file to see if it references a recognized namespace
		return $this->getIsExtensionTaxonomy();
	}

	/**
	 * Returns the name of the XBRL class that supports IFRS pckage contents
	 * {@inheritDoc}
	 * @see XBRL_Package::getXBRLClassname()
	 */
	public function getXBRLClassname()
	{
		return "XBRL_ESMA_ESEF";
	}

	/**
	 * Workout which file is the schema file
	 * @return void
	 * @throws "tpe:schemaFileNotFound"
	 */
	protected function determineSchemaFile()
	{
		if ( ! is_null( $this->schemaFile ) ) return;

		$schemaFilesList = $this->getSchemaEntryPoints();

		if ( count( $schemaFilesList ) == 0 )
		{
			throw XBRL_TaxonomyPackageException::withError( "tpe:schemaFileNotFound", "The package does not contain a schema (.xsd) file" );
		}

		foreach ( $schemaFilesList as $schemaFile )
		{

			if ( XBRL::startsWith( $schemaFile, 'http://www.esma.europa.eu/' ) &&
				 ! XBRL::endsWith( $schemaFile, 'esef_cor.xsd' ) )
			{
				continue;
			}

			$actualUri = $this->getActualUri( $schemaFile );
			$content = $this->getFile( $actualUri );
			if ( $content )
			{
				$this->schemaNamespace = $this->getTargetNamespace( $schemaFile, $content );
				$this->schemaFile = $schemaFile;

				$this->setUrlMap();
			}

			break;
		}
	}

}