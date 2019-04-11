<?php

/**
 * US GAAP 2015 taxonomy implementation
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
require_once('XBRL.php');

/**
 * Define the namespaces of the entry points supported by this taxonomy
 * @var array
 */
$entrypoint_namespaces_2014 = array(
	XBRL_US_GAAP_2015::$us_GAAP_2014_ENTRY_POINT_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2014_ENTRY_POINT_STD_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2014_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2014_NS,
	// XBRL_US_GAAP_2015::$us_GAAP_2014_ROLES_NS,
);

$entrypoint_namespaces_2015 = array(
	XBRL_US_GAAP_2015::$us_GAAP_2015_ENTRY_POINT_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2015_ENTRY_POINT_STD_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2015_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2015_NS,
	// XBRL_US_GAAP_2015::$us_GAAP_2015_ROLES_NS,
);

$entrypoint_namespaces_2016 = array(
	XBRL_US_GAAP_2015::$us_GAAP_2016_ENTRY_POINT_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2016_ENTRY_POINT_STD_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2016_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2016_NS,
	// XBRL_US_GAAP_2015::$us_GAAP_2016_ROLES_NS,
);

$entrypoint_namespaces_2017 = array(
	XBRL_US_GAAP_2015::$us_GAAP_2017_ENTRY_POINT_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2017_ENTRY_POINT_STD_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2017_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2017_NS,
	// XBRL_US_GAAP_2015::$us_GAAP_2017_ROLES_NS,
);

$entrypoint_namespaces_2018 = array(
	XBRL_US_GAAP_2015::$us_GAAP_2018_ENTRY_POINT_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2018_ENTRY_POINT_STD_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2018_ALL_NS,
	XBRL_US_GAAP_2015::$us_GAAP_2018_NS,
	// XBRL_US_GAAP_2015::$us_GAAP_2018_ROLES_NS,
);

XBRL::add_namespace_to_class_map_entries( $entrypoint_namespaces_2014, "XBRL_US_GAAP_2015" );
XBRL::add_namespace_to_class_map_entries( $entrypoint_namespaces_2015, "XBRL_US_GAAP_2015" );
XBRL::add_namespace_to_class_map_entries( $entrypoint_namespaces_2016, "XBRL_US_GAAP_2015" );
XBRL::add_namespace_to_class_map_entries( $entrypoint_namespaces_2017, "XBRL_US_GAAP_2015" );
XBRL::add_namespace_to_class_map_entries( $entrypoint_namespaces_2018, "XBRL_US_GAAP_2015" );

/**
 * Register xsd to class map entries
 *
 * This call defines the namespaces that apply to the use of the XBRL decendent class defined in this file.
 * The static function XBRL::withtaxonomy() will use the information provided by this call to instantiate
 * the correct (this) class.
 */
XBRL::add_namespace_to_class_map_entries( array_merge( array(
	"http://fasb.org/stm/com/2015-01-31",
	"http://fasb.org/stm/sfp-ucreo/2015-01-31",
	"http://fasb.org/stm/sfp-clreo/2015-01-31",
	"http://fasb.org/stm/soi-egm/2015-01-31",
	"http://fasb.org/stm/soi-re/2015-01-31",
	"http://fasb.org/stm/soi-reit/2015-01-31",
	"http://fasb.org/stm/scf-re/2015-01-31",
	"http://fasb.org/stm/sheci/2015-01-31",
	"http://fasb.org/stm/soi-indira/2015-01-31",
	"http://fasb.org/stm/soi/2015-01-31",
	"http://fasb.org/stm/sfp-cls/2015-01-31",
	"http://fasb.org/stm/scf-dir/2015-01-31",
	"http://fasb.org/stm/scf-indir/2015-01-31",
	"http://fasb.org/stm/spc/2015-01-31",
	"http://fasb.org/stm/soc/2015-01-31",
	"http://fasb.org/stm/soi-int/2015-01-31",
	"http://fasb.org/stm/sfp-dbo/2015-01-31",
	"http://fasb.org/stm/scf-dbo/2015-01-31",
	"http://fasb.org/stm/soi-ins/2015-01-31",
	"http://fasb.org/stm/sfp-ibo/2015-01-31",
	"http://fasb.org/stm/scf-inv/2015-01-31",
	"http://fasb.org/stm/soi-sbi/2015-01-31",
	"http://fasb.org/stm/sfp-sbo/2015-01-31",
	"http://fasb.org/stm/scf-sbo/2015-01-31",
	"http://fasb.org/stm/scf-indira/2015-01-31",
	"http://fasb.org/stm/scf-sd/2015-01-31",
	"http://fasb.org/dis/bsoff/2015-01-31",
	"http://fasb.org/dis/schedoi-hold/2015-01-31",
	"http://fasb.org/dis/schedoi-shorthold/2015-01-31",
	"http://fasb.org/dis/schedoi-sumhold/2015-01-31",
	"http://fasb.org/dis/schedoi-oocw/2015-01-31",
	"http://fasb.org/dis/schedoi-iiaa/2015-01-31",
	"http://fasb.org/dis/schedoi-otsh/2015-01-31",
	"http://fasb.org/dis/schedoi-fednote/2015-01-31",
	"http://fasb.org/dis/fs-interest/2015-01-31",
	"http://fasb.org/dis/sec-cndfir/2015-01-31",
	"http://fasb.org/dis/lea/2015-01-31",
	"http://fasb.org/dis/fs-fhlb/2015-01-31",
	"http://fasb.org/dis/ctbl/2015-01-31",
	"http://fasb.org/dis/ru/2015-01-31",
	"http://fasb.org/dis/cce/2015-01-31",
	"http://fasb.org/dis/inv/2015-01-31",
	"http://fasb.org/dis/iago/2015-01-31",
	"http://fasb.org/dis/pay/2015-01-31",
	"http://fasb.org/dis/aro/2015-01-31",
	"http://fasb.org/dis/ocpfs/2015-01-31",
	"http://fasb.org/dis/acec/2015-01-31",
	"http://fasb.org/dis/ir/2015-01-31",
	"http://fasb.org/dis/ap/2015-01-31",
	"http://fasb.org/dis/rlnro/2015-01-31",
	"http://fasb.org/dis/ides/2015-01-31",
	"http://fasb.org/dis/emjv/2015-01-31",
	"http://fasb.org/dis/iaoi/2015-01-31",
	"http://fasb.org/dis/dccpoa/2015-01-31",
	"http://fasb.org/dis/ppe/2015-01-31",
	"http://fasb.org/dis/ero/2015-01-31",
	"http://fasb.org/dis/edco/2015-01-31",
	"http://fasb.org/dis/dr/2015-01-31",
	"http://fasb.org/dis/cc/2015-01-31",
	"http://fasb.org/dis/guarantees/2015-01-31",
	"http://fasb.org/dis/debt/2015-01-31",
	"http://fasb.org/dis/othliab/2015-01-31",
	"http://fasb.org/dis/ni/2015-01-31",
	"http://fasb.org/dis/te/2015-01-31",
	"http://fasb.org/dis/equity/2015-01-31",
	"http://fasb.org/dis/crcgen/2015-01-31",
	"http://fasb.org/dis/crcsbp/2015-01-31",
	"http://fasb.org/dis/crcrb/2015-01-31",
	"http://fasb.org/dis/crcpb/2015-01-31",
	"http://fasb.org/dis/otherexp/2015-01-31",
	"http://fasb.org/dis/rd/2015-01-31",
	"http://fasb.org/dis/inctax/2015-01-31",
	"http://fasb.org/dis/disops/2015-01-31",
	"http://fasb.org/dis/eui/2015-01-31",
	"http://fasb.org/dis/eps/2015-01-31",
	"http://fasb.org/dis/sr/2015-01-31",
	"http://fasb.org/dis/bc/2015-01-31",
	"http://fasb.org/dis/reorg/2015-01-31",
	"http://fasb.org/dis/diha/2015-01-31",
	"http://fasb.org/dis/fifvd/2015-01-31",
	"http://fasb.org/dis/foct/2015-01-31",
	"http://fasb.org/dis/nt/2015-01-31",
	"http://fasb.org/dis/rpd/2015-01-31",
	"http://fasb.org/dis/ts/2015-01-31",
	"http://fasb.org/dis/se/2015-01-31",
	"http://fasb.org/dis/con/2015-01-31",
	"http://fasb.org/dis/fs-bt/2015-01-31",
	"http://fasb.org/dis/fs-bd/2015-01-31",
	"http://fasb.org/dis/fs-ins/2015-01-31",
	"http://fasb.org/dis/fs-mort/2015-01-31",
	"http://fasb.org/dis/hco/2015-01-31",
	"http://fasb.org/dis/ei/2015-01-31",
	"http://fasb.org/dis/re/2015-01-31",
	"http://fasb.org/dis/regop/2015-01-31",
	"http://fasb.org/dis/sec-vq/2015-01-31",
	"http://fasb.org/dis/sec-re/2015-01-31",
	"http://fasb.org/dis/sec-mort/2015-01-31",
	"http://fasb.org/dis/sec-sum/2015-01-31",
	"http://fasb.org/dis/sec-supins/2015-01-31",
	"http://fasb.org/dis/sec-reins/2015-01-31",
	"http://fasb.org/dis/sec-suppc/2015-01-31",
	"http://fasb.org/dis/oi/2015-01-31",
	"http://fasb.org/codification-part/2015-01-31",
	"http://fasb.org/legacy-part/2015-01-31",
	"http://fasb.org/us-types/2015-01-31",
	"http://xbrl.sec.gov/country-ent-all/2013-01-31",
	"http://xbrl.sec.gov/currency-all/2014-01-31",
	"http://xbrl.sec.gov/dei/2014-01-31",
	"http://xbrl.sec.gov/dei-all/2014-01-31",
	"http://xbrl.sec.gov/dei-std/2014-01-31",
	"http://xbrl.sec.gov/exch-ent-all/2015-01-31",
	"http://xbrl.sec.gov/invest/2013-01-31",
	"http://xbrl.sec.gov/invest-all/2013-01-31",
	"http://xbrl.sec.gov/invest-std/2013-01-31",
	"http://www.xbrl.org/2009/role/deprecated",
	"http://xbrl.sec.gov/country-ent-std/2013-01-31",
	"http://xbrl.sec.gov/country-std/2013-01-31",
	"http://xbrl.sec.gov/country/2013-01-31",
	"http://xbrl.sec.gov/country-all/2013-01-31",
	"http://xbrl.sec.gov/exch-all/2015-01-31",
	"http://xbrl.sec.gov/exch-std/2015-01-31",
	"http://xbrl.sec.gov/exch-ent-std/2015-01-31",
	"http://xbrl.sec.gov/currency-std/2014-01-31",
	"http://xbrl.sec.gov/currency/2014-01-31",
	"http://xbrl.sec.gov/exch/2015-01-31",
), $entrypoint_namespaces_2015 ), "XBRL_US_GAAP_2015" );
XBRL::add_entry_namespace_to_class_map_entries( $entrypoint_namespaces_2015, "XBRL_US_GAAP_2015" );

XBRL::add_namespace_to_class_map_entries( array_merge( array(
	"http://fasb.org/stm/com/2015-01-31",
	"http://fasb.org/stm/sfp-ucreo/2015-01-31",
	"http://fasb.org/stm/sfp-clreo/2015-01-31",
	"http://fasb.org/stm/soi-egm/2015-01-31",
	"http://fasb.org/stm/soi-re/2015-01-31",
	"http://fasb.org/stm/soi-reit/2015-01-31",
	"http://fasb.org/stm/scf-re/2015-01-31",
	"http://fasb.org/stm/sheci/2015-01-31",
	"http://fasb.org/stm/soi-indira/2015-01-31",
	"http://fasb.org/stm/soi/2015-01-31",
	"http://fasb.org/stm/sfp-cls/2015-01-31",
	"http://fasb.org/stm/scf-dir/2015-01-31",
	"http://fasb.org/stm/scf-indir/2015-01-31",
	"http://fasb.org/stm/spc/2015-01-31",
	"http://fasb.org/stm/soc/2015-01-31",
	"http://fasb.org/stm/soi-int/2015-01-31",
	"http://fasb.org/stm/sfp-dbo/2015-01-31",
	"http://fasb.org/stm/scf-dbo/2015-01-31",
	"http://fasb.org/stm/soi-ins/2015-01-31",
	"http://fasb.org/stm/sfp-ibo/2015-01-31",
	"http://fasb.org/stm/scf-inv/2015-01-31",
	"http://fasb.org/stm/soi-sbi/2015-01-31",
	"http://fasb.org/stm/sfp-sbo/2015-01-31",
	"http://fasb.org/stm/scf-sbo/2015-01-31",
	"http://fasb.org/stm/scf-indira/2015-01-31",
	"http://fasb.org/stm/scf-sd/2015-01-31",
	"http://fasb.org/dis/bsoff/2015-01-31",
	"http://fasb.org/dis/schedoi-hold/2015-01-31",
	"http://fasb.org/dis/schedoi-shorthold/2015-01-31",
	"http://fasb.org/dis/schedoi-sumhold/2015-01-31",
	"http://fasb.org/dis/schedoi-oocw/2015-01-31",
	"http://fasb.org/dis/schedoi-iiaa/2015-01-31",
	"http://fasb.org/dis/schedoi-otsh/2015-01-31",
	"http://fasb.org/dis/schedoi-fednote/2015-01-31",
	"http://fasb.org/dis/fs-interest/2015-01-31",
	"http://fasb.org/dis/sec-cndfir/2015-01-31",
	"http://fasb.org/dis/lea/2015-01-31",
	"http://fasb.org/dis/fs-fhlb/2015-01-31",
	"http://fasb.org/dis/ctbl/2015-01-31",
	"http://fasb.org/dis/ru/2015-01-31",
	"http://fasb.org/dis/cce/2015-01-31",
	"http://fasb.org/dis/inv/2015-01-31",
	"http://fasb.org/dis/iago/2015-01-31",
	"http://fasb.org/dis/pay/2015-01-31",
	"http://fasb.org/dis/aro/2015-01-31",
	"http://fasb.org/dis/ocpfs/2015-01-31",
	"http://fasb.org/dis/acec/2015-01-31",
	"http://fasb.org/dis/ir/2015-01-31",
	"http://fasb.org/dis/ap/2015-01-31",
	"http://fasb.org/dis/rlnro/2015-01-31",
	"http://fasb.org/dis/ides/2015-01-31",
	"http://fasb.org/dis/emjv/2015-01-31",
	"http://fasb.org/dis/iaoi/2015-01-31",
	"http://fasb.org/dis/dccpoa/2015-01-31",
	"http://fasb.org/dis/ppe/2015-01-31",
	"http://fasb.org/dis/ero/2015-01-31",
	"http://fasb.org/dis/edco/2015-01-31",
	"http://fasb.org/dis/dr/2015-01-31",
	"http://fasb.org/dis/cc/2015-01-31",
	"http://fasb.org/dis/guarantees/2015-01-31",
	"http://fasb.org/dis/debt/2015-01-31",
	"http://fasb.org/dis/othliab/2015-01-31",
	"http://fasb.org/dis/ni/2015-01-31",
	"http://fasb.org/dis/te/2015-01-31",
	"http://fasb.org/dis/equity/2015-01-31",
	"http://fasb.org/dis/crcgen/2015-01-31",
	"http://fasb.org/dis/crcsbp/2015-01-31",
	"http://fasb.org/dis/crcrb/2015-01-31",
	"http://fasb.org/dis/crcpb/2015-01-31",
	"http://fasb.org/dis/otherexp/2015-01-31",
	"http://fasb.org/dis/rd/2015-01-31",
	"http://fasb.org/dis/inctax/2015-01-31",
	"http://fasb.org/dis/disops/2015-01-31",
	"http://fasb.org/dis/eui/2015-01-31",
	"http://fasb.org/dis/eps/2015-01-31",
	"http://fasb.org/dis/sr/2015-01-31",
	"http://fasb.org/dis/bc/2015-01-31",
	"http://fasb.org/dis/reorg/2015-01-31",
	"http://fasb.org/dis/diha/2015-01-31",
	"http://fasb.org/dis/fifvd/2015-01-31",
	"http://fasb.org/dis/foct/2015-01-31",
	"http://fasb.org/dis/nt/2015-01-31",
	"http://fasb.org/dis/rpd/2015-01-31",
	"http://fasb.org/dis/ts/2015-01-31",
	"http://fasb.org/dis/se/2015-01-31",
	"http://fasb.org/dis/con/2015-01-31",
	"http://fasb.org/dis/fs-bt/2015-01-31",
	"http://fasb.org/dis/fs-bd/2015-01-31",
	"http://fasb.org/dis/fs-ins/2015-01-31",
	"http://fasb.org/dis/fs-mort/2015-01-31",
	"http://fasb.org/dis/hco/2015-01-31",
	"http://fasb.org/dis/ei/2015-01-31",
	"http://fasb.org/dis/re/2015-01-31",
	"http://fasb.org/dis/regop/2015-01-31",
	"http://fasb.org/dis/sec-vq/2015-01-31",
	"http://fasb.org/dis/sec-re/2015-01-31",
	"http://fasb.org/dis/sec-mort/2015-01-31",
	"http://fasb.org/dis/sec-sum/2015-01-31",
	"http://fasb.org/dis/sec-supins/2015-01-31",
	"http://fasb.org/dis/sec-reins/2015-01-31",
	"http://fasb.org/dis/sec-suppc/2015-01-31",
	"http://fasb.org/dis/oi/2015-01-31",
	"http://fasb.org/codification-part/2014-01-31",
	"http://fasb.org/legacy-part/2014-01-31",
	"http://xbrl.sec.gov/country-ent-all/2013-01-31",
	"http://xbrl.sec.gov/currency-all/2014-01-31",
	"http://xbrl.sec.gov/dei/2014-01-31",
	"http://xbrl.sec.gov/dei-all/2014-01-31",
	"http://xbrl.sec.gov/dei-std/2014-01-31",
	"http://xbrl.sec.gov/exch-ent-all/2015-01-31",
	"http://xbrl.sec.gov/invest/2013-01-31",
	"http://xbrl.sec.gov/invest-all/2013-01-31",
	"http://xbrl.sec.gov/invest-std/2013-01-31",
	"http://xbrl.sec.gov/country-ent-std/2013-01-31",
	"http://xbrl.sec.gov/country-std/2013-01-31",
	"http://xbrl.sec.gov/country/2013-01-31",
	"http://xbrl.sec.gov/country-all/2013-01-31",
	"http://xbrl.sec.gov/exch-all/2014-01-31",
	"http://xbrl.sec.gov/exch-std/2014-01-31",
	"http://xbrl.sec.gov/exch-ent-std/2014-01-31",
	"http://xbrl.sec.gov/currency/2014-01-31",
	"http://xbrl.sec.gov/exch/2014-01-31",
	"http://fasb.org/us-types/2014-01-31",
	"ttp://xbrl.sec.gov/stpr/2011-01-31",
), $entrypoint_namespaces_2014 ), "XBRL_US_GAAP_2015" );
XBRL::add_entry_namespace_to_class_map_entries( $entrypoint_namespaces_2014, "XBRL_US_GAAP_2014" );
XBRL::add_entry_namespace_to_class_map_entries( $entrypoint_namespaces_2016, "XBRL_US_GAAP_2015" );
XBRL::add_entry_namespace_to_class_map_entries( $entrypoint_namespaces_2017, "XBRL_US_GAAP_2015" );
XBRL::add_entry_namespace_to_class_map_entries( $entrypoint_namespaces_2018, "XBRL_US_GAAP_2015" );

/**
 * Register XSD to compiled taxonomy entries
 */
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-entryPoint-all-2018-01-31.xsd" ), "us-gaap-entire-2018-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-all-2018-01-31.xsd", "us-gaap-2018-01-31.xsd" ), "us-gaap-all-2018-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-2018-01-31.xsd"), "us-gaap-2018-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-std-2018-01-31.xsd"), "us-gaap-std-2018-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-ent-std-2018-01-31.xsd"), "us-gaap-ent-std-2018-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-ent-all-2018-01-31.xsd"), "us-gaap-ent-all-2018-01-31" );

XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-entryPoint-all-2017-01-31.xsd" ), "us-gaap-entire-2017-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-all-2017-01-31.xsd", "us-gaap-2017-01-31.xsd" ), "us-gaap-all-2017-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-2017-01-31.xsd"), "us-gaap-2017-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-std-2017-01-31.xsd"), "us-gaap-std-2017-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-ent-std-2017-01-31.xsd"), "us-gaap-ent-std-2017-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-ent-all-2017-01-31.xsd"), "us-gaap-ent-all-2017-01-31" );

XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-entryPoint-all-2016-01-31.xsd" ), "us-gaap-entire-2016-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-all-2016-01-31.xsd", "us-gaap-2016-01-31.xsd" ), "us-gaap-all-2016-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-2016-01-31.xsd"), "us-gaap-2016-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-std-2016-01-31.xsd"), "us-gaap-std-2016-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-ent-std-2016-01-31.xsd"), "us-gaap-ent-std-2016-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-ent-all-2016-01-31.xsd"), "us-gaap-ent-all-2016-01-31" );

XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-entryPoint-all-2015-01-31.xsd" ), "us-gaap-entire-2015-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-all-2015-01-31.xsd", "us-gaap-2015-01-31.xsd" ), "us-gaap-all-2015-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-2015-01-31.xsd"), "us-gaap-2015-01-31" );

XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-entryPoint-all-2014-01-31.xsd" ), "us-gaap-entire-2014-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-all-2014-01-31.xsd", "us-gaap-2014-01-31.xsd" ), "us-gaap-all-2014-01-31" );
XBRL::add_xsd_to_compiled_map_entries( array( "us-gaap-2014-01-31.xsd"), "us-gaap-2014-01-31" );

/**
 * Implements an XBRL descendent for the US GAAP taxonomy.
 * @author Bill Seddon
 */
class XBRL_US_GAAP_2015 extends XBRL
{

	/**
	 * http://fasb.org/us-gaap-entryPoint-all/2015-01-31
	 * @var string
	 */
	public static $us_GAAP_2015_ENTRY_POINT_ALL_NS	= "http://fasb.org/us-gaap-entryPoint-all/2015-01-31";
	/**
	 * http://fasb.org/us-gaap-entryPoint-std/2015-01-31
	 * @var string
	 */
	public static $us_GAAP_2015_ENTRY_POINT_STD_NS	= "http://fasb.org/us-gaap-entryPoint-std/2015-01-31";
	/**
	 * http://fasb.org/us-gaap-all/2015-01-31
	 * @var string
	 */
	public static $us_GAAP_2015_ALL_NS				= "http://fasb.org/us-gaap-all/2015-01-31";
	/**
	 * http://fasb.org/us-gaap/2015-01-31
	 * @var string
	 */
	public static $us_GAAP_2015_NS					= "http://fasb.org/us-gaap/2015-01-31";
	/**
	 * http://fasb.org/us-roles/2015-01-31
	 * @var string
	 */
	public static $us_GAAP_2015_ROLES_NS			= "http://fasb.org/us-roles/2015-01-31";

	/**
	 * http://fasb.org/us-gaap-entryPoint-all/2013-01-31
	 * @var string
	 */
	public static $us_GAAP_2013_ENTRY_POINT_ALL_NS	= "http://fasb.org/us-gaap-entryPoint-all/2013-01-31";
	/**
	 * http://fasb.org/us-gaap-entryPoint-std/2013-01-31
	 * @var string
	 */
	public static $us_GAAP_2013_ENTRY_POINT_STD_NS	= "http://fasb.org/us-gaap-entryPoint-std/2013-01-31";
	/**
	 * http://fasb.org/us-gaap-all/2013-01-31
	 * @var string
	 */
	public static $us_GAAP_2013_ALL_NS				= "http://fasb.org/us-gaap-all/2013-01-31";
	/**
	 * http://fasb.org/us-gaap/2014-01-31
	 * @var string
	 */
	public static $us_GAAP_2013_NS					= "http://fasb.org/us-gaap/2013-01-31";
	/**
	 * http://fasb.org/us-roles/2014-01-31
	 * @var string
	 */
	public static $us_GAAP_2013_ROLES_NS			= "http://fasb.org/us-roles/2013-01-31";

	/**
	 * http://fasb.org/us-gaap-entryPoint-all/2014-01-31
	 * @var string
	 */
	public static $us_GAAP_2014_ENTRY_POINT_ALL_NS	= "http://fasb.org/us-gaap-entryPoint-all/2014-01-31";
	/**
	 * http://fasb.org/us-gaap-entryPoint-std/2014-01-31
	 * @var string
	 */
	public static $us_GAAP_2014_ENTRY_POINT_STD_NS	= "http://fasb.org/us-gaap-entryPoint-std/2014-01-31";
	/**
	 * http://fasb.org/us-gaap-all/2014-01-31
	 * @var string
	 */
	public static $us_GAAP_2014_ALL_NS				= "http://fasb.org/us-gaap-all/2014-01-31";
	/**
	 * http://fasb.org/us-gaap/2014-01-31
	 * @var string
	 */
	public static $us_GAAP_2014_NS					= "http://fasb.org/us-gaap/2014-01-31";
	/**
	 * http://fasb.org/us-roles/2014-01-31
	 * @var string
	 */
	public static $us_GAAP_2014_ROLES_NS			= "http://fasb.org/us-roles/2014-01-31";

	/**
	 * http://fasb.org/us-gaap-entryPoint-all/2016-01-31
	 * @var string
	 */
	public static $us_GAAP_2016_ENTRY_POINT_ALL_NS	= "http://fasb.org/us-gaap-entryPoint-all/2016-01-31";
	/**
	 * http://fasb.org/us-gaap-entryPoint-std/2016-01-31
	 * @var string
	 */
	public static $us_GAAP_2016_ENTRY_POINT_STD_NS	= "http://fasb.org/us-gaap-entryPoint-std/2016-01-31";
	/**
	 * http://fasb.org/us-gaap-all/2016-01-31
	 * @var string
	 */
	public static $us_GAAP_2016_ALL_NS				= "http://fasb.org/us-gaap-all/2016-01-31";
	/**
	 * http://fasb.org/us-gaap/2016-01-31
	 * @var string
	 */
	public static $us_GAAP_2016_NS					= "http://fasb.org/us-gaap/2016-01-31";
	/**
	 * http://fasb.org/us-roles/2016-01-31
	 * @var string
	 */
	public static $us_GAAP_2016_ROLES_NS			= "http://fasb.org/us-roles/2016-01-31";


	/**
	 * http://fasb.org/us-gaap-entryPoint-all/2017-01-31
	 * @var string
	 */
	public static $us_GAAP_2017_ENTRY_POINT_ALL_NS	= "http://fasb.org/us-gaap-entryPoint-all/2017-01-31";
	/**
	 * http://fasb.org/us-gaap-entryPoint-std/2017-01-31
	 * @var string
	 */
	public static $us_GAAP_2017_ENTRY_POINT_STD_NS	= "http://fasb.org/us-gaap-entryPoint-std/2017-01-31";
	/**
	 * http://fasb.org/us-gaap-all/2017-01-31
	 * @var string
	 */
	public static $us_GAAP_2017_ALL_NS				= "http://fasb.org/us-gaap-all/2017-01-31";
	/**
	 * http://fasb.org/us-gaap/2017-01-31
	 * @var string
	 */
	public static $us_GAAP_2017_NS					= "http://fasb.org/us-gaap/2017-01-31";
	/**
	 * http://fasb.org/us-roles/2017-01-31
	 * @var string
	 */
	public static $us_GAAP_2017_ROLES_NS			= "http://fasb.org/us-roles/2017-01-31";


	/**
	 * http://fasb.org/us-gaap-entryPoint-all/2018-01-31
	 * @var string
	 */
	public static $us_GAAP_2018_ENTRY_POINT_ALL_NS	= "http://fasb.org/us-gaap-entryPoint-all/2018-01-31";
	/**
	 * http://fasb.org/us-gaap-entryPoint-std/2018-01-31
	 * @var string
	 */
	public static $us_GAAP_2018_ENTRY_POINT_STD_NS	= "http://fasb.org/us-gaap-entryPoint-std/2018-01-31";
	/**
	 * http://fasb.org/us-gaap-all/2018-01-31
	 * @var string
	 */
	public static $us_GAAP_2018_ALL_NS				= "http://fasb.org/us-gaap-all/2018-01-31";
	/**
	 * http://fasb.org/us-gaap/2018-01-31
	 * @var string
	 */
	public static $us_GAAP_2018_NS					= "http://fasb.org/us-gaap/2018-01-31";
	/**
	 * http://fasb.org/us-roles/2018-01-31
	 * @var string
	 */
	public static $us_GAAP_2018_ROLES_NS			= "http://fasb.org/us-roles/2018-01-31";


	/**
	 * An array of element ids that when they appear in a report their values should be treated as text.
	 * This has a specific meaning in the default report: the associated values are not shown tied to a
	 * specific financial year.
	 * @var array[string]
	 */
	private static $textItems = array(
		"dei_AccountingAddressMember",
		"dei_AccountingContactMember",
		"dei_AccountingFaxMember",
		"dei_AccountingPhoneMember",
		"dei_EntityAddressAddressDescription",
		"dei_EntityAddressAddressLine1",
		"dei_EntityAddressAddressLine2",
		"dei_EntityAddressAddressLine3",
		"dei_EntityAddressesAddressTypeAxis",
		"dei_AddressTypeDomain",
		"dei_AmendmentDescription",
		"dei_AmendmentFlag",
		"dei_BusinessContactMember",
		"dei_CityAreaCode",
		"dei_EntityAddressCityOrTown",
		"dei_ContactAddressMember",
		"dei_ContactFaxMember",
		"dei_EntityContactPersonnelContactPersonTypeAxis",
		"dei_ContactPersonnelEmailAddress",
		"dei_ContactPersonnelName",
		"dei_ContactPersonnelUniformResourceLocatorURL",
		"dei_ContactPhoneMember",
		"dei_ContainedFileInformationFileDescription",
		"dei_ContainedFileInformationFileName",
		"dei_ContainedFileInformationFileType",
		"dei_EntityAddressCountry",
		"dei_CountryRegion",
		"dei_CurrentFiscalYearEndDate",
		"dei_DocumentContactMember",
		"dei_DocumentCreationDate",
		"dei_DocumentDescription",
		"dei_DocumentEffectiveDate",
		"dei_DocumentInformationDocumentAxis",
		"dei_DocumentDomain",
		"dei_DocumentInformationLineItems",
		"dei_DocumentInformationTable",
		"dei_DocumentInformationTextBlock",
		"dei_DocumentName",
		"dei_DocumentPeriodEndDate",
		"dei_DocumentPeriodStartDate",
		"dei_EntitiesTable",
		"dei_EntityTextBlock",
		"dei_EntityAddressesLineItems",
		"dei_EntityAddressesTable",
		"dei_ContactPersonTypeDomain",
		"dei_EntityContactPersonnelLineItems",
		"dei_EntityContactPersonnelTable",
		// "dei_EntityDomain",
		"dei_EntityOtherIdentificationValue",
		"dei_EntityIncorporationDateOfIncorporation",
		"dei_EntityIncorporationStateCountryName",
		"dei_EntityInformationDateToChangeFormerLegalOrRegisteredName",
		"dei_EntityInformationFormerLegalOrRegisteredName",
		"dei_EntityInformationLineItems",
		"dei_EntityListingsTable",
		"dei_EntityPhoneFaxNumbersTable",
		"dei_EntitySectorIndustryClassificationsTable",
		"dei_Extension",
		"dei_FormerFiscalYearEndDate",
		"dei_GeneralFaxMember",
		"dei_GeneralPhoneMember",
		"dei_HumanResourcesContactMember",
		"dei_InvestorRelationsContactMember",
		"dei_InvestorRelationsFaxMember",
		"dei_InvestorRelationsPhoneMember",
		"dei_LegalAddressMember",
		"dei_LegalContactMember",
		"dei_LegalFaxMember",
		"dei_LegalPhoneMember",
		"dei_EntityListingsExchangeAxis",
		"dei_LocalPhoneNumber",
		"dei_MailingAddressMember",
		"dei_OtherAddressMember",
		"dei_PhoneFaxNumberDescription",
		"dei_PhoneFaxNumberTypeDomain",
		"dei_EntityAddressPostalZipCode",
		"dei_PrincipalAddressMember",
		"dei_EntityAddressRegion",
		"dei_EntityAddressStateOrProvince",
		"dei_TradingSymbol",
		"dei_EntityPhoneFaxNumbersLineItems",
		"dei_EntityListingsLineItems",
		"dei_EntitySectorIndustryClassificationsLineItems",
		"dei_EntitySectorIndustryClassificationsSectorAxis",
		"dei_EntityPhoneFaxNumbersPhoneFaxNumberTypeAxis",
		"dei_EntitySectorIndustryClassificationPrimary",
		"dei_EntityNorthAmericanIndustryClassificationsTable",
		"dei_EntityNorthAmericanIndustryClassificationsIndustryAxis",
		"dei_EntityNorthAmericanIndustryClassificationsLineItems",
		"dei_EntityNorthAmericanIndustryClassificationPrimary",
		"dei_ContainedFileInformationFileNumber",
		"dei_EntityWellKnownSeasonedIssuer",
		"dei_EntityVoluntaryFilers",
		"dei_EntityCurrentReportingStatus",
		"dei_EntityFilerCategory",
		"dei_EntityPublicFloat",
		"dei_EntityRegistrantName",
		"dei_EntityCentralIndexKey",
		"dei_EntityTaxIdentificationNumber",
		"dei_EntityDataUniversalNumberingSystemNumber",
		"dei_EntityOtherIdentificationType",
		"dei_EntityCommonStockSharesOutstanding",
		"dei_EntityListingPrimary",
		"dei_EntityListingDescription",
		"dei_EntityListingsInstrumentAxis",
		"dei_InstrumentDomain",
		"dei_EntityLegalForm",
		"dei_ParentEntityLegalName",
		"dei_EntityAccountingStandard",
		"dei_EntityHomeCountryISOCode",
		"dei_EntityReportingCurrencyISOCode",
		"dei_EntityListingSecurityTradingCurrency",
		"dei_EntityListingParValuePerShare",
		"dei_EntityListingForeign",
		"dei_EntityListingDepositoryReceiptRatio",
		"dei_DocumentTitle",
		"dei_DocumentSubtitle",
		"dei_DocumentSynopsis",
		"dei_DocumentFiscalYearFocus",
		"dei_DocumentFiscalPeriodFocus",
		"dei_DocumentVersion",
		"dei_DocumentCopyrightInformation",
		"dei_EntityLocationTable",
		"dei_EntityByLocationAxis",
		"dei_LocationDomain",
		"dei_EntityLocationPrimary",
		"dei_EntityLocationLineItems",
		"dei_SectorDomain",
		"dei_NAICSDomain",
		"dei_ExchangeDomain",
		"dei_LegalEntityAxis",
		"dei_DocumentType",
		"dei_DeprecatedItemsForDEI",
		"dei_EntityNumberOfEmployees",
		"dei_ApproximateDateOfCommencementOfProposedSaleToThePublic",
		"dei_PostEffectiveAmendmentNumber",
		"dei_PreEffectiveAmendmentNumber",
		"dei_RegistrationStatementAmendmentNumber",
		"dei_UTR",
		"dei_LegalEntityIdentifier",
	);

	/**
	 * Elements for which the value should be used as a label.  Usually used with tuples.
	 * @var array[string]
	 */
	public static $labelItems = array();

	/**
	 * Default contructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Display numbers with accuracy to 100K as millions to 1 decimal place.
		$this->setDisplayRoundingFactor( -5, 6, 'm' );
		$this->setDisplayRoundingFactor( -8, 9, 'b' );
	}

	/**
	 * Get a list of all the members
	 * @param array $dimensionalNode
	 * @return array An array of the member ids
	 */
	private function getValidMembers( $dimensionalNode )
	{
		$result = array();
		if ( ! $dimensionalNode || $dimensionalNode['nodeclass'] !== 'dimensional' )
		{
			return $result;
		}

		if ( $dimensionalNode['taxonomy_element']['type'] === 'uk-types:domainItemType' )
		{
			$result[ $dimensionalNode['taxonomy_element']['id'] ] = true;
		}

		if ( ! isset( $dimensionalNode['children'] ) )
		{
			return $result;
		}

		foreach ( $dimensionalNode['children'] as $nodeKey => $node )
		{
			$result += $this->getValidMembers( $node );
		}

		return $result;
	}

	/**
	 * Provides an opportunity for a descendant class implemenentation to take action when the main taxonomy is loaded
	 */
	public function afterMainTaxonomy()
	{
		// Run the pruning process over the roles taxonomy
		$map = array(
			XBRL_US_GAAP_2015::$us_GAAP_2013_NS => XBRL_US_GAAP_2015::$us_GAAP_2013_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2013_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2013_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2013_ENTRY_POINT_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2013_ROLES_NS,

			XBRL_US_GAAP_2015::$us_GAAP_2014_NS => XBRL_US_GAAP_2015::$us_GAAP_2014_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2014_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2014_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2014_ENTRY_POINT_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2014_ROLES_NS,

			XBRL_US_GAAP_2015::$us_GAAP_2015_NS => XBRL_US_GAAP_2015::$us_GAAP_2015_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2015_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2015_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2015_ENTRY_POINT_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2015_ROLES_NS,

			XBRL_US_GAAP_2015::$us_GAAP_2016_NS => XBRL_US_GAAP_2015::$us_GAAP_2016_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2016_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2016_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2016_ENTRY_POINT_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2016_ROLES_NS,

			XBRL_US_GAAP_2015::$us_GAAP_2017_NS => XBRL_US_GAAP_2015::$us_GAAP_2017_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2017_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2017_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2017_ENTRY_POINT_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2017_ROLES_NS,

			XBRL_US_GAAP_2015::$us_GAAP_2018_NS => XBRL_US_GAAP_2015::$us_GAAP_2018_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2018_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2018_ROLES_NS,
			XBRL_US_GAAP_2015::$us_GAAP_2018_ENTRY_POINT_ALL_NS => XBRL_US_GAAP_2015::$us_GAAP_2018_ROLES_NS,
		);

		/**
		 * @var XBRL $taxonomy
		 */
		$taxonomy = false;
		if ( isset( $map[ $this->getNamespace() ] ) )
		{
			$taxonomy = $this->getTaxonomyForNamespace( $map[ $this->getNamespace() ] );
		}

		if ( ! $taxonomy )
		{
			foreach ( array_unique( array_values( $map ) ) as $namespace => $roleNamespace )
			{
				if ( ! ( $taxonomy = $this->getTaxonomyForNamespace( $roleNamespace ) ) ) continue;
				break;
			}

			if ( ! $taxonomy )
			{
				$this->log()->err( "The Roles schema cannot be located for taxonomy with namespace '{$this->getNamespace()}'" );
				exit;
			}
		}

		// /**
		//  * @var XBRL $taxonomy
		//  */
		// $taxonomy = $this->getTaxonomyForNamespace( XBRL_US_GAAP_2015::$us_GAAP_2015_ROLES_NS );
		// if ( ! $taxonomy )
		// {
		// 	$taxonomy = $this->getTaxonomyForNamespace( XBRL_US_GAAP_2015::$us_GAAP_2014_ROLES_NS );
		// 	if ( ! $taxonomy )
		// 	{
		// 		$this->log()->err( "The Roles schema cannot be located for 2014 or 2015" );
		// 		exit;
		// 	}
		// }

		if ( $this->context->isExtensionTaxonomy() )
		{
			// This is needed because the definitions and presentations are loaded into
			// the roles schema which is loaded early so an additional fixup is required.
			$taxonomy->fixupPresentationHypercubes();
			$taxonomy->fixupDefinitionRoles(); // Mainly adds a 'paths' index to the 'hierarchy' element of each role.
		}

		$this->context->locale = 'en_US';
		$taxonomy->context->locale = 'en_US';
	}

	/**
	 * This function is overridden to add the members to the parent node before it is deleted
	 *
	 * @param array $dimensionalNode A node which has element 'nodeclass' === 'dimensional'
	 * @param array $parentNode The parent node so it can be updated
	 * @return bool True if the dimensional information should be deleted
	 */
	protected function beforeDimensionalPruned( $dimensionalNode, &$parentNode )
	{
		return parent::beforeDimensionalPruned( $dimensionalNode, $parentNode );

		// The dimensional information probably contains valid dimensional information
		// That indicate which members of possible hypercubes are valid for the nodes
		// of the parent.

		$members = $this->getValidMembers( $dimensionalNode );
		if ( count( $members ) )
		{
			$parentNode['members'] = $members;
		}

		return true;
	}

	/**
	 * Import schema is overrridden to import currency and country schemas which are referenced but not imported by us-types-2015-01-31.xsd
	 * {@inheritDoc}
	 * @see XBRL::importSchema()
	 * @param string $schemaLocation The location of the schema file being loaded
	 * @param int $depth (optional) The nesting depth
	 * @param bool $mainSchema True if the schema being loaded is the main DTS schema (entry point)
	 * @return void
	 */
	protected function importSchema( $schemaLocation, $depth = 0, $mainSchema = false )
	{
		if ( in_array( basename( $schemaLocation ), array( "us-types-2014-01-31.xsd", "us-types-2015-01-31.xsd" ) ) )
		{
			parent::importSchema( "http://xbrl.sec.gov/currency/2014/currency-2014-01-31.xsd", $depth );
			parent::importSchema( "http://xbrl.sec.gov/country/2013/country-2013-01-31.xsd", $depth );
			parent::importSchema( "http://xbrl.sec.gov/stpr/2011/stpr-2011-01-31.xsd", $depth );
		}

		parent::importSchema( $schemaLocation, $depth, $mainSchema );
	}

	/**
	 * Provides this implementation an opportunity to provide a list of valid dimension members for a node
	 * Doing this allows the use of elements in an instance document to be disambiguated.
	 * This function will be overridden in descendents
	 * @param array $node The node of the element being processed
	 * @param array $ancestors An array containing the ids of the nodes leading to and including the current node
	 * @return array Returns an empty array
	 */
	public function getValidDimensionMembersForNode( $node, $ancestors )
	{
		return array();
	}

	/**
	 * This function provides an opportunity for a taxonomy to sanitize and/or translate a string
	 *
	 * @param string $text The text to be sanitized
	 * @param string $type An optional element type such as num:integer
	 * @param string $language An optional language locale
	 * @return string The sanitized string
	 */
	public function sanitizeText( $text, $type = null, $language = null )
	{
		$text = preg_replace( "/\[heading\]/", "", $text );
		$text = preg_replace( "/\[Dimension\]/", "", $text );
		$text = preg_replace( "/\[default\]/", "", $text );
		$text = preg_replace( "/\[Abstract\]/", "", $text );
		$text = preg_replace( "/\[Line Items\]/", " - Line items", $text );
		$text = preg_replace( "/\[Table\]/", " - Table", $text );
		$text = preg_replace( "/\[Text Block\]/", "", $text );
		$text = preg_replace( "/\[Axis\]/", "", $text );
		$text = preg_replace( "/\[Member\]/", "", $text );
		$text = preg_replace( "/\[Domain\]/", "", $text );

		$text = rtrim( $text );
		if ( $type !== 'nonnum:textBlockItemType' )
		{
			$text = htmlentities( $text, ENT_COMPAT, 'UTF-8' );
		}

		return  rtrim( $text );
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
		$type = XBRL_Instance::getElementType( $element );

		switch ( $type )
		{
			case 'xbrli:monetaryItemType':
				$element['value'] = str_replace( ',', '', $element['value'] );
				return parent::formattedValue( $element, $instance, $includeCurrency );

			default:
				return parent::formattedValue( $element, $instance, $includeCurrency );
		}
	}

	/**
	 * Return the value of the element after removing any formatting.
	 * @param array $element
	 * @return float
	 * {@inheritDoc}
	 * @see XBRL::removeValueFormatting()
	 */
	public function removeNumberValueFormatting( $element )
	{
		return parent::removeNumberValueFormatting( $element );
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
	 * Overloads the implementation in XBRL
	 * @param string $key The key to lookup to determine whether the row should be excluded
	 * @param string $type The type of the item being tested (defaults to null)
	 * @return boolean
	 */
	public function excludeFromOutput( $key, $type = null )
	{
		if ( $type !== null && $type === 'nonnum:textBlockItemType' ) return true;
		return parent::excludeFromOutput( $key, $type );
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
		if ( $type === 'nonnum:textBlockItemType' ) return true;
		if ( in_array( $key, XBRL_US_GAAP_2015::$textItems ) ) return true;
		return parent::treatAsText( $key, $type );
	}

	/**
	 * Returns true if the element value with the $key is defined as one that should be used as a label - usually in tuple
	 * Can be overridden in a descendent.
	 * @param string $key The key to lookup to determine whether the row should be treated as a label
	 * @return boolean Defaults to false
	 */
	public function treatAsLabel( $key )
	{
		if ( isset( XBRL_US_GAAP_2015::$labelItems[ $key ] ) ) return XBRL_US_GAAP_2015::$labelItems[ $key ];
		return parent::treatAsText( $key );
	}

	/**
	 * Returns an array of locator(s) and corresponding presentation arc(s) that will be substituted for the $from in the $role
	 *
	 * Overrides XBRL to modify the presentation link base hierarchy.
	 *
	 * @param string $roleUri A role Uri to identify the base presentation link base being modified.
	 * @return array An array of locators and links
	 */
	public function getProxyPresentationNodes( $roleUri )
	{
		// See the UK GAAP for an example
		return false;

		if ( ! property_exists( $this, 'proxyPresentationNodes' ) )
		{
		}

		if ( ! is_array( $this->proxyPresentationNodes ) )
		{
			$this->log()->err( "The property 'proxyPresentationNodes' is not an array" );
			return false;
		}

		if ( ! isset( $this->proxyPresentationNodes[ $roleUri ] ) )
			return false;

		return $this->proxyPresentationNodes[ $roleUri ];
	}

	/**
	 * Whether all roles should be used when collecting primary items,
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
		foreach ( $nodes as $nodeKey => $node )
		{
			if ( $this->nodeIsHypercube( $node ) ) return true;

			if ( ! isset( $node['children'] ) || ! count( $node['children'] ) ) continue;
			$result = $this->primaryItemsAllowed( $node['children'], $roleRefKey );
			if ( $result ) return true;
		}

		return false;
	}

	/**
	 * Provides a descendant implementation a chance to define whether or not common hypercubes should be accumulated for a node.
	 * @param array $node An array of presentation hierarchy nodes
	 * @param string $roleRefKey
	 * @return bool True if primary items are allowed (default: true)
	 */
	protected function accumulateCommonHypercubesForNode( $node, $roleRefKey )
	{
		return ! $this->nodeIsHypercube( $node );

		return ! $this->nodeIsHypercube( $node ) &&
			   ! $this->nodeIsLineItemsAxis( $node );
	}

	/**
	 * Return false if the node should not be displayed.  May delegate to the taxonomy instance.
	 * @param array $node
	 * @return bool
	 */
	public function displayNode( $node )
	{
		return ! isset( $node['label'] ) ||
			(
				! $this->nodeIsHypercube( $node ) &&
				! $this->nodeIsLineItemsAxis( $node ) &&
				! $this->nodeIsAxis( $node ) /* &&
				(
					( isset( $node['children'] ) && count( $node['children'] ) ) ||
					( isset( $node['elements'] ) && count( $node['elements'] ) )
				) */
			);
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

		switch ( $namespace )
		{
			case "http://xbrl.sec.gov/dei/2014-01-31":

				$prefix = "dei";
				break;

			case 'http://fasb.org/us-types/2014-01-31':
				$prefix = "us-types";
				break;

			default:
				return parent::valueAlignmentForNamespace( $namespace, $name );
		}

		$type = "$prefix:$name";

		switch ( $type )
		{
			case 'us-types:dateStringItemType':
			default:
				return "left";
		}

	}

	/**
	 * Test whether $node represents a hypercube
	 * @param array $node A node array
	 * @return boolean
	 */
	public function nodeIsHypercube( $node )
	{
		$taxonomyElement = $this->getElementById( $node['label'] );
		if ( ! $taxonomyElement ) return false;
		return $taxonomyElement['substitutionGroup'] === XBRL_Constants::$xbrldtHypercubeItem;
	}

	/**
	 * Test whether $node represents a component axis
	 * @param array $node A node array
	 * @return boolean
	 */
	public function nodeIsAxis( $node )
	{
		return XBRL::endsWith( $node['label'], 'Axis' );
	}

	/**
	 * Test whether $node represents a line items axis
	 * @param array $node A node array
	 * @return boolean
	 */
	public function nodeIsLineItemsAxis( $node )
	{
		return XBRL::endsWith( $node['label'], 'LineItems' );
	}

	/**
	 * Returns a description for an element identified by href
	 * @param string $href  The id of the element for which a description is to be retrieved.  If only the fragment is provided, its assumed to be from the current taxonomy.
	 * @param null|array[string] (optional) $roles If true include the element text in the result.  If the argument is an array it will be an array of preferred labels.
	 * @param null|string $lang (optional) a language locale
	 * @param string $extendedLinkRole (optional) The ELR to apply when calling the parent getTaxonomyDescriptionForId
	 * @return bool|string A description string or false
	 */
	public function getTaxonomyDescriptionForId( $href, $roles = null, $lang = null, $extendedLinkRole = null )
	{
		$result = parent::getTaxonomyDescriptionForId( $href, $roles, $lang, $extendedLinkRole );
		if ( ! $result )
		{
			// Swap the labels with those of the base taxonomy
			if ( parent::swapLabelsFromBackup() )
			{
				// Try looking for the label
				$result = parent::getTaxonomyDescriptionForId( $href, $roles, $lang );

				// Swap the labels back
				parent::swapLabelsFromBackup();
			}

			// Last resort
			if ( ! $result && $this->getBaseTaxonomy() !== null )
			{
				// Swap the labels with those of the base taxonomy
				if ( parent::swapLabelsFromBackup() )
				{
					// Try looking for the label
					$result = parent::getTaxonomyDescriptionForId( $this->getBaseTaxonomy() . '#' . $href, $roles, $lang );

					// Swap the labels back
					parent::swapLabelsFromBackup();
				}
			}

		}

		return $result;
	}
}

?>