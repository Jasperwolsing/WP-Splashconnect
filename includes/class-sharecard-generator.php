<?php

class STA_Sharecard_Generator {

    const CANVAS_W = 1080;
    const CANVAS_H = 1350; // 4:5

    public static function generate( int $post_id ): void {
        $settings = get_option( 'sta_sharecard', [] );
        $bg_id    = intval( $settings['bg_id'] ?? 0 );
        $elements = $settings['elements'] ?? [];

        $W = self::CANVAS_W;
        $H = self::CANVAS_H;

        // Build canvas from background image
        $canvas = null;
        if ( $bg_id ) {
            $bg_path = get_attached_file( $bg_id );
            if ( $bg_path && file_exists( $bg_path ) ) {
                $mime   = mime_content_type( $bg_path );
                $bg_src = null;
                if ( $mime === 'image/jpeg' ) $bg_src = @imagecreatefromjpeg( $bg_path );
                elseif ( $mime === 'image/png' )  $bg_src = @imagecreatefrompng( $bg_path );
                elseif ( $mime === 'image/webp' ) $bg_src = @imagecreatefromwebp( $bg_path );
                if ( $bg_src ) {
                    $canvas = imagecreatetruecolor( $W, $H );
                    imagecopyresampled( $canvas, $bg_src, 0, 0, 0, 0, $W, $H, imagesx( $bg_src ), imagesy( $bg_src ) );
                    imagedestroy( $bg_src );
                }
            }
        }

        if ( ! $canvas ) {
            $canvas = imagecreatetruecolor( $W, $H );
            $bg_col = imagecolorallocate( $canvas, 11, 28, 44 );
            imagefilledrectangle( $canvas, 0, 0, $W - 1, $H - 1, $bg_col );
        }

        imagealphablending( $canvas, true );

        $plugin_dir = STA_DIR;
        $font_bold  = $plugin_dir . 'assets/fonts/font-bold.ttf';
        $font_reg   = $plugin_dir . 'assets/fonts/font-regular.ttf';

        // percent to pixels
        function sc_px( $pct, $dim ) { return (int) round( $pct / 100 * $dim ); }

        // --- PHOTO ---
        $el_photo = isset( $elements['photo'] ) ? $elements['photo'] : null;
        if ( $el_photo ) {
            $thumb_id = get_post_thumbnail_id( $post_id );
            $path     = $thumb_id ? get_attached_file( $thumb_id ) : '';
            if ( $path && file_exists( $path ) ) {
                $x    = sc_px( $el_photo['x'], $W );
                $y    = sc_px( $el_photo['y'], $H );
                $w    = sc_px( $el_photo['w'], $W );
                $h    = sc_px( $el_photo['h'], $H );
                $mime = mime_content_type( $path );
                $src  = null;
                if ( $mime === 'image/jpeg' ) $src = @imagecreatefromjpeg( $path );
                elseif ( $mime === 'image/png' )  $src = @imagecreatefrompng( $path );
                elseif ( $mime === 'image/webp' ) $src = @imagecreatefromwebp( $path );
                if ( $src ) {
                    // Read focal point from sta_focus_x / sta_focus_y (default: center-x, 1/3 from top)
                    $focal_x = 0.50; $focal_y = 0.33;
                    $fx = get_post_meta( $post_id, 'sta_focus_x', true );
                    $fy = get_post_meta( $post_id, 'sta_focus_y', true );
                    if ( $fx !== '' && $fy !== '' ) {
                        $focal_x = (float) $fx / 100;
                        $focal_y = (float) $fy / 100;
                    }
                    $sw = imagesx( $src ); $sh = imagesy( $src );
                    $tr = $w / $h;         $sr = $sw / $sh;
                    if ( $sr > $tr ) {
                        $cw = (int) round( $sh * $tr ); $ch = $sh;
                        $cx = (int) round( ( $sw - $cw ) / 2 ); $cy = 0;
                    } else {
                        $cw = $sw; $ch = (int) round( $sw / $tr );
                        $cx = 0;   $cy = (int) round( ( $sh - $ch ) / 3 );
                    }
                    // Apply focal point to crop origin
                    if ( $sr > $tr ) { $cx = max( 0, min( (int) round( $sw * $focal_x - $cw / 2 ), $sw - $cw ) ); }
                    else              { $cy = max( 0, min( (int) round( $sh * $focal_y - $ch / 2 ), $sh - $ch ) ); }
                    $dst = imagecreatetruecolor( $w, $h );
                    imagecopyresampled( $dst, $src, 0, 0, $cx, $cy, $w, $h, $cw, $ch );
                    imagecopy( $canvas, $dst, $x, $y, 0, 0, $w, $h );
                    imagedestroy( $src ); imagedestroy( $dst );
                }
            }
        }

        // --- FLAG ---
        $el_flag = isset( $elements['flag'] ) ? $elements['flag'] : null;
        if ( $el_flag ) {
            $country = strtolower( trim( get_post_meta( $post_id, 'sta_country', true ) ) );
            if ( $country ) {
                $x          = sc_px( $el_flag['x'], $W );
                $y          = sc_px( $el_flag['y'], $H );
                $w          = sc_px( $el_flag['w'], $W );
                $cache_dir  = sys_get_temp_dir() . '/sta_flags/';
                $cache_file = $cache_dir . $country . '.png';
                if ( ! is_dir( $cache_dir ) ) @mkdir( $cache_dir, 0755, true );
                if ( ! file_exists( $cache_file ) ) {
                    $resp = wp_remote_get( 'https://flagcdn.com/w160/' . $country . '.png', array( 'timeout' => 5 ) );
                    $data = wp_remote_retrieve_body( $resp );
                    if ( $data ) file_put_contents( $cache_file, $data );
                }
                if ( file_exists( $cache_file ) ) {
                    $flag = @imagecreatefrompng( $cache_file );
                    if ( ! $flag ) $flag = @imagecreatefromjpeg( $cache_file );
                    if ( $flag ) {
                        $fh       = (int) round( $w * imagesy( $flag ) / imagesx( $flag ) );
                        $border   = 4; // px border thickness
                        $tmp = imagecreatetruecolor( $w, $fh );
                        imagealphablending( $tmp, false ); imagesavealpha( $tmp, true );
                        imagecopyresampled( $tmp, $flag, 0, 0, 0, 0, $w, $fh, imagesx( $flag ), imagesy( $flag ) );
                        imagecopy( $canvas, $tmp, $x, $y, 0, 0, $w, $fh );
                        imagedestroy( $flag ); imagedestroy( $tmp );
                        // Draw dark blue border around flag
                        $border_clr = self::hex_to_gd( $canvas, '#0d2d63' );
                        imagesetthickness( $canvas, $border );
                        imagerectangle( $canvas, $x, $y, $x + $w - 1, $y + $fh - 1, $border_clr );
                        imagesetthickness( $canvas, 1 );
                    }
                }
            }
        }

        // --- LOGO ---
        $el_logo = isset( $elements['logo'] ) ? $elements['logo'] : null;
        $logo_id = intval( $settings['logo_id'] ?? 0 );
        if ( $el_logo && $logo_id ) {
            $logo_path = get_attached_file( $logo_id );
            if ( $logo_path && file_exists( $logo_path ) ) {
                $x    = sc_px( $el_logo['x'], $W );
                $y    = sc_px( $el_logo['y'], $H );
                $w    = sc_px( $el_logo['w'], $W );
                $mime = mime_content_type( $logo_path );
                $src  = null;
                if ( $mime === 'image/jpeg' )      $src = @imagecreatefromjpeg( $logo_path );
                elseif ( $mime === 'image/png' )   $src = @imagecreatefrompng( $logo_path );
                elseif ( $mime === 'image/webp' )  $src = @imagecreatefromwebp( $logo_path );
                elseif ( $mime === 'image/gif' )   $src = @imagecreatefromgif( $logo_path );
                if ( $src ) {
                    $lw = imagesx( $src );
                    $lh = imagesy( $src );
                    $h  = (int) round( $w * $lh / $lw );
                    $dst = imagecreatetruecolor( $w, $h );
                    imagealphablending( $dst, false );
                    imagesavealpha( $dst, true );
                    $trans = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
                    imagefilledrectangle( $dst, 0, 0, $w - 1, $h - 1, $trans );
                    imagealphablending( $dst, true );
                    imagecopyresampled( $dst, $src, 0, 0, 0, 0, $w, $h, $lw, $lh );
                    imagecopy( $canvas, $dst, $x, $y, 0, 0, $w, $h );
                    imagedestroy( $src ); imagedestroy( $dst );
                }
            }
        }

        // --- EXTRAS ---
        foreach ( $settings['extras'] ?? [] as $extra ) {
            $eid = intval( $extra['id'] ?? 0 );
            if ( ! $eid ) continue;
            $extra_path = get_attached_file( $eid );
            if ( ! $extra_path || ! file_exists( $extra_path ) ) continue;
            $x    = sc_px( $extra['x'], $W );
            $y    = sc_px( $extra['y'], $H );
            $w    = sc_px( $extra['w'], $W );
            $mime = mime_content_type( $extra_path );
            $src  = null;
            if ( $mime === 'image/jpeg' )     $src = @imagecreatefromjpeg( $extra_path );
            elseif ( $mime === 'image/png' )  $src = @imagecreatefrompng( $extra_path );
            elseif ( $mime === 'image/webp' ) $src = @imagecreatefromwebp( $extra_path );
            elseif ( $mime === 'image/gif' )  $src = @imagecreatefromgif( $extra_path );
            if ( $src ) {
                $ew = imagesx( $src );
                $eh = imagesy( $src );
                $h  = (int) round( $w * $eh / $ew );
                $dst = imagecreatetruecolor( $w, $h );
                imagealphablending( $dst, false );
                imagesavealpha( $dst, true );
                $trans = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
                imagefilledrectangle( $dst, 0, 0, $w - 1, $h - 1, $trans );
                imagealphablending( $dst, true );
                imagecopyresampled( $dst, $src, 0, 0, 0, 0, $w, $h, $ew, $eh );
                imagecopy( $canvas, $dst, $x, $y, 0, 0, $w, $h );
                imagedestroy( $src ); imagedestroy( $dst );
            }
        }

        // --- NAME (auto-shrink if too wide) ---
        $el_name = isset( $elements['name'] ) ? $elements['name'] : null;
        if ( $el_name && file_exists( $font_bold ) ) {
            $x        = sc_px( $el_name['x'], $W );
            $y        = sc_px( $el_name['y'], $H );
            $size     = intval( isset( $el_name['size'] ) ? $el_name['size'] : 72 );
            $color    = self::hex_to_gd( $canvas, isset( $el_name['color'] ) ? $el_name['color'] : '#ffffff' );
            $name     = get_the_title( $post_id );
            $max_w    = $W - $x - intval( $W * 0.04 );
            while ( $size > 20 ) {
                $bbox = imagettfbbox( $size, 0, $font_bold, $name );
                if ( abs( $bbox[2] - $bbox[0] ) <= $max_w ) break;
                $size -= 2;
            }
            imagettftext( $canvas, $size, 0, $x, $y + $size, $color, $font_bold, $name );
        }

        // --- EVENTS (pill shapes) ---
        $el_ev = isset( $elements['events'] ) ? $elements['events'] : null;
        if ( $el_ev && file_exists( $font_reg ) ) {
            $x        = sc_px( $el_ev['x'], $W );
            $y        = sc_px( $el_ev['y'], $H );
            $sz       = intval( isset( $el_ev['size'] ) ? $el_ev['size'] : 40 );
            $lh       = intval( isset( $el_ev['line_height'] ) ? $el_ev['line_height'] : (int) round( $sz * 1.8 ) );
            $txt_clr  = self::hex_to_gd( $canvas, isset( $el_ev['color'] ) ? $el_ev['color'] : '#ffffff' );
            $pill_clr   = self::hex_to_gd( $canvas, isset( $el_ev['pill_color'] ) ? $el_ev['pill_color'] : '#13315c' );
            $pill_shape = ( $el_ev['pill_shape'] ?? 'rounded' ) === 'rectangular' ? 'rectangular' : 'rounded';

            $pad_l = intval( $sz * 0.9 );  // left padding
            $pad_r = intval( $sz * 1.5 );  // right padding (wider)
            $pad_y = intval( $sz * 0.65 ); // vertical padding
            $pad_x = $pad_l;               // alias for text x offset

            $stroke_abbr = [
                'Butterfly'          => 'Fly',
                'Freestyle'          => 'Free',
                'Breaststroke'       => 'Breast',
                'Breatstroke'        => 'Breast',
                'Individual Medley'  => 'IM',
                'Backstroke'         => 'Back',
            ];

            $gap        = intval( $sz * 0.4 ); // horizontal gap between pills
            $max_right  = $W - intval( $W * 0.04 ); // right margin ~4% of canvas
            $cur_x      = $x;
            $cur_y      = $y;
            $pill_h     = $sz + $pad_y * 2;
            $r          = intval( $pill_h / 2 );

            $terms = wp_get_post_terms( $post_id, 'afstand', array( 'orderby' => 'name' ) );
            if ( ! is_wp_error( $terms ) ) {
                $gender = get_post_meta( $post_id, 'sta_gender', true );
                foreach ( $terms as $term ) {
                    $line = strtr( $term->name, $stroke_abbr );
                    // Day removed intentionally — pills show stroke only

                    $bbox   = imagettfbbox( $sz, 0, $font_reg, $line );
                    $tw     = abs( $bbox[2] - $bbox[0] );
                    $pill_w = $tw + $pad_l + $pad_r;

                    // Wrap to next row if pill doesn't fit
                    if ( $cur_x > $x && ( $cur_x + $pill_w ) > $max_right ) {
                        $cur_x  = $x;
                        $cur_y += $lh;
                    }

                    if ( $pill_shape === 'rectangular' ) {
                        imagefilledrectangle( $canvas, $cur_x, $cur_y, $cur_x + $pill_w, $cur_y + $pill_h, $pill_clr );
                    } else {
                        self::filled_rounded_rect( $canvas, $cur_x, $cur_y, $pill_w, $pill_h, $r, $pill_clr );
                    }
                    imagettftext( $canvas, $sz, 0, $cur_x + $pad_x, $cur_y + $pad_y + $sz, $txt_clr, $font_reg, $line );

                    $cur_x += $pill_w + $gap;
                }
            }
        }

        header( 'Content-Type: image/png' );
        header( 'Cache-Control: public, max-age=300' );
        header( 'Content-Disposition: inline; filename="profiel-est2026.png"' );
        imagepng( $canvas, null, 6 );
        imagedestroy( $canvas );
        exit;
    }

    private static function filled_rounded_rect( $img, int $x, int $y, int $w, int $h, int $r, int $color ): void {
        // Centre rectangle
        imagefilledrectangle( $img, $x + $r, $y, $x + $w - $r, $y + $h, $color );
        // Left/right strips
        imagefilledrectangle( $img, $x, $y + $r, $x + $r, $y + $h - $r, $color );
        imagefilledrectangle( $img, $x + $w - $r, $y + $r, $x + $w, $y + $h - $r, $color );
        // Four corner circles
        imagefilledellipse( $img, $x + $r,       $y + $r,       $r * 2, $r * 2, $color );
        imagefilledellipse( $img, $x + $w - $r,  $y + $r,       $r * 2, $r * 2, $color );
        imagefilledellipse( $img, $x + $r,       $y + $h - $r,  $r * 2, $r * 2, $color );
        imagefilledellipse( $img, $x + $w - $r,  $y + $h - $r,  $r * 2, $r * 2, $color );
    }

    private static function hex_to_gd( $img, string $hex ): int {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return imagecolorallocate( $img,
            hexdec( substr( $hex, 0, 2 ) ),
            hexdec( substr( $hex, 2, 2 ) ),
            hexdec( substr( $hex, 4, 2 ) )
        );
    }
}
