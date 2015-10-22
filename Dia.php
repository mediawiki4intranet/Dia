<?php
/**
 * Setup for Dia media handler, an extension that allows Dia (http://live.gnome.org/Dia)
 * diagrams to be rendered in MediaWiki pages.
 *
 * Supports SVG rendering and MediaWiki 1.16+.
 * Gzipped Dia file support needs a patch to MediaWiki core.
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author Marcel Toele (http://dwarfhouse.org), Vitaliy Filippov (http://wiki.4intra.net)
 * @copyright © 2007 Marcel Toele, © 2011+ Vitaliy Filippov
 * @licence GNU General Public License 2.0 or later
 */

if (!defined('MEDIAWIKI'))
{
    echo("This file is an extension to the MediaWiki software and cannot be used standalone.\n");
    die(1);
}

// Credits
$wgExtensionCredits['other'][] = array(
    'name'        => 'Dia',
    'author'      => 'Marcel Toele, Vitaliy Filippov',
    'url'         => 'http://wiki.4intra.net/Dia',
    'description' => 'Allows Dia diagrams to be rendered inside MediaWiki pages.',
);

// Default Config

// Dia diagrams may be uploaded as drawings.
// Dia diagrams are converted to png before they can be rendered on a page.
// Future versions of the extension may also output SVG.
//
// An external program is required to perform this conversion:
$wgDIAConverters = array(
    // dia -n -e testdiagram.png -t png -s 100 testdiagram.dia
    'dia' => '$path/dia -n -e $output -t $type -s $width $input',
);
// Pick one of the above
$wgDIAConverter = 'dia';
// If not in the executable PATH, specify
$wgDIAConverterPath = '';
// Don't scale a Dia file larger than this
$wgDIAMaxSize = 1024;

// Add the DiaHandler via the Autoload mechanism.
$wgMediaHandlers['application/x-dia-diagram'] = 'DiaHandler';
$wgAutoloadClasses['DiaHandler'] = __DIR__ . '/Dia.body.php';
$wgExtensionMessagesFiles['Dia'] = __DIR__ . '/Dia.i18n.php';
if (!in_array('dia', $wgFileExtensions))
    $wgFileExtensions[] = 'dia';
$wgXMLMimeTypes['http://www.lysator.liu.se/~alla/dia/:diagram'] = 'application/x-dia-diagram';
