<?php

/**
 * Comparison report implementation
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
 * Load the XBRL implementation
 */
require_once( 'XBRL-Report-Base.php' );

/**
 * Class to support the creation of comparative reports
 * @author Bill Seddon
 */
class XBRL_Report_Compare extends XBRL_Report_Base
{
	/**
	 * Default constructor
	 * @param array $years An array of the years to explicitly include in the report
	 */
	public function __construct( $years = array() ) {}

	/**
	 * This function is overridden to check the validity of the instance documents included
	 *
	 * @param bool $prune True if the presentation hierarchies should be pruned to keep only items with elements assigned
	 * @return void
	 */
	public function preparePresentation( $prune = true )
	{
		$docs = $this->getInstanceDocuments();

		$existingYears = null;

		foreach ( $docs as $instanceKey => $instance )
		{
			$instanceYears = array();

			foreach ( $instance->usedContexts as $key => $context )
			{
				$instanceYears[ $context['year'] ] = $context['year'];
			}

			// Eliminate any years that are not in the valid years list
			if ( $this->validYears )
			{
				$diffYears = array_diff( $instanceYears, $this->validYears );
				if ( $diffYears )
				{
					sort( $diffYears );
					$omittedYears = implode( ",", $diffYears );
					$this->log()->info( "Data in the instance document {$instance->getDocumentName()} for $omittedYears will not be included in the generated report." );
				}
				$instanceYears = array_intersect( $instanceYears, $this->validYears );
			}

			if ( $existingYears === null )
			{
				$existingYears = $instanceYears;
				continue;
			}

			// Check the instances have overlaps.  Order the arrays this way so the $instanceYears are checked
			$diffYears = array_diff( $instanceYears, $existingYears ) + array_diff( $existingYears, $instanceYears );
			if ( ! $diffYears ) continue;

			if ( ! array_intersect( $diffYears, $instanceYears ) )
			{
				$this->log()->info( "None of the years in instance {$instance->getDefaultCurrency()} overlap with other documents included in this report" );
			}
			if ( count( array_intersect( $diffYears, $instanceYears ) ) !== count( $instanceYears ) )
			{
				$omittedYears = implode( ",", $diffYears );
				$this->log()->info( "Some of the years ($omittedYears) in instance {$instance->getDefaultCurrency()} do not overlap with other documents included in this report" );
			}

			$existingYears = array_intersect_key( $existingYears, $instanceYears );

		}

		if ( ! $existingYears )
		{
			$message = $this->validYears
				? "There are no years in the instance documents selected that match the valid years you have chosen"
				: "The instance documents you have selected contain no data.";
			throw new Exception( $message );
		}

		// Make sure only $existingYears exist in $this->years
		$this->years = array_intersect_key( $this->years, $existingYears );

		parent::preparePresentation( $prune );
	}

}

?>