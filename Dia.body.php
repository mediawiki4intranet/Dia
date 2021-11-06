<?php

function wfGetDIAsize($filename)
{
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

    // centimeters
    $width = $xmax - $xmin;
    $height = $ymax - $ymin;

    // convert to pixels
    $width = round($width*19.981);
    $height = round($height*19.981);

    return array($width, $height);
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
        {
            return false;
        }
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
        {
            return new TransformParameterError($params);
        }

        $clientWidth = $params['width'];
        $clientHeight = $params['height'];
        $physicalWidth = $params['physicalWidth'];
        $physicalHeight = $params['physicalHeight'];
        $srcPath = method_exists($image, 'getLocalRefPath') ? $image->getLocalRefPath() : $image->getPath();

        if ($flags & self::TRANSFORM_LATER)
        {
            return new ThumbnailImage($image, substr($dstUrl, 0, -4).'.svg', $dstPath, $params);
        }

        if (!wfMkdirParents(dirname($dstPath)))
        {
            return new MediaTransformError(
                'thumbnail_error', $clientWidth, $clientHeight,
                wfMessage('thumbnail_dest_directory')->text()
            );
        }

        $err = false;
        $conv = $wgDIAConverters[$wgDIAConverter];
        if ($conv)
        {
            $repl = array(
                '$path/'  => $wgDIAConverterPath ? wfEscapeShellArg("$wgDIAConverterPath/") : "",
                '$width'  => intval($physicalWidth),
                '$height' => intval($physicalHeight),
                '$input'  => wfEscapeShellArg($srcPath),
                '$output' => wfEscapeShellArg($dstPath),
                '$type'   => 'png',
            );
            $cmd = str_replace(array_keys($repl), array_values($repl), $conv) . " 2>&1";
            wfDebug(__METHOD__.": $cmd\n");
            $err = wfShellExec($cmd, $retval);
            if ($retval == 0)
            {
                $repl['$output'] = wfEscapeShellArg($dstPath.'.svg');
                $repl['$type'] = 'svg';
                $cmd = str_replace(array_keys($repl), array_values($repl), $conv) . " 2>&1";
                $err = wfShellExec($cmd, $retval);
                if ($retval == 0)
                {
                    // Maybe TODO: Dia generates the same .svg for all image sizes
                    $svgName = $image->thumbName($params + array('svg' => true));
                    $svgPath = $image->getThumbPath($svgName);
                    $status = $image->repo->quickImport($dstPath.'.svg', $svgPath, $image->getThumbDisposition($svgName));
                }
            }
        }

        $removed = $this->removeBadFile($dstPath, $retval);
        if ($retval != 0 || $removed)
        {
            wfDebugLog('thumbnail',
                sprintf('thumbnail failed on %s: error %d "%s" from "%s"',
                    wfHostname(), $retval, trim($err), $cmd));
            return new MediaTransformError('thumbnail_error', $clientWidth, $clientHeight, $err);
        }
        return new ThumbnailImage($image, substr($dstUrl, 0, -4).'.svg', $dstPath, $params);
    }

    function getImageSize($image, $path)
    {
        return wfGetDIAsize($path);
    }

    function getThumbType($ext, $mime, $params = NULL)
    {
        return array('svg', 'image/svg+xml');
    }

    function getLongDesc($file)
    {
        global $wgLang;
        return wfMessage(
            'dia-long-desc', $file->getWidth(), $file->getHeight(),
            $wgLang->formatSize($file->getSize())
        )->text();
    }
}
