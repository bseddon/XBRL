<?php
/**
 * XBRL IFRS Taxonomy Package handler
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
 * Implements functions to read an IFRS taxonomy package
 */
class XBRL_IFRS_Package extends XBRL_TaxonomyPackage
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

		}

		return false;
	}

	/**
	 * Returns the name of the XBRL class that supports IFRS pckage contents
	 * {@inheritDoc}
	 * @see XBRL_Package::getXBRLClassname()
	 */
	public function getXBRLClassname()
	{
		return "XBRL_IFRS";
	}
}