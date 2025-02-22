<?php


class WMS_TCPDF_FONTS {

	protected static $cache_uniord = array();

	public static function addTTFfont( $fontfile, $fonttype = '', $enc = '', $flags = 32, $outpath = '', $platid = 3, $encid = 1, $addcbbox = false, $link = false ) {
		if ( ! WMS_TCPDF_STATIC::file_exists( $fontfile ) ) {
			return false;
		}
		$fmetric = array();
		$font_path_parts = pathinfo( $fontfile );
		if ( ! isset( $font_path_parts['filename'] ) ) {
			$font_path_parts['filename'] = substr( $font_path_parts['basename'], 0, -( strlen( $font_path_parts['extension'] ) + 1 ) );
		}
		$font_name = strtolower( $font_path_parts['filename'] );
		$font_name = preg_replace( '/[^a-z0-9_]/', '', $font_name );
		$search = array( 'bold', 'oblique', 'italic', 'regular' );
		$replace = array( 'b', 'i', 'i', '' );
		$font_name = str_replace( $search, $replace, $font_name );
		if ( empty( $font_name ) ) {
			$font_name = 'tcpdffont';
		}
		if ( empty( $outpath ) ) {
			$outpath = self::_getfontpath();
		}
		if ( @WMS_TCPDF_STATIC::file_exists( $outpath . $font_name . '.php' ) ) {
			return $font_name;
		}
		$fmetric['file'] = $font_name;
		$fmetric['ctg'] = $font_name . '.ctg.z';
		$font = file_get_contents( $fontfile );
		$fmetric['originalsize'] = strlen( $font );
		if ( empty( $fonttype ) ) {
			if ( WMS_TCPDF_STATIC::_getULONG( $font, 0 ) == 0x10000 ) {
				$fonttype = 'TrueTypeUnicode';
			} elseif ( substr( $font, 0, 4 ) == 'OTTO' ) {
				return false;
			} else {
				$fonttype = 'Type1';
			}
		}
		switch ( $fonttype ) {
			case 'CID0CT':
			case 'CID0CS':
			case 'CID0KR':
			case 'CID0JP': {
				$fmetric['type'] = 'cidfont0';
				break;
			}
			case 'Type1': {
				$fmetric['type'] = 'Type1';
				if ( empty( $enc ) and ( ( $flags & 4 ) == 0 ) ) {
					$enc = 'cp1252';
				}
				break;
			}
			case 'TrueType': {
				$fmetric['type'] = 'TrueType';
				break;
			}
			case 'TrueTypeUnicode':
			default: {
				$fmetric['type'] = 'TrueTypeUnicode';
				break;
			}
		}
		$fmetric['enc'] = preg_replace( '/[^A-Za-z0-9_\-]/', '', $enc );
		$fmetric['diff'] = '';
		if ( ( $fmetric['type'] == 'TrueType' ) or ( $fmetric['type'] == 'Type1' ) ) {
			if ( ! empty( $enc ) and ( $enc != 'cp1252' ) and isset( WMS_TCPDF_FONT_DATA::$encmap[ $enc ] ) ) {
				$enc_ref = WMS_TCPDF_FONT_DATA::$encmap['cp1252'];
				$enc_target = WMS_TCPDF_FONT_DATA::$encmap[ $enc ];
				$last = 0;
				for ( $i = 32; $i <= 255; ++$i ) {
					if ( $enc_target[ $i ] != $enc_ref[ $i ] ) {
						if ( $i != ( $last + 1 ) ) {
							$fmetric['diff'] .= $i . ' ';
						}
						$last = $i;
						$fmetric['diff'] .= '/' . $enc_target[ $i ] . ' ';
					}
				}
			}
		}
		if ( $fmetric['type'] == 'Type1' ) {
			$a = unpack( 'Cmarker/Ctype/Vsize', substr( $font, 0, 6 ) );
			if ( $a['marker'] != 128 ) {
				return false;
			}
			$fmetric['size1'] = $a['size'];
			$data = substr( $font, 6, $fmetric['size1'] );
			$a = unpack( 'Cmarker/Ctype/Vsize', substr( $font, ( 6 + $fmetric['size1'] ), 6 ) );
			if ( $a['marker'] != 128 ) {
				return false;
			}
			$fmetric['size2'] = $a['size'];
			$encrypted = substr( $font, ( 12 + $fmetric['size1'] ), $fmetric['size2'] );
			$data .= $encrypted;
			$fmetric['file'] .= '.z';
			$fp = WMS_TCPDF_STATIC::fopenLocal( $outpath . $fmetric['file'], 'wb' );
			fwrite( $fp, gzcompress( $data ) );
			fclose( $fp );
			$fmetric['Flags'] = $flags;
			preg_match( '#/FullName[\s]*\(([^\)]*)#', $font, $matches );
			$fmetric['name'] = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $matches[1] );
			preg_match( '#/FontBBox[\s]*{([^}]*)#', $font, $matches );
			$fmetric['bbox'] = trim( $matches[1] );
			$bv = explode( ' ', $fmetric['bbox'] );
			$fmetric['Ascent'] = intval( $bv[3] );
			$fmetric['Descent'] = intval( $bv[1] );
			preg_match( '#/ItalicAngle[\s]*([0-9\+\-]*)#', $font, $matches );
			$fmetric['italicAngle'] = intval( $matches[1] );
			if ( $fmetric['italicAngle'] != 0 ) {
				$fmetric['Flags'] |= 64;
			}
			preg_match( '#/UnderlinePosition[\s]*([0-9\+\-]*)#', $font, $matches );
			$fmetric['underlinePosition'] = intval( $matches[1] );
			preg_match( '#/UnderlineThickness[\s]*([0-9\+\-]*)#', $font, $matches );
			$fmetric['underlineThickness'] = intval( $matches[1] );
			preg_match( '#/isFixedPitch[\s]*([^\s]*)#', $font, $matches );
			if ( $matches[1] == 'true' ) {
				$fmetric['Flags'] |= 1;
			}
			$imap = array();
			if ( preg_match_all( '#dup[\s]([0-9]+)[\s]*/([^\s]*)[\s]put#sU', $font, $fmap, PREG_SET_ORDER ) > 0 ) {
				foreach ( $fmap as $v ) {
					$imap[ $v[2] ] = $v[1];
				}
			}
			$r = 55665; // eexec encryption constant
			$c1 = 52845;
			$c2 = 22719;
			$elen = strlen( $encrypted );
			$eplain = '';
			for ( $i = 0; $i < $elen; ++$i ) {
				$chr = ord( $encrypted[ $i ] );
				$eplain .= chr( $chr ^ ( $r >> 8 ) );
				$r = ( ( ( $chr + $r ) * $c1 + $c2 ) % 65536 );
			}
			if ( preg_match( '#/ForceBold[\s]*([^\s]*)#', $eplain, $matches ) > 0 ) {
				if ( $matches[1] == 'true' ) {
					$fmetric['Flags'] |= 0x40000;
				}
			}
			if ( preg_match( '#/StdVW[\s]*\[([^\]]*)#', $eplain, $matches ) > 0 ) {
				$fmetric['StemV'] = intval( $matches[1] );
			} else {
				$fmetric['StemV'] = 70;
			}
			if ( preg_match( '#/StdHW[\s]*\[([^\]]*)#', $eplain, $matches ) > 0 ) {
				$fmetric['StemH'] = intval( $matches[1] );
			} else {
				$fmetric['StemH'] = 30;
			}
			if ( preg_match( '#/BlueValues[\s]*\[([^\]]*)#', $eplain, $matches ) > 0 ) {
				$bv = explode( ' ', $matches[1] );
				if ( count( $bv ) >= 6 ) {
					$v1 = intval( $bv[2] );
					$v2 = intval( $bv[4] );
					if ( $v1 <= $v2 ) {
						$fmetric['XHeight'] = $v1;
						$fmetric['CapHeight'] = $v2;
					} else {
						$fmetric['XHeight'] = $v2;
						$fmetric['CapHeight'] = $v1;
					}
				} else {
					$fmetric['XHeight'] = 450;
					$fmetric['CapHeight'] = 700;
				}
			} else {
				$fmetric['XHeight'] = 450;
				$fmetric['CapHeight'] = 700;
			}
			if ( preg_match( '#/lenIV[\s]*([0-9]*)#', $eplain, $matches ) > 0 ) {
				$lenIV = intval( $matches[1] );
			} else {
				$lenIV = 4;
			}
			$fmetric['Leading'] = 0;
			$eplain = substr( $eplain, ( strpos( $eplain, '/CharStrings' ) + 1 ) );
			preg_match_all( '#/([A-Za-z0-9\.]*)[\s][0-9]+[\s]RD[\s](.*)[\s]ND#sU', $eplain, $matches, PREG_SET_ORDER );
			if ( ! empty( $enc ) and isset( WMS_TCPDF_FONT_DATA::$encmap[ $enc ] ) ) {
				$enc_map = WMS_TCPDF_FONT_DATA::$encmap[ $enc ];
			} else {
				$enc_map = false;
			}
			$fmetric['cw'] = '';
			$fmetric['MaxWidth'] = 0;
			$cwidths = array();
			foreach ( $matches as $k => $v ) {
				$cid = 0;
				if ( isset( $imap[ $v[1] ] ) ) {
					$cid = $imap[ $v[1] ];
				} elseif ( $enc_map !== false ) {
					$cid = array_search( $v[1], $enc_map );
					if ( $cid === false ) {
						$cid = 0;
					} elseif ( $cid > 1000 ) {
						$cid -= 1000;
					}
				}
				$r = 4330; // charstring encryption constant
				$c1 = 52845;
				$c2 = 22719;
				$cd = $v[2];
				$clen = strlen( $cd );
				$ccom = array();
				for ( $i = 0; $i < $clen; ++$i ) {
					$chr = ord( $cd[ $i ] );
					$ccom[] = ( $chr ^ ( $r >> 8 ) );
					$r = ( ( ( $chr + $r ) * $c1 + $c2 ) % 65536 );
				}
				$cdec = array();
				$ck = 0;
				$i = $lenIV;
				while ( $i < $clen ) {
					if ( $ccom[ $i ] < 32 ) {
						$cdec[ $ck ] = $ccom[ $i ];
						if ( ( $ck > 0 ) and ( $cdec[ $ck ] == 13 ) ) {
							$cwidths[ $cid ] = $cdec[ ( $ck - 1 ) ];
						}
						++$i;
					} elseif ( ( $ccom[ $i ] >= 32 ) and ( $ccom[ $i ] <= 246 ) ) {
						$cdec[ $ck ] = ( $ccom[ $i ] - 139 );
						++$i;
					} elseif ( ( $ccom[ $i ] >= 247 ) and ( $ccom[ $i ] <= 250 ) ) {
						$cdec[ $ck ] = ( ( ( $ccom[ $i ] - 247 ) * 256 ) + $ccom[ ( $i + 1 ) ] + 108 );
						$i += 2;
					} elseif ( ( $ccom[ $i ] >= 251 ) and ( $ccom[ $i ] <= 254 ) ) {
						$cdec[ $ck ] = ( ( -( $ccom[ $i ] - 251 ) * 256 ) - $ccom[ ( $i + 1 ) ] - 108 );
						$i += 2;
					} elseif ( $ccom[ $i ] == 255 ) {
						$sval = chr( $ccom[ ( $i + 1 ) ] ) . chr( $ccom[ ( $i + 2 ) ] ) . chr( $ccom[ ( $i + 3 ) ] ) . chr( $ccom[ ( $i + 4 ) ] );
						$vsval = unpack( 'li', $sval );
						$cdec[ $ck ] = $vsval['i'];
						$i += 5;
					}
					++$ck;
				}
			} // end for each matches
			$fmetric['MissingWidth'] = $cwidths[0];
			$fmetric['MaxWidth'] = $fmetric['MissingWidth'];
			$fmetric['AvgWidth'] = 0;
			for ( $cid = 0; $cid <= 255; ++$cid ) {
				if ( isset( $cwidths[ $cid ] ) ) {
					if ( $cwidths[ $cid ] > $fmetric['MaxWidth'] ) {
						$fmetric['MaxWidth'] = $cwidths[ $cid ];
					}
					$fmetric['AvgWidth'] += $cwidths[ $cid ];
					$fmetric['cw'] .= ',' . $cid . '=>' . $cwidths[ $cid ];
				} else {
					$fmetric['cw'] .= ',' . $cid . '=>' . $fmetric['MissingWidth'];
				}
			}
			$fmetric['AvgWidth'] = round( $fmetric['AvgWidth'] / count( $cwidths ) );
		} else {
			$offset = 0; // offset position of the font data
			if ( WMS_TCPDF_STATIC::_getULONG( $font, $offset ) != 0x10000 ) {
				return false;
			}
			if ( $fmetric['type'] != 'cidfont0' ) {
				if ( $link ) {
					symlink( $fontfile, $outpath . $fmetric['file'] );
				} else {
					$fmetric['file'] .= '.z';
					$fp = WMS_TCPDF_STATIC::fopenLocal( $outpath . $fmetric['file'], 'wb' );
					fwrite( $fp, gzcompress( $font ) );
					fclose( $fp );
				}
			}
			$offset += 4;
			$numTables = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset += 2;
			$offset += 6;
			$table = array();
			for ( $i = 0; $i < $numTables; ++$i ) {
				$tag = substr( $font, $offset, 4 );
				$offset += 4;
				$table[ $tag ] = array();
				$table[ $tag ]['checkSum'] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
				$offset += 4;
				$table[ $tag ]['offset'] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
				$offset += 4;
				$table[ $tag ]['length'] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
				$offset += 4;
			}
			$offset = $table['head']['offset'] + 12;
			if ( WMS_TCPDF_STATIC::_getULONG( $font, $offset ) != 0x5F0F3CF5 ) {
				return false;
			}
			$offset += 4;
			$offset += 2; // skip flags
			$fmetric['unitsPerEm'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset += 2;
			$urk = ( 1000 / $fmetric['unitsPerEm'] );
			$offset += 16; // skip created, modified
			$xMin = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$yMin = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$xMax = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$yMax = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$fmetric['bbox'] = '' . $xMin . ' ' . $yMin . ' ' . $xMax . ' ' . $yMax . '';
			$macStyle = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset += 2;
			$fmetric['Flags'] = $flags;
			if ( ( $macStyle & 2 ) == 2 ) {
				$fmetric['Flags'] |= 64;
			}
			$offset = $table['head']['offset'] + 50;
			$short_offset = ( WMS_TCPDF_STATIC::_getSHORT( $font, $offset ) == 0 );
			$offset += 2;
			$indexToLoc = array();
			$offset = $table['loca']['offset'];
			if ( $short_offset ) {
				$tot_num_glyphs = floor( $table['loca']['length'] / 2 ); // numGlyphs + 1
				for ( $i = 0; $i < $tot_num_glyphs; ++$i ) {
					$indexToLoc[ $i ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset ) * 2;
					if ( isset( $indexToLoc[ ( $i - 1 ) ] ) && ( $indexToLoc[ $i ] == $indexToLoc[ ( $i - 1 ) ] ) ) {
						unset( $indexToLoc[ ( $i - 1 ) ] );
					}
					$offset += 2;
				}
			} else {
				$tot_num_glyphs = floor( $table['loca']['length'] / 4 ); // numGlyphs + 1
				for ( $i = 0; $i < $tot_num_glyphs; ++$i ) {
					$indexToLoc[ $i ] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
					if ( isset( $indexToLoc[ ( $i - 1 ) ] ) && ( $indexToLoc[ $i ] == $indexToLoc[ ( $i - 1 ) ] ) ) {
						unset( $indexToLoc[ ( $i - 1 ) ] );
					}
					$offset += 4;
				}
			}
			$offset = $table['cmap']['offset'] + 2;
			$numEncodingTables = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset += 2;
			$encodingTables = array();
			for ( $i = 0; $i < $numEncodingTables; ++$i ) {
				$encodingTables[ $i ]['platformID'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
				$offset += 2;
				$encodingTables[ $i ]['encodingID'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
				$offset += 2;
				$encodingTables[ $i ]['offset'] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
				$offset += 4;
			}
			$offset = $table['OS/2']['offset'];
			$offset += 2; // skip version
			$fmetric['AvgWidth'] = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$usWeightClass = round( WMS_TCPDF_STATIC::_getUFWORD( $font, $offset ) * $urk );
			$fmetric['StemV'] = round( ( 70 * $usWeightClass ) / 400 );
			$fmetric['StemH'] = round( ( 30 * $usWeightClass ) / 400 );
			$offset += 2;
			$offset += 2; // usWidthClass
			$fsType = WMS_TCPDF_STATIC::_getSHORT( $font, $offset );
			$offset += 2;
			if ( $fsType == 2 ) {
				return false;
			}
			$fmetric['name'] = '';
			$offset = $table['name']['offset'];
			$offset += 2; // skip Format selector (=0).
			$numNameRecords = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset += 2;
			$stringStorageOffset = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset += 2;
			for ( $i = 0; $i < $numNameRecords; ++$i ) {
				$offset += 6; // skip Platform ID, Platform-specific encoding ID, Language ID.
				$nameID = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
				$offset += 2;
				if ( $nameID == 6 ) {
					$stringLength = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
					$offset += 2;
					$stringOffset = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
					$offset += 2;
					$offset = ( $table['name']['offset'] + $stringStorageOffset + $stringOffset );
					$fmetric['name'] = substr( $font, $offset, $stringLength );
					$fmetric['name'] = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $fmetric['name'] );
					break;
				} else {
					$offset += 4; // skip String length, String offset
				}
			}
			if ( empty( $fmetric['name'] ) ) {
				$fmetric['name'] = $font_name;
			}
			$offset = $table['post']['offset'];
			$offset += 4; // skip Format Type
			$fmetric['italicAngle'] = WMS_TCPDF_STATIC::_getFIXED( $font, $offset );
			$offset += 4;
			$fmetric['underlinePosition'] = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$fmetric['underlineThickness'] = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$isFixedPitch = ( WMS_TCPDF_STATIC::_getULONG( $font, $offset ) == 0 ) ? false : true;
			$offset += 2;
			if ( $isFixedPitch ) {
				$fmetric['Flags'] |= 1;
			}
			$offset = $table['hhea']['offset'];
			$offset += 4; // skip Table version number
			$fmetric['Ascent'] = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$fmetric['Descent'] = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$fmetric['Leading'] = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$fmetric['MaxWidth'] = round( WMS_TCPDF_STATIC::_getUFWORD( $font, $offset ) * $urk );
			$offset += 2;
			$offset += 22; // skip some values
			$numberOfHMetrics = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset = $table['maxp']['offset'];
			$offset += 4; // skip Table version number
			$numGlyphs = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$ctg = array();
			foreach ( $encodingTables as $enctable ) {
				if ( ( $enctable['platformID'] == $platid ) and ( $enctable['encodingID'] == $encid ) ) {
					$offset = $table['cmap']['offset'] + $enctable['offset'];
					$format = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
					$offset += 2;
					switch ( $format ) {
						case 0: { // Format 0: Byte encoding table
							$offset += 4; // skip length and version/language
							for ( $c = 0; $c < 256; ++$c ) {
								$g = WMS_TCPDF_STATIC::_getBYTE( $font, $offset );
								$ctg[ $c ] = $g;
								++$offset;
							}
							break;
						}
						case 2: { // Format 2: High-byte mapping through table
							$offset += 4; // skip length and version/language
							$numSubHeaders = 0;
							for ( $i = 0; $i < 256; ++$i ) {
								$subHeaderKeys[ $i ] = ( WMS_TCPDF_STATIC::_getUSHORT( $font, $offset ) / 8 );
								$offset += 2;
								if ( $numSubHeaders < $subHeaderKeys[ $i ] ) {
									$numSubHeaders = $subHeaderKeys[ $i ];
								}
							}
							++$numSubHeaders;
							$subHeaders = array();
							$numGlyphIndexArray = 0;
							for ( $k = 0; $k < $numSubHeaders; ++$k ) {
								$subHeaders[ $k ]['firstCode'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
								$subHeaders[ $k ]['entryCount'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
								$subHeaders[ $k ]['idDelta'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
								$subHeaders[ $k ]['idRangeOffset'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
								$subHeaders[ $k ]['idRangeOffset'] -= ( 2 + ( ( $numSubHeaders - $k - 1 ) * 8 ) );
								$subHeaders[ $k ]['idRangeOffset'] /= 2;
								$numGlyphIndexArray += $subHeaders[ $k ]['entryCount'];
							}
							for ( $k = 0; $k < $numGlyphIndexArray; ++$k ) {
								$glyphIndexArray[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
							}
							for ( $i = 0; $i < 256; ++$i ) {
								$k = $subHeaderKeys[ $i ];
								if ( $k == 0 ) {
									$c = $i;
									$g = $glyphIndexArray[0];
									$ctg[ $c ] = $g;
								} else {
									$start_byte = $subHeaders[ $k ]['firstCode'];
									$end_byte = $start_byte + $subHeaders[ $k ]['entryCount'];
									for ( $j = $start_byte; $j < $end_byte; ++$j ) {
										$c = ( ( $i << 8 ) + $j );
										$idRangeOffset = ( $subHeaders[ $k ]['idRangeOffset'] + $j - $subHeaders[ $k ]['firstCode'] );
										$g = ( $glyphIndexArray[ $idRangeOffset ] + $subHeaders[ $k ]['idDelta'] ) % 65536;
										if ( $g < 0 ) {
											$g = 0;
										}
										$ctg[ $c ] = $g;
									}
								}
							}
							break;
						}
						case 4: { // Format 4: Segment mapping to delta values
							$length = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
							$offset += 2;
							$offset += 2; // skip version/language
							$segCount = floor( WMS_TCPDF_STATIC::_getUSHORT( $font, $offset ) / 2 );
							$offset += 2;
							$offset += 6; // skip searchRange, entrySelector, rangeShift
							$endCount = array(); // array of end character codes for each segment
							for ( $k = 0; $k < $segCount; ++$k ) {
								$endCount[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
							}
							$offset += 2; // skip reservedPad
							$startCount = array(); // array of start character codes for each segment
							for ( $k = 0; $k < $segCount; ++$k ) {
								$startCount[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
							}
							$idDelta = array(); // delta for all character codes in segment
							for ( $k = 0; $k < $segCount; ++$k ) {
								$idDelta[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
							}
							$idRangeOffset = array(); // Offsets into glyphIdArray or 0
							for ( $k = 0; $k < $segCount; ++$k ) {
								$idRangeOffset[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
							}
							$gidlen = ( floor( $length / 2 ) - 8 - ( 4 * $segCount ) );
							$glyphIdArray = array(); // glyph index array
							for ( $k = 0; $k < $gidlen; ++$k ) {
								$glyphIdArray[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
							}
							for ( $k = 0; $k < $segCount - 1; ++$k ) {
								for ( $c = $startCount[ $k ]; $c <= $endCount[ $k ]; ++$c ) {
									if ( $idRangeOffset[ $k ] == 0 ) {
										$g = ( $idDelta[ $k ] + $c ) % 65536;
									} else {
										$gid = ( floor( $idRangeOffset[ $k ] / 2 ) + ( $c - $startCount[ $k ] ) - ( $segCount - $k ) );
										$g = ( $glyphIdArray[ $gid ] + $idDelta[ $k ] ) % 65536;
									}
									if ( $g < 0 ) {
										$g = 0;
									}
									$ctg[ $c ] = $g;
								}
							}
							break;
						}
						case 6: { // Format 6: Trimmed table mapping
							$offset += 4; // skip length and version/language
							$firstCode = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
							$offset += 2;
							$entryCount = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
							$offset += 2;
							for ( $k = 0; $k < $entryCount; ++$k ) {
								$c = ( $k + $firstCode );
								$g = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$offset += 2;
								$ctg[ $c ] = $g;
							}
							break;
						}
						case 8: { // Format 8: Mixed 16-bit and 32-bit coverage
							$offset += 10; // skip reserved, length and version/language
							for ( $k = 0; $k < 8192; ++$k ) {
								$is32[ $k ] = WMS_TCPDF_STATIC::_getBYTE( $font, $offset );
								++$offset;
							}
							$nGroups = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
							$offset += 4;
							for ( $i = 0; $i < $nGroups; ++$i ) {
								$startCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
								$offset += 4;
								$endCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
								$offset += 4;
								$startGlyphID = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
								$offset += 4;
								for ( $k = $startCharCode; $k <= $endCharCode; ++$k ) {
									$is32idx = floor( $c / 8 );
									if ( ( isset( $is32[ $is32idx ] ) ) and ( ( $is32[ $is32idx ] & ( 1 << ( 7 - ( $c % 8 ) ) ) ) == 0 ) ) {
										$c = $k;
									} else {
										$c = ( ( 55232 + ( $k >> 10 ) ) << 10 ) + ( 0xDC00 + ( $k & 0x3FF ) ) - 56613888;
									}
									$ctg[ $c ] = 0;
									++$startGlyphID;
								}
							}
							break;
						}
						case 10: { // Format 10: Trimmed array
							$offset += 10; // skip reserved, length and version/language
							$startCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
							$offset += 4;
							$numChars = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
							$offset += 4;
							for ( $k = 0; $k < $numChars; ++$k ) {
								$c = ( $k + $startCharCode );
								$g = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
								$ctg[ $c ] = $g;
								$offset += 2;
							}
							break;
						}
						case 12: { // Format 12: Segmented coverage
							$offset += 10; // skip length and version/language
							$nGroups = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
							$offset += 4;
							for ( $k = 0; $k < $nGroups; ++$k ) {
								$startCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
								$offset += 4;
								$endCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
								$offset += 4;
								$startGlyphCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
								$offset += 4;
								for ( $c = $startCharCode; $c <= $endCharCode; ++$c ) {
									$ctg[ $c ] = $startGlyphCode;
									++$startGlyphCode;
								}
							}
							break;
						}
						case 13: { // Format 13: Many-to-one range mappings
							break;
						}
						case 14: { // Format 14: Unicode Variation Sequences
							break;
						}
					}
				}
			}
			if ( ! isset( $ctg[0] ) ) {
				$ctg[0] = 0;
			}
			$offset = ( $table['glyf']['offset'] + $indexToLoc[ $ctg[120] ] + 4 );
			$yMin = WMS_TCPDF_STATIC::_getFWORD( $font, $offset );
			$offset += 4;
			$yMax = WMS_TCPDF_STATIC::_getFWORD( $font, $offset );
			$offset += 2;
			$fmetric['XHeight'] = round( ( $yMax - $yMin ) * $urk );
			$offset = ( $table['glyf']['offset'] + $indexToLoc[ $ctg[72] ] + 4 );
			$yMin = WMS_TCPDF_STATIC::_getFWORD( $font, $offset );
			$offset += 4;
			$yMax = WMS_TCPDF_STATIC::_getFWORD( $font, $offset );
			$offset += 2;
			$fmetric['CapHeight'] = round( ( $yMax - $yMin ) * $urk );
			$cw = array();
			$offset = $table['hmtx']['offset'];
			for ( $i = 0; $i < $numberOfHMetrics; ++$i ) {
				$cw[ $i ] = round( WMS_TCPDF_STATIC::_getUFWORD( $font, $offset ) * $urk );
				$offset += 4; // skip lsb
			}
			if ( $numberOfHMetrics < $numGlyphs ) {
				$cw = array_pad( $cw, $numGlyphs, $cw[ ( $numberOfHMetrics - 1 ) ] );
			}
			$fmetric['MissingWidth'] = $cw[0];
			$fmetric['cw'] = '';
			$fmetric['cbbox'] = '';
			for ( $cid = 0; $cid <= 65535; ++$cid ) {
				if ( isset( $ctg[ $cid ] ) ) {
					if ( isset( $cw[ $ctg[ $cid ] ] ) ) {
						$fmetric['cw'] .= ',' . $cid . '=>' . $cw[ $ctg[ $cid ] ];
					}
					if ( $addcbbox and isset( $indexToLoc[ $ctg[ $cid ] ] ) ) {
						$offset = ( $table['glyf']['offset'] + $indexToLoc[ $ctg[ $cid ] ] );
						$xMin = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset + 2 ) * $urk );
						$yMin = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset + 4 ) * $urk );
						$xMax = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset + 6 ) * $urk );
						$yMax = round( WMS_TCPDF_STATIC::_getFWORD( $font, $offset + 8 ) * $urk );
						$fmetric['cbbox'] .= ',' . $cid . '=>array(' . $xMin . ',' . $yMin . ',' . $xMax . ',' . $yMax . ')';
					}
				}
			}
		} // end of true type
		if ( ( $fmetric['type'] == 'TrueTypeUnicode' ) and ( count( $ctg ) == 256 ) ) {
			$fmetric['type'] = 'TrueType';
		}
		$pfile = '<' . '?' . 'php' . "\n";
		$pfile .= '// TCPDF FONT FILE DESCRIPTION' . "\n";
		$pfile .= '$type=\'' . $fmetric['type'] . '\';' . "\n";
		$pfile .= '$name=\'' . $fmetric['name'] . '\';' . "\n";
		$pfile .= '$up=' . $fmetric['underlinePosition'] . ';' . "\n";
		$pfile .= '$ut=' . $fmetric['underlineThickness'] . ';' . "\n";
		if ( $fmetric['MissingWidth'] > 0 ) {
			$pfile .= '$dw=' . $fmetric['MissingWidth'] . ';' . "\n";
		} else {
			$pfile .= '$dw=' . $fmetric['AvgWidth'] . ';' . "\n";
		}
		$pfile .= '$diff=\'' . $fmetric['diff'] . '\';' . "\n";
		if ( $fmetric['type'] == 'Type1' ) {
			$pfile .= '$enc=\'' . $fmetric['enc'] . '\';' . "\n";
			$pfile .= '$file=\'' . $fmetric['file'] . '\';' . "\n";
			$pfile .= '$size1=' . $fmetric['size1'] . ';' . "\n";
			$pfile .= '$size2=' . $fmetric['size2'] . ';' . "\n";
		} else {
			$pfile .= '$originalsize=' . $fmetric['originalsize'] . ';' . "\n";
			if ( $fmetric['type'] == 'cidfont0' ) {
				switch ( $fonttype ) {
					case 'CID0JP': {
						$pfile .= '// Japanese' . "\n";
						$pfile .= '$enc=\'UniJIS-UTF16-H\';' . "\n";
						$pfile .= '$cidinfo=array(\'Registry\'=>\'Adobe\', \'Ordering\'=>\'Japan1\',\'Supplement\'=>5);' . "\n";
						$pfile .= 'include(dirname(__FILE__).\'/uni2cid_aj16.php\');' . "\n";
						break;
					}
					case 'CID0KR': {
						$pfile .= '// Korean' . "\n";
						$pfile .= '$enc=\'UniKS-UTF16-H\';' . "\n";
						$pfile .= '$cidinfo=array(\'Registry\'=>\'Adobe\', \'Ordering\'=>\'Korea1\',\'Supplement\'=>0);' . "\n";
						$pfile .= 'include(dirname(__FILE__).\'/uni2cid_ak12.php\');' . "\n";
						break;
					}
					case 'CID0CS': {
						$pfile .= '// Chinese Simplified' . "\n";
						$pfile .= '$enc=\'UniGB-UTF16-H\';' . "\n";
						$pfile .= '$cidinfo=array(\'Registry\'=>\'Adobe\', \'Ordering\'=>\'GB1\',\'Supplement\'=>2);' . "\n";
						$pfile .= 'include(dirname(__FILE__).\'/uni2cid_ag15.php\');' . "\n";
						break;
					}
					case 'CID0CT':
					default: {
						$pfile .= '// Chinese Traditional' . "\n";
						$pfile .= '$enc=\'UniCNS-UTF16-H\';' . "\n";
						$pfile .= '$cidinfo=array(\'Registry\'=>\'Adobe\', \'Ordering\'=>\'CNS1\',\'Supplement\'=>0);' . "\n";
						$pfile .= 'include(dirname(__FILE__).\'/uni2cid_aj16.php\');' . "\n";
						break;
					}
				}
			} else {
				$pfile .= '$enc=\'' . $fmetric['enc'] . '\';' . "\n";
				$pfile .= '$file=\'' . $fmetric['file'] . '\';' . "\n";
				$pfile .= '$ctg=\'' . $fmetric['ctg'] . '\';' . "\n";
				$cidtogidmap = str_pad( '', 131072, "\x00" ); // (256 * 256 * 2) = 131072
				foreach ( $ctg as $cid => $gid ) {
					$cidtogidmap = self::updateCIDtoGIDmap( $cidtogidmap, $cid, $ctg[ $cid ] );
				}
				$fp = WMS_TCPDF_STATIC::fopenLocal( $outpath . $fmetric['ctg'], 'wb' );
				fwrite( $fp, gzcompress( $cidtogidmap ) );
				fclose( $fp );
			}
		}
		$pfile .= '$desc=array(';
		$pfile .= '\'Flags\'=>' . $fmetric['Flags'] . ',';
		$pfile .= '\'FontBBox\'=>\'[' . $fmetric['bbox'] . ']\',';
		$pfile .= '\'ItalicAngle\'=>' . $fmetric['italicAngle'] . ',';
		$pfile .= '\'Ascent\'=>' . $fmetric['Ascent'] . ',';
		$pfile .= '\'Descent\'=>' . $fmetric['Descent'] . ',';
		$pfile .= '\'Leading\'=>' . $fmetric['Leading'] . ',';
		$pfile .= '\'CapHeight\'=>' . $fmetric['CapHeight'] . ',';
		$pfile .= '\'XHeight\'=>' . $fmetric['XHeight'] . ',';
		$pfile .= '\'StemV\'=>' . $fmetric['StemV'] . ',';
		$pfile .= '\'StemH\'=>' . $fmetric['StemH'] . ',';
		$pfile .= '\'AvgWidth\'=>' . $fmetric['AvgWidth'] . ',';
		$pfile .= '\'MaxWidth\'=>' . $fmetric['MaxWidth'] . ',';
		$pfile .= '\'MissingWidth\'=>' . $fmetric['MissingWidth'] . '';
		$pfile .= ');' . "\n";
		if ( ! empty( $fmetric['cbbox'] ) ) {
			$pfile .= '$cbbox=array(' . substr( $fmetric['cbbox'], 1 ) . ');' . "\n";
		}
		$pfile .= '$cw=array(' . substr( $fmetric['cw'], 1 ) . ');' . "\n";
		$pfile .= '// --- EOF ---' . "\n";
		$fp = WMS_TCPDF_STATIC::fopenLocal( $outpath . $font_name . '.php', 'w' );
		fwrite( $fp, $pfile );
		fclose( $fp );
		return $font_name;
	}

	public static function _getTTFtableChecksum( $table, $length ) {
		$sum = 0;
		$tlen = ( $length / 4 );
		$offset = 0;
		for ( $i = 0; $i < $tlen; ++$i ) {
			$v = unpack( 'Ni', substr( $table, $offset, 4 ) );
			$sum += $v['i'];
			$offset += 4;
		}
		$sum = unpack( 'Ni', pack( 'N', $sum ) );
		return $sum['i'];
	}

	public static function _getTrueTypeFontSubset( $font, $subsetchars ) {
		ksort( $subsetchars );
		$offset = 0; // offset position of the font data
		if ( WMS_TCPDF_STATIC::_getULONG( $font, $offset ) != 0x10000 ) {
			return $font;
		}
		$offset += 4;
		$numTables = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
		$offset += 2;
		$offset += 6;
		$table = array();
		for ( $i = 0; $i < $numTables; ++$i ) {
			$tag = substr( $font, $offset, 4 );
			$offset += 4;
			$table[ $tag ] = array();
			$table[ $tag ]['checkSum'] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
			$offset += 4;
			$table[ $tag ]['offset'] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
			$offset += 4;
			$table[ $tag ]['length'] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
			$offset += 4;
		}
		$offset = $table['head']['offset'] + 12;
		if ( WMS_TCPDF_STATIC::_getULONG( $font, $offset ) != 0x5F0F3CF5 ) {
			return $font;
		}
		$offset += 4;
		$offset = $table['head']['offset'] + 50;
		$short_offset = ( WMS_TCPDF_STATIC::_getSHORT( $font, $offset ) == 0 );
		$offset += 2;
		$indexToLoc = array();
		$offset = $table['loca']['offset'];
		if ( $short_offset ) {
			$tot_num_glyphs = floor( $table['loca']['length'] / 2 ); // numGlyphs + 1
			for ( $i = 0; $i < $tot_num_glyphs; ++$i ) {
				$indexToLoc[ $i ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset ) * 2;
				$offset += 2;
			}
		} else {
			$tot_num_glyphs = ( $table['loca']['length'] / 4 ); // numGlyphs + 1
			for ( $i = 0; $i < $tot_num_glyphs; ++$i ) {
				$indexToLoc[ $i ] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
				$offset += 4;
			}
		}
		$subsetglyphs = array(); // glyph IDs on key
		$subsetglyphs[0] = true; // character codes that do not correspond to any glyph in the font should be mapped to glyph index 0
		$offset = $table['cmap']['offset'] + 2;
		$numEncodingTables = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
		$offset += 2;
		$encodingTables = array();
		for ( $i = 0; $i < $numEncodingTables; ++$i ) {
			$encodingTables[ $i ]['platformID'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset += 2;
			$encodingTables[ $i ]['encodingID'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset += 2;
			$encodingTables[ $i ]['offset'] = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
			$offset += 4;
		}
		foreach ( $encodingTables as $enctable ) {
			$offset = $table['cmap']['offset'] + $enctable['offset'];
			$format = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
			$offset += 2;
			switch ( $format ) {
				case 0: { // Format 0: Byte encoding table
					$offset += 4; // skip length and version/language
					for ( $c = 0; $c < 256; ++$c ) {
						if ( isset( $subsetchars[ $c ] ) ) {
							$g = WMS_TCPDF_STATIC::_getBYTE( $font, $offset );
							$subsetglyphs[ $g ] = true;
						}
						++$offset;
					}
					break;
				}
				case 2: { // Format 2: High-byte mapping through table
					$offset += 4; // skip length and version/language
					$numSubHeaders = 0;
					for ( $i = 0; $i < 256; ++$i ) {
						$subHeaderKeys[ $i ] = ( WMS_TCPDF_STATIC::_getUSHORT( $font, $offset ) / 8 );
						$offset += 2;
						if ( $numSubHeaders < $subHeaderKeys[ $i ] ) {
							$numSubHeaders = $subHeaderKeys[ $i ];
						}
					}
					++$numSubHeaders;
					$subHeaders = array();
					$numGlyphIndexArray = 0;
					for ( $k = 0; $k < $numSubHeaders; ++$k ) {
						$subHeaders[ $k ]['firstCode'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
						$subHeaders[ $k ]['entryCount'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
						$subHeaders[ $k ]['idDelta'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
						$subHeaders[ $k ]['idRangeOffset'] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
						$subHeaders[ $k ]['idRangeOffset'] -= ( 2 + ( ( $numSubHeaders - $k - 1 ) * 8 ) );
						$subHeaders[ $k ]['idRangeOffset'] /= 2;
						$numGlyphIndexArray += $subHeaders[ $k ]['entryCount'];
					}
					for ( $k = 0; $k < $numGlyphIndexArray; ++$k ) {
						$glyphIndexArray[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
					}
					for ( $i = 0; $i < 256; ++$i ) {
						$k = $subHeaderKeys[ $i ];
						if ( $k == 0 ) {
							$c = $i;
							if ( isset( $subsetchars[ $c ] ) ) {
								$g = $glyphIndexArray[0];
								$subsetglyphs[ $g ] = true;
							}
						} else {
							$start_byte = $subHeaders[ $k ]['firstCode'];
							$end_byte = $start_byte + $subHeaders[ $k ]['entryCount'];
							for ( $j = $start_byte; $j < $end_byte; ++$j ) {
								$c = ( ( $i << 8 ) + $j );
								if ( isset( $subsetchars[ $c ] ) ) {
									$idRangeOffset = ( $subHeaders[ $k ]['idRangeOffset'] + $j - $subHeaders[ $k ]['firstCode'] );
									$g = ( $glyphIndexArray[ $idRangeOffset ] + $subHeaders[ $k ]['idDelta'] ) % 65536;
									if ( $g < 0 ) {
										$g = 0;
									}
									$subsetglyphs[ $g ] = true;
								}
							}
						}
					}
					break;
				}
				case 4: { // Format 4: Segment mapping to delta values
					$length = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
					$offset += 2;
					$offset += 2; // skip version/language
					$segCount = floor( WMS_TCPDF_STATIC::_getUSHORT( $font, $offset ) / 2 );
					$offset += 2;
					$offset += 6; // skip searchRange, entrySelector, rangeShift
					$endCount = array(); // array of end character codes for each segment
					for ( $k = 0; $k < $segCount; ++$k ) {
						$endCount[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
					}
					$offset += 2; // skip reservedPad
					$startCount = array(); // array of start character codes for each segment
					for ( $k = 0; $k < $segCount; ++$k ) {
						$startCount[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
					}
					$idDelta = array(); // delta for all character codes in segment
					for ( $k = 0; $k < $segCount; ++$k ) {
						$idDelta[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
					}
					$idRangeOffset = array(); // Offsets into glyphIdArray or 0
					for ( $k = 0; $k < $segCount; ++$k ) {
						$idRangeOffset[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
					}
					$gidlen = ( floor( $length / 2 ) - 8 - ( 4 * $segCount ) );
					$glyphIdArray = array(); // glyph index array
					for ( $k = 0; $k < $gidlen; ++$k ) {
						$glyphIdArray[ $k ] = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
						$offset += 2;
					}
					for ( $k = 0; $k < $segCount; ++$k ) {
						for ( $c = $startCount[ $k ]; $c <= $endCount[ $k ]; ++$c ) {
							if ( isset( $subsetchars[ $c ] ) ) {
								if ( $idRangeOffset[ $k ] == 0 ) {
									$g = ( $idDelta[ $k ] + $c ) % 65536;
								} else {
									$gid = ( floor( $idRangeOffset[ $k ] / 2 ) + ( $c - $startCount[ $k ] ) - ( $segCount - $k ) );
									$g = ( $glyphIdArray[ $gid ] + $idDelta[ $k ] ) % 65536;
								}
								if ( $g < 0 ) {
									$g = 0;
								}
								$subsetglyphs[ $g ] = true;
							}
						}
					}
					break;
				}
				case 6: { // Format 6: Trimmed table mapping
					$offset += 4; // skip length and version/language
					$firstCode = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
					$offset += 2;
					$entryCount = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
					$offset += 2;
					for ( $k = 0; $k < $entryCount; ++$k ) {
						$c = ( $k + $firstCode );
						if ( isset( $subsetchars[ $c ] ) ) {
							$g = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
							$subsetglyphs[ $g ] = true;
						}
						$offset += 2;
					}
					break;
				}
				case 8: { // Format 8: Mixed 16-bit and 32-bit coverage
					$offset += 10; // skip reserved, length and version/language
					for ( $k = 0; $k < 8192; ++$k ) {
						$is32[ $k ] = WMS_TCPDF_STATIC::_getBYTE( $font, $offset );
						++$offset;
					}
					$nGroups = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
					$offset += 4;
					for ( $i = 0; $i < $nGroups; ++$i ) {
						$startCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
						$offset += 4;
						$endCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
						$offset += 4;
						$startGlyphID = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
						$offset += 4;
						for ( $k = $startCharCode; $k <= $endCharCode; ++$k ) {
							$is32idx = floor( $c / 8 );
							if ( ( isset( $is32[ $is32idx ] ) ) and ( ( $is32[ $is32idx ] & ( 1 << ( 7 - ( $c % 8 ) ) ) ) == 0 ) ) {
								$c = $k;
							} else {
								$c = ( ( 55232 + ( $k >> 10 ) ) << 10 ) + ( 0xDC00 + ( $k & 0x3FF ) ) - 56613888;
							}
							if ( isset( $subsetchars[ $c ] ) ) {
								$subsetglyphs[ $startGlyphID ] = true;
							}
							++$startGlyphID;
						}
					}
					break;
				}
				case 10: { // Format 10: Trimmed array
					$offset += 10; // skip reserved, length and version/language
					$startCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
					$offset += 4;
					$numChars = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
					$offset += 4;
					for ( $k = 0; $k < $numChars; ++$k ) {
						$c = ( $k + $startCharCode );
						if ( isset( $subsetchars[ $c ] ) ) {
							$g = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
							$subsetglyphs[ $g ] = true;
						}
						$offset += 2;
					}
					break;
				}
				case 12: { // Format 12: Segmented coverage
					$offset += 10; // skip length and version/language
					$nGroups = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
					$offset += 4;
					for ( $k = 0; $k < $nGroups; ++$k ) {
						$startCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
						$offset += 4;
						$endCharCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
						$offset += 4;
						$startGlyphCode = WMS_TCPDF_STATIC::_getULONG( $font, $offset );
						$offset += 4;
						for ( $c = $startCharCode; $c <= $endCharCode; ++$c ) {
							if ( isset( $subsetchars[ $c ] ) ) {
								$subsetglyphs[ $startGlyphCode ] = true;
							}
							++$startGlyphCode;
						}
					}
					break;
				}
				case 13: { // Format 13: Many-to-one range mappings
					break;
				}
				case 14: { // Format 14: Unicode Variation Sequences
					break;
				}
			}
		}
		$new_sga = $subsetglyphs;
		while ( ! empty( $new_sga ) ) {
			$sga = $new_sga;
			$new_sga = array();
			foreach ( $sga as $key => $val ) {
				if ( isset( $indexToLoc[ $key ] ) ) {
					$offset = ( $table['glyf']['offset'] + $indexToLoc[ $key ] );
					$numberOfContours = WMS_TCPDF_STATIC::_getSHORT( $font, $offset );
					$offset += 2;
					if ( $numberOfContours < 0 ) { // composite glyph
						$offset += 8; // skip xMin, yMin, xMax, yMax
						do {
							$flags = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
							$offset += 2;
							$glyphIndex = WMS_TCPDF_STATIC::_getUSHORT( $font, $offset );
							$offset += 2;
							if ( ! isset( $subsetglyphs[ $glyphIndex ] ) ) {
								$new_sga[ $glyphIndex ] = true;
							}
							if ( $flags & 1 ) {
								$offset += 4;
							} else {
								$offset += 2;
							}
							if ( $flags & 8 ) {
								$offset += 2;
							} elseif ( $flags & 64 ) {
								$offset += 4;
							} elseif ( $flags & 128 ) {
								$offset += 8;
							}
						} while ( $flags & 32 );
					}
				}
			}
			$subsetglyphs += $new_sga;
		}
		ksort( $subsetglyphs );
		$glyf = '';
		$loca = '';
		$offset = 0;
		$glyf_offset = $table['glyf']['offset'];
		for ( $i = 0; $i < $tot_num_glyphs; ++$i ) {
			if ( isset( $subsetglyphs[ $i ] ) ) {
				$length = ( $indexToLoc[ ( $i + 1 ) ] - $indexToLoc[ $i ] );
				$glyf .= substr( $font, ( $glyf_offset + $indexToLoc[ $i ] ), $length );
			} else {
				$length = 0;
			}
			if ( $short_offset ) {
				$loca .= pack( 'n', floor( $offset / 2 ) );
			} else {
				$loca .= pack( 'N', $offset );
			}
			$offset += $length;
		}
		$table_names = array( 'head', 'hhea', 'hmtx', 'maxp', 'cvt ', 'fpgm', 'prep' ); // minimum required table names
		$offset = 12;
		foreach ( $table as $tag => $val ) {
			if ( in_array( $tag, $table_names ) ) {
				$table[ $tag ]['data'] = substr( $font, $table[ $tag ]['offset'], $table[ $tag ]['length'] );
				if ( $tag == 'head' ) {
					$table[ $tag ]['data'] = substr( $table[ $tag ]['data'], 0, 8 ) . "\x0\x0\x0\x0" . substr( $table[ $tag ]['data'], 12 );
				}
				$pad = 4 - ( $table[ $tag ]['length'] % 4 );
				if ( $pad != 4 ) {
					$table[ $tag ]['length'] += $pad;
					$table[ $tag ]['data'] .= str_repeat( "\x0", $pad );
				}
				$table[ $tag ]['offset'] = $offset;
				$offset += $table[ $tag ]['length'];
			} else {
				unset( $table[ $tag ] );
			}
		}
		$table['loca']['data'] = $loca;
		$table['loca']['length'] = strlen( $loca );
		$pad = 4 - ( $table['loca']['length'] % 4 );
		if ( $pad != 4 ) {
			$table['loca']['length'] += $pad;
			$table['loca']['data'] .= str_repeat( "\x0", $pad );
		}
		$table['loca']['offset'] = $offset;
		$table['loca']['checkSum'] = self::_getTTFtableChecksum( $table['loca']['data'], $table['loca']['length'] );
		$offset += $table['loca']['length'];
		$table['glyf']['data'] = $glyf;
		$table['glyf']['length'] = strlen( $glyf );
		$pad = 4 - ( $table['glyf']['length'] % 4 );
		if ( $pad != 4 ) {
			$table['glyf']['length'] += $pad;
			$table['glyf']['data'] .= str_repeat( "\x0", $pad );
		}
		$table['glyf']['offset'] = $offset;
		$table['glyf']['checkSum'] = self::_getTTFtableChecksum( $table['glyf']['data'], $table['glyf']['length'] );
		$font = '';
		$font .= pack( 'N', 0x10000 ); // sfnt version
		$numTables = count( $table );
		$font .= pack( 'n', $numTables ); // numTables
		$entrySelector = floor( log( $numTables, 2 ) );
		$searchRange = pow( 2, $entrySelector ) * 16;
		$rangeShift = ( $numTables * 16 ) - $searchRange;
		$font .= pack( 'n', $searchRange ); // searchRange
		$font .= pack( 'n', $entrySelector ); // entrySelector
		$font .= pack( 'n', $rangeShift ); // rangeShift
		$offset = ( $numTables * 16 );
		foreach ( $table as $tag => $data ) {
			$font .= $tag; // tag
			$font .= pack( 'N', $data['checkSum'] ); // checkSum
			$font .= pack( 'N', ( $data['offset'] + $offset ) ); // offset
			$font .= pack( 'N', $data['length'] ); // length
		}
		foreach ( $table as $data ) {
			$font .= $data['data'];
		}
		$checkSumAdjustment = 0xB1B0AFBA - self::_getTTFtableChecksum( $font, strlen( $font ) );
		$font = substr( $font, 0, $table['head']['offset'] + 8 ) . pack( 'N', $checkSumAdjustment ) . substr( $font, $table['head']['offset'] + 12 );
		return $font;
	}

	public static function _putfontwidths( $font, $cidoffset = 0 ) {
		ksort( $font['cw'] );
		$rangeid = 0;
		$range = array();
		$prevcid = -2;
		$prevwidth = -1;
		$interval = false;
		foreach ( $font['cw'] as $cid => $width ) {
			$cid -= $cidoffset;
			if ( $font['subset'] and ( ! isset( $font['subsetchars'][ $cid ] ) ) ) {
				continue;
			}
			if ( $width != $font['dw'] ) {
				if ( $cid == ( $prevcid + 1 ) ) {
					if ( $width == $prevwidth ) {
						if ( $width == $range[ $rangeid ][0] ) {
							$range[ $rangeid ][] = $width;
						} else {
							array_pop( $range[ $rangeid ] );
							$rangeid = $prevcid;
							$range[ $rangeid ] = array();
							$range[ $rangeid ][] = $prevwidth;
							$range[ $rangeid ][] = $width;
						}
						$interval = true;
						$range[ $rangeid ]['interval'] = true;
					} else {
						if ( $interval ) {
							$rangeid = $cid;
							$range[ $rangeid ] = array();
							$range[ $rangeid ][] = $width;
						} else {
							$range[ $rangeid ][] = $width;
						}
						$interval = false;
					}
				} else {
					$rangeid = $cid;
					$range[ $rangeid ] = array();
					$range[ $rangeid ][] = $width;
					$interval = false;
				}
				$prevcid = $cid;
				$prevwidth = $width;
			}
		}
		$prevk = -1;
		$nextk = -1;
		$prevint = false;
		foreach ( $range as $k => $ws ) {
			$cws = count( $ws );
			if ( ( $k == $nextk ) and ( ! $prevint ) and ( ( ! isset( $ws['interval'] ) ) or ( $cws < 4 ) ) ) {
				if ( isset( $range[ $k ]['interval'] ) ) {
					unset( $range[ $k ]['interval'] );
				}
				$range[ $prevk ] = array_merge( $range[ $prevk ], $range[ $k ] );
				unset( $range[ $k ] );
			} else {
				$prevk = $k;
			}
			$nextk = $k + $cws;
			if ( isset( $ws['interval'] ) ) {
				if ( $cws > 3 ) {
					$prevint = true;
				} else {
					$prevint = false;
				}
				if ( isset( $range[ $k ]['interval'] ) ) {
					unset( $range[ $k ]['interval'] );
				}
				--$nextk;
			} else {
				$prevint = false;
			}
		}
		$w = '';
		foreach ( $range as $k => $ws ) {
			if ( count( array_count_values( $ws ) ) == 1 ) {
				$w .= ' ' . $k . ' ' . ( $k + count( $ws ) - 1 ) . ' ' . $ws[0];
			} else {
				$w .= ' ' . $k . ' [ ' . implode( ' ', $ws ) . ' ]';
			}
		}
		return '/W [' . $w . ' ]';
	}




	public static function updateCIDtoGIDmap( $map, $cid, $gid ) {
		if ( ( $cid >= 0 ) and ( $cid <= 0xFFFF ) and ( $gid >= 0 ) ) {
			if ( $gid > 0xFFFF ) {
				$gid -= 0x10000;
			}
			$map[ ( $cid * 2 ) ] = chr( $gid >> 8 );
			$map[ ( ( $cid * 2 ) + 1 ) ] = chr( $gid & 0xFF );
		}
		return $map;
	}

	public static function _getfontpath() {
		if ( ! defined( 'WMS_K_PATH_FONTS' ) and is_dir( $fdir = realpath( dirname( __FILE__ ) . '/../fonts' ) ) ) {
			if ( substr( $fdir, -1 ) != '/' ) {
				$fdir .= '/';
			}
			define( 'WMS_K_PATH_FONTS', $fdir );
		}
		return defined( 'WMS_K_PATH_FONTS' ) ? WMS_K_PATH_FONTS : '';
	}



	public static function getFontFullPath( $file, $fontdir = false ) {
		$fontfile = '';
		if ( ( $fontdir !== false ) and @WMS_TCPDF_STATIC::file_exists( $fontdir . $file ) ) {
			$fontfile = $fontdir . $file;
		} elseif ( @WMS_TCPDF_STATIC::file_exists( self::_getfontpath() . $file ) ) {
			$fontfile = self::_getfontpath() . $file;
		} elseif ( @WMS_TCPDF_STATIC::file_exists( $file ) ) {
			$fontfile = $file;
		}
		return $fontfile;
	}




	public static function getFontRefSize( $size, $refsize = 12 ) {
		switch ( $size ) {
			case 'xx-small': {
				$size = ( $refsize - 4 );
				break;
			}
			case 'x-small': {
				$size = ( $refsize - 3 );
				break;
			}
			case 'small': {
				$size = ( $refsize - 2 );
				break;
			}
			case 'medium': {
				$size = $refsize;
				break;
			}
			case 'large': {
				$size = ( $refsize + 2 );
				break;
			}
			case 'x-large': {
				$size = ( $refsize + 4 );
				break;
			}
			case 'xx-large': {
				$size = ( $refsize + 6 );
				break;
			}
			case 'smaller': {
				$size = ( $refsize - 3 );
				break;
			}
			case 'larger': {
				$size = ( $refsize + 3 );
				break;
			}
		}
		return $size;
	}
















































	public static function unichr( $c, $unicode = true ) {
		$c = intval( $c );
		if ( ! $unicode ) {
			return chr( $c );
		} elseif ( $c <= 0x7F ) {
			return chr( $c );
		} elseif ( $c <= 0x7FF ) {
			return chr( 0xC0 | $c >> 6 ) . chr( 0x80 | $c & 0x3F );
		} elseif ( $c <= 0xFFFF ) {
			return chr( 0xE0 | $c >> 12 ) . chr( 0x80 | $c >> 6 & 0x3F ) . chr( 0x80 | $c & 0x3F );
		} elseif ( $c <= 0x10FFFF ) {
			return chr( 0xF0 | $c >> 18 ) . chr( 0x80 | $c >> 12 & 0x3F ) . chr( 0x80 | $c >> 6 & 0x3F ) . chr( 0x80 | $c & 0x3F );
		} else {
			return '';
		}
	}

	public static function unichrUnicode( $c ) {
		return self::unichr( $c, true );
	}

	public static function unichrASCII( $c ) {
		return self::unichr( $c, false );
	}

	public static function arrUTF8ToUTF16BE( $unicode, $setbom = false ) {
		$outstr = ''; // string to be returned
		if ( $setbom ) {
			$outstr .= "\xFE\xFF"; // Byte Order Mark (BOM)
		}
		foreach ( $unicode as $char ) {
			if ( $char == 0x200b ) {
			} elseif ( $char == 0xFFFD ) {
				$outstr .= "\xFF\xFD"; // replacement character
			} elseif ( $char < 0x10000 ) {
				$outstr .= chr( $char >> 0x08 );
				$outstr .= chr( $char & 0xFF );
			} else {
				$char -= 0x10000;
				$w1 = 0xD800 | ( $char >> 0x0a );
				$w2 = 0xDC00 | ( $char & 0x3FF );
				$outstr .= chr( $w1 >> 0x08 );
				$outstr .= chr( $w1 & 0xFF );
				$outstr .= chr( $w2 >> 0x08 );
				$outstr .= chr( $w2 & 0xFF );
			}
		}
		return $outstr;
	}

	public static function UTF8ArrayToUniArray( $ta, $isunicode = true ) {
		if ( $isunicode ) {
			return array_map( array( 'WMS_TCPDF_FONTS', 'unichrUnicode' ), $ta );
		}
		return array_map( array( 'WMS_TCPDF_FONTS', 'unichrASCII' ), $ta );
	}

	public static function UTF8ArrSubString( $strarr, $start = '', $end = '', $unicode = true ) {
		if ( strlen( $start ) == 0 ) {
			$start = 0;
		}
		if ( strlen( $end ) == 0 ) {
			$end = count( $strarr );
		}
		$string = '';
		for ( $i = $start; $i < $end; ++$i ) {
			$string .= self::unichr( $strarr[ $i ], $unicode );
		}
		return $string;
	}

	public static function UniArrSubString( $uniarr, $start = '', $end = '' ) {
		if ( strlen( $start ) == 0 ) {
			$start = 0;
		}
		if ( strlen( $end ) == 0 ) {
			$end = count( $uniarr );
		}
		$string = '';
		for ( $i = $start; $i < $end; ++$i ) {
			$string .= $uniarr[ $i ];
		}
		return $string;
	}

	public static function UTF8ArrToLatin1Arr( $unicode ) {
		$outarr = array(); // array to be returned
		foreach ( $unicode as $char ) {
			if ( $char < 256 ) {
				$outarr[] = $char;
			} elseif ( array_key_exists( $char, WMS_TCPDF_FONT_DATA::$uni_utf8tolatin ) ) {
				$outarr[] = WMS_TCPDF_FONT_DATA::$uni_utf8tolatin[ $char ];
			} elseif ( $char == 0xFFFD ) {
			} else {
				$outarr[] = 63; // '?' character
			}
		}
		return $outarr;
	}

	public static function UTF8ArrToLatin1( $unicode ) {
		$outstr = ''; // string to be returned
		foreach ( $unicode as $char ) {
			if ( $char < 256 ) {
				$outstr .= chr( $char );
			} elseif ( array_key_exists( $char, WMS_TCPDF_FONT_DATA::$uni_utf8tolatin ) ) {
				$outstr .= chr( WMS_TCPDF_FONT_DATA::$uni_utf8tolatin[ $char ] );
			} elseif ( $char == 0xFFFD ) {
			} else {
				$outstr .= '?';
			}
		}
		return $outstr;
	}

	public static function uniord( $uch ) {
		if ( ! isset( self::$cache_uniord[ $uch ] ) ) {
			self::$cache_uniord[ $uch ] = self::getUniord( $uch );
		}
		return self::$cache_uniord[ $uch ];
	}

	public static function getUniord( $uch ) {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			list( , $char ) = @unpack( 'N', mb_convert_encoding( $uch, 'UCS-4BE', 'UTF-8' ) );
			if ( $char >= 0 ) {
				return $char;
			}
		}
		$bytes = array(); // array containing single character byte sequences
		$countbytes = 0;
		$numbytes = 1; // number of octetc needed to represent the UTF-8 character
		$length = strlen( $uch );
		for ( $i = 0; $i < $length; ++$i ) {
			$char = ord( $uch[ $i ] ); // get one string character at time
			if ( $countbytes == 0 ) { // get starting octect
				if ( $char <= 0x7F ) {
					return $char; // use the character "as is" because is ASCII
				} elseif ( ( $char >> 0x05 ) == 0x06 ) { // 2 bytes character (0x06 = 110 BIN)
					$bytes[] = ( $char - 0xC0 ) << 0x06;
					++$countbytes;
					$numbytes = 2;
				} elseif ( ( $char >> 0x04 ) == 0x0E ) { // 3 bytes character (0x0E = 1110 BIN)
					$bytes[] = ( $char - 0xE0 ) << 0x0C;
					++$countbytes;
					$numbytes = 3;
				} elseif ( ( $char >> 0x03 ) == 0x1E ) { // 4 bytes character (0x1E = 11110 BIN)
					$bytes[] = ( $char - 0xF0 ) << 0x12;
					++$countbytes;
					$numbytes = 4;
				} else {
					return 0xFFFD;
				}
			} elseif ( ( $char >> 0x06 ) == 0x02 ) { // bytes 2, 3 and 4 must start with 0x02 = 10 BIN
				$bytes[] = $char - 0x80;
				++$countbytes;
				if ( $countbytes == $numbytes ) {
					$char = $bytes[0];
					for ( $j = 1; $j < $numbytes; ++$j ) {
						$char += ( $bytes[ $j ] << ( ( $numbytes - $j - 1 ) * 0x06 ) );
					}
					if ( ( ( $char >= 0xD800 ) and ( $char <= 0xDFFF ) ) or ( $char >= 0x10FFFF ) ) {
						return 0xFFFD; // use replacement character
					} else {
						return $char;
					}
				}
			} else {
				return 0xFFFD;
			}
		}
		return 0xFFFD;
	}

	public static function UTF8StringToArray( $str, $isunicode = true, &$currentfont ) {
		if ( $isunicode ) {
			$chars = WMS_TCPDF_STATIC::pregSplit( '//', 'u', $str, -1, PREG_SPLIT_NO_EMPTY );
			$carr = array_map( array( 'WMS_TCPDF_FONTS', 'uniord' ), $chars );
		} else {
			$chars = str_split( $str );
			$carr = array_map( 'ord', $chars );
		}
		if ( is_array( $currentfont['subsetchars'] ) && is_array( $carr ) ) {
			$currentfont['subsetchars'] += array_fill_keys( $carr, true );
		} else {
			$currentfont['subsetchars'] = array_merge( $currentfont['subsetchars'], $carr );
		}
		return $carr;
	}

	public static function UTF8ToLatin1( $str, $isunicode = true, &$currentfont ) {
		$unicode = self::UTF8StringToArray( $str, $isunicode, $currentfont ); // array containing UTF-8 unicode values
		return self::UTF8ArrToLatin1( $unicode );
	}

	public static function UTF8ToUTF16BE( $str, $setbom = false, $isunicode = true, &$currentfont ) {
		if ( ! $isunicode ) {
			return $str; // string is not in unicode
		}
		$unicode = self::UTF8StringToArray( $str, $isunicode, $currentfont ); // array containing UTF-8 unicode values
		return self::arrUTF8ToUTF16BE( $unicode, $setbom );
	}

	public static function utf8StrRev( $str, $setbom = false, $forcertl = false, $isunicode = true, &$currentfont ) {
		return self::utf8StrArrRev( self::UTF8StringToArray( $str, $isunicode, $currentfont ), $str, $setbom, $forcertl, $isunicode, $currentfont );
	}

	public static function utf8StrArrRev( $arr, $str = '', $setbom = false, $forcertl = false, $isunicode = true, &$currentfont ) {
		return self::arrUTF8ToUTF16BE( self::utf8Bidi( $arr, $str, $forcertl, $isunicode, $currentfont ), $setbom );
	}

	public static function utf8Bidi( $ta, $str = '', $forcertl = false, $isunicode = true, &$currentfont ) {
		$pel = 0;
		$maxlevel = 0;
		if ( WMS_TCPDF_STATIC::empty_string( $str ) ) {
			$str = self::UTF8ArrSubString( $ta, '', '', $isunicode );
		}
		if ( preg_match( WMS_TCPDF_FONT_DATA::$uni_RE_PATTERN_ARABIC, $str ) ) {
			$arabic = true;
		} else {
			$arabic = false;
		}
		if ( ! ( $forcertl or $arabic or preg_match( WMS_TCPDF_FONT_DATA::$uni_RE_PATTERN_RTL, $str ) ) ) {
			return $ta;
		}

		$numchars = count( $ta );

		if ( $forcertl == 'R' ) {
			$pel = 1;
		} elseif ( $forcertl == 'L' ) {
			$pel = 0;
		} else {
			for ( $i = 0; $i < $numchars; ++$i ) {
				$type = WMS_TCPDF_FONT_DATA::$uni_type[ $ta[ $i ] ];
				if ( $type == 'L' ) {
					$pel = 0;
					break;
				} elseif ( ( $type == 'AL' ) or ( $type == 'R' ) ) {
					$pel = 1;
					break;
				}
			}
		}

		$cel = $pel;
		$dos = 'N';
		$remember = array();
		$sor = $pel % 2 ? 'R' : 'L';
		$eor = $sor;

		$chardata = array();

		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( $ta[ $i ] == WMS_TCPDF_FONT_DATA::$uni_RLE ) {
				$next_level = $cel + ( $cel % 2 ) + 1;
				if ( $next_level < 62 ) {
					$remember[] = array( 'num' => WMS_TCPDF_FONT_DATA::$uni_RLE, 'cel' => $cel, 'dos' => $dos );
					$cel = $next_level;
					$dos = 'N';
					$sor = $eor;
					$eor = $cel % 2 ? 'R' : 'L';
				}
			} elseif ( $ta[ $i ] == WMS_TCPDF_FONT_DATA::$uni_LRE ) {
				$next_level = $cel + 2 - ( $cel % 2 );
				if ( $next_level < 62 ) {
					$remember[] = array( 'num' => WMS_TCPDF_FONT_DATA::$uni_LRE, 'cel' => $cel, 'dos' => $dos );
					$cel = $next_level;
					$dos = 'N';
					$sor = $eor;
					$eor = $cel % 2 ? 'R' : 'L';
				}
			} elseif ( $ta[ $i ] == WMS_TCPDF_FONT_DATA::$uni_RLO ) {
				$next_level = $cel + ( $cel % 2 ) + 1;
				if ( $next_level < 62 ) {
					$remember[] = array( 'num' => WMS_TCPDF_FONT_DATA::$uni_RLO, 'cel' => $cel, 'dos' => $dos );
					$cel = $next_level;
					$dos = 'R';
					$sor = $eor;
					$eor = $cel % 2 ? 'R' : 'L';
				}
			} elseif ( $ta[ $i ] == WMS_TCPDF_FONT_DATA::$uni_LRO ) {
				$next_level = $cel + 2 - ( $cel % 2 );
				if ( $next_level < 62 ) {
					$remember[] = array( 'num' => WMS_TCPDF_FONT_DATA::$uni_LRO, 'cel' => $cel, 'dos' => $dos );
					$cel = $next_level;
					$dos = 'L';
					$sor = $eor;
					$eor = $cel % 2 ? 'R' : 'L';
				}
			} elseif ( $ta[ $i ] == WMS_TCPDF_FONT_DATA::$uni_PDF ) {
				if ( count( $remember ) ) {
					$last = count( $remember ) - 1;
					if ( ( $remember[ $last ]['num'] == WMS_TCPDF_FONT_DATA::$uni_RLE ) or
						( $remember[ $last ]['num'] == WMS_TCPDF_FONT_DATA::$uni_LRE ) or
						( $remember[ $last ]['num'] == WMS_TCPDF_FONT_DATA::$uni_RLO ) or
						( $remember[ $last ]['num'] == WMS_TCPDF_FONT_DATA::$uni_LRO ) ) {
						$match = array_pop( $remember );
						$cel = $match['cel'];
						$dos = $match['dos'];
						$sor = $eor;
						$eor = ( $cel > $match['cel'] ? $cel : $match['cel'] ) % 2 ? 'R' : 'L';
					}
				}
			} elseif ( ( $ta[ $i ] != WMS_TCPDF_FONT_DATA::$uni_RLE ) and
				( $ta[ $i ] != WMS_TCPDF_FONT_DATA::$uni_LRE ) and
				( $ta[ $i ] != WMS_TCPDF_FONT_DATA::$uni_RLO ) and
				( $ta[ $i ] != WMS_TCPDF_FONT_DATA::$uni_LRO ) and
				( $ta[ $i ] != WMS_TCPDF_FONT_DATA::$uni_PDF ) ) {
				if ( $dos != 'N' ) {
					$chardir = $dos;
				} else {
					if ( isset( WMS_TCPDF_FONT_DATA::$uni_type[ $ta[ $i ] ] ) ) {
						$chardir = WMS_TCPDF_FONT_DATA::$uni_type[ $ta[ $i ] ];
					} else {
						$chardir = 'L';
					}
				}
				$chardata[] = array( 'char' => $ta[ $i ], 'level' => $cel, 'type' => $chardir, 'sor' => $sor, 'eor' => $eor );
			}
		} // end for each char


		$numchars = count( $chardata );

		$prevlevel = -1; // track level changes
		$levcount = 0; // counts consecutive chars at the same level
		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( $chardata[ $i ]['type'] == 'NSM' ) {
				if ( $levcount ) {
					$chardata[ $i ]['type'] = $chardata[ $i ]['sor'];
				} elseif ( $i > 0 ) {
					$chardata[ $i ]['type'] = $chardata[ ( $i - 1 ) ]['type'];
				}
			}
			if ( $chardata[ $i ]['level'] != $prevlevel ) {
				$levcount = 0;
			} else {
				++$levcount;
			}
			$prevlevel = $chardata[ $i ]['level'];
		}

		$prevlevel = -1;
		$levcount = 0;
		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( $chardata[ $i ]['char'] == 'EN' ) {
				for ( $j = $levcount; $j >= 0; $j-- ) {
					if ( $chardata[ $j ]['type'] == 'AL' ) {
						$chardata[ $i ]['type'] = 'AN';
					} elseif ( ( $chardata[ $j ]['type'] == 'L' ) or ( $chardata[ $j ]['type'] == 'R' ) ) {
						break;
					}
				}
			}
			if ( $chardata[ $i ]['level'] != $prevlevel ) {
				$levcount = 0;
			} else {
				++$levcount;
			}
			$prevlevel = $chardata[ $i ]['level'];
		}

		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( $chardata[ $i ]['type'] == 'AL' ) {
				$chardata[ $i ]['type'] = 'R';
			}
		}

		$prevlevel = -1;
		$levcount = 0;
		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( ( $levcount > 0 ) and ( ( $i + 1 ) < $numchars ) and ( $chardata[ ( $i + 1 ) ]['level'] == $prevlevel ) ) {
				if ( ( $chardata[ $i ]['type'] == 'ES' ) and ( $chardata[ ( $i - 1 ) ]['type'] == 'EN' ) and ( $chardata[ ( $i + 1 ) ]['type'] == 'EN' ) ) {
					$chardata[ $i ]['type'] = 'EN';
				} elseif ( ( $chardata[ $i ]['type'] == 'CS' ) and ( $chardata[ ( $i - 1 ) ]['type'] == 'EN' ) and ( $chardata[ ( $i + 1 ) ]['type'] == 'EN' ) ) {
					$chardata[ $i ]['type'] = 'EN';
				} elseif ( ( $chardata[ $i ]['type'] == 'CS' ) and ( $chardata[ ( $i - 1 ) ]['type'] == 'AN' ) and ( $chardata[ ( $i + 1 ) ]['type'] == 'AN' ) ) {
					$chardata[ $i ]['type'] = 'AN';
				}
			}
			if ( $chardata[ $i ]['level'] != $prevlevel ) {
				$levcount = 0;
			} else {
				++$levcount;
			}
			$prevlevel = $chardata[ $i ]['level'];
		}

		$prevlevel = -1;
		$levcount = 0;
		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( $chardata[ $i ]['type'] == 'ET' ) {
				if ( ( $levcount > 0 ) and ( $chardata[ ( $i - 1 ) ]['type'] == 'EN' ) ) {
					$chardata[ $i ]['type'] = 'EN';
				} else {
					$j = $i + 1;
					while ( ( $j < $numchars ) and ( $chardata[ $j ]['level'] == $prevlevel ) ) {
						if ( $chardata[ $j ]['type'] == 'EN' ) {
							$chardata[ $i ]['type'] = 'EN';
							break;
						} elseif ( $chardata[ $j ]['type'] != 'ET' ) {
							break;
						}
						++$j;
					}
				}
			}
			if ( $chardata[ $i ]['level'] != $prevlevel ) {
				$levcount = 0;
			} else {
				++$levcount;
			}
			$prevlevel = $chardata[ $i ]['level'];
		}

		$prevlevel = -1;
		$levcount = 0;
		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( ( $chardata[ $i ]['type'] == 'ET' ) or ( $chardata[ $i ]['type'] == 'ES' ) or ( $chardata[ $i ]['type'] == 'CS' ) ) {
				$chardata[ $i ]['type'] = 'ON';
			}
			if ( $chardata[ $i ]['level'] != $prevlevel ) {
				$levcount = 0;
			} else {
				++$levcount;
			}
			$prevlevel = $chardata[ $i ]['level'];
		}

		$prevlevel = -1;
		$levcount = 0;
		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( $chardata[ $i ]['char'] == 'EN' ) {
				for ( $j = $levcount; $j >= 0; $j-- ) {
					if ( $chardata[ $j ]['type'] == 'L' ) {
						$chardata[ $i ]['type'] = 'L';
					} elseif ( $chardata[ $j ]['type'] == 'R' ) {
						break;
					}
				}
			}
			if ( $chardata[ $i ]['level'] != $prevlevel ) {
				$levcount = 0;
			} else {
				++$levcount;
			}
			$prevlevel = $chardata[ $i ]['level'];
		}

		$prevlevel = -1;
		$levcount = 0;
		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( ( $levcount > 0 ) and ( ( $i + 1 ) < $numchars ) and ( $chardata[ ( $i + 1 ) ]['level'] == $prevlevel ) ) {
				if ( ( $chardata[ $i ]['type'] == 'N' ) and ( $chardata[ ( $i - 1 ) ]['type'] == 'L' ) and ( $chardata[ ( $i + 1 ) ]['type'] == 'L' ) ) {
					$chardata[ $i ]['type'] = 'L';
				} elseif ( ( $chardata[ $i ]['type'] == 'N' ) and
					( ( $chardata[ ( $i - 1 ) ]['type'] == 'R' ) or ( $chardata[ ( $i - 1 ) ]['type'] == 'EN' ) or ( $chardata[ ( $i - 1 ) ]['type'] == 'AN' ) ) and
					( ( $chardata[ ( $i + 1 ) ]['type'] == 'R' ) or ( $chardata[ ( $i + 1 ) ]['type'] == 'EN' ) or ( $chardata[ ( $i + 1 ) ]['type'] == 'AN' ) ) ) {
					$chardata[ $i ]['type'] = 'R';
				} elseif ( $chardata[ $i ]['type'] == 'N' ) {
					$chardata[ $i ]['type'] = $chardata[ $i ]['sor'];
				}
			} elseif ( ( $levcount == 0 ) and ( ( $i + 1 ) < $numchars ) and ( $chardata[ ( $i + 1 ) ]['level'] == $prevlevel ) ) {
				if ( ( $chardata[ $i ]['type'] == 'N' ) and ( $chardata[ $i ]['sor'] == 'L' ) and ( $chardata[ ( $i + 1 ) ]['type'] == 'L' ) ) {
					$chardata[ $i ]['type'] = 'L';
				} elseif ( ( $chardata[ $i ]['type'] == 'N' ) and
					( ( $chardata[ $i ]['sor'] == 'R' ) or ( $chardata[ $i ]['sor'] == 'EN' ) or ( $chardata[ $i ]['sor'] == 'AN' ) ) and
					( ( $chardata[ ( $i + 1 ) ]['type'] == 'R' ) or ( $chardata[ ( $i + 1 ) ]['type'] == 'EN' ) or ( $chardata[ ( $i + 1 ) ]['type'] == 'AN' ) ) ) {
					$chardata[ $i ]['type'] = 'R';
				} elseif ( $chardata[ $i ]['type'] == 'N' ) {
					$chardata[ $i ]['type'] = $chardata[ $i ]['sor'];
				}
			} elseif ( ( $levcount > 0 ) and ( ( ( $i + 1 ) == $numchars ) or ( ( $i + 1 ) < $numchars ) and ( $chardata[ ( $i + 1 ) ]['level'] != $prevlevel ) ) ) {
				if ( ( $chardata[ $i ]['type'] == 'N' ) and ( $chardata[ ( $i - 1 ) ]['type'] == 'L' ) and ( $chardata[ $i ]['eor'] == 'L' ) ) {
					$chardata[ $i ]['type'] = 'L';
				} elseif ( ( $chardata[ $i ]['type'] == 'N' ) and
					( ( $chardata[ ( $i - 1 ) ]['type'] == 'R' ) or ( $chardata[ ( $i - 1 ) ]['type'] == 'EN' ) or ( $chardata[ ( $i - 1 ) ]['type'] == 'AN' ) ) and
					( ( $chardata[ $i ]['eor'] == 'R' ) or ( $chardata[ $i ]['eor'] == 'EN' ) or ( $chardata[ $i ]['eor'] == 'AN' ) ) ) {
					$chardata[ $i ]['type'] = 'R';
				} elseif ( $chardata[ $i ]['type'] == 'N' ) {
					$chardata[ $i ]['type'] = $chardata[ $i ]['sor'];
				}
			} elseif ( $chardata[ $i ]['type'] == 'N' ) {
				$chardata[ $i ]['type'] = $chardata[ $i ]['sor'];
			}
			if ( $chardata[ $i ]['level'] != $prevlevel ) {
				$levcount = 0;
			} else {
				++$levcount;
			}
			$prevlevel = $chardata[ $i ]['level'];
		}

		for ( $i = 0; $i < $numchars; ++$i ) {
			$odd = $chardata[ $i ]['level'] % 2;
			if ( $odd ) {
				if ( ( $chardata[ $i ]['type'] == 'L' ) or ( $chardata[ $i ]['type'] == 'AN' ) or ( $chardata[ $i ]['type'] == 'EN' ) ) {
					$chardata[ $i ]['level'] += 1;
				}
			} else {
				if ( $chardata[ $i ]['type'] == 'R' ) {
					$chardata[ $i ]['level'] += 1;
				} elseif ( ( $chardata[ $i ]['type'] == 'AN' ) or ( $chardata[ $i ]['type'] == 'EN' ) ) {
					$chardata[ $i ]['level'] += 2;
				}
			}
			$maxlevel = max( $chardata[ $i ]['level'], $maxlevel );
		}

		for ( $i = 0; $i < $numchars; ++$i ) {
			if ( ( $chardata[ $i ]['type'] == 'B' ) or ( $chardata[ $i ]['type'] == 'S' ) ) {
				$chardata[ $i ]['level'] = $pel;
			} elseif ( $chardata[ $i ]['type'] == 'WS' ) {
				$j = $i + 1;
				while ( $j < $numchars ) {
					if ( ( ( $chardata[ $j ]['type'] == 'B' ) or ( $chardata[ $j ]['type'] == 'S' ) ) or
						( ( $j == ( $numchars - 1 ) ) and ( $chardata[ $j ]['type'] == 'WS' ) ) ) {
						$chardata[ $i ]['level'] = $pel;
						break;
					} elseif ( $chardata[ $j ]['type'] != 'WS' ) {
						break;
					}
					++$j;
				}
			}
		}

		if ( $arabic ) {
			$endedletter = array( 1569, 1570, 1571, 1572, 1573, 1575, 1577, 1583, 1584, 1585, 1586, 1608, 1688 );
			$alfletter = array( 1570, 1571, 1573, 1575 );
			$chardata2 = $chardata;
			$laaletter = false;
			$charAL = array();
			$x = 0;
			for ( $i = 0; $i < $numchars; ++$i ) {
				if ( ( WMS_TCPDF_FONT_DATA::$uni_type[ $chardata[ $i ]['char'] ] == 'AL' ) or ( $chardata[ $i ]['char'] == 32 ) or ( $chardata[ $i ]['char'] == 8204 ) ) {
					$charAL[ $x ] = $chardata[ $i ];
					$charAL[ $x ]['i'] = $i;
					$chardata[ $i ]['x'] = $x;
					++$x;
				}
			}
			$numAL = $x;
			for ( $i = 0; $i < $numchars; ++$i ) {
				$thischar = $chardata[ $i ];
				if ( $i > 0 ) {
					$prevchar = $chardata[ ( $i - 1 ) ];
				} else {
					$prevchar = false;
				}
				if ( ( $i + 1 ) < $numchars ) {
					$nextchar = $chardata[ ( $i + 1 ) ];
				} else {
					$nextchar = false;
				}
				if ( WMS_TCPDF_FONT_DATA::$uni_type[ $thischar['char'] ] == 'AL' ) {
					$x = $thischar['x'];
					if ( $x > 0 ) {
						$prevchar = $charAL[ ( $x - 1 ) ];
					} else {
						$prevchar = false;
					}
					if ( ( $x + 1 ) < $numAL ) {
						$nextchar = $charAL[ ( $x + 1 ) ];
					} else {
						$nextchar = false;
					}
					if ( ( $prevchar !== false ) and ( $prevchar['char'] == 1604 ) and ( in_array( $thischar['char'], $alfletter ) ) ) {
						$arabicarr = WMS_TCPDF_FONT_DATA::$uni_laa_array;
						$laaletter = true;
						if ( $x > 1 ) {
							$prevchar = $charAL[ ( $x - 2 ) ];
						} else {
							$prevchar = false;
						}
					} else {
						$arabicarr = WMS_TCPDF_FONT_DATA::$uni_arabicsubst;
						$laaletter = false;
					}
					if ( ( $prevchar !== false ) and ( $nextchar !== false ) and
						( ( WMS_TCPDF_FONT_DATA::$uni_type[ $prevchar['char'] ] == 'AL' ) or ( WMS_TCPDF_FONT_DATA::$uni_type[ $prevchar['char'] ] == 'NSM' ) ) and
						( ( WMS_TCPDF_FONT_DATA::$uni_type[ $nextchar['char'] ] == 'AL' ) or ( WMS_TCPDF_FONT_DATA::$uni_type[ $nextchar['char'] ] == 'NSM' ) ) and
						( $prevchar['type'] == $thischar['type'] ) and
						( $nextchar['type'] == $thischar['type'] ) and
						( $nextchar['char'] != 1567 ) ) {
						if ( in_array( $prevchar['char'], $endedletter ) ) {
							if ( isset( $arabicarr[ $thischar['char'] ][2] ) ) {
								$chardata2[ $i ]['char'] = $arabicarr[ $thischar['char'] ][2];
							}
						} else {
							if ( isset( $arabicarr[ $thischar['char'] ][3] ) ) {
								$chardata2[ $i ]['char'] = $arabicarr[ $thischar['char'] ][3];
							}
						}
					} elseif ( ( $nextchar !== false ) and
						( ( WMS_TCPDF_FONT_DATA::$uni_type[ $nextchar['char'] ] == 'AL' ) or ( WMS_TCPDF_FONT_DATA::$uni_type[ $nextchar['char'] ] == 'NSM' ) ) and
						( $nextchar['type'] == $thischar['type'] ) and
						( $nextchar['char'] != 1567 ) ) {
						if ( isset( $arabicarr[ $chardata[ $i ]['char'] ][2] ) ) {
							$chardata2[ $i ]['char'] = $arabicarr[ $thischar['char'] ][2];
						}
					} elseif ( ( ( $prevchar !== false ) and
						( ( WMS_TCPDF_FONT_DATA::$uni_type[ $prevchar['char'] ] == 'AL' ) or ( WMS_TCPDF_FONT_DATA::$uni_type[ $prevchar['char'] ] == 'NSM' ) ) and
						( $prevchar['type'] == $thischar['type'] ) ) or
						( ( $nextchar !== false ) and ( $nextchar['char'] == 1567 ) ) ) {
						if ( ( $i > 1 ) and ( $thischar['char'] == 1607 ) and
							( $chardata[ $i - 1 ]['char'] == 1604 ) and
							( $chardata[ $i - 2 ]['char'] == 1604 ) ) {
							$chardata2[ $i - 2 ]['char'] = false;
							$chardata2[ $i - 1 ]['char'] = false;
							$chardata2[ $i ]['char'] = 65010;
						} else {
							if ( ( $prevchar !== false ) and in_array( $prevchar['char'], $endedletter ) ) {
								if ( isset( $arabicarr[ $thischar['char'] ][0] ) ) {
									$chardata2[ $i ]['char'] = $arabicarr[ $thischar['char'] ][0];
								}
							} else {
								if ( isset( $arabicarr[ $thischar['char'] ][1] ) ) {
									$chardata2[ $i ]['char'] = $arabicarr[ $thischar['char'] ][1];
								}
							}
						}
					} elseif ( isset( $arabicarr[ $thischar['char'] ][0] ) ) {
						$chardata2[ $i ]['char'] = $arabicarr[ $thischar['char'] ][0];
					}
					if ( $laaletter ) {
						$chardata2[ ( $charAL[ ( $x - 1 ) ]['i'] ) ]['char'] = false;
					}
				} // end if AL (Arabic Letter)
			} // end for each char
			for ( $i = 0; $i < ( $numchars - 1 ); ++$i ) {
				if ( ( $chardata2[ $i ]['char'] == 1617 ) and ( isset( WMS_TCPDF_FONT_DATA::$uni_diacritics[ ( $chardata2[ $i + 1 ]['char'] ) ] ) ) ) {
					if ( isset( $currentfont['cw'][ ( WMS_TCPDF_FONT_DATA::$uni_diacritics[ ( $chardata2[ $i + 1 ]['char'] ) ] ) ] ) ) {
						$chardata2[ $i ]['char'] = false;
						$chardata2[ $i + 1 ]['char'] = WMS_TCPDF_FONT_DATA::$uni_diacritics[ ( $chardata2[ $i + 1 ]['char'] ) ];
					}
				}
			}
			foreach ( $chardata2 as $key => $value ) {
				if ( $value['char'] === false ) {
					unset( $chardata2[ $key ] );
				}
			}
			$chardata = array_values( $chardata2 );
			$numchars = count( $chardata );
			unset( $chardata2 );
			unset( $arabicarr );
			unset( $laaletter );
			unset( $charAL );
		}

		for ( $j = $maxlevel; $j > 0; $j-- ) {
			$ordarray = array();
			$revarr = array();
			$onlevel = false;
			for ( $i = 0; $i < $numchars; ++$i ) {
				if ( $chardata[ $i ]['level'] >= $j ) {
					$onlevel = true;
					if ( isset( WMS_TCPDF_FONT_DATA::$uni_mirror[ $chardata[ $i ]['char'] ] ) ) {
						$chardata[ $i ]['char'] = WMS_TCPDF_FONT_DATA::$uni_mirror[ $chardata[ $i ]['char'] ];
					}
					$revarr[] = $chardata[ $i ];
				} else {
					if ( $onlevel ) {
						$revarr = array_reverse( $revarr );
						$ordarray = array_merge( $ordarray, $revarr );
						$revarr = array();
						$onlevel = false;
					}
					$ordarray[] = $chardata[ $i ];
				}
			}
			if ( $onlevel ) {
				$revarr = array_reverse( $revarr );
				$ordarray = array_merge( $ordarray, $revarr );
			}
			$chardata = $ordarray;
		}
		$ordarray = array();
		foreach ( $chardata as $cd ) {
			$ordarray[] = $cd['char'];
			$currentfont['subsetchars'][ $cd['char'] ] = true;
		}
		return $ordarray;
	}

} // END OF TCPDF_FONTS CLASS

