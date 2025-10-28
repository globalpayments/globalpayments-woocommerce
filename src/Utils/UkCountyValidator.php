<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * UK County Validator utility class
 * 
 * Handles validation and conversion of UK county names to 3-digit ISO codes
 * for GlobalPayments GpAPI HPP integration.
 * Heavily inspired by CountryUtils class.
 * 
 * @since 1.14.9
 */
class UkCountyValidator {

    /**
     * Significance threshold for fuzzy matching
     */
    const FUZZY_MATCH_THRESHOLD = 4;

    /**
     * UK counties mapped to 3-digit codes
     * 
     * @var array
     */
    private static $uk_counties = [
        'ABERDEEN CITY' => 'ABE',
        'ABERDEENSHIRE' => 'ABD',
        'ANGUS' => 'ANS',
        'ANTRIM AND NEWTOWNABBEY' => 'ANN',
        'ARDS AND NORTH DOWN' => "AND",
        'ARGYLL AND BUTE' => 'AGB',
        'ARMAGH CITY, BANBRIDGE AND CRAIGAVON' => 'ABC',
        'BARKING AND DAGENHAM' => 'BDG',
        'BARNET' => 'BNE',
        'BARNSLEY' => 'BNS',
        'BATH AND NORTH EAST SOMERSET' => 'BAS',
        'BEDFORD' => 'BDF',
        'BELFAST CITY' => 'BFS',
        'BEXLEY' => 'BEX',
        'BIRMINGHAM' => 'BIR',
        'BLACKBURN WITH DARWEN' => 'BBD',
        'BLACKPOOL' => 'BPL',
        'BLAENAU GWENT' => 'BGW',
        'BOLTON' => 'BOL',
        'BOURNEMOUTH, CHRISTCHURCH AND POOLE' => 'BCP',
        'BRACKNELL FOREST' => 'BRC',
        'BRADFORD' => 'BRD',
        'BRENT' => 'BEN',
        'BRIDGEND' => 'BGE',
        'BRIGHTON AND HOVE' => 'BNH',
        'BRISTOL, CITY OF' => 'BST',
        'BROMLEY' => 'BRY',
        'BUCKINGHAMSHIRE' => 'BKM',
        'BURY' => 'BUR',
        'CAERPHILLY' => 'CAY',
        'CALDERDALE' => 'CLD',
        'CAMBRIDGESHIRE' => 'CAM',
        'CAMDEN' => 'CMD',
        'CARDIFF' => 'CRF',
        'CARMARTHENSHIRE' => 'CMN',
        'CAUSEWAY COAST AND GLENS' => 'CCG',
        'CENTRAL BEDFORDSHIRE' => 'CBF',
        'CEREDIGION' => 'CGN',
        'CHESHIRE EAST' => 'CHE',
        'CHESHIRE WEST AND CHESTER' => 'CHW',
        'CLACKMANNANSHIRE' => 'CLK',
        'CONWY' => 'CWY',
        'CORNWALL' => 'CON',
        'COVENTRY' => 'COV',
        'CROYDON' => 'CRY',
        'CUMBRIA' => 'CMA',
        'DARLINGTON' => 'DAL',
        'DENBIGHSHIRE' => 'DEN',
        'DERBY' => 'DER',
        'DERBYSHIRE' => 'DBY',
        'DERRY AND STRABANE' => 'DRS',
        'DEVON' => 'DEV',
        'DONCASTER' => 'DNC',
        'DORSET' => 'DOR',
        'DUDLEY' => 'DUD',
        'DUMFRIES AND GALLOWAY' => 'DGY',
        'DUNDEE CITY' => 'DND',
        'DURHAM, COUNTY' => 'DUR',
        'EALING' => 'EAL',
        'EAST AYRSHIRE' => 'EAY',
        'EAST DUNBARTONSHIRE' => 'EDU',
        'EAST LOTHIAN' => 'ELN',
        'EAST RENFREWSHIRE' => 'ERW',
        'EAST RIDING OF YORKSHIRE' => 'ERY',
        'EAST SUSSEX' => 'ESX',
        'EDINBURGH, CITY OF' => 'EDH',
        'EILEAN SIAR' => 'ELS',
        'ENFIELD' => 'ENF',
        'ESSEX' => 'ESS',
        'FALKIRK' => 'FAL',
        'FERMANAGH AND OMAGH' => 'FMO',
        'FIFE' => 'FIF',
        'FLINTSHIRE' => 'FLN',
        'GATESHEAD' => 'GAT',
        'GLASGOW CITY' => 'GLG',
        'GLOUCESTERSHIRE' => 'GLS',
        'GREENWICH' => 'GRE',
        'GWYNEDD' => 'GWN',
        'HACKNEY' => 'HCK',
        'HALTON' => 'HAL',
        'HAMMERSMITH AND FULHAM' => 'HMF',
        'HAMPSHIRE' => 'HAM',
        'HARINGEY' => 'HRY',
        'HARROW' => 'HRW',
        'HARTLEPOOL' => 'HPL',
        'HAVERING' => 'HAV',
        'HEREFORDSHIRE' => 'HEF',
        'HERTFORDSHIRE' => 'HRT',
        'HIGHLAND' => 'HLD',
        'HILLINGDON' => 'HIL',
        'HOUNSLOW' => 'HNS',
        'INVERCLYDE' => 'IVC',
        'ISLE OF ANGLESEY' => 'AGY',
        'ISLE OF WIGHT' => 'IOW',
        'ISLES OF SCILLY' => 'IOS',
        'ISLINGTON' => 'ISL',
        'KENSINGTON AND CHELSEA' => 'KEC',
        'KENT' => 'KEN',
        'KINGSTON UPON HULL' => 'KHL',
        'KINGSTON UPON THAMES' => 'KTT',
        'KIRKLEES' => 'KIR',
        'KNOWSLEY' => 'KWL',
        'LAMBETH' => 'LBH',
        'LANCASHIRE' => 'LAN',
        'LEEDS' => 'LDS',
        'LEICESTER' => 'LCE',
        'LEICESTERSHIRE' => 'LEC',
        'LEWISHAM' => 'LEW',
        'LINCOLNSHIRE' => 'LIN',
        'LISBURN AND CASTLEREAGH' => 'LBC',
        'LIVERPOOL' => 'LIV',
        'LONDON, CITY OF' => 'LND',
        'LUTON' => 'LUT',
        'MANCHESTER' => 'MAN',
        'MEDWAY' => 'MDW',
        'MERTHYR TYDFIL' => 'MTY',
        'MERTON' => 'MRT',
        'MID AND EAST ANTRIM' => 'MEA',
        'MID-ULSTER' => 'MUL',
        'MIDDLESBROUGH' => 'MDB',
        'MIDLOTHIAN' => 'MLN',
        'MILTON KEYNES' => 'MIK',
        'MONMOUTHSHIRE' => 'MON',
        'MORAY' => 'MRY',
        'NEATH PORT TALBOT' => 'NTL',
        'NEWCASTLE UPON TYNE' => 'NET',
        'NEWHAM' => 'NWM',
        'NEWPORT' => 'NWP',
        'NEWRY, MOURNE AND DOWN' => 'NMD',
        'NORFOLK' => 'NFK',
        'NORTH AYRSHIRE' => 'NAY',
        'NORTH EAST LINCOLNSHIRE' => 'NEL',
        'NORTH LANARKSHIRE' => 'NLK',
        'NORTH LINCOLNSHIRE' => 'NLN',
        'NORTH NORTHAMPTONSHIRE' => 'NNH',
        'NORTH SOMERSET' => 'NSM',
        'NORTH TYNESIDE' => 'NTY',
        'NORTH YORKSHIRE' => 'NYK',
        'NORTHUMBERLAND' => 'NBL',
        'NOTTINGHAM' => 'NGM',
        'NOTTINGHAMSHIRE' => 'NTT',
        'OLDHAM' => 'OLD',
        'ORKNEY ISLANDS' => 'ORK',
        'OXFORDSHIRE' => 'OXF',
        'PEMBROKESHIRE' => 'PEM',
        'PERTH AND KINROSS' => 'PKN',
        'PETERBOROUGH' => 'PTE',
        'PLYMOUTH' => 'PLY',
        'PORTSMOUTH' => 'POR',
        'POWYS' => 'POW',
        'READING' => 'RDG',
        'REDBRIDGE' => 'RDB',
        'REDCAR AND CLEVELAND' => 'RCC',
        'RENFREWSHIRE' => 'RFW',
        'RHONDDA CYNON TAFF' => 'RCT',
        'RICHMOND UPON THAMES' => 'RIC',
        'ROCHDALE' => 'RCH',
        'ROTHERHAM' => 'ROT',
        'RUTLAND' => 'RUT',
        'SALFORD' => 'SLF',
        'SANDWELL' => 'SAW',
        'SCOTTISH BORDERS' => 'SCB',
        'SEFTON' => 'SFT',
        'SHEFFIELD' => 'SHF',
        'SHETLAND ISLANDS' => 'ZET',
        'SHROPSHIRE' => 'SHR',
        'SLOUGH' => 'SLG',
        'SOLIHULL' => 'SOL',
        'SOMERSET' => 'SOM',
        'SOUTH AYRSHIRE' => 'SAY',
        'SOUTH GLOUCESTERSHIRE' => 'SGC',
        'SOUTH LANARKSHIRE' => 'SLK',
        'SOUTH TYNESIDE' => 'STY',
        'SOUTHAMPTON' => 'STH',
        'SOUTHEND-ON-SEA' => 'SOS',
        'SOUTHWARK' => 'SWK',
        'ST. HELENS' => 'SHN',
        'STAFFORDSHIRE' => 'STS',
        'STIRLING' => 'STG',
        'STOCKPORT' => 'SKP',
        'STOCKTON-ON-TEES' => 'STT',
        'STOKE-ON-TRENT' => 'STE',
        'SUFFOLK' => 'SFK',
        'SUNDERLAND' => 'SND',
        'SURREY' => 'SRY',
        'SUTTON' => 'STN',
        'SWANSEA' => 'SWA',
        'SWINDON' => 'SWD',
        'TAMESIDE' => 'TAM',
        'TELFORD AND WREKIN' => 'TFW',
        'THURROCK' => 'THR',
        'TORBAY' => 'TOB',
        'TORFAEN' => 'TOF',
        'TOWER HAMLETS' => 'TWH',
        'TRAFFORD' => 'TRF',
        'VALE OF GLAMORGAN, THE' => 'VGL',
        'WAKEFIELD' => 'WKF',
        'WALSALL' => 'WLL',
        'WALTHAM FOREST' => 'WFT',
        'WANDSWORTH' => 'WND',
        'WARRINGTON' => 'WRT',
        'WARWICKSHIRE' => 'WAR',
        'WEST BERKSHIRE' => 'WBK',
        'WEST DUNBARTONSHIRE' => 'WDU',
        'WEST LOTHIAN' => 'WLN',
        'WEST NORTHAMPTONSHIRE' => 'WNH',
        'WEST SUSSEX' => 'WSX',
        'WESTMINSTER' => 'WSM',
        'WIGAN' => 'WGN',
        'WILTSHIRE' => 'WIL',
        'WINDSOR AND MAIDENHEAD' => 'WNM',
        'WIRRAL' => 'WRL',
        'WOKINGHAM' => 'WOK',
        'WOLVERHAMPTON' => 'WLV',
        'WORCESTERSHIRE' => 'WOR',
        'WREXHAM' => 'WRX',
        'YORK' => 'YOR'
    ];

    /**
     * Get UK county 3-digit code from county name
     *
     * @param string $county_name
     * @return string|null
     */
    public static function get_county_code( $county_name ) {
        if ( empty( $county_name ) ) {
            return null;
        }
        $county_name = trim( strtoupper( $county_name ) );

        // Try exact match first
        if ( isset( self::$uk_counties[ $county_name ] ) ) {
            return self::$uk_counties[ $county_name ];
        }

        // Try fuzzy matching for partial/misspelled counties
        $fuzzy_match = self::fuzzy_match_county( $county_name );
        if ( $fuzzy_match !== null ) {
            return self::$uk_counties[ $fuzzy_match ];
        }

        // Return null if no match found
        return null;
    }

    /**
     * Check if a county name is valid for UK
     *
     * @param string $county_name
     * @return bool
     */
    public static function is_valid_county( $county_name ) {
        return self::get_county_code( $county_name ) !== null;
    }

    /**
     * Get all UK counties
     *
     * @return array
     */
    public static function get_all_counties() {
        return array_keys( self::$uk_counties );
    }

    /**
     * Get all UK county codes
     *
     * @return array
     */
    public static function get_all_county_codes() {
        return array_values( self::$uk_counties );
    }

    /**
     * Fuzzy match county names using similar method as CountryUtils
     *
     * @param string $query
     * @return string|null
     */
    private static function fuzzy_match_county( $query ) {
        $counties = array_keys( self::$uk_counties );
        $matches = [];
        $high_score = -1;
        $best_match = null;

        foreach ( $counties as $county ) {
            $score = self::fuzzy_score( $county, $query );
            if ( $score > self::FUZZY_MATCH_THRESHOLD && $score > $high_score ) {
                $matches = [];
                $high_score = $score;
                $best_match = $county;
                $matches[] = $county;
            } elseif ( $score === $high_score ) {
                $matches[] = $county;
            }
        }

        // Return null if multiple matches found (ambiguous)
        if ( count( $matches ) > 1 ) {
            return null;
        }
        return $best_match;
    }

    /**
     * Calculate fuzzy score between two strings
     *
     * @param string $term
     * @param string $query
     * @return int
     */
    private static function fuzzy_score( $term, $query ) {
        if ( empty( $term ) || empty( $query ) ) {
            return 0;
        }

        $term_lower = strtolower( $term );
        $query_lower = strtolower( $query );
        $score = 0;
        $term_index = 0;
        $previous_matching_char_index = PHP_INT_MIN;

        for ( $query_index = 0; $query_index < strlen( $query_lower ); $query_index++ ) {
            $query_char = $query_lower[ $query_index ];
            $term_char_found = false;
            for ( ; $term_index < strlen( $term_lower ) && ! $term_char_found; $term_index++ ) {
                $term_char = $term_lower[ $term_index ];
                if ( $query_char === $term_char ) {
                    $score++;
                    if ( $previous_matching_char_index + 1 === $term_index ) {
                        $score += 2;
                    }
                    $previous_matching_char_index = $term_index;
                    $term_char_found = true;
                }
            }
        }

        return $score;
    }

    /**
     * Validate UK county for checkout data
     *
     * @param array $address_data Array containing 'country' and 'state' keys
     * @param string $address_type 'billing' or 'shipping' for error messages
     * @return array|null Returns error array if validation fails, null if passes
     */
    public static function validate_checkout_county( $address_data, $address_type = 'billing' ) {
        $country = $address_data['country'] ?? '';
        $state = $address_data['state'] ?? '';
        
        // Only validate if country is UK
        if ( $country !== 'GB' && $country !== 'UK' ) {
            return null;
        }

        // Check if county is valid
        if ( ! self::is_valid_county( $state ) ) {
            return [
                'code' => $address_type . '_state_invalid',
                'message' => sprintf(
                    __( '<strong>%s County</strong>: "%s" is not a valid UK county. Please enter a valid UK county name.', 'globalpayments-gateway-provider-for-woocommerce' ),
                    ucfirst( $address_type ),
                    $state
                )
            ];
        }

        return null;
    }
}