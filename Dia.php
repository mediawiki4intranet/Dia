<?php
/**
 * Setup for Dia extension, an extension that allows Dia (http://live.gnome.org/Dia) diagrams
 * to be rendered in MediaWiki pages.
 * Fixed for 1.16 + reformatted by Vitaliy Filippov, http://wiki.4intra.net/
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author Marcel Toele, dwarfhouse.org
 * @copyright © 2007 Marcel Toele
 * @licence GNU General Public Licence 2.0 or later
 */

if (!defined('MEDIAWIKI'))
{
    echo("This file is an extension to the MediaWiki software and cannot be used standalone.\n");
    die(1);
}

// Credits
$wgExtensionCredits['other'][] = array(
    'name'        => 'Dia',
    'author'      => 'Marcel Toele',
    'url'         => 'http://mediawiki.org/wiki/Extension:Dia',
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
// The nominal width of a Dia file when rendered to png
$wgDIANominalSize = 300;
// Don't scale a Dia file larger than this
$wgDIAMaxSize = 1024;

// Add the DiaHandler via the Autoload mechanism.
$wgMediaHandlers['application/x-dia-diagram'] = 'DiaHandler';
$wgAutoloadClasses['DiaHandler'] = dirname(__FILE__) . '/Dia.body.php';
$wgExtensionMessagesFiles['Dia'] = dirname(__FILE__) . '/Dia.i18n.php';
if (!in_array('dia', $wgFileExtensions))
    $wgFileExtensions[] = 'dia';
