<?php

function wfGetDIAsize($filename)
{
    global $wgDIANominalSize;

    $xmlstr = file_get_contents('compress.zlib://'.$filename);
    $xmlstr = str_replace("<dia:", "<", $xmlstr);
    $xmlstr = str_replace("</dia:", "</", $xmlstr);

    $xml = new SimpleXMLElement($xmlstr);

    $xmin = $ymin = $xmax = $ymax = -1;

    /*
     * Get the total bounding box (for correct aspect ratio).
     *
     * Note: the loop expects $x1<=$x2 and $y1<=$y2, but dia does this for you.
     */
    foreach($xml->xpath('//attribute[@name="obj_bb"]') as $boundingbox)
    {
        sscanf($boundingbox->rectangle['val'], "%f,%f;%f,%f", $x1, $y1, $x2, $y2);

        if ($xmin == -1 || $x1 < $xmin)
            $xmin = $x1;
        if ($xmax == -1 || $x2 > $xmax)
            $xmax = $x2;

        if ($ymin == -1 || $y1 < $ymin)
            $ymin = $y1;
        if ($ymax == -1 || $y2 > $ymax)
            $ymax = $y2;
    }

    $width = $xmax - $xmin;
    $height = $ymax - $ymin;

    $aspect = $width / $height;

    $nominal_width = $wgDIANominalSize;
    $nominal_height = (int)($nominal_width / $aspect);

    $res = array($nominal_width, $nominal_height);

    return $res;
}

/**
 * @addtogroup Media
 */
class DiaHandler extends ImageHandler
{
    function isEnabled()
    {
        global $wgDIAConverters, $wgDIAConverter;
        if (!isset($wgDIAConverters[$wgDIAConverter]))
        {
            wfDebug("\$wgDIAConverter is invalid, disabling DIA rendering.\n");
            return false;
        }
        return true;
    }

    function mustRender($file)
    {
        return true;
    }

    function canRender($file)
    {
        return true;
    }

    function normaliseParams($image, &$params)
    {
        global $wgDIAMaxSize;
        if (!parent::normaliseParams($image, $params))
            return false;

        // Don't make an image bigger than wgMaxDIASize
        $params['physicalWidth'] = $params['width'];
        $params['physicalHeight'] = $params['height'];
        if ($params['physicalWidth'] > $wgDIAMaxSize)
        {
            $srcWidth = $image->getWidth($params['page']);
            $srcHeight = $image->getHeight($params['page']);
            $params['physicalWidth'] = $wgDIAMaxSize;
            $params['physicalHeight'] = File::scaleHeight($srcWidth, $srcHeight, $wgDIAMaxSize);
        }
        return true;
    }

    function doTransform($image, $dstPath, $dstUrl, $params, $flags = 0)
    {
        global $wgDIAConverters, $wgDIAConverter, $wgDIAConverterPath;

        if (!$this->normaliseParams($image, $params))
            return new TransformParameterError($params);

        $clientWidth = $params['width'];
        $clientHeight = $params['height'];
        $physicalWidth = $params['physicalWidth'];
        $physicalHeight = $params['physicalHeight'];
        $srcPath = $image->getPath();

        if ($flags & self::TRANSFORM_LATER)
            return new ThumbnailImage($image, $dstUrl, $clientWidth, $clientHeight, $dstPath);

        if (!wfMkdirParents(dirname($dstPath)))
        {
            return new MediaTransformError(
                'thumbnail_error', $clientWidth, $clientHeight,
                wfMsg('thumbnail_dest_directory')
            );
        }

        $err = false;
        if (isset($wgDIAConverters[$wgDIAConverter]))
        {
            $cmd = str_replace(
                array('$path/', '$width', '$height', '$input', '$output'),
                array($wgDIAConverterPath ? wfEscapeShellArg("$wgDIAConverterPath/") : "",
                       intval($physicalWidth),
                       intval($physicalHeight),
                       wfEscapeShellArg($srcPath),
                       wfEscapeShellArg($dstPath)),
                $wgDIAConverters[$wgDIAConverter]) . " 2>&1";
            wfProfileIn('dia');
            wfDebug(__METHOD__.": $cmd\n");
            $err = wfShellExec($cmd, $retval);
            wfProfileOut('dia');
        }

        $removed = $this->removeBadFile($dstPath, $retval);
        if ($retval != 0 || $removed)
        {
            wfDebugLog('thumbnail',
                sprintf('thumbnail failed on %s: error %d "%s" from "%s"',
                    wfHostname(), $retval, trim($err), $cmd));
            return new MediaTransformError('thumbnail_error', $clientWidth, $clientHeight, $err);
        }
        else
            return new ThumbnailImage($image, $dstUrl, $clientWidth, $clientHeight, $dstPath);
    }

    function getImageSize($image, $path)
    {
        return wfGetDIAsize($path);
    }

    function getThumbType($ext, $mime)
    {
        return array('png', 'image/png');
    }

    function getLongDesc($file)
    {
        global $wgLang;
        wfLoadExtensionMessages('Dia');
        return wfMsg(
            'dia-long-desc', $file->getWidth(), $file->getHeight(),
            $wgLang->formatSize($file->getSize())
        );
    }
}
