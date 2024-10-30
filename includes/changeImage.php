<?php
/**
 * 画像タイプを変換
 **
 * access：
 * param ：String $fileName   対象ファイルのフルパス
 *       ：String $fileType   出力ファイルの画像タイプ
 *                            gif  : 'gif'
 *                            jpeg : 'jpg', 'jpeg'
 *                            png  : 'png'
 *       ：String $outputName 出力ファイル名 option
 *                            指定の無い場合は元ファイル名を使用
 *       ：String $outputPath 出力ファイルパス option
 *                            指定の無い場合は元ファイルパスを使用
 * return：成功時 true
 *         GDライブラリ使用不可、未対応の画像タイプの場合 false
 *  notes：Author:ksol asada
 */
function changeImage($fileName, $fileType, $outputName = null, $outputPath = null)
{
    // 出力ファイル名をセット
    if (!isset($outputName)) {
        $basename = basename($fileName);
        if (($pos = strrpos($basename, '.')) === false) {
            $outputName = $basename. '.'. $fileType;
        } else {
            $outputName = substr($basename, 0, $pos). '.'. $fileType;
        }
    }

    // 出力ファイルパスをセット
    if (!isset($outputPath)) {
        $outputPath = dirname($fileName);
    }

    $imageFnc = '';
    switch ($fileType) {
        case 'gif':
            $imageFnc = 'imagegif';
            break;

        case 'jpg':
        case 'jpeg':
            $imageFnc = 'imagejpeg';
            break;

        case 'png':
            $imageFnc = 'imagepng';
            break;

        default:
            return false;
    }

    // 対象ファイルのタイプを取得
    if (!$arrImageInfo = @getimagesize($fileName)) {
        return false;
    }

    // 変換処理・出力処理
    $imageResourceFnc = '';
    switch ($arrImageInfo[2]) {
        case IMAGETYPE_GIF:
            $imageResourceFnc = 'imagecreatefromgif';
            break;

        case IMAGETYPE_JPEG:
            $imageResourceFnc = 'imagecreatefromjpeg';
            break;

        case IMAGETYPE_PNG:
            $imageResourceFnc = 'imagecreatefrompng';
            break;

        case IMAGETYPE_BMP:
            $imageResourceFnc = 'ImageCreateFromBMP';
            break;

        default:
            return false;
    }

    // GDライブラリ関数が使用できるか
    if (!function_exists($imageFnc) || !function_exists($imageResourceFnc)) {
        return false;
    }

    return $imageFnc($imageResourceFnc($fileName), $outputPath. DIRECTORY_SEPARATOR. $outputName);
}

/*********************************************/
/* Fonction: ImageCreateFromBMP              */
/* Author:   DHKold                          */
/* Contact:  admin@dhkold.com                */
/* Date:     The 15th of June 2005           */
/* Version:  2.0B                            */
/*********************************************/
function ImageCreateFromBMP($filename)
{
    //Ouverture du fichier en mode binaire
    if (! $f1 = fopen($filename,"rb")) return false;

    //1 : Chargement des ent tes FICHIER
    $FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1,14));
    if ($FILE['file_type'] != 19778) return false;

    //2 : Chargement des ent tes BMP
    $BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel'.
                  '/Vcompression/Vsize_bitmap/Vhoriz_resolution'.
                  '/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1,40));
    $BMP['colors'] = pow(2,$BMP['bits_per_pixel']);
    if ($BMP['size_bitmap'] == 0) $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
    $BMP['bytes_per_pixel'] = $BMP['bits_per_pixel']/8;
    $BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
    $BMP['decal'] = ($BMP['width']*$BMP['bytes_per_pixel']/4);
    $BMP['decal'] -= floor($BMP['width']*$BMP['bytes_per_pixel']/4);
    $BMP['decal'] = 4-(4*$BMP['decal']);
    if ($BMP['decal'] == 4) $BMP['decal'] = 0;

    //3 : Chargement des couleurs de la palette
    $PALETTE = array();
    if ($BMP['colors'] < 16777216)
    {
        $PALETTE = unpack('V'.$BMP['colors'], fread($f1,$BMP['colors']*4));
    }

    //4 : Creation de l'image
    $IMG = fread($f1,$BMP['size_bitmap']);
    $VIDE = chr(0);

    $res = imagecreatetruecolor($BMP['width'],$BMP['height']);
    $P = 0;
    $Y = $BMP['height']-1;
    while ($Y >= 0)
    {
        $X=0;
        while ($X < $BMP['width'])
        {
            if ($BMP['bits_per_pixel'] == 24)
                $COLOR = unpack("V",substr($IMG,$P,3).$VIDE);
            elseif ($BMP['bits_per_pixel'] == 16)
            { 
                $COLOR = unpack("n",substr($IMG,$P,2));
                $COLOR[1] = $PALETTE[$COLOR[1]+1];
            }
            elseif ($BMP['bits_per_pixel'] == 8)
            { 
                $COLOR = unpack("n",$VIDE.substr($IMG,$P,1));
                $COLOR[1] = $PALETTE[$COLOR[1]+1];
            }
            elseif ($BMP['bits_per_pixel'] == 4)
            {
                $COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
                if (($P*2)%2 == 0) $COLOR[1] = ($COLOR[1] >> 4) ; else $COLOR[1] = ($COLOR[1] & 0x0F);
                $COLOR[1] = $PALETTE[$COLOR[1]+1];
            }
            elseif ($BMP['bits_per_pixel'] == 1)
            {
                $COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
                if     (($P*8)%8 == 0) $COLOR[1] =  $COLOR[1]        >>7;
                elseif (($P*8)%8 == 1) $COLOR[1] = ($COLOR[1] & 0x40)>>6;
                elseif (($P*8)%8 == 2) $COLOR[1] = ($COLOR[1] & 0x20)>>5;
                elseif (($P*8)%8 == 3) $COLOR[1] = ($COLOR[1] & 0x10)>>4;
                elseif (($P*8)%8 == 4) $COLOR[1] = ($COLOR[1] & 0x8)>>3;
                elseif (($P*8)%8 == 5) $COLOR[1] = ($COLOR[1] & 0x4)>>2;
                elseif (($P*8)%8 == 6) $COLOR[1] = ($COLOR[1] & 0x2)>>1;
                elseif (($P*8)%8 == 7) $COLOR[1] = ($COLOR[1] & 0x1);
                $COLOR[1] = $PALETTE[$COLOR[1]+1];
            }
            else
                return false;
            imagesetpixel($res,$X,$Y,$COLOR[1]);
            $X++;
            $P += $BMP['bytes_per_pixel'];
        }
        $Y--;
        $P+=$BMP['decal'];
    }

    //Fermeture du fichier
    fclose($f1);

    return $res;
}

?>