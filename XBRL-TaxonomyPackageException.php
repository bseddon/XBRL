<?php

/**
 * XBRL Taxonomy Package
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright ( C ) 2017 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * ( at your option ) any later version.
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
 * General exception class for taxonomy package handling
 */
class XBRL_TaxonomyPackageException extends \Exception
{
	/**
	 * QName of the error code being reported
	 * @var string $error
	 */
	public $error;

	/**
	 * Create an exception type with a taxonomy package error
	 * @param string QName of the error code being reported
	 * @param string $message
	 * @return \XBRL\Formulas\Exceptions\FormulasException
	 */
	public static function withError( $error, $message )
	{
		$ex = new XBRL_TaxonomyPackageException( $message );
		$ex->error = $error;

		return $ex;
	}
}